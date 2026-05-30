<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJson5Parser;
use PortLibs\LibSqlite\SQLiteJsonB;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

function displayJsonValue(mixed $value): mixed
{
    if (is_float($value)) {
        if (is_nan($value)) {
            return 'NaN';
        }
        if (!is_finite($value)) {
            return $value < 0 ? '-Infinity' : 'Infinity';
        }
    }
    if (is_array($value)) {
        $display = [];
        foreach ($value as $key => $item) {
            $display[$key] = displayJsonValue($item);
        }

        return $display;
    }

    return $value;
}

$jsonText = $argv[1] ?? null;
if ($jsonText === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-jsonb-option-fixture.php '{\"enabled\":true}'\n");
    fwrite(STDERR, "Encodes strict JSON or supported SQLite JSON5 text as a SQLite JSONB option_value fixture, including Infinity/-Infinity normalization and NaN-as-null behavior.\n");
    exit(1);
}

try {
    $value = json_decode($jsonText, true, 512, JSON_THROW_ON_ERROR);
    $input = 'strict JSON';
} catch (JsonException) {
    $value = SQLiteJson5Parser::decode($jsonText);
    $input = 'supported SQLite JSON5';
}

$jsonb = SQLiteJsonB::encode($value);

echo json_encode([
    'input' => $input,
    'decodedValue' => displayJsonValue($value),
    'sqliteJsonbHex' => bin2hex($jsonb),
    'sqliteJsonbBytes' => strlen($jsonb),
    'applicationUse' => 'Store these bytes as a wp_options option_value BLOB when preparing JSONB recovery fixtures.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
