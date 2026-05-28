<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$hosts85 = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":["seo","cache"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":[],"meta":{"enabled":false}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_gamma_settings',
        'option_value' => '{"rules":["forms"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
];

$hiddenRows85 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT o.option_name AS option_name,
            j.key AS rule_key,
            j.atom AS rule_atom,
            j.rowid AS rule_rowid,
            j._rowid_ AS rule__rowid_,
            j.oid AS rule_oid,
            j.fullkey AS rule_fullkey
       FROM wp_options AS o
       LEFT JOIN json_each AS j
              ON j.json = o.option_value
             AND j.root = o.scan_root
             AND j.atom IS NOT NULL
      ORDER BY o.option_id, j.rowid",
    ['wp_options' => $hosts85],
);

$treeRows85 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT o.option_id AS option_id,
            t.key AS node_key,
            t.type AS node_type,
            t.rowid AS node_rowid,
            t._rowid_ AS node__rowid_,
            t.oid AS node_oid,
            t.fullkey AS node_fullkey
       FROM wp_options AS o
       JOIN json_tree AS t
         ON t.json = o.option_value
        AND t.root = '$.meta'
      WHERE t.type IN ('object', 'true', 'false')
      ORDER BY o.option_id, t.rowid",
    ['wp_options' => $hosts85],
);

$plan85 = static fn (): array => SQLiteSelectSql::plan(
    "SELECT o.option_name, j.rowid
       FROM wp_options AS o
       LEFT JOIN json_each AS j
              ON j.json = o.option_value
             AND j.root = o.scan_root
             AND j.atom IS NOT NULL",
    ['wp_options' => $hosts85],
);

