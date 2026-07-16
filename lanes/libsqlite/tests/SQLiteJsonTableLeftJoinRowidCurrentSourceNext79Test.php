<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$settings79 = '{"plugins":{"seo":{"rules":["title","meta"]},"cache":{"rules":[]},"forms":{"rules":["contact"]}}}';

$sourceRows79 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT p.key AS plugin_key,
            p.rowid AS plugin_rowid,
            p._rowid_ AS plugin__rowid_,
            p.oid AS plugin_oid,
            r.key AS rule_key,
            r.rowid AS rule_rowid,
            r._rowid_ AS rule__rowid_,
            r.oid AS rule_oid,
            r.atom AS rule_name
       FROM json_each(:settings, '$.plugins') AS p
       LEFT JOIN json_each(p.value, '$.rules') AS r ON r.atom LIKE '%t%'
      ORDER BY plugin_key, rule_rowid",
    [],
    [':settings' => $settings79],
);

$treeRows79 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT node.key AS node_key,
            node.rowid AS node_rowid,
            node._rowid_ AS node__rowid_,
            node.oid AS node_oid,
            names.rowid AS name_rowid,
            names.atom AS name_atom
       FROM json_tree(:settings, '$.plugins') AS node
       LEFT JOIN json_each('[\"plugins\",\"seo\",\"cache\",\"forms\"]') AS names ON names.atom = node.key
      WHERE node.type = 'object'
      ORDER BY node.fullkey",
    [],
    [':settings' => $settings79],
);

