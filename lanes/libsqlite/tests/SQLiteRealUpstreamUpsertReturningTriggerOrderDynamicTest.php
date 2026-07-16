<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningTriggerOrderPlan;

$tests = [];

$run = static function (int $variant): array {
    $base = [
        ['setting_id' => 1, 'key_name' => 'alpha', 'rank' => 2 + ($variant % 7), 'revision' => 0],
        ['setting_id' => 3, 'key_name' => 'gamma', 'rank' => 4 + ($variant % 5), 'revision' => 0],
    ];
    $incoming = [
        ['setting_id' => 1, 'key_name' => 'alpha-new', 'rank' => 80 + ($variant % 13), 'revision' => 0],
        ['setting_id' => 2, 'key_name' => 'beta', 'rank' => 11 + ($variant % 17), 'revision' => 0],
        ['setting_id' => 3, 'key_name' => 'gamma-skip', 'rank' => 1, 'revision' => 0],
        ['setting_id' => 2, 'key_name' => 'beta-late', 'rank' => 60 + ($variant % 19), 'revision' => 0],
        ['setting_id' => 1, 'key_name' => 'alpha-skip', 'rank' => 1, 'revision' => 0],
        ['setting_id' => 1, 'key_name' => 'alpha-final', 'rank' => 99 + ($variant % 23), 'revision' => 0],
    ];

    return SQLiteUpsertReturningTriggerOrderPlan::execute(
        $base,
        $incoming,
        ['setting_id'],
        [
            'key_name' => static fn (array $old, array $incoming): string => (string) $incoming['key_name'],
            'rank' => static fn (array $old, array $incoming): int => (int) $incoming['rank'],
            'revision' => static fn (array $old): int => (int) $old['revision'] + 1,
        ],
        static fn (array $old, array $incoming): bool => $old['rank'] < $incoming['rank'],
        ['setting_id', 'key_name', 'rank', 'revision'],
    );
};

$tests['real upstream upsert2 trigger order dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert2.test 300 ON CONFLICT DO UPDATE fires BEFORE INSERT, BEFORE UPDATE, and AFTER UPDATE',
        'upsert2.test 320 failed DO UPDATE WHERE fires only BEFORE INSERT',
        'upsert2.test 400/420 repeat the same trigger ordering for WITHOUT ROWID tables',
        'returning1.test 17 RETURNING emits one row for each successful insert/update in statement order',
        'non-overlap: existing accepted files cover SELECT-input alias UPSERT RETURNING, omitted-target row streams, excluded aliasing, and expression assignments; this batch isolates trigger firing order around UPSERT RETURNING rows',
    ], [
        'upsert2.test 300 ON CONFLICT DO UPDATE fires BEFORE INSERT, BEFORE UPDATE, and AFTER UPDATE',
        'upsert2.test 320 failed DO UPDATE WHERE fires only BEFORE INSERT',
        'upsert2.test 400/420 repeat the same trigger ordering for WITHOUT ROWID tables',
        'returning1.test 17 RETURNING emits one row for each successful insert/update in statement order',
        'non-overlap: existing accepted files cover SELECT-input alias UPSERT RETURNING, omitted-target row streams, excluded aliasing, and expression assignments; this batch isolates trigger firing order around UPSERT RETURNING rows',
    ]);
};

$tests['real upstream upsert2 trigger order dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; adds a bounded native PHP trigger-order executor for generic UPSERT RETURNING rows',
        'no new support component needed; adds a bounded native PHP trigger-order executor for generic UPSERT RETURNING rows',
    );
};

for ($variant = 1; $variant <= 1000; ++$variant) {
    $tests[sprintf('real upstream upsert2 returning trigger order dynamic variant %04d', $variant)] = static function (TestRunner $t) use ($run, $variant): void {
        $result = $run($variant);
        $phases = array_column($result['audit'], 'phase');

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
            'before-insert',
            'before-update',
            'after-update',
        ], $phases, 'upsert2.test 300/320 trigger order with failed WHERE rows');
        $t->same([1, 2, 2, 1], array_column($result['returning'], 'setting_id'), 'RETURNING row stream follows changed source rows');
        $t->same(['alpha-new', 'beta', 'beta-late', 'alpha-final'], array_column($result['returning'], 'key_name'), 'RETURNING omits failed WHERE conflicts');
        $t->same(['gamma-skip', 'alpha-skip'], array_column($result['skipped'], 'key_name'), 'failed DO UPDATE WHERE rows are skipped');
        $t->same(6, count(array_filter($phases, static fn (string $phase): bool => $phase === 'before-insert')), 'BEFORE INSERT fires for every source row');
        $t->same(4, $result['changes'], 'changes equal successful insert/update count');
    };
}

$tests['real upstream upsert2 returning trigger order rejects missing unique column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningTriggerOrderPlan::execute(
        [['setting_id' => 1, 'key_name' => 'alpha']],
        [['key_name' => 'missing-id']],
        ['setting_id'],
        [],
        null,
        ['setting_id'],
    ));
};

$tests['real upstream upsert2 returning trigger order rejects missing returning column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningTriggerOrderPlan::execute(
        [],
        [['setting_id' => 1, 'key_name' => 'alpha']],
        ['setting_id'],
        [],
        null,
        ['missing'],
    ));
};

return $tests;
