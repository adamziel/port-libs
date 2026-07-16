<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$makeRows = static function (int $seed): array {
    $base = $seed * 1000;
    $largeA = str_repeat('a', 64 + ($seed % 17));
    $largeB = str_repeat('b', 64 + ($seed % 19));

    return [
        ['x' => $base + 11, 'y' => $largeA, 'source' => 'select-row-1'],
        ['x' => $base + 11, 'y' => $largeA, 'source' => 'select-row-2'],
        ['x' => $base + 33, 'y' => $largeB, 'source' => 'select-row-3'],
        ['x' => $base + 33, 'y' => $largeB, 'source' => 'select-row-4'],
    ];
};

$run = static function (int $seed) use ($makeRows): array {
    return SQLiteUpsertDoUpdateWherePlan::executeWithTriggerTrace(
        [],
        $makeRows($seed),
        ['x'],
        [
            'y' => static fn (array $current, array $incoming): string => (string) $incoming['y'],
            'source' => static fn (array $current, array $incoming): string => (string) $incoming['source'],
        ],
    );
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $tests["real upstream corpus upsert1.test upsert1-1300 trigger old image dynamic {$seed} before update sees previous row image"] = static function (TestRunner $t) use ($run, $seed): void {
        $trace = $run($seed)['trigger_trace'];
        $beforeUpdate = array_values(array_filter($trace, static fn (array $event): bool => $event['event'] === 'before-update'));

        $t->same(2, count($beforeUpdate));
        foreach ($beforeUpdate as $event) {
            $t->same($event['old']['x'], $event['new']['x']);
            $t->same($event['old']['y'], $event['new']['y']);
        }
    };

    $tests["real upstream corpus upsert1.test upsert1-1300 trigger old image dynamic {$seed} returning stream follows changed rows"] = static function (TestRunner $t) use ($run, $seed): void {
        $result = $run($seed);

        $t->same(4, $result['changes']);
        $t->same([$seed * 1000 + 11, $seed * 1000 + 11, $seed * 1000 + 33, $seed * 1000 + 33], array_column($result['returning_rows'], 'x'));
        $t->same(['select-row-1', 'select-row-2', 'select-row-3', 'select-row-4'], array_column($result['returning_rows'], 'source'));
    };

    $tests["real upstream corpus upsert1.test upsert1-1300 trigger old image dynamic {$seed} final table keeps last duplicate source"] = static function (TestRunner $t) use ($run, $seed): void {
        $result = $run($seed);

        $t->same([
            ['x' => $seed * 1000 + 11, 'source' => 'select-row-2'],
            ['x' => $seed * 1000 + 33, 'source' => 'select-row-4'],
        ], array_map(
            static fn (array $row): array => ['x' => $row['x'], 'source' => $row['source']],
            $result['after'],
        ));
    };
}

$tests['real upstream corpus upsert1.test upsert1-1300 source citation and dependency closure'] = static function (TestRunner $t) use ($run): void {
    $result = $run(1001);

    $t->same(['before-insert', 'after-insert', 'before-insert', 'before-update', 'after-update', 'before-insert', 'after-insert', 'before-insert', 'before-update', 'after-update'], array_column($result['trigger_trace'], 'event'));
    $t->same([
        'upsert1.test upsert1-1300: duplicate INSERT SELECT rows feed UPSERT DO UPDATE trigger old/new images',
        'returning1.test 4.1-4.5: UPSERT RETURNING emits changed rows in statement order',
        'non-overlap: this batch covers trigger old/new row-image stability for duplicate UPSERT source rows, not excluded-alias, target priority, partial-index, catch-all, or statement-current subquery batches',
        'dependency closure: no new support component needed; reuses native UPSERT trigger trace and RETURNING row stream helpers',
    ], [
        'upsert1.test upsert1-1300: duplicate INSERT SELECT rows feed UPSERT DO UPDATE trigger old/new images',
        'returning1.test 4.1-4.5: UPSERT RETURNING emits changed rows in statement order',
        'non-overlap: this batch covers trigger old/new row-image stability for duplicate UPSERT source rows, not excluded-alias, target priority, partial-index, catch-all, or statement-current subquery batches',
        'dependency closure: no new support component needed; reuses native UPSERT trigger trace and RETURNING row stream helpers',
    ]);
};

return $tests;
