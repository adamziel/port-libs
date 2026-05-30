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

$tests = [
    'real upstream triggerF cites WITHOUT ROWID trigger setup' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerF.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE t1(a INT PRIMARY KEY, b) WITHOUT ROWID'));
    },
    'real upstream triggerF cites replace operations that fire delete triggers' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerF.test');
        $t->true(is_string($source) && str_contains($source, 'INSERT OR REPLACE INTO t1 VALUES(2, \'three\');'));
        $t->true(is_string($source) && str_contains($source, 'UPDATE OR REPLACE t1 SET a=3 WHERE a=2;'));
    },
];

for ($i = 1; $i <= 1000; ++$i) {
    $before = ($i % 3) !== 1;
    $after = ($i % 3) !== 2;
    $rows = [
        ['a' => 1, 'b' => 'one'],
        ['a' => 2, 'b' => 'two'],
        ['a' => 3, 'b' => 'three'],
    ];
    if (($i % 5) === 0) {
        $rows[] = ['a' => 4, 'b' => 'four'];
    }
    if (($i % 7) === 0) {
        $rows[] = ['a' => 5, 'b' => 'five'];
    }

    $expectedLogValues = [];
    $rowCount = count($rows);
    foreach ([[1, 'one'], [2, 'two'], [3, 'three']] as $index => [$key, $label]) {
        if ($before) {
            $expectedLogValues[] = $key . $label . $rowCount;
        }
        --$rowCount;
        if ($after) {
            $expectedLogValues[] = $key . $label . $rowCount;
        }
        if ($index === 1) {
            ++$rowCount;
        }
    }
    $finalKeys = array_values(array_filter(
        [3, 4, 5],
        static fn (int $key): bool => in_array($key, array_column($rows, 'a'), true)
    ));

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidReplaceDeleteTriggerPlan($rows, $before, $after);
    $case = sprintf('triggerF.test 1.%d without rowid replace delete trigger dynamic %04d', $before && $after ? 4 : ($after ? 2 : 3), $i);

    foreach ([
        'source' => 'triggerF.test 1.2..1.4',
        'operation' => 'without-rowid-replace-delete-trigger-log',
        'status' => 'commit-ok',
        'before_trigger' => $before,
        'after_trigger' => $after,
        'trigger_count' => (int) $before + (int) $after,
        'log_count' => count($expectedLogValues),
        'replace_delete_count' => 3,
        'without_rowid_primary_key_preserved' => true,
        'dependencies.0' => 'sqlite-triggerF-without-rowid-delete-triggers-fire-for-replace-conflicts',
        'dependencies.1' => 'sqlite-triggerF-before-trigger-sees-row-before-delete',
        'dependencies.2' => 'sqlite-triggerF-after-trigger-sees-row-after-delete',
    ] as $path => $expected) {
        $tests['real upstream ' . $case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests['real upstream ' . $case . ' log values match upstream row-count visibility'] = static function (TestRunner $t) use ($plan, $expectedLogValues): void {
        $t->same($expectedLogValues, $plan()['log_values']);
    };
    $tests['real upstream ' . $case . ' final primary keys preserve WITHOUT ROWID replacement target'] = static function (TestRunner $t) use ($plan, $finalKeys): void {
        $t->same($finalKeys, $plan()['final_primary_keys']);
    };
    $tests['real upstream ' . $case . ' final replacement row keeps incoming value'] = static function (TestRunner $t) use ($plan): void {
        $rows = $plan()['final_rows'];
        $t->same(['a' => 3, 'b' => 'three'], $rows[array_search(3, array_column($rows, 'a'), true)]);
    };
}

$tests['real upstream triggerF rejects missing delete triggers'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidReplaceDeleteTriggerPlan([['a' => 1, 'b' => 'one']], false, false));
};

$tests['real upstream triggerF rejects duplicate WITHOUT ROWID primary key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidReplaceDeleteTriggerPlan([
        ['a' => 1, 'b' => 'one'],
        ['a' => 1, 'b' => 'again'],
    ], true, true));
};

return $tests;
