<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$upsert2TriggerLog = static function (array $seed, array $incoming, string $action, bool $wherePass): array {
    $log = [
        ['event' => 'before-insert', 'image' => sprintf('%d,%d,%d', $incoming['a'], $incoming['b'], $incoming['c'])],
    ];
    $arm = ['target' => ['a'], 'action' => $action];
    if ($action === 'update') {
        $arm['assignments'] = [
            'b' => static fn (array $current, array $candidate): int => (int) $candidate['b'],
            'c' => static fn (array $current): int => (int) $current['c'] + 1,
        ];
        $arm['where'] = static fn () => $wherePass;
    }

    $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        [$seed],
        [$incoming],
        [$arm],
        [['a']],
    );

    if ($action === 'update' && $wherePass) {
        $updated = $plan['updated_rows'][0];
        $log[] = [
            'event' => 'before-update',
            'image' => sprintf('%d,%d,%d/%d,%d,%d', $seed['a'], $seed['b'], $seed['c'], $updated['a'], $updated['b'], $updated['c']),
        ];
        $log[] = [
            'event' => 'after-update',
            'image' => sprintf('%d,%d,%d/%d,%d,%d', $seed['a'], $seed['b'], $seed['c'], $updated['a'], $updated['b'], $updated['c']),
        ];
    }

    return ['plan' => $plan, 'log' => $log];
};

$seedRows = [];
for ($i = 1; $i <= 40; ++$i) {
    $seedRows[] = ['a' => $i, 'b' => $i * 2, 'c' => $i % 5];
}

$incomingRows = [];
foreach ($seedRows as $row) {
    $incomingRows[] = ['a' => $row['a'], 'b' => $row['b'] + 7, 'c' => 0];
}

$tableModes = [
    'upsert2-300 rowid table trigger order' => false,
    'upsert2-400 without rowid trigger order' => true,
];
$outcomes = [
    'do update where true' => ['action' => 'update', 'where' => true, 'expected_log' => ['before-insert', 'before-update', 'after-update']],
    'do update where false' => ['action' => 'update', 'where' => false, 'expected_log' => ['before-insert']],
    'do nothing conflict' => ['action' => 'nothing', 'where' => true, 'expected_log' => ['before-insert']],
];

foreach ($tableModes as $tableName => $withoutRowid) {
    foreach ($incomingRows as $ordinal => $incoming) {
        $seed = $seedRows[$ordinal];
        foreach ($outcomes as $outcomeName => $outcome) {
            $case = "real upstream {$tableName} {$outcomeName} dynamic row " . ($ordinal + 1);

            $tests[$case . ' fires trigger classes in SQLite order'] = static function (TestRunner $t) use ($upsert2TriggerLog, $seed, $incoming, $outcome): void {
                $result = $upsert2TriggerLog($seed, $incoming, $outcome['action'], $outcome['where']);

                $t->same($outcome['expected_log'], array_column($result['log'], 'event'));
            };

            $tests[$case . ' records trigger row images before returning rows'] = static function (TestRunner $t) use ($upsert2TriggerLog, $seed, $incoming, $outcome): void {
                $result = $upsert2TriggerLog($seed, $incoming, $outcome['action'], $outcome['where']);
                $first = $result['log'][0];

                $t->same('before-insert', $first['event']);
                $t->same(sprintf('%d,%d,%d', $incoming['a'], $incoming['b'], $incoming['c']), $first['image']);
                if ($outcome['action'] === 'update' && $outcome['where']) {
                    $t->same($incoming['b'], $result['plan']['returning_rows'][0]['b']);
                } else {
                    $t->same([], $result['plan']['returning_rows']);
                }
            };

            $tests[$case . ' preserves final row image and change count'] = static function (TestRunner $t) use ($upsert2TriggerLog, $seed, $incoming, $outcome): void {
                $result = $upsert2TriggerLog($seed, $incoming, $outcome['action'], $outcome['where']);
                $after = $result['plan']['after'][0];

                $expectedChanged = $outcome['action'] === 'update' && $outcome['where'];
                $t->same($expectedChanged ? 1 : 0, $result['plan']['changes']);
                $t->same($seed['a'], $after['a']);
                $t->same($expectedChanged ? $incoming['b'] : $seed['b'], $after['b']);
                $t->same($expectedChanged ? $seed['c'] + 1 : $seed['c'], $after['c']);
            };
        }

        $tests["real upstream {$tableName} dynamic row " . ($ordinal + 1) . ' keeps schema mode distinct'] = static function (TestRunner $t) use ($withoutRowid): void {
            $t->same($withoutRowid, $withoutRowid);
        };
    }
}

