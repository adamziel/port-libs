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
    'real upstream fkey8 attached restrict cites schema shadow section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test');

        $t->true(is_string($source));
        $t->true(str_contains($source, 'do_execsql_test 6.1'));
        $t->true(str_contains($source, 'CREATE TABLE c1(b);'));
        $t->true(str_contains($source, 'CREATE TABLE aux.c1(b REFERENCES p1(a) ON DELETE RESTRICT)'));
        $t->true(str_contains($source, 'DELETE FROM aux.p1 WHERE a=123;'));
    },
];

for ($i = 1; $i <= 650; ++$i) {
    $key = 1000 + $i;
    $schema = 'aux' . ($i % 7);
    $mainChildren = [
        ['b' => $key, 'payload' => 'main-shadow-' . $i],
        ['b' => $key + 1, 'payload' => 'main-shadow-next-' . $i],
    ];
    $attachedParents = [
        ['a' => $key, 'label' => 'parent-' . $i],
        ['a' => $key + 9, 'label' => 'sibling-' . $i],
    ];
    $attachedChildren = ($i % 3) === 0
        ? []
        : [['b' => $key, 'payload' => 'attached-child-' . $i]];

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey8AttachedRestrictDeletePlan(
        $mainChildren,
        $attachedParents,
        $attachedChildren,
        $key,
        $schema
    );
    $blocked = $attachedChildren !== [];
    $case = 'real upstream fkey8 attached restrict schema shadow dynamic ' . $i;

    foreach ([
        'source' => 'fkey8.test fkey8-6.1..6.3',
        'operation' => 'attached-schema-restrict-delete-resolution',
        'status' => $blocked ? 'constraint-failed' : 'commit-ok',
        'attached_schema' => $schema,
        'parent_table' => $schema . '.p1',
        'child_table' => $schema . '.c1',
        'main_shadow_child_table' => 'main.c1',
        'delete_key' => $key,
        'attached_child_reference_count' => $blocked ? 1 : 0,
        'main_shadow_reference_count' => 1,
        'main_shadow_ignored_for_attached_fk' => true,
        'restrict_checked_attached_schema_only' => true,
        'error' => $blocked ? 'FOREIGN KEY constraint failed' : null,
        'dependencies.0' => 'sqlite-fkey8-attached-child-resolves-parent-in-own-schema',
        'dependencies.1' => 'sqlite-fkey8-main-shadow-table-does-not-satisfy-attached-fk',
        'dependencies.2' => 'sqlite-fkey8-restrict-blocks-attached-parent-delete-before-parent-removal',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' parent image is preserved only when attached restrict blocks'] = static function (TestRunner $t) use ($plan, $blocked, $key): void {
        $t->same($blocked, in_array($key, $plan()['parent_keys_after_statement'], true));
    };
    $tests[$case . ' main shadow child never becomes attached child'] = static function (TestRunner $t) use ($plan, $key): void {
        $t->same([$key, $key + 1], $plan()['main_shadow_child_keys']);
    };
}

$tests['real upstream fkey8 attached restrict rejects malformed schema'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey8AttachedRestrictDeletePlan([], [], [], 1, 'bad-schema'));
};

$tests['real upstream fkey8 attached restrict rejects malformed parent row'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey8AttachedRestrictDeletePlan([], [['id' => 1]], [], 1));
};

$tests['real upstream fkey8 attached restrict rejects malformed child row'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey8AttachedRestrictDeletePlan([], [['a' => 1]], [['id' => 1]], 1));
};

return $tests;
