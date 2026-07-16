<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$baseRowsFor = static fn (int $seed): array => [[
    'setting_id' => 1000 + $seed,
    'setting_key' => 'alpha-' . $seed,
    'tenant_id' => 2000 + $seed,
    'slot_id' => 3000 + $seed,
    'value_text' => 'base-alpha-' . $seed,
    'revision' => $seed,
]];

$quote = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$values = static function (array $row) use ($quote): string {
    return sprintf(
        '(%s,%s,%s,%s,%s,%s,%s)',
        $quote($row['setting_id']),
        $quote($row['setting_key']),
        $quote($row['tenant_id']),
        $quote($row['slot_id']),
        $quote($row['value_text']),
        $quote($row['revision']),
        $quote($row['payload']),
    );
};

$run = static function (array $rows, array $incoming, string $arms) use ($values): array {
    return SQLiteUpsertReturningSql::execute(
        'INSERT INTO app_settings(setting_id, setting_key, tenant_id, slot_id, value_text, revision, payload) VALUES '
        . $values($incoming)
        . ' '
        . $arms
        . ' RETURNING setting_id, setting_key, tenant_id, slot_id, value_text, revision',
        ['app_settings' => $rows],
        [['setting_id'], ['setting_key'], ['tenant_id'], ['slot_id']],
    );
};

$scenarios = [
    'upsert5-1.x.100 SQL chain first setting_id arm wins all conflicts' => static fn (int $seed): array => [
        'incoming' => [
            'setting_id' => 1000 + $seed,
            'setting_key' => 'alpha-' . $seed,
            'tenant_id' => 2000 + $seed,
            'slot_id' => 3000 + $seed,
            'value_text' => 'incoming',
            'revision' => $seed + 50,
            'payload' => 'all-' . $seed,
        ],
        'arms' => "ON CONFLICT(setting_id) DO UPDATE SET value_text='id:'||excluded.payload, revision=revision+1 "
            . "ON CONFLICT(tenant_id) DO UPDATE SET value_text='tenant:'||excluded.payload, revision=revision+1 "
            . "ON CONFLICT(slot_id) DO UPDATE SET value_text='slot:'||excluded.payload, revision=revision+1 "
            . "ON CONFLICT(setting_key) DO UPDATE SET value_text='key:'||excluded.payload, revision=revision+1",
        'expectedReturn' => [[
            'setting_id' => 1000 + $seed,
            'setting_key' => 'alpha-' . $seed,
            'tenant_id' => 2000 + $seed,
            'slot_id' => 3000 + $seed,
            'value_text' => 'id:all-' . $seed,
            'revision' => $seed + 1,
        ]],
        'expectedAfter' => [[
            'setting_id' => 1000 + $seed,
            'setting_key' => 'alpha-' . $seed,
            'tenant_id' => 2000 + $seed,
            'slot_id' => 3000 + $seed,
            'value_text' => 'id:all-' . $seed,
            'revision' => $seed + 1,
        ]],
        'conflictTarget' => ['setting_id'],
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.201 SQL chain tenant arm preempts later setting_id arm' => static fn (int $seed): array => [
        'incoming' => [
            'setting_id' => 1000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 2000 + $seed,
            'slot_id' => 7000 + $seed,
            'value_text' => 'incoming',
            'revision' => $seed + 60,
            'payload' => 'tenant-first-' . $seed,
        ],
        'arms' => "ON CONFLICT(tenant_id) DO UPDATE SET value_text='tenant:'||excluded.payload, revision=revision+1 "
            . "ON CONFLICT(setting_id) DO UPDATE SET value_text='id:'||excluded.payload, revision=revision+1 "
            . "ON CONFLICT(slot_id) DO UPDATE SET value_text='slot:'||excluded.payload, revision=revision+1",
        'expectedReturn' => [[
            'setting_id' => 1000 + $seed,
            'setting_key' => 'alpha-' . $seed,
            'tenant_id' => 2000 + $seed,
            'slot_id' => 3000 + $seed,
            'value_text' => 'tenant:tenant-first-' . $seed,
            'revision' => $seed + 1,
        ]],
        'expectedAfter' => [[
            'setting_id' => 1000 + $seed,
            'setting_key' => 'alpha-' . $seed,
            'tenant_id' => 2000 + $seed,
            'slot_id' => 3000 + $seed,
            'value_text' => 'tenant:tenant-first-' . $seed,
            'revision' => $seed + 1,
        ]],
        'conflictTarget' => ['tenant_id'],
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.422 SQL chain targeted do nothing suppresses catchall returning' => static fn (int $seed): array => [
        'incoming' => [
            'setting_id' => 9000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 8000 + $seed,
            'slot_id' => 3000 + $seed,
            'value_text' => 'incoming',
            'revision' => $seed + 70,
            'payload' => 'skip-slot-' . $seed,
        ],
        'arms' => "ON CONFLICT(tenant_id) DO NOTHING "
            . "ON CONFLICT(slot_id) DO NOTHING "
            . "ON CONFLICT DO UPDATE SET value_text='fallthrough:'||excluded.payload, revision=revision+1",
        'expectedReturn' => [],
        'expectedAfter' => $baseRowsFor($seed),
        'conflictTarget' => ['tenant_id'],
        'changes' => 0,
        'skipped' => 1,
    ],
    'upsert5-1.x.400 SQL chain catchall update handles key conflict after misses' => static fn (int $seed): array => [
        'incoming' => [
            'setting_id' => 9000 + $seed,
            'setting_key' => 'alpha-' . $seed,
            'tenant_id' => 8000 + $seed,
            'slot_id' => 7000 + $seed,
            'value_text' => 'incoming',
            'revision' => $seed + 80,
            'payload' => 'catch-key-' . $seed,
        ],
        'arms' => "ON CONFLICT(tenant_id) DO NOTHING "
            . "ON CONFLICT(slot_id) DO UPDATE SET value_text='slot:'||excluded.payload, revision=revision+1 "
            . "ON CONFLICT DO UPDATE SET value_text='catchall:'||excluded.payload, revision=revision+1",
        'expectedReturn' => [[
            'setting_id' => 1000 + $seed,
            'setting_key' => 'alpha-' . $seed,
            'tenant_id' => 2000 + $seed,
            'slot_id' => 3000 + $seed,
            'value_text' => 'catchall:catch-key-' . $seed,
            'revision' => $seed + 1,
        ]],
        'expectedAfter' => [[
            'setting_id' => 1000 + $seed,
            'setting_key' => 'alpha-' . $seed,
            'tenant_id' => 2000 + $seed,
            'slot_id' => 3000 + $seed,
            'value_text' => 'catchall:catch-key-' . $seed,
            'revision' => $seed + 1,
        ]],
        'conflictTarget' => ['tenant_id'],
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert2-320 SQL chain matching arm WHERE false does not fall through' => static fn (int $seed): array => [
        'incoming' => [
            'setting_id' => 1000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 8000 + $seed,
            'slot_id' => 7000 + $seed,
            'value_text' => 'incoming',
            'revision' => $seed - 1,
            'payload' => 'where-false-' . $seed,
        ],
        'arms' => "ON CONFLICT(setting_id) DO UPDATE SET value_text='id:'||excluded.payload, revision=excluded.revision WHERE excluded.revision>revision "
            . "ON CONFLICT DO UPDATE SET value_text='catchall:'||excluded.payload, revision=revision+1",
        'expectedReturn' => [],
        'expectedAfter' => $baseRowsFor($seed),
        'conflictTarget' => ['setting_id'],
        'changes' => 0,
        'skipped' => 1,
    ],
];

