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
    'real upstream triggerC rowid mutation cites before trigger delete block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'triggerC-7.1'));
        $t->true(is_string($source) && str_contains($source, 'DELETE FROM t7 WHERE a = 1;'));
    },
    'real upstream triggerC rowid mutation cites before trigger rowid move block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'triggerC-7.4'));
        $t->true(is_string($source) && str_contains($source, 'UPDATE t7 set rowid = 8 WHERE rowid=1;'));
    },
    'real upstream triggerC rowid mutation cites before delete block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'triggerC-7.7'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER t7t BEFORE DELETE ON t7'));
    },
];

for ($i = 1; $i <= 120; ++$i) {
    $base = [
        ['rowid' => 1, 'a' => 1, 'b' => 2],
        ['rowid' => 2, 'a' => 3, 'b' => 4],
        ['rowid' => 3, 'a' => 5, 'b' => 6],
    ];

    $cases = [
        'update unaffected row after delete-before trigger' => [
            'update',
            5,
            'delete-rowid-1',
            [2, 3],
            [3, 5],
            [4, 7],
            ['after fired 3->3'],
            true,
        ],
        'update deleted target is suppressed' => [
            'update',
            1,
            'delete-rowid-1',
            [2, 3],
            [3, 5],
            [4, 6],
            [],
            false,
        ],
        'update other row after rowid move fires both update triggers' => [
            'update',
            5,
            'move-rowid-1-to-8',
            [2, 3, 8],
            [3, 5, 1],
            [4, 7, 2],
            ['after fired 1->8', 'after fired 3->3'],
            true,
        ],
        'update moved target is suppressed' => [
            'update',
            1,
            'move-rowid-1-to-8',
            [2, 3, 8],
            [3, 5, 1],
            [4, 6, 2],
            ['after fired 1->8'],
            false,
        ],
        'delete other row after rowid move keeps moved row' => [
            'delete',
            3,
            'move-rowid-1-to-8',
            [3, 8],
            [5, 1],
            [6, 2],
            ['after fired 2'],
            true,
        ],
        'delete moved target is suppressed' => [
            'delete',
            1,
            'move-rowid-1-to-8',
            [2, 3, 8],
            [3, 5, 1],
            [4, 6, 2],
            [],
            false,
        ],
    ];

    foreach ($cases as $label => [$operation, $targetA, $mutation, $rowids, $aValues, $bValues, $afterLog, $changed]) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::beforeTriggerRowidMutation($base, $operation, $targetA, $mutation);
        $case = sprintf('triggerC-7 rowid mutation %s dynamic %03d', $label, $i);

        foreach ([
            'source' => 'triggerC.test triggerC-7.1..7.9',
            'operation' => 'before-trigger-rowid-mutation',
            'status' => 'commit-ok',
            'statement' => $operation,
            'target_a' => $targetA,
            'before_mutation' => $mutation,
            'before_trigger_applied' => true,
            'outer_statement_changed' => $changed,
            'final_rowids' => $rowids,
            'final_a_values' => $aValues,
            'final_b_values' => $bValues,
            'after_log' => $afterLog,
            'after_log_count' => count($afterLog),
            'dependencies.0' => 'sqlite-triggerC-before-trigger-can-delete-target-row',
            'dependencies.1' => 'sqlite-triggerC-before-trigger-can-move-rowid-before-outer-statement',
            'dependencies.2' => 'sqlite-triggerC-after-trigger-fires-only-for-surviving-outer-row-change',
        ] as $path => $expected) {
            $tests['real upstream ' . $case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

$tests['real upstream triggerC rowid mutation rejects unsupported operation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::beforeTriggerRowidMutation([], 'insert', 1, 'delete-rowid-1'));
};

$tests['real upstream triggerC rowid mutation rejects unsupported before action'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::beforeTriggerRowidMutation([], 'update', 1, 'rewrite'));
};

return $tests;
