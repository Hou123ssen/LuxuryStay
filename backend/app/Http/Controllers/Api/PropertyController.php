<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Properties\StorePropertyRequest;
use App\Http\Requests\Properties\UpdatePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Services\Bookings\BookingAvailabilityService;
use App\Services\Properties\PropertyMutationService;
use App\Services\Properties\PropertyQueryService;
use App\Services\Properties\ReviewEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PropertyController extends Controller
{
    public function index(Request $request, PropertyQueryService $propertyQueryService)
    {
        $paginator = $propertyQueryService->paginateIndex(
            $request,
            $this->perPage($request, 12),
            Auth::id()
        );

        $paginator->getCollection()->transform(function ($property) {
            return (new PropertyResource($property))->resolve();
        });

        return $this->paginatedResponse($paginator);
    }

    public function show(
        $id,
        PropertyQueryService $propertyQueryService,
        ReviewEligibilityService $reviewEligibilityService
    ) {
        $property = $propertyQueryService->findDetail((int) $id);
        $property->setAttribute(
            'review_eligible_bookings',
            $reviewEligibilityService->eligibleBookingsFor($property, Auth::id())
        );

        return response()->json((new PropertyResource($property))->resolve());
    }

    public function availability(Property $property, BookingAvailabilityService $availability)
    {
        $payload = $availability->unavailableForProperty($property);

        return response()->json([
            'property_id' => $property->id,
            'unavailable_ranges' => $payload['unavailable_ranges'],
            'unavailable_dates' => $payload['unavailable_dates'],
        ]);
    }

    public function store(StorePropertyRequest $request, PropertyMutationService $propertyMutationService)
    {
        $property = $propertyMutationService->create($request->validated(), (int) Auth::id());

        return response()->json(['data' => $property], 201);
    }

    public function update(UpdatePropertyRequest $request, $id, PropertyMutationService $propertyMutationService)
    {
        $property = $propertyMutationService->update((int) $id, $request->validated(), (int) Auth::id());

        return response()->json(['data' => $property]);
    }

    public function destroy($id, PropertyMutationService $propertyMutationService)
    {
        $propertyMutationService->delete((int) $id, (int) Auth::id());

        return response()->json(['message' => 'Deleted successfully']);
    }
}
