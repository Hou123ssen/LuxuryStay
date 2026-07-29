<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationRead;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NavbarCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_get_navbar_counts(): void
    {
        $this->getJson('/api/navbar-counts')->assertUnauthorized();
    }

    public function test_unread_message_count_sums_across_conversations_and_excludes_own_messages(): void
    {
        $user = User::factory()->create();
        $firstOther = User::factory()->create();
        $secondOther = User::factory()->create();
        $stranger = User::factory()->create();

        $firstConversation = $this->conversationBetween($user, $firstOther);
        $secondConversation = $this->conversationBetween($secondOther, $user);
        $strangerConversation = $this->conversationBetween($firstOther, $stranger);

        $this->message($firstConversation, $firstOther, 'First from other');
        $this->message($firstConversation, $firstOther, 'Second from other');
        $this->message($firstConversation, $user, 'Own reply');
        $this->message($secondConversation, $secondOther, 'Third from other');
        $this->message($strangerConversation, $firstOther, 'Not for user');

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/navbar-counts')
            ->assertOk()
            ->assertJsonPath('data.unread_messages_count', 3)
            ->assertJsonPath('data.unread_notifications_count', 0);
    }

    public function test_read_conversations_no_longer_contribute_to_navbar_message_count(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationBetween($user, $other);

        Carbon::setTestNow('2026-07-29 09:00:00');
        $this->message($conversation, $other, 'Already read');

        Carbon::setTestNow('2026-07-29 09:05:00');
        ConversationRead::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'last_read_at' => now(),
        ]);

        Carbon::setTestNow('2026-07-29 09:10:00');
        $this->message($conversation, $user, 'Own message after read');
        Carbon::setTestNow();

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/navbar-counts')
            ->assertOk()
            ->assertJsonPath('data.unread_messages_count', 0);
    }

    public function test_unread_notification_count_excludes_read_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'message' => 'Unread one',
        ]);
        Notification::create([
            'user_id' => $user->id,
            'message' => 'Unread two',
        ]);
        Notification::create([
            'user_id' => $user->id,
            'message' => 'Read one',
            'read' => now(),
        ]);
        Notification::create([
            'user_id' => $other->id,
            'message' => 'Someone else unread',
        ]);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/navbar-counts')
            ->assertOk()
            ->assertJsonPath('data.unread_messages_count', 0)
            ->assertJsonPath('data.unread_notifications_count', 2);
    }

    public function test_booking_request_notification_contributes_to_unread_notification_count(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->propertyFor($owner);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings', [
                'property_id' => $property->id,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-03',
            ])
            ->assertCreated();

        $this
            ->actingAs($owner, 'sanctum')
            ->getJson('/api/navbar-counts')
            ->assertOk()
            ->assertJsonPath('data.unread_notifications_count', 1);

        $this
            ->actingAs($guest, 'sanctum')
            ->getJson('/api/navbar-counts')
            ->assertOk()
            ->assertJsonPath('data.unread_notifications_count', 1);
    }

    public function test_notification_counts_are_scoped_to_authenticated_user_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'message' => 'User unread',
        ]);
        Notification::create([
            'user_id' => $other->id,
            'message' => 'Other unread one',
        ]);
        Notification::create([
            'user_id' => $other->id,
            'message' => 'Other unread two',
        ]);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/navbar-counts')
            ->assertOk()
            ->assertJsonPath('data.unread_notifications_count', 1);

        $this
            ->actingAs($other, 'sanctum')
            ->getJson('/api/navbar-counts')
            ->assertOk()
            ->assertJsonPath('data.unread_notifications_count', 2);
    }

    public function test_legacy_false_notification_read_value_is_treated_as_unread(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'message' => 'Legacy unread',
            'read' => false,
        ]);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/navbar-counts')
            ->assertOk()
            ->assertJsonPath('data.unread_notifications_count', 1);
    }

    public function test_mark_all_notifications_as_read_makes_navbar_notification_count_zero(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'message' => 'Unread one',
        ]);
        Notification::create([
            'user_id' => $user->id,
            'message' => 'Unread two',
        ]);

        $this
            ->actingAs($user, 'sanctum')
            ->putJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('message', 'Notifications marked as read.')
            ->assertJsonPath('unread_notifications_count', 0);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/navbar-counts')
            ->assertOk()
            ->assertJsonPath('data.unread_notifications_count', 0);
    }

    public function test_marking_one_notification_as_read_decreases_navbar_notification_count(): void
    {
        $user = User::factory()->create();

        $first = Notification::create([
            'user_id' => $user->id,
            'message' => 'Unread one',
        ]);
        Notification::create([
            'user_id' => $user->id,
            'message' => 'Unread two',
        ]);

        $this
            ->actingAs($user, 'sanctum')
            ->putJson('/api/notifications/'.$first->id.'/read')
            ->assertOk()
            ->assertJsonPath('message', 'Notification marked as read.')
            ->assertJsonPath('unread_notifications_count', 1);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/navbar-counts')
            ->assertOk()
            ->assertJsonPath('data.unread_notifications_count', 1);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $other->id,
            'message' => 'Other unread',
        ]);

        $this
            ->actingAs($user, 'sanctum')
            ->putJson('/api/notifications/'.$notification->id.'/read')
            ->assertNotFound();

        $this->assertNull($notification->refresh()->read);
    }

    public function test_unauthenticated_user_cannot_mark_notifications_as_read(): void
    {
        $notification = Notification::create([
            'user_id' => User::factory()->create()->id,
            'message' => 'Unread',
        ]);

        $this->putJson('/api/notifications/read-all')->assertUnauthorized();
        $this->putJson('/api/notifications/'.$notification->id.'/read')->assertUnauthorized();
    }

    public function test_read_all_route_marks_notifications_and_does_not_hit_parameter_route(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $first = Notification::create([
            'user_id' => $user->id,
            'message' => 'Unread one',
        ]);
        $second = Notification::create([
            'user_id' => $user->id,
            'message' => 'Unread two',
        ]);
        $otherNotification = Notification::create([
            'user_id' => $other->id,
            'message' => 'Other unread',
        ]);

        $this
            ->actingAs($user, 'sanctum')
            ->putJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('message', 'Notifications marked as read.')
            ->assertJsonPath('unread_notifications_count', 0);

        $this->assertNotNull($first->refresh()->read);
        $this->assertNotNull($second->refresh()->read);
        $this->assertNull($otherNotification->refresh()->read);
    }

    private function conversationBetween(User $first, User $second): Conversation
    {
        return Conversation::create([
            'user_one_id' => $first->id,
            'user_two_id' => $second->id,
        ]);
    }

    private function message(Conversation $conversation, User $sender, string $message): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => $message,
        ]);
    }

    private function propertyFor(User $user): Property
    {
        return Property::create([
            'user_id' => $user->id,
            'title' => 'Mansoria',
            'description' => 'A calm test property.',
            'type' => 'apartment',
            'price_per_night' => 250,
            'city' => 'Marrakech',
            'address' => 'Medina',
        ]);
    }
}
