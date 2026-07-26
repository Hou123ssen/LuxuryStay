<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\Conversation;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_create_call_session(): void
    {
        $conversation = $this->conversationBetween(User::factory()->create(), User::factory()->create());

        $this
            ->postJson('/api/conversations/'.$conversation->id.'/call-sessions')
            ->assertUnauthorized();
    }

    public function test_conversation_participant_can_create_call_session(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween($user, User::factory()->create());

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/call-sessions')
            ->assertOk()
            ->assertJsonPath('message', 'Call session ready.')
            ->assertJsonPath('data.conversation_id', $conversation->id)
            ->assertJsonPath('data.provider', 'community')
            ->assertJsonPath('data.domain', 'kmeet.infomaniak.com')
            ->assertJsonPath('data.script_url', 'https://kmeet.infomaniak.com/external_api.js')
            ->assertJsonPath('data.audio_only', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.started_by_id', $user->id);

        $this->assertNotEmpty($response->json('data.room_name'));
        $this->assertDatabaseHas('call_sessions', [
            'conversation_id' => $conversation->id,
            'started_by_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_non_participant_cannot_create_call_session(): void
    {
        $outsider = User::factory()->create();
        $conversation = $this->conversationBetween(User::factory()->create(), User::factory()->create());

        $this
            ->actingAs($outsider, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/call-sessions')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_room_name_is_not_predictable_from_conversation_id(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween($user, User::factory()->create());

        $roomName = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/call-sessions')
            ->assertOk()
            ->json('data.room_name');

        $this->assertNotSame((string) $conversation->id, $roomName);
        $this->assertFalse(preg_match('/^'.$conversation->id.'$/', $roomName) === 1);
    }

    public function test_same_active_conversation_call_request_reuses_existing_session(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween($user, User::factory()->create());

        $first = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/call-sessions')
            ->assertOk();

        $second = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/call-sessions')
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame($first->json('data.room_name'), $second->json('data.room_name'));
        $this->assertSame(1, CallSession::where('conversation_id', $conversation->id)->count());
    }

    public function test_different_conversations_get_different_room_names(): void
    {
        $user = User::factory()->create();
        $firstConversation = $this->conversationBetween($user, User::factory()->create());
        $secondConversation = $this->conversationBetween($user, User::factory()->create());

        $firstRoom = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$firstConversation->id.'/call-sessions')
            ->assertOk()
            ->json('data.room_name');

        $secondRoom = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$secondConversation->id.'/call-sessions')
            ->assertOk()
            ->json('data.room_name');

        $this->assertNotSame($firstRoom, $secondRoom);
    }

    public function test_client_cannot_override_room_or_provider_configuration(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween($user, User::factory()->create());

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/call-sessions', [
                'room_name' => 'conversation-'.$conversation->id,
                'provider' => 'evil',
                'domain' => 'example.test',
                'script_url' => 'https://example.test/external_api.js',
            ])
            ->assertOk();

        $this->assertNotSame('conversation-'.$conversation->id, $response->json('data.room_name'));
        $this->assertSame('community', $response->json('data.provider'));
        $this->assertSame('kmeet.infomaniak.com', $response->json('data.domain'));
        $this->assertSame('https://kmeet.infomaniak.com/external_api.js', $response->json('data.script_url'));
    }

    public function test_participant_can_end_call_session(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween($user, User::factory()->create());
        $callSession = $this->activeCallSession($conversation, $user);

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/call-sessions/'.$callSession->id.'/end')
            ->assertOk()
            ->assertJsonPath('message', 'Call session ended.')
            ->assertJsonPath('data.status', 'ended');

        $this->assertDatabaseHas('call_sessions', [
            'id' => $callSession->id,
            'status' => 'ended',
        ]);
        $this->assertNotNull($callSession->refresh()->ended_at);
    }

    public function test_non_participant_cannot_end_call_session(): void
    {
        $outsider = User::factory()->create();
        $conversation = $this->conversationBetween(User::factory()->create(), User::factory()->create());
        $callSession = $this->activeCallSession($conversation, User::find($conversation->user_one_id));

        $this
            ->actingAs($outsider, 'sanctum')
            ->postJson('/api/call-sessions/'.$callSession->id.'/end')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $this->assertSame('active', $callSession->refresh()->status);
        $this->assertNull($callSession->ended_at);
    }

    public function test_after_ending_creating_call_session_again_creates_new_active_room(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween($user, User::factory()->create());

        $first = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/call-sessions')
            ->assertOk();

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/call-sessions/'.$first->json('data.id').'/end')
            ->assertOk();

        $second = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/call-sessions')
            ->assertOk();

        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertNotSame($first->json('data.room_name'), $second->json('data.room_name'));
        $this->assertSame(2, CallSession::where('conversation_id', $conversation->id)->count());
        $this->assertSame('active', $second->json('data.status'));
    }

    private function conversationBetween(User $first, User $second, ?Property $property = null): Conversation
    {
        return Conversation::create([
            'property_id' => $property?->id,
            'user_one_id' => $first->id,
            'user_two_id' => $second->id,
        ]);
    }

    private function activeCallSession(Conversation $conversation, User $startedBy): CallSession
    {
        return CallSession::create([
            'conversation_id' => $conversation->id,
            'started_by_id' => $startedBy->id,
            'provider' => 'community',
            'domain' => 'kmeet.infomaniak.com',
            'room_name' => 'luxurrstay-test-'.uniqid(),
            'status' => 'active',
            'started_at' => now(),
        ]);
    }
}
