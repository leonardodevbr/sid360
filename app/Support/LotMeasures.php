<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Lot;

class LotMeasures
{
    /**
     * Área oficial do lote: manual quando existir, senão a calculada pelo mapa.
     */
    public static function resolveArea(Lot $lot): ?float
    {
        if ($lot->area !== null && $lot->area !== '') {
            return (float) $lot->area;
        }

        if ($lot->area_computed !== null && $lot->area_computed !== '') {
            return (float) $lot->area_computed;
        }

        return null;
    }

    /**
     * @return list<array{name: string, meters: float}>
     */
    public static function normalizeFaces(mixed $faces): array
    {
        if (! is_array($faces)) {
            return [];
        }

        $normalized = [];

        foreach ($faces as $face) {
            if (! is_array($face)) {
                continue;
            }

            $name = trim((string) ($face['name'] ?? ''));
            $meters = $face['meters'] ?? null;

            if ($name === '' || $meters === null || $meters === '') {
                continue;
            }

            $numeric = (float) $meters;

            if ($numeric <= 0 || ! is_finite($numeric)) {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'meters' => $numeric,
            ];
        }

        return $normalized;
    }

    /**
     * Rótulo curto para mapa/tabela: size_label ou faces derivadas.
     */
    public static function resolveDimensionsLabel(Lot $lot, bool $useTimes = true): ?string
    {
        $sizeLabel = trim((string) ($lot->size_label ?? ''));

        if ($sizeLabel !== '') {
            $label = preg_replace('/m$/i', '', $sizeLabel) ?? $sizeLabel;
            $label = preg_replace('/[×xX]/u', $useTimes ? '×' : 'x', $label) ?? $label;

            return trim($label) !== '' ? trim($label) : null;
        }

        return self::formatFacesAsDimensions($lot->faces ?? [], $useTimes);
    }

    /**
     * @param  list<array{name?: string, meters?: float|int|string}>|mixed  $faces
     */
    public static function formatFacesAsDimensions(mixed $faces, bool $useTimes = true): ?string
    {
        $normalized = self::normalizeFaces($faces);

        if ($normalized === []) {
            return null;
        }

        if (count($normalized) === 2) {
            $a = self::formatMeters($normalized[0]['meters']);
            $b = self::formatMeters($normalized[1]['meters']);
            $sep = $useTimes ? '×' : 'x';

            return "{$a}{$sep}{$b}";
        }

        $parts = [];

        foreach ($normalized as $face) {
            $parts[] = $face['name'].' '.self::formatMeters($face['meters']);
        }

        return implode(' · ', $parts);
    }

    /**
     * Texto contratual das medidas (override da venda > texto do lote > auto).
     */
    public static function resolveContractMeasuresText(Lot $lot, ?string $saleOverride = null): ?string
    {
        $override = trim((string) ($saleOverride ?? ''));

        if ($override !== '') {
            return $override;
        }

        $lotText = trim((string) ($lot->contract_measures_text ?? ''));

        if ($lotText !== '') {
            return $lotText;
        }

        return self::buildAutoContractMeasuresText($lot);
    }

    public static function buildAutoContractMeasuresText(Lot $lot): ?string
    {
        $parts = [];
        $area = self::resolveArea($lot);

        if ($area !== null) {
            $formatted = number_format($area, 0, ',', '.');
            $parts[] = "com área total de {$formatted}m² ({$formatted} metros quadrados)";
        }

        $faces = self::normalizeFaces($lot->faces ?? []);

        if ($faces !== []) {
            $faceParts = [];

            foreach ($faces as $face) {
                $faceParts[] = $face['name'].' de '.self::formatMeters($face['meters']).'m';
            }

            $parts[] = 'medindo '.self::joinPortugueseList($faceParts);
        } else {
            $dimensions = self::resolveDimensionsLabel($lot, true);

            if ($dimensions !== null) {
                $parts[] = "medindo {$dimensions}";
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    /**
     * Faces padrão para lote retangular gerado a partir de largura × profundidade.
     *
     * @return list<array{name: string, meters: float}>|null
     */
    public static function rectangularFaces(mixed $width, mixed $depth): ?array
    {
        $w = self::positiveMeters($width);
        $d = self::positiveMeters($depth);

        if ($w === null || $d === null) {
            return null;
        }

        return [
            ['name' => 'Frente', 'meters' => $w],
            ['name' => 'Lado esquerdo', 'meters' => $d],
            ['name' => 'Lado direito', 'meters' => $d],
            ['name' => 'Fundo', 'meters' => $w],
        ];
    }

    public static function formatMeters(float $meters): string
    {
        if (abs($meters - round($meters)) < 0.001) {
            return (string) (int) round($meters);
        }

        return rtrim(rtrim(number_format($meters, 2, ',', '.'), '0'), ',');
    }

    private static function positiveMeters(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $numeric = (float) $value;

        if ($numeric <= 0 || ! is_finite($numeric)) {
            return null;
        }

        return $numeric;
    }

    /**
     * @param  list<string>  $items
     */
    private static function joinPortugueseList(array $items): string
    {
        $count = count($items);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $items[0];
        }

        if ($count === 2) {
            return $items[0].' e '.$items[1];
        }

        $last = array_pop($items);

        return implode(', ', $items).' e '.$last;
    }
}
