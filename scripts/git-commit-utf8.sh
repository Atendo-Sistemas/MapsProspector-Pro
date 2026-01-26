#!/bin/bash
# Script Bash para fazer commits com encoding UTF-8 correto
# Uso: ./scripts/git-commit-utf8.sh "sua mensagem de commit"

MESSAGE="$1"

if [ -z "$MESSAGE" ]; then
    echo "Erro: Forneça uma mensagem de commit"
    exit 1
fi

# Configura encoding UTF-8
export LANG=pt_BR.UTF-8
export LC_ALL=pt_BR.UTF-8

# Cria arquivo temporário com a mensagem
TEMP_FILE=".commit-msg-temp"
echo -n "$MESSAGE" > "$TEMP_FILE"

# Faz o commit usando o arquivo
git commit -F "$TEMP_FILE"

# Remove arquivo temporário
rm -f "$TEMP_FILE"

echo "Commit realizado com encoding UTF-8!"
