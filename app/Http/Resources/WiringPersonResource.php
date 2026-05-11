<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WiringPersonResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'wiring_person_name' => $this->wiring_person_name,
            'wiring_person_no' => $this->wiring_person_no,
        ];
    }
}
