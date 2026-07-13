<?php

declare(strict_types=1);

namespace App\Actions\Lot;

use App\Models\Lot;
use App\Support\LotMeasures;

class UpdateLotAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Lot $lot, array $data): Lot
    {
        if (array_key_exists('faces', $data)) {
            $faces = LotMeasures::normalizeFaces($data['faces'] ?? null);
            $data['faces'] = $faces === [] ? null : $faces;
        }

        if (array_key_exists('size_label', $data)) {
            $sizeLabel = is_string($data['size_label'] ?? null) ? trim($data['size_label']) : null;
            $data['size_label'] = $sizeLabel !== '' ? $sizeLabel : null;
        }

        if (array_key_exists('contract_measures_text', $data)) {
            $text = is_string($data['contract_measures_text'] ?? null) ? trim($data['contract_measures_text']) : null;
            $data['contract_measures_text'] = $text !== '' ? $text : null;
        }

        $lot->update($data);

        return $lot->fresh(['development', 'zone.parent', 'street']);
    }
}
