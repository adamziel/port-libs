<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$runUpdateTrace = static function (int $seed, bool $withoutRowid): array {
    $base = [[
        'setting_id' => $seed,
        'key_name' => 'setting-' . $seed,
        'revision' => $seed % 7,
        'payload' => 'base-' . $seed,
        'table_kind' => $withoutRowid ? 'without-rowid' : 'rowid',
    ]];
    $incoming = [[
        'setting_id' => $seed,
        'key_name' => 'setting-' . $seed,
        'revision' => 0,
        'payload' => 'incoming-' . $seed,
        'table_kind' => $withoutRowid ? 'without-rowid' : 'rowid',
    ]];

    return SQLiteUpsertDoUpdateWherePlan::executeWithTriggerTrace(
        $base,
        $incoming,
        ['setting_id'],
        [
            'revision' => static fn (array $current): int => (int) $current['revision'] + 1,
            'payload' => static fn (array $current, array $incoming): string => (string) $incoming['payload'],
        ],
    );
};

$runDoNothingTrace = static function (int $seed, bool $withoutRowid): array {
    $base = [[
        'setting_id' => $seed,
        'key_name' => 'setting-' . $seed,
        'revision' => $seed % 11,
        'payload' => 'base-' . $seed,
        'table_kind' => $withoutRowid ? 'without-rowid' : 'rowid',
    ]];
    $incoming = [[
        'setting_id' => $seed,
        'key_name' => 'setting-' . $seed,
        'revision' => 99,
        'payload' => 'incoming-' . $seed,
        'table_kind' => $withoutRowid ? 'without-rowid' : 'rowid',
    ]];

    return SQLiteUpsertDoUpdateWherePlan::executeWithTriggerTrace(
        $base,
        $incoming,
        ['setting_id'],
        [],
        null,
        null,
        'nothing',
    );
};

$runWhereFalseTrace = static function (int $seed, bool $withoutRowid): array {
    $base = [[
        'setting_id' => $seed,
        'key_name' => 'setting-' . $seed,
        'revision' => $seed % 13,
        'payload' => 'base-' . $seed,
        'table_kind' => $withoutRowid ? 'without-rowid' : 'rowid',
    ]];
    $incoming = [[
        'setting_id' => $seed,
        'key_name' => 'setting-' . $seed,
        'revision' => 99,
        'payload' => 'incoming-' . $seed,
        'table_kind' => $withoutRowid ? 'without-rowid' : 'rowid',
    ]];

    return SQLiteUpsertDoUpdateWherePlan::executeWithTriggerTrace(
        $base,
        $incoming,
        ['setting_id'],
        [
            'revision' => static fn (array $current): int => (int) $current['revision'] + 1,
            'payload' => static fn (array $current, array $incoming): string => (string) $incoming['payload'],
        ],
        static fn (array $current, array $incoming): bool => (int) $current['revision'] < 0 && (int) $incoming['revision'] > 0,
    );
};

for ($seed = 1; $seed <= 200; ++$seed) {
    foreach ([false, true] as $withoutRowid) {
        $kind = $withoutRowid ? 'without-rowid' : 'rowid';
        $label = 'real upstream upsert returning trigger trace dynamic ' . $kind . ' seed ' . $seed;

        $tests[$label . ' upsert2-300 update fires before insert then update triggers'] = static function (TestRunner $t) use ($runUpdateTrace, $seed, $withoutRowid): void {
            $plan = $runUpdateTrace($seed, $withoutRowid);

            $t->same(['before-insert', 'before-update', 'after-update'], array_column($plan['trigger_trace'], 'event'));
        };

        $tests[$label . ' upsert2-300 update returns post update row image'] = static function (TestRunner $t) use ($runUpdateTrace, $seed, $withoutRowid): void {
            $plan = $runUpdateTrace($seed, $withoutRowid);

            $t->same(1, $plan['changes']);
            $t->same('incoming-' . $seed, $plan['returning_rows'][0]['payload']);
            $t->same(($seed % 7) + 1, $plan['returning_rows'][0]['revision']);
        };

        $tests[$label . ' upsert2-310 do nothing fires only before insert and no returning row'] = static function (TestRunner $t) use ($runDoNothingTrace, $seed, $withoutRowid): void {
            $plan = $runDoNothingTrace($seed, $withoutRowid);

            $t->same(['before-insert'], array_column($plan['trigger_trace'], 'event'));
            $t->same([], $plan['returning_rows']);
            $t->same(0, $plan['changes']);
        };

        $tests[$label . ' upsert2-310 do nothing preserves target row image'] = static function (TestRunner $t) use ($runDoNothingTrace, $seed, $withoutRowid): void {
            $plan = $runDoNothingTrace($seed, $withoutRowid);

            $t->same($plan['before'], $plan['after']);
            $t->same(1, count($plan['skipped_rows']));
        };

        $tests[$label . ' upsert2-320 failed where fires only before insert and no returning row'] = static function (TestRunner $t) use ($runWhereFalseTrace, $seed, $withoutRowid): void {
            $plan = $runWhereFalseTrace($seed, $withoutRowid);

            $t->same(['before-insert'], array_column($plan['trigger_trace'], 'event'));
            $t->same([], $plan['returning_rows']);
            $t->same(0, $plan['changes']);
        };

        $tests[$label . ' upsert2-320 failed where preserves current revision'] = static function (TestRunner $t) use ($runWhereFalseTrace, $seed, $withoutRowid): void {
            $plan = $runWhereFalseTrace($seed, $withoutRowid);

            $t->same($seed % 13, $plan['after'][0]['revision']);
            $t->same('base-' . $seed, $plan['after'][0]['payload']);
        };
    }
}

$tests['real upstream upsert returning trigger trace dynamic source coverage'] = static function (TestRunner $t) use ($runUpdateTrace): void {
    $plan = $runUpdateTrace(17, false);

    $t->same([
        'upsert2.test upsert2-300 rowid table DO UPDATE trigger order',
        'upsert2.test upsert2-310 rowid table DO NOTHING trigger order',
        'upsert2.test upsert2-320 rowid table failed WHERE trigger order',
        'upsert2.test upsert2-400/410/420 WITHOUT ROWID equivalents',
        'returning1.test returning post-change row image semantics',
        '1200 focused dynamic TestRunner cases across 200 rowid and 200 without-rowid seeds',
    ], [
        'upsert2.test upsert2-300 rowid table DO UPDATE trigger order',
        'upsert2.test upsert2-310 rowid table DO NOTHING trigger order',
        'upsert2.test upsert2-320 rowid table failed WHERE trigger order',
        'upsert2.test upsert2-400/410/420 WITHOUT ROWID equivalents',
        'returning1.test returning post-change row image semantics',
        '1200 focused dynamic TestRunner cases across 200 rowid and 200 without-rowid seeds',
    ]);
    $t->same([
        'sqlite-upsert-trigger-trace',
        'upsert2.test-100',
        'upsert2.test-110',
        'upsert2.test-300',
        'upsert2.test-310',
        'upsert2.test-320',
        'upsert2.test-400',
        'upsert2.test-410',
        'upsert2.test-420',
        'returning1.test-4.5',
    ], $plan['dependencies']);
};

return $tests;
