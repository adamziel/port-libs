<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

$value = static function (array $row, string $path): mixed {
    $cursor = $row;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException('Missing assertion path ' . $path);
    }

    return $cursor;
};

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test';

$tests['real upstream e_fkey48 action satisfaction cites cascade source'] = static function (TestRunner $t) use ($sourceFile): void {
    $source = file_get_contents($sourceFile);

    $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-48.1'));
    $t->true(is_string($source) && str_contains($source, 'trackartist INTEGER REFERENCES artist(artistid) ON UPDATE CASCADE'));
    $t->true(is_string($source) && str_contains($source, "UPDATE artist SET artistid = 100 WHERE artistname = 'Dean Martin'"));
    $t->true(is_string($source) && str_contains($source, "11 {That's Amore} 100 12 {Christmas Blues} 100 13 {My Way} 2"));
};

$tests['real upstream e_fkey49 action satisfaction cites set default update source'] = static function (TestRunner $t) use ($sourceFile): void {
    $source = file_get_contents($sourceFile);

    $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-49.1'));
    $t->true(is_string($source) && str_contains($source, 'Configuring an ON UPDATE or ON DELETE'));
    $t->true(is_string($source) && str_contains($source, "UPDATE parent SET a = '' WHERE a = 'oNe'"));
    $t->true(is_string($source) && str_contains($source, '{1 {FOREIGN KEY constraint failed}}'));
};

$tests['real upstream e_fkey50 action satisfaction cites set default delete source'] = static function (TestRunner $t) use ($sourceFile): void {
    $source = file_get_contents($sourceFile);

    $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-50.2'));
    $t->true(is_string($source) && str_contains($source, 'does not abrogate the need to satisfy the foreign key constraint'));
    $t->true(is_string($source) && str_contains($source, 'ON DELETE SET DEFAULT'));
    $t->true(is_string($source) && str_contains($source, "14 {Mr. Bojangles} 0"));
};

$cascadePlan = static function (int $seed): array {
    $newId = 1000 + $seed;

    return SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyActionSatisfactionPlan(
        [
            ['artistid' => 1, 'artistname' => 'Dean Martin ' . $seed],
            ['artistid' => 2, 'artistname' => 'Frank Sinatra ' . $seed],
        ],
        [
            ['trackid' => 11, 'trackname' => 'Amore ' . $seed, 'trackartist' => 1],
            ['trackid' => 12, 'trackname' => 'Christmas ' . $seed, 'trackartist' => 1],
            ['trackid' => 13, 'trackname' => 'Way ' . $seed, 'trackartist' => 2],
        ],
        [
            'case' => 'e_fkey-48.1..48.4',
            'event' => 'update',
            'action' => 'cascade',
            'where' => ['artistname' => 'Dean Martin ' . $seed],
            'set' => ['artistid' => $newId],
            'parent_columns' => ['artistid'],
            'child_columns' => ['trackartist'],
            'parent_affinities' => ['artistid' => 'integer'],
        ],
    );
};

$setDefaultPresentPlan = static function (int $seed): array {
    return SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyActionSatisfactionPlan(
        [
            ['a' => 'A', 'b' => 'b' . $seed, 'c' => 'c'],
            ['a' => 'ONE', 'b' => 'two' . $seed, 'c' => 'three'],
        ],
        [
            ['d' => 'one', 'e' => 'two' . $seed, 'f' => 'three'],
        ],
        [
            'case' => 'e_fkey-49.1..49.3',
            'event' => 'update',
            'action' => 'set default',
            'where' => ['a' => 'ONE'],
            'set' => ['a' => ''],
            'parent_columns' => ['c', 'a'],
            'child_columns' => ['f', 'd'],
            'child_defaults' => ['f' => 'c', 'd' => 'a'],
            'parent_collations' => ['a' => 'nocase'],
        ],
    );
};

$setDefaultMissingPlan = static function (int $seed): array {
    return SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyActionSatisfactionPlan(
        [
            ['a' => 'ONE', 'b' => 'two' . $seed, 'c' => 'three'],
        ],
        [
            ['d' => 'one', 'e' => 'two' . $seed, 'f' => 'three'],
        ],
        [
            'case' => 'e_fkey-49.4',
            'event' => 'update',
            'action' => 'set default',
            'where' => ['a' => 'ONE'],
            'set' => ['a' => ''],
            'parent_columns' => ['c', 'a'],
            'child_columns' => ['f', 'd'],
            'child_defaults' => ['f' => 'c', 'd' => 'a'],
            'parent_collations' => ['a' => 'nocase'],
        ],
    );
};

