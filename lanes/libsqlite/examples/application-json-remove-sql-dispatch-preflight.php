<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonRemove;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$function = $argv[1] ?? 'JSONB_REMOVE';
$input = $argv[2] ?? '{"plugin":{"enabled":true,"legacyToken":"secret","rules":[{"name":"seo"},{"name":"cache"}]},"keep":1}';
$paths = array_slice($argv, 3);
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

$result = SQLiteJsonRemove::removeSqlFunctionArguments($function, [$inputValue, ...$paths]);

echo json_encode([
    'function' => $function,
    'inputKind' => $inputKind,
    'paths' => $paths,
    'removedRoot' => $result === null,
    'resultType' => $result instanceof SQLiteBlobValue ? 'sqlite-jsonb-blob' : 'json-text-or-sql-null',
    'jsonAfter' => $result instanceof SQLiteBlobValue ? SQLiteJsonB::decode($result->bytes) : $result,
    'jsonbHexAfter' => $result instanceof SQLiteBlobValue ? bin2hex($result->bytes) : null,
    'applicationUse' => 'Local-only wp_options cleanup preflight that preserves SQLite json_remove() text results versus jsonb_remove() JSONB blob results through SQL argument-vector dispatch before import.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
