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

    public function test_conversation_participant_can_create_ringing_call_session(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween($user, User::factory()->create());

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/call-sessions')
            ->assertOk()
            ->assertJsonPath('message', 'Call session ready.')
            ->assertJsonPath('data.conversation_id', $conversation->id)
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.started_by_id', $user->id)
            ->assertJsonPath('data.started_by.id', $user->id);

        $this->assertArrayNotHasKey('room_name', $response->json('data'));
        $this->assertArrayNotHasKey('provider', $response->json('data'));
        $this->assertArrayNotHasKey('domain', $response->json('data'));
        $this->assertArrayNotHasKey('script_url', $response->json('data'));
        $this->assertDatabaseHas('call_sessions', [
            'conversation_id' => $conversation->id,
            'started_by_id' => $user->id,
            'status' => 'ringing',
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

    public function test_duplicate_active_calls_across_conversations_are_prevented_for_same_user(): void
    {
        $user = User::factory()->create();
        $firstConversation = $this->conversationBetween($user, User::factory()->create());
        $secondConversation = $this->conversationBetween($user, User::factory()->create());

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$firstConversation->id.'/call-sessions')
            ->assertOk();

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$secondConversation->id.'/call-sessions')
            ->assertConflict()
            ->assertJsonPath('message', 'This user is currently busy on another call. Please try again later.')
            ->assertJsonPath('code', 'CALL_PARTICIPANT_BUSY');

        $this->assertSame(1, CallSession::count());
    }

    public function test_duplicate_active_calls_are_prevented_when_other_participant_is_busy(): void
    {
        $busy = User::factory()->create();
        $caller = User::factory()->create();
        $firstConversation = $this->conversationBetween($busy, User::factory()->create());
        $secondConversation = $this->conversationBetween($caller, $busy);
        $this->activeCallSession($firstConversation, $busy);

        $this
            ->actingAs($caller, 'sanctum')
            ->postJson('/api/conversations/'.$secondConversation->id.'/call-sessions')
            ->assertConflict()
            ->assertJsonPath('message', 'This user is currently busy on another call. Please try again later.')
            ->assertJsonPath('code', 'CALL_PARTICIPANT_BUSY');
    }

    public function test_participant_gets_active_call_session(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween($user, User::factory()->create());
        $callSession = $this->activeCallSession($conversation, $user);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/conversations/'.$conversation->id.'/call-sessions/active')
            ->assertOk()
            ->assertJsonPath('data.id', $callSession->id)
            ->assertJsonPath('data.conversation_id', $conversation->id)
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.started_by_id', $user->id);

        $this->assertArrayNotHasKey('room_name', $this->actingAs($user, 'sanctum')
            ->getJson('/api/conversations/'.$conversation->id.'/call-sessions/active')
            ->json('data'));
    }

    public function test_participant_gets_null_when_no_active_call_session_exists(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween($user, User::factory()->create());

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/conversations/'.$conversation->id.'/call-sessions/active')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_non_participant_cannot_get_active_call_session(): void
    {
        $outsider = User::factory()->create();
        $conversation = $this->conversationBetween(User::factory()->create(), User::factory()->create());
        $this->activeCallSession($conversation, User::find($conversation->user_one_id));

        $this
            ->actingAs($outsider, 'sanctum')
            ->getJson('/api/conversations/'.$conversation->id.'/call-sessions/active')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_accepted_call_session_is_returned_as_active(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween($user, User::factory()->create());
        $callSession = $this->activeCallSession($conversation, $user, 'accepted');

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/conversations/'.$conversation->id.'/call-sessions/active')
            ->assertOk()
            ->assertJsonPath('data.id', $callSession->id)
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.room_name', $callSession->room_name);
    }

    public function test_declined_and_ended_call_sessions_are_not_returned_as_active(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween($user, User::factory()->create());

        $this->activeCallSession($conversation, $user)->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);
        $this->activeCallSession($conversation, $user)->update([
            'status' => 'declined',
            'ended_at' => now(),
        ]);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/conversations/'.$conversation->id.'/call-sessions/active')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_unauthenticated_user_cannot_get_active_call_session(): void
    {
        $conversation = $this->conversationBetween(User::factory()->create(), User::factory()->create());

        $this
            ->getJson('/api/conversations/'.$conversation->id.'/call-sessions/active')
            ->assertUnauthorized();
    }

    public function test_callee_can_see_incoming_ringing_call_globally(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $property = $this->propertyFor($caller, [
            'title' => 'Palm Suite',
            'city' => 'Marrakech',
        ]);
        $conversation = $this->conversationBetween($caller, $callee, $property);
        $callSession = $this->activeCallSession($conversation, $caller);

        $this
            ->actingAs($callee, 'sanctum')
            ->getJson('/api/call-sessions/incoming')
            ->assertOk()
            ->assertJsonPath('data.id', $callSession->id)
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.conversation_id', $conversation->id)
            ->assertJsonPath('data.started_by_id', $caller->id)
            ->assertJsonPath('data.started_by.id', $caller->id)
            ->assertJsonPath('data.started_by.name', $caller->name)
            ->assertJsonPath('data.started_by.email', $caller->email)
            ->assertJsonPath('data.conversation.property.id', $property->id)
            ->assertJsonPath('data.conversation.property.title', 'Palm Suite')
            ->assertJsonPath('data.conversation.property.city', 'Marrakech');
    }

    public function test_caller_does_not_see_their_own_call_as_incoming(): void
    {
        $caller = User::factory()->create();
        $conversation = $this->conversationBetween($caller, User::factory()->create());
        $this->activeCallSession($conversation, $caller);

        $this
            ->actingAs($caller, 'sanctum')
            ->getJson('/api/call-sessions/incoming')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_unrelated_user_does_not_see_incoming_call(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = $this->conversationBetween($caller, $callee);
        $this->activeCallSession($conversation, $caller);

        $this
            ->actingAs($outsider, 'sanctum')
            ->getJson('/api/call-sessions/incoming')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_non_ringing_calls_are_not_returned_as_incoming(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $conversation = $this->conversationBetween($caller, $callee);

        foreach (['accepted', 'declined', 'ended'] as $status) {
            $callSession = $this->activeCallSession($conversation, $caller, $status);
            if (in_array($status, ['declined', 'ended'], true)) {
                $callSession->update(['ended_at' => now()]);
            }
        }

        $this
            ->actingAs($callee, 'sanctum')
            ->getJson('/api/call-sessions/incoming')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_stale_ringing_call_expires_as_missed_and_is_excluded_from_active_results(): void
    {
        config(['calls.ringing_timeout_seconds' => 45]);

        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $conversation = $this->conversationBetween($caller, $callee);
        $callSession = $this->activeCallSession($conversation, $caller);
        $callSession->update(['started_at' => now()->subSeconds(46)]);

        $this
            ->actingAs($callee, 'sanctum')
            ->getJson('/api/call-sessions/incoming')
            ->assertOk()
            ->assertJsonPath('data', null);

        $callSession->refresh();
        $this->assertSame('missed', $callSession->status);
        $this->assertNotNull($callSession->ended_at);

        $this
            ->actingAs($caller, 'sanctum')
            ->getJson('/api/conversations/'.$conversation->id.'/call-sessions/active')
            ->assertOk()
            ->assertJsonPath('data', null);
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

        $this->assertNull($roomName);

        $callSession = CallSession::first();
        $callSession->update(['status' => 'accepted']);

        $roomName = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/conversations/'.$conversation->id.'/call-sessions/active')
            ->assertOk()
            ->json('data.room_name');

        $this->assertNotSame((string) $conversation->id, $roomName);
        $this->assertFalse(preg_match('/^'.$conversation->id.'$/', $roomName) === 1);
    }

    public function test_same_ringing_conversation_call_request_reuses_existing_session(): void
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

        $first = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$firstConversation->id.'/call-sessions')
            ->assertOk()
            ->json('data.id');

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/call-sessions/'.$first.'/end')
            ->assertOk();

        $second = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$secondConversation->id.'/call-sessions')
            ->assertOk()
            ->json('data.id');

        $this->assertNotSame($first, $second);
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
        $this->assertArrayNotHasKey('provider', $response->json('data'));
        $this->assertArrayNotHasKey('domain', $response->json('data'));
        $this->assertArrayNotHasKey('script_url', $response->json('data'));
    }

    public function test_callee_can_accept_ringing_call_session(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $conversation = $this->conversationBetween($caller, $callee);
        $callSession = $this->activeCallSession($conversation, $caller);

        $this
            ->actingAs($callee, 'sanctum')
            ->postJson('/api/call-sessions/'.$callSession->id.'/accept')
            ->assertOk()
            ->assertJsonPath('message', 'Call session accepted.')
            ->assertJsonPath('data.status', 'accepted');

        $this->assertSame('accepted', $callSession->refresh()->status);
    }

    public function test_invalid_state_transitions_return_conflict(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $conversation = $this->conversationBetween($caller, $callee);

        $declined = $this->activeCallSession($conversation, $caller, 'declined');
        $declined->update(['ended_at' => now()]);
        $this
            ->actingAs($callee, 'sanctum')
            ->postJson('/api/call-sessions/'.$declined->id.'/accept')
            ->assertConflict()
            ->assertJsonPath('message', 'This call can no longer be accepted.');

        $accepted = $this->activeCallSession($conversation, $caller, 'accepted');
        $this
            ->actingAs($callee, 'sanctum')
            ->postJson('/api/call-sessions/'.$accepted->id.'/decline')
            ->assertConflict()
            ->assertJsonPath('message', 'This call can no longer be declined.');

        $ended = $this->activeCallSession($conversation, $caller, 'ended');
        $ended->update(['ended_at' => now()]);
        $this
            ->actingAs($caller, 'sanctum')
            ->postJson('/api/call-sessions/'.$ended->id.'/end')
            ->assertConflict()
            ->assertJsonPath('message', 'This call has already finished.');
    }

    public function test_caller_cannot_accept_their_own_call_session(): void
    {
        $caller = User::factory()->create();
        $conversation = $this->conversationBetween($caller, User::factory()->create());
        $callSession = $this->activeCallSession($conversation, $caller);

        $this
            ->actingAs($caller, 'sanctum')
            ->postJson('/api/call-sessions/'.$callSession->id.'/accept')
            ->assertForbidden()
            ->assertJsonPath('message', 'Only the recipient can accept this call.');

        $this->assertSame('ringing', $callSession->refresh()->status);
    }

    public function test_callee_can_decline_ringing_call_session(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $conversation = $this->conversationBetween($caller, $callee);
        $callSession = $this->activeCallSession($conversation, $caller);

        $this
            ->actingAs($callee, 'sanctum')
            ->postJson('/api/call-sessions/'.$callSession->id.'/decline')
            ->assertOk()
            ->assertJsonPath('message', 'Call session declined.')
            ->assertJsonPath('data.status', 'declined');

        $callSession->refresh();
        $this->assertSame('declined', $callSession->status);
        $this->assertNotNull($callSession->ended_at);
    }

    public function test_caller_can_cancel_or_end_call_session(): void
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

    public function test_non_participant_cannot_accept_decline_or_end_call_session(): void
    {
        $outsider = User::factory()->create();
        $conversation = $this->conversationBetween(User::factory()->create(), User::factory()->create());
        $callSession = $this->activeCallSession($conversation, User::find($conversation->user_one_id));

        $this
            ->actingAs($outsider, 'sanctum')
            ->postJson('/api/call-sessions/'.$callSession->id.'/accept')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $this
            ->actingAs($outsider, 'sanctum')
            ->postJson('/api/call-sessions/'.$callSession->id.'/decline')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $this
            ->actingAs($outsider, 'sanctum')
            ->postJson('/api/call-sessions/'.$callSession->id.'/end')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $this->assertSame('ringing', $callSession->refresh()->status);
        $this->assertNull($callSession->ended_at);
    }

    public function test_after_ending_creating_call_session_again_creates_new_ringing_room(): void
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
        $this->assertSame(2, CallSession::where('conversation_id', $conversation->id)->count());
        $this->assertSame('ringing', $second->json('data.status'));
    }

    public function test_starting_calls_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $conversation = $this->conversationBetween($user, User::factory()->create());
            $response = $this
                ->actingAs($user, 'sanctum')
                ->postJson('/api/conversations/'.$conversation->id.'/call-sessions');

            if ($response->isOk()) {
                CallSession::find($response->json('data.id'))?->update([
                    'status' => 'ended',
                    'ended_at' => now(),
                ]);
            }
        }

        $conversation = $this->conversationBetween($user, User::factory()->create());

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations/'.$conversation->id.'/call-sessions')
            ->assertTooManyRequests();
    }

    public function test_call_session_response_contains_only_safe_fields(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $conversation = $this->conversationBetween($caller, $callee);
        $callSession = $this->activeCallSession($conversation, $caller, 'accepted');

        $payload = $this
            ->actingAs($callee, 'sanctum')
            ->getJson('/api/conversations/'.$conversation->id.'/call-sessions/active')
            ->assertOk()
            ->json('data');

        $this->assertSame($callSession->room_name, $payload['room_name']);
        $this->assertArrayNotHasKey('provider', $payload);
        $this->assertArrayNotHasKey('domain', $payload);
        $this->assertArrayNotHasKey('script_url', $payload);
        $this->assertArrayNotHasKey('password', $payload['started_by']);
        $this->assertArrayNotHasKey('remember_token', $payload['started_by']);
    }

    private function conversationBetween(User $first, User $second, ?Property $property = null): Conversation
    {
        return Conversation::create([
            'property_id' => $property?->id,
            'user_one_id' => $first->id,
            'user_two_id' => $second->id,
        ]);
    }

    private function activeCallSession(Conversation $conversation, User $startedBy, string $status = 'ringing'): CallSession
    {
        return CallSession::create([
            'conversation_id' => $conversation->id,
            'started_by_id' => $startedBy->id,
            'provider' => 'community',
            'domain' => 'kmeet.infomaniak.com',
            'room_name' => 'luxurrstay-test-'.uniqid(),
            'status' => $status,
            'started_at' => now(),
        ]);
    }

    private function propertyFor(User $user, array $overrides = []): Property
    {
        return Property::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Owner Suite',
            'description' => 'A calm test property.',
            'type' => 'apartment',
            'price_per_night' => 250,
            'city' => 'Marrakech',
            'address' => 'Medina',
        ], $overrides));
    }
}
