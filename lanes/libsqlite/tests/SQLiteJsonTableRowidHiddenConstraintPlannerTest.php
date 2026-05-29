<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$current99 = [
    'option_id' => 501,
    'option_name' => 'wp_plugin_json_rowids',
    'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":false},{"slug":"forms","enabled":true}]}',
    'scan_root' => '$.rules',
    'target_rowid' => 2,
];
$next99 = [
    'option_id' => 501,
    'option_name' => 'wp_plugin_json_rowids',
    'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":true},{"slug":"forms","enabled":true},{"slug":"shop","enabled":true}]}',
    'scan_root' => '$.rules',
    'target_rowid' => 2,
];

$plan99 = static fn (array $constraints = null): array => SQLiteJsonTablePlan::currentSourceRowidHiddenConstraintPlanner(
    'json_tree',
    $current99,
    $next99,
    'option_value',
    $constraints ?? [['column' => 'rowid', 'operator' => '=', 'value' => 6]],
    'scan_root',
    [['column' => 'rowid', 'direction' => 'ASC']],
);
$underscore99 = static fn (): array => $plan99([['column' => '_rowid_', 'operator' => '=', 'value' => 6]]);
$oid99 = static fn (): array => $plan99([['column' => 'oid', 'operator' => '=', 'value' => 6]]);
$mixed99 = static fn (): array => $plan99([
    ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ['column' => 'type', 'operator' => '=', 'value' => 'false'],
]);

$hosts99 = [
    $current99,
    array_replace($current99, [
        'option_id' => 502,
        'option_name' => 'wp_plugin_json_rowids_next',
        'option_value' => $next99['option_value'],
        'target_rowid' => 6,
    ]),
];

$rowidPlan99 = static fn (): array => SQLiteSelectSql::plan(
    "SELECT o.option_name, j.atom
       FROM wp_options AS o
       JOIN json_tree(o.option_value, o.scan_root) AS j
         ON j.rowid = o.target_rowid",
    ['wp_options' => $hosts99],
);
$underscorePlan99 = static fn (): array => SQLiteSelectSql::plan(
    "SELECT o.option_name, j.atom
       FROM wp_options AS o
       JOIN json_tree(o.option_value, o.scan_root) AS j
         ON j._rowid_ = o.target_rowid",
    ['wp_options' => $hosts99],
);
$oidPlan99 = static fn (): array => SQLiteSelectSql::plan(
    "SELECT o.option_name, j.atom
       FROM wp_options AS o
       JOIN json_tree(o.option_value, o.scan_root) AS j
         ON o.target_rowid = j.oid",
    ['wp_options' => $hosts99],
);
$rows99 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT o.option_id AS option_id,
            j.rowid AS rid,
            j._rowid_ AS underscored,
            j.oid AS oid,
            j.atom AS atom
       FROM wp_options AS o
       JOIN json_tree(o.option_value, o.scan_root) AS j
         ON j.rowid = o.target_rowid
      ORDER BY o.option_id",
    ['wp_options' => $hosts99],
);

