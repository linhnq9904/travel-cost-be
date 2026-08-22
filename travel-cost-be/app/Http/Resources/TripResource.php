<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'title' => $this->title,
            'description' => $this->description,
            'destination' => $this->destination,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'budget' => (float) $this->budget,
            'currency' => $this->currency,
            'status' => $this->status,
            'owner' => [
                'id' => $this->owner->id,
                'email' => $this->owner->email,
                'name' => $this->owner->profile?->full_name,
            ],
            'members_count' => $this->members->count(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
