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
                'development' => ['Cannot delete a development that has lots registered.'],
            ]);
        }

        $development->delete();
    }
}
