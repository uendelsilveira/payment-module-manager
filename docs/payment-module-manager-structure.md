# 🧩 Payment Module Manager

Pacote Laravel independente para gerenciar múltiplos gateways de pagamento (Mercado Pago, PagSeguro, PayPal, Stripe, etc).  
Permite integração plugável via composer (`uendelsilveira/payment-module-manager`).

---

## 📦 Estrutura do Projeto

```
payment-module-manager/
├── composer.json
├── src/
│   ├── Contracts/
│   │   └── PaymentProviderInterface.php
│   ├── Providers/│
│   │   └── PaymentServiceProvider.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── PaymentController.php
│   │   └── Requests/
│   │       └── CreatePaymentRequest.php
│   ├── Models/
│   │   └── Transaction.php
│   ├── Repositories/
│   │   └── TransactionRepository.php
│   ├── Services/
│   │   ├── PaymentService.php
│   │   └── GatewayManager.php
│   ├── Gateways/
│   │   ├── MercadoPagoStrategy.php
│   │   ├── PagSeguroStrategy.php
│   │   ├── PayPalStrategy.php
│   │   └── StripeStrategy.php
│   ├── Traits/
│   │   └── ApiResponseTrait.php
│   ├── Enums/
│   │   └── PaymentGateway.php
│   └── Facades/
│       └── Payment.php
├── routes/
│   └── api.php
├── database/
│   └── migrations/
│       └── 2025_10_27_000000_create_transactions_table.php
├── config/
│   └── payment.php
└── tests/
    ├── Feature/
    │   └── ApiPaymentTest.php
    └── Unit/
        ├── PaymentServiceTest.php
        └── GatewayManagerTest.php
```

---

## ⚙️ composer.json

```json
{
  "name": "uendelsilveira/payment-module-manager",
  "description": "Pacote gerenciador de múltiplos gateways de pagamento para Laravel.",
  "type": "library",
  "autoload": {
    "psr-4": {
      "UendelSilveira\\PaymentModuleManager\\": "src/"
    }
  },
  "extra": {
    "laravel": {
      "providers": [
        "UendelSilveira\\PaymentModuleManager\\Providers\\PaymentServiceProvider"
      ]
    }
  },
  "require": {
    "php": "^8.1|^8.2|^8.3",
    "illuminate/support": "^11.0",
    "illuminate/http": "^11.0",
    "illuminate/database": "^11.0"
  },
  "minimum-stability": "stable",
  "license": "MIT"
}
```

---

## 🚀 Instalação (local)

Para desenvolver e testar localmente, adicione o repositório ao seu projeto Laravel:

```bash
composer config repositories.payment-module-manager path ../payment-module-manager
composer require uendelsilveira/payment-module-manager:dev-main
```

---

## 🧠 Service Provider

```php
<?php

namespace UendelSilveira\PaymentModuleManager\Providers;

use Illuminate\Support\ServiceProvider;
use UendelSilveira\PaymentModuleManager\Repositories\TransactionRepository;
use UendelSilveira\PaymentModuleManager\Contracts\TransactionRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Services\PaymentService;

class PaymentServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/payment.php', 'payment');
        $this->app->bind(TransactionRepositoryInterface::class, TransactionRepository::class);
        $this->app->singleton(PaymentService::class, fn() => new PaymentService());
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->publishes([
            __DIR__.'/../../config/payment.php' => config_path('payment.php'),
        ], 'config');
    }
}
```

---

## 🧩 API Endpoint

Rota exposta automaticamente pelo pacote:

```
POST /api/payment/process
```

### Exemplo de payload:

```json
{
  "amount": 199.90,
  "method": "mercadopago",
  "description": "Assinatura Premium"
}
```

---

## 🧪 Testes

Testes unitários e de integração prontos para PHPUnit:

```bash
vendor/bin/phpunit
```

---

## 🧰 Traits

`ApiResponseTrait` padroniza todas as respostas da API:

```php
return $this->successResponse($data, 'Pagamento processado com sucesso');
return $this->errorResponse('Falha ao processar pagamento', 422);
```

---

## 📄 Licença

MIT © Uendel Silveira
