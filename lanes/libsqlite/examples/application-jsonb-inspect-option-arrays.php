<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJson5Parser;
use PortLibs\LibSqlite\SQLiteJsonB;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$input = $argv[1] ?? null;
$paths = array_slice($argv, 2);

if ($input === null || $paths === []) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-jsonb-inspect-option-arrays.php json-or-hex path [path...]\n");
    fwrite(STDERR, "Checks SQLite JSONB option/meta migration array paths with json_type/json_array_length semantics.\n");
    exit(1);
}

$decodeJsonInput = static function (string $text): mixed {
    try {
        return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return SQLiteJson5Parser::decode($text);
    }
};

if (str_starts_with($input, 'hex:')) {
    $jsonb = hex2bin(substr($input, 4));
    if (!is_string($jsonb)) {
        throw new InvalidArgumentException('JSONB hex input is malformed');
    }

    $decoded = SQLiteJsonB::decode($jsonb);
    $inputKind = 'sqlite-jsonb-hex';
} else {
    $decoded = $decodeJsonInput($input);
    $jsonb = SQLiteJsonB::encode($decoded);
    $inputKind = 'json-or-json5';
}

$checks = [];
foreach ($paths as $path) {
    $type = SQLiteJsonB::type($jsonb, $path);
    $arrayLength = SQLiteJsonB::arrayLength($jsonb, $path);
    $checks[] = [
        'path' => $path,
        'type' => $type,
        'arrayLength' => $arrayLength,
        'status' => $type === 'array' ? 'array-ok' : ($type === null ? 'missing' : 'not-array'),
    ];
}

echo json_encode([
    'inputKind' => $inputKind,
    'decodedValue' => $decoded,
    'checks' => $checks,
    'applicationUse' => 'Preflight wp_options option arrays and postmeta migration queues stored as SQLite JSONB before import or migration tools append or reorder entries.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
