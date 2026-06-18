<?php

declare(strict_types=1);

namespace App\Actions\Lot;

use App\Models\Lot;

class BulkUpdateLotsStatusAction
{
    /**
     * @param  list<int>  $ids
     * @return array{updated: int, skipped: list<array{id: int, number: string|null, reason: string}>}
     */
    public function execute(array $ids, string $status): array
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

            if ($lot->status === $status) {
                $updated++;

                continue;
            }

            $lot->update(['status' => $status]);
            $updated++;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }
}
