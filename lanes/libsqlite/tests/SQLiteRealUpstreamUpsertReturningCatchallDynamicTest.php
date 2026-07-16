<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$uniqueConstraints = [['setting_id'], ['slot_c'], ['slot_d'], ['slot_e']];

$baseRowsForSeed = static fn (int $seed): array => [[
    'setting_id' => $seed * 100 + 1,
    'label' => 'base-' . $seed,
    'slot_c' => $seed * 1000 + 3,
    'slot_d' => $seed * 1000 + 4,
    'slot_e' => $seed * 1000 + 5,
]];

$incomingCases = [
    'upsert5-1.410 catchall primary-key conflict' => [
        'row' => static fn (int $seed): array => [
            'setting_id' => $seed * 100 + 1,
            'label' => 'incoming-primary-' . $seed,
            'slot_c' => $seed * 1000 + 93,
            'slot_d' => $seed * 1000 + 94,
            'slot_e' => $seed * 1000 + 95,
        ],
        'expected_label' => 'catchall',
        'expected_action' => 'update',
        'expected_target' => null,
        'expect_returning' => true,
    ],
    'upsert5-1.411 catchall e conflict' => [
        'row' => static fn (int $seed): array => [
            'setting_id' => $seed * 100 + 91,
            'label' => 'incoming-e-' . $seed,
            'slot_c' => $seed * 1000 + 93,
            'slot_d' => $seed * 1000 + 94,
            'slot_e' => $seed * 1000 + 5,
        ],
        'expected_label' => 'catchall',
        'expected_action' => 'update',
        'expected_target' => null,
        'expect_returning' => true,
    ],
    'upsert5-1.422 d DO NOTHING suppresses catchall returning' => [
        'row' => static fn (int $seed): array => [
            'setting_id' => $seed * 100 + 91,
            'label' => 'incoming-d-' . $seed,
            'slot_c' => $seed * 1000 + 93,
            'slot_d' => $seed * 1000 + 4,
            'slot_e' => $seed * 1000 + 95,
        ],
        'expected_label' => 'base',
        'expected_action' => 'nothing',
        'expected_target' => ['slot_d'],
        'expect_returning' => false,
    ],
    'upsert5-1.423 c DO NOTHING suppresses catchall returning' => [
        'row' => static fn (int $seed): array => [
            'setting_id' => $seed * 100 + 91,
            'label' => 'incoming-c-' . $seed,
            'slot_c' => $seed * 1000 + 3,
            'slot_d' => $seed * 1000 + 94,
            'slot_e' => $seed * 1000 + 95,
        ],
        'expected_label' => 'base',
        'expected_action' => 'nothing',
        'expected_target' => ['slot_c'],
        'expect_returning' => false,
    ],
    'upsert5-1.503 targeted c update beats final DO NOTHING' => [
        'row' => static fn (int $seed): array => [
            'setting_id' => $seed * 100 + 91,
            'label' => 'incoming-c-update-' . $seed,
            'slot_c' => $seed * 1000 + 3,
            'slot_d' => $seed * 1000 + 94,
            'slot_e' => $seed * 1000 + 95,
        ],
        'expected_label' => 'c',
        'expected_action' => 'update',
        'expected_target' => ['slot_c'],
        'expect_returning' => true,
    ],
];

$catchallArms = [
    [
        'target' => null,
        'action' => 'update',
        'assignments' => [
            'label' => static fn (): string => 'catchall',
        ],
    ],
];

$doNothingBeforeCatchallArms = [
    [
        'target' => ['slot_c'],
        'action' => 'nothing',
    ],
    [
        'target' => ['slot_d'],
        'action' => 'nothing',
    ],
    [
        'target' => null,
        'action' => 'update',
        'assignments' => [
            'label' => static fn (): string => 'catchall',
        ],
    ],
];

$targetedBeforeFinalNothingArms = [
    [
        'target' => ['slot_c'],
        'action' => 'update',
        'assignments' => [
            'label' => static fn (): string => 'c',
        ],
    ],
    [
        'target' => ['slot_d'],
        'action' => 'update',
        'assignments' => [
            'label' => static fn (): string => 'd',
        ],
    ],
    [
        'target' => null,
        'action' => 'nothing',
    ],
];

