<?php

declare(strict_types=1);

namespace App\Actions\Lot;

use App\Models\Lot;

class StoreLotAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Lot
    {
        return Lot::query()->create($data);
    }
}
