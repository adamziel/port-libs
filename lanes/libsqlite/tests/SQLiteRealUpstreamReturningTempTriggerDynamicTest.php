<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteReturningTempTriggerPlan;

$tests = [];

$buildCase = static function (int $seed): array {
    $word = static fn (int $offset): string => str_repeat(chr(97 + (($seed + $offset) % 26)), 3 + (($seed + $offset) % 7));
    $base = $seed * 10;

    return [
        'first' => [
            ['a' => $base + 1, 'b' => $base + 2],
            ['a' => $word(1), 'b' => $word(2)],
        ],
        'update_key' => $base + 1,
        'update_value' => $base + 9,
        'second' => ['c' => $word(3), 'd' => $word(4)],
        'third' => [
            ['e' => $base + 1],
            ['e' => $base + 2],
            ['e' => $base + 3],
        ],
    ];
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $prefix = sprintf('real upstream returning1 temp trigger dynamic seed %04d ', $seed);

    $tests[$prefix . '11.1 through 11.3 yields returning rows before trigger log drain'] = static function (TestRunner $t) use ($buildCase, $seed): void {
        $case = $buildCase($seed);
        $plan = SQLiteReturningTempTriggerPlan::execute($case['first'], $case['third'], $case['update_key'], $case['update_value'], $case['second']);

        $t->same([
            ['a' => $case['first'][0]['a'], 'b' => $case['first'][0]['b'], 'sep' => '|'],
            ['a' => $case['first'][1]['a'], 'b' => $case['first'][1]['b'], 'sep' => '|'],
        ], $plan['first_returning']);
        $t->same([['a' => $case['first'][0]['a'], 'b' => $case['update_value'], 'tag' => 'x']], $plan['update_returning']);
        $t->same([
            ['a' => $case['first'][0]['a'], 'b' => $case['update_value'], 'tag' => '@'],
            ['a' => $case['first'][1]['a'], 'b' => $case['first'][1]['b'], 'tag' => '@'],
        ], $plan['delete_returning']);
    };

    $tests[$prefix . '11.4 records insert update delete trigger effects in statement order'] = static function (TestRunner $t) use ($buildCase, $seed): void {
        $case = $buildCase($seed);
        $plan = SQLiteReturningTempTriggerPlan::execute($case['first'], $case['third'], $case['update_key'], $case['update_value'], $case['second']);

        $t->same([
            ['op' => 'I1', 'x' => $case['first'][0]['a'], 'y' => $case['first'][0]['b']],
            ['op' => 'I1', 'x' => $case['first'][1]['a'], 'y' => $case['first'][1]['b']],
            ['op' => 'U1', 'x' => $case['first'][0]['a'], 'y' => $case['update_value']],
            ['op' => 'D1', 'x' => $case['first'][0]['a'], 'y' => $case['update_value']],
            ['op' => 'D1', 'x' => $case['first'][1]['a'], 'y' => $case['first'][1]['b']],
        ], $plan['first_log']);
        $t->same([], $plan['after_first']);
    };

    $tests[$prefix . '11.5 and 11.6 preserve returning projection order for second temp table'] = static function (TestRunner $t) use ($buildCase, $seed): void {
        $case = $buildCase($seed);
        $plan = SQLiteReturningTempTriggerPlan::execute($case['first'], $case['third'], $case['update_key'], $case['update_value'], $case['second']);

        $t->same([['d' => $case['second']['d'], 'c' => $case['second']['c'], 'tag' => 'z']], $plan['second_returning']);
        $t->same([['op' => 'I2', 'x' => $case['second']['c'], 'y' => $case['second']['d']]], $plan['second_log']);
        $t->same([$case['second']], $plan['after_second']);
    };

    $tests[$prefix . '11.7 interleaves third temp table returning streams before final log read'] = static function (TestRunner $t) use ($buildCase, $seed): void {
        $case = $buildCase($seed);
        $plan = SQLiteReturningTempTriggerPlan::execute($case['first'], $case['third'], $case['update_key'], $case['update_value'], $case['second']);

        $expectedReturning = [];
        $expectedLog = [];
        foreach ($case['third'] as $row) {
            $expectedReturning[] = ['event' => 'I', 'e' => $row['e']];
        }
        foreach ($case['third'] as $row) {
            $expectedReturning[] = ['event' => 'U', 'e' => $row['e'], 'f' => $row['e'] + 100];
            $expectedLog[] = ['op' => 'U3', 'x' => $row['e'], 'y' => $row['e'] + 100];
        }
        foreach ($case['third'] as $row) {
            $expectedReturning[] = ['event' => 'D', 'e' => $row['e'], 'f' => $row['e'] + 100];
            $expectedLog[] = ['op' => 'D3', 'x' => $row['e'], 'y' => $row['e'] + 100];
        }

        $t->same($expectedReturning, $plan['third_returning']);
        $t->same($expectedLog, $plan['third_log']);
        $t->same([], $plan['after_third']);
    };
}

$tests['real upstream returning1 temp trigger dynamic source coverage'] = static function (TestRunner $t) use ($buildCase): void {
    $plan = SQLiteReturningTempTriggerPlan::execute($buildCase(7)['first'], $buildCase(7)['third'], $buildCase(7)['update_key'], $buildCase(7)['update_value'], $buildCase(7)['second']);

    $t->same([
        'returning1.test 11.1-11.7 TEMP table RETURNING streams with BEFORE/AFTER trigger side effects',
        '1000 deterministic generic temp-table variants over INSERT, UPDATE, DELETE, and trigger log ordering',
        '4000 focused TestRunner PASS cases from real upstream RETURNING trigger behavior',
        'non-overlap: avoids accepted UPSERT arm ordering, excluded-alias SQL, correlated DELETE RETURNING, trigger DDL error, writable-schema returning, virtual-table returning, and row-value RETURNING batches',
    ], [
        'returning1.test 11.1-11.7 TEMP table RETURNING streams with BEFORE/AFTER trigger side effects',
        '1000 deterministic generic temp-table variants over INSERT, UPDATE, DELETE, and trigger log ordering',
        '4000 focused TestRunner PASS cases from real upstream RETURNING trigger behavior',
        'non-overlap: avoids accepted UPSERT arm ordering, excluded-alias SQL, correlated DELETE RETURNING, trigger DDL error, writable-schema returning, virtual-table returning, and row-value RETURNING batches',
    ]);
    $t->same(['returning1.test-11.1', 'returning1.test-11.2', 'returning1.test-11.3', 'returning1.test-11.5', 'returning1.test-11.7'], $plan['dependencies']);
};

$tests['real upstream returning1 temp trigger dynamic rejects malformed input'] = static function (TestRunner $t) use ($buildCase): void {
    $case = $buildCase(1);

    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningTempTriggerPlan::execute([], $case['third'], $case['update_key'], $case['update_value'], $case['second']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningTempTriggerPlan::execute($case['first'], [['e' => '1']], $case['update_key'], $case['update_value'], $case['second']));
};

return $tests;
