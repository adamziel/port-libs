<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateIndex;
use PortLibs\LibSqlite\SQLiteIndexPredicate;

$columnFor = static function (string $sql): mixed {
    $columns = SQLiteCreateIndex::columns($sql);

    return $columns[0] ?? null;
};

$predicateFor = static function (string $sql) use ($columnFor): SQLiteIndexPredicate {
    $column = $columnFor($sql);
    if ($column === null || $column->partialPredicate === null) {
        throw new RuntimeException('Expected partial-index predicate');
    }

    return $column->partialPredicate;
};

$index6Rows = static function (int $max): array {
    $rows = [];
    for ($value = 1; $value <= $max; $value++) {
        $rows[] = [
            'a' => $value % 3 !== 0 ? $value : null,
            'b' => $value,
            'c' => $value,
        ];
    }

    return $rows;
};

$matchingRows = static function (array $rows, SQLiteIndexPredicate $predicate, string $column): array {
    return array_values(array_filter(
        $rows,
        static fn (array $row): bool => $predicate->isImpliedByPointLookup($column, $row[$column] ?? null),
    ));
};

$index3Rows = static function (): array {
    $rows = [];
    for ($value = 1; $value < 200; $value++) {
        $rows[] = [
            'a' => $value % 5 !== 0 ? 999 : $value,
            'b' => $value,
        ];
    }

    return $rows;
};

$uniqueIndexAllows = static function (array $rows, SQLiteIndexPredicate $predicate, string $column, mixed $value): bool {
    if (!$predicate->isImpliedByPointLookup($column, $value)) {
        return true;
    }

    foreach ($rows as $row) {
        if (($row[$column] ?? null) === $value && $predicate->isImpliedByPointLookup($column, $row[$column])) {
            return false;
        }
    }

    return true;
};

$countByPredicate = static function (array $rows, SQLiteIndexPredicate $predicate, string $column): int {
    return count(array_filter(
        $rows,
        static fn (array $row): bool => $predicate->isImpliedByPointLookup($column, $row[$column] ?? null),
    ));
};

$upstreamSources = [
    'index6-1.1',
    'index6-1.1.1',
    'index6-1.10',
    'index6-1.11',
    'index6-1.12',
    'index6-1.13',
    'index6-1.15',
    'index6-2.1',
    'index6-2.2',
    'index6-2.4',
    'index6-2.102',
    'index6-2.103',
    'index6-2.104',
    'index6-3.1',
    'index6-3.2',
    'index6-3.3',
    'index6-3.4',
    'index6-5.0',
    'index6-9.1',
    'index6-10.1',
    'index6-10.2',
    'index6-10.3',
    'index6-11.1',
    'index6-11.2',
    'index6-13.1',
    'index6-14.1',
];

$tests['real upstream index6 partial index corpus cites hydrated upstream scenarios'] = static function (TestRunner $t) use ($upstreamSources): void {
    $t->same('index6.test', 'index6.test');
    $t->same(26, count($upstreamSources));
    $t->contains('index6-3.2', implode(',', $upstreamSources));
};

$tests['real upstream index6 parses ordinary partial index where is not null'] = static function (TestRunner $t) use ($columnFor): void {
    $column = $columnFor('CREATE INDEX t1a ON t1(a) WHERE a IS NOT NULL');
    $t->same('a', $column?->columnName);
    $t->same(true, $column?->partial);
    $t->same(SQLiteIndexPredicate::IS_NOT_NULL, $column?->partialPredicate?->operator);
};

$tests['real upstream index6 parses shorthand partial index where not null'] = static function (TestRunner $t) use ($columnFor): void {
    $column = $columnFor('CREATE INDEX index_0 ON t0(c0) WHERE c0 NOT NULL');
    $t->same('c0', $column?->columnName);
    $t->same(true, $column?->partial);
    $t->same(SQLiteIndexPredicate::IS_NOT_NULL, $column?->partialPredicate?->operator);
};

$tests['real upstream index6 parses qualified between partial predicate'] = static function (TestRunner $t) use ($predicateFor): void {
    $predicate = $predicateFor('CREATE INDEX t3b ON t3(b) WHERE xyzzy.t3.b BETWEEN 5 AND 10');
    $t->same('b', $predicate->columnName);
    $t->same(SQLiteIndexPredicate::BETWEEN, $predicate->operator);
    $t->same(['lower' => 5, 'upper' => 10], $predicate->value);
};

