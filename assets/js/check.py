import sys

try:
    with open('app.js', 'r') as f:
        text = f.read()
except Exception as e:
    print "Nao consegui ler o arquivo app.js:", e
    sys.exit(1)

stack = []
line_num = 1
in_string = False
string_char = ''
in_line_comment = False
in_block_comment = False
escape = False

i = 0
while i < len(text):
    char = text[i]
    if char == '\n':
        line_num += 1
        in_line_comment = False

    if in_line_comment or in_block_comment:
        if in_block_comment and char == '/' and i > 0 and text[i-1] == '*':
            in_block_comment = False
        i += 1
        continue

    if in_string:
        if escape:
            escape = False
        elif char == '\\':
            escape = True
        elif char == string_char:
            in_string = False
        i += 1
        continue

    if char in '"\'`':
        in_string = True
        string_char = char
        i += 1
        continue

    if char == '/' and i < len(text)-1:
        if text[i+1] == '/':
            in_line_comment = True
        elif text[i+1] == '*':
            in_block_comment = True
        i += 1
        continue

    pairs = {'}': '{', ']': '[', ')': '('}
    if char in '{[(':
        stack.append((char, line_num))
    elif char in '}])':
        if len(stack) == 0:
            pass
        else:
            last_open, open_line = stack[-1]
            if last_open == pairs[char]:
                stack.pop()

    i += 1

if len(stack) > 0:
    print "\n[X] ENCONTRADO! O codigo parou esperando o fechamento:"
    for item in stack:
        print " -> '", item[0], "' aberto na linha", item[1], "e NUNCA foi fechado."
else:
    print "\n[V] Sucesso! Nenhuma chave ou parentese ficou aberto no app.js."
