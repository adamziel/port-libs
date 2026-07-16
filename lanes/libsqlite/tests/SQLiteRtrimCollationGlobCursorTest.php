<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteGlobCursor;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$compareCases = [
    'space suffix equals unpadded text' => ['siteurl ', 'siteurl', 0],
    'multiple space suffix equals unpadded text' => ['siteurl   ', 'siteurl', 0],
    'tab suffix remains greater than unpadded text' => ["siteurl\t", 'siteurl', 1],
    'newline suffix remains greater than unpadded text' => ["siteurl\n", 'siteurl', 1],
    'carriage return suffix remains greater than unpadded text' => ["siteurl\r", 'siteurl', 1],
    'nul suffix remains greater than unpadded text' => ["siteurl\0", 'siteurl', 1],
    'vertical tab suffix remains greater than unpadded text' => ["siteurl\x0B", 'siteurl', 1],
    'tab after trimmed space still remains distinct' => ["siteurl\t ", 'siteurl', 1],
    'space after tab is trimmed but tab remains distinct' => ["siteurl \t ", 'siteurl', 1],
    'interior tab is never trimmed' => ["site\turl ", 'siteurl', -1],
    'trailing space trims before binary suffix comparison' => ['siteurl z ', 'siteurl aa', 1],
    'numeric text with space suffix equals after text affinity' => ['10 ', 10, 0],
    'numeric text with tab suffix remains distinct after text affinity' => ["10\t", 10, 1],
    'numeric storage still sorts below text despite rtrim collation' => [10, '10 ', -1],
    'ascii case is not folded by rtrim' => ['SiteURL ', 'siteurl', -1],
    'unicode text only trims ascii space' => ['plugin_å ', 'plugin_å', 0],
    'unicode text keeps newline suffix distinct' => ["plugin_å\n", 'plugin_å', 1],
    'emoji text only trims ascii space' => ['plugin_😀 ', 'plugin_😀', 0],
    'emoji text keeps tab suffix distinct' => ["plugin_😀\t", 'plugin_😀', 1],
    'leading space remains significant' => [' siteurl ', 'siteurl', -1],
    'right side tab suffix remains greater than left side' => ['siteurl', "siteurl\t", -1],
    'right side newline suffix remains greater than left side' => ['siteurl', "siteurl\n", -1],
    'both sides trim spaces before tab comparison' => ["siteurl\t  ", "siteurl\t", 0],
    'both sides keep different non-space suffixes' => ["siteurl\t", "siteurl\n", -1],
    'space before newline is not trimmed past newline' => ["siteurl \n", 'siteurl', 1],
    'newline before space remains after final space trim' => ["siteurl\n ", 'siteurl', 1],
    'carriage return before space remains after final space trim' => ["siteurl\r ", 'siteurl', 1],
    'nul before space remains after final space trim' => ["siteurl\0 ", 'siteurl', 1],
    'vertical tab before space remains after final space trim' => ["siteurl\x0B ", 'siteurl', 1],
    'plain spaces on both sides collapse to equality' => ['siteurl   ', 'siteurl ', 0],
    'unicode uppercase remains binary distinct after trimming spaces' => ['plugin_Å ', 'plugin_å', -1],
    'emoji trailing spaces compare before following text' => ['plugin_😀   ', 'plugin_😀a', -1],
];

foreach ($compareCases as $name => [$left, $right, $expected]) {
    $tests['rtrim collation current next76 affinity compare ' . $name] = static function (TestRunner $t) use ($left, $right, $expected): void {
        $comparison = SQLiteAffinityComparison::compare($left, $right, 'TEXT', 'NONE', 'RTRIM');
        $t->same($expected, $comparison === null ? null : $comparison <=> 0);
    };
}

$rows = [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://example.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'siteurl ', 'key_value' => 'space padded', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => "siteurl\t", 'key_value' => 'tab padded', 'load_policy' => 'no'],
    ['setting_id' => 4, 'key_name' => "siteurl\n", 'key_value' => 'newline padded', 'load_policy' => 'no'],
    ['setting_id' => 5, 'key_name' => "siteurl\0", 'key_value' => 'nul padded', 'load_policy' => 'no'],
    ['setting_id' => 6, 'key_name' => 'SiteURL ', 'key_value' => 'case padded', 'load_policy' => 'no'],
    ['setting_id' => 7, 'key_name' => 'plugin_å ', 'key_value' => 'unicode space', 'load_policy' => 'yes'],
    ['setting_id' => 8, 'key_name' => "plugin_å\t", 'key_value' => 'unicode tab', 'load_policy' => 'no'],
];
$tables = ['app_settings' => $rows];

