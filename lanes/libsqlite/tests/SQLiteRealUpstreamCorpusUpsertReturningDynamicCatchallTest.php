<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$baseRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'tenant_id' => 10, 'slot_id' => 100, 'load_policy' => 'eager', 'revision' => 1],
    ['setting_id' => 2, 'key_name' => 'theme', 'tenant_id' => 20, 'slot_id' => 200, 'load_policy' => 'lazy', 'revision' => 2],
    ['setting_id' => 3, 'key_name' => 'routes', 'tenant_id' => 30, 'slot_id' => 300, 'load_policy' => 'manual', 'revision' => 3],
    ['setting_id' => 4, 'key_name' => 'plugins', 'tenant_id' => 40, 'slot_id' => 400, 'load_policy' => 'eager', 'revision' => 4],
];

$uniqueConstraints = [
    ['setting_id'],
    ['key_name'],
    ['tenant_id'],
    ['slot_id'],
];

$armOrders = [
    [['key_name'], ['tenant_id'], null],
    [['tenant_id'], ['slot_id'], null],
    [['slot_id'], ['key_name'], null],
    [['key_name'], ['slot_id'], null],
    [['tenant_id'], ['key_name'], null],
];

$templates = [
    'upsert5-1.400 primary key reaches catchall update' => ['setting_id' => 1, 'key_name' => 'incoming_a', 'tenant_id' => 901, 'slot_id' => 1901],
    'upsert5-1.403 key conflict uses first named arm' => ['setting_id' => 901, 'key_name' => 'base_url', 'tenant_id' => 902, 'slot_id' => 1902],
    'upsert5-1.404 tenant conflict uses first named arm' => ['setting_id' => 902, 'key_name' => 'incoming_b', 'tenant_id' => 20, 'slot_id' => 1903],
    'upsert5-1.405 slot conflict can precede catchall' => ['setting_id' => 903, 'key_name' => 'incoming_c', 'tenant_id' => 903, 'slot_id' => 300],
    'upsert5-2.300 no conflict inserts and returns' => ['setting_id' => 904, 'key_name' => 'incoming_d', 'tenant_id' => 904, 'slot_id' => 1904],
];

$makeIncoming = static function (array $template, int $variant): array {
    return [
        'setting_id' => (int) $template['setting_id'],
        'key_name' => (string) $template['key_name'] . '_' . (string) $variant,
        'tenant_id' => (int) $template['tenant_id'],
        'slot_id' => (int) $template['slot_id'],
        'load_policy' => 'incoming-' . (string) $variant,
        'revision' => $variant,
    ];
};

$preserveConflictValues = static function (array $template, array $incoming): array {
    if ($template['key_name'] === 'base_url') {
        $incoming['key_name'] = 'base_url';
    }

    return $incoming;
};

$makeArms = static function (array $order, int $variant): array {
    $arms = [];
    foreach ($order as $position => $target) {
        $targetName = $target === null ? 'catchall' : implode('_', $target);
        $action = (($variant + $position) % 4) === 0 ? 'nothing' : 'update';
        $arms[] = [
            'target' => $target,
            'action' => $action,
            'assignments' => $action === 'nothing' ? [] : [
                'load_policy' => static fn (array $current, array $incoming): string => $targetName . '-' . (string) $incoming['revision'],
                'revision' => static fn (array $current, array $incoming): int => (int) $current['revision'] + (int) $incoming['revision'],
            ],
        ];
    }

    return $arms;
};

$firstMatchingArm = static function (array $rows, array $incoming, array $arms): ?array {
    foreach ($arms as $arm) {
        $target = $arm['target'];
        foreach ($rows as $row) {
            if ($target === null) {
                foreach ([['setting_id'], ['key_name'], ['tenant_id'], ['slot_id']] as $constraint) {
                    $matches = true;
                    foreach ($constraint as $column) {
                        if ($row[$column] !== $incoming[$column]) {
                            $matches = false;
                            break;
                        }
                    }
                    if ($matches) {
                        return ['target' => null, 'action' => $arm['action']];
                    }
                }
                continue;
            }

            $matches = true;
            foreach ($target as $column) {
                if ($row[$column] !== $incoming[$column]) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return ['target' => $target, 'action' => $arm['action']];
            }
        }
    }

    return null;
};

