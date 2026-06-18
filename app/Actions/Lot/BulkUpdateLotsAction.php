<?php

declare(strict_types=1);

namespace App\Actions\Lot;

use App\Models\DevelopmentZone;
use App\Models\Lot;

class BulkUpdateLotsAction
{
    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $fields
     * @return array{updated: int, skipped: list<array{id: int, number: string|null, reason: string}>}
     */
    public function execute(array $ids, array $fields): array
    {
        $updated = 0;
        $skipped = [];

        foreach ($ids as $id) {
            $lot = Lot::query()->find((int) $id);

            if ($lot === null) {
                continue;
            }

            if ($lot->status === Lot::STATUS_SOLD) {
                $skipped[] = [
                    'id' => $lot->id,
                    'number' => $lot->number,
                    'reason' => 'Lotes vendidos não podem ser alterados em massa.',
                ];

                continue;
            }

            $payload = $this->buildPayload($lot, $fields, $skipped);

            if ($payload === null) {
                continue;
            }

            if ($payload === []) {
                $updated++;

                continue;
            }

            $lot->update($payload);
            $updated++;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  list<array{id: int, number: string|null, reason: string}>  $skipped
     * @return array<string, mixed>|null
     */
    private function buildPayload(Lot $lot, array $fields, array &$skipped): ?array
    {
        $payload = [];

        if (array_key_exists('zone_id', $fields)) {
            $zoneId = $fields['zone_id'];

            if ($zoneId !== null) {
                $zone = DevelopmentZone::query()->find((int) $zoneId);

                if ($zone === null || $zone->development_id !== $lot->development_id) {
                    $skipped[] = [
                        'id' => $lot->id,
                        'number' => $lot->number,
                        'reason' => 'A zona selecionada não pertence ao empreendimento do lote.',
                    ];

                    return null;
                }
            }

            $payload['zone_id'] = $zoneId;
        }

        if (array_key_exists('block', $fields)) {
            $block = $fields['block'];
            $payload['block'] = is_string($block) && trim($block) !== '' ? trim($block) : null;
        }

        if (array_key_exists('area', $fields)) {
            $payload['area'] = $fields['area'];
        }

        if (array_key_exists('size_label', $fields)) {
            $sizeLabel = $fields['size_label'];
            $payload['size_label'] = is_string($sizeLabel) && trim($sizeLabel) !== ''
                ? trim($sizeLabel)
                : null;
        }

        if (array_key_exists('total_value', $fields)) {
            $payload['total_value'] = $fields['total_value'];
        }

        if (array_key_exists('status', $fields)) {
            $payload['status'] = $fields['status'];
        }

        return $payload;
    }
}
