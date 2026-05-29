<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$validJsonb = new SQLiteBlobValue(SQLiteJsonB::encode([
    'plugin' => [
        'name' => 'forms',
        'enabled' => false,
        'priority' => 3,
        'limits' => ['daily' => 10],
        'rules' => [
            ['name' => 'validate', 'enabled' => true],
        ],
    ],
]));
$malformedJsonb = new SQLiteBlobValue("\xcc" . '{"plugin":{"name":"broken"}}');
$truncatedJsonb = new SQLiteBlobValue(substr(SQLiteJsonB::encode(['plugin' => ['name' => 'truncated', 'priority' => 9]]), 0, -1));

$currentRows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_cache_settings',
        'option_value' => '{"plugin":{"name":"cache","enabled":true,"priority":7,"limits":{"daily":25},"rules":[{"name":"warm"},{"name":"serve"}]}}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms_settings',
        'option_value' => $validJsonb,
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_missing_settings',
        'option_value' => '{"plugin":{"enabled":false}}',
        'autoload' => 'no',
    ],
];

$nextRows = [
    $currentRows[0],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms_settings',
        'option_value' => $malformedJsonb,
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_missing_settings',
        'option_value' => '{"plugin":{"name":"empty","enabled":false}}',
        'autoload' => 'no',
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_truncated_settings',
        'option_value' => $truncatedJsonb,
        'autoload' => 'no',
    ],
];

$textPlan = static fn (): array => SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare(
    $currentRows,
    $nextRows,
    '$.plugin.name',
    '->>',
);
$valuePlan = static fn (): array => SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare(
    $currentRows,
    $nextRows,
    '$.plugin.limits',
    '->',
);
$priorityPlan = static fn (): array => SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare(
    $currentRows,
    $nextRows,
    '$.plugin.priority',
    '->>',
);
$select = static fn (string $sql, array $rows = null): array => SQLiteSelectSql::execute($sql, ['wp_options' => $rows ?? $nextRows]);

