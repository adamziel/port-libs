<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeAggregateOrderCursor;

$tests = [];

$rows = [
    ['rowid' => 1, 'name' => 'siteurl', 'bytes' => 14, 'priority' => 30, 'enabled' => 1, 'label' => 'Core'],
    ['rowid' => 2, 'name' => 'blogname', 'bytes' => 9, 'priority' => 20, 'enabled' => '1', 'label' => 'core'],
    ['rowid' => 3, 'name' => '_transient_timeout_feed', 'bytes' => 24, 'priority' => null, 'enabled' => 0, 'label' => 'skip-missing-order'],
    ['rowid' => 4, 'name' => '_transient_feed', 'bytes' => 40, 'priority' => 40, 'enabled' => null, 'label' => 'skip-null-filter'],
    ['rowid' => 5, 'name' => 'rewrite_rules', 'bytes' => 100, 'priority' => 10, 'enabled' => true, 'label' => 'Theme'],
    ['rowid' => 6, 'name' => 'cron', 'bytes' => 3, 'priority' => 20, 'enabled' => false, 'label' => 'skip-false-filter'],
    ['rowid' => 7, 'name' => 'active_plugins', 'bytes' => 75, 'priority' => 30, 'enabled' => 2, 'label' => 'plugin'],
];

$tests['vdbe aggregate filter skips missing value on false row'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor(
        [
            ['sort' => 2, 'include' => 0],
            ['value' => 'kept', 'sort' => 1, 'include' => 1],
        ],
        'value',
        ['sort'],
        'include',
    );

    $t->same(['kept'], $cursor->values());
};

$tests['vdbe aggregate filter skips missing order on false row'] = static function (TestRunner $t): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor(
        [
            ['value' => 'skipped', 'include' => 0],
            ['value' => 'kept', 'sort' => 1, 'include' => 1],
        ],
        'value',
        ['sort'],
        'include',
    );

    $t->same(['kept'], $cursor->values());
};

$tests['vdbe aggregate filter still requires filter column before skipping'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor(
        [['value' => 'skipped', 'sort' => 1]],
        'value',
        ['sort'],
        'include',
    ));
};

$tests['vdbe aggregate filter still validates kept value column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor(
        [['sort' => 1, 'include' => 1]],
        'value',
        ['sort'],
        'include',
    ));
};

$tests['vdbe aggregate filter still validates kept order column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderCursor(
        [['value' => 'kept', 'include' => 1]],
        'value',
        ['sort'],
        'include',
    ));
};

$tests['vdbe aggregate filter current next walks ordered Application option rows'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor($rows, 'name', ['priority', 'name'], 'enabled', ['NUMERIC', 'TEXT'], ['BINARY', 'BINARY'], [true, false], ['LAST', null]);

    $t->same('active_plugins', $cursor->currentValue());
    $t->same(7, $cursor->currentRow()['rowid']);
    $cursor->next();
    $t->same('siteurl', $cursor->currentValue());
    $t->same(1, $cursor->currentRow()['rowid']);
    $cursor->next();
    $t->same('blogname', $cursor->currentValue());
    $t->same(2, $cursor->currentRow()['rowid']);
    $cursor->next();
    $t->same('rewrite_rules', $cursor->currentValue());
    $t->same(5, $cursor->currentRow()['rowid']);
    $cursor->next();
    $t->true($cursor->eof());
};

$tests['vdbe aggregate filter summary reports skipped input rows'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor($rows, 'bytes', ['priority'], 'enabled', ['NUMERIC'], [], [true], ['LAST']);

    $t->same([
        'inputRows' => 7,
        'filteredRows' => 4,
        'orderedRows' => 4,
        'filter' => true,
        'eof' => false,
    ], $cursor->summary());
};

$tests['vdbe aggregate filter remaining rows starts at current row'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor($rows, 'name', ['priority', 'name'], 'enabled', ['NUMERIC', 'TEXT'], ['BINARY', 'BINARY'], [true, false], ['LAST', null]);
    $cursor->next();

    $t->same([1, 2, 5], array_map(static fn (array $row): int => $row['rowid'], $cursor->remainingRows()));
};

$tests['vdbe aggregate filter aggregate functions use ordered filtered values'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor($rows, 'bytes', ['priority', 'name'], 'enabled', ['NUMERIC', 'TEXT'], ['BINARY', 'BINARY'], [true, false], ['LAST', null]);

    $t->same([75, 14, 9, 100], $cursor->values());
    $t->same(4, $cursor->countValue());
    $t->same(198, $cursor->sum());
    $t->same(198.0, $cursor->total());
    $t->same(49.5, $cursor->avg());
    $t->same('75|14|9|100', $cursor->groupConcat('|'));
};

