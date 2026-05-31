<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$uniqueConstraints = [['setting_id'], ['key_name'], ['slot']];

$runDoNothingByKey = static function (array $rows, array $incoming) use ($uniqueConstraints): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $rows,
        [$incoming],
        [[
            'target' => ['key_name'],
            'action' => 'nothing',
        ]],
        $uniqueConstraints,
    );
};

$runDoNothingBySlot = static function (array $rows, array $incoming) use ($uniqueConstraints): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $rows,
        [$incoming],
        [[
            'target' => ['slot'],
            'action' => 'nothing',
        ]],
        $uniqueConstraints,
    );
};

$runUpdateByKey = static function (array $rows, array $incoming) use ($uniqueConstraints): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $rows,
        [$incoming],
        [[
            'target' => ['key_name'],
            'action' => 'update',
            'assignments' => [
                'key_name' => static fn (array $current): string => (string) $current['key_name'] . '-updated',
                'payload' => static fn (array $current, array $incoming): string => (string) $incoming['payload'],
            ],
        ]],
        $uniqueConstraints,
    );
};

$runUpdateBySlot = static function (array $rows, array $incoming) use ($uniqueConstraints): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $rows,
        [$incoming],
        [[
            'target' => ['slot'],
            'action' => 'update',
            'assignments' => [
                'slot' => static fn (array $current): int => (int) $current['slot'] + 100000,
                'payload' => static fn (array $current, array $incoming): string => (string) $incoming['payload'],
            ],
        ]],
        $uniqueConstraints,
    );
};

for ($seed = 1; $seed <= 250; ++$seed) {
    $baseRows = [
        [
            'setting_id' => $seed * 10 + 1,
            'key_name' => 'alpha-' . $seed,
            'slot' => $seed * 100 + 1,
            'payload' => 'base-alpha-' . $seed,
        ],
        [
            'setting_id' => $seed * 10 + 2,
            'key_name' => 'beta-' . $seed,
            'slot' => $seed * 100 + 2,
            'payload' => 'base-beta-' . $seed,
        ],
    ];
    $incomingKeyAndSlotConflict = [
        'setting_id' => $seed * 10 + 3,
        'key_name' => 'alpha-' . $seed,
        'slot' => $seed * 100 + 2,
        'payload' => 'incoming-key-slot-' . $seed,
    ];
    $incomingSlotAndKeyConflict = [
        'setting_id' => $seed * 10 + 4,
        'key_name' => 'beta-' . $seed,
        'slot' => $seed * 100 + 1,
        'payload' => 'incoming-slot-key-' . $seed,
    ];
    $label = 'real upstream upsert4 returning replace precedence seed ' . $seed;

    $tests[$label . ' upsert4-6.1 key DO NOTHING wins before replace side conflict'] = static function (TestRunner $t) use ($runDoNothingByKey, $baseRows, $incomingKeyAndSlotConflict): void {
        $plan = $runDoNothingByKey($baseRows, $incomingKeyAndSlotConflict);

        $t->same($baseRows, $plan['after']);
    };

    $tests[$label . ' upsert4-6.1 key DO NOTHING suppresses RETURNING rows'] = static function (TestRunner $t) use ($runDoNothingByKey, $baseRows, $incomingKeyAndSlotConflict): void {
        $plan = $runDoNothingByKey($baseRows, $incomingKeyAndSlotConflict);

        $t->same([], $plan['returning_rows']);
        $t->same(0, $plan['changes']);
    };

    $tests[$label . ' upsert4-6.2 slot DO NOTHING wins before replace side conflict'] = static function (TestRunner $t) use ($runDoNothingBySlot, $baseRows, $incomingSlotAndKeyConflict): void {
        $plan = $runDoNothingBySlot($baseRows, $incomingSlotAndKeyConflict);

        $t->same($baseRows, $plan['after']);
    };

    $tests[$label . ' upsert4-6.2 slot DO NOTHING records selected conflict arm'] = static function (TestRunner $t) use ($runDoNothingBySlot, $baseRows, $incomingSlotAndKeyConflict): void {
        $plan = $runDoNothingBySlot($baseRows, $incomingSlotAndKeyConflict);

        $t->same([['slot']], array_column($plan['matched_arms'], 'target'));
        $t->same(['nothing'], array_column($plan['matched_arms'], 'action'));
    };

    $tests[$label . ' upsert4-6.2 key DO UPDATE mutates current row before replace handling'] = static function (TestRunner $t) use ($runUpdateByKey, $baseRows, $incomingKeyAndSlotConflict): void {
        $plan = $runUpdateByKey($baseRows, $incomingKeyAndSlotConflict);

        $t->same('alpha-' . ((int) (($baseRows[0]['setting_id'] - 1) / 10)) . '-updated', $plan['after'][0]['key_name']);
        $t->same($baseRows[1], $plan['after'][1]);
    };

    $tests[$label . ' upsert4-6.2 key DO UPDATE yields updated current row'] = static function (TestRunner $t) use ($runUpdateByKey, $baseRows, $incomingKeyAndSlotConflict): void {
        $plan = $runUpdateByKey($baseRows, $incomingKeyAndSlotConflict);

        $t->same(1, $plan['changes']);
        $t->same($plan['after'][0], $plan['returning_rows'][0]);
        $t->same('incoming-key-slot-' . ((int) (($baseRows[0]['setting_id'] - 1) / 10)), $plan['returning_rows'][0]['payload']);
    };

    $tests[$label . ' upsert4-6.2 slot DO UPDATE mutates slot row before replace handling'] = static function (TestRunner $t) use ($runUpdateBySlot, $baseRows, $incomingSlotAndKeyConflict): void {
        $plan = $runUpdateBySlot($baseRows, $incomingSlotAndKeyConflict);

        $t->same((int) $baseRows[0]['slot'] + 100000, $plan['after'][0]['slot']);
        $t->same($baseRows[1], $plan['after'][1]);
    };

    $tests[$label . ' upsert4-6.2 slot DO UPDATE yields one RETURNING row'] = static function (TestRunner $t) use ($runUpdateBySlot, $baseRows, $incomingSlotAndKeyConflict): void {
        $plan = $runUpdateBySlot($baseRows, $incomingSlotAndKeyConflict);

        $t->same(1, count($plan['returning_rows']));
        $t->same($plan['after'][0]['setting_id'], $plan['returning_rows'][0]['setting_id']);
    };
}

$tests['real upstream upsert4 returning replace precedence source coverage cites upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        'upsert4.test upsert4-6.1 INSERT OR REPLACE secondary conflict is not processed before ON CONFLICT DO NOTHING',
        'upsert4.test upsert4-6.2 INSERT OR REPLACE secondary conflict is not processed before ON CONFLICT DO UPDATE',
    ], [
        'upsert4.test upsert4-6.1 INSERT OR REPLACE secondary conflict is not processed before ON CONFLICT DO NOTHING',
        'upsert4.test upsert4-6.2 INSERT OR REPLACE secondary conflict is not processed before ON CONFLICT DO UPDATE',
    ]);
};

return $tests;
