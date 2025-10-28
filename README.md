# 💳 Payment Module Manager

Pacote **Laravel** para gerenciamento centralizado de pagamentos — atualmente com suporte ao **Mercado Pago**.  
Projetado para ser **plugável**, **extensível** e **fácil de integrar** em qualquer aplicação Laravel.

---

## ✨ Funcionalidades

- **Integração com Mercado Pago:** Processa pagamentos via API (PIX e Cartão de Crédito).
- **Gerenciamento de Credenciais via API:** Permite salvar e atualizar credenciais dos gateways via endpoints de API, com armazenamento seguro no banco de dados.
- **Conexão OAuth 2.0 (Mercado Pago Connect):** Fluxo seguro de autenticação e autorização para vincular contas do Mercado Pago de usuários finais.
- **Arquitetura Modular:** Separação clara entre `Providers`, `Controllers`, `Services`, `Repositories` e `Strategies`.
- **Validação de Requisições:** Validação robusta das entradas antes de processar pagamentos.
- **Persistência de Transações:** Armazena todos os detalhes das transações.
- **Respostas Padronizadas:** Uso do `ApiResponseTrait` para respostas JSON consistentes.
- **Segurança de Webhooks:** Verificação de assinatura em notificações do Mercado Pago.

---

## 📦 Instalação

Instale o pacote via Composer:

```bash
composer require uendelsilveira/payment-module-manager
```

> 💡 **Caso o pacote ainda não esteja publicado no [Packagist](https://packagist.org/)**, adicione o repositório GitHub no seu `composer.json` antes de executar o comando acima:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/uendelsilveira/payment-module-manager.git"
    }
]
```

---

## ⚙️ Configuração

### Publicar o arquivo de configuração

```bash
php artisan vendor:publish --provider="UendelSilveira\\PaymentModuleManager\\Providers\\PaymentServiceProvider" --tag="config"
```

Isso criará o arquivo `config/payment.php`, onde você pode definir as credenciais padrão do Mercado Pago.

---

### Variáveis de Ambiente

Adicione as seguintes variáveis ao seu `.env`:

```dotenv
MERCADOPAGO_PUBLIC_KEY="SEU_PUBLIC_KEY"
MERCADOPAGO_ACCESS_TOKEN="SEU_ACCESS_TOKEN"
MERCADOPAGO_WEBHOOK_SECRET="SEU_WEBHOOK_SECRET"

MERCADOPAGO_CLIENT_ID="SEU_CLIENT_ID"
MERCADOPAGO_CLIENT_SECRET="SEU_CLIENT_SECRET"
```

> ⚠️ **Importante:**
> - Use credenciais de **teste** em ambientes de desenvolvimento.
> - As variáveis `CLIENT_ID` e `CLIENT_SECRET` pertencem à **sua aplicação**, e não ao usuário final.
> - Essas credenciais são usadas como **fallback** se nenhuma configuração for encontrada no banco de dados.

---

### Migrações

Execute as migrações para criar as tabelas necessárias:

```bash
php artisan migrate
```

Tabelas criadas:
- `transactions`
- `payment_settings`

---

## 🚀 Uso

### 📘 Documentação da API (OpenAPI/Swagger)

A documentação completa dos endpoints está disponível em formato OpenAPI.  
Você pode visualizá-la no [Swagger Editor](https://editor.swagger.io/):

📄 [**Abrir documentação (openapi.yaml)**](./docs/openapi.yaml)

---

### Endpoints de Pagamento

#### `POST /api/payment/process`

##### Exemplo (PIX)

```json
{
  "amount": 199.90,
  "method": "mercadopago",
  "description": "Assinatura Premium",
  "payer_email": "cliente@example.com",
  "payment_method_id": "pix"
}
```

##### Exemplo (Cartão de Crédito)

```json
{
  "amount": 199.90,
  "method": "mercadopago",
  "description": "Assinatura Premium",
  "payer_email": "cliente@example.com",
  "payment_method_id": "credit_card",
  "token": "...",
  "installments": 1,
  "issuer_id": "...",
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

---

### Endpoints de Configuração

#### `GET /api/settings/mercadopago`
Retorna as credenciais salvas do Mercado Pago.

#### `POST /api/settings/mercadopago`
Salva ou atualiza as credenciais no banco de dados.

##### Exemplo:
```json
{
  "public_key": "APP_USR-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "access_token": "APP_USR-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "webhook_secret": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
}
```

---

### Endpoints de Conexão (OAuth 2.0)

#### `GET /api/connect/mercadopago`
Redireciona o usuário para a tela de autorização do Mercado Pago.

#### `GET /api/connect/mercadopago/callback`
Recebe o código de autorização, troca por `access_token` e `public_key`, e armazena no banco de dados.

---

## 🧪 Testes

1. Instale as dependências de desenvolvimento:
    ```bash
    composer update
    ```

2. Configure credenciais de teste no `phpunit.xml`:
    ```xml
    <php>
        <env name="MERCADOPAGO_ACCESS_TOKEN" value="SEU_ACCESS_TOKEN_DE_TESTE"/>
        <env name="MERCADOPAGO_WEBHOOK_SECRET" value="SEU_WEBHOOK_SECRET_DE_TESTE"/>
        <env name="MERCADOPAGO_CLIENT_ID" value="SEU_CLIENT_ID_DE_TESTE"/>
        <env name="MERCADOPAGO_CLIENT_SECRET" value="SEU_CLIENT_SECRET_DE_TESTE"/>
    </php>
    ```

3. Execute os testes:
    ```bash
    composer test
    ```

---

## 🗺️ Roadmap

### Próximos gateways planejados
- [ ] **Pagar.me**
- [ ] **Stripe**
- [ ] **PayPal**
- [ ] **Pix via diferentes provedores**
- [ ] **Adyen** (corporativo)

### Próximas funcionalidades
- [ ] **Emissão de Nota Fiscal** integrada com provedores nacionais.
- [ ] **Painel administrativo Filament** para visualizar transações e credenciais.
- [ ] **Suporte multi-tenant nativo**.
- [ ] **Webhook universal** para múltiplos gateways.
- [ ] **Sistema de notificações** em tempo real (WebSockets / Pusher).

---

## 📄 Licença

Licenciado sob a [MIT License](LICENSE).  
© 2025 **Uendel Silveira** — Full Laravel Developer
