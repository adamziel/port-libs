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

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/temptrigger.test';

$tests = [
    'real upstream temptrigger cites shared cache reload section' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'do_test temptrigger-1.1'));
        $t->true(is_string($source) && str_contains($source, 'do_test temptrigger-1.4'));
        $t->true(is_string($source) && str_contains($source, 'sqlite3_enable_shared_cache 1'));
    },
    'real upstream temptrigger cites attached schema reload section' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, "CREATE TEMP TRIGGER tr2 AFTER INSERT ON aux.t2"));
        $t->true(is_string($source) && str_contains($source, 'do_test temptrigger-3.3.2'));
    },
    'real upstream temptrigger cites qualified temp trigger dml section' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'do_catchsql_test 8.1.1'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TEMP TRIGGER tr1 AFTER INSERT ON t2'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TEMP TRIGGER tr3 AFTER DELETE ON t2'));
    },
    'real upstream temptrigger cites attached chain section' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'set nDb 8'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TEMP TRIGGER tr$ii AFTER INSERT ON db$ii.tbl'));
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test 9.5.3'));
    },
];

for ($i = 1; $i <= 90; ++$i) {
    foreach ([
        ['schema-reload', false],
        ['connection-reopen', false],
        ['schema-reload', true],
    ] as [$reloadKind, $attached]) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::tempTriggerSharedCacheReloadPlan($i * 10, $reloadKind, $attached);
        foreach ([
            'status' => 'commit-ok',
            'reload_kind' => $reloadKind,
            'attached_schema' => $attached,
            'base_rows.0.0' => $i * 10,
            'base_rows.1.0' => $i * 10 + 2,
            'base_rows.2.0' => $i * 10 + 4,
            'temp_rows.0.0' => $i * 10,
            'temp_rows.1.0' => $i * 10 + 4,
            'temp_trigger_fired_for_owner_before_reload' => true,
            'temp_trigger_hidden_from_peer_connection' => !$attached,
            'temp_trigger_survived_schema_reload' => true,
            'drop_trigger_after_reload_ok' => true,
            'schema_cookie_source' => $attached ? 'attached-database-peer' : $reloadKind,
            'dependencies.0' => 'sqlite-temptrigger-connection-local-temp-trigger',
            'dependencies.1' => 'sqlite-temptrigger-shared-cache-schema-reload-preserves-owner-trigger',
            'dependencies.2' => 'sqlite-temptrigger-attached-schema-reload-preserves-temp-trigger',
        ] as $path => $expected) {
            $tests['real upstream temptrigger shared reload dynamic ' . $i . ' ' . $reloadKind . ' ' . ($attached ? 'attached' : 'main') . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

foreach (['insert', 'update', 'delete'] as $event) {
    for ($i = 1; $i <= 80; ++$i) {
        foreach ([true, false] as $tempTrigger) {
            $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::tempTriggerQualifiedBodyPlan($event, $tempTrigger, 'left-' . $i, 'right-' . $i);
            $expectedTarget = $tempTrigger && $event === 'insert' ? [['left-' . $i, 'right-' . $i]] : [];
            $expectedMain = [];
            if ($tempTrigger && $event === 'update') {
                $expectedMain = [['left-' . $i, 'right-' . $i]];
            } elseif ($tempTrigger && $event === 'delete') {
                $expectedMain = [['main-survivor', 'left-' . $i . ':right-' . $i]];
            }
            foreach ([
                'source' => 'temptrigger.test temptrigger-8.1.1..8.3.3',
                'operation' => 'temp-trigger-qualified-body-dml',
                'status' => $tempTrigger ? 'commit-ok' : 'create-trigger-error',
                'event' => $event,
                'temp_trigger' => $tempTrigger,
                'qualified_dml_allowed' => $tempTrigger,
                'target_rows' => $expectedTarget,
                'main_rows' => $expectedMain,
                'error' => $tempTrigger ? null : 'qualified table names are not allowed on INSERT, UPDATE, and DELETE statements within triggers',
                'dependencies.0' => 'sqlite-temptrigger-qualified-dml-only-for-temp-triggers',
                'dependencies.1' => 'sqlite-temptrigger-qualified-insert-update-delete-routes-attached-targets',
            ] as $path => $expected) {
                $tests['real upstream temptrigger qualified dml dynamic ' . $i . ' ' . $event . ' ' . ($tempTrigger ? 'temp' : 'persistent') . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                    $t->same($expected, $value($plan(), (string) $path));
                };
            }
        }
    }
}

for ($i = 1; $i <= 80; ++$i) {
    foreach ([false, true] as $mainShadowCreated) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::tempTriggerNameResolutionPlan($i * 20, $mainShadowCreated);
        foreach ([
            'source' => 'temptrigger.test temptrigger-6.0..7.6',
            'operation' => 'temp-trigger-name-resolution',
            'status' => 'commit-ok',
            'main_shadow_created' => $mainShadowCreated,
            'main_rows' => $mainShadowCreated ? [[$i * 20 + 2, $i * 20 + 3]] : [],
            'aux_rows.0.0' => $i * 20,
            'qualified_aux_reference_in_temp_trigger_ok' => true,
            'qualified_aux_reference_in_persistent_trigger_error' => true,
            'main_trigger_still_guards_main_table' => true,
            'dependencies.1' => 'sqlite-temptrigger-recreated-body-resolves-unqualified-name-after-shadow',
        ] as $path => $expected) {
            $tests['real upstream temptrigger name resolution dynamic ' . $i . ' ' . ($mainShadowCreated ? 'main-shadow' : 'aux-first') . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

foreach (['insert', 'update', 'delete'] as $event) {
    for ($i = 1; $i <= 80; ++$i) {
        $count = 4 + ($i % 5);
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::tempTriggerAttachedChainPlan($count, ['a' => 'a' . $i, 'b' => 'b' . $i, 'c' => 'c' . $i], $event);
        foreach ([
            'source' => 'temptrigger.test temptrigger-9.0..9.5.3',
            'operation' => 'temp-trigger-attached-schema-chain',
            'status' => 'commit-ok',
            'event' => $event,
            'database_count' => $count,
            'trigger_count' => $count - 1,
            'cascade_depth' => $count - 1,
            'rotates_values_across_attached_schemas' => $event !== 'delete',
            'delete_clears_chained_attached_tables' => $event === 'delete',
            'dependencies.0' => 'sqlite-temptrigger-attached-insert-chain-routes-new-values',
            'dependencies.1' => 'sqlite-temptrigger-attached-update-chain-routes-new-values',
            'dependencies.2' => 'sqlite-temptrigger-attached-delete-chain-clears-downstream-tables',
        ] as $path => $expected) {
            $tests['real upstream temptrigger attached chain dynamic ' . $i . ' ' . $event . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
        if ($event !== 'delete') {
            $tests['real upstream temptrigger attached chain dynamic ' . $i . ' ' . $event . ' db1 rotated'] = static function (TestRunner $t) use ($plan, $i, $value): void {
                $t->same([['b' . $i, 'c' . $i, 'a' . $i]], $value($plan(), 'rows_by_schema.db1'));
            };
            $tests['real upstream temptrigger attached chain dynamic ' . $i . ' ' . $event . ' db2 rotated'] = static function (TestRunner $t) use ($plan, $i, $value): void {
                $t->same([['c' . $i, 'a' . $i, 'b' . $i]], $value($plan(), 'rows_by_schema.db2'));
            };
        } else {
            $tests['real upstream temptrigger attached chain dynamic ' . $i . ' delete clears db1'] = static function (TestRunner $t) use ($plan, $value): void {
                $t->same([], $value($plan(), 'rows_by_schema.db1'));
            };
            $tests['real upstream temptrigger attached chain dynamic ' . $i . ' delete clears db2'] = static function (TestRunner $t) use ($plan, $value): void {
                $t->same([], $value($plan(), 'rows_by_schema.db2'));
            };
        }
    }
}

$tests['real upstream temptrigger rejects unsupported reload kind'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::tempTriggerSharedCacheReloadPlan(1, 'vacuum'));
$tests['real upstream temptrigger rejects unsupported qualified dml event'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::tempTriggerQualifiedBodyPlan('select', true, 1, 2));
$tests['real upstream temptrigger rejects short attached chain'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::tempTriggerAttachedChainPlan(1, ['a' => 1, 'b' => 2, 'c' => 3], 'insert'));
$tests['real upstream temptrigger rejects malformed chain row'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::tempTriggerAttachedChainPlan(2, ['a' => 1, 'b' => 2], 'insert'));

return $tests;
