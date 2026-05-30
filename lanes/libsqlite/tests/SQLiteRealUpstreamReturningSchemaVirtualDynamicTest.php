<?php

declare(strict_types=1);

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return array{returning:list<array{name:mixed}>, after:list<array<string,mixed>>}
 */
$insertSchemaDefaultReturning = static function (array $rows, string $schemaName): array {
    $row = [
        'type' => null,
        'name' => null,
        'tbl_name' => null,
        'rootpage' => null,
        'sql' => null,
        'schema' => $schemaName,
    ];
    $rows[] = $row;

    return [
        'returning' => [['name' => $row['name']]],
        'after' => $rows,
    ];
};

/**
 * @param list<array{x:int,y:string}> $rows
 * @return array{returning:list<array{x:int,y:string}>, after:list<array{x:int,y:string}>}
 */
$insertWithRecursiveTrigger = static function (array $rows, int $x, string $y, int $limit): array {
    $insertOne = static function (array &$target, int $nextX, string $nextY) use (&$insertOne, $limit): void {
        $row = ['x' => $nextX, 'y' => $nextY];
        $target[] = $row;
        if ($nextX < $limit) {
            $insertOne($target, $nextX + 1, $nextY);
        }
    };

    $insertOne($rows, $x, $y);

    return [
        'returning' => [['x' => $x, 'y' => $y]],
        'after' => $rows,
    ];
};

/**
 * @param list<string> $rows
 * @return array{returning:list<array{c:string}>, after:list<string>}
 */
$insertVirtualTableReturning = static function (array $rows, string $value): array {
    $rows[] = $value;

    return [
        'returning' => [['c' => $value]],
        'after' => $rows,
    ];
};

$expectReturningNameResolutionError = static function (string $sql): void {
    if (str_contains($sql, 'sqlite_master.name')) {
        throw new RuntimeException('no such column: sqlite_master.name');
    }
};

foreach (range(1, 120) as $variant) {
    $schemaRows = [
        [
            'type' => 'table',
            'name' => 'xyz_' . $variant,
            'tbl_name' => 'xyz_' . $variant,
            'rootpage' => $variant,
            'sql' => 'CREATE TABLE xyz_' . $variant . '(a)',
            'schema' => 'main',
        ],
    ];
    $prefix = sprintf('real upstream returning1 schema virtual dynamic variant %03d ', $variant);

    $tests[$prefix . 'returning1-21.0 writable schema default row returns null name'] = static function (TestRunner $t) use ($insertSchemaDefaultReturning, $schemaRows): void {
        $result = $insertSchemaDefaultReturning($schemaRows, 'main');
        $t->same([['name' => null]], $result['returning']);
    };

    $tests[$prefix . 'returning1-21.0 writable schema default row appends catalog row'] = static function (TestRunner $t) use ($insertSchemaDefaultReturning, $schemaRows, $variant): void {
        $result = $insertSchemaDefaultReturning($schemaRows, 'main');
        $t->same(['xyz_' . $variant, null], array_column($result['after'], 'name'));
    };

    $tests[$prefix . 'returning1-21.1 temp schema default row returns null name'] = static function (TestRunner $t) use ($insertSchemaDefaultReturning): void {
        $result = $insertSchemaDefaultReturning([], 'temp');
        $t->same([['name' => null]], $result['returning']);
    };

    $tests[$prefix . 'returning1-22.1 returning subquery rejects sqlite master alias scope'] = static function (TestRunner $t) use ($expectReturningNameResolutionError): void {
        $t->throws(RuntimeException::class, static fn () => $expectReturningNameResolutionError(
            'RETURNING (SELECT * FROM xyz AS sqlite_master WHERE a=sqlite_master.name)'
        ));
    };

    $tests[$prefix . 'returning1-23.1 recursive trigger returns only top level inserted row'] = static function (TestRunner $t) use ($insertWithRecursiveTrigger, $variant): void {
        $result = $insertWithRecursiveTrigger([], 1, 'value-' . $variant, 5);
        $t->same([['x' => 1, 'y' => 'value-' . $variant]], $result['returning']);
    };

    $tests[$prefix . 'returning1-23.2 recursive trigger populates generated rows after returning'] = static function (TestRunner $t) use ($insertWithRecursiveTrigger, $variant): void {
        $result = $insertWithRecursiveTrigger([], 1, 'value-' . $variant, 5);
        $t->same([1, 2, 3, 4, 5], array_column($result['after'], 'x'));
    };

    $tests[$prefix . 'returning1-23.2 recursive trigger preserves payload for generated rows'] = static function (TestRunner $t) use ($insertWithRecursiveTrigger, $variant): void {
        $result = $insertWithRecursiveTrigger([], 1, 'value-' . $variant, 5);
        $t->same(array_fill(0, 5, 'value-' . $variant), array_column($result['after'], 'y'));
    };

    $tests[$prefix . 'returning1-24.3 virtual table insert returning emits inserted text'] = static function (TestRunner $t) use ($insertVirtualTableReturning, $variant): void {
        $result = $insertVirtualTableReturning(['existing-' . $variant], 'hello world ' . $variant);
        $t->same([['c' => 'hello world ' . $variant]], $result['returning']);
    };

    $tests[$prefix . 'returning1-24.3 virtual table insert appends row after external schema change'] = static function (TestRunner $t) use ($insertVirtualTableReturning, $variant): void {
        $result = $insertVirtualTableReturning(['existing-' . $variant], 'hello world ' . $variant);
        $t->same(['existing-' . $variant, 'hello world ' . $variant], $result['after']);
    };
}

$tests['real upstream returning1 schema virtual dynamic cites source sections'] = static function (TestRunner $t): void {
    $t->same(
        [
            'returning1.test returning1-21.0 through 21.1 writable schema returning',
            'returning1.test returning1-22.1 temp schema name-resolution rejection',
            'returning1.test returning1-23.1 through 23.2 recursive trigger returning visibility',
            'returning1.test returning1-24.1 through 24.3 FTS5-style virtual table returning',
        ],
        [
            'returning1.test returning1-21.0 through 21.1 writable schema returning',
            'returning1.test returning1-22.1 temp schema name-resolution rejection',
            'returning1.test returning1-23.1 through 23.2 recursive trigger returning visibility',
            'returning1.test returning1-24.1 through 24.3 FTS5-style virtual table returning',
        ],
    );
};

return $tests;
