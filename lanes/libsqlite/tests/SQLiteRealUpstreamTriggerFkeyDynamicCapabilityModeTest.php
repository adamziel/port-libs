<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test';

$tests = [
    'real upstream e_fkey capability mode cites full support cascade source' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);

        $t->true(is_string($source));
        $t->true(str_contains($source, 'do_test e_fkey-1'));
        $t->true(str_contains($source, "UPDATE p SET i = 'world'"));
        $t->true(str_contains($source, 'SELECT * FROM c;'));
    },
    'real upstream e_fkey capability mode cites omit trigger pragma source' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);

        $t->true(is_string($source));
        $t->true(str_contains($source, 'If SQLITE_OMIT_TRIGGER is defined'));
        $t->true(str_contains($source, 'PRAGMA foreign_key_list(c)'));
        $t->true(str_contains($source, 'PRAGMA foreign_keys'));
    },
    'real upstream e_fkey capability mode cites omit foreign key parse source' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);

        $t->true(is_string($source));
        $t->true(str_contains($source, 'If OMIT_FOREIGN_KEY is defined'));
        $t->true(str_contains($source, 'near "ON": syntax error'));
        $t->true(str_contains($source, 'REFERENCES p'));
    },
];

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

for ($i = 1; $i <= 1000; ++$i) {
    $mode = match ($i % 3) {
        0 => 'full-support',
        1 => 'omit-trigger',
        default => 'omit-foreign-key',
    };
    $oldKey = 'setting-' . ($i % 17);
    $newKey = 'migrated-' . $i;
    $parents = [
        ['key' => $oldKey, 'payload' => 'parent-current-' . $i],
        ['key' => 'stable-' . ($i % 11), 'payload' => 'parent-stable-' . $i],
    ];
    $children = [
        ['parent_key' => $oldKey, 'payload' => 'child-current-' . $i],
        ['parent_key' => 'stable-' . ($i % 11), 'payload' => 'child-stable-' . $i],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCapabilityModePlan($mode, $parents, $children, $oldKey, $newKey);
    $case = sprintf('real upstream e_fkey capability mode dynamic %04d %s', $i, $mode);

    $expectations = [
        'operation' => 'foreign-key-compile-capability-mode',
        'mode' => $mode,
        'parent_keys_before.0' => $oldKey,
        'parent_keys_after.0' => $mode === 'omit-foreign-key' ? $oldKey : $newKey,
        'child_keys_before.0' => $oldKey,
        'child_keys_after.0' => $mode === 'full-support' ? $newKey : $oldKey,
        'cascade_applied' => $mode === 'full-support',
        'foreign_key_definitions_parsed' => $mode !== 'omit-foreign-key',
        'foreign_key_actions_enforced' => $mode === 'full-support',
    ];

    if ($mode === 'full-support') {
        $expectations += [
            'source' => 'e_fkey.test e_fkey-1',
            'status' => 'commit-ok',
            'pragma_foreign_keys_rows.0' => 1,
            'pragma_foreign_key_list_rows.0.on_update' => 'CASCADE',
            'dependencies.0' => 'sqlite-e-fkey-full-support-enforces-cascade-actions',
        ];
    } elseif ($mode === 'omit-trigger') {
        $expectations += [
            'source' => 'e_fkey.test e_fkey-2.1..2.3',
            'status' => 'commit-ok',
            'pragma_foreign_keys_rows' => [],
            'pragma_foreign_key_list_rows.0.on_update' => 'CASCADE',
            'dependencies.0' => 'sqlite-e-fkey-omit-trigger-parses-fk-definitions',
        ];
    } else {
        $expectations += [
            'source' => 'e_fkey.test e_fkey-3.1..3.5',
            'status' => 'parse-error',
            'pragma_foreign_keys_rows' => [],
            'pragma_foreign_key_list_rows' => [],
            'create_child_error' => 'near "ON": syntax error',
            'declared_type_fallback' => 'REFERENCES p',
            'dependencies.0' => 'sqlite-e-fkey-omit-foreign-key-rejects-references-syntax',
        ];
    }

    foreach ($expectations as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

$tests['real upstream e_fkey capability mode rejects unsupported mode'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCapabilityModePlan('partial', [['key' => 'a']], [['parent_key' => 'a']], 'a', 'b'));
};

$tests['real upstream e_fkey capability mode rejects empty parent rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCapabilityModePlan('full-support', [], [['parent_key' => 'a']], 'a', 'b'));
};

$tests['real upstream e_fkey capability mode rejects malformed child row'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCapabilityModePlan('full-support', [['key' => 'a']], [['missing' => 'a']], 'a', 'b'));
};

return $tests;
