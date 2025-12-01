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
use UendelSilveira\PaymentModuleManager\Database\Factories\PaymentGatewayFactory;
use UendelSilveira\PaymentModuleManager\Services\WebhookIdempotencyService;

$dotenv = Dotenv::createImmutable(__DIR__.'/..');
$dotenv->load();

DatabaseManager::init();

$service = new WebhookIdempotencyService;

echo "🔄 Searching for unprocessed webhooks...\n\n";

$unprocessed = $service->getUnprocessedWebhooks();

if (empty($unprocessed)) {
    echo "✅ No unprocessed webhooks found.\n";
    exit(0);
}

echo 'Found '.count($unprocessed)." unprocessed webhook(s).\n\n";

foreach ($unprocessed as $webhook) {
    echo "Processing webhook: {$webhook['event_id']} ({$webhook['gateway']})...\n";

    try {
        $gateway = PaymentGatewayFactory::create($webhook['gateway']);
        $gateway->handleWebhook($webhook['payload']);

        $service->markAsProcessed($webhook['gateway'], $webhook['event_id']);

        echo "✅ Webhook {$webhook['event_id']} processed successfully.\n";
    } catch (Exception $e) {
        echo "❌ Failed to process webhook {$webhook['event_id']}: {$e->getMessage()}\n";

        $service->markAsFailed($webhook['gateway'], $webhook['event_id'], $e->getMessage());
    }

    echo "\n";
}

echo "🎉 Reprocessing completed!\n";
