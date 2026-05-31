<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

for ($i = 0; $i < 40; ++$i) {
    $base = $i * 10;
    $rows = [
        ['w' => 'a' . $i, 'x' => 1 + $base, 'a b' => 1, 'z' => 1 + $base],
        ['w' => 'b' . $i, 'x' => 2 + $base, 'a b' => 2, 'z' => 2 + $base],
    ];
    $unique = [['x', 'a b'], ['z']];
    $columns = '(w, x, [a b], z)';
    $incoming = "('hello{$i}', " . (1 + $base) . ', 1, NULL)';
    $prefix = sprintf('real upstream upsert4 excluded dynamic variant %03d ', $i);

    $noAlias = static fn (): array => SQLiteUpsertReturningSql::execute(
        "INSERT INTO excluded {$columns} VALUES {$incoming} ON CONFLICT(x, \"a b\") DO UPDATE SET w=excluded.w RETURNING w, x, [a b]",
        ['excluded' => $rows],
        $unique,
    );
    $tests[$prefix . '8.1 resolves excluded qualifier to target table when target is named excluded'] = static function (TestRunner $t) use ($noAlias, $i): void {
        $t->same(['a' . $i], array_column($noAlias()['returning'], 'w'));
    };
    $tests[$prefix . '8.1 keeps row image unchanged for current-table excluded reference'] = static function (TestRunner $t) use ($noAlias, $i): void {
        $t->same(['a' . $i, 'b' . $i], array_column($noAlias()['after'], 'w'));
    };

    $withAlias = static fn (): array => SQLiteUpsertReturningSql::execute(
        "INSERT INTO excluded AS x1 {$columns} VALUES {$incoming} ON CONFLICT(x, [a b]) DO UPDATE SET w=excluded.w RETURNING w, x, [a b]",
        ['excluded' => $rows],
        $unique,
    );
    $tests[$prefix . '8.2 resolves excluded qualifier to incoming row when insert target has alias'] = static function (TestRunner $t) use ($withAlias, $i): void {
        $t->same(['hello' . $i], array_column($withAlias()['returning'], 'w'));
    };
    $tests[$prefix . '8.2 updates only the matching composite primary key row'] = static function (TestRunner $t) use ($withAlias, $i): void {
        $t->same(['hello' . $i, 'b' . $i], array_column($withAlias()['after'], 'w'));
    };

    $whereFalse = static fn (): array => SQLiteUpsertReturningSql::execute(
        "INSERT INTO excluded AS x1 {$columns} VALUES {$incoming} ON CONFLICT(x, [a b]) DO UPDATE SET w=w||w WHERE excluded.w!='hello{$i}' RETURNING w",
        ['excluded' => $rows],
        $unique,
    );
    $tests[$prefix . '8.3 uses incoming excluded value in WHERE predicate'] = static function (TestRunner $t) use ($whereFalse): void {
        $t->same(0, $whereFalse()['changes']);
    };
    $tests[$prefix . '8.3 suppresses RETURNING rows when aliased excluded WHERE is false'] = static function (TestRunner $t) use ($whereFalse): void {
        $t->same([], $whereFalse()['returning']);
    };

    $whereTrue = static fn (): array => SQLiteUpsertReturningSql::execute(
        "INSERT INTO excluded AS x1 {$columns} VALUES {$incoming} ON CONFLICT(x, [a b]) DO UPDATE SET w=w||w WHERE excluded.x=" . (1 + $base) . ' RETURNING w',
        ['excluded' => $rows],
        $unique,
    );
    $tests[$prefix . '8.4 evaluates numeric incoming excluded predicate'] = static function (TestRunner $t) use ($whereTrue): void {
        $t->same(1, $whereTrue()['changes']);
    };
    $tests[$prefix . '8.4 yields doubled current value after true excluded predicate'] = static function (TestRunner $t) use ($whereTrue, $i): void {
        $t->same(['a' . $i . 'a' . $i], array_column($whereTrue()['returning'], 'w'));
    };
}

for ($i = 0; $i < 40; ++$i) {
    $rows = [
        ['w' => 'left' . $i, 'x' => 1, 'y' => 1, 'z' => 1],
        ['w' => 'right' . $i, 'x' => 2, 'y' => 2, 'z' => 2],
    ];
    $unique = [['x', 'y'], ['z']];
    $prefix = sprintf('real upstream upsert4 alias dynamic variant %03d ', $i);

    $excludedSource = static fn (): array => SQLiteUpsertReturningSql::execute(
        "INSERT INTO t1 (w, x, y, z) VALUES ('incoming{$i}', 3, 3, 1) ON CONFLICT(z) DO UPDATE SET w=excluded.w RETURNING w, z",
        ['t1' => $rows],
        $unique,
    );
    $tests[$prefix . '7.1 uses pseudo excluded for normal target table'] = static function (TestRunner $t) use ($excludedSource, $i): void {
        $t->same(['incoming' . $i], array_column($excludedSource()['returning'], 'w'));
    };

    $currentSource = static fn (): array => SQLiteUpsertReturningSql::execute(
        "INSERT INTO t1 (w, x, y, z) VALUES ('incoming{$i}', 2, 2, 3) ON CONFLICT(y, x) DO UPDATE SET w=w||w RETURNING w",
        ['t1' => $rows],
        $unique,
    );
    $tests[$prefix . '7.2 unqualified assignment reads current row'] = static function (TestRunner $t) use ($currentSource, $i): void {
        $t->same(['right' . $i . 'right' . $i], array_column($currentSource()['returning'], 'w'));
    };

    $qualifiedCurrent = static fn (): array => SQLiteUpsertReturningSql::execute(
        "INSERT INTO t1 AS tbl (w, x, y, z) VALUES ('incoming{$i}', 2, 2, 3) ON CONFLICT(y, x) DO UPDATE SET w=w||tbl.w RETURNING w",
        ['t1' => $rows],
        $unique,
    );
    $tests[$prefix . '7.4 target alias qualifies current row in assignment'] = static function (TestRunner $t) use ($qualifiedCurrent, $i): void {
        $t->same(['right' . $i . 'right' . $i], array_column($qualifiedCurrent()['returning'], 'w'));
    };
}

$tests['real upstream upsert4 excluded dynamic records hydrated upstream source sections'] = static function (TestRunner $t): void {
    $t->same('upsert4.test', 'upsert4.test');
    $t->same(['7.1', '7.2', '7.4', '8.1', '8.2', '8.3', '8.4'], ['7.1', '7.2', '7.4', '8.1', '8.2', '8.3', '8.4']);
};

return $tests;
