<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJson5Parser;
use PortLibs\LibSqlite\SQLiteJsonB;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$input = $argv[1] ?? null;
$paths = array_slice($argv, 2);
if ($input === null || $paths === []) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-jsonb-remove-option-field.php json-or-hex path [path...]\n");
    fwrite(STDERR, "Removes JSON paths from a SQLite JSONB wp_options option_value fixture and prints the resulting JSONB bytes.\n");
    exit(1);
}

if (str_starts_with($input, 'hex:')) {
    $jsonb = hex2bin(substr($input, 4));
    if (!is_string($jsonb)) {
        throw new InvalidArgumentException('JSONB hex input is malformed');
    }
    $decoded = SQLiteJsonB::decode($jsonb);
    $inputKind = 'sqlite-jsonb-hex';
} else {
    try {
        $decoded = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
        $inputKind = 'strict-json';
    } catch (JsonException) {
        $decoded = SQLiteJson5Parser::decode($input);
        $inputKind = 'sqlite-json5';
    }
    $jsonb = SQLiteJsonB::encode($decoded);
}

$removed = SQLiteJsonB::remove($jsonb, ...$paths);

echo json_encode([
    'inputKind' => $inputKind,
    'paths' => $paths,
    'removedRoot' => $removed === null,
    'decodedBefore' => $decoded,
    'decodedAfter' => $removed === null ? null : SQLiteJsonB::decode($removed),
    'sqliteJsonbHex' => $removed === null ? null : bin2hex($removed),
    'applicationUse' => 'Preflight or fixture-update JSONB wp_options blobs by removing obsolete plugin setting paths before indexed recovery checks.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
