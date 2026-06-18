<?php

declare(strict_types=1);

namespace App\Actions\Lot;

use App\Models\Lot;
use Illuminate\Validation\ValidationException;

class BulkDeleteLotsAction
{
    public function __construct(
        private readonly DeleteLotAction $deleteLotAction,
    ) {}

    /**
     * @param  list<int>  $ids
     * @return array{deleted: int, skipped: list<array{id: int, number: string|null, reason: string}>}
     */
    public function execute(array $ids): array
    {
        $deleted = 0;
        $skipped = [];

        foreach ($ids as $id) {
            $lot = Lot::query()->find((int) $id);

            if ($lot === null) {
                continue;
            }

            try {
                $this->deleteLotAction->execute($lot);
                $deleted++;
            } catch (ValidationException $exception) {
                $reason = collect($exception->errors())->flatten()->first()
                    ?? 'Não foi possível excluir o lote.';

                $skipped[] = [
                    'id' => $lot->id,
                    'number' => $lot->number,
                    'reason' => (string) $reason,
                ];
            }
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped,
        ];
    }
}