$tests = [
    'function is normalized' => static fn (TestRunner $t) => $t->same('json_tree', $plan99()['function']),
    'dependency marker is appended' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-rowid-hidden-constraint-planner', $plan99()['dependencies'], true)),
    'rowid original alias is preserved for current' => static fn (TestRunner $t) => $t->same('rowid', $plan99()['currentRowidAliasConstraints'][0]['originalColumn']),
    'rowid original alias is preserved for next' => static fn (TestRunner $t) => $t->same('rowid', $plan99()['nextRowidAliasConstraints'][0]['originalColumn']),
    'rowid normalizes to id for current' => static fn (TestRunner $t) => $t->same('id', $plan99()['currentRowidAliasConstraints'][0]['normalizedColumn']),
    'rowid normalizes to id for next' => static fn (TestRunner $t) => $t->same('id', $plan99()['nextRowidAliasConstraints'][0]['normalizedColumn']),
    'rowid alias source is source constraint' => static fn (TestRunner $t) => $t->same('source-constraint', $plan99()['currentRowidAliasConstraints'][0]['source']),
    'rowid alias operator is equality' => static fn (TestRunner $t) => $t->same('=', $plan99()['currentRowidAliasConstraints'][0]['operator']),
    'rowid alias remains usable' => static fn (TestRunner $t) => $t->same(true, $plan99()['currentRowidAliasConstraints'][0]['usable']),
    'rowid alias transition records current alias' => static fn (TestRunner $t) => $t->same(['rowid'], $plan99()['rowidAliasTransition']['current']),
    'rowid alias transition records next alias' => static fn (TestRunner $t) => $t->same(['rowid'], $plan99()['rowidAliasTransition']['next']),
    'rowid alias transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan99()['rowidAliasTransition']['changed']),
    'underscore original alias is preserved' => static fn (TestRunner $t) => $t->same('_rowid_', $underscore99()['currentRowidAliasConstraints'][0]['originalColumn']),
    'underscore normalizes to id' => static fn (TestRunner $t) => $t->same('id', $underscore99()['currentRowidAliasConstraints'][0]['normalizedColumn']),
    'underscore transition records alias' => static fn (TestRunner $t) => $t->same(['_rowid_'], $underscore99()['rowidAliasTransition']['current']),
    'oid original alias is preserved' => static fn (TestRunner $t) => $t->same('oid', $oid99()['currentRowidAliasConstraints'][0]['originalColumn']),
    'oid normalizes to id' => static fn (TestRunner $t) => $t->same('id', $oid99()['currentRowidAliasConstraints'][0]['normalizedColumn']),
    'oid transition records alias' => static fn (TestRunner $t) => $t->same(['oid'], $oid99()['rowidAliasTransition']['current']),
    'mixed constraints only record rowid alias' => static fn (TestRunner $t) => $t->same(['rowid'], array_column($mixed99()['currentRowidAliasConstraints'], 'originalColumn')),
    'mixed constraints preserve current rows' => static fn (TestRunner $t) => $t->same([6], $mixed99()['currentRowidSummary']['rowids']),
    'mixed constraints preserve next residual filtering' => static fn (TestRunner $t) => $t->same([], $mixed99()['nextRowidSummary']['rowids']),
    'rowid hidden constraint reasons include hidden rowid reasons' => static fn (TestRunner $t) => $t->true(in_array('hidden-rowid-residual-constraint-present', $plan99()['rowidHiddenConstraintReplanReasons'], true)),
    'rowid hidden constraint reasons include source json changed' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan99()['rowidHiddenConstraintReplanReasons'], true)),
    'rowid hidden constraint reasons do not invent alias change for stable alias' => static fn (TestRunner $t) => $t->same(false, in_array('hidden-rowid-alias-provenance-changed', $plan99()['rowidHiddenConstraintReplanReasons'], true)),
    'rowid selected current atom remains false' => static fn (TestRunner $t) => $t->same(0, $plan99()['currentRows'][0]['atom']),
    'rowid selected next atom becomes true' => static fn (TestRunner $t) => $t->same(1, $plan99()['nextRows'][0]['atom']),
    'rowid selected fullkey is stable' => static fn (TestRunner $t) => $t->same('$.rules[1].enabled', $plan99()['currentRows'][0]['fullkey']),
    'rowid alias constraint still records normalized column' => static fn (TestRunner $t) => $t->same(['id'], array_column($plan99()['currentRowidAliasConstraints'], 'normalizedColumn')),
    'underscore alias constraint records normalized column' => static fn (TestRunner $t) => $t->same(['id'], array_column($underscore99()['currentRowidAliasConstraints'], 'normalizedColumn')),
    'oid alias constraint records normalized column' => static fn (TestRunner $t) => $t->same(['id'], array_column($oid99()['currentRowidAliasConstraints'], 'normalizedColumn')),
    'parser plan rowid hidden index normalizes column' => static fn (TestRunner $t) => $t->same(['id'], array_column($rowidPlan99()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'column')),
    'parser plan rowid hidden index preserves original alias' => static fn (TestRunner $t) => $t->same(['rowid'], array_column($rowidPlan99()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'originalColumn')),
    'parser plan underscore hidden index normalizes column' => static fn (TestRunner $t) => $t->same(['id'], array_column($underscorePlan99()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'column')),
    'parser plan underscore hidden index preserves original alias' => static fn (TestRunner $t) => $t->same(['_rowid_'], array_column($underscorePlan99()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'originalColumn')),
    'parser plan oid hidden index normalizes column' => static fn (TestRunner $t) => $t->same(['id'], array_column($oidPlan99()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'column')),
    'parser plan oid hidden index preserves original alias' => static fn (TestRunner $t) => $t->same(['oid'], array_column($oidPlan99()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'originalColumn')),
    'parser plan rowid expression remains host target' => static fn (TestRunner $t) => $t->same(['o.target_rowid'], array_column($rowidPlan99()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'expression')),
    'parser plan rowid constraint count is one' => static fn (TestRunner $t) => $t->same(1, $rowidPlan99()['joins'][0]['jsonTableHiddenIndex']['constraintCount']),
    'parser plan dynamic rows remain callable' => static fn (TestRunner $t) => $t->true(is_callable($rowidPlan99()['joins'][0]['dynamicRows'])),
    'parser execution keeps rowid alias values' => static fn (TestRunner $t) => $t->same([2, 6], array_column($rows99(), 'rid')),
    'parser execution keeps underscore alias values' => static fn (TestRunner $t) => $t->same([2, 6], array_column($rows99(), 'underscored')),
    'parser execution keeps oid alias values' => static fn (TestRunner $t) => $t->same([2, 6], array_column($rows99(), 'oid')),
    'parser execution rowid hidden constraint selects seo object' => static fn (TestRunner $t) => $t->same('seo', $rows99()[0]['atom']),
    'parser execution rowid hidden constraint selects enabled leaf' => static fn (TestRunner $t) => $t->same(1, $rows99()[1]['atom']),
    'dynamic rowid hidden index narrows current source' => static function (TestRunner $t) use ($rowidPlan99, $current99): void {
        $rows = $rowidPlan99()['joins'][0]['dynamicRows'](['o.option_value' => $current99['option_value'], 'o.scan_root' => $current99['scan_root'], 'o.target_rowid' => 2]);
        $t->same(1, count($rows));
    },
    'dynamic rowid hidden index returns current seo object' => static function (TestRunner $t) use ($rowidPlan99, $current99): void {
        $rows = $rowidPlan99()['joins'][0]['dynamicRows'](['o.option_value' => $current99['option_value'], 'o.scan_root' => $current99['scan_root'], 'o.target_rowid' => 2]);
        $t->same('seo', $rows[0]['j.atom']);
    },
    'dynamic underscore hidden index returns current seo object' => static function (TestRunner $t) use ($underscorePlan99, $current99): void {
        $rows = $underscorePlan99()['joins'][0]['dynamicRows'](['o.option_value' => $current99['option_value'], 'o.scan_root' => $current99['scan_root'], 'o.target_rowid' => 2]);
        $t->same('seo', $rows[0]['j.atom']);
    },
    'dynamic oid hidden index returns enabled leaf' => static function (TestRunner $t) use ($oidPlan99, $current99): void {
        $rows = $oidPlan99()['joins'][0]['dynamicRows'](['o.option_value' => $current99['option_value'], 'o.scan_root' => $current99['scan_root'], 'o.target_rowid' => 6]);
        $t->same(0, $rows[0]['j.atom']);
    },
    'dynamic rowid null target returns empty' => static function (TestRunner $t) use ($rowidPlan99, $current99): void {
        $rows = $rowidPlan99()['joins'][0]['dynamicRows'](['o.option_value' => $current99['option_value'], 'o.scan_root' => $current99['scan_root'], 'o.target_rowid' => null]);
        $t->same([], $rows);
    },
    'bad function is still rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceRowidHiddenConstraintPlanner('json_bad', $current99, $next99, 'option_value')),
    'missing current json column is still rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceRowidHiddenConstraintPlanner('json_tree', [], $next99, 'option_value')),
];

foreach ($tests as $name => $case) {
    $tests['json table rowid hidden constraint current source ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
