<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonRemove;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$input = $argv[1] ?? '{"plugin":{"enabled":true,"legacyToken":"secret","rules":[{"name":"seo"},{"name":"cache"}]},"keep":1}';
$paths = array_slice($argv, 2);
if ($paths === []) {
    $paths = ['$.plugin.legacyToken', '$.plugin.rules[0]'];
}

if (str_starts_with($input, 'hex:')) {
    $bytes = hex2bin(substr($input, 4));
    if (!is_string($bytes)) {
        throw new InvalidArgumentException('JSONB hex input is malformed');
    }
    $inputValue = new SQLiteBlobValue($bytes);
    $inputKind = SQLiteJsonB::isSuperficiallyJsonB($bytes) ? 'sqlite-jsonb-hex' : 'cast-text-blob-hex';
} else {
    $inputValue = $input;
    $inputKind = 'json-or-json5-text';
}

$removed = SQLiteJsonRemove::remove($inputValue, ...$paths);

echo json_encode([
    'inputKind' => $inputKind,
    'paths' => $paths,
    'removedRoot' => $removed === null,
    'jsonAfter' => $removed,
    'applicationUse' => 'Local-only wp_options option_value cleanup that mirrors SQLite json_remove() text-result behavior for copied JSON, JSON5, or JSONB option fixtures before import.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
