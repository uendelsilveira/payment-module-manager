# 💳 Payment Module Manager

Um pacote Laravel para gerenciar pagamentos, atualmente focado na integração com o Mercado Pago. Projetado para ser plugável e fácil de usar em qualquer aplicação Laravel.

---

## ✨ Funcionalidades

-   **Integração com Mercado Pago:** Processa pagamentos via API do Mercado Pago (atualmente configurado para PIX).
-   **Estrutura Modular:** Separação clara de responsabilidades usando Service Providers, Controllers, Services, Repositories e Estratégias de Gateway.
-   **Validação de Requisições:** Validação robusta de dados de entrada para o processamento de pagamentos.
-   **Persistência de Transações:** Armazena detalhes das transações em um banco de dados.
-   **Respostas Padronizadas:** Utiliza um `ApiResponseTrait` para respostas JSON consistentes.

---

## 📦 Instalação

Para usar este pacote em seu projeto Laravel, adicione-o via Composer:

```bash
composer require us/payment-module-manager
```

Se você estiver a desenvolver o pacote localmente, você deve configurá-lo como um repositório `vcs` no seu `composer.json`:

```json

{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/uendelsilveira/payment-module-manager.git"
        }
    ],
    "require": {
        "us/payment-module-manager": "@dev"
    }
}
```

Após adicionar, execute `composer update`.

O Service Provider do pacote será automaticamente descoberto pelo Laravel.

---

## ⚙️ Configuração

Publique o arquivo de configuração do pacote para sua aplicação:

```bash
php artisan vendor:publish --provider="Us\\PaymentModuleManager\\Providers\\PaymentServiceProvider" --tag="config"
```

Isso criará um arquivo `config/payment.php` onde você pode definir suas credenciais do Mercado Pago.

### Variáveis de Ambiente

Adicione as seguintes variáveis ao seu arquivo `.env`:

```dotenv
MERCADOPAGO_PUBLIC_KEY="SEU_PUBLIC_KEY_DE_TESTE_OU_PRODUCAO"
MERCADOPAGO_ACCESS_TOKEN="SEU_ACCESS_TOKEN_DE_TESTE_OU_PRODUCAO"
```

**Importante:** Use sempre credenciais de teste para ambientes de desenvolvimento e teste.

### Migrações

Execute as migrações para criar a tabela `transactions`:

```bash
php artisan migrate
```

---

## 🚀 Uso

O pacote expõe um endpoint de API para processar pagamentos.

### Endpoint

`POST /api/payment/process`

### Exemplo de Requisição (JSON Body)

```json
{
  "amount": 199.90,
  "method": "mercadopago",
  "description": "Assinatura Premium",
  "payer_email": "cliente@example.com"
}
```

### Exemplo de Resposta de Sucesso (Status 201 Created)

```json
{
  "success": true,
  "message": "Pagamento processado com sucesso.",
  "data": {
    "id": "123456789",
    "status": "pending",
    "transaction_amount": 199.90,
    "description": "Assinatura Premium",
    "payment_method_id": "pix",
    "status_detail": "pending_challenge",
    "external_resource_url": "data:image/png;base64,...",
    "metadata": [],
    "gateway": "mercadopago",
    "amount": "199.90",
    "currency": "BRL",
    "created_at": "2023-10-27T10:00:00.000000Z",
    "updated_at": "2023-10-27T10:00:00.000000Z"
  }
}
```

### Exemplo de Resposta de Erro (Status 422 Unprocessable Entity)

```json
{
  "success": false,
  "message": "Os dados fornecidos são inválidos.",
  "errors": {
    "amount": [
      "O campo amount é obrigatório."
    ]
  }
}
```

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
