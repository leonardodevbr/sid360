<?php

declare(strict_types=1);

namespace App\Actions\Development;

use App\Models\Development;

class StoreDevelopmentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Development
    {
        return Development::query()->create($data);
    }
}
