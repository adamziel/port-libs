<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteGroupedAggregate;
use PortLibs\LibSqlite\SQLiteSelectCompound;
use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Root-gate reduction for the grouped-order and compound-select coercion
 * failures reproduced on base 237d5f4b8e36df3db6c68956f219939b05a1e90f.
 *
 * Upstream source truth for set-operator ordering and LIMIT/OFFSET behavior:
 * /home/claude/port-libs/.upstream-cache/libsqlite/test/select9.test
 */

$summaryRows = [
    ['load_policy' => 'yes', 'name' => 'siteurl', 'bytes' => 20],
    ['load_policy' => 'yes', 'name' => 'home', 'bytes' => 20],
    ['load_policy' => 'yes', 'name' => 'blogname', 'bytes' => 9],
    ['load_policy' => 'no', 'name' => '_transient_feed', 'bytes' => 12],
    ['load_policy' => 'no', 'name' => 'empty_cache_key', 'bytes' => 0],
    ['load_policy' => 'no', 'name' => 'legacy_null', 'bytes' => null],
    ['load_policy' => null, 'name' => 'orphaned', 'bytes' => 3],
    ['load_policy' => null, 'name' => 'orphaned-again', 'bytes' => 7],
];

$appSettings = [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'bytes' => 24],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'bytes' => 24],
    ['setting_id' => 3, 'key_name' => 'blogname', 'key_value' => 'Example Site', 'load_policy' => 'yes', 'bytes' => 9],
    ['setting_id' => 4, 'key_name' => '_transient_feed', 'key_value' => 'cached', 'load_policy' => 'no', 'bytes' => 12],
];
$networkSettings = [
    ['setting_id' => 10, 'key_name' => 'siteurl', 'load_policy' => 'yes', 'bytes' => 30],
    ['setting_id' => 11, 'key_name' => 'upload_path', 'load_policy' => 'no', 'bytes' => 8],
    ['setting_id' => 12, 'key_name' => 'network_admin_email', 'load_policy' => 'yes', 'bytes' => 20],
];

return [
    'grouped having preserves first seen group order until explicit order by' => static function (TestRunner $t) use ($summaryRows): void {
        $summary = SQLiteGroupedAggregate::summarize($summaryRows, 'load_policy', 'bytes');

        $t->same(['yes', 'no', null], array_column($summary, 'group'));
        $t->same(['yes', 'no'], array_column(SQLiteGroupedAggregate::havingCountAtLeast($summary, 3), 'group'));
        $t->same([null, 'no', 'yes'], array_column(SQLiteGroupedAggregate::orderBy($summary, 'group'), 'group'));
    },
    'compound union keeps boolean presentation for numeric duplicate keys' => static function (TestRunner $t): void {
        $rows = SQLiteSelectCompound::union(
            [['value' => true], ['value' => 1], ['value' => false], ['value' => 0]],
            [['value' => 1], ['value' => 0]],
        );

        $t->same([true, false], array_column($rows, 'value'));
    },
    'compound union still retains later text duplicate for collated set keys' => static function (TestRunner $t): void {
        $rows = SQLiteSelectCompound::combine(
            [['name' => 'siteurl'], ['name' => 'home']],
            [['name' => 'SiteURL']],
            'UNION',
            ['name' => 'NOCASE'],
        );

        $t->same(['SiteURL', 'home'], array_column($rows, 'name'));
    },
    'mixed compound sql final order keeps arm order for equal final sort keys' => static function (TestRunner $t) use ($appSettings, $networkSettings): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT key_name AS name, bytes FROM app_settings WHERE setting_id <= 2 UNION ALL SELECT key_name AS name, bytes FROM network_settings WHERE load_policy = 'yes' UNION SELECT key_name AS name, bytes FROM app_settings WHERE key_name = 'blogname' ORDER BY bytes DESC LIMIT 2, 3",
            ['app_settings' => $appSettings, 'network_settings' => $networkSettings],
        );

        $t->same(['home', 'network_admin_email', 'blogname'], array_column($rows, 'name'));
        $t->same([24, 20, 9], array_column($rows, 'bytes'));
    },
    'pure set operators keep upstream select9 final set ordering before order by windows' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            'SELECT a, b FROM t1 UNION SELECT d, e FROM t2 ORDER BY 2 LIMIT 3',
            [
                't1' => [
                    ['a' => 3, 'b' => null],
                    ['a' => 9, 'b' => null],
                    ['a' => 6, 'b' => null],
                    ['a' => 1, 'b' => 'one'],
                ],
                't2' => [
                    ['d' => 3, 'e' => null],
                    ['d' => 6, 'e' => null],
                    ['d' => 9, 'e' => null],
                ],
            ],
        );

        $t->same([3, 6, 9], array_column($rows, 'a'));
        $t->same([null, null, null], array_column($rows, 'b'));
    },
    'compound duplicate validation still rejects non scalar result values' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectCompound::union([['value' => ['not' => 'scalar']]], []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectCompound::union([['value' => new stdClass()]], []));
        $rows = SQLiteSelectCompound::union([['value' => new SQLiteBlobValue('a')]], [['value' => new SQLiteBlobValue('a')]]);
        $t->same('a', $rows[0]['value']->bytes);
    },
];
