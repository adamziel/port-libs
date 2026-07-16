<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

/**
 * @param array<string,mixed> $array
 */
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

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test';
$operationCount = 24;

$tests = [
    'real upstream e_fkey runtime intro cites pragma and transaction source' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-4.1'));
        $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-5.1'));
        $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-6.1'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA foreign_keys = OFF'));
    },
    'real upstream e_fkey runtime intro cites artist track source' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-7.1'));
        $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-10.5'));
        $t->true(is_string($source) && str_contains($source, 'test_r52486_21352'));
        $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-14.4'));
    },
];

for ($seed = 1; $seed <= 250; ++$seed) {
    $notNullChildKey = $seed % 5 === 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyRuntimeIntroPlan(
        $seed,
        $operationCount,
        $notNullChildKey
    );
    $case = sprintf('real upstream e_fkey runtime intro dynamic %03d', $seed);

    $expected = [
        'source' => 'e_fkey.test e_fkey-4.1..14.4',
        'operation' => 'runtime-foreign-key-intro-and-example',
        'seed' => $seed,
        'foreign_keys_default_enabled' => false,
        'default_disabled.child_key_after_parent_update' => 'hello_' . $seed,
        'enabled_cascade.child_key_after_parent_update' => 'world_' . $seed,
        'pragma_state_sequence' => [0, 1, 0],
        'transaction_toggle.first_delete_status' => 'constraint-failed',
        'transaction_toggle.state_inside_first_transaction' => 1,
        'transaction_toggle.second_delete_status' => 'commit-ok',
        'transaction_toggle.state_inside_second_transaction' => 0,
        'create_track_schema_status' => 'commit-ok',
        'missing_artist_insert.status' => 'constraint-failed',
        'wrong_artist_after_parent_insert.status' => 'constraint-failed',
        'valid_artist_insert.status' => 'commit-ok',
        'dependent_artist_delete.status' => 'constraint-failed',
        'delete_after_track_removal.status' => 'commit-ok',
        'null_child_insert.status' => $notNullChildKey ? 'not-null-failed' : 'commit-ok',
        'null_child_exempt_from_fk' => !$notNullChildKey,
        'track_artist_not_null' => $notNullChildKey,
        'dynamic_operation_count' => $operationCount,
        'dynamic_invariant_violation_count' => 0,
        'dynamic_failed_invariant_after_rollback' => false,
        'dynamic_final_invariant_ok' => true,
        'foreign_key_expression' => 'trackartist IS NULL OR EXISTS(parent)',
        'example.missing_artist_insert_status' => 'constraint-failed',
        'example.null_artist_insert_status' => 'commit-ok',
        'example.missing_artist_update_status' => 'constraint-failed',
        'example.add_artist_update_insert_status' => 'commit-ok',
        'example.delete_dependent_sinatra_status' => 'constraint-failed',
        'example.delete_sinatra_after_track_status' => 'commit-ok',
        'example.update_dean_with_tracks_status' => 'constraint-failed',
        'example.update_dean_after_track_delete_status' => 'commit-ok',
        'example.final_artist_ids' => [3, 4],
        'example.final_track_artist_ids' => [3, 3],
        'dependencies.0' => 'sqlite-efkey-runtime-foreign-keys-default-off',
        'dependencies.1' => 'sqlite-efkey-runtime-pragma-reports-connection-state',
        'dependencies.2' => 'sqlite-efkey-runtime-toggle-inside-transaction-is-no-op',
        'dependencies.3' => 'sqlite-efkey-intro-null-child-key-satisfies-foreign-key',
        'dependencies.4' => 'sqlite-efkey-intro-parent-delete-update-blocked-while-child-references',
        'dependencies.5' => 'sqlite-efkey-intro-invariant-preserved-after-failed-statements',
    ];

    foreach ($expected as $path => $expectedValue) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expectedValue, $value): void {
            $t->same($expectedValue, $value($plan(), (string) $path));
        };
    }
}

$tests['real upstream e_fkey runtime intro rejects non-positive seed'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyRuntimeIntroPlan(0)
    );
};

$tests['real upstream e_fkey runtime intro rejects empty dynamic operation count'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyRuntimeIntroPlan(1, 0)
    );
};

return $tests;