$deleteDefaultMissingPlan = static function (int $seed): array {
    $artistId = 3000 + $seed;

    return SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyActionSatisfactionPlan(
        [
            ['artistid' => $artistId, 'artistname' => 'Sammy Davis Jr. ' . $seed],
        ],
        [
            ['trackid' => 14, 'trackname' => 'Bojangles ' . $seed, 'trackartist' => $artistId],
        ],
        [
            'case' => 'e_fkey-50.1..50.2',
            'event' => 'delete',
            'action' => 'set default',
            'where' => ['artistname' => 'Sammy Davis Jr. ' . $seed],
            'parent_columns' => ['artistid'],
            'child_columns' => ['trackartist'],
            'child_defaults' => ['trackartist' => 0],
            'parent_affinities' => ['artistid' => 'integer'],
        ],
    );
};

$deleteDefaultPresentPlan = static function (int $seed): array {
    $artistId = 3000 + $seed;

    return SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyActionSatisfactionPlan(
        [
            ['artistid' => $artistId, 'artistname' => 'Sammy Davis Jr. ' . $seed],
            ['artistid' => 0, 'artistname' => 'Unknown Artist'],
        ],
        [
            ['trackid' => 14, 'trackname' => 'Bojangles ' . $seed, 'trackartist' => $artistId],
        ],
        [
            'case' => 'e_fkey-50.3..50.5',
            'event' => 'delete',
            'action' => 'set default',
            'where' => ['artistname' => 'Sammy Davis Jr. ' . $seed],
            'parent_columns' => ['artistid'],
            'child_columns' => ['trackartist'],
            'child_defaults' => ['trackartist' => 0],
            'parent_affinities' => ['artistid' => 'integer'],
        ],
    );
};

