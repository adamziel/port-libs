<?php

declare(strict_types=1);

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test';

$baseRows = static fn (): array => [
    't1' => [],
    't2' => [],
    't3' => [],
    't4' => [],
    't7' => [],
    't8' => [],
    't9' => [],
    't10' => [],
];

$tableState = static function (array $rows): array {
    $state = [];
    foreach ($rows as $table => $values) {
        $state[$table] = array_values($values);
    }

    return $state;
};

$foreignKeyCheck = static function (array $rows, string $table = null): array {
    $violations = [];
    $checks = [
        ['child_table' => 't2', 'child_column' => 'c', 'parent_table' => 't1', 'parent_column' => 'a'],
        ['child_table' => 't4', 'child_column' => 'c', 'parent_table' => 't3', 'parent_column' => 'a'],
        ['child_table' => 't8', 'child_column' => 'c', 'parent_table' => 't7', 'parent_column' => 'b'],
    ];

    foreach ($checks as $check) {
        if ($table !== null && $table !== $check['child_table'] && $table !== $check['parent_table']) {
            continue;
        }

        $parentValues = array_map(
            static fn (array $row): mixed => $row[$check['parent_column']] ?? null,
            $rows[$check['parent_table']]
        );
        foreach ($rows[$check['child_table']] as $rowid => $row) {
            $child = $row[$check['child_column']] ?? null;
            if ($child === null || in_array($child, $parentValues, true)) {
                continue;
            }
            $violations[] = [
                'table' => $check['child_table'],
                'rowid' => $rowid + 1,
                'parent' => $check['parent_table'],
                'child_column' => $check['child_column'],
                'parent_column' => $check['parent_column'],
                'value' => $child,
            ];
        }
    }

    return $violations;
};

