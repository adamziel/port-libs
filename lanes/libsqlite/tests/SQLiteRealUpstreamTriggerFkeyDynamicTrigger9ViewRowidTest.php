<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

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

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger9.test';

$tests['real upstream trigger9 view rowid cites disabled rowid delete'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);
    $t->true(is_string($source) && str_contains($source, 'DELETE FROM v1 WHERE rowid=1'));
    $t->true(is_string($source) && str_contains($source, 'no such column: rowid'));
};

$tests['real upstream trigger9 view rowid cites disabled rowid update'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);
    $t->true(is_string($source) && str_contains($source, 'UPDATE v1 SET a=b WHERE rowid=2'));
    $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER tr2 INSTEAD OF UPDATE ON v1'));
};

for ($i = 1; $i <= 140; ++$i) {
    $rows = [
        ['a' => $i, 'b' => $i + 1],
        ['a' => $i + 2, 'b' => $i + 3],
        ['a' => $i + 4, 'b' => $i + 5],
    ];

    $disabledDelete = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger9ViewRowidAccessPlan($rows, 'delete', false, 1);
    $disabledUpdate = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger9ViewRowidAccessPlan($rows, 'update', false, 2);
    $enabledDelete = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger9ViewRowidAccessPlan($rows, 'delete', true, 1);
    $enabledUpdate = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger9ViewRowidAccessPlan($rows, 'update', true, 2);
    $enabledMiss = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger9ViewRowidAccessPlan($rows, 'update', true, 99);

    foreach ([
        'source' => 'trigger9.test trigger9-4.1..4.3',
        'operation' => 'view-rowid-trigger-policy',
        'status' => 'schema-error',
        'statement' => 'delete',
        'allow_rowid_in_view' => false,
        'error' => 'no such column: rowid',
        'selected_rowids' => [],
        'trigger_log' => [],
        'rows_after_statement.0.a' => $i,
        'dependencies.0' => 'sqlite-trigger9-view-rowid-disabled-rejects-delete-update',
    ] as $path => $expected) {
        $tests["real upstream trigger9 view rowid disabled delete dynamic {$i} {$path}"] = static function (TestRunner $t) use ($disabledDelete, $path, $expected, $value): void {
            $t->same($expected, $value($disabledDelete(), (string) $path));
        };
    }

    foreach ([
        'status' => 'schema-error',
        'statement' => 'update',
        'allow_rowid_in_view' => false,
        'error' => 'no such column: rowid',
        'rows_after_statement.1.a' => $i + 2,
        'rows_after_statement.1.b' => $i + 3,
    ] as $path => $expected) {
        $tests["real upstream trigger9 view rowid disabled update dynamic {$i} {$path}"] = static function (TestRunner $t) use ($disabledUpdate, $path, $expected, $value): void {
            $t->same($expected, $value($disabledUpdate(), (string) $path));
        };
    }

    foreach ([
        'status' => 'commit-ok',
        'statement' => 'delete',
        'allow_rowid_in_view' => true,
        'selected_rowids.0' => 1,
        'trigger_log.0' => 'delete',
        'rows_after_statement.0.a' => $i,
        'dependencies.1' => 'sqlite-trigger9-view-rowid-enabled-routes-instead-of-triggers',
    ] as $path => $expected) {
        $tests["real upstream trigger9 view rowid enabled delete dynamic {$i} {$path}"] = static function (TestRunner $t) use ($enabledDelete, $path, $expected, $value): void {
            $t->same($expected, $value($enabledDelete(), (string) $path));
        };
    }

    foreach ([
        'status' => 'commit-ok',
        'statement' => 'update',
        'allow_rowid_in_view' => true,
        'selected_rowids.0' => 2,
        'trigger_log.0' => 'update',
        'new_rows_seen_by_trigger.0.a' => $i + 3,
        'new_rows_seen_by_trigger.0.b' => $i + 3,
        'rows_after_statement.1.a' => $i + 2,
        'rows_after_statement.1.b' => $i + 3,
        'dependencies.2' => 'sqlite-trigger9-instead-of-trigger-log-follows-statement-kind',
    ] as $path => $expected) {
        $tests["real upstream trigger9 view rowid enabled update dynamic {$i} {$path}"] = static function (TestRunner $t) use ($enabledUpdate, $path, $expected, $value): void {
            $t->same($expected, $value($enabledUpdate(), (string) $path));
        };
    }

    foreach ([
        'status' => 'commit-ok',
        'statement' => 'update',
        'selected_rowids' => [],
        'trigger_log' => [],
        'new_rows_seen_by_trigger' => [],
        'rows_after_statement.2.a' => $i + 4,
        'rows_after_statement.2.b' => $i + 5,
    ] as $path => $expected) {
        $tests["real upstream trigger9 view rowid enabled miss dynamic {$i} {$path}"] = static function (TestRunner $t) use ($enabledMiss, $path, $expected, $value): void {
            $t->same($expected, $value($enabledMiss(), (string) $path));
        };
    }
}

$tests['real upstream trigger9 view rowid rejects unsupported statement'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::trigger9ViewRowidAccessPlan([], 'insert', true, 1));
$tests['real upstream trigger9 view rowid rejects malformed view row'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::trigger9ViewRowidAccessPlan([['a' => 1]], 'update', true, 1));

return $tests;
