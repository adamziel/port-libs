<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$autoload = $argv[2] ?? 'yes';
$limit = isset($argv[3]) ? (int) $argv[3] : 100;
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-autoloaded-options.php path/to/application.sqlite [autoload] [limit]\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForPointLookup('wp_options', 'autoload', $autoload);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByIndexedLoadPolicy($autoload, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'autoload' => $autoload,
    'wpOptionsAutoloadIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
