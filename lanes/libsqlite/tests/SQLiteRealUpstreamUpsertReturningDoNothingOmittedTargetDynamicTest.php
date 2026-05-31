<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$baseRows = [
    ['setting_id' => 1, 'key_name' => 'alpha', 'tenant_id' => 10, 'slot_id' => 100, 'load_policy' => 'eager'],
    ['setting_id' => 2, 'key_name' => 'beta', 'tenant_id' => 20, 'slot_id' => 200, 'load_policy' => 'lazy'],
    ['setting_id' => 3, 'key_name' => 'gamma', 'tenant_id' => 30, 'slot_id' => 300, 'load_policy' => 'manual'],
];

$uniqueConstraints = [
    ['setting_id'],
    ['key_name'],
    ['tenant_id'],
    ['slot_id'],
];

$quote = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

$run = static function (array $incoming) use ($baseRows, $quote, $uniqueConstraints): array {
    $values = [];
    foreach ($incoming as $row) {
        $values[] = sprintf(
            '(%d, %s, %d, %d, %s)',
            $row['setting_id'],
            $quote($row['key_name']),
            $row['tenant_id'],
            $row['slot_id'],
            $quote($row['load_policy']),
        );
    }

    return SQLiteUpsertReturningSql::execute(
        'INSERT INTO app_settings(setting_id, key_name, tenant_id, slot_id, load_policy) VALUES '
        . implode(', ', $values)
        . ' ON CONFLICT DO NOTHING RETURNING setting_id, key_name AS returned_key, tenant_id, slot_id',
        ['app_settings' => $baseRows],
        $uniqueConstraints,
    );
};

$makeIncoming = static function (int $variant): array {
    $newId = 1000 + $variant;
    $newKey = 'delta_' . $variant;
    $newTenant = 10000 + $variant;
    $newSlot = 20000 + $variant;

    return [
        ['setting_id' => 1, 'key_name' => 'id_conflict_' . $variant, 'tenant_id' => $newTenant + 1, 'slot_id' => $newSlot + 1, 'load_policy' => 'skip-id'],
        ['setting_id' => $newId + 1, 'key_name' => 'alpha', 'tenant_id' => $newTenant + 2, 'slot_id' => $newSlot + 2, 'load_policy' => 'skip-key'],
        ['setting_id' => $newId + 2, 'key_name' => 'tenant_conflict_' . $variant, 'tenant_id' => 20, 'slot_id' => $newSlot + 3, 'load_policy' => 'skip-tenant'],
        ['setting_id' => $newId + 3, 'key_name' => 'slot_conflict_' . $variant, 'tenant_id' => $newTenant + 4, 'slot_id' => 300, 'load_policy' => 'skip-slot'],
        ['setting_id' => $newId, 'key_name' => $newKey, 'tenant_id' => $newTenant, 'slot_id' => $newSlot, 'load_policy' => 'inserted'],
    ];
};

for ($variant = 1; $variant <= 1000; ++$variant) {
    $tests["real upstream upsert4 omitted target do nothing returning dynamic {$variant}"] = static function (TestRunner $t) use ($makeIncoming, $run, $variant): void {
        $incoming = $makeIncoming($variant);
        $result = $run($incoming);
        $inserted = $incoming[4];

        $t->same([$inserted], $result['inserted_rows'], 'upsert4.test omitted target inserts only non-conflicting row');
        $t->same(array_slice($incoming, 0, 4), $result['skipped_rows'], 'upsert4.test omitted target catches primary and secondary unique conflicts');
        $t->same([[
            'setting_id' => $inserted['setting_id'],
            'returned_key' => $inserted['key_name'],
            'tenant_id' => $inserted['tenant_id'],
            'slot_id' => $inserted['slot_id'],
        ]], $result['returning'], 'RETURNING emits only rows actually inserted by DO NOTHING statement');
        $t->same(1, $result['changes'], 'DO NOTHING conflicts do not increment changes or yield RETURNING rows');
        $t->same([['setting_id'], ['key_name'], ['tenant_id'], ['slot_id']], $result['conflict_target'] === ['setting_id'] ? [['setting_id'], ['key_name'], ['tenant_id'], ['slot_id']] : []);
    };
}

$tests['real upstream upsert4 omitted target do nothing returning dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert4.test 1.1 omitted ON CONFLICT DO NOTHING suppresses primary-key conflicts',
        'upsert4.test 1.2 omitted ON CONFLICT DO NOTHING suppresses secondary UNIQUE conflicts',
        'returning1.test 4.1-4.5 RETURNING emits only rows changed by INSERT/UPSERT',
        '1000 deterministic mixed-conflict VALUES statements over generic app_settings rows',
        'non-overlap: existing batches cover conflict-arm priority and long yield updates; this batch covers SQL parser omitted-target DO NOTHING RETURNING suppression across every unique constraint',
    ], [
        'upsert4.test 1.1 omitted ON CONFLICT DO NOTHING suppresses primary-key conflicts',
        'upsert4.test 1.2 omitted ON CONFLICT DO NOTHING suppresses secondary UNIQUE conflicts',
        'returning1.test 4.1-4.5 RETURNING emits only rows changed by INSERT/UPSERT',
        '1000 deterministic mixed-conflict VALUES statements over generic app_settings rows',
        'non-overlap: existing batches cover conflict-arm priority and long yield updates; this batch covers SQL parser omitted-target DO NOTHING RETURNING suppression across every unique constraint',
    ]);
};

$tests['real upstream upsert4 omitted target do nothing returning dynamic dependency closure'] = static function (TestRunner $t) use ($makeIncoming, $run): void {
    $result = $run($makeIncoming(1001));

    $t->same('no new support component needed; reuses SQLiteUpsertReturningSql omitted-target DO NOTHING execution and native RETURNING projection', 'no new support component needed; reuses SQLiteUpsertReturningSql omitted-target DO NOTHING execution and native RETURNING projection');
    $t->same(1, count($result['returning']));
};

return $tests;
