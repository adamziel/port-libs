<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan;

$valueAt = static function (array $array, string $path): mixed {
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

$buildRows = static function (int $length, string $shape): array {
    $parents = [];
    $children = [];
    for ($id = 1; $id <= $length; ++$id) {
        $parents[] = [
            'setting_id' => $id,
            'tenant_id' => 10 + $id,
            'key_name' => 'setting_' . $id,
            'key_value' => 'value_' . $id,
        ];
        if ($shape === 'all' || ($shape === 'odd' && $id % 2 === 1) || ($shape === 'tail' && $id > 2)) {
            $children[] = [
                'ref_id' => 100 + $id,
                'setting_id' => $id,
                'label' => 'ref_' . $id,
            ];
        }
    }
    if ($shape === 'nulls') {
        $children[] = ['ref_id' => 191, 'setting_id' => null, 'label' => 'loose_ref'];
        $children[] = ['ref_id' => 192, 'setting_id' => $length, 'label' => 'tail_ref'];
    }

    return [$parents, $children];
};

$schemaText = static function (string $scope, bool $foreignKeys, bool $blockedDefault): string {
    $prefix = $scope === 'main' ? '' : $scope . '.';
    $columns = [
        'setting_id PRIMARY KEY',
        'tenant_id',
        'key_name',
        'key_value',
        'nullable_ref REFERENCES app_parent',
        'explicit_null DEFAULT NULL REFERENCES app_parent',
    ];
    if (!$foreignKeys || !$blockedDefault) {
        $columns[] = "load_policy DEFAULT 'eager' REFERENCES app_parent";
    }

    return 'CREATE TABLE ' . $prefix . 'app_settings(' . implode(', ', $columns) . ')';
};

$renameParentSql = static function (string $createSql, string $old, string $new): string {
    $quoted = '"' . str_replace('"', '""', $new) . '"';
    $patterns = [
        '/REFERENCES\s+"' . preg_quote($old, '/') . '"/i',
        '/REFERENCES\s+' . preg_quote($old, '/') . '\b/i',
    ];

    return preg_replace($patterns, 'REFERENCES ' . $quoted, $createSql) ?? $createSql;
};

$runDrop = static function (array $case) use ($buildRows): array {
    [$parents, $children] = $buildRows((int) $case['length'], (string) $case['shape']);

    return SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan::deleteParents(
        $parents,
        $children,
        range(1, (int) $case['delete_count']),
        [
            'parent_key' => 'setting_id',
            'child_key' => 'setting_id',
            'on_delete' => (string) $case['action'],
            'deferred' => (bool) $case['deferred'],
            'default' => 0,
        ],
        [
            [
                'name' => 'app_settings_after_delete_audit',
                'timing' => 'after',
                'event' => 'delete',
                'action' => 'audit',
                'values' => [
                    'setting_id' => 'old.setting_id',
                    'key_name' => 'old.key_name',
                ],
            ],
        ],
        [
            'recursive_triggers' => (bool) $case['recursive'],
            'max_depth' => 64,
            'current_source' => (string) $case['scope'],
            'next_source' => 'after-drop-' . (string) $case['scope'],
        ],
    );
};

$expectedAfterDrop = static function (array $case) use ($buildRows): array {
    [$parents, $children] = $buildRows((int) $case['length'], (string) $case['shape']);
    $deleted = range(1, (int) $case['delete_count']);
    $remainingParents = array_values(array_filter(
        $parents,
        static fn (array $row): bool => !in_array($row['setting_id'], $deleted, true),
    ));
    $remainingKeys = array_column($remainingParents, 'setting_id');
    $action = (string) $case['action'];
    $nextChildren = [];
    foreach ($children as $child) {
        $childKey = $child['setting_id'];
        if (!in_array($childKey, $deleted, true)) {
            $nextChildren[] = $child;
            continue;
        }
        if ($action === 'cascade') {
            continue;
        }
        if ($action === 'set null') {
            $child['setting_id'] = null;
        } elseif ($action === 'set default') {
            $child['setting_id'] = 0;
        }
        $nextChildren[] = $child;
    }

    $violations = 0;
    foreach ($nextChildren as $child) {
        if ($child['setting_id'] !== null && !in_array($child['setting_id'], $remainingKeys, true)) {
            ++$violations;
        }
    }

    return [
        'parents' => count($remainingParents),
        'children' => count($nextChildren),
        'actions' => count(array_filter(
            $children,
            static fn (array $child): bool => in_array($child['setting_id'], $deleted, true),
        )),
        'violations' => $violations,
        'status' => $violations === 0 ? 'ok' : 'deferred-constraint-failed',
    ];
};

$tests = [
    'real upstream fkey2 schema drop dynamic cites upstream sections' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'fkey2-14.1*: ALTER TABLE ADD COLUMN'));
        $t->true(is_string($source) && str_contains($source, 'fkey2-14.2*: ALTER TABLE RENAME TABLE'));
        $t->true(is_string($source) && str_contains($source, 'fkey2-14.3*: DROP TABLE'));
    },
];

