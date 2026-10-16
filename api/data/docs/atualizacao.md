# Atualização e Versionamento

## Versão Atual
- Versão: 1.0.0
- Base: CNAE 2.0 (versão atualizada pelo IBGE)
- Data de geração: Ver metadata.json

## Processo de Atualização
1. Baixar nova versão da tabela CNAE no site do IBGE/Concla
2. Comparar com a versão anterior usando diff
3. Atualizar apenas os registros alterados
4. Manter compatibilidade com versões anteriores
5. Incrementar versão no metadata.json e CHANGELOG.md

## Compatibilidade
Nenhuma atualização futura deve quebrar a compatibilidade com versões anteriores. Novos campos serão adicionados como opcionais.
