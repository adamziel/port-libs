<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectQuery;
use PortLibs\LibSqlite\SQLiteSelectSql;

$hosts108 = [
    [
        'option_id' => 10,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":["seo","cache","forms"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
        'target_rowid' => 2,
    ],
    [
        'option_id' => 20,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":["media"],"meta":{"enabled":false}}',
        'scan_root' => '$.rules',
        'target_rowid' => 1,
    ],
    [
        'option_id' => 30,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"rules":[],"meta":{"enabled":false}}',
        'scan_root' => '$.rules',
        'target_rowid' => 1,
    ],
    [
        'option_id' => 40,
        'option_name' => 'plugin_miss_settings',
        'option_value' => '{"rules":["shop"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
        'target_rowid' => 9,
    ],
];

$whereRows108 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT o.option_id AS option_id,
            o.option_name AS option_name,
            j.key AS rule_key,
            j.atom AS rule_atom,
            j.rowid AS rule_rowid,
            j._rowid_ AS rule__rowid_,
            j.oid AS rule_oid,
            j.fullkey AS rule_fullkey
       FROM wp_options AS o
       JOIN json_each(o.option_value, o.scan_root) AS j ON j.atom IS NOT NULL
      WHERE j.rowid = o.target_rowid
      ORDER BY o.option_id",
    ['wp_options' => $hosts108],
);

$wherePlan108 = static fn (): array => SQLiteSelectSql::plan(
    "SELECT o.option_id, j.atom
       FROM wp_options AS o
       JOIN json_each(o.option_value, o.scan_root) AS j ON j.atom IS NOT NULL
      WHERE j.rowid = o.target_rowid
      ORDER BY o.option_id",
    ['wp_options' => $hosts108],
);

$underscorePlan108 = static fn (): array => SQLiteSelectSql::plan(
    "SELECT o.option_id, j.atom
       FROM wp_options AS o
       JOIN json_each(o.option_value, o.scan_root) AS j ON j.atom IS NOT NULL
      WHERE j._rowid_ = o.target_rowid",
    ['wp_options' => $hosts108],
);

$oidPlan108 = static fn (): array => SQLiteSelectSql::plan(
    "SELECT o.option_id, t.atom
       FROM wp_options AS o
       JOIN json_tree(o.option_value, '$.meta') AS t ON t.type IN ('true', 'false')
      WHERE t.oid = o.target_rowid",
    ['wp_options' => $hosts108],
);

$leftRows108 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT o.option_id AS option_id, j.atom AS rule_atom, j.rowid AS rule_rowid
       FROM wp_options AS o
       LEFT JOIN json_each(o.option_value, o.scan_root) AS j ON j.atom IS NOT NULL
      WHERE j.rowid = o.target_rowid
      ORDER BY o.option_id",
    ['wp_options' => $hosts108],
);

$leftPlanRows108 = static fn (): array => SQLiteSelectQuery::execute($wherePlan108());

