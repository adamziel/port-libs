<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexedByPlan;

$baseIndexes = [
    ['name' => 'i1', 'table' => 'items', 'columns' => ['a'], 'covering' => true],
    ['name' => 'i2', 'table' => 'items', 'columns' => ['b']],
    ['name' => 'i3', 'table' => 'events', 'columns' => ['c']],
    ['name' => 'i4', 'table' => 'events', 'columns' => ['d']],
    ['name' => 'sqlite_autoindex_unique_items_1', 'table' => 'unique_items', 'columns' => ['e'], 'unique' => true, 'auto' => true],
    ['name' => 'p1', 'table' => 'orders', 'columns' => ['z']],
    ['name' => 'p2', 'table' => 'orders', 'columns' => ['y'], 'partial' => ['column' => 'z', 'operator' => '=', 'value' => 1]],
];

$term = static fn (string $column, mixed $value, string $operator = '='): array => ['column' => $column, 'operator' => $operator, 'value' => $value];

$queries = [
    'indexedby-2.1 not indexed select scans table' => [
        'statement' => 'SELECT', 'table' => 'items', 'indexes' => $baseIndexes, 'notIndexed' => true,
        'where' => [$term('a', 'one'), $term('b', 'two')],
        'expect' => ['ok' => true, 'operation' => 'SCAN', 'accessPath' => 'table-scan', 'indexName' => null, 'forced' => false, 'notIndexed' => true],
    ],
    'indexedby-2.2 forced i1 search' => [
        'statement' => 'SELECT', 'table' => 'items', 'indexes' => $baseIndexes, 'indexedBy' => 'i1',
        'where' => [$term('a', 'one'), $term('b', 'two')], 'projectedColumns' => ['a', 'rowid'],
        'expect' => ['ok' => true, 'operation' => 'SEARCH', 'accessPath' => 'covering-index', 'indexName' => 'i1', 'forced' => true, 'matchedColumns' => ['a'], 'covering' => true],
    ],
    'indexedby-2.3 forced i2 search' => [
        'statement' => 'SELECT', 'table' => 'items', 'indexes' => $baseIndexes, 'indexedBy' => 'i2',
        'where' => [$term('a', 'one'), $term('b', 'two')],
        'expect' => ['ok' => true, 'operation' => 'SEARCH', 'accessPath' => 'index', 'indexName' => 'i2', 'forced' => true, 'matchedColumns' => ['b']],
    ],
    'indexedby-2.4 wrong table index is no such index' => [
        'statement' => 'SELECT', 'table' => 'items', 'indexes' => $baseIndexes, 'indexedBy' => 'i3',
        'where' => [$term('a', 'one'), $term('b', 'two')],
        'expect' => ['ok' => false, 'reason' => 'missing-index', 'error' => 'no such index: i3'],
    ],
    'indexedby-2.5 missing index is no such index' => [
        'statement' => 'SELECT', 'table' => 'items', 'indexes' => $baseIndexes, 'indexedBy' => 'i5',
        'where' => [$term('a', 'one')],
        'expect' => ['ok' => false, 'reason' => 'missing-index', 'error' => 'no such index: i5'],
    ],
    'indexedby-2.7 view indexed by fails before table planning' => [
        'statement' => 'SELECT', 'table' => 'active_items_view', 'indexes' => $baseIndexes, 'indexedBy' => 'i1',
        'where' => [$term('a', 'one')], 'isView' => true,
        'expect' => ['ok' => false, 'reason' => 'view-indexed-by', 'error' => 'no such index: i1'],
    ],
    'indexedby-3.1 unforced select chooses index' => [
        'statement' => 'SELECT', 'table' => 'items', 'indexes' => $baseIndexes,
        'where' => [$term('a', 'one'), $term('b', 'two')],
        'expect' => ['ok' => true, 'operation' => 'SEARCH', 'indexName' => 'i1', 'forced' => false, 'matchedColumns' => ['a']],
    ],
    'indexedby-3.1.1 not indexed select with terms scans' => [
        'statement' => 'SELECT', 'table' => 'items', 'indexes' => $baseIndexes, 'notIndexed' => true,
        'where' => [$term('a', 'one'), $term('b', 'two')],
        'expect' => ['ok' => true, 'detail' => 'SCAN items', 'indexName' => null],
    ],
    'indexedby-3.1.2 not indexed rowid still searches integer primary key' => [
        'statement' => 'SELECT', 'table' => 'items', 'indexes' => $baseIndexes, 'notIndexed' => true,
        'where' => [$term('rowid', 1)],
        'expect' => ['ok' => true, 'accessPath' => 'integer-primary-key', 'detail' => 'SEARCH items USING INTEGER PRIMARY KEY (rowid=?)'],
    ],
    'indexedby-3.8 forced unique autoindex scan for order' => [
        'statement' => 'SELECT', 'table' => 'unique_items', 'indexes' => $baseIndexes, 'indexedBy' => 'sqlite_autoindex_unique_items_1',
        'where' => [], 'orderBy' => ['e'],
        'expect' => ['ok' => true, 'operation' => 'SCAN', 'indexName' => 'sqlite_autoindex_unique_items_1', 'autoIndex' => true],
    ],
    'indexedby-3.9 forced unique autoindex search' => [
        'statement' => 'SELECT', 'table' => 'unique_items', 'indexes' => $baseIndexes, 'indexedBy' => 'sqlite_autoindex_unique_items_1',
        'where' => [$term('e', 10)],
        'expect' => ['ok' => true, 'operation' => 'SEARCH', 'indexName' => 'sqlite_autoindex_unique_items_1', 'matchedColumns' => ['e'], 'autoIndex' => true],
    ],
    'indexedby-3.11 missing second autoindex fails' => [
        'statement' => 'SELECT', 'table' => 'unique_items', 'indexes' => $baseIndexes, 'indexedBy' => 'sqlite_autoindex_unique_items_2',
        'where' => [$term('f', 10)],
        'expect' => ['ok' => false, 'error' => 'no such index: sqlite_autoindex_unique_items_2'],
    ],
    'indexedby-7.1 unforced delete chooses i1' => [
        'statement' => 'DELETE', 'table' => 'items', 'indexes' => $baseIndexes, 'where' => [$term('a', 5)],
        'expect' => ['ok' => true, 'statement' => 'DELETE', 'operation' => 'SEARCH', 'indexName' => 'i1', 'forced' => false],
    ],
    'indexedby-7.2 not indexed delete scans' => [
        'statement' => 'DELETE', 'table' => 'items', 'indexes' => $baseIndexes, 'notIndexed' => true, 'where' => [$term('a', 5)],
        'expect' => ['ok' => true, 'statement' => 'DELETE', 'operation' => 'SCAN', 'indexName' => null],
    ],
    'indexedby-7.3 forced delete i1' => [
        'statement' => 'DELETE', 'table' => 'items', 'indexes' => $baseIndexes, 'indexedBy' => 'i1', 'where' => [$term('a', 5)],
        'expect' => ['ok' => true, 'statement' => 'DELETE', 'indexName' => 'i1', 'matchedColumns' => ['a'], 'forced' => true],
    ],
    'indexedby-7.5 forced delete i2 with two terms' => [
        'statement' => 'DELETE', 'table' => 'items', 'indexes' => $baseIndexes, 'indexedBy' => 'i2', 'where' => [$term('a', 5), $term('b', 10)],
        'expect' => ['ok' => true, 'statement' => 'DELETE', 'indexName' => 'i2', 'matchedColumns' => ['b'], 'forced' => true],
    ],
    'indexedby-8.1 unforced update chooses covering i1' => [
        'statement' => 'UPDATE', 'table' => 'items', 'indexes' => $baseIndexes, 'where' => [$term('a', 5)], 'projectedColumns' => ['a'],
        'expect' => ['ok' => true, 'statement' => 'UPDATE', 'accessPath' => 'covering-index', 'indexName' => 'i1', 'covering' => true],
    ],
    'indexedby-8.2 not indexed update scans' => [
        'statement' => 'UPDATE', 'table' => 'items', 'indexes' => $baseIndexes, 'notIndexed' => true, 'where' => [$term('a', 5)],
        'expect' => ['ok' => true, 'statement' => 'UPDATE', 'operation' => 'SCAN', 'indexName' => null],
    ],
    'indexedby-8.5 forced update i2 with two terms' => [
        'statement' => 'UPDATE', 'table' => 'items', 'indexes' => $baseIndexes, 'indexedBy' => 'i2', 'where' => [$term('a', 5), $term('b', 10)],
        'expect' => ['ok' => true, 'statement' => 'UPDATE', 'operation' => 'SEARCH', 'indexName' => 'i2', 'matchedColumns' => ['b']],
    ],
    'indexedby-12.2 forced unusable partial index fails' => [
        'statement' => 'SELECT', 'table' => 'orders', 'indexes' => $baseIndexes, 'indexedBy' => 'p2', 'where' => [],
        'expect' => ['ok' => false, 'reason' => 'partial-index-not-implied', 'error' => 'no query solution'],
    ],
    'indexedby-12.4 partial index still fails after recreate order changes' => [
        'statement' => 'SELECT', 'table' => 'orders',
        'indexes' => [
            ['name' => 'p2', 'table' => 'orders', 'columns' => ['y'], 'partial' => ['column' => 'z', 'operator' => '=', 'value' => 1]],
            ['name' => 'p1', 'table' => 'orders', 'columns' => ['z']],
        ],
        'indexedBy' => 'p2', 'where' => [],
        'expect' => ['ok' => false, 'reason' => 'partial-index-not-implied', 'error' => 'no query solution'],
    ],
    'indexedby-12 forced partial index is usable when predicate is implied' => [
        'statement' => 'SELECT', 'table' => 'orders', 'indexes' => $baseIndexes, 'indexedBy' => 'p2',
        'where' => [$term('z', 1), $term('y', 'open')],
        'expect' => ['ok' => true, 'operation' => 'SEARCH', 'indexName' => 'p2', 'partial' => true, 'matchedColumns' => ['y']],
    ],
];

