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
 * @param list<int> $values
 * @return array{path:string,integerValues:list<int>,appSettingsKeyValueIntegerInListIndexRootPage:?int,limit:int,settings:list<array<string,mixed>>}
 */
function applicationSettingValueIntegerListPayload(string $databasePath, array $values, int $limit): array
{
    $database = SQLiteDatabase::fromFile($databasePath);
    $indexRootPage = $database->indexRootPageForIntegerCastInLookup('app_settings', 'key_value', $values);
    $settings = array_map(
        static fn (SQLiteKeyValueRow $setting): array => $setting->toArray(),
        $database->keyValueRowsByIndexedIntegerValues($values, $limit),
    );

    return [
        'path' => $databasePath,
        'integerValues' => $values,
        'appSettingsKeyValueIntegerInListIndexRootPage' => $indexRootPage,
        'limit' => $limit,
        'settings' => $settings,
    ];
}

function applicationSettingValueIntegerListFixtureBytes(): string
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
            'app_settings_integer_value',
            'app_settings',
            3,
            'CREATE INDEX app_settings_integer_value ON app_settings(CAST(key_value AS INTEGER)) WHERE key_value IS NOT NULL',
        ])),
    ], $pageSize, 100, $makeFirstPage($pageSize, 3));
    $tablePage = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'module_twelve', '12', 'yes'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'module_thirty_four', '34', 'no'])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'counter_text', 'abc', 'no'])),
    ], $pageSize);
    $indexPage = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode([0, 3])),
        SQLiteIndexCell::encode(SQLiteRecord::encode([12, 1])),
        SQLiteIndexCell::encode(SQLiteRecord::encode([34, 2])),
    ], $pageSize);

    return $schemaPage . $tablePage . $indexPage;
}

if (($argv[1] ?? null) === '--self-test') {
    $path = tempnam(sys_get_temp_dir(), 'libsqlite-integer-settings-');
    if ($path === false) {
        throw new RuntimeException('Unable to allocate temporary fixture database');
    }
    file_put_contents($path, applicationSettingValueIntegerListFixtureBytes());
    try {
        $payload = applicationSettingValueIntegerListPayload($path, [34, 0], 10);
    } finally {
        @unlink($path);
    }

    if ($payload['appSettingsKeyValueIntegerInListIndexRootPage'] !== 3) {
        throw new RuntimeException('Self-test failed to find app_settings integer expression index');
    }
    $keyNames = array_column($payload['settings'], 'key_name');
    if ($keyNames !== ['counter_text', 'module_thirty_four']) {
        throw new RuntimeException('Self-test returned unexpected settings: ' . implode(',', $keyNames));
    }

    echo "application-setting-value-integer-list self-test passed\n";
    exit(0);
}

$databasePath = $argv[1] ?? null;
$valueList = $argv[2] ?? null;
$limit = isset($argv[3]) ? (int) $argv[3] : 100;
if ($databasePath === null || $valueList === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-setting-value-integer-list.php path/to/application.sqlite integer_value[,integer_value...] [limit]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON app_settings(CAST(key_value AS INTEGER)).\n");
    exit(1);
}

$values = [];
foreach (explode(',', $valueList) as $value) {
    $value = trim($value);
    if ($value === '' || !preg_match('/^[+-]?\d+$/', $value)) {
        fwrite(STDERR, "Integer value lists may only contain base-10 integer literals separated by commas.\n");
        exit(1);
    }
    $values[] = (int) $value;
}

echo json_encode(
    applicationSettingValueIntegerListPayload($databasePath, $values, $limit),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";
