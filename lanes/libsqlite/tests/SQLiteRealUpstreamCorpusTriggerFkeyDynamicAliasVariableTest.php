<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$valueAt = static function (array $array, string $path): mixed {
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

$tests = [
    'real upstream corpus trigger fkey dynamic alias variable cites triggerD rowid alias sections' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerD.test');
        $t->true(is_string($source));
        $t->true(str_contains($source, 'Verify that when columns named "rowid", "oid", and "_rowid_" appear'));
        $t->true(str_contains($source, 'do_test triggerD-1.2'));
        $t->true(str_contains($source, 'do_test triggerD-2.2'));
    },
    'real upstream corpus trigger fkey dynamic alias variable cites triggerE variable null sections' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerE.test');
        $t->true(is_string($source));
        $t->true(str_contains($source, 'trigger cannot use variables'));
        $t->true(str_contains($source, 'Test that variable references within trigger definitions loaded from'));
        $t->true(str_contains($source, 'do_execsql_test 2.2.1'));
    },
];

foreach (range(1, 120) as $seed) {
    foreach ([true, false] as $ordinaryColumns) {
        foreach (['insert', 'update', 'delete'] as $event) {
            $row = $ordinaryColumns
                ? ['rowid' => 100 + $seed, 'oid' => 200 + $seed, '_rowid_' => 300 + $seed, 'x' => 400 + $seed]
                : ['w' => 100 + $seed, 'x' => 200 + $seed, 'y' => 300 + $seed, 'z' => 400 + $seed];
            $storageRowid = $seed + 1;
            $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerRowidAliasResolution($row, $event, $ordinaryColumns, $storageRowid);
            $case = sprintf(
                'real upstream triggerD rowid alias dynamic seed %03d %s %s',
                $seed,
                $ordinaryColumns ? 'ordinary-columns' : 'storage-rowid',
                $event
            );

            $expectedNew = [];
            $expectedOld = [];
            if ($ordinaryColumns) {
                $base = [100 + $seed, 200 + $seed, 300 + $seed, 400 + $seed, 'ordinary-column'];
                if ($event === 'insert') {
                    $expectedNew = $base;
                } elseif ($event === 'update') {
                    $expectedOld = $base;
                    $expectedNew = [101 + $seed, 200 + $seed, 300 + $seed, 400 + $seed, 'ordinary-column'];
                } else {
                    $expectedOld = $base;
                }
            } else {
                $base = [$storageRowid, $storageRowid, $storageRowid, 200 + $seed, 'storage-rowid'];
                if ($event === 'insert') {
                    $expectedNew = $base;
                } elseif ($event === 'update') {
                    $expectedOld = $base;
                    $expectedNew = [$storageRowid, $storageRowid, $storageRowid, 201 + $seed, 'storage-rowid'];
                } else {
                    $expectedOld = $base;
                }
            }

            $expectations = [
                'source' => $ordinaryColumns ? 'triggerD.test triggerD-1.1..1.4' : 'triggerD.test triggerD-2.1..2.4',
                'operation' => 'trigger-rowid-alias-resolution',
                'status' => 'commit-ok',
                'event' => $event,
                'ordinary_rowid_columns' => $ordinaryColumns,
                'storage_rowid' => $storageRowid,
                'old_values' => $expectedOld,
                'new_values' => $expectedNew,
                'rowid_source' => $ordinaryColumns ? 'ordinary-column' : 'storage-rowid',
                'dependencies.0' => 'sqlite-triggerD-rowid-oid-_rowid_-ordinary-columns-shadow-storage-rowid',
                'dependencies.1' => 'sqlite-triggerD-old-new-rowid-aliases-use-storage-rowid-without-shadow-columns',
                'dependencies.2' => 'sqlite-triggerD-before-after-triggers-see-event-specific-old-new-images',
            ];
            foreach ($expectations as $path => $expected) {
                $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $valueAt): void {
                    $t->same($expected, $valueAt($plan(), (string) $path));
                };
            }

            $tests[$case . ' before trigger order'] = static function (TestRunner $t) use ($plan, $event): void {
                $actual = $plan();
                $expected = $event === 'update' ? ['r3.old', 'r3.new'] : ($event === 'insert' ? ['r1'] : ['r5']);
                $t->same($expected, array_column($actual['before_log'], 'trigger'));
            };
            $tests[$case . ' after trigger order'] = static function (TestRunner $t) use ($plan, $event): void {
                $actual = $plan();
                $expected = $event === 'update' ? ['r4.old', 'r4.new'] : ($event === 'insert' ? ['r2'] : ['r6']);
                $t->same($expected, array_column($actual['after_log'], 'trigger'));
            };
            $tests[$case . ' combined log count'] = static function (TestRunner $t) use ($plan, $event): void {
                $t->same($event === 'update' ? 4 : 2, $plan()['log_count']);
            };
        }
    }

    foreach (['insert-null-pair', 'when-null-update'] as $program) {
        $targetRows = [
            ['c' => 'x' . $seed, 'd' => 'y' . $seed],
            ['c' => null, 'd' => 'z' . $seed],
        ];
        if ($seed % 3 === 0) {
            $targetRows[] = ['c' => null, 'd' => 'extra' . $seed];
        }
        $inserted = ['a' => $seed, 'b' => $seed + 1];
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::storedTriggerVariablesResolveNull($targetRows, $inserted, $program);
        $case = sprintf('real upstream triggerE stored variable null dynamic seed %03d %s', $seed, $program);
        $expectedChanged = $program === 'insert-null-pair' ? 1 : 1 + ($seed % 3 === 0 ? 1 : 0);
        $expectedCValues = $program === 'insert-null-pair'
            ? array_merge(array_column($targetRows, 'c'), [null])
            : array_map(static fn (array $row): mixed => $row['c'] ?? $row['d'], $targetRows);

        $expectations = [
            'source' => 'triggerE.test triggerE-2.1..2.3',
            'operation' => 'stored-trigger-variables-resolve-null',
            'status' => 'commit-ok',
            'program' => $program,
            'inserted_row' => $inserted,
            'variable_value' => null,
            'trigger_when_result' => $program === 'when-null-update',
            'changed_rows' => $expectedChanged,
            'target_c_values' => array_values($expectedCValues),
            'dependencies.0' => 'sqlite-triggerE-create-trigger-rejects-bound-variables',
            'dependencies.1' => 'sqlite-triggerE-writable-schema-loaded-trigger-variables-resolve-null',
            'dependencies.2' => 'sqlite-triggerE-null-variable-drives-when-and-update-expression',
        ];
        foreach ($expectations as $path => $expected) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $valueAt): void {
                $t->same($expected, $valueAt($plan(), (string) $path));
            };
        }
    }
}

$tests['real upstream triggerD rowid alias rejects unsupported event'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerRowidAliasResolution(['rowid' => 1, 'oid' => 2, '_rowid_' => 3, 'x' => 4], 'merge', true, 1));
};
$tests['real upstream triggerD rowid alias rejects missing row column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerRowidAliasResolution(['rowid' => 1, 'oid' => 2, 'x' => 4], 'insert', true, 1));
};
$tests['real upstream triggerE stored trigger rejects unsupported program'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::storedTriggerVariablesResolveNull([], ['a' => 1], 'select-bound'));
};

return $tests;
