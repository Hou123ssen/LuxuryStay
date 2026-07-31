<?php

namespace App\Services\Properties;

use App\Models\Property;
use App\Models\Review;
use App\Support\OwnerReliability;
use App\Support\PropertyRating;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PropertyQueryService
{
    public function paginateIndex(Request $request, int $perPage, ?int $userId = null): LengthAwarePaginator
    {
        $paginator = $this->applyFiltersAndSort($this->baseIndexQuery(), $request)
            ->paginate($perPage)
            ->withQueryString();

        $this->prepareIndexCollection($paginator->getCollection(), $userId);

        return $paginator;
    }

    public function findDetail(int $propertyId): Property
    {
        $property = $this->withRatingAggregates(
            Property::with([
                'images',
                'reviews' => function ($query) {
                    $query->published()->with('user')->latest();
                },
            ])
        )->findOrFail($propertyId);

        PropertyRating::apply($property);
        OwnerReliability::apply($property);

        return $property;
    }

    public function prepareFavoriteProperties(Collection $properties): void
    {
        OwnerReliability::applyToCollection($properties);

        $properties->each(function (Property $property) {
            PropertyRating::apply($property);
        });
    }

    public function withRatingAggregates($query)
    {
        return $query
            ->withAvg(['reviews as reviews_avg_rating' => function ($query) {
                $query->published();
            }], 'rating')
            ->withCount(['reviews as reviews_count' => function ($query) {
                $query->published();
            }])
            ->withCount(['reviews as pending_high_risk_reviews_count' => function ($query) {
                $query
                    ->where('status', Review::STATUS_PENDING_REVIEW)
                    ->where('risk_score', '>=', config('reviews.risk.high_risk_threshold'));
            }]);
    }

    private function baseIndexQuery()
    {
        return $this->withRatingAggregates(Property::with('images'));
    }

    private function applyFiltersAndSort($query, Request $request)
    {
        if ($request->city) {
            $query->where('city', 'like', '%'.$request->city.'%');
        }

        if ($request->min_price) {
            $query->where('price_per_night', '>=', $request->min_price);
        }

        if ($request->max_price) {
            $query->where('price_per_night', '<=', $request->max_price);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        switch ($request->sort) {
            case 'price_asc':
                return $query->orderBy('price_per_night', 'asc');
            case 'price_desc':
                return $query->orderBy('price_per_night', 'desc');
            case 'rating':
                return $query->orderByRaw(PropertyRating::weightedScoreSql('reviews_avg_rating', 'reviews_count').' desc');
            default:
                return $query->orderBy('created_at', 'desc');
        }
    }

    private function prepareIndexCollection(Collection $properties, ?int $userId): void
    {
        OwnerReliability::applyToCollection($properties);

        $properties->transform(function (Property $property) use ($userId) {
            $property->is_favorite = $userId
                ? $property->favorites()->where('user_id', $userId)->exists()
                : false;

            return PropertyRating::apply($property);
        });
    }
}
