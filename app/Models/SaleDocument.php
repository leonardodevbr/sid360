<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDocument extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'sale_id',
        'client_document_id',
        'type',
        'side',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<ClientDocument, $this>
     */
    public function clientDocument(): BelongsTo
    {
        return $this->belongsTo(ClientDocument::class);
    }

    public static function typeLabel(?string $type): string
    {
        return ClientDocument::typeLabel($type);
    }

    public static function sideLabel(?string $side): string
    {
        return ClientDocument::sideLabel($side);
    }
}
