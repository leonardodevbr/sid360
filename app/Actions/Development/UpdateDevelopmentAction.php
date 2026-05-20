<?php

declare(strict_types=1);

namespace App\Actions\Development;

use App\Models\Development;

class UpdateDevelopmentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Development $development, array $data): Development
    {
        $development->update($data);

        return $development->fresh();
    }
}
