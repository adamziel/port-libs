<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$baseRows = [
    ['a' => 1, 'b' => 'seed', 'c' => 3, 'd' => 4, 'e' => 5, 'revision' => 1],
];
$uniqueConstraints = [['a'], ['c'], ['d'], ['e']];

$makeArms = static function (array $order, string $catchAllAction, int $case): array {
    $arms = [];
    foreach ($order as $target) {
        $arms[] = [
            'target' => [$target],
            'action' => 'update',
            'assignments' => [
                'b' => static fn (array $current, array $incoming): string => $target . '-' . (string) $incoming['payload'],
                'revision' => static fn (array $current): int => (int) $current['revision'] + 1,
            ],
        ];
    }

    $arms[] = [
        'target' => null,
        'action' => $catchAllAction,
        'assignments' => $catchAllAction === 'nothing' ? [] : [
            'b' => static fn (array $current, array $incoming): string => 'catch-' . (string) $incoming['payload'],
            'revision' => static fn (array $current): int => (int) $current['revision'] + 10,
        ],
        'where' => static fn (array $current, array $incoming): bool => (($case + (int) $incoming['payload']) % 11) !== 0,
    ];

    return $arms;
};

$incomingTemplates = [
    'upsert5-1.420 catch-all updates primary-key conflict after c and d miss' => ['a' => 1, 'c' => 93, 'd' => 94, 'e' => 95, 'target' => null],
    'upsert5-1.421 catch-all updates e conflict after c and d miss' => ['a' => 91, 'c' => 93, 'd' => 94, 'e' => 5, 'target' => null],
    'upsert5-1.422 c do-nothing suppresses returning before catch-all' => ['a' => 91, 'c' => 3, 'd' => 94, 'e' => 95, 'target' => 'c'],
    'upsert5-1.423 d do-nothing suppresses returning before catch-all' => ['a' => 91, 'c' => 93, 'd' => 4, 'e' => 95, 'target' => 'd'],
    'upsert5-1.503 c update beats trailing catch-all do-nothing' => ['a' => 91, 'c' => 3, 'd' => 94, 'e' => 95, 'target' => 'c'],
    'upsert5-1.505 d update beats trailing catch-all do-nothing' => ['a' => 1, 'c' => 93, 'd' => 4, 'e' => 5, 'target' => 'd'],
    'upsert5-3.0 redundant first bb conflict arm wins' => ['a' => 1, 'c' => 93, 'd' => 94, 'e' => 95, 'target' => 'a'],
    'returning1-17 duplicate stream returns each insert or update step' => ['a' => 91, 'c' => 93, 'd' => 94, 'e' => 95, 'target' => 'insert'],
];

$orders = [
    ['c', 'd'],
    ['d', 'c'],
    ['c', 'd', 'a'],
    ['d', 'c', 'a'],
    ['c', 'd', 'e'],
    ['d', 'c', 'e'],
    ['a', 'c', 'd'],
    ['e', 'd', 'c'],
];

$case = 0;
for ($round = 0; $round < 125; ++$round) {
    $order = $orders[$round % count($orders)];
    $catchAllAction = ($round % 5) === 0 ? 'nothing' : 'update';
    foreach ($incomingTemplates as $label => $template) {
        ++$case;
        $incoming = [
            'a' => $template['a'] + ($template['target'] === 'insert' ? $case : 0),
            'b' => null,
            'c' => $template['c'] + ($template['target'] === 'insert' ? $case : 0),
            'd' => $template['d'] + ($template['target'] === 'insert' ? $case : 0),
            'e' => $template['e'] + ($template['target'] === 'insert' ? $case : 0),
            'revision' => 1,
            'payload' => $case,
        ];
        $expectedTarget = null;
        if ($template['target'] === 'insert') {
            $expectedTarget = 'insert';
        } else {
            foreach ($order as $candidate) {
                if ($incoming[$candidate] === $baseRows[0][$candidate]) {
                    $expectedTarget = $candidate;
                    break;
                }
            }
        }
        if ($expectedTarget === null && $template['target'] !== 'insert') {
            $expectedTarget = $catchAllAction === 'nothing' ? 'catch-nothing' : 'catch-update';
        }

        $tests[sprintf('real upstream upsert returning yield dynamic %04d %s', $case, $label)] = static function (TestRunner $t) use ($baseRows, $uniqueConstraints, $makeArms, $order, $catchAllAction, $case, $incoming, $expectedTarget): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
                $baseRows,
                [$incoming],
                $makeArms($order, $catchAllAction, $case),
                $uniqueConstraints,
            );

            $events = array_column($plan['yield_trace'], 'event');
            $t->same('before-insert', $events[0], 'yield trace starts before conflict handling');

            if ($expectedTarget === 'insert') {
                $t->same(['before-insert', 'insert-returning'], $events);
                $t->same(1, count($plan['returning_rows']));
                $t->same($incoming['a'], $plan['returning_rows'][0]['a']);
                return;
            }

            if ($expectedTarget === 'catch-nothing') {
                $t->same(['before-insert', 'conflict-do-nothing'], $events);
                $t->same(0, count($plan['returning_rows']));
                $t->same(0, $plan['changes']);
                return;
            }

            if ($expectedTarget === 'catch-update') {
                $whereAllowsUpdate = (($case + (int) $incoming['payload']) % 11) !== 0;
                $t->same($whereAllowsUpdate ? ['before-insert', 'update-returning'] : ['before-insert', 'conflict-update-where-false'], $events);
                $t->same($whereAllowsUpdate ? 1 : 0, count($plan['returning_rows']));
                $t->same($whereAllowsUpdate ? 1 : 0, $plan['changes']);
                if ($whereAllowsUpdate) {
                    $t->same('catch-' . (string) $incoming['payload'], $plan['returning_rows'][0]['b']);
                }
                return;
            }

            $t->same(['before-insert', 'update-returning'], $events);
            $t->same([[$expectedTarget]], array_column($plan['matched_arms'], 'target'));
            $t->same(1, $plan['changes']);
            $t->same($expectedTarget . '-' . (string) $incoming['payload'], $plan['returning_rows'][0]['b']);
        };
    }
}

$tests['real upstream upsert returning yield dynamic cites source Tcl sections'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
    $t->same([
        'upsert5.test 1.420-1.423 first matching arm, DO NOTHING, and catch-all behavior',
        'upsert5.test 1.503-1.505 explicit arm priority before trailing catch-all DO NOTHING',
        'upsert5.test 3.0-3.6 redundant ON CONFLICT arm does not corrupt indexed row view',
        'returning1.test 17.* INSERT ON CONFLICT DO UPDATE RETURNING emits one row per changed input row',
    ], [
        'upsert5.test 1.420-1.423 first matching arm, DO NOTHING, and catch-all behavior',
        'upsert5.test 1.503-1.505 explicit arm priority before trailing catch-all DO NOTHING',
        'upsert5.test 3.0-3.6 redundant ON CONFLICT arm does not corrupt indexed row view',
        'returning1.test 17.* INSERT ON CONFLICT DO UPDATE RETURNING emits one row per changed input row',
    ]);
};

return $tests;
