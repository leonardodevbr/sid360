<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDocument extends Model
{
    public const TYPE_RG = 'rg';

    public const TYPE_CPF = 'cpf';

    public const TYPE_CNH = 'cnh';

    public const TYPE_COMPROVANTE_RESIDENCIA = 'comprovante_residencia';

    public const TYPE_COMPROVANTE_RENDA = 'comprovante_renda';

    public const TYPE_OUTRO = 'outro';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_RG,
        self::TYPE_CPF,
        self::TYPE_CNH,
        self::TYPE_COMPROVANTE_RESIDENCIA,
        self::TYPE_COMPROVANTE_RENDA,
        self::TYPE_OUTRO,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'type',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size',
        'version',
        'is_current',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_current' => 'boolean',
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            self::TYPE_RG => 'RG',
            self::TYPE_CPF => 'CPF',
            self::TYPE_CNH => 'CNH',
            self::TYPE_COMPROVANTE_RESIDENCIA => 'Comprovante de residência',
            self::TYPE_COMPROVANTE_RENDA => 'Comprovante de renda',
            self::TYPE_OUTRO => 'Outro',
            default => '',
        };
    }
}