$tests = [];

foreach ($queries as $name => $query) {
    $tests["real upstream indexedby dynamic btree {$name}"] = static function (TestRunner $t) use ($query): void {
        $plan = SQLiteIndexedByPlan::plan(
            $query['statement'],
            $query['table'],
            $query['indexes'],
            $query['where'] ?? [],
            $query['indexedBy'] ?? null,
            $query['notIndexed'] ?? false,
            $query['orderBy'] ?? [],
            $query['projectedColumns'] ?? ['*'],
            $query['isView'] ?? false,
        );

        foreach ($query['expect'] as $key => $expected) {
            $t->same($expected, $plan[$key] ?? null, $key);
        }
        $t->same('indexedby.test', $plan['upstream']);
        $t->same($query['statement'], $plan['statement']);
        $t->same($query['table'], $plan['table']);
    };
}

$dynamicCases = [];
foreach (['SELECT', 'DELETE', 'UPDATE'] as $statement) {
    foreach (['a' => 'i1', 'b' => 'i2'] as $column => $indexName) {
        for ($i = 1; $i <= 48; $i++) {
            $dynamicCases["{$statement} forced {$indexName} equality {$i}"] = [
                'statement' => $statement,
                'table' => 'items',
                'indexes' => $baseIndexes,
                'indexedBy' => $indexName,
                'where' => [$term($column, $i), $term($column === 'a' ? 'b' : 'a', $i + 100)],
                'column' => $column,
                'indexName' => $indexName,
            ];
        }
    }
}