$tests = [
    'jsonb path operator malformed current source next106 records operator' => static fn (TestRunner $t) => $t->same('->>', $textPlan()['operator']),
    'jsonb path operator malformed current source next106 records path' => static fn (TestRunner $t) => $t->same('$.plugin.name', $textPlan()['path']),
    'jsonb path operator malformed current source next106 counts current rows' => static fn (TestRunner $t) => $t->same(3, $textPlan()['currentRowCount']),
    'jsonb path operator malformed current source next106 counts next rows' => static fn (TestRunner $t) => $t->same(4, $textPlan()['nextRowCount']),
    'jsonb path operator malformed current source next106 counts current valid rows' => static fn (TestRunner $t) => $t->same(2, $textPlan()['currentValidRowCount']),
    'jsonb path operator malformed current source next106 counts next valid rows' => static fn (TestRunner $t) => $t->same(2, $textPlan()['nextValidRowCount']),
    'jsonb path operator malformed current source next106 current has no malformed jsonb' => static fn (TestRunner $t) => $t->same([], $textPlan()['currentMalformedRowids']),
    'jsonb path operator malformed current source next106 next records malformed jsonb rowids' => static fn (TestRunner $t) => $t->same([2, 4], $textPlan()['nextMalformedRowids']),
    'jsonb path operator malformed current source next106 current records missing path rowids' => static fn (TestRunner $t) => $t->same([3], $textPlan()['currentMissingPathRowids']),
    'jsonb path operator malformed current source next106 next missing path disappears after source update' => static fn (TestRunner $t) => $t->same([], $textPlan()['nextMissingPathRowids']),
    'jsonb path operator malformed current source next106 requires reprepare on malformed change' => static fn (TestRunner $t) => $t->same(true, $textPlan()['reprepareRequired']),
    'jsonb path operator malformed current source next106 records malformed reprepare reason' => static fn (TestRunner $t) => $t->same('jsonb-operator-malformed-source-tape-changed', $textPlan()['reprepareReason']),
    'jsonb path operator malformed current source next106 preserves current reader policy' => static fn (TestRunner $t) => $t->same('keep-current-jsonb-operator-source-until-statement-reset', $textPlan()['currentReaderPolicy']),
    'jsonb path operator malformed current source next106 next reader policy reports abort' => static fn (TestRunner $t) => $t->same('next-jsonb-operator-source-errors-before-row-yield', $textPlan()['nextReaderPolicy']),
    'jsonb path operator malformed current source next106 statement would abort on next malformed source' => static fn (TestRunner $t) => $t->same(true, $textPlan()['statementWouldAbort']),
    'jsonb path operator malformed current source next106 current signature includes text result' => static fn (TestRunner $t) => $t->same('cache', $textPlan()['currentSignature'][0]['result']),
    'jsonb path operator malformed current source next106 current signature includes jsonb result' => static fn (TestRunner $t) => $t->same('forms', $textPlan()['currentSignature'][1]['result']),
    'jsonb path operator malformed current source next106 current signature distinguishes missing path' => static fn (TestRunner $t) => $t->same(false, $textPlan()['currentSignature'][2]['found']),
    'jsonb path operator malformed current source next106 next signature keeps valid text result' => static fn (TestRunner $t) => $t->same('cache', $textPlan()['nextSignature'][0]['result']),
    'jsonb path operator malformed current source next106 next signature marks malformed source' => static fn (TestRunner $t) => $t->same('malformed-jsonb', $textPlan()['nextSignature'][1]['sourceKind']),
    'jsonb path operator malformed current source next106 next signature keeps repaired text row' => static fn (TestRunner $t) => $t->same('empty', $textPlan()['nextSignature'][2]['result']),
    'jsonb path operator malformed current source next106 truncated source is malformed jsonb' => static fn (TestRunner $t) => $t->same('malformed-jsonb', $textPlan()['nextSignature'][3]['sourceKind']),
    'jsonb path operator malformed current source next106 diagnostics expose malformed error' => static fn (TestRunner $t) => $t->same(true, is_string($textPlan()['next']['diagnostics'][2]['error'])),
    'jsonb path operator malformed current source next106 diagnostics expose truncated error' => static fn (TestRunner $t) => $t->same(true, is_string($textPlan()['next']['diagnostics'][4]['error'])),
    'jsonb path operator malformed current source next106 diagnostics keep json text kind' => static fn (TestRunner $t) => $t->same('json-text', $textPlan()['current']['diagnostics'][1]['sourceKind']),
    'jsonb path operator malformed current source next106 diagnostics keep jsonb kind' => static fn (TestRunner $t) => $t->same('jsonb', $textPlan()['current']['diagnostics'][2]['sourceKind']),
    'jsonb path operator malformed current source next106 value plan records operator' => static fn (TestRunner $t) => $t->same('->', $valuePlan()['operator']),
    'jsonb path operator malformed current source next106 value operator returns canonical object' => static fn (TestRunner $t) => $t->same('{"daily":25}', $valuePlan()['currentSignature'][0]['result']),
    'jsonb path operator malformed current source next106 value operator returns canonical jsonb object' => static fn (TestRunner $t) => $t->same('{"daily":10}', $valuePlan()['currentSignature'][1]['result']),
    'jsonb path operator malformed current source next106 value operator keeps missing path separate' => static fn (TestRunner $t) => $t->same([3], $valuePlan()['currentMissingPathRowids']),
    'jsonb path operator malformed current source next106 value operator reports same malformed next rows' => static fn (TestRunner $t) => $t->same([2, 4], $valuePlan()['nextMalformedRowids']),
    'jsonb path operator malformed current source next106 numeric text operator returns integer' => static fn (TestRunner $t) => $t->same(7, $priorityPlan()['currentSignature'][0]['result']),
    'jsonb path operator malformed current source next106 numeric jsonb operator returns integer' => static fn (TestRunner $t) => $t->same(3, $priorityPlan()['currentSignature'][1]['result']),
    'jsonb path operator malformed current source next106 numeric missing path rowids' => static fn (TestRunner $t) => $t->same([3], $priorityPlan()['currentMissingPathRowids']),
    'jsonb path operator malformed current source next106 numeric next malformed rowids' => static fn (TestRunner $t) => $t->same([2, 4], $priorityPlan()['nextMalformedRowids']),
    'jsonb path operator malformed current source next106 rejects malformed path' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare($currentRows, $nextRows, '$.plugin[#-]', '->>')),
    'jsonb path operator malformed current source next106 rejects unsupported operator' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare($currentRows, $nextRows, '$.plugin.name', '#>')),
    'jsonb path operator malformed current source next106 rejects missing json column' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare([['option_id' => 9]], [], '$.plugin.name')),
    'jsonb path operator malformed current source next106 rejects non integer rowid' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare([['option_id' => '9', 'option_value' => '{}']], [], '$.plugin.name')),
    'jsonb path operator malformed current source next106 rejects non json source type' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare([['option_id' => 9, 'option_value' => 12]], [], '$.plugin.name')),
    'select sql jsonb path operator current source next106 aborts on projected malformed jsonb' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $select("SELECT option_value ->> '$.plugin.name' AS name FROM wp_options ORDER BY option_id")),
    'select sql jsonb path operator current source next106 aborts on where malformed jsonb' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $select("SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.name' = 'cache' ORDER BY option_id")),
    'select sql jsonb path operator current source next106 false and guard skips malformed source' => static fn (TestRunner $t) => $t->same([], array_column($select("SELECT option_name FROM wp_options WHERE 0 AND option_value ->> '$.plugin.name' = 'cache' ORDER BY option_id"), 'option_name')),
    'select sql jsonb path operator current source next106 true or guard skips malformed source' => static fn (TestRunner $t) => $t->same(['plugin_cache_settings', 'plugin_forms_settings', 'plugin_missing_settings', 'plugin_truncated_settings'], array_column($select("SELECT option_name FROM wp_options WHERE 1 OR option_value ->> '$.plugin.name' = 'cache' ORDER BY option_id"), 'option_name')),
    'select sql jsonb path operator current source next106 case lazy branch skips malformed source' => static fn (TestRunner $t) => $t->same(['safe', 'safe', 'safe', 'safe'], array_column($select("SELECT CASE WHEN 1 THEN 'safe' ELSE option_value ->> '$.plugin.name' END AS state FROM wp_options ORDER BY option_id"), 'state')),
    'select sql jsonb path operator current source next106 limit stops before malformed source' => static fn (TestRunner $t) => $t->same(['cache'], array_column($select("SELECT option_value ->> '$.plugin.name' AS name FROM wp_options WHERE option_id = 1 LIMIT 1"), 'name')),
    'select sql jsonb path operator current source next106 current rows project without abort' => static fn (TestRunner $t) => $t->same(['cache', 'forms', null], array_column($select("SELECT option_value ->> '$.plugin.name' AS name FROM wp_options ORDER BY option_id", $currentRows), 'name')),
    'select sql jsonb path operator current source next106 current rows value fragments project' => static fn (TestRunner $t) => $t->same(['{"daily":25}', '{"daily":10}', null], array_column($select("SELECT option_value -> '$.plugin.limits' AS limits FROM wp_options ORDER BY option_id", $currentRows), 'limits')),
    'select sql jsonb path operator current source next106 current rows filter text and jsonb' => static fn (TestRunner $t) => $t->same(['plugin_cache_settings', 'plugin_forms_settings'], array_column($select("SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.name' IN ('cache','forms') ORDER BY option_id", $currentRows), 'option_name')),
    'select sql jsonb path operator current source next106 current rows numeric filter' => static fn (TestRunner $t) => $t->same(['plugin_cache_settings', 'plugin_forms_settings'], array_column($select("SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.priority' >= 3 ORDER BY option_id", $currentRows), 'option_name')),
    'jsonb path operator malformed current source next106 valid-only next reader policy is runnable' => static function (TestRunner $t) use ($currentRows): void {
        $plan = SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare($currentRows, $currentRows, '$.plugin.name');
        $t->same('next-jsonb-operator-source-is-runnable', $plan['nextReaderPolicy']);
        $t->same(false, $plan['statementWouldAbort']);
    },
    'jsonb path operator malformed current source next106 stable source has stable reason' => static fn (TestRunner $t) => $t->same('stable-jsonb-operator-source', SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare($currentRows, $currentRows, '$.plugin.name')['reprepareReason']),
    'jsonb path operator malformed current source next106 changed text source has value reason' => static function (TestRunner $t) use ($currentRows): void {
        $changed = $currentRows;
        $changed[0]['option_value'] = '{"plugin":{"name":"cache-next","enabled":true,"priority":8}}';
        $plan = SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare($currentRows, $changed, '$.plugin.name');
        $t->same('jsonb-operator-path-result-changed', $plan['reprepareReason']);
        $t->same(true, $plan['valueChanged']);
    },
    'jsonb path operator malformed current source next106 source kind handles sql null' => static function (TestRunner $t): void {
        $plan = SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare([['option_id' => 1, 'option_value' => null]], [], '$.plugin.name');
        $t->same('sql-null', $plan['currentSignature'][0]['sourceKind']);
        $t->same(false, $plan['currentSignature'][0]['found']);
    },
    'jsonb path operator malformed current source next106 records dependency list' => static fn (TestRunner $t) => $t->same(['SQLiteJsonB', 'SQLiteJsonInspection', 'SQLiteJsonPath'], $textPlan()['dependencies']),
];

