<?php

declare(strict_types=1);

namespace App\Actions\Development;

use App\Models\Development;
use Illuminate\Validation\ValidationException;

class DeleteDevelopmentAction
{
    public function execute(Development $development): void
    {
        if ($development->lots()->exists()) {
            throw ValidationException::withMessages([
                'development' => ['Não é possível excluir um empreendimento que possui lotes cadastrados.'],
            ]);
        }

        $development->delete();
    }
}
