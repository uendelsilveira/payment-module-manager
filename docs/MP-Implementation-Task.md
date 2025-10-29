# 💳 Mercado Pago - Plano de Implementação e Melhorias (Revisado)

*Este documento foi atualizado após uma análise detalhada do código em 29/10/2025. Ele substitui a lista de tarefas anterior e reflete as próximas ações para aprimorar o módulo.*

---

## Tarefa 1: Implementar Endpoint de Consulta de Pagamento (Alta Prioridade)

**Por que:** Atualmente, o status de um pagamento só é atualizado via webhook (assíncrono). É crucial para a aplicação cliente ter um meio de consultar o status de uma transação em tempo real (síncrono), por exemplo, para exibir uma tela de confirmação de compra ou para fins de reconciliação.

**Plano de Ação:**
1.  **Rota:** Adicionar a rota `GET /api/payments/{transaction}` ao arquivo de rotas do pacote.
2.  **Controller:** Criar um método `show(Transaction $transaction)` no `PaymentController.php`.
3.  **Serviço:** Implementar um método `getPaymentDetails(Transaction $transaction)` no `PaymentService.php`.
    - Este método irá chamar o gateway para buscar o status mais recente.
    - Irá comparar o status do gateway com o status local e, se diferente, atualizar o banco de dados.
4.  **Gateway:**
    - Adicionar um método `getPayment(string $externalPaymentId)` à interface `PaymentGatewayInterface` e à `MercadoPagoStrategy`.
    - A implementação na `MercadoPagoStrategy` chamará o `MercadoPagoClient` para fazer uma requisição `GET` ao endpoint `v1/payments/{id}` da API do Mercado Pago.
5.  **Documentação:** Atualizar o `openapi.yaml` e o `README.md` para incluir o novo endpoint.
6.  **Testes:** Criar testes de feature para validar o funcionamento do endpoint.

---

## Tarefa 2: Corrigir Falha Silenciosa no Reprocessamento (Média Prioridade)

**Por que:** O comando Artisan `payment:reprocess-failed` não reporta erros corretamente. Se uma tentativa de reprocessamento falhar, o erro é capturado e logado, mas não é relançado. Isso faz com que o comando termine com status de sucesso, dando uma falsa impressão de que todos os pagamentos foram reprocessados com êxito.

**Plano de Ação:**
1.  **Serviço:** No método `reprocess` do `PaymentService.php`, localizar o bloco `catch (Throwable $e)`.
2.  **Modificação:** Após logar o erro e atualizar o contador de tentativas, relançar a exceção (`throw $e;`).
3.  **Comando:** Ajustar o comando Artisan que chama este serviço para capturar a exceção e exibir uma mensagem de erro clara no console, indicando qual transação específica falhou ao ser reprocessada.
4.  **Testes:** Adaptar os testes do comando para garantir que ele falhe (retorne um código de saída diferente de 0) quando uma exceção ocorrer.

---

## Tarefa 3: Refatorar a Estratégia do Gateway (Média Prioridade) - **CONCLUÍDA**

**Por que:** O método `charge` na `MercadoPagoStrategy` está se tornando grande e contém valores fixos ("hardcoded"), como `'visa'` e `'bolbradesco'`. Isso limita a flexibilidade e dificulta a manutenção. A refatoração tornará o código mais limpo, flexível e aderente aos princípios de software.

**Plano de Ação:**
1.  **Remover Valores Fixos:**
    - Modificar a lógica para que o `payment_method_id` de cartões de crédito (ex: `visa`, `master`, `elo`) seja recebido dinamicamente da requisição, em vez de ser fixado como `'visa'`.
    - O mesmo se aplica ao boleto, permitindo flexibilidade futura.
2.  **Dividir o Método `charge`:**
    - Criar métodos privados e específicos dentro da `MercadoPagoStrategy` para construir o payload de cada tipo de pagamento (ex: `private function buildCreditCardPayload(array $data): array`).
    - O método `charge` principal se tornará mais limpo, atuando como um despachante que chama o método de construção apropriado com base no `payment_method_id`.
3.  **Testes:** Revisar e garantir que todos os testes de pagamento continuem passando após a refatoração.

---

## Tarefa 4: Documentação Contínua

**Por que:** A documentação é um componente vital do pacote e deve sempre refletir a implementação atual.

**Plano de Ação:**
- Após a conclusão de cada tarefa acima, garantir que tanto o `README.md` quanto o `openapi.yaml` sejam atualizados para refletir as novas funcionalidades, correções e quaisquer mudanças nas estruturas de requisição/resposta.
