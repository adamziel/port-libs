<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$hosts93 = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":["seo","cache","media"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
        'target_rowid' => 2,
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":["forms"],"meta":{"enabled":false}}',
        'scan_root' => '$.rules',
        'target_rowid' => 1,
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"rules":[],"meta":{"enabled":false}}',
        'scan_root' => '$.rules',
        'target_rowid' => 1,
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_missing_settings',
        'option_value' => '{"rules":["unused"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
        'target_rowid' => 9,
    ],
];

$rows93 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT o.option_id AS option_id,
            o.option_name AS option_name,
            j.key AS rule_key,
            j.atom AS rule_atom,
            j.rowid AS rule_rowid,
            j._rowid_ AS rule__rowid_,
            j.oid AS rule_oid,
            j.fullkey AS rule_fullkey
       FROM wp_options AS o
       LEFT JOIN json_each(o.option_value, o.scan_root) AS j
              ON j.rowid = o.target_rowid
      ORDER BY o.option_id",
    ['wp_options' => $hosts93],
);

$oidRows93 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT o.option_id AS option_id,
            t.key AS node_key,
            t.atom AS node_atom,
            t.oid AS node_oid,
            t.fullkey AS node_fullkey
       FROM wp_options AS o
       JOIN json_tree(o.option_value, '$.meta') AS t
         ON t.oid = o.target_rowid
      ORDER BY o.option_id",
    ['wp_options' => $hosts93],
);

$underscoreRows93 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT o.option_id AS option_id,
            j.atom AS atom,
            j._rowid_ AS rid
       FROM wp_options AS o
       JOIN json_each(o.option_value, o.scan_root) AS j
         ON j._rowid_ = o.target_rowid
      ORDER BY o.option_id",
    ['wp_options' => $hosts93],
);

$literalRows93 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT o.option_id AS option_id,
            j.atom AS atom,
            j.rowid AS rid
       FROM wp_options AS o
       JOIN json_each(o.option_value, o.scan_root) AS j
         ON 1 = j.rowid
      ORDER BY o.option_id",
    ['wp_options' => $hosts93],
);

$plan93 = static fn (): array => SQLiteSelectSql::plan(
    "SELECT o.option_name, j.atom
       FROM wp_options AS o
       LEFT JOIN json_each(o.option_value, o.scan_root) AS j
              ON j.rowid = o.target_rowid",
    ['wp_options' => $hosts93],
);

$oidPlan93 = static fn (): array => SQLiteSelectSql::plan(
    "SELECT o.option_name, t.atom
       FROM wp_options AS o
       JOIN json_tree(o.option_value, '$.meta') AS t
         ON t.oid = o.target_rowid",
    ['wp_options' => $hosts93],
);

$underscorePlan93 = static fn (): array => SQLiteSelectSql::plan(
    "SELECT o.option_name, j.atom
       FROM wp_options AS o
       JOIN json_each(o.option_value, o.scan_root) AS j
         ON j._rowid_ = o.target_rowid",
    ['wp_options' => $hosts93],
);

