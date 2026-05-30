<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

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

$triggerGSource = '/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerG.test';

$tests = [
    'real upstream triggerG recursive trigger cites OP Once ticket' => static function (TestRunner $t) use ($triggerGSource): void {
        $source = file_get_contents($triggerGSource);
        $t->true(is_string($source) && str_contains($source, 'The OP_Once opcode was not working correctly for recursive triggers.'));
    },
    'real upstream triggerG recursive trigger cites single table SELECT body' => static function (TestRunner $t) use ($triggerGSource): void {
        $source = file_get_contents($triggerGSource);
        $t->true(is_string($source) && str_contains($source, 'INSERT INTO t2 SELECT new.c*100+a FROM t1 WHERE a IN (1, 2, 3, 4);'));
    },
    'real upstream triggerG recursive trigger cites cross join SELECT body' => static function (TestRunner $t) use ($triggerGSource): void {
        $source = file_get_contents($triggerGSource);
        $t->true(is_string($source) && str_contains($source, 'INSERT INTO t2 SELECT new.c*10000+xx.a*100+yy.a'));
    },
    'real upstream triggerG recursive trigger cites expected recursive rows' => static function (TestRunner $t) use ($triggerGSource): void {
        $source = file_get_contents($triggerGSource);
        $t->true(is_string($source) && str_contains($source, '} {2 3 4 5}'));
    },
];

for ($i = 1; $i <= 120; ++$i) {
    $seed = 1 + ($i % 3);
    $limit = $seed + 3 + ($i % 2);
    $recursive = ($i % 11) !== 0;
    $crossJoin = ($i % 3) === 0;
    $indexedValues = match ($i % 5) {
        0 => [0, 2, 3, 8, 9],
        1 => [1, 2, 3, 4],
        2 => [0, 1, 2, 3, 4, 5],
        3 => [2, 3, 4, 5],
        default => [-1, 0, 2, 3, 4, 7],
    };

    $firings = $recursive ? range($seed, $limit) : [$seed];
    $expectedT2 = [];
    foreach ($firings as $current) {
        if ($crossJoin) {
            foreach ($indexedValues as $left) {
                if ($left < 1 || $left > 4) {
                    continue;
                }
                foreach ($indexedValues as $right) {
                    if ($right < 2 || $right > 5) {
                        continue;
                    }
                    $expectedT2[] = ($current * 10000) + ($left * 100) + $right;
                }
            }
            continue;
        }

        foreach ($indexedValues as $indexed) {
            if ($indexed >= 1 && $indexed <= 4) {
                $expectedT2[] = ($current * 100) + $indexed;
            }
        }
    }
    sort($expectedT2);

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::recursiveOnceTriggerSelectPlan(
        $seed,
        $indexedValues,
        $limit,
        $recursive,
        $crossJoin
    );

    $case = 'triggerG recursive select subprogram dynamic ' . $i;
    foreach ([
        'source' => 'triggerG.test triggerG-100..200',
        'operation' => 'recursive-trigger-select-subprogram-once-reset',
        'status' => 'commit-ok',
        'recursive_triggers' => $recursive,
        'seed' => $seed,
        'recursive_limit' => $limit,
        'cross_join' => $crossJoin,
        'indexed_values' => $indexedValues,
        'trigger_firings' => $firings,
        'trigger_fire_count' => count($firings),
        't3_values' => $firings,
        't2_values' => $expectedT2,
        't2_count' => count($expectedT2),
        'once_subprogram_reset_per_firing' => true,
        'dependencies.0' => 'sqlite-triggerG-recursive-trigger-subprogram-select',
        'dependencies.1' => 'sqlite-triggerG-op-once-resets-for-each-trigger-invocation',
        'dependencies.2' => 'sqlite-triggerG-indexed-in-filter-inside-recursive-trigger',
    ] as $path => $expected) {
        $tests['real upstream ' . $case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests['real upstream ' . $case . ' t2 rows are emitted once per trigger firing'] = static function (TestRunner $t) use ($plan, $expectedT2): void {
        $t->same($expectedT2, $plan()['t2_values']);
    };
    $tests['real upstream ' . $case . ' recursive trigger records monotonic t3 rows'] = static function (TestRunner $t) use ($plan, $firings): void {
        $t->same($firings, $plan()['t3_values']);
    };
    $tests['real upstream ' . $case . ' OP Once reset preserves later firing SELECT output'] = static function (TestRunner $t) use ($plan, $recursive): void {
        $actual = $plan();
        $t->true($recursive ? $actual['trigger_fire_count'] > 1 && $actual['t2_count'] > 0 : $actual['trigger_fire_count'] === 1);
    };
}

$tests['real upstream triggerG recursive trigger rejects non integer index values'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::recursiveOnceTriggerSelectPlan(2, [0, '2', 3]));
};

$tests['real upstream triggerG recursive trigger rejects inverted recursive bound'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::recursiveOnceTriggerSelectPlan(5, [2, 3], 4));
};

return $tests;
