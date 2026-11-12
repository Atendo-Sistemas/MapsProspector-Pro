# Sistema de Relevância

## Escala de Relevância
A relevância é um valor inteiro de 0 a 100 que indica a força da associação entre um termo e um CNAE.

| Faixa | Significado | Exemplo |
|-------|-------------|---------|
| 95-100 | Match exato | Nome oficial da subclasse |
| 85-94 | Forte associação | Descrição completa |
| 75-84 | Associação direta | Palavra-chave principal |
| 60-74 | Associação indireta | Sinônimos e variantes |
| 45-59 | Associação fraca | Termos populares |
| 30-44 | Associação mínima | Regionalismos |
| 1-29 | Referência distante | Termos relacionados |

## Atualização de Relevância
A relevância pode ser ajustada automaticamente com base em:
- Frequência de busca do termo
- Taxa de conversão em classificações
- Feedback de usuários
- Análise de correspondência semântica