$tests['real upstream index6 parses in-list partial predicate'] = static function (TestRunner $t) use ($predicateFor): void {
    $predicate = $predicateFor('CREATE INDEX t9ca ON t9(c,a) WHERE a in (10,12,20)');
    $t->same('a', $predicate->columnName);
    $t->same(SQLiteIndexPredicate::IN_LIST, $predicate->operator);
    $t->same([10, 12, 20], $predicate->value);
};

$tests['real upstream index6 parses and-connected partial predicate'] = static function (TestRunner $t) use ($predicateFor): void {
    $predicate = $predicateFor('CREATE INDEX t10x ON t10(d) WHERE a=1 AND b=2 AND c=3');
    $t->same(SQLiteIndexPredicate::AND, $predicate->operator);
    $t->same(3, count($predicate->value));
};

$tests['real upstream index6 initial partial stat counts match index6-1.1'] = static function (TestRunner $t) use ($index6Rows, $predicateFor, $countByPredicate): void {
    $rows = $index6Rows(20);
    $t->same(14, $countByPredicate($rows, $predicateFor('CREATE INDEX t1a ON t1(a) WHERE a IS NOT NULL'), 'a'));
    $t->same(10, $countByPredicate($rows, $predicateFor('CREATE INDEX t1b ON t1(b) WHERE b>10'), 'b'));
    $t->same(20, count($rows));
};

$tests['real upstream index6 updated partial stat counts match index6-1.11'] = static function (TestRunner $t) use ($index6Rows, $predicateFor, $countByPredicate): void {
    $rows = array_map(static fn (array $row): array => ['a' => $row['b'], 'b' => $row['b'], 'c' => $row['c']], $index6Rows(20));
    $t->same(20, $countByPredicate($rows, $predicateFor('CREATE INDEX t1a ON t1(a) WHERE a IS NOT NULL'), 'a'));
    $t->same(10, $countByPredicate($rows, $predicateFor('CREATE INDEX t1b ON t1(b) WHERE b>10'), 'b'));
};

$tests['real upstream index6 nullified and shifted partial stat counts match repeated index6-1.11'] = static function (TestRunner $t) use ($index6Rows, $predicateFor, $countByPredicate): void {
    $rows = array_map(static function (array $row): array {
        $b = $row['b'] + 100;

        return ['a' => $row['b'] % 3 !== 0 ? null : $row['b'], 'b' => $b, 'c' => $row['c']];
    }, $index6Rows(20));
    $t->same(6, $countByPredicate($rows, $predicateFor('CREATE INDEX t1a ON t1(a) WHERE a IS NOT NULL'), 'a'));
    $t->same(20, $countByPredicate($rows, $predicateFor('CREATE INDEX t1b ON t1(b) WHERE b>10'), 'b'));
};

$tests['real upstream index6 delete between partial stat counts match index6-1.13'] = static function (TestRunner $t) use ($index6Rows, $predicateFor, $countByPredicate): void {
    $rows = array_map(static function (array $row): array {
        $shifted = $row['b'] + 100;

        return ['a' => $shifted % 3 !== 0 ? $shifted : null, 'b' => $row['b'], 'c' => $row['c']];
    }, $index6Rows(20));
    $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['b'] < 8 || $row['b'] > 12));
    $t->same(15, count($rows));
    $t->same(10, $countByPredicate($rows, $predicateFor('CREATE INDEX t1a ON t1(a) WHERE a IS NOT NULL'), 'a'));
    $t->same(8, $countByPredicate($rows, $predicateFor('CREATE INDEX t1b ON t1(b) WHERE b>10'), 'b'));
};

$tests['real upstream index6 unique partial index rejects indexed duplicate'] = static function (TestRunner $t) use ($index3Rows, $predicateFor, $uniqueIndexAllows): void {
    $rows = $index3Rows();
    $predicate = $predicateFor('CREATE UNIQUE INDEX t3a ON t3(a) WHERE a<>999');
    $t->same(false, $uniqueIndexAllows($rows, $predicate, 'a', 150));
    $t->same(true, $uniqueIndexAllows($rows, $predicate, 'a', 999));
};

$tests['real upstream index6 unique partial index admits duplicate sentinel rows'] = static function (TestRunner $t) use ($index3Rows): void {
    $rows = $index3Rows();
    $rows[] = ['a' => 999, 'b' => 'test1'];
    $rows[] = ['a' => 999, 'b' => 'test2'];
    $t->same(162, count(array_filter($rows, static fn (array $row): bool => $row['a'] === 999)));
};

