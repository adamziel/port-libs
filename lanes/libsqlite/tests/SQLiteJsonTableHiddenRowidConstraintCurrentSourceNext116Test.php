<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$json116 = '{"plugins":[{"slug":"cache","active":true},{"slug":"seo","active":false},{"slug":"forms","active":true}],"meta":{"site":1,"network":2}}';

$eachRowid116 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT key AS key,
            atom AS atom,
            rowid AS rowid,
            _rowid_ AS underscore_rowid,
            oid AS oid,
            fullkey AS fullkey
       FROM json_each('[\"cache\",\"seo\",\"forms\"]') AS j
      WHERE rowid = 2",
    [],
);

$eachCommuted116 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT atom AS atom, rowid AS rowid
       FROM json_each('[\"cache\",\"seo\",\"forms\"]') AS j
      WHERE 3 = rowid",
    [],
);

$eachUnderscore116 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT atom AS atom, _rowid_ AS rowid
       FROM json_each('[\"cache\",\"seo\",\"forms\"]') AS j
      WHERE _rowid_ = 1",
    [],
);

$eachOid116 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT atom AS atom, oid AS rowid
       FROM json_each('[\"cache\",\"seo\",\"forms\"]') AS j
      WHERE oid = 99",
    [],
);

$treeRowid116 = static function () use ($json116): array {
    return SQLiteSelectSql::execute(
    "SELECT key AS key,
            atom AS atom,
            rowid AS rowid,
            fullkey AS fullkey,
            path AS path
       FROM json_tree('{$json116}', '$.plugins') AS t
      WHERE rowid = 5",
    [],
    );
};

$treeOid116 = static function () use ($json116): array {
    return SQLiteSelectSql::execute(
    "SELECT key AS key, atom AS atom, oid AS oid, fullkey AS fullkey
       FROM json_tree('{$json116}', '$.meta') AS t
      WHERE oid = 2",
    [],
    );
};

$residual116 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT atom AS atom, rowid AS rowid
       FROM json_each('[\"cache\",\"seo\",\"forms\"]') AS j
      WHERE rowid = 2 AND atom = 'seo'",
    [],
);

$residualMiss116 = static fn (): array => SQLiteSelectSql::execute(
    "SELECT atom AS atom, rowid AS rowid
       FROM json_each('[\"cache\",\"seo\",\"forms\"]') AS j
      WHERE rowid = 2 AND atom = 'cache'",
    [],
);

$plan116 = static fn (): array => SQLiteSelectSql::plan(
    "SELECT atom, rowid
       FROM json_each('[\"cache\",\"seo\",\"forms\"]') AS j
      WHERE rowid = 2",
    [],
);

