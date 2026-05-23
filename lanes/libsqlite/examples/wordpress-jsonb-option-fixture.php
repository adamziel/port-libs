<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJson5Parser;
use PortLibs\LibSqlite\SQLiteJsonB;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jsonText = $argv[1] ?? null;
if ($jsonText === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/wordpress-jsonb-option-fixture.php '{\"enabled\":true}'\n");
    fwrite(STDERR, "Encodes strict JSON or supported SQLite JSON5 text as a SQLite JSONB option_value fixture.\n");
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
    'decodedValue' => $value,
    'sqliteJsonbHex' => bin2hex($jsonb),
    'sqliteJsonbBytes' => strlen($jsonb),
    'wordpressUse' => 'Store these bytes as a wp_options option_value BLOB when preparing JSONB recovery fixtures.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
