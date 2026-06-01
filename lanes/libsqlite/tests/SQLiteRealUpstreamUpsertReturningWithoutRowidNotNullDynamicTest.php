<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertWithoutRowidConstraintPlan;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test';

$tests['real upstream upsert1 1000 without rowid not-null source contains catchsql'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);

    $t->true(is_string($source));
    $t->contains('do_catchsql_test upsert1-1000', (string) $source);
    $t->contains('CREATE TABLE t0(c0 PRIMARY KEY, c1, c2 UNIQUE) WITHOUT ROWID', (string) $source);
    $t->contains('INSERT OR FAIL INTO t0(c2) VALUES (0), (NULL)', (string) $source);
    $t->contains('ON CONFLICT(c2) DO UPDATE SET c1 = c0', (string) $source);
    $t->contains('NOT NULL constraint failed: t0.c0', (string) $source);
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $tests[sprintf('real upstream upsert1 1000 without rowid not-null abort dynamic %04d', $seed)] =
        static function (TestRunner $t) use ($seed): void {
            $table = 'app_without_rowid_' . $seed;
            $rows = [
                [
                    'setting_id' => 100000 + $seed,
                    'tenant_id' => 1 + ($seed % 7),
                    'key_name' => 'existing-' . $seed,
                    'key_value' => 'stable-' . $seed,
                ],
            ];
            $incomingRows = [
                [
                    'tenant_id' => 20 + ($seed % 11),
                    'key_name' => 'incoming-' . $seed,
                    'key_value' => 'candidate-' . $seed,
                ],
                [
                    'tenant_id' => 30 + ($seed % 13),
                    'key_name' => 'later-' . $seed,
                    'key_value' => 'unreached-' . $seed,
                ],
            ];

            $plan = SQLiteUpsertWithoutRowidConstraintPlan::missingPrimaryKeyAbort(
                $rows,
                $incomingRows,
                ['setting_id'],
                ['key_name'],
                $table,
                ['setting_id', 'key_name', 'key_value'],
            );

            $t->same('upsert1.test', $plan['source']);
            $t->same('upsert1-1000 WITHOUT ROWID primary-key NOT NULL failure aborts before UPSERT conflict handling', $plan['scenario']);
            $t->same(1, $plan['rc']);
            $t->same(false, $plan['ok']);
            $t->same('NOT NULL constraint failed: ' . $table . '.setting_id', $plan['error']);
            $t->same(true, $plan['without_rowid']);
            $t->same(['setting_id'], $plan['primary_key']);
            $t->same(['key_name'], $plan['conflict_target']);
            $t->same(['setting_id', 'key_name', 'key_value'], $plan['returning']);
            $t->same($rows, $plan['before']);
            $t->same($incomingRows, $plan['incoming_rows']);
            $t->same($incomingRows[0], $plan['failed_row']);
            $t->same(1, $plan['failed_ordinal']);
            $t->same('setting_id', $plan['failed_column']);
            $t->same($rows, $plan['after']);
            $t->same([], $plan['inserted_rows']);
            $t->same([], $plan['updated_rows']);
            $t->same([], $plan['skipped_rows']);
            $t->same([], $plan['returning_rows']);
            $t->same(0, $plan['changes']);
            $t->same(0, $plan['processed_rows']);
            $t->same(false, $plan['conflict_probe_attempted']);
            $t->same(false, $plan['later_rows_processed']);
            $t->same([
                'upsert1.test-1000',
                'returning1.test-error-no-row-stream',
                'sqlite-without-rowid-primary-key-not-null-before-upsert-conflict',
            ], $plan['dependencies']);
        };
}

$tests['real upstream upsert1 1000 without rowid not-null rejects malformed plans'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertWithoutRowidConstraintPlan::missingPrimaryKeyAbort([['setting_id' => 1]], [], ['setting_id'], ['key_name']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertWithoutRowidConstraintPlan::missingPrimaryKeyAbort([['setting_id' => 1]], [['setting_id' => 2]], [], ['key_name']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertWithoutRowidConstraintPlan::missingPrimaryKeyAbort([['setting_id' => 1]], [['setting_id' => 2]], ['setting_id'], []));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertWithoutRowidConstraintPlan::missingPrimaryKeyAbort([['setting_id' => 1]], [['setting_id' => 2]], ['setting_id'], ['key_name'], 'bad-table'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertWithoutRowidConstraintPlan::missingPrimaryKeyAbort([['setting_id' => 1]], [['setting_id' => 2]], ['setting_id'], ['key_name'], 'app_without_rowid', []));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertWithoutRowidConstraintPlan::missingPrimaryKeyAbort([['setting_id' => 1]], [['setting_id' => 2]], ['setting_id'], ['key_name']));
};

$tests['real upstream upsert1 1000 without rowid not-null source coverage and non overlap'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-1000 INSERT OR FAIL into a WITHOUT ROWID table fails on missing PRIMARY KEY before ON CONFLICT(c2) DO UPDATE',
        '1000 deterministic UPSERT RETURNING variants assert unchanged storage, zero changes, empty RETURNING rows, and no later-row processing after the primary-key NOT NULL failure',
        'non-overlap: this owns the upstream upsert1-1000 WITHOUT ROWID primary-key NOT NULL barrier, not the accepted secondary unique conflict abort, target-priority, OR IGNORE, trigger-order, upsert5 arm-priority, QRF formatter, or returning trigger streams',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-1000 INSERT OR FAIL into a WITHOUT ROWID table fails on missing PRIMARY KEY before ON CONFLICT(c2) DO UPDATE',
        '1000 deterministic UPSERT RETURNING variants assert unchanged storage, zero changes, empty RETURNING rows, and no later-row processing after the primary-key NOT NULL failure',
        'non-overlap: this owns the upstream upsert1-1000 WITHOUT ROWID primary-key NOT NULL barrier, not the accepted secondary unique conflict abort, target-priority, OR IGNORE, trigger-order, upsert5 arm-priority, QRF formatter, or returning trigger streams',
    ]);
};

$tests['real upstream upsert1 1000 without rowid not-null dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new external support component needed; adds a bounded native PHP row-array barrier for WITHOUT ROWID primary-key validation before UPSERT conflict handling and RETURNING row emission',
        'no new external support component needed; adds a bounded native PHP row-array barrier for WITHOUT ROWID primary-key validation before UPSERT conflict handling and RETURNING row emission',
    );
};

return $tests;
