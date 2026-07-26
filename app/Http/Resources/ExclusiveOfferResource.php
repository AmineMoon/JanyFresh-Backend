<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExclusiveOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'discount_percentage' => $this->discount_percentage,
            'badge_text' => $this->badge_text,
            'button_text' => $this->button_text,
            'image' => $this->image_url,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