$sqlCases = [
    'case base trims only space suffix' => [
        "SELECT setting_id, CASE key_name COLLATE RTRIM WHEN 'siteurl' THEN 'match' ELSE 'miss' END AS bucket FROM app_settings ORDER BY setting_id",
        'bucket',
        ['match', 'match', 'miss', 'miss', 'miss', 'miss', 'miss', 'miss'],
    ],
    'case when operand trims only space suffix' => [
        "SELECT setting_id, CASE key_name WHEN 'siteurl ' COLLATE RTRIM THEN 'match' ELSE 'miss' END AS bucket FROM app_settings ORDER BY setting_id",
        'bucket',
        ['match', 'match', 'miss', 'miss', 'miss', 'miss', 'miss', 'miss'],
    ],
    'case rtrim does not fold case' => [
        "SELECT setting_id, CASE key_name COLLATE RTRIM WHEN 'SiteURL' THEN 'match' ELSE 'miss' END AS bucket FROM app_settings ORDER BY setting_id",
        'bucket',
        ['miss', 'miss', 'miss', 'miss', 'miss', 'match', 'miss', 'miss'],
    ],
    'where case keeps tab padded option distinct' => [
        "SELECT setting_id FROM app_settings WHERE CASE key_name COLLATE RTRIM WHEN 'siteurl' THEN 1 ELSE 0 END = 1 ORDER BY setting_id",
        'setting_id',
        [1, 2],
    ],
    'unicode space suffix matches under rtrim' => [
        "SELECT setting_id FROM app_settings WHERE CASE key_name COLLATE RTRIM WHEN 'plugin_å' THEN 1 ELSE 0 END = 1 ORDER BY setting_id",
        'setting_id',
        [7],
    ],
    'unicode tab suffix remains distinct under rtrim' => [
        "SELECT setting_id FROM app_settings WHERE CASE key_name COLLATE RTRIM WHEN 'plugin_å\t' THEN 1 ELSE 0 END = 1 ORDER BY setting_id",
        'setting_id',
        [8],
    ],
];

foreach ($sqlCases as $name => [$sql, $column, $expected]) {
    $tests['rtrim collation current next76 select sql ' . $name] = static function (TestRunner $t) use ($tables, $sql, $column, $expected): void {
        $t->same($expected, array_column(SQLiteSelectSql::execute($sql, $tables), $column));
    };
}

$entries = [
    ['key' => 'siteurl', 'rowid' => 1, 'payload' => ['key_name' => 'siteurl']],
    ['key' => 'siteurl ', 'rowid' => 2, 'payload' => ['key_name' => 'siteurl ']],
    ['key' => "siteurl\t", 'rowid' => 3, 'payload' => ['key_name' => "siteurl\t"]],
    ['key' => "siteurl\n", 'rowid' => 4, 'payload' => ['key_name' => "siteurl\n"]],
    ['key' => 'siteurl  ', 'rowid' => 5, 'payload' => ['key_name' => 'siteurl  ']],
    ['key' => "siteurl\t ", 'rowid' => 6, 'payload' => ['key_name' => "siteurl\t "]],
    ['key' => 'sitevalue', 'rowid' => 7, 'payload' => ['key_name' => 'sitevalue']],
];

$cursor = static fn (string $pattern = 'siteurl'): SQLiteGlobCursor => new SQLiteGlobCursor($entries, $pattern, 'RTRIM');

$cursorCases = [
    'first exact current is unpadded row' => [0, 'currentRowid', 1],
    'first exact next is single-space peer' => [0, 'nextRowid', 2],
    'single-space peer compares equal to lower bound' => [1, 'comparisonToLower', 0],
    'double-space peer compares equal to lower bound' => [2, 'comparisonToLower', 0],
    'tab peer remains greater than exact lower bound' => [3, 'comparisonToLower', 1],
    'newline peer remains greater than exact lower bound' => [5, 'comparisonToLower', 1],
    'tab-space peer keeps tab after rtrim' => [4, 'comparisonToLower', 1],
    'space peer remains in exact prefix range' => [1, 'inRange', true],
    'tab peer remains in exact prefix range before residual filter' => [3, 'inRange', true],
    'tab peer residual does not match exact LIKE' => [3, 'residualMatch', false],
    'next tab peer remains in exact prefix range from double-space peer' => [2, 'nextInRange', true],
    'row after exact prefix is outside range' => [6, 'inRange', false],
];

foreach ($cursorCases as $name => [$advance, $path, $expected]) {
    $tests['rtrim collation current next76 glob cursor ' . $name] = static function (TestRunner $t) use ($cursor, $advance, $path, $expected): void {
        $instance = $cursor();
        for ($i = 0; $i < $advance; $i++) {
            $instance->next();
        }
        $plan = $instance->currentNextPlan();
        $t->same($expected, $plan[$path]);
    };
}

$tests['rtrim collation current next76 glob matched rows keep only exact binary names after residual'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([1], array_column($cursor()->matchedRows(), 'rowid'));
};

$tests['rtrim collation current next76 glob cursor preserves tab padded payload outside exact match'] = static function (TestRunner $t) use ($cursor): void {
    $cursor = $cursor('siteurl*');
    $t->same([1, 2, 5, 3, 6, 4], array_column($cursor->matchedRows(), 'rowid'));
};

$tests['rtrim collation current next76 rejects unsupported collation through affinity compare'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAffinityComparison::compare('a', 'a', 'TEXT', 'NONE', 'UNICODE_RTRIM'));
};

return $tests;
