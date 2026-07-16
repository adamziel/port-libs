<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteKeyValueRow;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

/**
 * @param list<mixed> $lookupValues
 * @return array{path:string,jsonPath:string,lookupValues:list<mixed>,appSettingsJsonExtractInListIndexRootPage:?int,limit:int,settings:list<array<string,mixed>>}
 */
function applicationJsonSettingValueListPayload(string $databasePath, string $jsonPath, array $lookupValues, int $limit): array
{
    $database = SQLiteDatabase::fromFile($databasePath);
    $indexRootPage = $database->indexRootPageForJsonExtractInLookup('app_settings', 'key_value', $jsonPath, $lookupValues);
    $settings = array_map(
        static fn (SQLiteKeyValueRow $setting): array => $setting->toArray(),
        $database->keyValueRowsByIndexedJsonValues($jsonPath, $lookupValues, $limit),
    );

    return [
        'path' => $databasePath,
        'jsonPath' => $jsonPath,
        'lookupValues' => $lookupValues,
        'appSettingsJsonExtractInListIndexRootPage' => $indexRootPage,
        'limit' => $limit,
        'settings' => $settings,
    ];
}

function applicationJsonSettingValueListFixtureBytes(): string
{
    $makeFirstPage = static function (int $pageSize, int $databaseSizePages): string {
        $page = str_repeat("\0", $pageSize);
        $page = substr_replace($page, "SQLite format 3\0", 0, 16);
        $page = substr_replace($page, pack('n', $pageSize === 65536 ? 1 : $pageSize), 16, 2);
        $page[18] = "\x01";
        $page[19] = "\x01";
        $page[20] = "\x00";
        $page[21] = "\x40";
        $page[22] = "\x20";
        $page[23] = "\x20";
        $page = substr_replace($page, pack('N', $databaseSizePages), 28, 4);
        $page = substr_replace($page, pack('N', 1), 56, 4);

        return $page;
    };

    $pageSize = 512;
    $schemaPage = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
            'table',
            'app_settings',
            'app_settings',
            2,
            'CREATE TABLE app_settings(setting_id integer primary key, key_name text, key_value text, load_policy text)',
        ])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
            'index',
            'app_settings_mode_value',
            'app_settings',
            3,
            'CREATE INDEX app_settings_mode_value ON app_settings(json_extract(key_value, \'$.mode\')) WHERE key_value IS NOT NULL',
        ])),
    ], $pageSize, 100, $makeFirstPage($pageSize, 3));
    $tablePage = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'module_enabled', '{"mode":"enabled"}', 'yes'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'module_disabled', '{"mode":"disabled"}', 'no'])),
    ], $pageSize);
    $indexPage = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['disabled', 2])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['enabled', 1])),
    ], $pageSize);

    return $schemaPage . $tablePage . $indexPage;
}

if (($argv[1] ?? null) === '--self-test') {
    $path = tempnam(sys_get_temp_dir(), 'libsqlite-json-settings-');
    if ($path === false) {
        throw new RuntimeException('Unable to allocate temporary fixture database');
    }
    file_put_contents($path, applicationJsonSettingValueListFixtureBytes());
    try {
        $payload = applicationJsonSettingValueListPayload($path, '$.mode', ['enabled', 'disabled'], 10);
    } finally {
        @unlink($path);
    }

    if ($payload['appSettingsJsonExtractInListIndexRootPage'] !== 3) {
        throw new RuntimeException('Self-test failed to find app_settings json_extract index');
    }
    $keyNames = array_column($payload['settings'], 'key_name');
    if ($keyNames !== ['module_disabled', 'module_enabled']) {
        throw new RuntimeException('Self-test returned unexpected settings: ' . implode(',', $keyNames));
    }

    echo "application-json-setting-value-list self-test passed\n";
    exit(0);
}

$databasePath = $argv[1] ?? null;
$jsonPath = $argv[2] ?? null;
$valueList = $argv[3] ?? null;
$limit = isset($argv[4]) ? (int) $argv[4] : 100;
if ($databasePath === null || $jsonPath === null || $valueList === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-json-setting-value-list.php path/to/application.sqlite json_path json_scalar[,json_scalar...] [limit]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON app_settings(json_extract(key_value, '$.key')).\n");
    exit(1);
}

$values = array_values(array_filter(
    array_map(trim(...), explode(',', $valueList)),
    static fn (string $value): bool => $value !== '',
));
$lookupValues = array_map(
    static fn (string $value): mixed => match (strtolower($value)) {
        'true' => true,
        'false' => false,
        'null' => null,
        default => preg_match('/^[+-]?\d+$/', $value) === 1 ? (int) $value : $value,
    },
    $values,
);

echo json_encode(
    applicationJsonSettingValueListPayload($databasePath, $jsonPath, $lookupValues, $limit),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";
