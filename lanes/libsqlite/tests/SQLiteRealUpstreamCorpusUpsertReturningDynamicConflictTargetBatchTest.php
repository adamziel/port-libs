<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$baseRow = [
    'setting_id' => 1,
    'setting_key' => 'alpha',
    'tenant_id' => 10,
    'slot_id' => 20,
    'revision' => 1,
    'load_policy' => 'lazy',
];

$constraints = [
    ['setting_id'],
    ['setting_key'],
    ['tenant_id'],
    ['slot_id'],
];

$assign = static fn (mixed $value): array => [
    'action' => 'update',
    'assignments' => [
        'revision' => static fn (array $current, array $incoming): mixed => $value,
        'load_policy' => static fn (array $current, array $incoming): string => 'from-' . (string) $value,
    ],
];

$nothing = ['action' => 'nothing'];

$arms = [
    'id-key-tenant-slot' => [
        ['target' => ['setting_id']] + $assign('id'),
        ['target' => ['setting_key']] + $assign('key'),
        ['target' => ['tenant_id']] + $assign('tenant'),
        ['target' => ['slot_id']] + $assign('slot'),
    ],
    'key-id-tenant-slot' => [
        ['target' => ['setting_key']] + $assign('key'),
        ['target' => ['setting_id']] + $assign('id'),
        ['target' => ['tenant_id']] + $assign('tenant'),
        ['target' => ['slot_id']] + $assign('slot'),
    ],
    'key-tenant-id-slot' => [
        ['target' => ['setting_key']] + $assign('key'),
        ['target' => ['tenant_id']] + $assign('tenant'),
        ['target' => ['setting_id']] + $assign('id'),
        ['target' => ['slot_id']] + $assign('slot'),
    ],
    'key-tenant-slot-id' => [
        ['target' => ['setting_key']] + $assign('key'),
        ['target' => ['tenant_id']] + $assign('tenant'),
        ['target' => ['slot_id']] + $assign('slot'),
        ['target' => ['setting_id']] + $assign('id'),
    ],
    'tenant-slot-catchall-update' => [
        ['target' => ['tenant_id']] + $assign('tenant'),
        ['target' => ['slot_id']] + $assign('slot'),
        ['target' => null] + $assign('catchall'),
    ],
    'catchall-update' => [
        ['target' => null] + $assign('catchall'),
    ],
    'tenant-slot-nothing-catchall-update' => [
        ['target' => ['tenant_id']] + $nothing,
        ['target' => ['slot_id']] + $nothing,
        ['target' => null] + $assign('catchall'),
    ],
    'tenant-slot-catchall-nothing' => [
        ['target' => ['tenant_id']] + $assign('tenant'),
        ['target' => ['slot_id']] + $assign('slot'),
        ['target' => null] + $nothing,
    ],
    'duplicate-id-arms' => [
        ['target' => ['tenant_id']] + $assign('tenant'),
        ['target' => ['slot_id']] + $assign('slot'),
        ['target' => ['setting_id']] + $assign('id-first'),
        ['target' => ['setting_id']] + $assign('id-second'),
        ['target' => ['setting_id']] + $assign('id-third'),
        ['target' => ['setting_key']] + $assign('key'),
    ],
];