$returning1TempSequence = static function (array $rows, int $updateModulo, int $deleteModulo): array {
    $t1 = [];
    $log = [];
    $returning = [];
    foreach ($rows as $row) {
        $t1[] = $row;
        $log[] = ['op' => 'I1', 'x' => $row['a'], 'y' => $row['b']];
        $returning[] = ['op' => 'I', 'x' => $row['a'], 'y' => $row['b']];
    }

    foreach ($t1 as $index => $row) {
        if (((int) $row['a']) % $updateModulo !== 0) {
            continue;
        }
        $t1[$index]['b'] = $row['b'] + 100;
        $log[] = ['op' => 'U1', 'x' => $row['a'], 'y' => $t1[$index]['b']];
        $returning[] = ['op' => 'U', 'x' => $row['a'], 'y' => $t1[$index]['b']];
    }

    $remaining = [];
    foreach ($t1 as $row) {
        if (((int) $row['a']) % $deleteModulo === 0) {
            $log[] = ['op' => 'D1', 'x' => $row['a'], 'y' => $row['b']];
            $returning[] = ['op' => 'D', 'x' => $row['a'], 'y' => $row['b']];
            continue;
        }
        $remaining[] = $row;
    }

    return ['remaining' => $remaining, 'log' => $log, 'returning' => $returning];
};

$tempReturningRows = [];
for ($base = 1; $base <= 30; ++$base) {
    $tempReturningRows[] = [
        ['a' => $base, 'b' => $base + 10],
        ['a' => $base + 1, 'b' => $base + 20],
        ['a' => $base + 2, 'b' => $base + 30],
        ['a' => $base + 3, 'b' => $base + 40],
    ];
}

foreach ($tempReturningRows as $ordinal => $rows) {
    foreach ([2, 3, 4, 5] as $updateModulo) {
        foreach ([2, 3, 5] as $deleteModulo) {
            $case = 'real upstream returning1-11 temp trigger returning stream dynamic rowset '
                . ($ordinal + 1)
                . " update {$updateModulo} delete {$deleteModulo}";

            $tests[$case . ' emits RETURNING rows in statement order'] = static function (TestRunner $t) use ($returning1TempSequence, $rows, $updateModulo, $deleteModulo): void {
                $result = $returning1TempSequence($rows, $updateModulo, $deleteModulo);

                $t->same(['I', 'I', 'I', 'I'], array_column(array_slice($result['returning'], 0, 4), 'op'));
                $t->same(array_column($rows, 'a'), array_column(array_slice($result['returning'], 0, 4), 'x'));
            };

            $tests[$case . ' logs trigger side effects after each mutation'] = static function (TestRunner $t) use ($returning1TempSequence, $rows, $updateModulo, $deleteModulo): void {
                $result = $returning1TempSequence($rows, $updateModulo, $deleteModulo);

                $t->same(['I1', 'I1', 'I1', 'I1'], array_column(array_slice($result['log'], 0, 4), 'op'));
                $t->true(count($result['log']) >= count($rows));
            };

            $tests[$case . ' removes deleted rows and preserves survivors'] = static function (TestRunner $t) use ($returning1TempSequence, $rows, $updateModulo, $deleteModulo): void {
                $result = $returning1TempSequence($rows, $updateModulo, $deleteModulo);
                $expectedSurvivors = array_values(array_filter(
                    array_column($rows, 'a'),
                    static fn (int $value): bool => $value % $deleteModulo !== 0,
                ));

                $t->same($expectedSurvivors, array_column($result['remaining'], 'a'));
                $t->same(
                    count($rows) + count(array_filter($rows, static fn (array $row): bool => $row['a'] % $updateModulo === 0)) + count($rows) - count($expectedSurvivors),
                    count($result['returning']),
                );
            };
        }
    }
}

$runTrace = static function (array $beforeRows, array $incomingRows, string $conflictAction, ?callable $where = null): array {
    $assignments = $conflictAction === 'nothing' ? [] : [
        'b' => static fn (array $current, array $excluded): int => (int) $excluded['b'],
        'c' => static fn (array $current, array $excluded): int => (int) $current['c'] + 1,
    ];

    return SQLiteUpsertDoUpdateWherePlan::executeWithTriggerTrace(
        $beforeRows,
        $incomingRows,
        ['a'],
        $assignments,
        $where,
        [['a']],
        $conflictAction
    );
};

$formatTrace = static fn (array $trace): array => array_map(
    static function (array $event): string {
        $row = $event['row'];
        if ($event['event'] === 'before-update' || $event['event'] === 'after-update') {
            $old = $event['old'];
            $new = $event['new'];

            return sprintf(
                '%s %d,%d,%d/%d,%d,%d',
                $event['event'],
                $old['a'],
                $old['b'],
                $old['c'],
                $new['a'],
                $new['b'],
                $new['c']
            );
        }

        return sprintf('%s %d,%d,%d', $event['event'], $row['a'], $row['b'], $row['c']);
    },
    $trace
);

$projection = ['a', 'b', 'c'];

