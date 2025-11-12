<?php

namespace UendelSilveira\PaymentModuleManager\Repositories;

use Illuminate\Support\Facades\Config;
use UendelSilveira\PaymentModuleManager\Contracts\SettingsRepositoryInterface;

class SettingsRepository implements SettingsRepositoryInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $cache = [];

    /**
     * Obtém uma configuração, seja do banco, do config() ou de variáveis .env
     */
    public function get(string $key, mixed $default = null): ?string
    {
        // 1️⃣ Prioriza cache local
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        // 2️⃣ Tenta obter via config()
        $value = Config::get($key);

        // 3️⃣ Se ainda não encontrar, usa default
        $value ??= $default;

        // 4️⃣ Armazena em cache local
        $this->cache[$key] = $value;

        return $value;
    }

    /**
     * Define um valor (poderia salvar no banco, mas aqui é só cache local)
     */
    public function set(string $key, mixed $value): void
    {
        $this->cache[$key] = $value;
    }
}
