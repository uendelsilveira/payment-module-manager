<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Repositories;

use UendelSilveira\PaymentModuleManager\Contracts\SettingsRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Models\PaymentSetting;

class SettingsRepository implements SettingsRepositoryInterface
{
    public function get(string $key, $default = null): ?string
    {
        $setting = PaymentSetting::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public function set(string $key, ?string $value): void
    {
        PaymentSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