$tests = [
    'json table hidden rowid constraint current source next116 filters bare json_each to one row' => static fn (TestRunner $t) => $t->same(1, count($eachRowid116())),
    'json table hidden rowid constraint current source next116 returns selected atom' => static fn (TestRunner $t) => $t->same('seo', $eachRowid116()[0]['atom']),
    'json table hidden rowid constraint current source next116 returns selected key' => static fn (TestRunner $t) => $t->same(1, $eachRowid116()[0]['key']),
    'json table hidden rowid constraint current source next116 preserves rowid' => static fn (TestRunner $t) => $t->same(2, $eachRowid116()[0]['rowid']),
    'json table hidden rowid constraint current source next116 preserves underscore rowid' => static fn (TestRunner $t) => $t->same(2, $eachRowid116()[0]['underscore_rowid']),
    'json table hidden rowid constraint current source next116 preserves oid' => static fn (TestRunner $t) => $t->same(2, $eachRowid116()[0]['oid']),
    'json table hidden rowid constraint current source next116 keeps fullkey' => static fn (TestRunner $t) => $t->same('$[1]', $eachRowid116()[0]['fullkey']),
    'json table hidden rowid constraint current source next116 commuted rowid filters' => static fn (TestRunner $t) => $t->same([['atom' => 'forms', 'rowid' => 3]], $eachCommuted116()),
    'json table hidden rowid constraint current source next116 underscore alias filters' => static fn (TestRunner $t) => $t->same([['atom' => 'cache', 'rowid' => 1]], $eachUnderscore116()),
    'json table hidden rowid constraint current source next116 oid alias miss returns empty' => static fn (TestRunner $t) => $t->same([], $eachOid116()),
    'json table hidden rowid constraint current source next116 json_tree filters to one row' => static fn (TestRunner $t) => $t->same(1, count($treeRowid116())),
    'json table hidden rowid constraint current source next116 json_tree returns slug key' => static fn (TestRunner $t) => $t->same('slug', $treeRowid116()[0]['key']),
    'json table hidden rowid constraint current source next116 json_tree returns selected atom' => static fn (TestRunner $t) => $t->same('seo', $treeRowid116()[0]['atom']),
    'json table hidden rowid constraint current source next116 json_tree preserves id as rowid' => static fn (TestRunner $t) => $t->same(5, $treeRowid116()[0]['rowid']),
    'json table hidden rowid constraint current source next116 json_tree fullkey is root scoped' => static fn (TestRunner $t) => $t->same('$.plugins[1].slug', $treeRowid116()[0]['fullkey']),
    'json table hidden rowid constraint current source next116 json_tree path is parent object' => static fn (TestRunner $t) => $t->same('$.plugins[1]', $treeRowid116()[0]['path']),
    'json table hidden rowid constraint current source next116 json_tree oid filters rooted object' => static fn (TestRunner $t) => $t->same([['key' => 'network', 'atom' => 2, 'oid' => 2, 'fullkey' => '$.meta.network']], $treeOid116()),
    'json table hidden rowid constraint current source next116 residual match keeps row' => static fn (TestRunner $t) => $t->same([['atom' => 'seo', 'rowid' => 2]], $residual116()),
    'json table hidden rowid constraint current source next116 residual miss drops row' => static fn (TestRunner $t) => $t->same([], $residualMiss116()),
    'json table hidden rowid constraint current source next116 plan omits rowid where' => static fn (TestRunner $t) => $t->same(false, isset($plan116()['where'])),
    'json table hidden rowid constraint current source next116 plan from contains one current row' => static fn (TestRunner $t) => $t->same(1, count($plan116()['from'])),
    'json table hidden rowid constraint current source next116 plan current row atom' => static fn (TestRunner $t) => $t->same('seo', $plan116()['from'][0]['atom']),
    'json table hidden rowid constraint current source next116 plan current rowid alias' => static fn (TestRunner $t) => $t->same(2, $plan116()['from'][0]['rowid']),
    'json table hidden rowid constraint current source next116 qualified alias does not leak to current row' => static fn (TestRunner $t) => $t->same(false, array_key_exists('j.rowid', $plan116()['from'][0])),
    'json table hidden rowid constraint current source next116 unaliased source rowid filters' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT atom, rowid FROM json_each('[\"cache\",\"seo\",\"forms\"]') WHERE rowid = 3",
            [],
        );
        $t->same([['atom' => 'forms', 'rowid' => 3]], $rows);
    },
    'json table hidden rowid constraint current source next116 alias without as filters' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT atom AS atom, rowid AS rowid FROM json_each('[\"cache\",\"seo\",\"forms\"]') e WHERE rowid = 1",
            [],
        );
        $t->same([['atom' => 'cache', 'rowid' => 1]], $rows);
    },
    'json table hidden rowid constraint current source next116 root constraint composes with rowid' => static function (TestRunner $t) use ($json116): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT key AS key, atom AS atom, rowid AS rowid
               FROM json_tree('{$json116}', '$.plugins[0]') AS t
              WHERE rowid = 2",
            [],
        );
        $t->same([['key' => 'active', 'atom' => 1, 'rowid' => 2]], $rows);
    },
    'json table hidden rowid constraint current source next116 null rowid literal returns empty' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT atom AS atom FROM json_each('[\"cache\",\"seo\",\"forms\"]') AS j WHERE rowid = NULL",
            [],
        );
        $t->same([], $rows);
    },
    'json table hidden rowid constraint current source next116 keeps visible id residual' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT atom AS atom, id AS id FROM json_each('[\"cache\",\"seo\",\"forms\"]') AS j WHERE id = 2",
            [],
        );
        $t->same([['atom' => 'seo', 'id' => 2]], $rows);
    },
    'json table hidden rowid constraint current source next116 where with function args no longer raises missing qualified column' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT atom AS atom FROM json_each('[\"cache\",\"seo\",\"forms\"]') AS j WHERE rowid = 1",
            [],
        );
        $t->same([['atom' => 'cache']], $rows);
    },
];

return $tests;
