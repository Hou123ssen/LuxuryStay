<?php

namespace App\Services\Admin;

use App\Models\Property;
use App\Models\Review;
use App\Support\OwnerReliability;
use App\Support\PropertyRating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class AdminPropertyQueryService
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $query = $this->baseQuery();

        $this->applyFilters($query, $request);
        $this->applySort($query, (string) $request->query('sort', 'newest'));

        $paginator = $query
            ->paginate($perPage)
            ->appends($request->query());

        $this->prepareProperties($paginator->getCollection());

        return $paginator;
    }

    public function forDetail(Property $property): Property
    {
        $property = $this->baseQuery()
            ->whereKey($property->id)
            ->firstOrFail();

        $this->prepareProperties(collect([$property]));

        return $property;
    }

    private function baseQuery(): Builder
    {
        $query = Property::query()
            ->with('user:id,name,email,role')
            ->withCount('bookings');

        if ($this->hasReviewsTable()) {
            $query
                ->withAvg(['reviews as reviews_avg_rating' => fn ($reviews) => $reviews->published()], 'rating')
                ->withCount(['reviews as reviews_count' => fn ($reviews) => $reviews->published()])
                ->withCount(['reviews as all_reviews_count'])
                ->withCount(['reviews as pending_high_risk_reviews_count' => function ($reviews) {
                    $reviews
                        ->where('status', Review::STATUS_PENDING_REVIEW)
                        ->where('risk_score', '>=', config('reviews.risk.high_risk_threshold'));
                }]);
        } else {
            $query
                ->selectRaw('NULL as reviews_avg_rating')
                ->selectRaw('0 as reviews_count')
                ->selectRaw('0 as all_reviews_count')
                ->selectRaw('0 as pending_high_risk_reviews_count');
        }

        if ($this->hasReportsTable()) {
            $query->selectSub(function ($reports) {
                $reports
                    ->from('reports')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('reports.property_id', 'properties.id');
            }, 'reports_count');
        } else {
            $query->selectRaw('0 as reports_count');
        }

        if ($this->hasImagesTable()) {
            $query->withCount('images');
        } else {
            $query->selectRaw('0 as images_count');
        }

        return $query;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $properties) use ($search) {
                $properties
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $owners) use ($search) {
                        $owners
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('owner_id')) {
            $query->where('user_id', (int) $request->query('owner_id'));
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', '%'.trim((string) $request->query('city')).'%');
        }

        if ($request->filled('country_code') && Schema::hasColumn('properties', 'country_code')) {
            $query->where('country_code', strtoupper(trim((string) $request->query('country_code'))));
        }

        if ($request->filled('status') && Schema::hasColumn('properties', 'status')) {
            $query->where('status', trim((string) $request->query('status')));
        }

        if ($request->has('has_bookings')) {
            $this->booleanRelationFilter($query, 'bookings', $request->query('has_bookings'));
        }

        if ($request->has('has_reviews')) {
            $this->booleanReviewsFilter($query, $request->query('has_reviews'));
        }

        if ($request->has('has_reports')) {
            $this->booleanReportsFilter($query, $request->query('has_reports'));
        }

        if ($request->filled('min_price')) {
            $query->where('price_per_night', '>=', (float) $request->query('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price_per_night', '<=', (float) $request->query('max_price'));
        }
    }

    private function booleanRelationFilter(Builder $query, string $relation, mixed $value): void
    {
        $enabled = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        $enabled ? $query->has($relation) : $query->doesntHave($relation);
    }

    private function booleanReportsFilter(Builder $query, mixed $value): void
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
            ->whereColumn('reports.property_id', 'properties.id');
        });
    }

    private function booleanReviewsFilter(Builder $query, mixed $value): void
    {
        $enabled = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if (! $this->hasReviewsTable()) {
            if ($enabled) {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        $this->booleanRelationFilter($query, 'reviews', $value);
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->oldest('created_at'),
            'title' => $query->orderBy('title')->orderBy('id'),
            'bookings_count' => $query->orderByDesc('bookings_count')->orderByDesc('created_at'),
            'reviews_count' => $query->orderByDesc('reviews_count')->orderByDesc('created_at'),
            'reports_count' => $query->orderByDesc('reports_count')->orderByDesc('created_at'),
            'rating' => $query->orderByRaw(PropertyRating::weightedScoreSql('reviews_avg_rating', 'reviews_count').' desc')->orderByDesc('created_at'),
            'price_low' => $query->orderBy('price_per_night')->orderBy('id'),
            'price_high' => $query->orderByDesc('price_per_night')->orderByDesc('created_at'),
            default => $query->latest('created_at'),
        };
    }

    private function prepareProperties($properties): void
    {
        OwnerReliability::applyToCollection($properties);

        $properties->each(fn (Property $property) => PropertyRating::apply($property));
    }

    private function hasReviewsTable(): bool
    {
        return Schema::hasTable('reviews')
            && Schema::hasColumn('reviews', 'property_id')
            && Schema::hasColumn('reviews', 'status')
            && Schema::hasColumn('reviews', 'rating');
    }

    private function hasReportsTable(): bool
    {
        return Schema::hasTable('reports') && Schema::hasColumn('reports', 'property_id');
    }

    private function hasImagesTable(): bool
    {
        return Schema::hasTable('images') && Schema::hasColumn('images', 'property_id');
    }
}
