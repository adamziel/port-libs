<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateIndex;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

$path = static function (string $expression, string $operator = 'text'): ?string {
    $sql = 'CREATE INDEX fixture ON app_settings(' . $expression . ') WHERE key_value IS NOT NULL';
    $index = $operator === 'value'
        ? SQLiteCreateIndex::firstJsonValueOperatorExpression($sql)
        : SQLiteCreateIndex::firstJsonTextOperatorExpression($sql);

    return $index?->path;
};

$collation = static function (string $expression, string $operator = 'text'): ?string {
    $sql = 'CREATE INDEX fixture ON app_settings(' . $expression . ') WHERE key_value IS NOT NULL';
    $index = $operator === 'value'
        ? SQLiteCreateIndex::firstJsonValueOperatorExpression($sql)
        : SQLiteCreateIndex::firstJsonTextOperatorExpression($sql);

    return $index?->collation;
};

$pageSize = 4096;
$makeFirstPage = static function (int $databasePageCount) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databasePageCount), 28, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$indexCell = static fn (array $values): string => SQLiteIndexCell::encode(SQLiteRecord::encode($values));

return [
    'normalizes parenthesized collated text RHS label for json text operator' => static fn (TestRunner $t) => $t->same(
        '$.cache',
        $path("key_value ->> ('cache' COLLATE nocase)"),
    ),
    'normalizes parenthesized collated dotted text RHS label for json text operator' => static fn (TestRunner $t) => $t->same(
        '$."plugin.enabled"',
        $path("key_value ->> ('plugin.enabled' COLLATE binary)"),
    ),
    'normalizes parenthesized collated quoted JSON path RHS for json text operator' => static fn (TestRunner $t) => $t->same(
        '$."plugin enabled"',
        $path('key_value ->> (\'$."plugin enabled"\' COLLATE nocase)'),
    ),
    'normalizes parenthesized collated array index RHS for json text operator' => static fn (TestRunner $t) => $t->same(
        '$[2]',
        $path("key_value ->> ('[2]' COLLATE rtrim)"),
    ),
    'normalizes parenthesized collated reverse array RHS for json text operator' => static fn (TestRunner $t) => $t->same(
        '$[#-1]',
        $path("key_value ->> ('[#-1]' COLLATE nocase)"),
    ),
    'normalizes parenthesized collated integer RHS for json text operator' => static fn (TestRunner $t) => $t->same(
        '$[2]',
        $path('key_value ->> (2 COLLATE nocase)'),
    ),
    'normalizes parenthesized collated negative integer RHS for json text operator' => static fn (TestRunner $t) => $t->same(
        '$[#-1]',
        $path('key_value ->> (-1 COLLATE binary)'),
    ),
    'normalizes nested parenthesized collated RHS for json text operator' => static fn (TestRunner $t) => $t->same(
        '$.cache',
        $path("key_value ->> (('cache' COLLATE nocase))"),
    ),
    'keeps outer json text operator index collation after collated RHS' => static fn (TestRunner $t) => $t->same(
        'RTRIM',
        $collation("key_value ->> ('cache' COLLATE nocase) COLLATE rtrim"),
    ),
    'keeps binary index collation when only RHS is collated' => static fn (TestRunner $t) => $t->same(
        'BINARY',
        $collation("key_value ->> ('cache' COLLATE nocase)"),
    ),
    'normalizes parenthesized collated json_quote null RHS for json text operator' => static fn (TestRunner $t) => $t->same(
        '$.null',
        $path('key_value ->> (json_quote(NULL) COLLATE nocase)'),
    ),
    'normalizes parenthesized collated json_quote integer RHS for json text operator' => static fn (TestRunner $t) => $t->same(
        '$."12"',
        $path('key_value ->> (json_quote(12) COLLATE nocase)'),
    ),
    'normalizes parenthesized collated min string RHS for json text operator' => static fn (TestRunner $t) => $t->same(
        '$.cache',
        $path("key_value ->> (min('seo', 'cache') COLLATE nocase)"),
    ),
    'normalizes parenthesized collated max string RHS for json text operator' => static fn (TestRunner $t) => $t->same(
        '$.seo',
        $path("key_value ->> (max('seo', 'cache') COLLATE nocase)"),
    ),
    'normalizes parenthesized collated min integer RHS for json text operator' => static fn (TestRunner $t) => $t->same(
        '$[1]',
        $path('key_value ->> (min(2, 1) COLLATE nocase)'),
    ),
    'normalizes parenthesized collated max integer RHS for json text operator' => static fn (TestRunner $t) => $t->same(
        '$[2]',
        $path('key_value ->> (max(2, 1) COLLATE nocase)'),
    ),
    'rejects missing RHS collation name in json text operator' => static fn (TestRunner $t) => $t->same(
        null,
        $path("key_value ->> ('cache' COLLATE)"),
    ),
    'rejects extra token after RHS collation name in json text operator' => static fn (TestRunner $t) => $t->same(
        null,
        $path("key_value ->> ('cache' COLLATE nocase DESC)"),
    ),
    'rejects collated malformed path in json text operator' => static fn (TestRunner $t) => $t->same(
        null,
        $path("key_value ->> ('$.rules[#-]' COLLATE nocase)"),
    ),
    'rejects collated real RHS in json text operator' => static fn (TestRunner $t) => $t->same(
        null,
        $path('key_value ->> (1.5 COLLATE nocase)'),
    ),
    'normalizes parenthesized collated text RHS label for json value operator' => static fn (TestRunner $t) => $t->same(
        '$.cache',
        $path("key_value -> ('cache' COLLATE nocase)", 'value'),
    ),
    'normalizes parenthesized collated dotted RHS for json value operator' => static fn (TestRunner $t) => $t->same(
        '$."plugin.enabled"',
        $path("key_value -> ('plugin.enabled' COLLATE binary)", 'value'),
    ),
    'normalizes parenthesized collated integer RHS for json value operator' => static fn (TestRunner $t) => $t->same(
        '$[3]',
        $path('key_value -> (3 COLLATE nocase)', 'value'),
    ),
    'normalizes parenthesized collated min RHS for json value operator' => static fn (TestRunner $t) => $t->same(
        '$.cache',
        $path("key_value -> (min('seo', 'cache') COLLATE nocase)", 'value'),
    ),
    'keeps outer json value operator index collation after collated RHS' => static fn (TestRunner $t) => $t->same(
        'NOCASE',
        $collation("key_value -> ('cache' COLLATE rtrim) COLLATE nocase", 'value'),
    ),
    'rejects missing RHS collation name in json value operator' => static fn (TestRunner $t) => $t->same(
        null,
        $path("key_value -> ('cache' COLLATE)", 'value'),
    ),
    'rejects extra token after RHS collation name in json value operator' => static fn (TestRunner $t) => $t->same(
        null,
        $path("key_value -> ('cache' COLLATE nocase ASC)", 'value'),
    ),
    'rejects collated malformed path in json value operator' => static fn (TestRunner $t) => $t->same(
        null,
        $path("key_value -> ('$.rules[#-]' COLLATE nocase)", 'value'),
    ),
    'uses application json text operator index with collated RHS label' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $indexCell, $pageSize): void {
        $schemaPage = SQLiteTableLeafPage::assemble([
            $schemaCell(['table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id integer primary key, key_name text, key_value text, load_policy text)'], 1),
            $schemaCell(['index', 'app_settings_json_cache', 'app_settings', 3, "CREATE INDEX app_settings_json_cache ON app_settings(key_value ->> ('cache' COLLATE nocase)) WHERE key_value IS NOT NULL"], 2),
        ], $pageSize, 100, $makeFirstPage(3));
        $tablePage = SQLiteTableLeafPage::assemble([
            $schemaCell([null, 'plugin_cache_settings', '{"cache":"hit"}', 'no'], 1),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . SQLiteIndexLeafPage::assemble([$indexCell(['hit', 1])], $pageSize));

        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('app_settings', 'key_value', '$.cache', 'hit'));
        $t->same(['plugin_cache_settings'], array_map(static fn (SQLiteKeyValueRow $setting): string => $setting->keyName, $database->keyValueRowsByIndexedJsonValue('$.cache', 'hit')));
    },
    'uses application json text operator index with collated RHS dotted label' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $indexCell, $pageSize): void {
        $schemaPage = SQLiteTableLeafPage::assemble([
            $schemaCell(['table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id integer primary key, key_name text, key_value text, load_policy text)'], 1),
            $schemaCell(['index', 'app_settings_json_enabled', 'app_settings', 3, "CREATE INDEX app_settings_json_enabled ON app_settings(key_value ->> ('plugin.enabled' COLLATE binary)) WHERE key_value IS NOT NULL"], 2),
        ], $pageSize, 100, $makeFirstPage(3));
        $tablePage = SQLiteTableLeafPage::assemble([
            $schemaCell([null, 'plugin_enabled_settings', '{"plugin.enabled":"yes"}', 'no'], 1),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . SQLiteIndexLeafPage::assemble([$indexCell(['yes', 1])], $pageSize));

        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('app_settings', 'key_value', '$."plugin.enabled"', 'yes'));
        $t->same(['plugin_enabled_settings'], array_map(static fn (SQLiteKeyValueRow $setting): string => $setting->keyName, $database->keyValueRowsByIndexedJsonValue('$."plugin.enabled"', 'yes')));
    },
];