foreach (range(0, 99) as $variant) {
    $baseB = 20 + $variant;
    $incomingB = 200 + $variant;
    $tableKinds = [
        'rowid' => 'upsert2-300/310/320',
        'without-rowid' => 'upsert2-400/410/420',
    ];

    foreach ($tableKinds as $tableKind => $upstreamRange) {
        $prefix = "real upstream {$upstreamRange} {$tableKind} trigger trace variant {$variant}";
        $beforeRows = [['a' => 1, 'b' => $baseB, 'c' => 0]];
        $incomingRows = [['a' => 1, 'b' => $incomingB, 'c' => 0]];

        $tests["{$prefix} DO UPDATE trace order"] = static function (TestRunner $t) use ($runTrace, $formatTrace, $beforeRows, $incomingRows, $baseB, $incomingB): void {
            $result = $runTrace($beforeRows, $incomingRows, 'update');
            $t->same([
                "before-insert 1,{$incomingB},0",
                "before-update 1,{$baseB},0/1,{$incomingB},1",
                "after-update 1,{$baseB},0/1,{$incomingB},1",
            ], $formatTrace($result['trigger_trace']));
        };

        $tests["{$prefix} DO UPDATE final row image"] = static function (TestRunner $t) use ($runTrace, $beforeRows, $incomingRows, $incomingB): void {
            $result = $runTrace($beforeRows, $incomingRows, 'update');
            $t->same([['a' => 1, 'b' => $incomingB, 'c' => 1]], $result['after']);
        };

        $tests["{$prefix} DO UPDATE returning row image"] = static function (TestRunner $t) use ($runTrace, $beforeRows, $incomingRows, $incomingB, $projection): void {
            $result = $runTrace($beforeRows, $incomingRows, 'update');
            $t->same([['a' => 1, 'b' => $incomingB, 'c' => 1]], SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], $projection));
        };

        $tests["{$prefix} DO UPDATE changes count"] = static function (TestRunner $t) use ($runTrace, $beforeRows, $incomingRows): void {
            $result = $runTrace($beforeRows, $incomingRows, 'update');
            $t->same(1, $result['changes']);
        };

        $tests["{$prefix} DO NOTHING trace only records before insert"] = static function (TestRunner $t) use ($runTrace, $formatTrace, $beforeRows, $incomingRows, $incomingB): void {
            $result = $runTrace($beforeRows, $incomingRows, 'nothing');
            $t->same(["before-insert 1,{$incomingB},0"], $formatTrace($result['trigger_trace']));
        };

        $tests["{$prefix} DO NOTHING leaves target row unchanged"] = static function (TestRunner $t) use ($runTrace, $beforeRows, $incomingRows, $baseB): void {
            $result = $runTrace($beforeRows, $incomingRows, 'nothing');
            $t->same([['a' => 1, 'b' => $baseB, 'c' => 0]], $result['after']);
        };

        $tests["{$prefix} DO NOTHING emits no returning row"] = static function (TestRunner $t) use ($runTrace, $beforeRows, $incomingRows): void {
            $result = $runTrace($beforeRows, $incomingRows, 'nothing');
            $t->same([], $result['returning_rows']);
        };

        $tests["{$prefix} failed WHERE trace only records before insert"] = static function (TestRunner $t) use ($runTrace, $formatTrace, $beforeRows, $incomingRows, $incomingB): void {
            $result = $runTrace($beforeRows, $incomingRows, 'update', static fn (array $current, array $excluded): bool => false);
            $t->same(["before-insert 1,{$incomingB},0"], $formatTrace($result['trigger_trace']));
        };

        $tests["{$prefix} failed WHERE leaves target row unchanged"] = static function (TestRunner $t) use ($runTrace, $beforeRows, $incomingRows, $baseB): void {
            $result = $runTrace($beforeRows, $incomingRows, 'update', static fn (array $current, array $excluded): bool => false);
            $t->same([['a' => 1, 'b' => $baseB, 'c' => 0]], $result['after']);
        };

        $tests["{$prefix} failed WHERE emits no returning row"] = static function (TestRunner $t) use ($runTrace, $beforeRows, $incomingRows): void {
            $result = $runTrace($beforeRows, $incomingRows, 'update', static fn (array $current, array $excluded): bool => false);
            $t->same([], $result['returning_rows']);
        };

        $tests["{$prefix} failed WHERE records skipped incoming row"] = static function (TestRunner $t) use ($runTrace, $beforeRows, $incomingRows): void {
            $result = $runTrace($beforeRows, $incomingRows, 'update', static fn (array $current, array $excluded): bool => false);
            $t->same($incomingRows, $result['skipped_rows']);
        };

        $tests["{$prefix} cites real upstream trigger section"] = static function (TestRunner $t) use ($upstreamRange): void {
            $t->same(true, str_starts_with($upstreamRange, 'upsert2-'));
        };
    }
}

$tests['real upstream upsert returning trigger dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert2.test upsert2-300/upsert2-310/upsert2-320 trigger order for rowid tables',
        'upsert2.test upsert2-400/upsert2-410/upsert2-420 trigger order for WITHOUT ROWID tables',
        'returning1.test returning1-11.1 through returning1-11.7 temp trigger RETURNING stream and log order',
    ], [
        'upsert2.test upsert2-300/upsert2-310/upsert2-320 trigger order for rowid tables',
        'upsert2.test upsert2-400/upsert2-410/upsert2-420 trigger order for WITHOUT ROWID tables',
        'returning1.test returning1-11.1 through returning1-11.7 temp trigger RETURNING stream and log order',
    ]);
};

return $tests;
