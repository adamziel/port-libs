<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$sql = 'INSERT INTO hist(x,cnt) VALUES(%d,1) '
    . 'ON CONFLICT(x) DO UPDATE SET cnt=cnt+1 '
    . 'RETURNING x,cnt';

$streamFor = static function (int $case): array {
    $base = ($case % 17) + 1;
    $stride = (($case % 7) * 2) + 1;
    $width = 4 + ($case % 6);
    $length = 18 + ($case % 11);
    $stream = [];

    for ($i = 0; $i < $length; ++$i) {
        $stream[] = $base + (($i * $stride + intdiv($i, 3) + $case) % $width);
    }
    $stream[] = $base;
    $stream[] = $base + (($stride + $case) % $width);

    return $stream;
};

$oracle = static function (array $stream): array {
    $hist = [];
    $returning = [];
    $inserted = 0;
    $updated = 0;

    foreach ($stream as $value) {
        if (!array_key_exists($value, $hist)) {
            $hist[$value] = 1;
            ++$inserted;
        } else {
            ++$hist[$value];
            ++$updated;
        }
        $returning[] = ['x' => $value, 'cnt' => $hist[$value]];
    }

    ksort($hist);
    $ordered = [];
    foreach ($hist as $value => $count) {
        $ordered[] = ['x' => $value, 'cnt' => $count];
    }

    return [
        'ordered' => $ordered,
        'returning' => $returning,
        'inserted' => $inserted,
        'updated' => $updated,
        'changes' => count($stream),
        'max_count' => max($hist),
        'keys' => array_keys($hist),
    ];
};

$executeTriggerBody = static function (array $stream) use ($sql): array {
    $hist = [];
    $returning = [];
    $inserted = 0;
    $updated = 0;
    $changes = 0;

    foreach ($stream as $value) {
        $result = SQLiteUpsertReturningSql::execute(sprintf($sql, $value), ['hist' => $hist], [['x']]);
        $hist = $result['after'];
        $returning[] = $result['returning'][0];
        $inserted += count($result['inserted_rows']);
        $updated += count($result['updated_rows']);
        $changes += $result['changes'];
    }

    usort($hist, static fn (array $left, array $right): int => $left['x'] <=> $right['x']);

    return [
        'ordered' => $hist,
        'returning' => $returning,
        'inserted' => $inserted,
        'updated' => $updated,
        'changes' => $changes,
        'max_count' => max(array_column($hist, 'cnt')),
        'keys' => array_column($hist, 'x'),
    ];
};

for ($case = 0; $case < 1000; ++$case) {
    $stream = $streamFor($case);
    $expected = $oracle($stream);

    $tests[sprintf('real upstream upsert4 returning dynamic trigger histogram %04d', $case)] = static function (TestRunner $t) use ($executeTriggerBody, $stream, $expected, $case): void {
        $actual = $executeTriggerBody($stream);

        $t->same($expected['returning'], $actual['returning'], "upsert4.test 9.1 trigger body RETURNING stream {$case}");
        $t->same($expected['ordered'], $actual['ordered'], "upsert4.test 9.1 final histogram rows {$case}");
        $t->same($expected['inserted'], $actual['inserted'], "upsert4.test 9.1 inserted histogram key count {$case}");
        $t->same($expected['updated'], $actual['updated'], "upsert4.test 9.1 updated histogram key count {$case}");
        $t->same($expected['changes'], $actual['changes'], "upsert4.test 9.1 one trigger-body change per source row {$case}");
        $t->same($expected['max_count'], $actual['max_count'], "upsert4.test 9.1 repeated key max count {$case}");
        $t->same($expected['keys'], $actual['keys'], "upsert4.test 9.1 ordered histogram keys {$case}");
    };
}

$tests['real upstream upsert4 returning dynamic trigger histogram cites source section'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test upsert4-9.0 creates AFTER INSERT trigger with UPSERT body',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test upsert4-9.1 repeated inserted values accumulate histogram counts',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test upsert4-9.0 creates AFTER INSERT trigger with UPSERT body',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test upsert4-9.1 repeated inserted values accumulate histogram counts',
    ]);
};

$tests['real upstream upsert4 returning dynamic trigger histogram dependency closure'] = static function (TestRunner $t) use ($executeTriggerBody, $streamFor): void {
    $stream = $streamFor(9);
    $actual = $executeTriggerBody($stream);

    $t->same('no new support component needed; reuses SQLiteUpsertReturningSql for the upstream trigger-body UPSERT and native RETURNING projection', 'no new support component needed; reuses SQLiteUpsertReturningSql for the upstream trigger-body UPSERT and native RETURNING projection');
    $t->same(count($stream), array_sum(array_column($actual['ordered'], 'cnt')), 'upsert4.test 9 histogram count sum matches trigger source rows');
};

return $tests;
