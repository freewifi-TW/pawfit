<?php

namespace App\Http\Resources;

use App\Models\ShareLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShareLinkResource extends JsonResource
{
    /** @var ShareLink */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug,
            'url' => $this->resource->url(),
            'watermark' => $this->resource->watermark,
            'created_at' => $this->resource->created_at,
        ];
    }
}
