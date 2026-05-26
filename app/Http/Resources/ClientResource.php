<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cpf' => $this->cpf,
            'rg' => $this->rg,
            'rg_issuer' => $this->rg_issuer,
            'profession' => $this->profession,
            'marital_status' => $this->marital_status,
            'marital_status_label' => Client::maritalStatusLabel($this->marital_status),
            'phone' => $this->phone,
            'whatsapp_status' => $this->whatsapp_status,
            'email' => $this->email,
            'zip_code' => $this->zip_code,
            'address' => $this->address,
            'address_number' => $this->address_number,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