$tests = [
    'hidden lateral rowid current source next85 row count keeps left empty host' => static function (TestRunner $t) use ($hiddenRows85): void {
        $t->same(4, count($hiddenRows85()));
    },
    'hidden lateral rowid current source next85 option order follows host rows' => static function (TestRunner $t) use ($hiddenRows85): void {
        $t->same(['plugin_alpha_settings', 'plugin_alpha_settings', 'plugin_beta_settings', 'plugin_gamma_settings'], array_column($hiddenRows85(), 'option_name'));
    },
    'hidden lateral rowid current source next85 rule atoms come from current host json' => static function (TestRunner $t) use ($hiddenRows85): void {
        $t->same(['seo', 'cache', null, 'forms'], array_column($hiddenRows85(), 'rule_atom'));
    },
    'hidden lateral rowid current source next85 rule keys reset per host root' => static function (TestRunner $t) use ($hiddenRows85): void {
        $t->same([0, 1, null, 0], array_column($hiddenRows85(), 'rule_key'));
    },
    'hidden lateral rowid current source next85 rowid aliases reset per host root' => static function (TestRunner $t) use ($hiddenRows85): void {
        $t->same([1, 2, null, 1], array_column($hiddenRows85(), 'rule_rowid'));
    },
    'hidden lateral rowid current source next85 underscore rowid mirrors rowid' => static function (TestRunner $t) use ($hiddenRows85): void {
        $t->same([1, 2, null, 1], array_column($hiddenRows85(), 'rule__rowid_'));
    },
    'hidden lateral rowid current source next85 oid mirrors rowid' => static function (TestRunner $t) use ($hiddenRows85): void {
        $t->same([1, 2, null, 1], array_column($hiddenRows85(), 'rule_oid'));
    },
    'hidden lateral rowid current source next85 fullkeys are root relative' => static function (TestRunner $t) use ($hiddenRows85): void {
        $t->same(['$.rules[0]', '$.rules[1]', null, '$.rules[0]'], array_column($hiddenRows85(), 'rule_fullkey'));
    },
    'hidden lateral rowid current source next85 null extends missing right key' => static function (TestRunner $t) use ($hiddenRows85): void {
        $t->same(null, $hiddenRows85()[2]['rule_key']);
    },
    'hidden lateral rowid current source next85 null extends missing right rowid' => static function (TestRunner $t) use ($hiddenRows85): void {
        $t->same(null, $hiddenRows85()[2]['rule_rowid']);
    },
    'hidden lateral rowid current source next85 where may filter generated rowid' => static function (TestRunner $t) use ($hosts85): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_name AS option_name, j.atom AS atom
               FROM wp_options AS o
               JOIN json_each AS j
                 ON j.json = o.option_value
                AND j.root = o.scan_root
              WHERE j.rowid = 1
              ORDER BY o.option_id",
            ['wp_options' => $hosts85],
        );
        $t->same([['option_name' => 'plugin_alpha_settings', 'atom' => 'seo'], ['option_name' => 'plugin_gamma_settings', 'atom' => 'forms']], $rows);
    },
    'hidden lateral rowid current source next85 where may filter generated oid' => static function (TestRunner $t) use ($hosts85): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_name AS option_name, j.atom AS atom
               FROM wp_options AS o
               JOIN json_each AS j
                 ON j.json = o.option_value
                AND j.root = o.scan_root
              WHERE j.oid = 2",
            ['wp_options' => $hosts85],
        );
        $t->same([['option_name' => 'plugin_alpha_settings', 'atom' => 'cache']], $rows);
    },
    'hidden lateral rowid current source next85 hidden constraints are omitted from residual on predicate' => static function (TestRunner $t) use ($plan85): void {
        $t->same(true, is_callable($plan85()['joins'][0]['predicate'] ?? null));
    },
    'hidden lateral rowid current source next85 plan records hidden json index' => static function (TestRunner $t) use ($plan85): void {
        $t->same('j', $plan85()['joins'][0]['jsonTableHiddenIndex']['alias']);
    },
    'hidden lateral rowid current source next85 plan records json and root hidden constraints' => static function (TestRunner $t) use ($plan85): void {
        $t->same(['json', 'root'], array_column($plan85()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'column'));
    },
    'hidden lateral rowid current source next85 plan records current-source expressions' => static function (TestRunner $t) use ($plan85): void {
        $t->same(['o.option_value', 'o.scan_root'], array_column($plan85()['joins'][0]['jsonTableHiddenIndex']['constraints'], 'expression'));
    },
    'hidden lateral rowid current source next85 dynamic join exposes nullable json table columns' => static function (TestRunner $t) use ($plan85): void {
        $t->same(['j.key', 'j.value', 'j.type', 'j.atom', 'j.id', 'j.parent', 'j.fullkey', 'j.path', 'j.rowid', 'j._rowid_', 'j.oid'], $plan85()['joins'][0]['rightColumns']);
    },
    'hidden lateral rowid current source next85 tree row count includes meta objects and booleans' => static function (TestRunner $t) use ($treeRows85): void {
        $t->same(6, count($treeRows85()));
    },
    'hidden lateral rowid current source next85 tree option ids repeat per current source' => static function (TestRunner $t) use ($treeRows85): void {
        $t->same([1, 1, 2, 2, 3, 3], array_column($treeRows85(), 'option_id'));
    },
    'hidden lateral rowid current source next85 tree keys include selected meta roots' => static function (TestRunner $t) use ($treeRows85): void {
        $t->same(['meta', 'enabled', 'meta', 'enabled', 'meta', 'enabled'], array_column($treeRows85(), 'node_key'));
    },
    'hidden lateral rowid current source next85 tree types include boolean leaves' => static function (TestRunner $t) use ($treeRows85): void {
        $t->same(['object', 'true', 'object', 'false', 'object', 'true'], array_column($treeRows85(), 'node_type'));
    },
    'hidden lateral rowid current source next85 tree rowids are local to each host' => static function (TestRunner $t) use ($treeRows85): void {
        $t->same([0, 1, 0, 1, 0, 1], array_column($treeRows85(), 'node_rowid'));
    },
    'hidden lateral rowid current source next85 tree underscore rowid mirrors rowid' => static function (TestRunner $t) use ($treeRows85): void {
        $t->same([0, 1, 0, 1, 0, 1], array_column($treeRows85(), 'node__rowid_'));
    },
    'hidden lateral rowid current source next85 tree oid mirrors rowid' => static function (TestRunner $t) use ($treeRows85): void {
        $t->same([0, 1, 0, 1, 0, 1], array_column($treeRows85(), 'node_oid'));
    },
    'hidden lateral rowid current source next85 tree fullkeys are selected from dynamic hidden root' => static function (TestRunner $t) use ($treeRows85): void {
        $t->same(['$.meta', '$.meta.enabled', '$.meta', '$.meta.enabled', '$.meta', '$.meta.enabled'], array_column($treeRows85(), 'node_fullkey'));
    },
    'hidden lateral rowid current source next85 literal hidden root works with current json source' => static function (TestRunner $t) use ($hosts85): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_id AS option_id, t.rowid AS rowid, t.type AS type
               FROM wp_options AS o
               JOIN json_tree AS t ON t.json = o.option_value AND t.root = '$.meta'
              WHERE t.type = 'object'
              ORDER BY o.option_id",
            ['wp_options' => $hosts85],
        );
        $t->same([[1, 0, 'object'], [2, 0, 'object'], [3, 0, 'object']], array_map(static fn (array $row): array => array_values($row), $rows));
    },
    'hidden lateral rowid current source next85 null host json yields no inner rows' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_id AS option_id, j.rowid AS rowid
               FROM wp_options AS o
               JOIN json_each AS j ON j.json = o.option_value AND j.root = '$.rules'",
            ['wp_options' => [['option_id' => 9, 'option_value' => null]]],
        );
        $t->same([], $rows);
    },
    'hidden lateral rowid current source next85 null root yields left null extension' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_id AS option_id, j.rowid AS rowid
               FROM wp_options AS o
               LEFT JOIN json_each AS j ON j.json = o.option_value AND j.root = o.scan_root",
            ['wp_options' => [['option_id' => 9, 'option_value' => '{"rules":["seo"]}', 'scan_root' => null]]],
        );
        $t->same([['option_id' => 9, 'rowid' => null]], $rows);
    },
    'hidden lateral rowid current source next85 unsupported hidden column remains residual' => static function (TestRunner $t) use ($hosts85): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_id AS option_id
               FROM wp_options AS o
               LEFT JOIN json_each AS j ON j.json = o.option_value AND j.atom IS NOT NULL
              WHERE o.option_id = 1",
            ['wp_options' => $hosts85],
        );
        $t->same([['option_id' => 1]], $rows);
    },
    'hidden lateral rowid current source next85 missing json hidden constraint leaves no right rows' => static function (TestRunner $t) use ($hosts85): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_id AS option_id, j.rowid AS rowid
               FROM wp_options AS o
               LEFT JOIN json_each AS j ON j.root = o.scan_root
              WHERE o.option_id = 1",
            ['wp_options' => $hosts85],
        );
        $t->same([['option_id' => 1, 'rowid' => null]], $rows);
    },
];

return $tests;