$tests = [
    'json table lateral rowid hidden current source next93 left join row count keeps current hosts' => static fn (TestRunner $t) => $t->same(4, count($rows93())),
    'json table lateral rowid hidden current source next93 option order follows current source' => static fn (TestRunner $t) => $t->same([1, 2, 3, 4], array_column($rows93(), 'option_id')),
    'json table lateral rowid hidden current source next93 rowid filters per host' => static fn (TestRunner $t) => $t->same([2, 1, null, null], array_column($rows93(), 'rule_rowid')),
    'json table lateral rowid hidden current source next93 atoms follow host rowid target' => static fn (TestRunner $t) => $t->same(['cache', 'forms', null, null], array_column($rows93(), 'rule_atom')),
    'json table lateral rowid hidden current source next93 keys follow host rowid target' => static fn (TestRunner $t) => $t->same([1, 0, null, null], array_column($rows93(), 'rule_key')),
    'json table lateral rowid hidden current source next93 underscore rowid mirrors rowid' => static fn (TestRunner $t) => $t->same([2, 1, null, null], array_column($rows93(), 'rule__rowid_')),
    'json table lateral rowid hidden current source next93 oid mirrors rowid' => static fn (TestRunner $t) => $t->same([2, 1, null, null], array_column($rows93(), 'rule_oid')),
    'json table lateral rowid hidden current source next93 fullkey follows selected current source row' => static fn (TestRunner $t) => $t->same(['$.rules[1]', '$.rules[0]', null, null], array_column($rows93(), 'rule_fullkey')),
    'json table lateral rowid hidden current source next93 empty array left extends null atom' => static fn (TestRunner $t) => $t->same(null, $rows93()[2]['rule_atom']),
    'json table lateral rowid hidden current source next93 missing target left extends null atom' => static fn (TestRunner $t) => $t->same(null, $rows93()[3]['rule_atom']),
    'json table lateral rowid hidden current source next93 oid join row count' => static fn (TestRunner $t) => $t->same(2, count($oidRows93())),
    'json table lateral rowid hidden current source next93 oid join option ids' => static fn (TestRunner $t) => $t->same([2, 3], array_column($oidRows93(), 'option_id')),
    'json table lateral rowid hidden current source next93 oid join selects enabled leaves' => static fn (TestRunner $t) => $t->same(['enabled', 'enabled'], array_column($oidRows93(), 'node_key')),
    'json table lateral rowid hidden current source next93 oid join atoms use current host json' => static fn (TestRunner $t) => $t->same([0, 0], array_column($oidRows93(), 'node_atom')),
    'json table lateral rowid hidden current source next93 oid join preserves oid values' => static fn (TestRunner $t) => $t->same([1, 1], array_column($oidRows93(), 'node_oid')),
    'json table lateral rowid hidden current source next93 oid join fullkeys are root scoped' => static fn (TestRunner $t) => $t->same(['$.meta.enabled', '$.meta.enabled'], array_column($oidRows93(), 'node_fullkey')),
    'json table lateral rowid hidden current source next93 underscore join rows match rowid join non-null hosts' => static fn (TestRunner $t) => $t->same([[1, 'cache', 2], [2, 'forms', 1]], array_map(static fn (array $row): array => array_values($row), $underscoreRows93())),
    'json table lateral rowid hidden current source next93 literal rowid constraint still applies per current source' => static fn (TestRunner $t) => $t->same([[1, 'seo', 1], [2, 'forms', 1], [4, 'unused', 1]], array_map(static fn (array $row): array => array_values($row), $literalRows93())),
    'json table lateral rowid hidden current source next93 plan records hidden index alias' => static fn (TestRunner $t) => $t->same('j', $plan93()['joins'][0]['jsonTableHiddenIndex']['alias']),
    'json table lateral rowid hidden current source next93 plan records id hidden constraint' => static fn (TestRunner $t) => $t->same(['id'], array_column($plan93()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'column')),
    'json table lateral rowid hidden current source next93 plan records rowid expression source' => static fn (TestRunner $t) => $t->same(['o.target_rowid'], array_column($plan93()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'expression')),
    'json table lateral rowid hidden current source next93 rowid predicate removed after hidden extraction' => static fn (TestRunner $t) => $t->true(is_callable($plan93()['joins'][0]['predicate'])),
    'json table lateral rowid hidden current source next93 right columns include rowid aliases' => static fn (TestRunner $t) => $t->same(['j.key', 'j.value', 'j.type', 'j.atom', 'j.id', 'j.parent', 'j.fullkey', 'j.path', 'j.rowid', 'j._rowid_', 'j.oid'], $plan93()['joins'][0]['rightColumns']),
    'json table lateral rowid hidden current source next93 dynamic rows are indexed by host row' => static fn (TestRunner $t) => $t->true(is_callable($plan93()['joins'][0]['dynamicRows'])),
    'json table lateral rowid hidden current source next93 oid plan records id hidden constraint' => static fn (TestRunner $t) => $t->same(['id'], array_column($oidPlan93()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'column')),
    'json table lateral rowid hidden current source next93 oid plan records expression' => static fn (TestRunner $t) => $t->same(['o.target_rowid'], array_column($oidPlan93()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'expression')),
    'json table lateral rowid hidden current source next93 underscore plan records id hidden constraint' => static fn (TestRunner $t) => $t->same(['id'], array_column($underscorePlan93()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'column')),
    'json table lateral rowid hidden current source next93 underscore plan records expression' => static fn (TestRunner $t) => $t->same(['o.target_rowid'], array_column($underscorePlan93()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'expression')),
    'json table lateral rowid hidden current source next93 dynamic rowid constraint narrows alpha to one row' => static function (TestRunner $t) use ($plan93, $hosts93): void {
        $rows = $plan93()['joins'][0]['dynamicRows'](['o.option_value' => $hosts93[0]['option_value'], 'o.scan_root' => $hosts93[0]['scan_root'], 'o.target_rowid' => 2]);
        $t->same(1, count($rows));
    },
    'json table lateral rowid hidden current source next93 dynamic rowid constraint returns alpha cache' => static function (TestRunner $t) use ($plan93, $hosts93): void {
        $rows = $plan93()['joins'][0]['dynamicRows'](['o.option_value' => $hosts93[0]['option_value'], 'o.scan_root' => $hosts93[0]['scan_root'], 'o.target_rowid' => 2]);
        $t->same('cache', $rows[0]['j.atom']);
    },
    'json table lateral rowid hidden current source next93 dynamic rowid constraint returns beta forms' => static function (TestRunner $t) use ($plan93, $hosts93): void {
        $rows = $plan93()['joins'][0]['dynamicRows'](['o.option_value' => $hosts93[1]['option_value'], 'o.scan_root' => $hosts93[1]['scan_root'], 'o.target_rowid' => 1]);
        $t->same('forms', $rows[0]['j.atom']);
    },
    'json table lateral rowid hidden current source next93 dynamic rowid miss returns empty' => static function (TestRunner $t) use ($plan93, $hosts93): void {
        $rows = $plan93()['joins'][0]['dynamicRows'](['o.option_value' => $hosts93[3]['option_value'], 'o.scan_root' => $hosts93[3]['scan_root'], 'o.target_rowid' => 9]);
        $t->same([], $rows);
    },
    'json table lateral rowid hidden current source next93 dynamic oid constraint narrows tree' => static function (TestRunner $t) use ($oidPlan93, $hosts93): void {
        $rows = $oidPlan93()['joins'][0]['dynamicRows'](['o.option_value' => $hosts93[0]['option_value'], 'o.target_rowid' => 1]);
        $t->same([['t.key' => 'enabled', 't.value' => 1, 't.type' => 'true', 't.atom' => 1, 't.id' => 1, 't.parent' => 0, 't.fullkey' => '$.meta.enabled', 't.path' => '$.meta', 't.rowid' => 1, 't._rowid_' => 1, 't.oid' => 1]], $rows);
    },
    'json table lateral rowid hidden current source next93 dynamic underscore constraint narrows each' => static function (TestRunner $t) use ($underscorePlan93, $hosts93): void {
        $rows = $underscorePlan93()['joins'][0]['dynamicRows'](['o.option_value' => $hosts93[0]['option_value'], 'o.scan_root' => $hosts93[0]['scan_root'], 'o.target_rowid' => 2]);
        $t->same('cache', $rows[0]['j.atom']);
    },
    'json table lateral rowid hidden current source next93 null rowid expression returns empty dynamic rows' => static function (TestRunner $t) use ($plan93, $hosts93): void {
        $rows = $plan93()['joins'][0]['dynamicRows'](['o.option_value' => $hosts93[0]['option_value'], 'o.scan_root' => $hosts93[0]['scan_root'], 'o.target_rowid' => null]);
        $t->same([], $rows);
    },
    'json table lateral rowid hidden current source next93 left join null rowid expression extends null' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_id AS option_id, j.rowid AS rid
               FROM wp_options AS o
               LEFT JOIN json_each(o.option_value, o.scan_root) AS j ON j.rowid = o.target_rowid",
            ['wp_options' => [['option_id' => 9, 'option_value' => '{"rules":["seo"]}', 'scan_root' => '$.rules', 'target_rowid' => null]]],
        );
        $t->same([['option_id' => 9, 'rid' => null]], $rows);
    },
    'json table lateral rowid hidden current source next93 rowid expression composes with visible residual' => static function (TestRunner $t) use ($hosts93): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_id AS option_id, j.atom AS atom
               FROM wp_options AS o
               JOIN json_each(o.option_value, o.scan_root) AS j
                 ON j.rowid = o.target_rowid AND j.atom = 'cache'",
            ['wp_options' => $hosts93],
        );
        $t->same([['option_id' => 1, 'atom' => 'cache']], $rows);
    },
    'json table lateral rowid hidden current source next93 commuted rowid expression is extracted' => static function (TestRunner $t) use ($hosts93): void {
        $plan = SQLiteSelectSql::plan(
            "SELECT o.option_name, j.atom
               FROM wp_options AS o
               JOIN json_each(o.option_value, o.scan_root) AS j
                 ON o.target_rowid = j.rowid",
            ['wp_options' => $hosts93],
        );
        $t->same(['id'], array_column($plan['joins'][0]['jsonTableHiddenIndex']['constraints'], 'column'));
    },
    'json table lateral rowid hidden current source next93 commuted oid expression is extracted' => static function (TestRunner $t) use ($hosts93): void {
        $plan = SQLiteSelectSql::plan(
            "SELECT o.option_name, t.atom
               FROM wp_options AS o
               JOIN json_tree(o.option_value, '$.meta') AS t
                 ON o.target_rowid = t.oid",
            ['wp_options' => $hosts93],
        );
        $t->same(['id'], array_column($plan['joins'][0]['jsonTableHiddenIndex']['constraints'], 'column'));
    },
    'json table lateral rowid hidden current source next93 bad alias does not create hidden index' => static function (TestRunner $t) use ($hosts93): void {
        $plan = SQLiteSelectSql::plan(
            "SELECT o.option_name, j.atom
               FROM wp_options AS o
               JOIN json_each(o.option_value, o.scan_root) AS j
                 ON o.target_rowid = o.option_id",
            ['wp_options' => $hosts93],
        );
        $t->same(false, isset($plan['joins'][0]['jsonTableHiddenIndex']));
    },
];

return $tests;