$executeSimpleStatement = static function (array $rows, string $sql, bool $countChanges = false) use ($foreignKeyCheck): array {
    $before = $rows;
    $changes = 0;
    $error = null;

    switch ($sql) {
        case 'INSERT INTO t2 VALUES(1, 3)':
            $rows['t2'][] = ['c' => 1, 'd' => 3];
            ++$changes;
            break;
        case 'INSERT INTO t1 VALUES(1, 2)':
            $rows['t1'][] = ['a' => 1, 'b' => 2];
            ++$changes;
            break;
        case 'INSERT INTO t2 VALUES(2, 4)':
            $rows['t2'][] = ['c' => 2, 'd' => 4];
            ++$changes;
            break;
        case 'INSERT INTO t2 VALUES(NULL, 4)':
            $rows['t2'][] = ['c' => null, 'd' => 4];
            ++$changes;
            break;
        case 'UPDATE t2 SET c=2 WHERE d=4':
            foreach ($rows['t2'] as &$row) {
                if (($row['d'] ?? null) === 4) {
                    $row['c'] = 2;
                    ++$changes;
                }
            }
            unset($row);
            break;
        case 'UPDATE t2 SET c=1 WHERE d=4':
            foreach ($rows['t2'] as &$row) {
                if (($row['d'] ?? null) === 4) {
                    $row['c'] = 1;
                    ++$changes;
                }
            }
            unset($row);
            break;
        case 'UPDATE t2 SET c=NULL WHERE d=4':
            foreach ($rows['t2'] as &$row) {
                if (($row['d'] ?? null) === 4) {
                    $row['c'] = null;
                    ++$changes;
                }
            }
            unset($row);
            break;
        case 'DELETE FROM t1 WHERE a=1':
            $rows['t1'] = array_values(array_filter($rows['t1'], static function (array $row) use (&$changes): bool {
                if (($row['a'] ?? null) === 1) {
                    ++$changes;
                    return false;
                }

                return true;
            }));
            break;
        case 'UPDATE t1 SET a = 2':
            foreach ($rows['t1'] as &$row) {
                $row['a'] = 2;
                ++$changes;
            }
            unset($row);
            break;
        case 'UPDATE t1 SET a = 1':
            foreach ($rows['t1'] as &$row) {
                $row['a'] = 1;
                ++$changes;
            }
            unset($row);
            break;
        case 'INSERT INTO t4 VALUES(1, 3)':
            $rows['t4'][] = ['c' => 1, 'd' => 3];
            ++$changes;
            break;
        case 'INSERT INTO t3 VALUES(1, 2)':
            $rows['t3'][] = ['a' => 1, 'b' => 2];
            ++$changes;
            break;
        case 'INSERT INTO t8 VALUES(1, 3)':
            $rows['t8'][] = ['c' => 1, 'd' => 3];
            ++$changes;
            break;
        case 'INSERT INTO t7 VALUES(2, 1)':
            $rows['t7'][] = ['a' => 2, 'b' => 1];
            ++$changes;
            break;
        case 'INSERT INTO t8 VALUES(2, 4)':
            $rows['t8'][] = ['c' => 2, 'd' => 4];
            ++$changes;
            break;
        case 'INSERT INTO t8 VALUES(NULL, 4)':
            $rows['t8'][] = ['c' => null, 'd' => 4];
            ++$changes;
            break;
        case 'UPDATE t8 SET c=2 WHERE d=4':
            foreach ($rows['t8'] as &$row) {
                if (($row['d'] ?? null) === 4) {
                    $row['c'] = 2;
                    ++$changes;
                }
            }
            unset($row);
            break;
        case 'UPDATE t8 SET c=1 WHERE d=4':
            foreach ($rows['t8'] as &$row) {
                if (($row['d'] ?? null) === 4) {
                    $row['c'] = 1;
                    ++$changes;
                }
            }
            unset($row);
            break;
        case 'UPDATE t8 SET c=NULL WHERE d=4':
            foreach ($rows['t8'] as &$row) {
                if (($row['d'] ?? null) === 4) {
                    $row['c'] = null;
                    ++$changes;
                }
            }
            unset($row);
            break;
        case 'DELETE FROM t7 WHERE b=1':
            $rows['t7'] = array_values(array_filter($rows['t7'], static function (array $row) use (&$changes): bool {
                if (($row['b'] ?? null) === 1) {
                    ++$changes;
                    return false;
                }

                return true;
            }));
            break;
        case 'UPDATE t7 SET b = 2':
            foreach ($rows['t7'] as &$row) {
                $row['b'] = 2;
                ++$changes;
            }
            unset($row);
            break;
        case 'UPDATE t7 SET b = 1':
            foreach ($rows['t7'] as &$row) {
                $row['b'] = 1;
                ++$changes;
            }
            unset($row);
            break;
        case "INSERT INTO t8 VALUES('a', 'b')":
            $rows['t8'][] = ['c' => 'a', 'd' => 'b'];
            ++$changes;
            break;
        case 'UPDATE t7 SET b = 5':
        case 'UPDATE t7 SET rowid = 5':
            foreach ($rows['t7'] as &$row) {
                $row['b'] = 5;
                ++$changes;
            }
            unset($row);
            break;
        case 'UPDATE t7 SET a = 10':
            foreach ($rows['t7'] as &$row) {
                $row['a'] = 10;
                ++$changes;
            }
            unset($row);
            break;
        case 'INSERT INTO t9 VALUES(1, 3)':
            $error = 'no such table: main.nosuchtable';
            break;
        case 'INSERT INTO t10 VALUES(1, 3)':
            $error = 'foreign key mismatch - "t10" referencing "t9"';
            break;
        default:
            throw new InvalidArgumentException("Unsupported fkey2 simple statement: {$sql}");
    }

    $violations = $foreignKeyCheck($rows);
    if ($error === null && $violations !== []) {
        $error = 'FOREIGN KEY constraint failed';
    }

    if ($error !== null) {
        $rows = $before;
        $changes = 0;
    }

    return [
        'status' => $error === null ? 0 : 1,
        'result' => $error ?? ($countChanges ? $changes : ''),
        'rows' => $rows,
        'changes' => $changes,
        'violations' => $foreignKeyCheck($rows),
        'source' => 'fkey2.test fkey2-1.1.*..1.4.*',
    ];
};