foreach ([
    'cache text row' => [1, 'cache'],
    'forms jsonb row' => [2, 'forms'],
    'missing text row' => [3, null],
] as $label => [$rowid, $expected]) {
    $tests['jsonb path operator malformed current source next106 generated current diagnostic ' . $label] = static function (TestRunner $t) use ($textPlan, $rowid, $expected): void {
        $diagnostic = $textPlan()['current']['diagnostics'][$rowid];
        $t->same($expected !== null, $diagnostic['found']);
        $t->same($expected, $diagnostic['result']);
    };
}

foreach ([
    'cache next valid' => [1, 'json-text', 'cache', null],
    'forms next malformed' => [2, 'malformed-jsonb', null, true],
    'missing next repaired' => [3, 'json-text', 'empty', null],
    'truncated next malformed' => [4, 'malformed-jsonb', null, true],
] as $label => [$rowid, $kind, $result, $hasError]) {
    $tests['jsonb path operator malformed current source next106 generated next diagnostic ' . $label] = static function (TestRunner $t) use ($textPlan, $rowid, $kind, $result, $hasError): void {
        $diagnostic = $textPlan()['next']['diagnostics'][$rowid];
        $t->same($kind, $diagnostic['sourceKind']);
        $t->same($result, $diagnostic['result']);
        $t->same($hasError === true, is_string($diagnostic['error']));
    };
}

return $tests;