$tests['vdbe aggregate filter empty cursor is eof'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeAggregateOrderCursor($rows, 'bytes', ['priority'], 'enabled', ['NUMERIC'], [], [true], ['LAST']);
    while (!$cursor->eof()) {
        $cursor->next();
    }

    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentValue());
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentRow());
};

$truthCases = [
    'boolean true' => [true, ['yes']],
    'boolean false' => [false, []],
    'integer one' => [1, ['yes']],
    'integer zero' => [0, []],
    'integer negative' => [-1, ['yes']],
    'float half' => [0.5, ['yes']],
    'float zero' => [0.0, []],
    'numeric string one' => ['1', ['yes']],
    'numeric string decimal' => ['0.25', ['yes']],
    'numeric string zero' => ['0', []],
    'numeric string zero decimal' => ['0.0', []],
    'text true is false like SQLite numeric conversion' => ['true', []],
    'empty text false' => ['', []],
    'null false' => [null, []],
];

foreach ($truthCases as $name => [$filter, $expected]) {
    $tests['vdbe aggregate filter SQL truthiness ' . $name] = static function (TestRunner $t) use ($filter, $expected): void {
        $cursor = new SQLiteVdbeAggregateOrderCursor(
            [
                ['value' => 'yes', 'sort' => 1, 'include' => $filter],
                ['sort' => 2, 'include' => 0],
            ],
            'value',
            ['sort'],
            'include',
        );

        $t->same($expected, $cursor->values());
    };
}

$orderCases = [
    'numeric ascending' => [
        [['value' => 'b', 'sort' => 2], ['value' => 'a', 'sort' => 1], ['value' => 'c', 'sort' => 3]],
        ['sort'],
        'value',
        [],
        [],
        [],
        [],
        ['a', 'b', 'c'],
    ],
    'numeric descending' => [
        [['value' => 'b', 'sort' => 2], ['value' => 'a', 'sort' => 1], ['value' => 'c', 'sort' => 3]],
        ['sort'],
        'value',
        [],
        [],
        [true],
        [],
        ['c', 'b', 'a'],
    ],
    'nulls first ascending' => [
        [['value' => 'b', 'sort' => 2], ['value' => 'null', 'sort' => null], ['value' => 'a', 'sort' => 1]],
        ['sort'],
        'value',
        [],
        [],
        [],
        ['FIRST'],
        ['null', 'a', 'b'],
    ],
    'nulls last ascending' => [
        [['value' => 'b', 'sort' => 2], ['value' => 'null', 'sort' => null], ['value' => 'a', 'sort' => 1]],
        ['sort'],
        'value',
        [],
        [],
        [],
        ['LAST'],
        ['a', 'b', 'null'],
    ],
    'nocase text order' => [
        [['value' => 'b', 'sort' => 'Beta'], ['value' => 'a', 'sort' => 'alpha'], ['value' => 'c', 'sort' => 'Cache']],
        ['sort'],
        'value',
        [],
        ['NOCASE'],
        [],
        [],
        ['a', 'b', 'c'],
    ],
    'binary text order' => [
        [['value' => 'b', 'sort' => 'Beta'], ['value' => 'a', 'sort' => 'alpha'], ['value' => 'c', 'sort' => 'Cache']],
        ['sort'],
        'value',
        [],
        ['BINARY'],
        [],
        [],
        ['b', 'c', 'a'],
    ],
    'multi term stable order' => [
        [['value' => 'b', 'bucket' => 1, 'sort' => 2], ['value' => 'a', 'bucket' => 1, 'sort' => 1], ['value' => 'c', 'bucket' => 0, 'sort' => 9]],
        ['bucket', 'sort'],
        'value',
        ['NUMERIC', 'NUMERIC'],
        [],
        [false, false],
        [],
        ['c', 'a', 'b'],
    ],
    'blob values concatenate in sort order' => [
        [['value' => new SQLiteBlobValue('b'), 'sort' => 2], ['value' => new SQLiteBlobValue('a'), 'sort' => 1]],
        ['sort'],
        'value',
        [],
        [],
        [],
        [],
        [new SQLiteBlobValue('a'), new SQLiteBlobValue('b')],
    ],
];

foreach ($orderCases as $name => [$caseRows, $orderColumns, $valueColumn, $affinities, $collations, $descending, $nulls, $expected]) {
    $tests['vdbe aggregate ORDER BY current values ' . $name] = static function (TestRunner $t) use ($caseRows, $orderColumns, $valueColumn, $affinities, $collations, $descending, $nulls, $expected): void {
        $cursor = new SQLiteVdbeAggregateOrderCursor($caseRows, $valueColumn, $orderColumns, null, $affinities, $collations, $descending, $nulls);
        $actual = $cursor->values();
        $normalize = static fn (mixed $value): mixed => $value instanceof SQLiteBlobValue ? $value->bytes : $value;

        $t->same(array_map($normalize, $expected), array_map($normalize, $actual));
    };
}

