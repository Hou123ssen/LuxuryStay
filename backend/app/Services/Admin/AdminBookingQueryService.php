<?php

namespace App\Services\Admin;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class AdminBookingQueryService
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $query = $this->baseQuery();

        $this->applyFilters($query, $request);
        $this->applySort($query, (string) $request->query('sort', 'newest'));

        return $query
            ->paginate($perPage)
            ->appends($request->query());
    }

    public function forDetail(Booking $booking): Booking
    {
        return $this->baseQuery()
            ->whereKey($booking->id)
            ->firstOrFail();
    }

    private function baseQuery(): Builder
    {
        $query = Booking::query()
            ->with([
                'user:id,name,email,role',
                'property:id,user_id,title,city',
                'property.user:id,name,email,role',
            ]);

        if ($this->hasReviewsTable()) {
            $query->withCount('review as reviews_count');
        } else {
            $query->selectRaw('0 as reviews_count');
        }

        if ($this->hasReportsTable()) {
            $query->selectSub(function ($reports) {
                $reports
                    ->from('reports')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('reports.booking_id', 'bookings.id');
            }, 'reports_count');
        } else {
            $query->selectRaw('0 as reports_count');
        }

        return $query;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $bookings) use ($search) {
                if (is_numeric($search)) {
                    $bookings->orWhere('id', (int) $search);
                }

                $bookings
                    ->orWhereHas('user', function (Builder $guests) use ($search) {
                        $guests
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('property', function (Builder $properties) use ($search) {
                        $properties->where('title', 'like', "%{$search}%");
                    })
                    ->orWhereHas('property.user', function (Builder $owners) use ($search) {
                        $owners
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', trim((string) $request->query('status')));
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', (int) $request->query('property_id'));
        }

        $guestId = $request->query('guest_id', $request->query('user_id'));
        if ($guestId !== null && $guestId !== '') {
            $query->where('user_id', (int) $guestId);
        }

        if ($request->filled('owner_id')) {
            $query->whereHas('property', fn (Builder $properties) => $properties->where('user_id', (int) $request->query('owner_id')));
        }

        if ($request->filled('city')) {
            $city = trim((string) $request->query('city'));
            $query->whereHas('property', fn (Builder $properties) => $properties->where('city', 'like', "%{$city}%"));
        }

        $this->dateFilter($query, 'start_date', '>=', $request->query('start_date_from'));
        $this->dateFilter($query, 'start_date', '<=', $request->query('start_date_to'));
        $this->dateFilter($query, 'end_date', '>=', $request->query('end_date_from'));
        $this->dateFilter($query, 'end_date', '<=', $request->query('end_date_to'));
        $this->dateFilter($query, 'created_at', '>=', $request->query('created_from'));
        $this->dateFilter($query, 'created_at', '<=', $request->query('created_to'));

        if ($request->filled('min_total')) {
            $query->where('total_price', '>=', (float) $request->query('min_total'));
        }

        if ($request->filled('max_total')) {
            $query->where('total_price', '<=', (float) $request->query('max_total'));
        }

        if ($request->has('has_review')) {
            $this->booleanReviewFilter($query, $request->query('has_review'));
        }

        if ($request->has('has_report')) {
            $this->booleanReportFilter($query, $request->query('has_report'));
        }
    }

    private function dateFilter(Builder $query, string $column, string $operator, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $query->whereDate($column, $operator, (string) $value);
    }

    private function booleanReviewFilter(Builder $query, mixed $value): void
    {
        $enabled = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if (! $this->hasReviewsTable()) {
            if ($enabled) {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        $enabled ? $query->has('review') : $query->doesntHave('review');
    }

    private function booleanReportFilter(Builder $query, mixed $value): void
    {
        $enabled = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if (! $this->hasReportsTable()) {
            if ($enabled) {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        $method = $enabled ? 'whereExists' : 'whereNotExists';
        $query->{$method}(function ($reports) {
            $reports
                ->from('reports')
                ->selectRaw('1')
                ->whereColumn('reports.booking_id', 'bookings.id');
        });
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->oldest('created_at'),
            'start_date' => $query->orderBy('start_date')->orderBy('id'),
            'end_date' => $query->orderBy('end_date')->orderBy('id'),
            'total_high' => $query->orderByDesc('total_price')->orderByDesc('created_at'),
            'total_low' => $query->orderBy('total_price')->orderBy('id'),
            'status' => $query->orderBy('status')->orderByDesc('created_at'),
            'property_title' => $query
                ->orderByRaw('(select title from properties where properties.id = bookings.property_id limit 1) asc')
                ->orderByDesc('created_at'),
            default => $query->latest('created_at'),
        };
    }

    private function hasReviewsTable(): bool
    {
        return Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'booking_id');
    }

    private function hasReportsTable(): bool
    {
        return Schema::hasTable('reports') && Schema::hasColumn('reports', 'booking_id');
    }
}
