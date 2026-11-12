<?php
/**
 * Debug: qual LinkedInSearchService o endpoint carrega de verdade.
 * Exclua este arquivo após o diagnóstico.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

$serviceFile = __DIR__ . '/../services/linkedinSearchService.php';
$serviceFileReal = realpath($serviceFile);
echo "Arquivo no disco: " . ($serviceFileReal ?: $serviceFile) . "\n";

if ($serviceFileReal) {
    $content = file_get_contents($serviceFileReal);
    echo "Tamanho: " . strlen($content) . " bytes\n";
    $pos = strpos($content, 'profileScraperMode');
    echo "profileScraperMode encontrado: " . ($pos !== false ? 'SIM (pos ' . $pos . ')' : 'NAO') . "\n";
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/linkedinSearchService.php';

$ref = new ReflectionClass('LinkedInSearchService');
echo "Arquivo carregado pela classe: " . $ref->getFileName() . "\n";

$m = $ref->getMethod('startProfileRun');
$src = file($m->getFileName());
$lines = array_slice($src, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1);
echo "Conteúdo de startProfileRun:\n" . implode('', $lines) . "\n";
