<?php

declare(strict_types=1);

namespace App\Actions\Lot;

use App\Models\Lot;

class DeleteLotAction
{
    public function execute(Lot $lot): void
    {
        $lot->delete();
    }
}