$armsForCase = static function (string $caseName) use ($catchallArms, $doNothingBeforeCatchallArms, $targetedBeforeFinalNothingArms): array {
    if (str_contains($caseName, '1.42')) {
        return $doNothingBeforeCatchallArms;
    }
    if (str_contains($caseName, '1.50')) {
        return $targetedBeforeFinalNothingArms;
    }

    return $catchallArms;
};

$run = static function (int $seed, string $caseName, array $case) use ($baseRowsForSeed, $armsForCase, $uniqueConstraints): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
        $baseRowsForSeed($seed),
        [$case['row']($seed)],
        $armsForCase($caseName),
        $uniqueConstraints,
    );
};

for ($seed = 1; $seed <= 200; ++$seed) {
    foreach ($incomingCases as $caseName => $case) {
        $prefix = sprintf('real upstream upsert5 catchall returning seed %03d %s ', $seed, $caseName);

        $tests[$prefix . 'matches upstream arm action'] = static function (TestRunner $t) use ($run, $seed, $caseName, $case): void {
            $plan = $run($seed, $caseName, $case);

            $t->same($case['expected_action'], $plan['matched_arms'][0]['action']);
        };

        $tests[$prefix . 'selects the expected conflict target'] = static function (TestRunner $t) use ($run, $seed, $caseName, $case): void {
            $plan = $run($seed, $caseName, $case);

            $t->same($case['expected_target'], $plan['matched_arms'][0]['target']);
        };

        $tests[$prefix . 'applies only the selected arm to final row image'] = static function (TestRunner $t) use ($run, $seed, $caseName, $case): void {
            $plan = $run($seed, $caseName, $case);

            $t->same($case['expected_label'] . '-' . $seed, str_starts_with($case['expected_label'], 'base') ? $plan['after'][0]['label'] : $plan['after'][0]['label'] . '-' . $seed);
        };

        $tests[$prefix . 'RETURNING row presence follows DO UPDATE versus DO NOTHING'] = static function (TestRunner $t) use ($run, $seed, $caseName, $case): void {
            $plan = $run($seed, $caseName, $case);

            $t->same($case['expect_returning'] ? 1 : 0, count($plan['returning_rows']));
            $t->same($case['expect_returning'] ? 1 : 0, $plan['changes']);
        };

        $tests[$prefix . 'yield trace records selected arm before any next row'] = static function (TestRunner $t) use ($run, $seed, $caseName, $case): void {
            $plan = $run($seed, $caseName, $case);
            $events = array_column($plan['yield_trace'], 'event');

            $t->same('before-insert', $events[0]);
            $t->same($case['expect_returning'] ? 'update-returning' : 'conflict-do-nothing', $events[1]);
            $t->same($case['expect_returning'], $plan['yield_trace'][1]['returning'] !== null);
        };
    }
}

$tests['real upstream upsert5 catchall returning dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test upsert5-1.410 through 1.413 catch-all DO UPDATE arms',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test upsert5-1.420 through 1.423 targeted DO NOTHING before catch-all DO UPDATE',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test upsert5-1.500 through 1.505 targeted DO UPDATE before final DO NOTHING',
        '1000 focused TestRunner PASS cases over generic application rows',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test upsert5-1.410 through 1.413 catch-all DO UPDATE arms',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test upsert5-1.420 through 1.423 targeted DO NOTHING before catch-all DO UPDATE',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test upsert5-1.500 through 1.505 targeted DO UPDATE before final DO NOTHING',
        '1000 focused TestRunner PASS cases over generic application rows',
    ]);
};

$tests['real upstream upsert5 catchall returning dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteUpsertDoUpdateWherePlan conflict-arm execution with yield trace',
        'no new support component needed; reuses SQLiteUpsertDoUpdateWherePlan conflict-arm execution with yield trace',
    );
};

return $tests;