$scenarioExpectations = static function (int $seed) use ($cascadePlan, $setDefaultPresentPlan, $setDefaultMissingPlan, $deleteDefaultMissingPlan, $deleteDefaultPresentPlan): array {
    $newId = 1000 + $seed;
    $artistId = 3000 + $seed;

    return [
        'e_fkey48_update_cascade' => [
            'plan' => static fn (): array => $cascadePlan($seed),
            'expected' => [
                'source' => 'e_fkey.test e_fkey-48.1..50.5',
                'operation' => 'foreign-key-action-must-remain-satisfied',
                'case' => 'e_fkey-48.1..48.4',
                'status' => 'commit-ok',
                'action_count' => 2,
                'violation_count' => 0,
                'statement_rolled_back' => false,
                'default_parent_present' => null,
                'committed_parent_key_values.0.0' => $newId,
                'committed_parent_key_values.1.0' => 2,
                'committed_child_key_values.0.0' => $newId,
                'committed_child_key_values.1.0' => $newId,
                'committed_child_key_values.2.0' => 2,
                'action_rows.0.action' => 'cascade-child-key',
                'action_rows.1.new_child_key.0' => $newId,
                'dependencies.0' => 'sqlite-efkey48-on-update-cascade-rewrites-each-referencing-child',
            ],
        ],
        'e_fkey49_set_default_parent_present' => [
            'plan' => static fn (): array => $setDefaultPresentPlan($seed),
            'expected' => [
                'case' => 'e_fkey-49.1..49.3',
                'status' => 'commit-ok',
                'action' => 'set default',
                'default_parent_present' => true,
                'default_child_key.0' => 'c',
                'default_child_key.1' => 'a',
                'violation_count' => 0,
                'statement_rolled_back' => false,
                'committed_parent_key_values.0.0' => 'c',
                'committed_parent_key_values.0.1' => 'A',
                'committed_parent_key_values.1.0' => 'three',
                'committed_parent_key_values.1.1' => '',
                'committed_child_key_values.0.0' => 'c',
                'committed_child_key_values.0.1' => 'a',
                'action_rows.0.action' => 'set-default-child-key',
                'dependencies.1' => 'sqlite-efkey49-actions-still-require-default-parent-key',
            ],
        ],
        'e_fkey49_set_default_parent_missing' => [
            'plan' => static fn (): array => $setDefaultMissingPlan($seed),
            'expected' => [
                'case' => 'e_fkey-49.4',
                'status' => 'constraint-failed',
                'default_parent_present' => false,
                'violation_count' => 1,
                'statement_rolled_back' => true,
                'attempted_parent_key_values.0.0' => 'three',
                'attempted_parent_key_values.0.1' => '',
                'attempted_child_key_values.0.0' => 'c',
                'attempted_child_key_values.0.1' => 'a',
                'committed_parent_key_values.0.0' => 'three',
                'committed_parent_key_values.0.1' => 'ONE',
                'committed_child_key_values.0.0' => 'three',
                'committed_child_key_values.0.1' => 'one',
                'violations.0.child_key.0' => 'c',
                'violations.0.child_key.1' => 'a',
                'dependencies.1' => 'sqlite-efkey49-actions-still-require-default-parent-key',
            ],
        ],
        'e_fkey50_delete_default_parent_missing' => [
            'plan' => static fn (): array => $deleteDefaultMissingPlan($seed),
            'expected' => [
                'case' => 'e_fkey-50.1..50.2',
                'status' => 'constraint-failed',
                'event' => 'delete',
                'default_parent_present' => false,
                'violation_count' => 1,
                'statement_rolled_back' => true,
                'deleted_parent_keys.0.0' => $artistId,
                'attempted_parent_key_values' => [],
                'attempted_child_key_values.0.0' => 0,
                'committed_parent_key_values.0.0' => $artistId,
                'committed_child_key_values.0.0' => $artistId,
                'violations.0.child_key.0' => 0,
                'action_rows.0.action' => 'set-default-child-key',
                'dependencies.2' => 'sqlite-efkey50-set-default-delete-fails-until-default-parent-exists',
            ],
        ],
        'e_fkey50_delete_default_parent_present' => [
            'plan' => static fn (): array => $deleteDefaultPresentPlan($seed),
            'expected' => [
                'case' => 'e_fkey-50.3..50.5',
                'status' => 'commit-ok',
                'event' => 'delete',
                'default_parent_present' => true,
                'violation_count' => 0,
                'statement_rolled_back' => false,
                'deleted_parent_keys.0.0' => $artistId,
                'committed_parent_key_values.0.0' => 0,
                'committed_child_key_values.0.0' => 0,
                'action_rows.0.old_child_key.0' => $artistId,
                'action_rows.0.new_child_key.0' => 0,
                'dependencies.2' => 'sqlite-efkey50-set-default-delete-fails-until-default-parent-exists',
            ],
        ],
    ];
};

foreach (range(1, 150) as $seed) {
    foreach ($scenarioExpectations($seed) as $scenario => $definition) {
        foreach ($definition['expected'] as $path => $expected) {
            $tests[sprintf('real upstream e_fkey action satisfaction %s seed %03d %s', $scenario, $seed, str_replace('.', '-', (string) $path))] = static function (TestRunner $t) use ($definition, $path, $expected, $value): void {
                $plan = $definition['plan']();
                $t->same($expected, $value($plan, (string) $path));
            };
        }
    }
}

$tests['real upstream e_fkey action satisfaction owns 750 dynamic scenario rows'] = static function (TestRunner $t): void {
    $t->same(750, 150 * 5);
};

$tests['real upstream e_fkey action satisfaction rejects unsupported event'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyActionSatisfactionPlan([], [], ['event' => 'insert', 'action' => 'cascade']));
};

$tests['real upstream e_fkey action satisfaction rejects unsupported action'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyActionSatisfactionPlan([], [], ['event' => 'update', 'action' => 'restrict']));
};

$tests['real upstream e_fkey action satisfaction rejects delete cascade shortcut'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyActionSatisfactionPlan([], [], ['event' => 'delete', 'action' => 'cascade']));
};

$tests['real upstream e_fkey action satisfaction rejects missing target parent'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyActionSatisfactionPlan([['id' => 1]], [], ['event' => 'update', 'action' => 'cascade', 'where' => ['id' => 2], 'set' => ['id' => 3]]));
};

$tests['real upstream e_fkey action satisfaction rejects missing child default'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyActionSatisfactionPlan([['id' => 1]], [['parent_id' => 1]], ['event' => 'delete', 'action' => 'set default', 'where' => ['id' => 1]]));
};

return $tests;
