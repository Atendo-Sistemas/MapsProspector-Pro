# Revisão e operação segura

Revisão do código em 16/10/2026; não substitui auditoria de produção ou teste de invasão.

## Alterações desta revisão

- As APIs de autenticação e busca não exibem erros PHP. Respostas de erro não incluem stack traces, caminhos locais ou mensagens de conexão do banco; detalhes operacionais são enviados ao log do servidor.
- A conexão com o banco retorna mensagens genéricas ao cliente e registra a causa somente no log.
- A criptografia de chaves não usa mais uma chave padrão fixa. Se não houver uma chave configurada ou persistida, a inicialização falha em vez de cifrar dados com um segredo conhecido.
- Foram removidos os endpoints de diagnóstico/debug e os criadores legados de links InfinitePay que continham credenciais de banco embutidas.
- A busca Maps informa que os dados vieram do fluxo de coleta integrado ao Google Maps e orienta a confirmar telefone e endereço antes do contato.

## Ações operacionais obrigatórias

1. **Rotacione credenciais que já foram commitadas.** Remover um segredo do estado atual não o remove do histórico Git nem de cópias/implantações antigas. Troque credenciais de banco e quaisquer chaves que tenham sido expostas em versões anteriores; atualize somente o arquivo privado de configuração ou o ambiente do servidor.
2. Configure `ENCRYPTION_KEY` ou preserve `/storage/encryption_key` em backup protegido. Não apague nem regenere essa chave enquanto houver dados criptografados que precisem ser lidos.
3. Mantenha `.env` fora do controle de versão; use `.env.example` apenas como modelo sem valores secretos.
4. Mantenha `display_errors` desativado em produção e proteja os logs contra acesso HTTP. Não reative rotas diagnósticas com saída de configuração, sessão, dados do banco ou conteúdo de arquivos.
5. Após o deploy, valide login, busca Maps, pesquisa CNPJ, exportação e pagamento em homologação. Confirme isolamento de dados entre empresas e se os valores debitados correspondem aos leads novos.
6. Acompanhe logs do PHP/servidor, métricas do Dashboard e consumo de cada provedor. As métricas Apify não representam o coletor Maps privado.

## Testes automatizados

Execute `php tests/security-regression.php` para verificar as regras básicas de URL de webhook e a criptografia de chaves. Fluxos de login, cobrança, provedores externos e isolamento multi-tenant dependem do banco e de serviços configurados; devem ser validados em homologação com contas de teste separadas.

