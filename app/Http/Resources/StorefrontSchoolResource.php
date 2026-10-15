<?php

namespace App\Http\Resources;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * The public face of a school. Deliberately tiny: no email, phone, address or payout data.
 *
 * @mixin School
 */
class StorefrontSchoolResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'city' => $this->city,
            'state' => $this->state,
            'avatar_url' => $this->avatar_path ? Storage::url($this->avatar_path) : null,
        ];
    }
}
