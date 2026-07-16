<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteForeignKeyReplaceCounterPlan;

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

$parentRows = static function (int $size): array {
    $rows = [];
    for ($id = 1; $id <= $size; ++$id) {
        $rows[] = ['setting_id' => $id, 'label' => 'parent_' . $id];
    }

    return $rows;
};

$childRows = static function (int $size, int $stride): array {
    $rows = [];
    for ($id = 1; $id <= $size; ++$id) {
        if ($id % $stride === 0) {
            $rows[] = ['child_id' => $id, 'setting_id' => $id, 'payload' => 'child_' . $id];
        }
    }

    return $rows;
};

$replaceParent = static function (int $size, int $target, bool $trigger, bool $deferred, ?int $priorDeleted = null) use ($parentRows, $childRows): array {
    $foreignKey = [
        'parent_key' => 'setting_id',
        'child_key' => 'setting_id',
        'deferred' => $deferred,
        'without_rowid_parent' => true,
    ];
    if ($priorDeleted !== null) {
        $foreignKey['prior_deleted_parent_keys'] = [$priorDeleted];
    }
    if ($trigger) {
        $foreignKey['trigger_replace_parent'] = ['setting_id' => $target + 1, 'label' => 'trigger_replaced_' . ($target + 1)];
    }

    return SQLiteForeignKeyReplaceCounterPlan::replaceParent(
        $parentRows($size),
        $childRows($size, 2),
        $foreignKey,
        ['setting_id' => $target, 'label' => 'replacement_' . $target],
    );
};

$replaceChild = static function (int $size, int $target, bool $deferred) use ($childRows): array {
    return SQLiteForeignKeyReplaceCounterPlan::replaceChild(
        $childRows($size, 1),
        ['child_key' => 'child_id', 'deferred' => $deferred],
        ['child_id' => $target, 'setting_id' => 9999, 'payload' => 'replacement_child_' . $target],
    );
};

$tests['real upstream corpus fkey8 replace counter cites upstream sections'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test');
    $t->true(is_string($source) && str_contains($source, 'The following tests check that foreign key constaint counters'));
    $t->true(is_string($source) && str_contains($source, 'INSERT OR REPLACE INTO p1 VALUES(2, \'two\')'));
    $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER p3d AFTER DELETE ON p3'));
};

foreach ([4, 5, 6, 7, 8, 9, 10, 11, 12, 13] as $size) {
    foreach (range(1, $size - 1) as $target) {
        foreach ([true, false] as $deferred) {
            foreach ([true, false] as $trigger) {
                $prefix = sprintf(
                    'real upstream fkey8 parent replace size %02d target %02d %s %s',
                    $size,
                    $target,
                    $deferred ? 'deferred' : 'immediate',
                    $trigger ? 'triggered' : 'plain',
                );
                $plan = static fn (): array => $replaceParent($size, $target, $trigger, $deferred);
                foreach ([
                    'status' => 'commit-ok',
                    'uses_statement_journal' => true,
                    'deferred_counter' => 0,
                    'statement_counter' => 0,
                    'implicit_deletes.0.row.setting_id' => $target,
                    'implicit_deletes.0.reason' => 'replace-conflict',
                    'foreign_key_violations' => [],
                    'dependencies.0' => 'sqlite-fkey8-replace-implicit-delete-counter',
                ] as $path => $expected) {
                    $tests[$prefix . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                        $t->same($expected, $value($plan(), (string) $path));
                    };
                }

                $tests[$prefix . ' parent cardinality'] = static function (TestRunner $t) use ($plan, $size, $trigger): void {
                    $t->same($size, count($plan()['parent']));
                };
                $tests[$prefix . ' trigger effect count'] = static function (TestRunner $t) use ($plan, $trigger): void {
                    $t->same($trigger ? 1 : 0, count($plan()['trigger_effects']));
                };
                $tests[$prefix . ' trigger effect replacement key'] = static function (TestRunner $t) use ($plan, $trigger, $target): void {
                    $effects = $plan()['trigger_effects'];
                    $t->same($trigger ? [$target + 1] : [], array_column($effects, 'replaced_key'));
                };
            }
        }
    }
}