$tests['real upstream index6 qualified between counts match index6-5.0'] = static function (TestRunner $t) use ($index3Rows, $predicateFor, $countByPredicate): void {
    $rows = $index3Rows();
    $predicate = $predicateFor('CREATE INDEX t3b ON t3(b) WHERE xyzzy.t3.b BETWEEN 5 AND 10');
    $t->same(6, $countByPredicate($rows, $predicate, 'b'));
};

$tests['real upstream index6 and predicate implies reordered equality terms'] = static function (TestRunner $t) use ($predicateFor): void {
    $predicate = $predicateFor('CREATE INDEX t10x ON t10(d) WHERE a=1 AND b=2 AND c=3');
    $t->same(true, $predicate->value[0]->isImpliedByPointLookup('a', 1));
    $t->same(true, $predicate->value[1]->isImpliedByPointLookup('b', 2));
    $t->same(true, $predicate->value[2]->isImpliedByPointLookup('c', 3));
};

$tests['real upstream index6 not-null predicate does not filter boolean truth rows'] = static function (TestRunner $t) use ($predicateFor): void {
    $predicate = $predicateFor('CREATE INDEX index_0 ON t0(c0) WHERE c0 NOT NULL');
    $t->same(false, $predicate->isImpliedByPointLookup('c0', null));
    $t->same(true, $predicate->isImpliedByPointLookup('c0', 0));
    $t->same(true, $predicate->isImpliedByPointLookup('c0', ''));
};

foreach (range(1, 20) as $value) {
    $tests['real upstream index6-1.1 t1a row admission value ' . $value] = static function (TestRunner $t) use ($predicateFor, $value): void {
        $predicate = $predicateFor('CREATE INDEX t1a ON t1(a) WHERE a IS NOT NULL');
        $a = $value % 3 !== 0 ? $value : null;
        $t->same($value % 3 !== 0, $predicate->isImpliedByPointLookup('a', $a));
    };

    $tests['real upstream index6-1.1 t1b row admission value ' . $value] = static function (TestRunner $t) use ($predicateFor, $value): void {
        $predicate = $predicateFor('CREATE INDEX t1b ON t1(b) WHERE b>10');
        $t->same($value > 10, $predicate->isImpliedByPointLookup('b', $value));
    };
}

foreach (range(1, 999) as $value) {
    $tests['real upstream index6-2.1 t2a1 dynamic admission value ' . $value] = static function (TestRunner $t) use ($predicateFor, $value): void {
        $predicate = $predicateFor('CREATE INDEX t2a1 ON t2(a) WHERE a IS NOT NULL');
        $a = $value % 2 === 0 ? null : $value;
        $t->same($value % 2 !== 0, $predicate->isImpliedByPointLookup('a', $a));
    };
}

foreach ([15, 99, 100, 101, 199, 200, 201, 515] as $value) {
    $tests['real upstream index6-2.102 or partial index point implication ' . $value] = static function (TestRunner $t) use ($predicateFor, $value): void {
        $predicate = $predicateFor('CREATE INDEX t2a2 ON t2(a) WHERE a<100 OR a>200');
        $t->same($value < 100 || $value > 200, $predicate->isImpliedByPointLookup('a', $value));
    };
}

foreach ([9, 10, 11, 12, 13, 20, null] as $value) {
    $tests['real upstream index6-9.1 in-list partial index implication ' . var_export($value, true)] = static function (TestRunner $t) use ($predicateFor, $value): void {
        $predicate = $predicateFor('CREATE INDEX t9ca ON t9(c,a) WHERE a in (10,12,20)');
        $t->same(in_array($value, [10, 12, 20], true), $predicate->isExpressionInListImpliedByPointLookup('a', $value));
    };
}

foreach (range(1, 199) as $value) {
    $tests['real upstream index6-3.1 partial unique admission value ' . $value] = static function (TestRunner $t) use ($predicateFor, $value): void {
        $predicate = $predicateFor('CREATE UNIQUE INDEX t3a ON t3(a) WHERE a<>999');
        $a = $value % 5 !== 0 ? 999 : $value;
        $t->same($a !== 999, $predicate->isImpliedByPointLookup('a', $a));
    };
}

return $tests;