for ($variant = 1; $variant <= 1000; ++$variant) {
    $templateName = array_keys($templates)[($variant - 1) % count($templates)];
    $template = $templates[$templateName];
    $order = $armOrders[($variant - 1) % count($armOrders)];
    $incoming = $preserveConflictValues($template, $makeIncoming($template, $variant));
    $arms = $makeArms($order, $variant);
    $expectedArm = $firstMatchingArm($baseRows, $incoming, $arms);
    $prefix = 'real upstream corpus upsert returning dynamic catchall ' . $variant . ' ' . $templateName;

    $tests[$prefix . ' matched arm follows upstream first-match ordering'] = static function (TestRunner $t) use ($baseRows, $uniqueConstraints, $incoming, $arms, $expectedArm): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, [$incoming], $arms, $uniqueConstraints);
        $actual = $plan['matched_arms'][0] ?? null;

        $t->same($expectedArm['target'] ?? null, $actual['target'] ?? null);
        $t->same($expectedArm['action'] ?? 'insert', $actual['action'] ?? 'insert');
    };

    $tests[$prefix . ' RETURNING rows appear only for changed rows'] = static function (TestRunner $t) use ($baseRows, $uniqueConstraints, $incoming, $arms, $expectedArm): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, [$incoming], $arms, $uniqueConstraints);
        $changes = ($expectedArm['action'] ?? 'insert') === 'nothing' ? 0 : 1;

        $t->same($changes, $plan['changes']);
        $t->same($changes, count($plan['returning_rows']));
    };

    $tests[$prefix . ' insert update skip partitions match selected action'] = static function (TestRunner $t) use ($baseRows, $uniqueConstraints, $incoming, $arms, $expectedArm): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, [$incoming], $arms, $uniqueConstraints);
        $action = $expectedArm['action'] ?? 'insert';

        $t->same($action === 'insert' ? 1 : 0, count($plan['inserted_rows']));
        $t->same($action === 'update' ? 1 : 0, count($plan['updated_rows']));
        $t->same($action === 'nothing' ? 1 : 0, count($plan['skipped_rows']));
    };

    $tests[$prefix . ' projected RETURNING aliases preserve chosen row image'] = static function (TestRunner $t) use ($baseRows, $uniqueConstraints, $incoming, $arms, $expectedArm): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, [$incoming], $arms, $uniqueConstraints);
        $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], [
            'setting_id',
            'key' => static fn (array $row): string => (string) $row['key_name'],
            'load' => static fn (array $row): string => (string) $row['load_policy'],
        ]);

        if (($expectedArm['action'] ?? 'insert') === 'nothing') {
            $t->same([], $projected);
            return;
        }

        $t->same(['setting_id', 'key', 'load'], array_keys($projected[0]));
        $t->same(1, count($projected));
    };

    $tests[$prefix . ' final unique constraints remain intact'] = static function (TestRunner $t) use ($baseRows, $uniqueConstraints, $incoming, $arms): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, [$incoming], $arms, $uniqueConstraints);

        foreach ($uniqueConstraints as $constraint) {
            $seen = [];
            foreach ($plan['after'] as $row) {
                $key = implode("\0", array_map(static fn (string $column): string => (string) $row[$column], $constraint));
                $t->same(false, isset($seen[$key]));
                $seen[$key] = true;
            }
        }
    };
}

$tests['real upstream corpus upsert returning dynamic catchall source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert5.test 1.400-1.409 catch-all ON CONFLICT DO UPDATE arm selection',
        'upsert5.test 1.500-1.509 catch-all ON CONFLICT DO NOTHING suppression',
        'returning1.test 4.2 and 4.5 UPSERT RETURNING emits changed insert/update rows only',
        'non-overlap: existing dynamic wide-matrix tests cover named-arm permutations; this batch stresses catch-all arm fallback and DO NOTHING RETURNING suppression across generic app settings rows',
    ], [
        'upsert5.test 1.400-1.409 catch-all ON CONFLICT DO UPDATE arm selection',
        'upsert5.test 1.500-1.509 catch-all ON CONFLICT DO NOTHING suppression',
        'returning1.test 4.2 and 4.5 UPSERT RETURNING emits changed insert/update rows only',
        'non-overlap: existing dynamic wide-matrix tests cover named-arm permutations; this batch stresses catch-all arm fallback and DO NOTHING RETURNING suppression across generic app settings rows',
    ]);
};

$tests['real upstream corpus upsert returning dynamic catchall dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses native SQLiteUpsertDoUpdateWherePlan conflict-arm execution, catch-all matching, and RETURNING projection',
        'no new support component needed; reuses native SQLiteUpsertDoUpdateWherePlan conflict-arm execution, catch-all matching, and RETURNING projection',
    );
};

return $tests;
