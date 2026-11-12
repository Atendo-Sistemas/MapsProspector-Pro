# Script PowerShell para fazer commits com encoding UTF-8 correto
# Uso: .\scripts\git-commit-utf8.ps1 "sua mensagem de commit"

param(
    [Parameter(Mandatory=$true)]
    [string]$Message
)

# Configura encoding UTF-8
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8
chcp 65001 | Out-Null

# Cria arquivo temporário com a mensagem em UTF-8 SEM BOM (evita ﻿ e corrupção da mensagem no Git)
$tempFile = Join-Path $PSScriptRoot "..\.commit-msg-temp"
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($tempFile, $Message, $utf8NoBom)

# Faz o commit usando o arquivo
git commit -F $tempFile

# Remove arquivo temporário
Remove-Item $tempFile -ErrorAction SilentlyContinue

Write-Host "Commit realizado com encoding UTF-8!" -ForegroundColor Green
