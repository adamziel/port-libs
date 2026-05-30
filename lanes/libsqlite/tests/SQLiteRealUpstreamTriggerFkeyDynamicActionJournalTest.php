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
    'real upstream fkey8 action journal cites delete action matrix' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE c1(b REFERENCES p1 ON DELETE SET NULL)'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE c1(b REFERENCES p1 ON DELETE SET DEFAULT)'));
    },
    'real upstream fkey8 action journal cites update action matrix' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test');
        $t->true(is_string($source) && str_contains($source, 'UPDATE OR IGNORE p1 SET a = ?'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE c1(b REFERENCES p1 ON UPDATE CASCADE, c)'));
    },
    'real upstream fkey8 action journal cites attached cascade regression' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test');
        $t->true(is_string($source) && str_contains($source, 'Forum: https://sqlite.org/forum/forumpost/636bd0180a'));
        $t->true(is_string($source) && str_contains($source, 'UPDATE aux.p1 SET pid = pid * 10'));
    },
];

$deleteActions = [
    'cascade' => false,
    'set null' => false,
    'set default' => true,
];
$updateActions = [
    'cascade' => false,
    'set null' => true,
    'set default' => true,
];

for ($i = 1; $i <= 86; ++$i) {
    $parents = [
        ['pid' => $i, 'label' => 'primary_' . $i],
        ['pid' => $i + 1000, 'label' => 'secondary_' . $i],
    ];
    $children = [
        ['cid' => ($i * 10) + 1, 'pid' => $i, 'payload' => 'left_' . $i],
        ['cid' => ($i * 10) + 2, 'pid' => $i, 'payload' => 'right_' . $i],
        ['cid' => ($i * 10) + 3, 'pid' => $i + 1000, 'payload' => 'kept_' . $i],
    ];
    if ($i % 5 === 0) {
        $children[] = ['cid' => ($i * 10) + 4, 'pid' => null, 'payload' => 'null_' . $i];
    }

    foreach ($deleteActions as $action => $journal) {
        $default = -$i;
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyActionJournalPlan(
            $parents,
            $children,
            ['operation' => 'delete', 'action' => $action, 'default' => $default],
        );
        $case = 'fkey8-1 delete ' . $action . ' statement journal dynamic ' . $i;
        $expectedChildPids = match ($action) {
            'cascade' => [],
            'set null' => array_fill(0, count($children), null),
            default => array_fill(0, count($children), $default),
        };
        if ($action === 'cascade' && $i % 5 === 0) {
            $expectedChildPids = [null];
        } elseif ($i % 5 === 0) {
            $expectedChildPids[count($expectedChildPids) - 1] = null;
        }

        foreach ([
            'source' => 'fkey8.test fkey8-1.2.1..1.5.3',
            'operation' => 'foreign-key-action-statement-journal-plan',
            'statement_operation' => 'delete',
            'foreign_key_action' => $action,
            'statement_journal' => $journal,
            'status' => $action === 'set default' ? 'constraint-failed' : 'commit-ok',
            'parent_pids' => [],
            'child_pids' => $expectedChildPids,
            'action_count' => count($children) - ($i % 5 === 0 ? 1 : 0),
            'rollback_image_parent_pids' => $journal ? [$i, $i + 1000] : [],
            'dependencies.0' => 'sqlite-fkey8-action-statement-journal-classification',
            'dependencies.1' => 'sqlite-fkey8-set-null-default-child-key-rewrite',
        ] as $path => $expected) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }

        $tests[$case . ' rollback child image is present only when statement journal is needed'] = static function (TestRunner $t) use ($plan, $journal, $children): void {
            $actual = $plan()['rollback_image_child_pids'];
            $t->same($journal ? array_column($children, 'pid') : [], $actual);
        };
        $tests[$case . ' action old pid preserves deleted parent key'] = static function (TestRunner $t) use ($plan, $action, $i): void {
            $actions = $plan()['actions'];
            if ($action === 'cascade') {
                $t->same($i, $actions[0]['old_pid']);
            } else {
                $t->same($i, $actions[0]['old_pid']);
            }
        };
    }
}

