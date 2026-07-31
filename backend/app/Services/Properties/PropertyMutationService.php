<?php

namespace App\Services\Properties;

use App\Models\Property;
use Illuminate\Http\Exceptions\HttpResponseException;

class PropertyMutationService
{
    public function create(array $validated, int $userId): Property
    {
        return Property::create(array_merge($validated, [
            'user_id' => $userId,
        ]));
    }

    public function update(int $propertyId, array $validated, int $userId): Property
    {
        $property = Property::findOrFail($propertyId);

        $this->authorizeOwner($property, $userId);

        $property->update($validated);

        return $property;
    }

    public function delete(int $propertyId, int $userId): void
    {
        $property = Property::findOrFail($propertyId);

        $this->authorizeOwner($property, $userId);

        $property->delete();
    }

    private function authorizeOwner(Property $property, int $userId): void
    {
        if ((int) $property->user_id === $userId) {
            return;
        }

        throw new HttpResponseException(response()->json(['error' => 'Unauthorized'], 403));
    }
}