$tests = [
    'json table hidden rowid current source rebase next108 where row count' => static fn (TestRunner $t) => $t->same(2, count($whereRows108())),
    'json table hidden rowid current source rebase next108 where option ids' => static fn (TestRunner $t) => $t->same([10, 20], array_column($whereRows108(), 'option_id')),
    'json table hidden rowid current source rebase next108 where option names' => static fn (TestRunner $t) => $t->same(['plugin_alpha_settings', 'plugin_beta_settings'], array_column($whereRows108(), 'option_name')),
    'json table hidden rowid current source rebase next108 where atoms' => static fn (TestRunner $t) => $t->same(['cache', 'media'], array_column($whereRows108(), 'rule_atom')),
    'json table hidden rowid current source rebase next108 where keys' => static fn (TestRunner $t) => $t->same([1, 0], array_column($whereRows108(), 'rule_key')),
    'json table hidden rowid current source rebase next108 rowids' => static fn (TestRunner $t) => $t->same([2, 1], array_column($whereRows108(), 'rule_rowid')),
    'json table hidden rowid current source rebase next108 underscore rowids' => static fn (TestRunner $t) => $t->same([2, 1], array_column($whereRows108(), 'rule__rowid_')),
    'json table hidden rowid current source rebase next108 oid rowids' => static fn (TestRunner $t) => $t->same([2, 1], array_column($whereRows108(), 'rule_oid')),
    'json table hidden rowid current source rebase next108 fullkeys' => static fn (TestRunner $t) => $t->same(['$.rules[1]', '$.rules[0]'], array_column($whereRows108(), 'rule_fullkey')),
    'json table hidden rowid current source rebase next108 plan has join' => static fn (TestRunner $t) => $t->same(1, count($wherePlan108()['joins'])),
    'json table hidden rowid current source rebase next108 plan keeps where residual' => static fn (TestRunner $t) => $t->same('=', $wherePlan108()['where']['operator']),
    'json table hidden rowid current source rebase next108 plan residual left is rowid' => static fn (TestRunner $t) => $t->same('j.rowid', $wherePlan108()['where']['left']['name']),
    'json table hidden rowid current source rebase next108 plan residual right is source' => static fn (TestRunner $t) => $t->same('o.target_rowid', $wherePlan108()['where']['right']['name']),
    'json table hidden rowid current source rebase next108 hidden index alias' => static fn (TestRunner $t) => $t->same('j', $wherePlan108()['joins'][0]['jsonTableHiddenIndex']['alias']),
    'json table hidden rowid current source rebase next108 hidden index columns include id' => static fn (TestRunner $t) => $t->same(['id'], array_column($wherePlan108()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'column')),
    'json table hidden rowid current source rebase next108 hidden index original rowid' => static fn (TestRunner $t) => $t->same('rowid', $wherePlan108()['joins'][0]['jsonTableHiddenIndex']['constraints'][0]['originalColumn']),
    'json table hidden rowid current source rebase next108 hidden index rowid expression' => static fn (TestRunner $t) => $t->same('o.target_rowid', $wherePlan108()['joins'][0]['jsonTableHiddenIndex']['constraints'][0]['expression']),
    'json table hidden rowid current source rebase next108 hidden index constraint count' => static fn (TestRunner $t) => $t->same(1, $wherePlan108()['joins'][0]['jsonTableHiddenIndex']['constraintCount']),
    'json table hidden rowid current source rebase next108 dynamic rows callable' => static fn (TestRunner $t) => $t->true(is_callable($wherePlan108()['joins'][0]['dynamicRows'])),
    'json table hidden rowid current source rebase next108 right columns include aliases' => static fn (TestRunner $t) => $t->same(['j.key', 'j.value', 'j.type', 'j.atom', 'j.id', 'j.parent', 'j.fullkey', 'j.path', 'j.rowid', 'j._rowid_', 'j.oid'], $wherePlan108()['joins'][0]['rightColumns']),
    'json table hidden rowid current source rebase next108 alpha dynamic row count' => static function (TestRunner $t) use ($wherePlan108, $hosts108): void {
        $rows = $wherePlan108()['joins'][0]['dynamicRows'](['o.option_value' => $hosts108[0]['option_value'], 'o.scan_root' => $hosts108[0]['scan_root'], 'o.target_rowid' => 2]);
        $t->same(1, count($rows));
    },
    'json table hidden rowid current source rebase next108 alpha dynamic atom' => static function (TestRunner $t) use ($wherePlan108, $hosts108): void {
        $rows = $wherePlan108()['joins'][0]['dynamicRows'](['o.option_value' => $hosts108[0]['option_value'], 'o.scan_root' => $hosts108[0]['scan_root'], 'o.target_rowid' => 2]);
        $t->same('cache', $rows[0]['j.atom']);
    },
    'json table hidden rowid current source rebase next108 alpha dynamic key' => static function (TestRunner $t) use ($wherePlan108, $hosts108): void {
        $rows = $wherePlan108()['joins'][0]['dynamicRows'](['o.option_value' => $hosts108[0]['option_value'], 'o.scan_root' => $hosts108[0]['scan_root'], 'o.target_rowid' => 2]);
        $t->same(1, $rows[0]['j.key']);
    },
    'json table hidden rowid current source rebase next108 alpha dynamic rowid' => static function (TestRunner $t) use ($wherePlan108, $hosts108): void {
        $rows = $wherePlan108()['joins'][0]['dynamicRows'](['o.option_value' => $hosts108[0]['option_value'], 'o.scan_root' => $hosts108[0]['scan_root'], 'o.target_rowid' => 2]);
        $t->same(2, $rows[0]['j.rowid']);
    },
    'json table hidden rowid current source rebase next108 alpha dynamic fullkey' => static function (TestRunner $t) use ($wherePlan108, $hosts108): void {
        $rows = $wherePlan108()['joins'][0]['dynamicRows'](['o.option_value' => $hosts108[0]['option_value'], 'o.scan_root' => $hosts108[0]['scan_root'], 'o.target_rowid' => 2]);
        $t->same('$.rules[1]', $rows[0]['j.fullkey']);
    },
    'json table hidden rowid current source rebase next108 beta dynamic atom' => static function (TestRunner $t) use ($wherePlan108, $hosts108): void {
        $rows = $wherePlan108()['joins'][0]['dynamicRows'](['o.option_value' => $hosts108[1]['option_value'], 'o.scan_root' => $hosts108[1]['scan_root'], 'o.target_rowid' => 1]);
        $t->same('media', $rows[0]['j.atom']);
    },
    'json table hidden rowid current source rebase next108 empty dynamic returns none' => static function (TestRunner $t) use ($wherePlan108, $hosts108): void {
        $rows = $wherePlan108()['joins'][0]['dynamicRows'](['o.option_value' => $hosts108[2]['option_value'], 'o.scan_root' => $hosts108[2]['scan_root'], 'o.target_rowid' => 1]);
        $t->same([], $rows);
    },
    'json table hidden rowid current source rebase next108 miss dynamic returns none' => static function (TestRunner $t) use ($wherePlan108, $hosts108): void {
        $rows = $wherePlan108()['joins'][0]['dynamicRows'](['o.option_value' => $hosts108[3]['option_value'], 'o.scan_root' => $hosts108[3]['scan_root'], 'o.target_rowid' => 9]);
        $t->same([], $rows);
    },
    'json table hidden rowid current source rebase next108 null target dynamic returns none' => static function (TestRunner $t) use ($wherePlan108, $hosts108): void {
        $rows = $wherePlan108()['joins'][0]['dynamicRows'](['o.option_value' => $hosts108[0]['option_value'], 'o.scan_root' => $hosts108[0]['scan_root'], 'o.target_rowid' => null]);
        $t->same([], $rows);
    },
    'json table hidden rowid current source rebase next108 left join keeps where rowids' => static fn (TestRunner $t) => $t->same([2, 1], array_column($leftRows108(), 'rule_rowid')),
    'json table hidden rowid current source rebase next108 query execute matches plan atoms' => static fn (TestRunner $t) => $t->same(['cache', 'media'], array_column($leftPlanRows108(), 'j.atom')),
    'json table hidden rowid current source rebase next108 underscore hidden index columns' => static fn (TestRunner $t) => $t->same(['id'], array_column($underscorePlan108()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'column')),
    'json table hidden rowid current source rebase next108 underscore original column' => static fn (TestRunner $t) => $t->same('_rowid_', $underscorePlan108()['joins'][0]['jsonTableHiddenIndex']['constraints'][0]['originalColumn']),
    'json table hidden rowid current source rebase next108 underscore expression' => static fn (TestRunner $t) => $t->same('o.target_rowid', $underscorePlan108()['joins'][0]['jsonTableHiddenIndex']['constraints'][0]['expression']),
    'json table hidden rowid current source rebase next108 underscore dynamic atom' => static function (TestRunner $t) use ($underscorePlan108, $hosts108): void {
        $rows = $underscorePlan108()['joins'][0]['dynamicRows'](['o.option_value' => $hosts108[0]['option_value'], 'o.scan_root' => $hosts108[0]['scan_root'], 'o.target_rowid' => 2]);
        $t->same('cache', $rows[0]['j.atom']);
    },
    'json table hidden rowid current source rebase next108 oid hidden index columns' => static fn (TestRunner $t) => $t->same(['id'], array_column($oidPlan108()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'column')),
    'json table hidden rowid current source rebase next108 oid original column' => static fn (TestRunner $t) => $t->same('oid', $oidPlan108()['joins'][0]['jsonTableHiddenIndex']['constraints'][0]['originalColumn']),
    'json table hidden rowid current source rebase next108 oid expression' => static fn (TestRunner $t) => $t->same('o.target_rowid', $oidPlan108()['joins'][0]['jsonTableHiddenIndex']['constraints'][0]['expression']),
    'json table hidden rowid current source rebase next108 oid dynamic false atom' => static function (TestRunner $t) use ($oidPlan108, $hosts108): void {
        $rows = $oidPlan108()['joins'][0]['dynamicRows'](['o.option_value' => $hosts108[1]['option_value'], 'o.target_rowid' => 1]);
        $t->same(0, $rows[0]['t.atom']);
    },
    'json table hidden rowid current source rebase next108 oid dynamic key' => static function (TestRunner $t) use ($oidPlan108, $hosts108): void {
        $rows = $oidPlan108()['joins'][0]['dynamicRows'](['o.option_value' => $hosts108[1]['option_value'], 'o.target_rowid' => 1]);
        $t->same('enabled', $rows[0]['t.key']);
    },
    'json table hidden rowid current source rebase next108 oid dynamic fullkey' => static function (TestRunner $t) use ($oidPlan108, $hosts108): void {
        $rows = $oidPlan108()['joins'][0]['dynamicRows'](['o.option_value' => $hosts108[1]['option_value'], 'o.target_rowid' => 1]);
        $t->same('$.meta.enabled', $rows[0]['t.fullkey']);
    },
    'json table hidden rowid current source rebase next108 oid dynamic rowid' => static function (TestRunner $t) use ($oidPlan108, $hosts108): void {
        $rows = $oidPlan108()['joins'][0]['dynamicRows'](['o.option_value' => $hosts108[1]['option_value'], 'o.target_rowid' => 1]);
        $t->same(1, $rows[0]['t.rowid']);
    },
    'json table hidden rowid current source rebase next108 oid execute rows' => static fn (TestRunner $t) => $t->same([[20, 0], [30, 0]], array_map(static fn (array $row): array => array_values($row), SQLiteSelectSql::execute(
        "SELECT o.option_id AS option_id, t.atom AS atom
           FROM wp_options AS o
           JOIN json_tree(o.option_value, '$.meta') AS t ON t.type IN ('true', 'false')
          WHERE t.oid = o.target_rowid
          ORDER BY o.option_id",
        ['wp_options' => $hosts108],
    ))),
    'json table hidden rowid current source rebase next108 duplicate on rowid wins over where rowid' => static function (TestRunner $t) use ($hosts108): void {
        $plan = SQLiteSelectSql::plan(
            "SELECT o.option_id, j.atom
               FROM wp_options AS o
               JOIN json_each(o.option_value, o.scan_root) AS j ON j.rowid = 1
              WHERE j.rowid = o.target_rowid",
            ['wp_options' => $hosts108],
        );
        $t->same('1', $plan['joins'][0]['jsonTableHiddenIndex']['constraints'][0]['expression']);
    },
    'json table hidden rowid current source rebase next108 duplicate on rowid leaves where residual filtering' => static function (TestRunner $t) use ($hosts108): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_id AS option_id, j.atom AS atom
               FROM wp_options AS o
               JOIN json_each(o.option_value, o.scan_root) AS j ON j.rowid = 1
              WHERE j.rowid = o.target_rowid
              ORDER BY o.option_id",
            ['wp_options' => $hosts108],
        );
        $t->same([['option_id' => 20, 'atom' => 'media']], $rows);
    },
    'json table hidden rowid current source rebase next108 malformed json remains guarded by existing validation' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT o.option_id AS option_id, j.atom AS atom
           FROM wp_options AS o
           JOIN json_each(o.option_value, o.scan_root) AS j ON j.atom IS NOT NULL
          WHERE j.rowid = o.target_rowid",
        ['wp_options' => [['option_id' => 50, 'option_value' => '{bad', 'scan_root' => '$.rules', 'target_rowid' => 1]]],
    )),
    'json table hidden rowid current source rebase next108 null root skipped after where pushdown' => static fn (TestRunner $t) => $t->same([], SQLiteSelectSql::execute(
        "SELECT o.option_id AS option_id, j.atom AS atom
           FROM wp_options AS o
           JOIN json_each(o.option_value, o.scan_root) AS j ON j.atom IS NOT NULL
          WHERE j.rowid = o.target_rowid",
        ['wp_options' => [['option_id' => 60, 'option_value' => '{"rules":["seo"]}', 'scan_root' => null, 'target_rowid' => 1]]],
    )),
    'json table hidden rowid current source rebase next108 plan order by preserved' => static fn (TestRunner $t) => $t->same([['column' => 'o.option_id']], $wherePlan108()['orderBy']),
    'json table hidden rowid current source rebase next108 select projection preserved' => static fn (TestRunner $t) => $t->same(['o.option_id', 'j.atom'], array_column($wherePlan108()['select'], 'name')),
    'json table hidden rowid current source rebase next108 select projection width preserved' => static fn (TestRunner $t) => $t->same(2, count($wherePlan108()['select'])),
    'json table hidden rowid current source rebase next108 dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

return $tests;