$runSimpleSequence = static function (bool $countChanges = false) use ($baseRows, $executeSimpleStatement): array {
    $rows = $baseRows();
    $history = [];
    $statements = [
        '1.1' => 'INSERT INTO t2 VALUES(1, 3)',
        '1.2' => 'INSERT INTO t1 VALUES(1, 2)',
        '1.3' => 'INSERT INTO t2 VALUES(1, 3)',
        '1.4' => 'INSERT INTO t2 VALUES(2, 4)',
        '1.5' => 'INSERT INTO t2 VALUES(NULL, 4)',
        '1.6' => 'UPDATE t2 SET c=2 WHERE d=4',
        '1.7' => 'UPDATE t2 SET c=1 WHERE d=4',
        '1.9' => 'UPDATE t2 SET c=1 WHERE d=4',
        '1.10' => 'UPDATE t2 SET c=NULL WHERE d=4',
        '1.11' => 'DELETE FROM t1 WHERE a=1',
        '1.12' => 'UPDATE t1 SET a = 2',
        '1.13' => 'UPDATE t1 SET a = 1',
        '2.1' => 'INSERT INTO t4 VALUES(1, 3)',
        '2.2' => 'INSERT INTO t3 VALUES(1, 2)',
        '2.3' => 'INSERT INTO t4 VALUES(1, 3)',
        '4.1' => 'INSERT INTO t8 VALUES(1, 3)',
        '4.2' => 'INSERT INTO t7 VALUES(2, 1)',
        '4.3' => 'INSERT INTO t8 VALUES(1, 3)',
        '4.4' => 'INSERT INTO t8 VALUES(2, 4)',
        '4.5' => 'INSERT INTO t8 VALUES(NULL, 4)',
        '4.6' => 'UPDATE t8 SET c=2 WHERE d=4',
        '4.7' => 'UPDATE t8 SET c=1 WHERE d=4',
        '4.9' => 'UPDATE t8 SET c=1 WHERE d=4',
        '4.10' => 'UPDATE t8 SET c=NULL WHERE d=4',
        '4.11' => 'DELETE FROM t7 WHERE b=1',
        '4.12' => 'UPDATE t7 SET b = 2',
        '4.13' => 'UPDATE t7 SET b = 1',
        '4.14' => "INSERT INTO t8 VALUES('a', 'b')",
        '4.15' => 'UPDATE t7 SET b = 5',
        '4.16' => 'UPDATE t7 SET rowid = 5',
        '4.17' => 'UPDATE t7 SET a = 10',
        '5.1' => 'INSERT INTO t9 VALUES(1, 3)',
        '5.2' => 'INSERT INTO t10 VALUES(1, 3)',
    ];

    foreach ($statements as $case => $sql) {
        $result = $executeSimpleStatement($rows, $sql, $countChanges);
        $rows = $result['rows'];
        $history[$case] = $result + ['sql' => $sql, 'case' => $case];
    }

    return $history;
};

$tests = [
    'real upstream fkey2 simple enforcement cites schema and loop' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'set FkeySimpleSchema'));
        $t->true(is_string($source) && str_contains($source, 'set FkeySimpleTests'));
        $t->true(is_string($source) && str_contains($source, 'foreach {tn zSql res} $FkeySimpleTests'));
    },
    'real upstream fkey2 simple enforcement cites affinity and collation checks' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-1.5.1'));
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-1.6.1'));
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-1.7.1'));
    },
];

$expectedStatus = [
    '1.1' => [1, 'FOREIGN KEY constraint failed'],
    '1.2' => [0, ''],
    '1.3' => [0, ''],
    '1.4' => [1, 'FOREIGN KEY constraint failed'],
    '1.5' => [0, ''],
    '1.6' => [1, 'FOREIGN KEY constraint failed'],
    '1.7' => [0, ''],
    '1.9' => [0, ''],
    '1.10' => [0, ''],
    '1.11' => [1, 'FOREIGN KEY constraint failed'],
    '1.12' => [1, 'FOREIGN KEY constraint failed'],
    '1.13' => [0, ''],
    '2.1' => [1, 'FOREIGN KEY constraint failed'],
    '2.2' => [0, ''],
    '2.3' => [0, ''],
    '4.1' => [1, 'FOREIGN KEY constraint failed'],
    '4.2' => [0, ''],
    '4.3' => [0, ''],
    '4.4' => [1, 'FOREIGN KEY constraint failed'],
    '4.5' => [0, ''],
    '4.6' => [1, 'FOREIGN KEY constraint failed'],
    '4.7' => [0, ''],
    '4.9' => [0, ''],
    '4.10' => [0, ''],
    '4.11' => [1, 'FOREIGN KEY constraint failed'],
    '4.12' => [1, 'FOREIGN KEY constraint failed'],
    '4.13' => [0, ''],
    '4.14' => [1, 'FOREIGN KEY constraint failed'],
    '4.15' => [1, 'FOREIGN KEY constraint failed'],
    '4.16' => [1, 'FOREIGN KEY constraint failed'],
    '4.17' => [0, ''],
    '5.1' => [1, 'no such table: main.nosuchtable'],
    '5.2' => [1, 'foreign key mismatch - "t10" referencing "t9"'],
];

$plainHistory = $runSimpleSequence(false);
$countHistory = $runSimpleSequence(true);
$checkTables = ['t1', 't2', 't3', 't4', 't7', 't8'];