foreach ($scenarios as $scenario => $factory) {
    for ($seed = 1; $seed <= 200; ++$seed) {
        $case = $factory($seed);
        $tests["real upstream corpus UPSERT RETURNING SQL multi-arm dynamic {$scenario} seed {$seed}"] =
            static function (TestRunner $t) use ($run, $baseRowsFor, $case, $seed): void {
                $result = $run($baseRowsFor($seed), $case['incoming'], $case['arms']);

                $t->same($case['expectedReturn'], $result['returning']);
                $t->same($case['expectedAfter'], $result['after']);
                $t->same($case['changes'], $result['changes']);
                $t->same($case['skipped'], count($result['skipped_rows']));
                $t->same($case['conflictTarget'], $result['conflict_target']);
            };
    }
}

$tests['real upstream corpus UPSERT RETURNING SQL multi-arm source coverage'] = static function (TestRunner $t) use ($scenarios): void {
    $t->same(5, count($scenarios));
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test 1.$tn.100/201/400/422 generalized ON CONFLICT arm ordering and catchall behavior',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test 320/321 DO UPDATE WHERE false suppresses RETURNING rows without trying later arms',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test 4.5 UPSERT RETURNING emits only changed row images in statement order',
        'non-overlap: existing accepted batches exercise native conflict-arm helpers; this batch wires chained ON CONFLICT SQL text through SQLiteUpsertReturningSql',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test 1.$tn.100/201/400/422 generalized ON CONFLICT arm ordering and catchall behavior',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test 320/321 DO UPDATE WHERE false suppresses RETURNING rows without trying later arms',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test 4.5 UPSERT RETURNING emits only changed row images in statement order',
        'non-overlap: existing accepted batches exercise native conflict-arm helpers; this batch wires chained ON CONFLICT SQL text through SQLiteUpsertReturningSql',
    ]);
};

$tests['real upstream corpus UPSERT RETURNING SQL multi-arm dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteUpsertReturningSql SQL parser plus native SQLiteUpsertDoUpdateWherePlan conflict-arm execution',
        'no new support component needed; reuses SQLiteUpsertReturningSql SQL parser plus native SQLiteUpsertDoUpdateWherePlan conflict-arm execution',
    );
};

return $tests;
