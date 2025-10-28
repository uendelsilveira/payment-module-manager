<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

# 💳 Payment Module Manager (Mercado Pago Only)

Um pacote Laravel para gerenciar pagamentos, atualmente focado na integração com o Mercado Pago. Projetado para ser plugável e fácil de usar em qualquer aplicação Laravel.

---

## ✨ Funcionalidades

-   **Integração com Mercado Pago:** Processa pagamentos via API do Mercado Pago (PIX e Cartão de Crédito).
-   **Gerenciamento de Credenciais via API:** Permite que as credenciais do gateway sejam salvas e gerenciadas através de endpoints de API, armazenando-as no banco de dados.
-   **Conexão OAuth 2.0 (Mercado Pago Connect):** Facilita a conexão da conta do Mercado Pago do usuário final através de um fluxo de autorização seguro.
-   **Estrutura Modular:** Separação clara de responsabilidades usando Service Providers, Controllers, Services, Repositories e Estratégias de Gateway.
-   **Validação de Requisições:** Validação robusta de dados de entrada para o processamento de pagamentos.
-   **Persistência de Transações:** Armazena detalhes das transações em um banco de dados.
-   **Respostas Padronizadas:** Utiliza um `ApiResponseTrait` para respostas JSON consistentes.
-   **Segurança de Webhooks:** Verificação de assinatura para notificações do Mercado Pago.

---

## 📦 Instalação

Para usar este pacote em seu projeto Laravel, adicione-o via Composer:

```bash
composer require uendelsilveira/payment-module-manager
```

**Nota:** Se o pacote ainda não estiver publicado no [Packagist](https://packagist.org/), você precisará adicionar o repositório do GitHub ao seu `composer.json` antes de executar o comando acima:

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

Publique o arquivo de configuração do pacote para sua aplicação:

```bash
php artisan vendor:publish --provider="UendelSilveira\PaymentModuleManager\Providers\PaymentServiceProvider" --tag="config"
```

Isso criará um arquivo `config/payment.php` onde você pode definir suas credenciais do Mercado Pago.

### Variáveis de Ambiente

Adicione as seguintes variáveis ao seu arquivo `.env`. Estas variáveis funcionarão como um **fallback** se nenhuma configuração for encontrada no banco de dados, e são essenciais para o fluxo de conexão OAuth.

```dotenv
MERCADOPAGO_PUBLIC_KEY="SEU_PUBLIC_KEY_DE_TESTE_OU_PRODUCAO"
MERCADOPAGO_ACCESS_TOKEN="SEU_ACCESS_TOKEN_DE_TESTE_OU_PRODUCAO"
MERCADOPAGO_WEBHOOK_SECRET="SEU_WEBHOOK_SECRET_DE_TESTE_OU_PRODUCAO"

MERCADOPAGO_CLIENT_ID="SEU_CLIENT_ID_DA_APLICACAO"
MERCADOPAGO_CLIENT_SECRET="SEU_CLIENT_SECRET_DA_APLICACAO"
```

**Importante:** Use sempre credenciais de teste para ambientes de desenvolvimento e teste. As credenciais `CLIENT_ID` e `CLIENT_SECRET` são da **sua aplicação**, não do usuário final.

### Migrações

Execute as migrações para criar as tabelas `transactions` e `payment_settings`:

```bash
php artisan migrate
```

---

## 🚀 Uso

### Documentação da API (OpenAPI/Swagger)

Uma documentação detalhada da API, incluindo todos os endpoints, parâmetros e exemplos de resposta, está disponível no formato OpenAPI. Você pode visualizar este arquivo usando qualquer ferramenta compatível com OpenAPI, como o [Swagger Editor](https://editor.swagger.io/).

[**Ver a Documentação da API (openapi.yaml)**](./docs/openapi.yaml)

### Endpoints de Pagamento

`POST /api/payment/process`

#### Exemplo de Requisição (PIX)

```json
{
  "amount": 199.90,
  "method": "mercadopago",
  "description": "Assinatura Premium",
  "payer_email": "cliente@example.com",
  "payment_method_id": "pix"
}
```

#### Exemplo de Requisição (Cartão de Crédito)

```json
{
  "amount": 199.90,
  "method": "mercadopago",
  "description": "Assinatura Premium",
  "payer_email": "cliente@example.com",
  "payment_method_id": "credit_card",
  "token": "...", // Token gerado pelo frontend
  "installments": 1,
  "issuer_id": "...", // ID do emissor do cartão
  "payer": {
    "first_name": "João",
    "last_name": "Silva",
    "identification": {
      "type": "CPF",
      "number": "..."
    }
  }
}
```

### Endpoints de Configuração

`GET /api/settings/mercadopago`

Retorna as credenciais do Mercado Pago atualmente salvas no banco de dados.

`POST /api/settings/mercadopago`

Salva ou atualiza as credenciais do Mercado Pago no banco de dados.

#### Exemplo de Requisição

```json
{
  "public_key": "APP_USR-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "access_token": "APP_USR-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "webhook_secret": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
}
```

### Endpoints de Conexão (OAuth 2.0)

`GET /api/connect/mercadopago`

Redireciona o usuário para a página de autorização do Mercado Pago. Após a autorização, o Mercado Pago redirecionará para o endpoint de callback.

`GET /api/connect/mercadopago/callback`

Endpoint de callback que recebe o código de autorização do Mercado Pago, troca-o por um `access_token` e `public_key` e os salva no banco de dados.

---

## 🧪 Testes

Para executar os testes unitários e de feature do pacote:

1.  Certifique-se de ter as dependências de desenvolvimento instaladas:
    ```bash
    composer update
    ```
2.  Configure suas credenciais de teste do Mercado Pago **no arquivo `phpunit.xml`** (na raiz do pacote) para o ambiente de teste:
    ```xml
    <!-- phpunit.xml -->
    <php>
        <env name="MERCADOPAGO_ACCESS_TOKEN" value="SEU_ACCESS_TOKEN_DE_TESTE"/>
        <env name="MERCADOPAGO_WEBHOOK_SECRET" value="SEU_WEBHOOK_SECRET_DE_TESTE"/>
        <env name="MERCADOPAGO_CLIENT_ID" value="SEU_CLIENT_ID_DE_TESTE"/>
        <env name="MERCADOPAGO_CLIENT_SECRET" value="SEU_CLIENT_SECRET_DE_TESTE"/>
    </php>
    ```
3.  Execute os testes:
    ```bash
    composer test
    ```

---

## 📄 Licença

Este projeto está licenciado sob a Licença MIT.

© 2025 Uendel Silveira - Full Laravel Developer
