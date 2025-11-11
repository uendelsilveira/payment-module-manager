<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Contracts;

interface SettingsRepositoryInterface
{
    public function get(string $key, mixed $default = null): ?string;

    public function set(string $key, ?string $value): void;
}
