# Correções aplicadas — Asaas e créditos

## Alterações

1. **Bloqueio de saldo no backend (`api/search.php`)**
   - Valida plano, bônus e consumo antes de chamar o coletor.
   - Retorna HTTP 403 com `credits_exhausted` quando o saldo acaba.
   - Limita a coleta ao saldo disponível e corta respostas do provedor que excedam esse saldo.
   - Mantém a conta superadministradora isenta.

2. **Unificação do CNPJ (`api/cnpj-search.php`)**
   - Removido o uso das colunas legadas `tenants.tokens_used` e `tenants.tokens_limit`.
   - O limite agora usa `plans.token_limit`, `tenant_usage.tokens_used` e `token_bonus`.
   - O consumo agora usa `incrementTenantUsage()`.

3. **Plano obrigatório após o freemium**
   - Bloqueadas solicitações de créditos avulsos para tenants no plano `freemium-20` em:
     - `api/create-payment.php`
     - `api/credit-requests.php`
     - `services/ManualPaymentRequest.php`
   - O usuário deve contratar um plano.

4. **Schema completo**
   - `Database/maps_schema_full.sql` agora cria o plano `freemium-20` com 20 créditos.
   - Incluída a tabela `free_signup_credit_claims` para impedir repetição do benefício.
   - Bancos existentes ainda devem executar as migrações correspondentes.

5. **Vencimento após confirmação do pagamento**
   - `services/AsaasSubscriptionService.php` agora prioriza `paymentDate`, `confirmedDate`, `clientPaymentDate` e `creditDate` enviados pelo Asaas.
   - A data prevista (`dueDate`) não é mais usada como data de compra.
   - Após a confirmação, `next_due_date`, `access_until` e `tenants.plan_access_until` são recalculados a partir da data efetiva do pagamento.
   - Uma nova compra substitui o vencimento anterior, evitando que uma data antiga futura impeça a renovação.

## Validação

- Lint PHP executado em 100 arquivos: sem erros de sintaxe.
- Teste do cálculo: compra em 16/11/2026 gera próxima cobrança em 16/12/2026 e acesso até 15/12/2026.
- Não foi realizada cobrança real no Asaas; o teste de produção deve ser feito com credenciais sandbox e webhook acessível.

## Implantação

Para banco novo, execute `Database/maps_schema_full.sql`.

Para banco existente, execute `Database/database_migration_freemium.sql` e as demais migrações já previstas pelo projeto antes de publicar os arquivos PHP corrigidos.