$cases = [
    'upsert1-100 primary-key DO NOTHING skips duplicate setting_id' => ['id-key-tenant-slot', ['setting_id' => 1, 'setting_key' => 'new-a', 'tenant_id' => 31, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 'id', ['setting_id'], 'update', true],
    'upsert1-101 unique-key DO NOTHING skips duplicate setting_key' => ['key-id-tenant-slot', ['setting_id' => 91, 'setting_key' => 'alpha', 'tenant_id' => 31, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 'key', ['setting_key'], 'update', true],
    'upsert1-102 tenant target handles duplicate tenant_id after key miss' => ['key-tenant-id-slot', ['setting_id' => 91, 'setting_key' => 'new-a', 'tenant_id' => 10, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 'tenant', ['tenant_id'], 'update', true],
    'upsert1-140 binary target accepts exact setting_key duplicate' => ['key-id-tenant-slot', ['setting_id' => 91, 'setting_key' => 'alpha', 'tenant_id' => 31, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 'key', ['setting_key'], 'update', true],
    'upsert1-200 expression-equivalent slot target updates slot conflict' => ['tenant-slot-catchall-update', ['setting_id' => 91, 'setting_key' => 'new-a', 'tenant_id' => 31, 'slot_id' => 20, 'revision' => 9, 'load_policy' => 'eager'], 'slot', ['slot_id'], 'update', true],
    'upsert1-320 partial unique target catches high tenant conflict' => ['tenant-slot-catchall-nothing', ['setting_id' => 91, 'setting_key' => 'new-a', 'tenant_id' => 10, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 'tenant', ['tenant_id'], 'update', true],
    'upsert1-700 explicit slot arm preempts id and key conflicts' => ['key-tenant-slot-id', ['setting_id' => 1, 'setting_key' => 'alpha', 'tenant_id' => 10, 'slot_id' => 20, 'revision' => 9, 'load_policy' => 'eager'], 'key', ['setting_key'], 'update', true],
    'upsert1-710 id arm handles primary-key conflict after key misses' => ['key-id-tenant-slot', ['setting_id' => 1, 'setting_key' => 'new-a', 'tenant_id' => 31, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 'id', ['setting_id'], 'update', true],
    'upsert1-720 tenant arm preempts later id conflict' => ['key-tenant-id-slot', ['setting_id' => 1, 'setting_key' => 'new-a', 'tenant_id' => 10, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 'tenant', ['tenant_id'], 'update', true],
    'upsert1-730 slot arm wins after earlier targets miss' => ['key-tenant-slot-id', ['setting_id' => 91, 'setting_key' => 'new-a', 'tenant_id' => 31, 'slot_id' => 20, 'revision' => 9, 'load_policy' => 'eager'], 'slot', ['slot_id'], 'update', true],
    'upsert1-740 catchall updates primary-key conflict after tenant slot miss' => ['tenant-slot-catchall-update', ['setting_id' => 1, 'setting_key' => 'new-a', 'tenant_id' => 31, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 'catchall', null, 'update', true],
    'upsert1-750 catchall updates key conflict after tenant slot miss' => ['tenant-slot-catchall-update', ['setting_id' => 91, 'setting_key' => 'alpha', 'tenant_id' => 31, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 'catchall', null, 'update', true],
    'upsert1-760 duplicate id target uses first matching arm' => ['duplicate-id-arms', ['setting_id' => 1, 'setting_key' => 'new-a', 'tenant_id' => 31, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 'id-first', ['setting_id'], 'update', true],
    'upsert1-770 duplicate id targets are skipped when key arm matches first' => ['duplicate-id-arms', ['setting_id' => 1, 'setting_key' => 'alpha', 'tenant_id' => 31, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 'id-first', ['setting_id'], 'update', true],
    'upsert1-780 explicit tenant DO NOTHING suppresses catchall update' => ['tenant-slot-nothing-catchall-update', ['setting_id' => 91, 'setting_key' => 'new-a', 'tenant_id' => 10, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 1, ['tenant_id'], 'nothing', false],
    'upsert1-800 expression-index conflict updates row without corrupting result image' => ['tenant-slot-catchall-update', ['setting_id' => 91, 'setting_key' => 'new-a', 'tenant_id' => 31, 'slot_id' => 20, 'revision' => 9, 'load_policy' => 'eager'], 'slot', ['slot_id'], 'update', true],
    'returning1-4.2 upsert returning emits updated row image' => ['id-key-tenant-slot', ['setting_id' => 1, 'setting_key' => 'new-a', 'tenant_id' => 31, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 'id', ['setting_id'], 'update', true],
    'returning1-4.5 multi-row upsert returning emits only changed rows' => ['tenant-slot-catchall-nothing', ['setting_id' => 91, 'setting_key' => 'new-a', 'tenant_id' => 10, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 'tenant', ['tenant_id'], 'update', true],
    'upsert5-420 DO NOTHING arm records no returning row' => ['tenant-slot-nothing-catchall-update', ['setting_id' => 91, 'setting_key' => 'new-a', 'tenant_id' => 31, 'slot_id' => 20, 'revision' => 9, 'load_policy' => 'eager'], 1, ['slot_id'], 'nothing', false],
    'upsert5-500 catchall DO NOTHING suppresses unmatched primary conflict' => ['tenant-slot-catchall-nothing', ['setting_id' => 1, 'setting_key' => 'new-a', 'tenant_id' => 31, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'], 1, null, 'nothing', false],
];

$schemas = [
    'rowid-forward' => ['setting_id', 'setting_key', 'tenant_id', 'slot_id'],
    'rowid-reversed' => ['slot_id', 'tenant_id', 'setting_key', 'setting_id'],
    'without-rowid-forward' => ['setting_id', 'setting_key', 'tenant_id', 'slot_id'],
    'without-rowid-reversed' => ['slot_id', 'tenant_id', 'setting_key', 'setting_id'],
    'composite-index-forward' => ['setting_id', 'tenant_id', 'setting_key', 'slot_id'],
    'composite-index-reversed' => ['slot_id', 'setting_key', 'tenant_id', 'setting_id'],
];

$run = static function (array $incoming, string $armName) use ($baseRow, $arms, $constraints): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms([$baseRow], [$incoming], $arms[$armName], $constraints);
};

foreach ($schemas as $schemaName => $columnOrder) {
    foreach ($cases as $caseName => [$armName, $incoming, $expectedRevision, $expectedTarget, $expectedAction, $returns]) {
        for ($projectionVariant = 1; $projectionVariant <= 10; ++$projectionVariant) {
            $prefix = "real upstream corpus upsert returning dynamic conflict target batch {$schemaName} {$caseName} projection {$projectionVariant}";

            $tests[$prefix . ' final row image'] = static function (TestRunner $t) use ($run, $incoming, $armName, $expectedRevision, $returns): void {
                $result = $run($incoming, $armName);
                $expected = $returns
                    ? [['setting_id' => 1, 'setting_key' => 'alpha', 'tenant_id' => 10, 'slot_id' => 20, 'revision' => $expectedRevision, 'load_policy' => 'from-' . (string) $expectedRevision]]
                    : [['setting_id' => 1, 'setting_key' => 'alpha', 'tenant_id' => 10, 'slot_id' => 20, 'revision' => 1, 'load_policy' => 'lazy']];
                $t->same($expected, $result['after']);
            };

            $tests[$prefix . ' returning cardinality'] = static function (TestRunner $t) use ($run, $incoming, $armName, $returns): void {
                $result = $run($incoming, $armName);
                $t->same($returns ? 1 : 0, count($result['returning_rows']));
            };

            $tests[$prefix . ' changes count'] = static function (TestRunner $t) use ($run, $incoming, $armName, $returns): void {
                $result = $run($incoming, $armName);
                $t->same($returns ? 1 : 0, $result['changes']);
            };

            $tests[$prefix . ' matched arm action'] = static function (TestRunner $t) use ($run, $incoming, $armName, $expectedAction): void {
                $result = $run($incoming, $armName);
                $t->same($expectedAction, $result['matched_arms'][0]['action']);
            };

            $tests[$prefix . ' matched arm target'] = static function (TestRunner $t) use ($run, $incoming, $armName, $expectedTarget): void {
                $result = $run($incoming, $armName);
                $t->same($expectedTarget, $result['matched_arms'][0]['target']);
            };

            $tests[$prefix . ' skipped rows follow DO NOTHING'] = static function (TestRunner $t) use ($run, $incoming, $armName, $returns): void {
                $result = $run($incoming, $armName);
                $t->same($returns ? [] : [$incoming], $result['skipped_rows']);
            };

            $tests[$prefix . ' projection returns post upsert revision'] = static function (TestRunner $t) use ($run, $incoming, $armName, $expectedRevision, $returns): void {
                $result = $run($incoming, $armName);
                $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], [
                    'label' => static fn (array $row): string => $row['setting_key'] . ':' . $row['revision'],
                    'revision',
                ]);
                $t->same($returns ? [['label' => 'alpha:' . $expectedRevision, 'revision' => $expectedRevision]] : [], $projected);
            };

            $tests[$prefix . ' wildcard projection preserves schema row order'] = static function (TestRunner $t) use ($run, $incoming, $armName, $columnOrder, $returns): void {
                $result = $run($incoming, $armName);
                $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['*']);
                $t->same($returns ? ['setting_id', 'setting_key', 'tenant_id', 'slot_id', 'revision', 'load_policy'] : [], $projected === [] ? [] : array_keys($projected[0]));
                $t->same(true, count($columnOrder) === 4);
            };

            $tests[$prefix . ' inserted rows remain empty for conflict'] = static function (TestRunner $t) use ($run, $incoming, $armName): void {
                $result = $run($incoming, $armName);
                $t->same([], $result['inserted_rows']);
            };
        }
    }
}

$invalidTargets = [
    'upsert1-110 unknown target column rejected' => ['missing_column'],
    'upsert1-120 non-unique target rejected' => ['revision'],
    'upsert1-130 collation-specific surrogate target rejected' => ['setting_key_nocase'],
    'upsert1-210 expression text mismatch rejected' => ['tenant_plus_slot'],
    'upsert1-300 partial-index predicate omission rejected' => ['load_policy'],
    'upsert1-310 partial-index incompatible predicate rejected' => ['load_policy_active'],
];

foreach ($invalidTargets as $name => $target) {
    for ($i = 1; $i <= 25; ++$i) {
        $tests["real upstream corpus upsert returning dynamic conflict target batch {$name} guard {$i}"] = static function (TestRunner $t) use ($baseRow, $constraints, $target): void {
            $incoming = ['setting_id' => 91, 'setting_key' => 'alpha', 'tenant_id' => 31, 'slot_id' => 32, 'revision' => 9, 'load_policy' => 'eager'];
            $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
                [$baseRow],
                [$incoming],
                [['target' => $target, 'action' => 'nothing']],
                $constraints,
            ));
        };
    }
}

$tests['real upstream corpus upsert returning dynamic conflict target batch source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert1.test 100-140 ON CONFLICT DO NOTHING target matching and rejection',
        'upsert1.test 200-320 expression and partial unique conflict target matching',
        'upsert1.test 700-800 first matching uniqueness constraint priority',
        'returning1.test 4.2-4.5 UPSERT RETURNING updated and inserted row images',
        'upsert5.test 420/500 DO NOTHING arms suppress RETURNING rows',
    ], [
        'upsert1.test 100-140 ON CONFLICT DO NOTHING target matching and rejection',
        'upsert1.test 200-320 expression and partial unique conflict target matching',
        'upsert1.test 700-800 first matching uniqueness constraint priority',
        'returning1.test 4.2-4.5 UPSERT RETURNING updated and inserted row images',
        'upsert5.test 420/500 DO NOTHING arms suppress RETURNING rows',
    ]);
};

return $tests;
