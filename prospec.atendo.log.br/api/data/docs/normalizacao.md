# Normalização de Termos

## Processo de Normalização
Cada termo passa por um pipeline de normalização que gera múltiplas variantes para garantir cobertura máxima em buscas:

1. **Original**: Texto exatamente como fornecido
2. **Minúscula**: Todo o texto em letras minúsculas
3. **Sem acento**: Remoção de acentuação (á → a, é → e, etc.)
4. **Sem caracteres especiais**: Remoção de caracteres não alfanuméricos
5. **Sem hífen**: Substituição de hífens por espaços
6. **Sem espaços duplicados**: Colapso de múltiplos espaços
7. **Full-text**: Versão otimizada para busca de texto completo

## Exemplo
| Variante | Resultado |
|----------|-----------|
| Original | "Comércio Atacadista de Café em Grão" |
| Minúscula | "comércio atacadista de café em grão" |
| Sem acento | "Comercio Atacadista de Cafe em Grao" |
| Sem especiais | "comercio atacadista de cafe em grao" |
| Sem hífen | "comercio atacadista de cafe em grao" |
| Full-text | "comercio atacadista de cafe em grao" |
