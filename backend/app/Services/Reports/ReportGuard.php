<?php

namespace App\Services\Reports;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Report;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReportGuard
{
    private const ALLOWED_BOOKING_STATUSES = [
        Booking::STATUS_ACCEPTED,
        Booking::STATUS_COMPLETED,
        Booking::STATUS_CANCELLED,
    ];

    public function authorizeCreate(int $reporterUserId, Booking $booking, Property $property): void
    {
        if ((int) $property->user_id === $reporterUserId) {
            $this->forbidden();
        }

        if ((int) $booking->user_id !== $reporterUserId) {
            $this->forbidden();
        }

        if ((int) $booking->property_id !== (int) $property->id) {
            $this->unprocessable();
        }

        if (! in_array($booking->status, self::ALLOWED_BOOKING_STATUSES, true)) {
            $this->unprocessable();
        }

        if ($this->duplicateExists($reporterUserId, $booking->id)) {
            throw new HttpResponseException(response()->json([
                'message' => 'A report for this booking is already under review.',
            ], 409));
        }
    }

    private function duplicateExists(int $reporterUserId, int $bookingId): bool
    {
        return Report::where('reporter_user_id', $reporterUserId)
            ->where('booking_id', $bookingId)
            ->exists();
    }

    private function forbidden(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'This booking cannot be reported.',
        ], 403));
    }

    private function unprocessable(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'This booking cannot be reported.',
        ], 422));
    }
}
