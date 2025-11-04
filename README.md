# 💳 Payment Module Manager

[![Build Status](https://img.shields.io/github/actions/workflow/status/uendelsilveira/payment-module-manager/ci.yml?branch=main&label=Build)](https://github.com/uendelsilveira/payment-module-manager/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/uendelsilveira/payment-module-manager?&branch=main&label=Code%20Coverage)](https://codecov.io/gh/uendelsilveira/payment-module-manager)
[![Latest Version](https://img.shields.io/packagist/v/uendelsilveira/payment-module-manager?&label=Packagist)](https://packagist.org/packages/uendelsilveira/payment-module-manager)
![License](https://img.shields.io/badge/license-MIT-green?&logo=mit&logoColor=white&label=License)

[![PHP](https://img.shields.io/packagist/php-v/uendelsilveira/payment-module-manager?&logo=php&logoColor=blue&&label=PHP)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/LARAVEL-11.x-ff2d20?&logo=laravel&logoColor=red)](https://laravel.com)


**Versão:** 1.1.0
**Status:** ✅ **PRODUÇÃO-READY**

Um pacote Laravel robusto e escalável para gerenciamento de pagamentos, com foco inicial na integração com o Mercado Pago. Projetado para ser seguro, plugável e fácil de usar em qualquer aplicação Laravel.

---

## ✨ Funcionalidades Principais

O módulo foi reestruturado com foco em segurança, escalabilidade e manutenibilidade, implementando as melhores práticas de desenvolvimento de software.

### Segurança
- **Autenticação e Autorização:** Middlewares configuráveis para proteger rotas com estratégias como `api_token`, `laravel_auth` ou `custom`.
- **Proteção de Credenciais:** As credenciais nunca são expostas via API, sendo sempre mascaradas.
- **Validação de Webhook:** Assinatura de webhooks do Mercado Pago é validada compulsoriamente em ambiente de produção, incluindo proteção contra *replay attacks*.
- **Rate Limiting:** Proteção contra abuso e ataques de força bruta com limites de requisição configuráveis por tipo de endpoint.
- **Validação de Idempotência:** Previne o processamento duplicado de transações através de uma `Idempotency-Key`.

### Arquitetura e Performance
- **Estrutura Modular:** Separação clara de responsabilidades (Services, Repositories, Gateways).
- **Processamento Assíncrono:** Webhooks são processados em filas para respostas mais rápidas e maior resiliência.
- **Cache de Configurações:** As configurações do gateway são cacheadas para minimizar queries ao banco de dados.
- **Índices Otimizados:** Índices de banco de dados implementados nas colunas mais consultadas para queries de alta performance.
- **Logging Estruturado:** Logs detalhados com `Correlation ID` para rastreabilidade completa de requisições.

### Funcionalidades do Gateway
- **Integração com Mercado Pago:** Processa e consulta pagamentos via PIX, Cartão de Crédito (com parcelamento) e Boleto.
- **Gerenciamento via API:** Credenciais do gateway podem ser gerenciadas através de endpoints da API.
- **Conexão OAuth 2.0:** Fluxo seguro para conectar contas de usuários do Mercado Pago.
- **Reprocessamento de Falhas:** Comando Artisan (`payment:reprocess-failed`) para reprocessar transações que falharam, com estratégia de *retry* configurável.
- **Relatórios e Métricas:** Endpoints para sumarizar transações e analisar dados por método de pagamento.
- **Health Check:** Endpoint `GET /api/health` para monitorar a saúde da aplicação e suas dependências (banco de dados, cache, API externa).

---

## 📋 Requisitos

- **PHP:** ^8.2
- **Laravel:** ^11.0

---

## 📦 Instalação

Adicione o pacote ao seu projeto via Composer:

```bash
composer require uendelsilveira/payment-module-manager
```

Se o pacote não estiver no Packagist, adicione o repositório ao seu `composer.json`:

```json
// composer.json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/uendelsilveira/payment-module-manager.git"
    }
]
```

---

## ⚙️ Configuração

1.  **Publique o Arquivo de Configuração:**
    ```bash
    php artisan vendor:publish --provider="UendelSilveira\PaymentModuleManager\Providers\PaymentServiceProvider" --tag="config"
    ```
    Isso criará o arquivo `config/payment.php`.

2.  **Execute as Migrações:**
    ```bash
    php artisan migrate
    ```
    Isso criará as tabelas `transactions` e `payment_settings`, agora com `soft deletes` e índices otimizados.

3.  **Configure as Variáveis de Ambiente (.env):**
    Estas variáveis servem como fallback se nenhuma configuração for encontrada no banco de dados.

    ```dotenv
    MERCADOPAGO_PUBLIC_KEY="SEU_PUBLIC_KEY"
    MERCADOPAGO_ACCESS_TOKEN="SEU_ACCESS_TOKEN"
    MERCADOPAGO_WEBHOOK_SECRET="SEU_WEBHOOK_SECRET"

    MERCADOPAGO_CLIENT_ID="SEU_CLIENT_ID_DA_APLICACAO"
    MERCADOPAGO_CLIENT_SECRET="SEU_CLIENT_SECRET_DA_APLICACAO"
    ```

---

## 🚀 Quick Start

Para começar a usar o módulo rapidamente, siga estes passos:

1.  **Configure suas credenciais** no arquivo `.env`.
2.  **Execute as migrações:** `php artisan migrate`.
3.  **Processe um pagamento (PIX):**
    ```bash
    curl -X POST "http://localhost/api/payment/process" \
         -H "Content-Type: application/json" \
         -H "Authorization: Bearer SEU_API_TOKEN" \
         -H "Idempotency-Key: unique-request-id-123" \
         -d '{
               "amount": 100.50,
               "method": "mercadopago",
               "description": "Produto Exemplo",
               "payer_email": "comprador@email.com",
               "payment_method_id": "pix"
             }'
    ```
4.  **Consulte o pagamento:** (Substitua `{transaction_id}` pelo ID retornado)
    ```bash
    curl -X GET "http://localhost/api/payments/{transaction_id}" \
         -H "Authorization: Bearer SEU_API_TOKEN"
    ```

---

## 📖 Uso Detalhado

### Documentação da API (OpenAPI)

Uma documentação detalhada da API está disponível no formato OpenAPI. Visualize-a com ferramentas como o [Swagger Editor](https://editor.swagger.io/).

[**Ver a Documentação da API (openapi.yaml)**](./docs/openapi.yaml)

### Endpoints e Exemplos

#### `POST /api/payment/process`

Cria e processa um novo pagamento.

**Exemplo de Requisição (Cartão de Crédito):**
```bash
curl -X POST "http://localhost/api/payment/process" \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer SEU_API_TOKEN" \
     -d '{
           "amount": 199.90,
           "method": "mercadopago",
           "description": "Assinatura Premium",
           "payer_email": "cliente@example.com",
           "payment_method_id": "credit_card",
           "token": "...",
           "installments": 1,
           "issuer_id": "...",
           "payer": { "first_name": "João", "last_name": "Silva", "identification": { "type": "CPF", "number": "..." } }
         }'
```

**Exemplo de Requisição (Boleto):**
```bash
curl -X POST "http://localhost/api/payment/process" \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer SEU_API_TOKEN" \
     -d '{
           "amount": 100.00,
           "method": "mercadopago",
           "description": "Pagamento de Fatura",
           "payer_email": "cliente@example.com",
           "payment_method_id": "boleto",
           "payer": { "first_name": "Maria", "last_name": "Souza", "identification": { "type": "CPF", "number": "11122233344" }, "address": { "zip_code": "01000000", "street_name": "Rua Exemplo", "street_number": "123", "neighborhood": "Centro", "city": "São Paulo", "federal_unit": "SP" } }
         }'
```

**Exemplo de Resposta (Sucesso - PIX):**
```json
{
    "status": "success",
    "message": "Payment processed successfully.",
    "data": {
        "transaction_id": "d8f2b3a0-6b7a-4b1e-8b0a-1b2c3d4e5f6a",
        "status": "pending",
        "pix_qr_code": "...",
        "pix_qr_code_base64": "..."
    }
}
```

**Exemplo de Resposta (Erro de Validação):**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "amount": [
            "The amount must be a number."
        ]
    }
}
```

### Comandos Artisan

-   **Reprocessar Pagamentos Falhos:**
    ```bash
    php artisan payment:reprocess-failed --limit=10 --max-retries=3 --dry-run
    ```

---

## 🛡️ Segurança

### Configurando a Autenticação

A autenticação é configurada no arquivo `config/payment.php`. Você pode escolher uma das seguintes estratégias:

-   `none`: Nenhuma autenticação (use apenas em desenvolvimento).
-   `api_token`: Um token de API fixo, definido no arquivo de configuração.
-   `laravel_auth`: Usa o sistema de autenticação padrão do Laravel (ex: Sanctum).
-   `custom`: Permite que você defina um callback customizado para sua própria lógica de autenticação.

---

##  diagrams

### Fluxo de Pagamento

```mermaid
sequenceDiagram
    participant Client
    participant Your Application
    participant Payment Module
    participant Mercado Pago

    Client->>Your Application: 1. Request Payment (e.g., PIX)
    Your Application->>Payment Module: 2. Process Payment
    Payment Module->>Mercado Pago: 3. Create Payment
    Mercado Pago-->>Payment Module: 4. Return PIX Code
    Payment Module-->>Your Application: 5. Return Transaction ID & PIX Code
    Your Application-->>Client: 6. Display PIX Code
    Client->>Mercado Pago: 7. Pays PIX
    Mercado Pago->>Payment Module: 8. Webhook Notification (payment approved)
    Payment Module->>Your Application: 9. Dispatch Event (PaymentProcessed)
```

---

## 🤔 Troubleshooting (Problemas Comuns)

-   **Erro `InvalidConfigurationException`:**
    -   **Causa:** As credenciais do Mercado Pago não foram configuradas corretamente.
    -   **Solução:** Verifique se as variáveis `MERCADOPAGO_*` estão definidas no seu arquivo `.env` ou se foram salvas via API.

-   **Pagamentos falham com `401 Unauthorized`:**
    -   **Causa:** O middleware de autenticação está bloqueando a requisição.
    -   **Solução:** Certifique-se de que a estratégia de autenticação em `config/payment.php` está correta e que você está enviando o token de autorização no cabeçalho da requisição (`Authorization: Bearer SEU_TOKEN`).

---

## 🗺️ Roadmap e Contribuições

Este projeto é mantido ativamente. Contribuições são bem-vindas! Antes de contribuir, por favor, leia o arquivo `CONTRIBUTING.md` (a ser criado).

### 🔄 Versionamento Automático

Este projeto utiliza **versionamento semântico automático** baseado em [Conventional Commits](https://www.conventionalcommits.org/).

#### Como funciona:

1. **Commits seguem o padrão convencional:**
   - `feat:` - Nova funcionalidade (incrementa MINOR: 1.0.0 → 1.1.0)
   - `fix:` - Correção de bug (incrementa PATCH: 1.0.0 → 1.0.1)
   - `feat!:` ou `BREAKING CHANGE:` - Mudança que quebra compatibilidade (incrementa MAJOR: 1.0.0 → 2.0.0)

2. **Ao fazer push para `main`:**
   - Testes são executados automaticamente
   - Versão é calculada baseada nos commits
   - `README.md` e `composer.json` são atualizados
   - Changelog é gerado automaticamente
   - Tag Git e GitHub Release são criados

3. **Exemplo de uso:**
   ```bash
   # Feature nova (minor bump)
   git commit -m "feat(pix): adicionar suporte a QR code dinâmico"
   
   # Bug fix (patch bump)
   git commit -m "fix(webhook): corrigir validação de assinatura"
   
   # Breaking change (major bump)
   git commit -m "feat!: migrar para API v2 do Mercado Pago"
   ```

📖 **[Ver guia completo de commits convencionais](.github/COMMIT_CONVENTION.md)**

#### Versionamento Semântico

Este projeto segue o [Versionamento Semântico 2.0.0](https://semver.org/spec/v2.0.0.html). 

- **MAJOR** (X.0.0): Mudanças incompatíveis com versões anteriores
- **MINOR** (0.X.0): Novas funcionalidades mantendo compatibilidade
- **PATCH** (0.0.X): Correções de bugs mantendo compatibilidade

Para ver as mudanças detalhadas de cada versão, consulte as [GitHub Releases](https://github.com/uendelsilveira/payment-module-manager/releases).

### Próximos Passos
- Criação de `CONTRIBUTING.md`.
- Integração com Codecov e GitHub Actions para relatórios de cobertura e build status.
- Configuração de análise estática com PHPStan/Psalm.
- Suporte a Docker para um ambiente de desenvolvimento padronizado.

---

## 📄 Licença

Este projeto está licenciado sob a Licença MIT.

© 2025 Uendel Silveira - Full Stack Developer