for ($i = 1; $i <= 86; ++$i) {
    $parents = [
        ['pid' => $i, 'label' => 'primary_' . $i],
        ['pid' => $i + 1000, 'label' => 'secondary_' . $i],
    ];
    $children = [
        ['cid' => ($i * 10) + 1, 'pid' => $i, 'payload' => 'left_' . $i],
        ['cid' => ($i * 10) + 2, 'pid' => $i, 'payload' => 'right_' . $i],
        ['cid' => ($i * 10) + 3, 'pid' => $i + 1000, 'payload' => 'kept_' . $i],
    ];

    foreach ($updateActions as $action => $journal) {
        $newPid = $i * 10;
        $default = -($i * 10);
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyActionJournalPlan(
            $parents,
            $children,
            ['operation' => 'update', 'action' => $action, 'default' => $default],
        );
        $case = 'fkey8-1 update ' . $action . ' statement journal dynamic ' . $i;
        $expectedParentPids = [$newPid, ($i + 1000) * 10];
        $expectedChildPids = match ($action) {
            'cascade' => [$newPid, $newPid, ($i + 1000) * 10],
            'set null' => [null, null, null],
            default => [$default, $default, $default],
        };

        foreach ([
            'source' => 'fkey8.test fkey8-1.6.1..1.6.4,7.1..7.3',
            'statement_operation' => 'update',
            'foreign_key_action' => $action,
            'statement_journal' => $journal,
            'status' => $action === 'set default' ? 'constraint-failed' : 'commit-ok',
            'parent_pids' => $expectedParentPids,
            'child_pids' => $expectedChildPids,
            'action_count' => count($children),
            'rollback_image_parent_pids' => $journal ? [$i, $i + 1000] : [],
            'dependencies.2' => 'sqlite-fkey8-attached-update-cascade-child-key-rewrite',
        ] as $path => $expected) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }

        $tests[$case . ' first action rewrites first child from old parent'] = static function (TestRunner $t) use ($plan, $i): void {
            $action = $plan()['actions'][0];
            $t->same($i, $action['old_pid']);
            $t->same(($i * 10) + 1, $action['cid']);
        };
        $tests[$case . ' rollback child image tracks original child keys'] = static function (TestRunner $t) use ($plan, $journal, $children): void {
            $t->same($journal ? array_column($children, 'pid') : [], $plan()['rollback_image_child_pids']);
        };
    }
}

for ($i = 1; $i <= 42; ++$i) {
    $parents = [
        ['pid' => 10 + $i, 'label' => 'aux_a_' . $i],
        ['pid' => 20 + $i, 'label' => 'aux_b_' . $i],
    ];
    $children = [
        ['cid' => 11 + $i, 'pid' => 10 + $i, 'payload' => 'aux_left_' . $i],
        ['cid' => 12 + $i, 'pid' => 10 + $i, 'payload' => 'aux_right_' . $i],
        ['cid' => 21 + $i, 'pid' => 20 + $i, 'payload' => 'aux_left_' . $i],
        ['cid' => 22 + $i, 'pid' => 20 + $i, 'payload' => 'aux_right_' . $i],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyActionJournalPlan(
        $parents,
        $children,
        ['operation' => 'update', 'action' => 'cascade', 'attached' => true],
    );
    $case = 'fkey8-7 attached update cascade dynamic ' . $i;
    foreach ([
        'source' => 'fkey8.test fkey8-1.6.1..1.6.4,7.1..7.3',
        'attached_schema' => true,
        'statement_journal' => false,
        'status' => 'commit-ok',
        'parent_pids' => [(10 + $i) * 10, (20 + $i) * 10],
        'child_pids' => [(10 + $i) * 10, (10 + $i) * 10, (20 + $i) * 10, (20 + $i) * 10],
        'violation_count' => 0,
        'action_count' => 4,
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
    $tests[$case . ' dependency cites attached update cascade rewrite'] = static function (TestRunner $t) use ($plan): void {
        $t->same('sqlite-fkey8-attached-update-cascade-child-key-rewrite', $plan()['dependencies'][2]);
    };
}

return $tests;
