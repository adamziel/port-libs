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

$parents = [
    ['setting_id' => 1, 'key_name' => 'one'],
    ['setting_id' => 2, 'key_name' => 'two'],
    ['setting_id' => 3, 'key_name' => 'three'],
];
$children = [
    ['child_id' => 'a', 'setting_id' => 1],
    ['child_id' => 'b', 'setting_id' => 2],
];

$tests = [
    'real upstream fkey6 lifecycle cites deferred status and reset sections' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test');
        $t->true(is_string($source) && str_contains($source, 'DBSTATUS_DEFERRED_FKS'));
        $t->true(is_string($source) && str_contains($source, 'automatically switched off at each COMMIT or ROLLBACK'));
        $t->true(is_string($source) && str_contains($source, 'DROP TABLE c1'));
        $t->true(is_string($source) && str_contains($source, 'FOREIGN KEY constraint failed'));
    },
];

for ($seed = 1; $seed <= 70; ++$seed) {
    $mode = $seed % 4;
    $deleteKey = ($seed % 2) + 1;
    if ($mode === 0) {
        $steps = [
            ['action' => 'begin'],
            ['action' => 'set-defer', 'enabled' => true],
            ['action' => 'delete-parent', 'key' => $deleteKey],
            ['action' => 'delete-child', 'key' => $deleteKey],
            ['action' => 'commit'],
        ];
        $expected = [
            'status' => 'commit-ok',
            'inside_transaction' => false,
            'defer_foreign_keys' => false,
            'defer_resets_at_boundary' => true,
            'deferred_status_history' => [1, 0, 0],
            'deferred_violation_count' => 0,
            'dbstatus_deferred_fks' => 0,
            'commit_failed' => false,
        ];
    } elseif ($mode === 1) {
        $steps = [
            ['action' => 'begin'],
            ['action' => 'set-defer', 'enabled' => true],
            ['action' => 'insert-child', 'row' => ['child_id' => 'missing-' . $seed, 'setting_id' => 99 + $seed]],
            ['action' => 'drop-child-table'],
            ['action' => 'commit'],
        ];
        $expected = [
            'status' => 'commit-ok',
            'inside_transaction' => false,
            'defer_foreign_keys' => false,
            'defer_resets_at_boundary' => true,
            'deferred_status_history' => [1, 0, 0],
            'deferred_violation_count' => 0,
            'dbstatus_deferred_fks' => 0,
            'commit_failed' => false,
        ];
    } elseif ($mode === 2) {
        $steps = [
            ['action' => 'begin'],
            ['action' => 'set-defer', 'enabled' => true],
            ['action' => 'delete-parent', 'key' => $deleteKey],
            ['action' => 'commit'],
        ];
        $expected = [
            'status' => 'deferred-commit-failed',
            'inside_transaction' => true,
            'defer_foreign_keys' => true,
            'defer_resets_at_boundary' => false,
            'deferred_status_history' => [1, 1],
            'deferred_violation_count' => 1,
            'dbstatus_deferred_fks' => 1,
            'commit_failed' => true,
        ];
    } else {
        $steps = [
            ['action' => 'begin'],
            ['action' => 'set-defer', 'enabled' => true],
            ['action' => 'delete-parent', 'key' => $deleteKey],
            ['action' => 'rollback'],
        ];
        $expected = [
            'status' => 'commit-ok',
            'inside_transaction' => false,
            'defer_foreign_keys' => false,
            'defer_resets_at_boundary' => true,
            'deferred_status_history' => [1, 0],
            'deferred_violation_count' => 0,
            'dbstatus_deferred_fks' => 0,
            'commit_failed' => false,
        ];
    }

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysTransactionStatusPlan(
        $parents,
        $children,
        'setting_id',
        'setting_id',
        $steps
    );
    $case = 'real upstream fkey6 defer lifecycle dynamic seed ' . $seed;

    foreach ([
        'source' => 'fkey6.test fkey6-1.0..1.22, fkey6-2.1..2.6, fkey6-4.0..4.2',
        'operation' => 'defer-foreign-keys-transaction-status',
        'dependencies.0' => 'sqlite-fkey6-defer-foreign-keys-defaults-off',
        'dependencies.1' => 'sqlite-fkey6-dbstatus-deferred-fks-tracks-outstanding-violations',
        'dependencies.2' => 'sqlite-fkey6-deferred-counter-clears-when-child-row-or-table-is-removed',
        'dependencies.3' => 'sqlite-fkey6-defer-foreign-keys-resets-at-commit-or-rollback',
        'dependencies.4' => 'sqlite-fkey6-commit-fails-with-outstanding-deferred-violations',
    ] + $expected as $path => $expectedValue) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expectedValue, $value): void {
            $t->same($expectedValue, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' event history preserves deferred counter transition'] = static function (TestRunner $t) use ($plan, $mode): void {
        $actual = $plan();
        $counts = array_values(array_filter(array_map(
            static fn (array $event): ?int => array_key_exists('deferred_violation_count', $event) ? (int) $event['deferred_violation_count'] : null,
            $actual['events']
        ), static fn (?int $count): bool => $count !== null));
        $t->same($actual['deferred_status_history'], $counts);
        $t->same($mode === 2, $actual['commit_failed']);
    };
}

$tests['real upstream fkey6 lifecycle rejects mutation outside transaction'] = static function (TestRunner $t) use ($parents, $children): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysTransactionStatusPlan(
        $parents,
        $children,
        'setting_id',
        'setting_id',
        [['action' => 'delete-parent', 'key' => 1]]
    ));
};

return $tests;
