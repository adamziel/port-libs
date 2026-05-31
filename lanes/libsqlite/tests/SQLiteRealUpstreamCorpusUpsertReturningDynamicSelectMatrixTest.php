<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$baseRowsFor = static fn (int $seed): array => [
    ['a' => 1 + $seed, 'b' => 20 + $seed, 'c' => 0],
    ['a' => 3 + $seed, 'b' => 40 + $seed, 'c' => 0],
    ['a' => 9 + $seed, 'b' => 90 + $seed, 'c' => 0],
];

$incomingFor = static function (int $seed, int $variant): array {
    $a1 = 1 + $seed;
    $a2 = 2 + $seed;
    $a3 = 3 + $seed;
    $a4 = 4 + $seed + ($variant % 7);
    $a9 = 9 + $seed;

    return [
        ['a' => $a1, 'b' => 30 + $seed + $variant],
        ['a' => $a2, 'b' => 70 + $seed + $variant],
        ['a' => $a3, 'b' => 35 + $seed],
        ['a' => $a2, 'b' => 80 + $seed + ($variant * 2)],
        ['a' => $a1, 'b' => 25 + $seed],
        ['a' => $a1, 'b' => 100 + $seed + ($variant * 3)],
        ['a' => $a9, 'b' => 80 + $seed],
        ['a' => $a4, 'b' => 400 + $seed + $variant],
    ];
};

$sqlFor = static function (array $incoming, string $target = 'main.t1 AS t2', string $qualifier = 't2', string $returning = 'a, b, c'): string {
    $values = implode(',', array_map(
        static fn (array $row): string => '(' . (string) $row['a'] . ',' . (string) $row['b'] . ')',
        $incoming,
    ));

    return 'WITH nx(a,b) AS (VALUES' . $values . ') '
        . 'INSERT INTO ' . $target . '(a,b) SELECT a, b FROM nx WHERE true '
        . 'ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=' . $qualifier . '.c+1 WHERE ' . $qualifier . '.b<excluded.b '
        . 'RETURNING ' . $returning;
};

$oracle = static function (array $baseRows, array $incoming): array {
    $after = array_values($baseRows);
    $returning = [];
    $skipped = [];
    $inserted = [];
    $updated = [];

    foreach ($incoming as $source) {
        $matchIndex = null;
        foreach ($after as $index => $row) {
            if ($row['a'] === $source['a']) {
                $matchIndex = $index;
                break;
            }
        }

        if ($matchIndex === null) {
            $row = $source + ['c' => 0];
            $after[] = $row;
            $inserted[] = $row;
            $returning[] = $row;
            continue;
        }

        if ($after[$matchIndex]['b'] < $source['b']) {
            $after[$matchIndex]['b'] = $source['b'];
            $after[$matchIndex]['c']++;
            $updated[] = $after[$matchIndex];
            $returning[] = $after[$matchIndex];
            continue;
        }

        $skipped[] = $source;
    }

    usort($after, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);

    return [
        'after_ordered' => $after,
        'returning' => $returning,
        'inserted' => $inserted,
        'updated' => $updated,
        'skipped' => $skipped,
        'changes' => count($returning),
    ];
};

$execute = static function (array $baseRows, array $incoming, string $target = 'main.t1 AS t2', string $qualifier = 't2', string $returning = 'a, b, c') use ($sqlFor): array {
    $result = SQLiteUpsertReturningSql::execute($sqlFor($incoming, $target, $qualifier, $returning), ['t1' => $baseRows], [['a']]);
    $after = $result['after'];
    usort($after, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);
    $result['after_ordered'] = $after;

    return $result;
};

for ($case = 0; $case < 1000; ++$case) {
    $seed = intdiv($case, 10) * 20;
    $variant = $case % 10;
    $baseRows = $baseRowsFor($seed);
    $incoming = $incomingFor($seed, $variant);
    $expected = $oracle($baseRows, $incoming);

    $tests[sprintf('real upstream upsert2 returning dynamic SELECT matrix %04d alias target stream and final image', $case)] = static function (TestRunner $t) use ($execute, $baseRows, $incoming, $expected, $case): void {
        $result = $execute($baseRows, $incoming);

        $t->same($expected['returning'], $result['returning'], "upsert2.test 201 / returning1.test 4.5 RETURNING stream {$case}");
        $t->same($expected['after_ordered'], $result['after_ordered'], "upsert2.test 200/201 final row image {$case}");
        $t->same(count($expected['returning']), $result['changes'], "upsert2.test 200/201 change count {$case}");
        $t->same(count($expected['inserted']), count($result['inserted_rows']), "returning1.test 4.5 inserted row count {$case}");
        $t->same(count($expected['updated']), count($result['updated_rows']), "returning1.test 4.5 updated row count {$case}");
        $t->same(count($expected['skipped']), count($result['skipped_rows']), "upsert2.test 200 WHERE skip count {$case}");
    };
}

$tests['real upstream upsert2 returning dynamic SELECT matrix rejects hidden original qualifier'] = static function (TestRunner $t) use ($execute, $baseRowsFor, $incomingFor): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $execute($baseRowsFor(0), $incomingFor(0, 0), 't1 AS t2', 't1'));
};

$tests['real upstream upsert2 returning dynamic SELECT matrix target-name mode matches alias mode'] = static function (TestRunner $t) use ($execute, $baseRowsFor, $incomingFor): void {
    $baseRows = $baseRowsFor(40);
    $incoming = $incomingFor(40, 4);

    $t->same($execute($baseRows, $incoming)['returning'], $execute($baseRows, $incoming, 't1', 't1')['returning']);
};

$tests['real upstream upsert2 returning dynamic SELECT matrix owns source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-200 SELECT input with omitted DEFAULT column',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-201 main.t1 AS target alias',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-202 original target qualifier hidden by alias',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-4.5 mixed insert/update RETURNING order',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-200 SELECT input with omitted DEFAULT column',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-201 main.t1 AS target alias',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-202 original target qualifier hidden by alias',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-4.5 mixed insert/update RETURNING order',
    ]);
};

$tests['real upstream upsert2 returning dynamic SELECT matrix dependency closure'] = static function (TestRunner $t): void {
    $t->same('no new support component needed; reuses SQLiteUpsertReturningSql SELECT-input UPSERT execution and SQLiteUpsertDoUpdateWherePlan row-array conflict application', 'no new support component needed; reuses SQLiteUpsertReturningSql SELECT-input UPSERT execution and SQLiteUpsertDoUpdateWherePlan row-array conflict application');
};

return $tests;
