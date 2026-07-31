<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\ReviewModerationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_admin_review_routes(): void
    {
        $review = $this->reviewFor();

        $this->getJson('/api/admin/reviews')->assertUnauthorized();
        $this->getJson('/api/admin/reviews/'.$review->id)->assertUnauthorized();
        $this->putJson('/api/admin/reviews/'.$review->id.'/publish')->assertUnauthorized();
        $this->putJson('/api/admin/reviews/'.$review->id.'/reject')->assertUnauthorized();
    }

    public function test_normal_user_and_property_owner_cannot_list_admin_reviews(): void
    {
        $review = $this->reviewFor();
        $normalUser = User::factory()->create();
        $propertyOwner = $review->property->user;

        $this
            ->actingAs($normalUser, 'sanctum')
            ->getJson('/api/admin/reviews')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $this
            ->actingAs($propertyOwner, 'sanctum')
            ->getJson('/api/admin/reviews')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_admin_can_list_reviews_with_safe_moderation_context(): void
    {
        $admin = $this->admin();
        $review = $this->reviewFor([
            'status' => Review::STATUS_PENDING_REVIEW,
            'risk_score' => 85,
            'risk_reasons' => ['burst_pattern'],
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.id', $review->id)
            ->assertJsonPath('data.0.status', Review::STATUS_PENDING_REVIEW)
            ->assertJsonPath('data.0.risk_score', 85)
            ->assertJsonPath('data.0.risk_reasons.0', 'burst_pattern')
            ->assertJsonPath('data.0.user.id', $review->user_id)
            ->assertJsonPath('data.0.user.name', $review->user->name)
            ->assertJsonPath('data.0.property.id', $review->property_id)
            ->assertJsonPath('data.0.property.title', $review->property->title)
            ->assertJsonPath('data.0.booking.id', $review->booking_id);

        $payload = $response->json('data.0');
        $this->assertArrayNotHasKey('ip_hash', $payload);
        $this->assertArrayNotHasKey('user_agent_hash', $payload);
        $this->assertArrayNotHasKey('moderation_logs', $payload);
        $this->assertArrayNotHasKey('email', $payload['user']);
    }

    public function test_admin_can_filter_reviews_by_status_rating_property_user_and_high_risk(): void
    {
        $admin = $this->admin();
        $matching = $this->reviewFor([
            'status' => Review::STATUS_PENDING_REVIEW,
            'rating' => 2,
            'risk_score' => config('reviews.risk.high_risk_threshold'),
        ]);
        $this->reviewFor([
            'status' => Review::STATUS_PUBLISHED,
            'rating' => 5,
            'risk_score' => 0,
        ]);

        $query = http_build_query([
            'status' => Review::STATUS_PENDING_REVIEW,
            'rating' => 2,
            'property_id' => $matching->property_id,
            'user_id' => $matching->user_id,
            'risk_level' => 'high',
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reviews?'.$query)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);
    }

    public function test_admin_can_view_one_review(): void
    {
        $admin = $this->admin();
        $review = $this->reviewFor();

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reviews/'.$review->id)
            ->assertOk()
            ->assertJsonPath('data.id', $review->id)
            ->assertJsonPath('data.booking.start_date', $review->booking->start_date->toJSON())
            ->assertJsonPath('data.booking.end_date', $review->booking->end_date->toJSON())
            ->assertJsonMissingPath('data.ip_hash')
            ->assertJsonMissingPath('data.user_agent_hash');
    }

    public function test_admin_can_publish_pending_review_and_audit_it(): void
    {
        $admin = $this->admin();
        $review = $this->reviewFor([
            'status' => Review::STATUS_PENDING_REVIEW,
            'published_at' => null,
            'risk_score' => 80,
            'risk_reasons' => ['test_signal'],
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reviews/'.$review->id.'/publish', [
                'reason' => 'Evidence checked.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Review published.')
            ->assertJsonPath('data.status', Review::STATUS_PUBLISHED)
            ->assertJsonPath('data.moderated_by', $admin->id);

        $review->refresh();
        $this->assertSame(Review::STATUS_PUBLISHED, $review->status);
        $this->assertSame($admin->id, $review->moderated_by);
        $this->assertNotNull($review->moderated_at);
        $this->assertNotNull($review->published_at);

        $this->assertAuditLog($review, ReviewModerationLog::ACTION_MODERATOR_PUBLISHED, $admin, 'Evidence checked.');
        $this->assertAuditLog($review, ReviewModerationLog::ACTION_STATUS_CHANGED, $admin, 'Evidence checked.', [
            'old_status' => Review::STATUS_PENDING_REVIEW,
            'new_status' => Review::STATUS_PUBLISHED,
        ]);
    }

    public function test_admin_can_reject_pending_review_and_audit_it(): void
    {
        $admin = $this->admin();
        $review = $this->reviewFor([
            'status' => Review::STATUS_PENDING_REVIEW,
            'published_at' => null,
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reviews/'.$review->id.'/reject', [
                'reason' => 'Does not meet review standards.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Review rejected.')
            ->assertJsonPath('data.status', Review::STATUS_REJECTED)
            ->assertJsonPath('data.published_at', null);

        $review->refresh();
        $this->assertSame(Review::STATUS_REJECTED, $review->status);
        $this->assertSame($admin->id, $review->moderated_by);
        $this->assertNotNull($review->moderated_at);
        $this->assertNull($review->published_at);

        $this->assertAuditLog($review, ReviewModerationLog::ACTION_MODERATOR_REJECTED, $admin, 'Does not meet review standards.');
        $this->assertAuditLog($review, ReviewModerationLog::ACTION_STATUS_CHANGED, $admin, 'Does not meet review standards.', [
            'old_status' => Review::STATUS_PENDING_REVIEW,
            'new_status' => Review::STATUS_REJECTED,
        ]);
    }

    public function test_admin_can_reject_published_review(): void
    {
        $admin = $this->admin();
        $review = $this->reviewFor([
            'status' => Review::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reviews/'.$review->id.'/reject')
            ->assertOk()
            ->assertJsonPath('data.status', Review::STATUS_REJECTED)
            ->assertJsonPath('data.published_at', null);

        $review->refresh();
        $this->assertSame(Review::STATUS_REJECTED, $review->status);
        $this->assertNull($review->published_at);
    }

    public function test_rejected_review_cannot_be_moderated_again(): void
    {
        $admin = $this->admin();
        $review = $this->reviewFor(['status' => Review::STATUS_REJECTED]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reviews/'.$review->id.'/publish')
            ->assertConflict()
            ->assertJsonPath('message', 'This review has already been rejected.');
    }

    public function test_publishing_already_published_review_returns_conflict(): void
    {
        $admin = $this->admin();
        $review = $this->reviewFor(['status' => Review::STATUS_PUBLISHED]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reviews/'.$review->id.'/publish')
            ->assertConflict()
            ->assertJsonPath('message', 'This review is already published.');
    }

    public function test_public_api_does_not_expose_review_moderation_or_risk_fields(): void
    {
        $viewer = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        $this->reviewFor([
            'property_id' => $property->id,
            'status' => Review::STATUS_PUBLISHED,
            'risk_score' => 70,
            'risk_reasons' => ['internal_reason'],
            'moderated_by' => $this->admin()->id,
            'moderated_at' => now(),
        ]);

        $indexPayload = $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/properties')
            ->assertOk()
            ->json('data.0');

        $detail = $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonMissingPath('reviews.0.risk_score')
            ->assertJsonMissingPath('reviews.0.risk_reasons')
            ->assertJsonMissingPath('reviews.0.ip_hash')
            ->assertJsonMissingPath('reviews.0.user_agent_hash')
            ->assertJsonMissingPath('reviews.0.moderated_by')
            ->assertJsonMissingPath('reviews.0.moderated_at')
            ->assertJsonMissingPath('reviews.0.moderation_logs');

        $this->assertArrayNotHasKey('risk_score', $indexPayload);
        $this->assertArrayNotHasKey('risk_reasons', $indexPayload);
        $this->assertArrayNotHasKey('moderated_by', $indexPayload);
        $this->assertArrayNotHasKey('moderated_at', $indexPayload);
        $this->assertNotEmpty($detail->json('reviews'));
    }

    public function test_publishing_pending_review_makes_it_affect_public_rating_counts(): void
    {
        $admin = $this->admin();
        $viewer = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        $review = $this->reviewFor([
            'property_id' => $property->id,
            'rating' => 5,
            'status' => Review::STATUS_PENDING_REVIEW,
            'published_at' => null,
        ]);

        $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('reviews_count', 0)
            ->assertJsonPath('average_rating', null);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reviews/'.$review->id.'/publish')
            ->assertOk();

        $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('reviews_count', 1)
            ->assertJsonPath('average_rating', 5);
    }

    public function test_rejecting_published_review_removes_it_from_public_rating_counts(): void
    {
        $admin = $this->admin();
        $viewer = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        $review = $this->reviewFor([
            'property_id' => $property->id,
            'rating' => 5,
            'status' => Review::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('reviews_count', 1)
            ->assertJsonPath('average_rating', 5);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reviews/'.$review->id.'/reject')
            ->assertOk();

        $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('reviews_count', 0)
            ->assertJsonPath('average_rating', null);
    }

    public function test_trust_badge_rules_remain_unchanged_after_review_moderation(): void
    {
        $admin = $this->admin();
        $viewer = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        $targetReview = null;

        for ($i = 0; $i < 10; $i++) {
            $review = $this->reviewFor([
                'property_id' => $property->id,
                'rating' => 5,
                'status' => Review::STATUS_PUBLISHED,
                'published_at' => now()->subDays(2),
            ]);

            $targetReview ??= $review;
        }

        $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('trust_badge', 'trusted');

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reviews/'.$targetReview->id.'/reject')
            ->assertOk();

        $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('reviews_count', 9)
            ->assertJsonPath('trust_badge', null);
    }

    private function assertAuditLog(
        Review $review,
        string $action,
        User $admin,
        ?string $reason,
        array $metadata = []
    ): void {
        $log = ReviewModerationLog::where('review_id', $review->id)
            ->where('action', $action)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame($reason, $log->reason);
        $this->assertArrayNotHasKey('ip_hash', $log->metadata ?? []);
        $this->assertArrayNotHasKey('user_agent_hash', $log->metadata ?? []);

        foreach ($metadata as $key => $value) {
            $this->assertSame($value, $log->metadata[$key] ?? null);
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function reviewFor(array $attributes = []): Review
    {
        $property = isset($attributes['property_id'])
            ? Property::findOrFail($attributes['property_id'])
            : $this->propertyFor(User::factory()->create());
        $guest = isset($attributes['user_id'])
            ? User::findOrFail($attributes['user_id'])
            : User::factory()->create();
        $booking = isset($attributes['booking_id'])
            ? Booking::findOrFail($attributes['booking_id'])
            : $this->bookingFor($guest, $property);

        $payload = array_merge([
            'user_id' => $guest->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'rating' => 4,
            'comment' => 'A review awaiting moderation.',
            'status' => Review::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'risk_score' => 0,
            'risk_reasons' => [],
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent_hash' => hash('sha256', 'Test Browser'),
        ], $attributes);

        $review = new Review([
            'user_id' => $payload['user_id'],
            'property_id' => $payload['property_id'],
            'booking_id' => $payload['booking_id'],
            'rating' => $payload['rating'],
            'comment' => $payload['comment'],
        ]);

        $review->forceFill([
            'status' => $payload['status'],
            'published_at' => $payload['published_at'] ?? null,
            'moderated_at' => $payload['moderated_at'] ?? null,
            'moderated_by' => $payload['moderated_by'] ?? null,
            'risk_score' => $payload['risk_score'],
            'risk_reasons' => $payload['risk_reasons'],
            'ip_hash' => $payload['ip_hash'],
            'user_agent_hash' => $payload['user_agent_hash'],
        ])->save();

        return $review->refresh()->load(['user', 'property', 'booking']);
    }

    private function propertyFor(User $user, array $attributes = []): Property
    {
        return Property::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Owner Suite',
            'description' => 'A calm test property.',
            'type' => 'apartment',
            'price_per_night' => 250,
            'city' => 'Marrakech',
            'address' => 'Medina',
        ], $attributes));
    }

    private function bookingFor(User $user, Property $property, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'start_date' => now()->subDays(8)->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
            'total_price' => 500,
            'status' => Booking::STATUS_ACCEPTED,
        ], $attributes));
    }
}
