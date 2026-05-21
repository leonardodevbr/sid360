<?php

declare(strict_types=1);

namespace App\Actions\Lot;

use App\Models\Lot;
use Illuminate\Validation\ValidationException;

class DeleteLotAction
{
    public function execute(Lot $lot): void
    {
        if ($lot->sales()->exists()) {
            throw ValidationException::withMessages([
                'lot' => ['Não é possível excluir um lote que possui venda vinculada.'],
            ]);
        }

        $lot->delete();
    }
}