foreach ($dynamicCases as $name => $case) {
    $tests["real upstream indexedby dynamic btree {$name}"] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteIndexedByPlan::plan($case['statement'], $case['table'], $case['indexes'], $case['where'], $case['indexedBy']);

        $t->same(true, $plan['ok']);
        $t->same($case['statement'], $plan['statement']);
        $t->same('SEARCH', $plan['operation']);
        $t->same($case['indexName'], $plan['indexName']);
        $t->same([$case['column']], $plan['matchedColumns']);
        $t->same(true, $plan['forced']);
        $t->same('indexedby.test', $plan['upstream']);
    };
}

$tests['real upstream indexedby dynamic btree batch preserves query order and errors'] = static function (TestRunner $t) use ($queries): void {
    $plans = SQLiteIndexedByPlan::batch(array_values($queries));
    $t->same(count($queries), count($plans));
    $t->same(true, $plans[0]['ok']);
    $t->same(false, $plans[3]['ok']);
    $t->same('no such index: i3', $plans[3]['error']);
    $t->same('no query solution', $plans[19]['error']);
};

$tests['real upstream indexedby dynamic btree rejects unsupported statement type'] = static function (TestRunner $t) use ($baseIndexes): void {
    $t->throws(InvalidArgumentException::class, fn () => SQLiteIndexedByPlan::plan('INSERT', 'items', $baseIndexes));
};

$tests['real upstream indexedby dynamic btree rejects conflicting index directives'] = static function (TestRunner $t) use ($baseIndexes): void {
    $t->throws(InvalidArgumentException::class, fn () => SQLiteIndexedByPlan::plan('SELECT', 'items', $baseIndexes, [], 'i1', true));
};

$tests['real upstream indexedby dynamic btree rejects empty table name'] = static function (TestRunner $t) use ($baseIndexes): void {
    $t->throws(InvalidArgumentException::class, fn () => SQLiteIndexedByPlan::plan('SELECT', '', $baseIndexes));
};

return $tests;