foreach ([4, 5, 6, 7, 8, 9, 10, 11, 12, 13] as $size) {
    foreach (range(1, $size - 1) as $priorDeleted) {
        foreach ([true, false] as $deferred) {
            $replacementKey = $priorDeleted === $size ? 1 : $priorDeleted + 1;
            $prefix = sprintf(
                'real upstream fkey8 prior delete replace size %02d deleted %02d replacing %02d %s',
                $size,
                $priorDeleted,
                $replacementKey,
                $deferred ? 'deferred' : 'immediate',
            );
            $plan = static fn (): array => $replaceParent($size, $replacementKey, false, $deferred, $priorDeleted);
            $hasDanglingChild = $priorDeleted % 2 === 0;
            $expectedStatus = $hasDanglingChild ? ($deferred ? 'deferred-commit-blocked' : 'statement-blocked') : 'commit-ok';
            $expectedViolation = $hasDanglingChild
                ? [['child_index' => (int) (($priorDeleted / 2) - 1), 'child_key' => $priorDeleted, 'parent' => 'setting_id', 'phase' => $deferred ? 'deferred-commit' : 'statement']]
                : [];

            foreach ([
                'status' => $expectedStatus,
                'prior_deletes.0.row.setting_id' => $priorDeleted,
                'prior_deletes.0.reason' => 'statement-delete',
                'implicit_deletes.0.row.setting_id' => $replacementKey,
                'deferred_counter' => $deferred ? count($expectedViolation) : 0,
                'statement_counter' => $deferred ? 0 : count($expectedViolation),
                'foreign_key_violations' => $expectedViolation,
                'uses_statement_journal' => true,
                'dependencies.0' => 'sqlite-fkey8-replace-implicit-delete-counter',
            ] as $path => $expected) {
                $tests[$prefix . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                    $t->same($expected, $value($plan(), (string) $path));
                };
            }
        }
    }
}

foreach ([3, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $size) {
    foreach (range(1, $size) as $target) {
        foreach ([true, false] as $deferred) {
            $prefix = sprintf(
                'real upstream fkey8 child replace cancellation size %02d target %02d %s',
                $size,
                $target,
                $deferred ? 'deferred' : 'immediate',
            );
            $plan = static fn (): array => $replaceChild($size, $target, $deferred);

            foreach ([
                'status' => 'commit-ok',
                'deferred_counter' => 0,
                'statement_counter' => 0,
                'implicit_deletes.0.row.child_id' => $target,
                'implicit_deletes.0.reason' => 'replace-conflict',
                'foreign_key_violations' => [],
                'dependencies.0' => 'sqlite-fkey8-replace-child-counter-cancellation',
            ] as $path => $expected) {
                $tests[$prefix . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                    $t->same($expected, $value($plan(), (string) $path));
                };
            }

            $tests[$prefix . ' child cardinality preserved'] = static function (TestRunner $t) use ($plan, $size): void {
                $t->same($size, count($plan()['child']));
            };
            $tests[$prefix . ' replacement payload is last row'] = static function (TestRunner $t) use ($plan, $target): void {
                $children = $plan()['child'];
                $t->same('replacement_child_' . $target, $children[count($children) - 1]['payload']);
            };
        }
    }
}

$guardCases = [
    'malformed parent key is rejected' => static fn (): array => SQLiteForeignKeyReplaceCounterPlan::replaceParent([], [], ['parent_key' => 'bad key', 'child_key' => 'setting_id'], ['setting_id' => 1]),
    'malformed child key is rejected' => static fn (): array => SQLiteForeignKeyReplaceCounterPlan::replaceParent([], [], ['parent_key' => 'setting_id', 'child_key' => 'bad key'], ['setting_id' => 1]),
    'missing parent replacement key is rejected' => static fn (): array => SQLiteForeignKeyReplaceCounterPlan::replaceParent([], [], ['parent_key' => 'setting_id', 'child_key' => 'setting_id'], ['label' => 'x']),
    'missing child replacement key is rejected' => static fn (): array => SQLiteForeignKeyReplaceCounterPlan::replaceChild([], ['child_key' => 'child_id'], ['payload' => 'x']),
    'trigger replacement missing parent key is rejected' => static fn (): array => SQLiteForeignKeyReplaceCounterPlan::replaceParent([['setting_id' => 1]], [], ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'trigger_replace_parent' => ['label' => 'x']], ['setting_id' => 1]),
];

foreach ($guardCases as $name => $callable) {
    $tests['real upstream corpus fkey8 replace counter ' . $name] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $callable);
}

return $tests;
