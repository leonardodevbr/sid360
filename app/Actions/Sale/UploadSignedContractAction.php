<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Sale;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadSignedContractAction
{
    public function execute(Sale $sale, UploadedFile $file): Sale
    {
        $disk = Storage::disk('local');

        if ($sale->signed_contract_path && $disk->exists($sale->signed_contract_path)) {
            $disk->delete($sale->signed_contract_path);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = $file->storeAs(
            "sales/{$sale->id}",
            "signed-contract.{$extension}",
            'local',
        );

        if ($path === false) {
            throw new \RuntimeException('Failed to store signed contract file.');
        }

        $sale->update([
            'signed_contract_path' => $path,
            'signed_contract_original_name' => $file->getClientOriginalName(),
        ]);

        return $sale->fresh();
    }
}