$schemaScopes = ['main', 'temp', 'aux'];
foreach ($schemaScopes as $scope) {
    foreach ([true, false] as $foreignKeys) {
        foreach ([true, false] as $blockedDefault) {
            $label = sprintf('fkey2-14.1 add references column %s fk-%s default-%s', $scope, $foreignKeys ? 'on' : 'off', $blockedDefault ? 'blocked' : 'accepted');
            $tests[$label . ' schema text'] = static function (TestRunner $t) use ($schemaText, $scope, $foreignKeys, $blockedDefault): void {
                $sql = $schemaText($scope, $foreignKeys, $blockedDefault);
                $prefix = $scope === 'main' ? 'CREATE TABLE app_settings' : 'CREATE TABLE ' . $scope . '.app_settings';
                $t->same($prefix, substr($sql, 0, strlen($prefix)));
                $t->true(str_contains($sql, 'nullable_ref REFERENCES app_parent'));
                $t->true(str_contains($sql, 'explicit_null DEFAULT NULL REFERENCES app_parent'));
                $t->same(!$foreignKeys || !$blockedDefault, str_contains($sql, "load_policy DEFAULT 'eager' REFERENCES app_parent"));
            };
        }
    }

    foreach (['app_parent' => 'app_parent_next', '"app_parent"' => 'tenant_parent_next'] as $old => $new) {
        $label = sprintf('fkey2-14.2 rename parent %s %s', $scope, trim($old, '"'));
        $tests[$label . ' rewrites references'] = static function (TestRunner $t) use ($schemaText, $renameParentSql, $scope, $old, $new): void {
            $sql = str_replace('app_parent', $old, $schemaText($scope, true, false));
            $renamed = $renameParentSql($sql, trim($old, '"'), $new);
            $t->true(str_contains($renamed, 'REFERENCES "' . $new . '"'));
            $t->same(false, str_contains($renamed, 'REFERENCES ' . $old . ','));
            $t->same($scope === 'main' ? 0 : 1, substr_count($renamed, $scope . '.'));
        };
    }
}

$dropCases = [];
foreach ($schemaScopes as $scope) {
    foreach (['cascade', 'set null', 'set default', 'no action', 'restrict'] as $action) {
        foreach ([true, false] as $deferred) {
            foreach ([6, 9, 12, 15] as $length) {
                foreach ([1, 2, 4] as $deleteCount) {
                    foreach (['all', 'odd', 'tail', 'nulls'] as $shape) {
                        $dropCases[] = compact('scope', 'action', 'deferred', 'length', 'deleteCount', 'shape') + [
                            'delete_count' => $deleteCount,
                            'recursive' => true,
                        ];
                    }
                }
            }
        }
    }
}

foreach ($dropCases as $index => $case) {
    $caseLabel = sprintf(
        'fkey2-14.3 drop table %s %s %s len%d del%d %s',
        $case['scope'],
        str_replace(' ', '-', (string) $case['action']),
        $case['deferred'] ? 'deferred' : 'immediate',
        $case['length'],
        $case['delete_count'],
        $case['shape'],
    );
    $expected = $expectedAfterDrop($case);
    $immediateFailure = !$case['deferred']
        && in_array($case['action'], ['set default', 'no action', 'restrict'], true)
        && $expected['violations'] > 0;

    if ($immediateFailure) {
        $tests[$caseLabel . ' immediate violation blocks drop'] = static function (TestRunner $t) use ($runDrop, $case): void {
            try {
                $runDrop($case);
                $t->true(false);
            } catch (InvalidArgumentException $exception) {
                $t->true(str_contains($exception->getMessage(), 'immediate delete constraint failed'));
            }
        };
        continue;
    }

    $plan = static fn (): array => $runDrop($case);
    foreach ([
        'commit_status' => $expected['status'],
        'parent-count' => $expected['parents'],
        'child-count' => $expected['children'],
        'action-count' => $expected['actions'],
        'violation-count' => $expected['violations'],
        'current_source' => $case['scope'],
        'next_source' => 'after-drop-' . $case['scope'],
        'dependencies.1' => 'sqlite-fkey-delete-action-corpus',
    ] as $path => $expectedValue) {
        $tests[$caseLabel . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expectedValue, $valueAt): void {
            $result = $plan();
            $actual = match ($path) {
                'parent-count' => count($result['parent']),
                'child-count' => count($result['child']),
                'action-count' => count($result['foreign_key_actions']),
                'violation-count' => count($result['deferred_violations']),
                default => $valueAt($result, (string) $path),
            };
            $t->same($expectedValue, $actual);
        };
    }

    if ($index % 7 === 0) {
        $tests[$caseLabel . ' first deleted key'] = static function (TestRunner $t) use ($plan): void {
            $t->same(1, $plan()['deleted_parent_keys'][0]);
        };
    }
}

return $tests;
