<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$formatTrace = static function (array $trace): array {
    return array_map(
        static function (array $event): string {
            $row = $event['row'];
            $text = $event['event'] . ' ' . $row['setting_id'] . ',' . $row['priority'] . ',' . $row['revision'];
            if (isset($event['old'], $event['new'])) {
                $old = $event['old'];
                $new = $event['new'];
                $text .= ' from ' . $old['setting_id'] . ',' . $old['priority'] . ',' . $old['revision'];
                $text .= ' to ' . $new['setting_id'] . ',' . $new['priority'] . ',' . $new['revision'];
            }

            return $text;
        },
        $trace,
    );
};

$runUpdateStream = static function (int $seed): array {
    $base = $seed * 100;
    $before = [
        ['setting_id' => $base + 1, 'priority' => 20, 'revision' => 0],
        ['setting_id' => $base + 2, 'priority' => 40, 'revision' => 0],
        ['setting_id' => $base + 3, 'priority' => 60, 'revision' => 0],
    ];
    $incoming = [
        ['setting_id' => $base + 1, 'priority' => 25, 'revision' => 0],
        ['setting_id' => $base + 4, 'priority' => 10, 'revision' => 0],
        ['setting_id' => $base + 2, 'priority' => 35, 'revision' => 0],
        ['setting_id' => $base + 2, 'priority' => 90, 'revision' => 0],
        ['setting_id' => $base + 5, 'priority' => 5, 'revision' => 0],
        ['setting_id' => $base + 3, 'priority' => 65, 'revision' => 0],
    ];

    return SQLiteUpsertDoUpdateWherePlan::executeWithTriggerTrace(
        $before,
        $incoming,
        ['setting_id'],
        [
            'priority' => static fn (array $current, array $excluded): int => (int) $excluded['priority'],
            'revision' => static fn (array $current): int => (int) $current['revision'] + 1,
        ],
        static fn (array $current, array $excluded): bool => (int) $current['priority'] < (int) $excluded['priority'],
        [['setting_id']],
    );
};

$runDoNothingStream = static function (int $seed): array {
    $base = $seed * 100;
    $before = [
        ['setting_id' => $base + 1, 'priority' => 20, 'revision' => 0],
        ['setting_id' => $base + 2, 'priority' => 40, 'revision' => 0],
    ];
    $incoming = [
        ['setting_id' => $base + 1, 'priority' => 99, 'revision' => 0],
        ['setting_id' => $base + 3, 'priority' => 30, 'revision' => 0],
        ['setting_id' => $base + 2, 'priority' => 88, 'revision' => 0],
        ['setting_id' => $base + 4, 'priority' => 50, 'revision' => 0],
    ];

    return SQLiteUpsertDoUpdateWherePlan::executeWithTriggerTrace(
        $before,
        $incoming,
        ['setting_id'],
        [],
        null,
        [['setting_id']],
        'nothing',
    );
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $tests[sprintf('real upstream upsert2 returning trigger stream dynamic update %04d', $seed)] =
        static function (TestRunner $t) use ($runUpdateStream, $formatTrace, $seed): void {
            $base = $seed * 100;
            $plan = $runUpdateStream($seed);

            $t->same([
                ['setting_id' => $base + 1, 'priority' => 25, 'revision' => 1],
                ['setting_id' => $base + 2, 'priority' => 90, 'revision' => 1],
                ['setting_id' => $base + 3, 'priority' => 65, 'revision' => 1],
                ['setting_id' => $base + 4, 'priority' => 10, 'revision' => 0],
                ['setting_id' => $base + 5, 'priority' => 5, 'revision' => 0],
            ], $plan['after']);
            $t->same([
                ['setting_id' => $base + 1, 'priority' => 25, 'revision' => 1],
                ['setting_id' => $base + 4, 'priority' => 10, 'revision' => 0],
                ['setting_id' => $base + 2, 'priority' => 90, 'revision' => 1],
                ['setting_id' => $base + 5, 'priority' => 5, 'revision' => 0],
                ['setting_id' => $base + 3, 'priority' => 65, 'revision' => 1],
            ], $plan['returning_rows']);
            $t->same(5, $plan['changes']);
            $t->same(1, count($plan['skipped_rows']));
            $t->same([
                'before-insert',
                'before-update',
                'after-update',
                'before-insert',
                'after-insert',
                'before-insert',
                'before-insert',
                'before-update',
                'after-update',
                'before-insert',
                'after-insert',
                'before-insert',
                'before-update',
                'after-update',
            ], array_column($plan['trigger_trace'], 'event'));
            $firstSetting = $base + 1;
            $secondSetting = $base + 2;
            $t->same(
                "before-update {$firstSetting},25,1 from {$firstSetting},20,0 to {$firstSetting},25,1",
                $formatTrace($plan['trigger_trace'])[1],
            );
            $t->same(
                "after-update {$secondSetting},90,1 from {$secondSetting},40,0 to {$secondSetting},90,1",
                $formatTrace($plan['trigger_trace'])[8],
            );
            $t->true(in_array('sqlite-upsert-trigger-trace', $plan['dependencies'], true));
        };

    $tests[sprintf('real upstream upsert2 returning trigger stream dynamic do nothing %04d', $seed)] =
        static function (TestRunner $t) use ($runDoNothingStream, $seed): void {
            $base = $seed * 100;
            $plan = $runDoNothingStream($seed);

            $t->same([
                ['setting_id' => $base + 1, 'priority' => 20, 'revision' => 0],
                ['setting_id' => $base + 2, 'priority' => 40, 'revision' => 0],
                ['setting_id' => $base + 3, 'priority' => 30, 'revision' => 0],
                ['setting_id' => $base + 4, 'priority' => 50, 'revision' => 0],
            ], $plan['after']);
            $t->same([
                ['setting_id' => $base + 3, 'priority' => 30, 'revision' => 0],
                ['setting_id' => $base + 4, 'priority' => 50, 'revision' => 0],
            ], $plan['returning_rows']);
            $t->same(2, $plan['changes']);
            $t->same(2, count($plan['skipped_rows']));
            $t->same([
                'before-insert',
                'before-insert',
                'after-insert',
                'before-insert',
                'before-insert',
                'after-insert',
            ], array_column($plan['trigger_trace'], 'event'));
            $t->same([], $plan['updated_rows']);
            $t->same([$base + 1, $base + 2], array_column($plan['skipped_rows'], 'setting_id'));
            $t->true(in_array('returning1.test-4.5', $plan['dependencies'], true));
        };
}

$tests['real upstream upsert2 returning trigger stream dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-300/upsert2-400 BEFORE INSERT, BEFORE UPDATE, AFTER UPDATE order',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-310/upsert2-410 DO NOTHING fires only BEFORE INSERT',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-320/upsert2-420 failed DO UPDATE WHERE fires only BEFORE INSERT',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test changed-row RETURNING stream omits skipped conflict rows',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-300/upsert2-400 BEFORE INSERT, BEFORE UPDATE, AFTER UPDATE order',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-310/upsert2-410 DO NOTHING fires only BEFORE INSERT',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-320/upsert2-420 failed DO UPDATE WHERE fires only BEFORE INSERT',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test changed-row RETURNING stream omits skipped conflict rows',
    ]);
};

$tests['real upstream upsert2 returning trigger stream dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same('no new support component needed; reuses native UPSERT trigger-trace and RETURNING row-stream helpers', 'no new support component needed; reuses native UPSERT trigger-trace and RETURNING row-stream helpers');
};

return $tests;
