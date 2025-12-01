<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created at: 24/11/25
*/

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Dotenv\Dotenv;
use Illuminate\Database\DatabaseManager;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(__DIR__.'/..');
$dotenv->load();

// Inicializar banco de dados
DatabaseManager::init();

echo "🔄 Rolling back migrations...\n\n";

try {
    DatabaseManager::rollbackMigrations();
} catch (Exception $e) {
    echo "❌ Rollback failed: {$e->getMessage()}\n";
    exit(1);
}