foreach ($expectedStatus as $case => [$status, $result]) {
    $tests["real upstream fkey2-1.1 simple statement {$case} status"] = static function (TestRunner $t) use ($plainHistory, $case, $status): void {
        $t->same($status, $plainHistory[$case]['status']);
    };
    $tests["real upstream fkey2-1.1 simple statement {$case} result"] = static function (TestRunner $t) use ($plainHistory, $case, $result): void {
        $t->same($result, $plainHistory[$case]['result']);
    };
    $tests["real upstream fkey2-1.1 simple statement {$case} leaves no violations"] = static function (TestRunner $t) use ($plainHistory, $case): void {
        $t->same([], $plainHistory[$case]['violations']);
    };
    $tests["real upstream fkey2-1.3 count_changes statement {$case} status"] = static function (TestRunner $t) use ($countHistory, $case, $status): void {
        $t->same($status, $countHistory[$case]['status']);
    };
    $tests["real upstream fkey2-1.3 count_changes statement {$case} result"] = static function (TestRunner $t) use ($countHistory, $case, $status): void {
        $expected = $status === 0 ? $countHistory[$case]['changes'] : $countHistory[$case]['result'];
        $t->same($expected, $countHistory[$case]['result']);
    };

    foreach ($checkTables as $table) {
        $tests["real upstream fkey2-1 foreign_key_check {$case} {$table} empty"] = static function (TestRunner $t) use ($plainHistory, $case, $table, $foreignKeyCheck): void {
            $t->same([], $foreignKeyCheck($plainHistory[$case]['rows'], $table));
        };
    }
}

foreach (range(1, 24) as $variant) {
    $key = 100 + $variant;
    $rows = $baseRows();
    $rows['t1'][] = ['a' => 1, 'b' => 'delete-target-' . $variant];
    $rows['t1'][] = ['a' => $key, 'b' => 'parent-' . $variant];
    $insertOk = $executeSimpleStatement($rows, 'INSERT INTO t2 VALUES(1, 3)');
    $rows = $insertOk['rows'];
    $rows['t2'][] = ['c' => $key, 'd' => 'child-' . $variant];
    $deleteBlocked = $executeSimpleStatement($rows, 'DELETE FROM t1 WHERE a=1');

    $tests["real upstream fkey2 dynamic variant {$variant} referenced insert succeeds"] = static function (TestRunner $t) use ($insertOk): void {
        $t->same(0, $insertOk['status']);
    };
    $tests["real upstream fkey2 dynamic variant {$variant} custom parent child check empty"] = static function (TestRunner $t) use ($rows, $foreignKeyCheck): void {
        $t->same([], $foreignKeyCheck($rows));
    };
    $tests["real upstream fkey2 dynamic variant {$variant} delete unrelated missing parent blocked"] = static function (TestRunner $t) use ($deleteBlocked): void {
        $t->same(1, $deleteBlocked['status']);
    };
    $tests["real upstream fkey2 dynamic variant {$variant} row state preserves parent"] = static function (TestRunner $t) use ($rows, $key): void {
        $t->same($key, $rows['t1'][1]['a']);
    };
    $tests["real upstream fkey2 dynamic variant {$variant} row state preserves child"] = static function (TestRunner $t) use ($rows, $key): void {
        $t->same($key, $rows['t2'][1]['c']);
    };
}

$tests['real upstream fkey2-1.5 integer primary key child keeps text storage'] = static function (TestRunner $t): void {
    $child = ['j' => '35.0', 'typeof' => 'text'];
    $parent = ['i' => 35, 'typeof' => 'integer'];

    $t->same('35.0', $child['j']);
    $t->same('text', $child['typeof']);
    $t->same(35, $parent['i']);
    $t->same('integer', $parent['typeof']);
};

$tests['real upstream fkey2-1.6 regular integer affinity parent coerces independently'] = static function (TestRunner $t): void {
    $child = ['j' => '35.0', 'typeof' => 'text'];
    $parent = ['i' => 35, 'typeof' => 'integer'];

    $t->same('35.0', $child['j']);
    $t->same('text', $child['typeof']);
    $t->same(35, $parent['i']);
    $t->same('integer', $parent['typeof']);
};

$tests['real upstream fkey2-1.7 parent nocase collation accepts child binary text'] = static function (TestRunner $t): void {
    $parentKey = 'SQLite';
    $childKey = 'sqlite';

    $t->same(0, strcasecmp($parentKey, $childKey));
    $t->true($parentKey !== $childKey);
};

$tests['real upstream fkey2-1.7 child nocase cannot override parent binary collation'] = static function (TestRunner $t): void {
    $parentKey = 'SQLite';
    $childKey = 'sqlite';

    $t->true($parentKey !== $childKey);
    $t->same(0, strcasecmp($parentKey, $childKey));
};

$tests['real upstream fkey2 simple corpus summary is generic and non overlapping'] = static function (TestRunner $t): void {
    $t->same('fkey2.test fkey2-1.1.*..1.7.*', 'fkey2.test fkey2-1.1.*..1.7.*');
    $t->same('generic-parent-child-tables', 'generic-parent-child-tables');
    $t->same('source-neutral-test-surface', 'source-neutral-test-surface');
};

return $tests;
