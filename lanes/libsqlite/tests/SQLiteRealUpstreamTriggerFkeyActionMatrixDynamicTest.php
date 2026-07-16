<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerForeignKeyReturningPlan;

$parents = static function (int $seed): array {
    return [
        ['setting_id' => 1, 'setting_key' => 'default-' . $seed, 'payload' => 'parent-default'],
        ['setting_id' => 2, 'setting_key' => 'active-' . $seed, 'payload' => 'parent-active'],
        ['setting_id' => 3, 'setting_key' => 'other-' . $seed, 'payload' => 'parent-other'],
    ];
};

$children = static function (int $seed): array {
    return [
        ['child_id' => 10, 'parent_key' => 'active-' . $seed, 'payload' => 'matched-a'],
        ['child_id' => 11, 'parent_key' => 'active-' . $seed, 'payload' => 'matched-b'],
        ['child_id' => 12, 'parent_key' => 'other-' . $seed, 'payload' => 'other-parent'],
        ['child_id' => 13, 'parent_key' => null, 'payload' => 'null-key'],
        ['child_id' => 14, 'parent_key' => 'active-' . $seed, 'payload' => 'matched-c'],
    ];
};

$fk = static function (int $seed, string $onUpdate, string $onDelete, bool $deferred = false): array {
    return [
        'parent_key' => 'setting_key',
        'child_key' => 'parent_key',
        'on_update' => $onUpdate,
        'on_delete' => $onDelete,
        'deferred' => $deferred,
        'child_default' => 'default-' . $seed,
    ];
};

$childRefs = static fn (array $result): array => array_values(array_map(
    static fn (array $row): mixed => $row['parent_key'],
    $result['child'],
));

$tests = [
    'real upstream e_fkey action matrix cites section 4.3 action block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test');
        $t->true(is_string($source) && str_contains($source, '### SECTION 4.3: ON DELETE and ON UPDATE Actions'));
        $t->true(is_string($source) && str_contains($source, 'A single foreign key constraint may have'));
    },
    'real upstream e_fkey action matrix cites action vocabulary block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test');
        $t->true(is_string($source) && str_contains($source, '"NO ACTION", "RESTRICT", "SET NULL", "SET DEFAULT" or "CASCADE"'));
        $t->true(is_string($source) && str_contains($source, 'If none is specified explicitly, "NO ACTION" is the default'));
    },
    'real upstream e_fkey action matrix cites concrete set null default cascade tests' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test');
        $t->true(is_string($source) && str_contains($source, 'Test SET NULL actions.'));
        $t->true(is_string($source) && str_contains($source, 'Test SET DEFAULT actions.'));
        $t->true(is_string($source) && str_contains($source, 'Test ON DELETE CASCADE actions.'));
        $t->true(is_string($source) && str_contains($source, 'Test ON UPDATE CASCADE actions.'));
    },
    'real upstream e_fkey action matrix cites no action deferred distinction' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test');
        $t->true(is_string($source) && str_contains($source, 'Configuring "NO ACTION" means just that'));
        $t->true(is_string($source) && str_contains($source, 'Deferred foreign key constraints are not'));
        $t->true(is_string($source) && str_contains($source, 'checked until the transaction tries to COMMIT'));
    },
];

$scenarios = [
    'e_fkey-43 update set null' => [
        'event' => 'update',
        'update' => 'set null',
        'delete' => 'no action',
        'deferred' => false,
        'refs' => [null, null, 'other-%d', null, null],
        'actions' => 3,
        'violations' => 0,
    ],
    'e_fkey-45 update set default' => [
        'event' => 'update',
        'update' => 'set default',
        'delete' => 'no action',
        'deferred' => false,
        'refs' => ['default-%d', 'default-%d', 'other-%d', null, 'default-%d'],
        'actions' => 3,
        'violations' => 0,
    ],
    'e_fkey-47 update cascade' => [
        'event' => 'update',
        'update' => 'cascade',
        'delete' => 'no action',
        'deferred' => false,
        'refs' => ['renamed-%d', 'renamed-%d', 'other-%d', null, 'renamed-%d'],
        'actions' => 3,
        'violations' => 0,
    ],
    'e_fkey-39 update no action deferred' => [
        'event' => 'update',
        'update' => 'no action',
        'delete' => 'no action',
        'deferred' => true,
        'refs' => ['active-%d', 'active-%d', 'other-%d', null, 'active-%d'],
        'actions' => 3,
        'violations' => 6,
    ],
    'e_fkey-43 delete set null' => [
        'event' => 'delete',
        'update' => 'no action',
        'delete' => 'set null',
        'deferred' => false,
        'refs' => [null, null, 'other-%d', null, null],
        'actions' => 3,
        'violations' => 0,
    ],
    'e_fkey-45 delete set default' => [
        'event' => 'delete',
        'update' => 'no action',
        'delete' => 'set default',
        'deferred' => false,
        'refs' => ['default-%d', 'default-%d', 'other-%d', null, 'default-%d'],
        'actions' => 3,
        'violations' => 0,
    ],
    'e_fkey-46 delete cascade' => [
        'event' => 'delete',
        'update' => 'no action',
        'delete' => 'cascade',
        'deferred' => false,
        'refs' => ['other-%d', null],
        'actions' => 3,
        'violations' => 0,
    ],
    'e_fkey-39 delete no action deferred' => [
        'event' => 'delete',
        'update' => 'no action',
        'delete' => 'no action',
        'deferred' => true,
        'refs' => ['active-%d', 'active-%d', 'other-%d', null, 'active-%d'],
        'actions' => 3,
        'violations' => 6,
    ],
];

for ($seed = 1; $seed <= 125; ++$seed) {
    foreach ($scenarios as $name => $scenario) {
        $case = 'real upstream trigger fkey action matrix dynamic ' . $name . ' seed ' . $seed;
        $tests[$case] = static function (TestRunner $t) use ($parents, $children, $fk, $childRefs, $seed, $scenario): void {
            if ($scenario['event'] === 'update') {
                $result = SQLiteTriggerForeignKeyReturningPlan::updateParents(
                    $parents($seed),
                    $children($seed),
                    ['setting_key' => 'renamed-' . $seed],
                    static fn (array $row): bool => $row['setting_key'] === 'active-' . $seed,
                    $fk($seed, $scenario['update'], $scenario['delete'], $scenario['deferred']),
                    [],
                    [
                        ['expr' => 'old.setting_key', 'as' => 'old_key'],
                        ['expr' => 'new.setting_key', 'as' => 'new_key'],
                    ],
                    'setting_id',
                );
            } else {
                $result = SQLiteTriggerForeignKeyReturningPlan::deleteParents(
                    $parents($seed),
                    $children($seed),
                    static fn (array $row): bool => $row['setting_key'] === 'active-' . $seed,
                    $fk($seed, $scenario['update'], $scenario['delete'], $scenario['deferred']),
                    [],
                    [
                        ['expr' => 'old.setting_key', 'as' => 'old_key'],
                    ],
                    'setting_id',
                );
            }

            $expectedRefs = array_map(
                static fn (mixed $value): mixed => is_string($value) ? sprintf($value, $seed) : $value,
                $scenario['refs'],
            );

            $t->same($expectedRefs, $childRefs($result));
            $t->same($scenario['actions'], count($result['foreign_key_actions']));
            $t->same($scenario['violations'], count($result['foreign_key_violations']));
            $t->same(1, $result['changes']);
            $t->same(1, count($result['yielded']));
        };
    }
}

return $tests;