$aggregateCases = [
    'count skips null value' => [[['value' => null, 'sort' => 1], ['value' => 4, 'sort' => 2], ['value' => 5, 'sort' => 3]], 'countValue', 2],
    'sum null only returns null' => [[['value' => null, 'sort' => 1], ['value' => null, 'sort' => 2]], 'sum', null],
    'total null only returns zero' => [[['value' => null, 'sort' => 1], ['value' => null, 'sort' => 2]], 'total', 0.0],
    'avg null only returns null' => [[['value' => null, 'sort' => 1], ['value' => null, 'sort' => 2]], 'avg', null],
    'group concat skips null' => [[['value' => 'a', 'sort' => 1], ['value' => null, 'sort' => 2], ['value' => 'b', 'sort' => 3]], 'groupConcat', 'a,b'],
];

foreach ($aggregateCases as $name => [$caseRows, $method, $expected]) {
    $tests['vdbe aggregate ORDER BY aggregate ' . $name] = static function (TestRunner $t) use ($caseRows, $method, $expected): void {
        $cursor = new SQLiteVdbeAggregateOrderCursor($caseRows, 'value', ['sort']);

        $t->same($expected, $cursor->{$method}());
    };
}

$invalidCases = [
    'rows must be list' => static fn () => new SQLiteVdbeAggregateOrderCursor([1 => ['value' => 'x', 'sort' => 1]], 'value', ['sort']),
    'value column non-empty' => static fn () => new SQLiteVdbeAggregateOrderCursor([['value' => 'x', 'sort' => 1]], '', ['sort']),
    'order columns non-empty' => static fn () => new SQLiteVdbeAggregateOrderCursor([['value' => 'x', 'sort' => 1]], 'value', []),
    'order column names non-empty' => static fn () => new SQLiteVdbeAggregateOrderCursor([['value' => 'x', 'sort' => 1]], 'value', ['']),
    'kept order value rejects array' => static fn () => new SQLiteVdbeAggregateOrderCursor([['value' => 'x', 'sort' => [], 'include' => 1]], 'value', ['sort'], 'include'),
    'unsupported affinity fails through sorter compare' => static fn () => new SQLiteVdbeAggregateOrderCursor([['value' => 'x', 'sort' => 1]], 'value', ['sort'], null, ['BAD']),
    'unsupported collation fails through sorter compare' => static fn () => new SQLiteVdbeAggregateOrderCursor([['value' => 'x', 'sort' => 'a']], 'value', ['sort'], null, [], ['BAD']),
];

foreach ($invalidCases as $name => $callback) {
    $tests['vdbe aggregate ORDER BY invalid ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

$currentNextCases = [
    'one row' => [[['value' => 'a', 'sort' => 1]], ['a']],
    'two rows' => [[['value' => 'b', 'sort' => 2], ['value' => 'a', 'sort' => 1]], ['a', 'b']],
    'three rows with duplicate sort keeps input sequence' => [[['value' => 'a', 'sort' => 1], ['value' => 'b', 'sort' => 1], ['value' => 'c', 'sort' => 2]], ['a', 'b', 'c']],
    'filtered duplicate sort skips middle' => [[['value' => 'a', 'sort' => 1, 'include' => 1], ['sort' => 1, 'include' => 0], ['value' => 'c', 'sort' => 2, 'include' => 1]], ['a', 'c']],
    'filtered text zero skips malformed payload' => [[['value' => 'a', 'sort' => 1, 'include' => 1], ['sort' => [], 'include' => '0'], ['value' => 'c', 'sort' => 2, 'include' => 1]], ['a', 'c']],
    'filtered text nonnumeric skips malformed payload' => [[['value' => 'a', 'sort' => 1, 'include' => 1], ['sort' => [], 'include' => 'enabled'], ['value' => 'c', 'sort' => 2, 'include' => 1]], ['a', 'c']],
    'filtered null skips missing payload' => [[['value' => 'a', 'sort' => 1, 'include' => 1], ['include' => null], ['value' => 'c', 'sort' => 2, 'include' => 1]], ['a', 'c']],
    'filtered false skips unsupported blob sort payload' => [[['value' => 'a', 'sort' => 1, 'include' => 1], ['sort' => [], 'include' => false], ['value' => 'c', 'sort' => 2, 'include' => 1]], ['a', 'c']],
];

foreach ($currentNextCases as $name => [$caseRows, $expected]) {
    $tests['vdbe aggregate current next ' . $name] = static function (TestRunner $t) use ($caseRows, $expected): void {
        $cursor = new SQLiteVdbeAggregateOrderCursor($caseRows, 'value', ['sort'], array_key_exists('include', $caseRows[0]) ? 'include' : null);
        $actual = [];
        while (!$cursor->eof()) {
            $actual[] = $cursor->currentValue();
            $cursor->next();
        }

        $t->same($expected, $actual);
        $t->true($cursor->eof());
    };
}

return $tests;
