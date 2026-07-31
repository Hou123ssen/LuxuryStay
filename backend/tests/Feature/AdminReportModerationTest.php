<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_admin_reports(): void
    {
        $report = $this->reportFor();

        $this->getJson('/api/admin/reports')->assertUnauthorized();
        $this->getJson('/api/admin/reports/'.$report->id)->assertUnauthorized();
        $this->putJson('/api/admin/reports/'.$report->id.'/review')->assertUnauthorized();
    }

    public function test_normal_user_and_property_owner_cannot_list_admin_reports(): void
    {
        $report = $this->reportFor();
        $normalUser = User::factory()->create();
        $propertyOwner = $report->property->user;

        $this
            ->actingAs($normalUser, 'sanctum')
            ->getJson('/api/admin/reports')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $this
            ->actingAs($propertyOwner, 'sanctum')
            ->getJson('/api/admin/reports')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_admin_can_list_reports_with_safe_related_payload(): void
    {
        $admin = $this->admin();
        $report = $this->reportFor([
            'category' => Report::CATEGORY_UNSAFE_PROPERTY,
            'severity' => Report::SEVERITY_HIGH,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reports')
            ->assertOk()
            ->assertJsonPath('data.0.id', $report->id)
            ->assertJsonPath('data.0.property.id', $report->property_id)
            ->assertJsonPath('data.0.property.title', $report->property->title)
            ->assertJsonPath('data.0.reporter.id', $report->reporter_user_id)
            ->assertJsonPath('data.0.reporter.name', $report->reporter->name)
            ->assertJsonPath('data.0.reported_user.id', $report->reported_user_id)
            ->assertJsonPath('data.0.reported_user.name', $report->reportedUser->name);

        $payload = $response->json('data.0');
        $this->assertArrayNotHasKey('email', $payload['reporter']);
        $this->assertArrayNotHasKey('email', $payload['reported_user']);
    }

    public function test_admin_can_view_report_detail(): void
    {
        $admin = $this->admin();
        $report = $this->reportFor();

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reports/'.$report->id)
            ->assertOk()
            ->assertJsonPath('data.id', $report->id)
            ->assertJsonPath('data.reporter_user_id', $report->reporter_user_id)
            ->assertJsonPath('data.property_id', $report->property_id)
            ->assertJsonPath('data.booking_id', $report->booking_id);
    }

    public function test_admin_can_filter_reports_by_status_severity_and_category(): void
    {
        $admin = $this->admin();
        $matching = $this->reportFor([
            'status' => Report::STATUS_REVIEWED,
            'severity' => Report::SEVERITY_HIGH,
            'category' => Report::CATEGORY_SCAM_OR_FRAUD,
        ]);
        $this->reportFor([
            'status' => Report::STATUS_PENDING,
            'severity' => Report::SEVERITY_NORMAL,
            'category' => Report::CATEGORY_HOST_ISSUE,
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reports?status=reviewed&severity=high&category=scam_or_fraud')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);
    }

    public function test_admin_can_mark_pending_report_as_reviewed(): void
    {
        $admin = $this->admin();
        $report = $this->reportFor(['status' => Report::STATUS_PENDING]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reports/'.$report->id.'/review', [
                'admin_notes' => 'Checked booking details.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Report marked as reviewed.')
            ->assertJsonPath('data.status', Report::STATUS_REVIEWED)
            ->assertJsonPath('data.admin_notes', 'Checked booking details.')
            ->assertJsonPath('data.reviewed_by_user_id', $admin->id)
            ->assertJsonPath('data.resolved_at', null);

        $report->refresh();
        $this->assertSame(Report::STATUS_REVIEWED, $report->status);
        $this->assertSame($admin->id, $report->reviewed_by_user_id);
        $this->assertNotNull($report->reviewed_at);
        $this->assertNull($report->resolved_at);
    }

    public function test_admin_can_resolve_pending_report(): void
    {
        $admin = $this->admin();
        $report = $this->reportFor(['status' => Report::STATUS_PENDING]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reports/'.$report->id.'/resolve', [
                'admin_notes' => 'Issue resolved.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Report resolved.')
            ->assertJsonPath('data.status', Report::STATUS_RESOLVED)
            ->assertJsonPath('data.admin_notes', 'Issue resolved.');

        $report->refresh();
        $this->assertSame($admin->id, $report->reviewed_by_user_id);
        $this->assertNotNull($report->reviewed_at);
        $this->assertNotNull($report->resolved_at);
    }

    public function test_admin_can_resolve_reviewed_report(): void
    {
        $admin = $this->admin();
        $report = $this->reportFor([
            'status' => Report::STATUS_REVIEWED,
            'reviewed_at' => now()->subDay(),
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reports/'.$report->id.'/resolve')
            ->assertOk()
            ->assertJsonPath('data.status', Report::STATUS_RESOLVED);

        $this->assertNotNull($report->refresh()->resolved_at);
    }

    public function test_admin_can_reject_pending_report(): void
    {
        $admin = $this->admin();
        $report = $this->reportFor(['status' => Report::STATUS_PENDING]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reports/'.$report->id.'/reject', [
                'admin_notes' => 'Not enough evidence.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Report rejected.')
            ->assertJsonPath('data.status', Report::STATUS_REJECTED)
            ->assertJsonPath('data.admin_notes', 'Not enough evidence.')
            ->assertJsonPath('data.resolved_at', null);

        $report->refresh();
        $this->assertNotNull($report->reviewed_at);
        $this->assertNull($report->resolved_at);
    }

    public function test_admin_can_reject_reviewed_report(): void
    {
        $admin = $this->admin();
        $report = $this->reportFor(['status' => Report::STATUS_REVIEWED]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reports/'.$report->id.'/reject')
            ->assertOk()
            ->assertJsonPath('data.status', Report::STATUS_REJECTED);
    }

    public function test_resolved_or_rejected_report_cannot_be_moderated_again(): void
    {
        $admin = $this->admin();
        $resolved = $this->reportFor(['status' => Report::STATUS_RESOLVED]);
        $rejected = $this->reportFor(['status' => Report::STATUS_REJECTED]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reports/'.$resolved->id.'/reject')
            ->assertConflict()
            ->assertJsonPath('message', 'This report has already been closed.');

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/reports/'.$rejected->id.'/resolve')
            ->assertConflict()
            ->assertJsonPath('message', 'This report has already been closed.');
    }

    public function test_normal_report_submission_response_still_hides_admin_notes(): void
    {
        $guest = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        $booking = $this->bookingFor($guest, $property);

        $response = $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reports', [
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'category' => Report::CATEGORY_HOST_ISSUE,
                'description' => 'Something went wrong.',
            ])
            ->assertCreated();

        $this->assertArrayNotHasKey('admin_notes', $response->json('report'));
        $this->assertArrayNotHasKey('reviewed_by_user_id', $response->json('report'));
    }

    public function test_non_admin_routes_do_not_expose_admin_report_details(): void
    {
        $guest = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        $booking = $this->bookingFor($guest, $property);

        Report::create([
            'reporter_user_id' => $guest->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'reported_user_id' => $property->user_id,
            'category' => Report::CATEGORY_HOST_ISSUE,
            'description' => 'Existing report.',
            'status' => Report::STATUS_REVIEWED,
            'severity' => Report::SEVERITY_NORMAL,
        ]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reports', [
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'category' => Report::CATEGORY_HOST_ISSUE,
            ])
            ->assertConflict()
            ->assertJsonMissing(['admin_notes']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function reportFor(array $attributes = []): Report
    {
        $reporter = User::factory()->create();
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($reporter, $property);

        return Report::create(array_merge([
            'reporter_user_id' => $reporter->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'reported_user_id' => $owner->id,
            'category' => Report::CATEGORY_HOST_ISSUE,
            'description' => 'A report description.',
            'status' => Report::STATUS_PENDING,
            'severity' => Report::SEVERITY_NORMAL,
        ], $attributes));
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
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'total_price' => 500,
            'status' => Booking::STATUS_ACCEPTED,
        ], $attributes));
    }
}
