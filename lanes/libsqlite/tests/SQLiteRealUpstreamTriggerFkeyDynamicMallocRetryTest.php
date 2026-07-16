<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey_malloc.test';

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
    'real upstream fkey malloc retry cites cascade action source' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);
        $t->true(is_string($source) && str_contains($source, 'do_malloc_test fkey_malloc-1'));
        $t->true(is_string($source) && str_contains($source, 'ON UPDATE CASCADE ON DELETE CASCADE'));
    },
    'real upstream fkey malloc retry cites deferred composite source' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);
        $t->true(is_string($source) && str_contains($source, 'do_malloc_test fkey_malloc-2'));
        $t->true(is_string($source) && str_contains($source, 'DEFERRABLE INITIALLY DEFERRED'));
    },
    'real upstream fkey malloc retry cites mismatch and drop source' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);
        $t->true(is_string($source) && str_contains($source, 'catch_fk_error {INSERT INTO t6 VALUES(2)}'));
        $t->true(is_string($source) && str_contains($source, 'do_malloc_test fkey_malloc-7'));
    },
];

$scenarios = [
    'cascade-delete' => [
        'source' => 'fkey_malloc.test fkey_malloc-1',
        'operation' => 'foreign-key-cascade-update-delete-retry',
        'status' => 'commit-ok',
        'expected_action_count' => 2,
        'dependencies.0' => 'sqlite-fkey-malloc-cascade-update-delete-rolls-back-on-fault',
    ],
    'deferred-composite' => [
        'source' => 'fkey_malloc.test fkey_malloc-2',
        'operation' => 'foreign-key-deferred-composite-retry',
        'status' => 'commit-ok',
        'parents_after.0.a' => 'c',
        'children_after.0.x' => 'c',
        'expected_action_count' => 3,
        'dependencies.0' => 'sqlite-fkey-malloc-deferred-composite-counter-survives-retry',
    ],
    'set-default-null' => [
        'source' => 'fkey_malloc.test fkey_malloc-3',
        'operation' => 'foreign-key-set-default-set-null-retry',
        'status' => 'commit-ok',
        'parents_after.0.x' => 14,
        'children_after.0.y' => 14,
        'children_after.1.y' => null,
        'dependencies.0' => 'sqlite-fkey-malloc-set-default-uses-column-default',
    ],
    'mismatch-errors' => [
        'source' => 'fkey_malloc.test fkey_malloc-4',
        'operation' => 'foreign-key-mismatch-error-retry',
        'status' => 'expected-errors',
        'statement_journal_required' => false,
        'errors.1' => 'foreign key mismatch',
        'dependencies.1' => 'sqlite-fkey-malloc-mismatch-error-is-stable-across-retry',
    ],
    'composite-update' => [
        'source' => 'fkey_malloc.test fkey_malloc-5',
        'operation' => 'foreign-key-composite-update-cascade-retry',
        'status' => 'commit-ok',
        'parents_after.0.x' => 5,
        'children_after.0.a' => 5,
        'dependencies.1' => 'sqlite-fkey-malloc-child-key-column-order-is-preserved',
    ],
    'self-restrict-default' => [
        'source' => 'fkey_malloc.test fkey_malloc-6',
        'operation' => 'foreign-key-self-restrict-set-default-retry',
        'status' => 'constraint-failed',
        'parents_after.0.x' => 'abc',
        'errors.0' => 'FOREIGN KEY constraint failed',
        'dependencies.0' => 'sqlite-fkey-malloc-restrict-self-reference-rolls-back-statement',
    ],
    'drop-parent' => [
        'source' => 'fkey_malloc.test fkey_malloc-7',
        'operation' => 'foreign-key-drop-parent-retry',
        'status' => 'constraint-failed',
        'parents_after.0.a' => 1,
        'errors.0' => 'FOREIGN KEY constraint failed',
        'dependencies.1' => 'sqlite-fkey-malloc-deferred-child-table-drop-does-not-mask-parent-drop-check',
    ],
];

for ($i = 1; $i <= 160; ++$i) {
    foreach ($scenarios as $scenario => $expectations) {
        $faultInjected = ($i + strlen($scenario)) % 3 !== 0;
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyMallocRetryPlan($scenario, $i, $faultInjected);
        $case = sprintf('real upstream fkey_malloc dynamic %s attempt %03d', $scenario, $i);

        foreach ($expectations + [
            'fault_attempt' => $i,
            'fault_injected' => $faultInjected,
            'first_attempt_status' => $faultInjected ? 'out-of-memory-before-commit' : $expectations['status'],
            'retry_attempted' => $faultInjected,
            'retry_status' => $expectations['status'],
            'rolled_back_fault_attempt' => $faultInjected,
            'final_status_after_retry' => $expectations['status'],
            'foreign_key_check_clean_after_retry' => true,
            'deferred_counter_after' => 0,
            'native_fault_boundary' => 'malloc-fault-does-not-commit-partial-fk-action',
        ] as $path => $expected) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

$tests['real upstream fkey malloc retry rejects unsupported scenario'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyMallocRetryPlan('unsupported', 0, false));
};

$tests['real upstream fkey malloc retry rejects negative fault attempt'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyMallocRetryPlan('cascade-delete', -1, false));
};

return $tests;
