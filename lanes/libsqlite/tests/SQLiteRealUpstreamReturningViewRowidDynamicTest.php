<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteReturningViewRowidPlan;

$tests = [];

$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test';

$tests['real upstream returning1 view rowid cites section 10 insert returning rowid'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);
    $t->true(is_string($source) && str_contains($source, 'do_catchsql_test 10.3a'));
    $t->true(is_string($source) && str_contains($source, 'INSERT INTO t1(a, b) VALUES(1234, 5678) RETURNING rowid'));
    $t->true(is_string($source) && str_contains($source, 'no such column: new.rowid'));
};

$tests['real upstream returning1 view rowid cites section 10 update returning rowid'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);
    $t->true(is_string($source) && str_contains($source, 'do_catchsql_test 10.3b'));
    $t->true(is_string($source) && str_contains($source, "UPDATE t1 SET a='z' WHERE b='y' RETURNING rowid"));
    $t->true(is_string($source) && str_contains($source, 'INSERT INTO log VALUES(\'update\', new.rowid, new.a, new.b)'));
};

$tests['real upstream returning1 view rowid cites enabled undefined rowid branch'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);
    $t->true(is_string($source) && str_contains($source, 'INSERT returns -1, and the UPDATE returns NULL'));
    $t->true(is_string($source) && str_contains($source, 'insert -1 1234 5678 update {} z y update {} z y update {} z y'));
};

$makeRows = static function (int $seed): array {
    $rows = [];
    $matchCount = 1 + ($seed % 5);
    for ($i = 0; $i < $matchCount; ++$i) {
        $rows[] = [
            'a' => 'seed-' . $seed . '-match-' . $i,
            'b' => 'match-' . $seed,
        ];
    }

    $rows[] = [
        'a' => 'seed-' . $seed . '-keep-left',
        'b' => 'other-left-' . $seed,
    ];
    $rows[] = [
        'a' => 'seed-' . $seed . '-keep-right',
        'b' => 'other-right-' . $seed,
    ];

    return $rows;
};

$expectedAfterEnabled = static function (array $rows, array $insertRow, mixed $updateA, mixed $updateWhereB): array {
    $after = $rows;
    $after[] = $insertRow;

    foreach ($after as $index => $row) {
        if ($row['b'] === $updateWhereB) {
            $after[$index]['a'] = $updateA;
        }
    }

    return $after;
};

$expectedUpdateLog = static function (array $rows, mixed $updateA, mixed $updateWhereB): array {
    $log = [];
    foreach ($rows as $row) {
        if ($row['b'] === $updateWhereB) {
            $log[] = ['op' => 'update', 'rowid' => null, 'a' => $updateA, 'b' => $updateWhereB];
        }
    }

    return $log;
};

$expectedNullReturning = static function (array $rows, mixed $updateWhereB): array {
    $returning = [];
    foreach ($rows as $row) {
        if ($row['b'] === $updateWhereB) {
            $returning[] = ['rowid' => null];
        }
    }

    return $returning;
};

