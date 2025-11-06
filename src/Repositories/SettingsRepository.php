<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Repositories;

use Illuminate\Support\Facades\Cache;
use UendelSilveira\PaymentModuleManager\Contracts\SettingsRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Models\PaymentSetting;

class SettingsRepository implements SettingsRepositoryInterface
{
    /**
     * Cache TTL em segundos (1 hora por padrão)
     */
    protected int $cacheTtl = 3600;

    /**
     * Prefixo para chaves de cache
     */
    protected string $cachePrefix = 'payment_settings:';

    /**
     * @param mixed $default
     */
    public function get(string $key, $default = null): ?string
    {
        $cacheKey = $this->getCacheKey($key);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($key, $default): ?string {
            /** @var PaymentSetting|null $setting */
            $setting = PaymentSetting::where('key', $key)->first();

            return $setting ? $setting->value : ($default !== null ? (string) $default : null);
        });
    }

    public function set(string $key, ?string $value): void
    {
        PaymentSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        // Invalidar cache após atualização
        $this->forgetCache($key);
    }

    /**
     * Gera a chave de cache para uma configuração
     */
    protected function getCacheKey(string $key): string
    {
        return $this->cachePrefix.$key;
    }

    /**
     * Remove uma configuração do cache
     */
    protected function forgetCache(string $key): void
    {
        Cache::forget($this->getCacheKey($key));
    }

    /**
     * Limpa todo o cache de configurações
     */
    public function clearCache(): void
    {
        // Se estiver usando Redis/Memcached, pode usar tags
        // Cache::tags(['payment_settings'])->flush();

        // Para drivers que não suportam tags, limpar individualmente
        // ou usar um padrão de chave para limpar em lote
        Cache::flush();
    }
}
