<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$jsonPath = $argv[2] ?? null;
$value = $argv[3] ?? null;
$limit = isset($argv[4]) ? (int) $argv[4] : 100;
if ($databasePath === null || $jsonPath === null || $value === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-jsonb-option-value.php path/to/application.sqlite json_path json_scalar [limit]\n");
    fwrite(STDERR, "Reads json_extract(option_value, path) indexes whose wp_options option_value rows may be SQLite JSONB blobs.\n");
    exit(1);
}

$lookupValue = match (strtolower($value)) {
    'true' => true,
    'false' => false,
    'null' => null,
    default => preg_match('/^[+-]?\d+$/', $value) === 1 ? (int) $value : $value,
};

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', $jsonPath, $lookupValue);
$options = array_map(
    static function (SQLiteKeyValueRow $option): array {
        $row = $option->toArray();
        $value = $row['option_value'];
        if (preg_match('//u', $value) !== 1) {
            $row['option_value_encoding'] = 'binary';
            $row['option_value_hex'] = bin2hex($value);
            unset($row['option_value']);
        }

        return $row;
    },
    $database->keyValueRowsByIndexedJsonValue($jsonPath, $lookupValue, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'jsonPath' => $jsonPath,
    'lookupValue' => $lookupValue,
    'wpOptionsJsonExtractIndexRootPage' => $indexRootPage,
    'jsonInput' => 'strict JSON, supported SQLite JSON5, or SQLite JSONB blobs',
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