for ($i = 1; $i <= 1000; ++$i) {
    $rows = $makeRows($i);
    $insertRow = ['a' => 'insert-' . $i, 'b' => 'fresh-' . $i];
    $updateA = 'updated-' . $i;
    $whereB = 'match-' . $i;

    $disabled = static fn (): array => SQLiteReturningViewRowidPlan::returning1Section10Plan(
        $rows,
        $insertRow,
        $updateA,
        $whereB,
        false
    );
    $enabled = static fn (): array => SQLiteReturningViewRowidPlan::returning1Section10Plan(
        $rows,
        $insertRow,
        $updateA,
        $whereB,
        true
    );

    $tests["real upstream returning1 view rowid disabled branch rejects before triggers dynamic {$i}"] = static function (TestRunner $t) use ($disabled, $rows, $value): void {
        $plan = $disabled();

        $t->same('returning1.test returning1-10.1..10.4', $value($plan, 'source'));
        $t->same('schema-error', $value($plan, 'status'));
        $t->same(false, $value($plan, 'allow_rowid_in_view'));
        $t->same('no such column: new.rowid', $value($plan, 'insert.error'));
        $t->same('no such column: new.rowid', $value($plan, 'update.error'));
        $t->same([], $value($plan, 'trigger_log'));
        $t->same($rows, $value($plan, 'rows_after_statement'));
        $t->same(0, $value($plan, 'changes'));
    };

    $tests["real upstream returning1 view rowid enabled insert returns minus one dynamic {$i}"] = static function (TestRunner $t) use ($enabled, $rows, $insertRow, $value): void {
        $plan = $enabled();
        $expectedAfterInsert = $rows;
        $expectedAfterInsert[] = $insertRow;

        $t->same('commit-ok', $value($plan, 'insert.status'));
        $t->same([['rowid' => -1]], $value($plan, 'insert.returning'));
        $t->same([['op' => 'insert', 'rowid' => -1, 'a' => $insertRow['a'], 'b' => $insertRow['b']]], $value($plan, 'insert.trigger_log'));
        $t->same($expectedAfterInsert, $value($plan, 'insert.rows_after_statement'));
        $t->same(1, $value($plan, 'insert.changes'));
    };

    $tests["real upstream returning1 view rowid enabled update returns null rowids dynamic {$i}"] = static function (TestRunner $t) use ($enabled, $rows, $insertRow, $updateA, $whereB, $expectedNullReturning, $expectedUpdateLog, $value): void {
        $rowsAfterInsert = $rows;
        $rowsAfterInsert[] = $insertRow;
        $expectedReturning = $expectedNullReturning($rowsAfterInsert, $whereB);

        $plan = $enabled();

        $t->same('commit-ok', $value($plan, 'update.status'));
        $t->same($expectedReturning, $value($plan, 'update.returning'));
        $t->same($expectedUpdateLog($rowsAfterInsert, $updateA, $whereB), $value($plan, 'update.trigger_log'));
        $t->same(count($expectedReturning), $value($plan, 'update.changes'));
        $t->same(null, $value($plan, 'update.error'));
    };

    $tests["real upstream returning1 view rowid enabled keeps log order and final rows dynamic {$i}"] = static function (TestRunner $t) use ($enabled, $rows, $insertRow, $updateA, $whereB, $expectedAfterEnabled, $expectedUpdateLog, $expectedNullReturning, $value): void {
        $rowsAfterInsert = $rows;
        $rowsAfterInsert[] = $insertRow;
        $expectedLog = array_merge(
            [['op' => 'insert', 'rowid' => -1, 'a' => $insertRow['a'], 'b' => $insertRow['b']]],
            $expectedUpdateLog($rowsAfterInsert, $updateA, $whereB)
        );
        $expectedUpdateReturning = $expectedNullReturning($rowsAfterInsert, $whereB);

        $plan = $enabled();

        $t->same('returning-view-rowid-instead-of-trigger', $value($plan, 'operation'));
        $t->same('commit-ok', $value($plan, 'status'));
        $t->same(true, $value($plan, 'allow_rowid_in_view'));
        $t->same($expectedLog, $value($plan, 'trigger_log'));
        $t->same($expectedAfterEnabled($rows, $insertRow, $updateA, $whereB), $value($plan, 'rows_after_statement'));
        $t->same([['rowid' => -1]], $value($plan, 'returning.insert'));
        $t->same($expectedUpdateReturning, $value($plan, 'returning.update'));
        $t->same(1 + count($expectedUpdateReturning), $value($plan, 'changes'));
        $t->same('sqlite-returning-view-rowid-trigger-log-order', $value($plan, 'dependencies.5'));
    };
}

$tests['real upstream returning1 view rowid rejects malformed base row'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteReturningViewRowidPlan::returning1Section10Plan([['a' => 'x']], ['a' => 'i', 'b' => 'j'], 'z', 'j', true)
);

$tests['real upstream returning1 view rowid rejects sparse base row list'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteReturningViewRowidPlan::returning1Section10Plan([1 => ['a' => 'x', 'b' => 'y']], ['a' => 'i', 'b' => 'j'], 'z', 'j', true)
);

$tests['real upstream returning1 view rowid rejects malformed insert row'] = static fn (TestRunner $t) => $t->throws(
    InvalidArgumentException::class,
    static fn () => SQLiteReturningViewRowidPlan::returning1Section10Plan([['a' => 'x', 'b' => 'y']], ['a' => 'i'], 'z', 'j', true)
);

return $tests;
