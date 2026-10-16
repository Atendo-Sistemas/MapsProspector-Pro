#!/bin/bash
# Aplicação única e definitiva da exportação Webhook v2
set -e

APP=/home/naciona4/prospec.atendo.log.br
PHP=/usr/bin/php
GREP=/bin/grep

EXPECTED_SIZE_BULK=6723
EXPECTED_SIZE_REPLACE=7656

# ---------- 1. Deduplicar o index.php ----------
echo "==> 1. Corrigindo index.php"
# Remove linha 598 (duplicada)
sed -i '598d' "$APP/index.php"
# Remove o script duplicado que ficou junto na linha 467
LINE467=$(sed -n '467p' "$APP/index.php")
case "$LINE467" in
  *export-replace*export-replace*)
    NEW=$(printf '%s' "$LINE467" | sed 's|<script src="assets/js/export-replace.js?v=20260816-200"></script><script src="assets/js/export-replace.js?v=20260816-200"></script></body>|<script src="assets/js/export-replace.js?v=20260816-200"></script></body>|')
    sed -i "467s|.*|$NEW|" "$APP/index.php"
    ;;
esac
# Remove também qualquer linha antiga de export-patch (opcional, seguro)
$GREP -n "export-patch\|export-replace" "$APP/index.php" || echo "(nenhuma linha encontrada)"

# ---------- 2. Baixar arquivos v2 com verificação de tamanho ----------
echo "==> 2. Baixando arquivos"
curl -sS -o "$APP/api/export-bulk.php" 'https://files.manuscdn.com/user_upload_by_module/session_file/310519663175087169/tRNokdLMKfQbcgAq.php'
curl -sS -o "$APP/assets/js/export-replace.js" 'https://files.manuscdn.com/user_upload_by_module/session_file/310519663175087169/ksWBoDJWbYBFawRU.js'

# ---------- 3. Verificação ----------
echo "==> 3. Verificando"
$PHP -l "$APP/api/export-bulk.php"
SIZE_BULK=$(wc -c < "$APP/api/export-bulk.php" | tr -d ' ')
SIZE_REPLACE=$(wc -c < "$APP/assets/js/export-replace.js" | tr -d ' ')
echo "export-bulk.php tamanho: $SIZE_BULK (esperado $EXPECTED_SIZE_BULK)"
echo "export-replace.js tamanho: $SIZE_REPLACE (esperado $EXPECTED_SIZE_REPLACE)"
COUNT_BULK=$($GREP -c "webhook_exports" "$APP/api/export-bulk.php")
COUNT_REPLACE=$($GREP -c "EXPORT_REPLACE_V2" "$APP/assets/js/export-replace.js")
echo "webhook_exports no export-bulk.php: $COUNT_BULK (esperado 3)"
echo "EXPORT_REPLACE_V2 no export-replace.js: $COUNT_REPLACE (esperado 2)"

if [ "$SIZE_BULK" = "$EXPECTED_SIZE_BULK" ] && [ "$SIZE_REPLACE" = "$EXPECTED_SIZE_REPLACE" ] && [ "$COUNT_BULK" = "3" ] && [ "$COUNT_REPLACE" = "2" ]; then
  echo "==> SUCESSO: v2 aplicado corretamente."
else
  echo "==> ATENCAO: alguma verificacao nao bateu. Envie o resultado acima para o Manus."
fi
