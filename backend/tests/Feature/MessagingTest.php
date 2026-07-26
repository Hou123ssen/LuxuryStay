<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_conversations(): void
    {
        $this->getJson('/api/conversations')->assertUnauthorized();
    }

    public function test_authenticated_user_lists_only_own_conversations(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $stranger = User::factory()->create();

        $ownConversation = Conversation::create([
            'user_one_id' => $user->id,
            'user_two_id' => $other->id,
        ]);
        $otherConversation = Conversation::create([
            'user_one_id' => $other->id,
            'user_two_id' => $stranger->id,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/conversations')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($ownConversation->id));
        $this->assertFalse($ids->contains($otherConversation->id));
    }

    public function test_authenticated_user_can_create_conversation_by_property_id(): void
    {
        $guest = User::factory()->create();
        $host = User::factory()->create();
        $property = $this->propertyFor($host, [
            'title' => 'Mansoria',
            'city' => 'Casablanca',
        ]);

        $response = $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/conversations', [
                'property_id' => $property->id,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('property_id', $property->id)
            ->assertJsonPath('property.id', $property->id)
            ->assertJsonPath('property.title', 'Mansoria')
            ->assertJsonPath('property.city', 'Casablanca')
            ->assertJsonPath('other_user.id', $host->id);

        $this->assertDatabaseHas('conversations', [
            'property_id' => $property->id,
            'user_one_id' => $guest->id,
            'user_two_id' => $host->id,
        ]);
    }

    public function test_property_owner_cannot_start_conversation_about_own_property(): void
    {
        $host = User::factory()->create();
        $property = $this->propertyFor($host);

        $this
            ->actingAs($host, 'sanctum')
            ->postJson('/api/conversations', [
                'property_id' => $property->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot start a conversation about your own property.');

        $this->assertDatabaseMissing('conversations', [
            'property_id' => $property->id,
            'user_one_id' => $host->id,
            'user_two_id' => $host->id,
        ]);
    }

    public function test_creating_same_property_conversation_twice_returns_existing_conversation(): void
    {
        $guest = User::factory()->create();
        $host = User::factory()->create();
        $property = $this->propertyFor($host);

        $first = $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/conversations', [
                'property_id' => $property->id,
            ])
            ->assertCreated();

        $second = $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/conversations', [
                'property_id' => $property->id,
            ])
            ->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, Conversation::where('property_id', $property->id)->count());
    }

    public function test_same_guest_and_host_can_have_separate_conversations_for_different_properties(): void
    {
        $guest = User::factory()->create();
        $host = User::factory()->create();
        $firstProperty = $this->propertyFor($host, ['title' => 'First Suite']);
        $secondProperty = $this->propertyFor($host, ['title' => 'Second Suite']);

        $first = $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/conversations', [
                'property_id' => $firstProperty->id,
            ])
            ->assertCreated();

        $second = $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/conversations', [
                'property_id' => $secondProperty->id,
            ])
            ->assertCreated();

        $this->assertNotSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseHas('conversations', ['property_id' => $firstProperty->id]);
        $this->assertDatabaseHas('conversations', ['property_id' => $secondProperty->id]);
    }

    public function test_user_cannot_read_another_users_conversation_messages(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween(
            User::factory()->create(),
            User::factory()->create()
        );

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $conversation->user_one_id,
            'message' => 'Private message',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/messages/'.$conversation->id);

        $response
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
        $this->assertResponseDoesNotExposeInternals($response->getContent());
    }

    public function test_user_can_read_messages_from_own_conversation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationBetween($user, $other);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $other->id,
            'message' => 'Welcome',
        ]);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/messages/'.$conversation->id)
            ->assertOk()
            ->assertJsonPath('data.0.message', 'Welcome')
            ->assertJsonPath('data.0.conversation_id', $conversation->id);
    }

    public function test_participant_can_read_messages_from_property_scoped_conversation(): void
    {
        $guest = User::factory()->create();
        $host = User::factory()->create();
        $property = $this->propertyFor($host);
        $conversation = $this->conversationBetween($guest, $host, $property);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $host->id,
            'message' => 'About the property',
        ]);

        $this
            ->actingAs($guest, 'sanctum')
            ->getJson('/api/messages/'.$conversation->id)
            ->assertOk()
            ->assertJsonPath('data.0.message', 'About the property');
    }

    public function test_user_cannot_send_message_to_conversation_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween(
            User::factory()->create(),
            User::factory()->create()
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/messages', [
                'conversation_id' => $conversation->id,
                'body' => 'I should not be here.',
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conversation->id,
            'message' => 'I should not be here.',
        ]);
        $this->assertResponseDoesNotExposeInternals($response->getContent());
    }

    public function test_user_can_send_message_to_own_conversation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationBetween($user, $other);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/messages', [
                'conversation_id' => $conversation->id,
                'body' => 'Hello host.',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('conversation_id', $conversation->id)
            ->assertJsonPath('sender_id', $user->id)
            ->assertJsonPath('message', 'Hello host.');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'message' => 'Hello host.',
        ]);
    }

    public function test_participant_can_send_message_to_property_scoped_conversation(): void
    {
        $guest = User::factory()->create();
        $host = User::factory()->create();
        $property = $this->propertyFor($host);
        $conversation = $this->conversationBetween($guest, $host, $property);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/messages', [
                'conversation_id' => $conversation->id,
                'body' => 'Is this property available?',
            ])
            ->assertCreated()
            ->assertJsonPath('conversation_id', $conversation->id)
            ->assertJsonPath('sender_id', $guest->id)
            ->assertJsonPath('message', 'Is this property available?');
    }

    public function test_non_participant_cannot_read_property_scoped_conversation(): void
    {
        $guest = User::factory()->create();
        $host = User::factory()->create();
        $outsider = User::factory()->create();
        $property = $this->propertyFor($host);
        $conversation = $this->conversationBetween($guest, $host, $property);

        $this
            ->actingAs($outsider, 'sanctum')
            ->getJson('/api/messages/'.$conversation->id)
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_non_participant_cannot_send_to_property_scoped_conversation(): void
    {
        $guest = User::factory()->create();
        $host = User::factory()->create();
        $outsider = User::factory()->create();
        $property = $this->propertyFor($host);
        $conversation = $this->conversationBetween($guest, $host, $property);

        $this
            ->actingAs($outsider, 'sanctum')
            ->postJson('/api/messages', [
                'conversation_id' => $conversation->id,
                'body' => 'Not my thread.',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conversation->id,
            'message' => 'Not my thread.',
        ]);
    }

    public function test_client_submitted_sender_id_cannot_spoof_sender(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationBetween($user, $other);

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/messages', [
                'conversation_id' => $conversation->id,
                'sender_id' => $other->id,
                'body' => 'Spoof attempt.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sender_id']);

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $other->id,
            'message' => 'Spoof attempt.',
        ]);
    }

    public function test_empty_message_is_rejected(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween($user, User::factory()->create());

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/messages', [
                'conversation_id' => $conversation->id,
                'body' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_whitespace_only_message_is_rejected(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween($user, User::factory()->create());

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/messages', [
                'conversation_id' => $conversation->id,
                'body' => '   ',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_oversized_message_is_rejected(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween($user, User::factory()->create());

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/messages', [
                'conversation_id' => $conversation->id,
                'body' => str_repeat('a', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_self_conversation_creation_is_rejected(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations', [
                'other_user_id' => $user->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot start a conversation with yourself.');
    }

    public function test_creating_conversation_with_missing_user_is_rejected(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/conversations', [
                'other_user_id' => 999999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['other_user_id']);
    }

    public function test_messaging_errors_do_not_expose_raw_exception_details(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversationBetween(
            User::factory()->create(),
            User::factory()->create()
        );

        $readResponse = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/messages/'.$conversation->id);

        $sendResponse = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/messages', [
                'conversation_id' => $conversation->id,
                'body' => 'No leak please.',
            ]);

        $this->assertResponseDoesNotExposeInternals($readResponse->getContent());
        $this->assertResponseDoesNotExposeInternals($sendResponse->getContent());
    }

    private function conversationBetween(User $first, User $second, ?Property $property = null): Conversation
    {
        return Conversation::create([
            'property_id' => $property?->id,
            'user_one_id' => $first->id,
            'user_two_id' => $second->id,
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

    private function assertResponseDoesNotExposeInternals(string $content): void
    {
        $this->assertStringNotContainsString('"exception"', $content);
        $this->assertStringNotContainsString('"file"', $content);
        $this->assertStringNotContainsString('"trace"', $content);
        $this->assertStringNotContainsString('"line"', $content);
        $this->assertStringNotContainsString('C:\\', $content);
        $this->assertStringNotContainsString('vendor\\', $content);
    }
}
