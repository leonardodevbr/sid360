<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Development;

/**
 * Resolve os dados do vendedor/empresa exibidos no contrato.
 *
 * Os dados do vendedor podem ser sobrescritos por empreendimento
 * (App\Models\Development::seller_*); quando o empreendimento não define
 * um campo, o valor cai para a configuração global em Settings (grupo
 * "contrato"). Os fallbacks abaixo reproduzem os valores que estavam
 * hardcoded no template do contrato antes da migração para Settings.
 */
class ContractParty
{
    /**
     * @return array{name: string, cpf: string, rg: string, rg_issuer: string, address: string}
     */
    public static function seller(?Development $development = null): array
    {
        return [
            'name' => self::resolve($development?->seller_name, 'vendedor_nome', 'Sidiclei Novais Baretto'),
            'cpf' => self::resolve($development?->seller_cpf, 'vendedor_cpf', '311.168.558-60'),
            'rg' => self::resolve($development?->seller_rg, 'vendedor_rg', '08.280.665-90'),
            'rg_issuer' => self::resolve($development?->seller_rg_issuer, 'vendedor_rg_issuer', 'SSP/BA'),
            'address' => self::resolve(
                $development?->seller_address,
                'vendedor_endereco',
                'Rua Arlindo Montino, nº 4, s/nº, Centro, Cafarnaum — Bahia',
            ),
        ];
    }

    /**
     * @return array{nome: string, tagline: string, site: string}
     */
    public static function company(): array
    {
        return [
            'nome' => (string) Settings::get('empresa_nome', 'Sid360 Imóveis'),
            'tagline' => (string) Settings::get('empresa_tagline', 'Imóveis Residencial, Comercial e Rural'),
            'site' => (string) Settings::get('empresa_site', 'sid360.com.br'),
        ];
    }

    /**
     * @return array{cidade: string, estado: string, estado_extenso: string}
     */
    public static function foro(): array
    {
        return [
            'cidade' => (string) Settings::get('foro_cidade', 'Cafarnaum'),
            'estado' => (string) Settings::get('foro_estado', 'BA'),
            'estado_extenso' => (string) Settings::get('foro_estado_extenso', 'Bahia'),
        ];
    }

    private static function resolve(?string $override, string $settingKey, string $fallback): string
    {
        if ($override !== null && trim($override) !== '') {
            return $override;
        }

        return (string) Settings::get($settingKey, $fallback);
    }
}
