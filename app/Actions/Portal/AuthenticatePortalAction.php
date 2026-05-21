<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Models\Client;
use App\Support\DocumentHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticatePortalAction
{
    /**
     * @return array{portal_token: string, client: array{name: string}}
     */
    public function execute(string $cpf, string $phone): array
    {
        $cpfDigits = DocumentHelper::digitsOnly($cpf);

        if (strlen($cpfDigits) !== 11) {
            throw ValidationException::withMessages([
                'cpf' => ['Informe um CPF válido.'],
            ]);
        }

        $client = Client::query()
            ->whereNotNull('cpf')
            ->get()
            ->first(fn (Client $candidate): bool => DocumentHelper::digitsOnly($candidate->cpf) === $cpfDigits);

        if ($client === null || ! DocumentHelper::phoneMatches($client->phone, $phone)) {
            throw ValidationException::withMessages([
                'cpf' => ['CPF ou WhatsApp não encontrados. Verifique os dados ou fale com a corretora.'],
            ]);
        }

        $token = Str::random(64);
        $ttlMinutes = (int) config('portal.token_ttl_minutes', 120);

        Cache::put("portal:token:{$token}", $client->id, now()->addMinutes($ttlMinutes));

        return [
            'portal_token' => $token,
            'client' => [
                'name' => $client->name,
            ],
        ];
    }
}
