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

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test';

$tests = [
    'real upstream triggerC basic lifecycle cites old new trigger setup' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('CREATE TRIGGER trig1 BEFORE INSERT ON t1', $source);
        $t->contains('CREATE TRIGGER trig4 AFTER UPDATE ON t1', $source);
        $t->contains('CREATE TRIGGER trig6 AFTER DELETE ON t1', $source);
    },
    'real upstream triggerC basic lifecycle cites aborting delete trigger' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('CREATE TRIGGER t4t AFTER DELETE ON t4', $source);
        $t->contains("SELECT RAISE(ABORT, 'delete is not supported')", $source);
        $t->contains('SELECT * FROM t4', $source);
    },
];

for ($i = 1; $i <= 160; ++$i) {
    $insert = [
        'a' => 'A' . $i,
        'b' => 'B' . $i,
        'c' => 'C' . $i,
    ];
    $updatedA = 'a' . $i;
    $updatedC = $i % 2 === 0 ? 'c' . $i : 'C' . $i;
    $deleteAfterUpdate = $i % 5 !== 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCBasicOldNewLifecyclePlan(
        $insert,
        ['a' => $updatedA, 'c' => $updatedC],
        $deleteAfterUpdate
    );

    $expectedLogCount = $deleteAfterUpdate ? 6 : 4;
    $expectedRowsAfterDelete = $deleteAfterUpdate ? [] : [['a' => $updatedA, 'b' => 'B' . $i, 'c' => $updatedC]];

    foreach ([
        'source' => 'triggerC.test triggerC-1.2..1.10',
        'operation' => 'basic-before-after-old-new-lifecycle',
        'status' => 'commit-ok',
        'inserted_row.a' => 'A' . $i,
        'inserted_row.b' => 'B' . $i,
        'inserted_row.c' => 'C' . $i,
        'updated_row.a' => $updatedA,
        'updated_row.b' => 'B' . $i,
        'updated_row.c' => $updatedC,
        'delete_after_update' => $deleteAfterUpdate,
        'delete_status' => $deleteAfterUpdate ? 'commit-ok' : 'not-attempted',
        'rows_after_insert.0.a' => 'A' . $i,
        'rows_after_update.0.a' => $updatedA,
        'rows_after_update.0.c' => $updatedC,
        'rows_after_delete' => $expectedRowsAfterDelete,
        'log_count' => $expectedLogCount,
        'log.0.phase' => 'before',
        'log.0.old_a' => null,
        'log.0.new_a' => 'A' . $i,
        'log.1.phase' => 'after',
        'log.1.old_b' => null,
        'log.1.new_b' => 'B' . $i,
        'log.2.phase' => 'before',
        'log.2.old_a' => 'A' . $i,
        'log.2.new_a' => $updatedA,
        'log.3.phase' => 'after',
        'log.3.old_c' => 'C' . $i,
        'log.3.new_c' => $updatedC,
        'abort_delete.status' => 'constraint-failed',
        'abort_delete.error' => 'delete is not supported',
        'abort_delete.rows_after_statement.0.a' => 1,
        'abort_delete.rows_after_statement.0.b' => 2,
        'abort_delete.rolled_back' => true,
        'dependencies.0' => 'sqlite-triggerC-before-insert-sees-new-row-only',
        'dependencies.1' => 'sqlite-triggerC-update-feeds-old-and-new-row-images',
        'dependencies.2' => 'sqlite-triggerC-delete-feeds-old-row-only',
        'dependencies.3' => 'sqlite-triggerC-raise-abort-delete-preserves-row',
    ] as $path => $expected) {
        $tests[sprintf('real upstream triggerC basic lifecycle dynamic %03d %s', $i, $path)] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    if ($deleteAfterUpdate) {
        foreach ([
            'log.4.phase' => 'before',
            'log.4.old_a' => $updatedA,
            'log.4.new_a' => null,
            'log.5.phase' => 'after',
            'log.5.old_b' => 'B' . $i,
            'log.5.new_b' => null,
        ] as $path => $expected) {
            $tests[sprintf('real upstream triggerC basic delete dynamic %03d %s', $i, $path)] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

$tests['real upstream triggerC basic lifecycle rejects missing insert column'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerCBasicOldNewLifecyclePlan(['a' => 'A', 'b' => 'B']));
$tests['real upstream triggerC basic lifecycle rejects unsupported update column'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerCBasicOldNewLifecyclePlan(['a' => 'A', 'b' => 'B', 'c' => 'C'], ['missing' => 'x']));

return $tests;
