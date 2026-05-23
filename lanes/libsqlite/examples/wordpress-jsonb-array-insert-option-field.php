<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJson5Parser;
use PortLibs\LibSqlite\SQLiteJsonB;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$input = $argv[1] ?? null;
$arguments = array_slice($argv, 2);

if ($input === null || count($arguments) < 2 || count($arguments) % 2 !== 0) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/wordpress-jsonb-array-insert-option-field.php json-or-hex path value [path value...]\n");
    fwrite(STDERR, "Applies SQLite jsonb_array_insert semantics to option/meta JSON migration arrays and prints the resulting JSONB bytes.\n");
    exit(1);
}

$decodeJsonInput = static function (string $text): mixed {
    try {
        return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return SQLiteJson5Parser::decode($text);
    }
};

$decodeValue = static function (string $text) use ($decodeJsonInput): mixed {
    if (str_starts_with($text, 'text:')) {
        return substr($text, 5);
    }
    if (str_starts_with($text, 'hex:')) {
        $bytes = hex2bin(substr($text, 4));
        if (!is_string($bytes)) {
            throw new InvalidArgumentException('JSONB value hex is malformed');
        }

        return SQLiteJsonB::decode($bytes);
    }

    try {
        return $decodeJsonInput($text);
    } catch (InvalidArgumentException) {
        return $text;
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

$path = array_shift($arguments);
$value = $decodeValue(array_shift($arguments));
$extraPairs = [];
while ($arguments !== []) {
    $extraPath = array_shift($arguments);
    $extraValue = $decodeValue(array_shift($arguments));
    $extraPairs[] = $extraPath;
    $extraPairs[] = $extraValue;
}

$mutated = SQLiteJsonB::arrayInsert($jsonb, $path, $value, ...$extraPairs);

echo json_encode([
    'inputKind' => $inputKind,
    'operation' => 'array_insert',
    'decodedBefore' => $decoded,
    'decodedAfter' => SQLiteJsonB::decode($mutated),
    'sqliteJsonbHex' => bin2hex($mutated),
    'wordpressUse' => 'Preflight wp_options option arrays or postmeta migration queues by inserting JSON elements with SQLite jsonb_array_insert path semantics without requiring the SQLite extension.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
