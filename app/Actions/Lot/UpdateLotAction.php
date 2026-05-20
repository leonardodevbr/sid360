<?php

declare(strict_types=1);

namespace App\Actions\Lot;

use App\Models\Lot;

class UpdateLotAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Lot $lot, array $data): Lot
    {
        $lot->update($data);

        return $lot->fresh(['development']);
    }
}