$tests = [
    'sqlite json table left join rowid current source next79 current source row count' => static function (TestRunner $t) use ($sourceRows79): void {
        $t->same(4, count($sourceRows79()));
    },
    'sqlite json table left join rowid current source next79 current source key order' => static function (TestRunner $t) use ($sourceRows79): void {
        $t->same(['cache', 'forms', 'seo', 'seo'], array_column($sourceRows79(), 'plugin_key'));
    },
    'sqlite json table left join rowid current source next79 exposes base rowid aliases' => static function (TestRunner $t) use ($sourceRows79): void {
        $t->same([2, 3, 1, 1], array_column($sourceRows79(), 'plugin_rowid'));
    },
    'sqlite json table left join rowid current source next79 exposes base underscore rowid aliases' => static function (TestRunner $t) use ($sourceRows79): void {
        $t->same([2, 3, 1, 1], array_column($sourceRows79(), 'plugin__rowid_'));
    },
    'sqlite json table left join rowid current source next79 exposes base oid aliases' => static function (TestRunner $t) use ($sourceRows79): void {
        $t->same([2, 3, 1, 1], array_column($sourceRows79(), 'plugin_oid'));
    },
    'sqlite json table left join rowid current source next79 null extends missing right rowid' => static function (TestRunner $t) use ($sourceRows79): void {
        $t->same(null, $sourceRows79()[0]['rule_rowid']);
    },
    'sqlite json table left join rowid current source next79 null extends missing right underscore rowid' => static function (TestRunner $t) use ($sourceRows79): void {
        $t->same(null, $sourceRows79()[0]['rule__rowid_']);
    },
    'sqlite json table left join rowid current source next79 null extends missing right oid' => static function (TestRunner $t) use ($sourceRows79): void {
        $t->same(null, $sourceRows79()[0]['rule_oid']);
    },
    'sqlite json table left join rowid current source next79 keeps matched right rowid' => static function (TestRunner $t) use ($sourceRows79): void {
        $t->same(1, $sourceRows79()[1]['rule_rowid']);
    },
    'sqlite json table left join rowid current source next79 keeps matched right oid' => static function (TestRunner $t) use ($sourceRows79): void {
        $t->same(1, $sourceRows79()[1]['rule_oid']);
    },
    'sqlite json table left join rowid current source next79 filters right rows without dropping source' => static function (TestRunner $t) use ($sourceRows79): void {
        $t->same(['contact', null, 'title'], [$sourceRows79()[1]['rule_name'], $sourceRows79()[0]['rule_name'], $sourceRows79()[2]['rule_name']]);
    },
    'sqlite json table left join rowid current source next79 supports base rowid in where' => static function (TestRunner $t) use ($settings79): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT p.key AS plugin_key FROM json_each(:settings, '$.plugins') AS p LEFT JOIN json_each(p.value, '$.rules') AS r ON r.atom IS NOT NULL WHERE p.rowid = 2",
            [],
            [':settings' => $settings79],
        );
        $t->same([['plugin_key' => 'cache']], $rows);
    },
    'sqlite json table left join rowid current source next79 supports base underscore rowid in where' => static function (TestRunner $t) use ($settings79): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT p.key AS plugin_key FROM json_each(:settings, '$.plugins') AS p LEFT JOIN json_each(p.value, '$.rules') AS r ON r.atom IS NOT NULL WHERE p._rowid_ = 3",
            [],
            [':settings' => $settings79],
        );
        $t->same([['plugin_key' => 'forms']], $rows);
    },
    'sqlite json table left join rowid current source next79 supports base oid in where' => static function (TestRunner $t) use ($settings79): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT p.key AS plugin_key FROM json_each(:settings, '$.plugins') AS p LEFT JOIN json_each(p.value, '$.rules') AS r ON r.atom IS NOT NULL WHERE p.oid = 1 ORDER BY r.rowid",
            [],
            [':settings' => $settings79],
        );
        $t->same([['plugin_key' => 'seo'], ['plugin_key' => 'seo']], $rows);
    },
    'sqlite json table left join rowid current source next79 supports no-join base rowid projection' => static function (TestRunner $t) use ($settings79): void {
        $rows = SQLiteSelectSql::execute("SELECT rowid AS rid, _rowid_ AS urid, oid AS oid, key AS plugin_key FROM json_each(:settings, '$.plugins') ORDER BY key", [], [':settings' => $settings79]);
        $t->same([[2, 2, 2, 'cache'], [3, 3, 3, 'forms'], [1, 1, 1, 'seo']], array_map(static fn (array $row): array => array_values($row), $rows));
    },
    'sqlite json table left join rowid current source next79 tree source row count' => static function (TestRunner $t) use ($treeRows79): void {
        $t->same(4, count($treeRows79()));
    },
    'sqlite json table left join rowid current source next79 tree exposes source rowids' => static function (TestRunner $t) use ($treeRows79): void {
        $t->same([0, 5, 7, 1], array_column($treeRows79(), 'node_rowid'));
    },
    'sqlite json table left join rowid current source next79 tree exposes source underscore rowids' => static function (TestRunner $t) use ($treeRows79): void {
        $t->same([0, 5, 7, 1], array_column($treeRows79(), 'node__rowid_'));
    },
    'sqlite json table left join rowid current source next79 tree exposes source oid' => static function (TestRunner $t) use ($treeRows79): void {
        $t->same([0, 5, 7, 1], array_column($treeRows79(), 'node_oid'));
    },
    'sqlite json table left join rowid current source next79 tree null extends missing child rowids' => static function (TestRunner $t) use ($treeRows79): void {
        $t->same([1, 3, 4, 2], array_column($treeRows79(), 'name_rowid'));
    },
    'sqlite json table left join rowid current source next79 tree joins current source names' => static function (TestRunner $t) use ($treeRows79): void {
        $t->same(['plugins', 'cache', 'forms', 'seo'], array_column($treeRows79(), 'name_atom'));
    },
    'sqlite json table left join rowid current source next79 plan qualifies json current source rowids' => static function (TestRunner $t) use ($settings79): void {
        $plan = SQLiteSelectSql::plan(
            "SELECT p.rowid, r.rowid FROM json_each(:settings, '$.plugins') AS p LEFT JOIN json_each(p.value, '$.rules') AS r ON r.atom IS NOT NULL",
            [],
            [':settings' => $settings79],
        );
        $t->same(true, array_key_exists('p.rowid', $plan['from'][0]));
    },
    'sqlite json table left join rowid current source next79 plan qualifies json current source oid' => static function (TestRunner $t) use ($settings79): void {
        $plan = SQLiteSelectSql::plan(
            "SELECT p.oid, r.oid FROM json_each(:settings, '$.plugins') AS p LEFT JOIN json_each(p.value, '$.rules') AS r ON r.atom IS NOT NULL",
            [],
            [':settings' => $settings79],
        );
        $t->same(true, array_key_exists('p.oid', $plan['from'][0]));
    },
    'sqlite json table left join rowid current source next79 plan keeps right nullable aliases' => static function (TestRunner $t) use ($settings79): void {
        $plan = SQLiteSelectSql::plan(
            "SELECT p.rowid, r.rowid FROM json_each(:settings, '$.plugins') AS p LEFT JOIN json_each(p.value, '$.rules') AS r ON r.atom IS NOT NULL",
            [],
            [':settings' => $settings79],
        );
        $t->same(['r.key', 'r.value', 'r.type', 'r.atom', 'r.id', 'r.parent', 'r.fullkey', 'r.path', 'r.rowid', 'r._rowid_', 'r.oid'], $plan['joins'][0]['rightColumns']);
    },
];

return $tests;
