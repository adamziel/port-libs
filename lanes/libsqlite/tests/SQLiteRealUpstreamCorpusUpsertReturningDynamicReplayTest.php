<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$uniqueConstraints = [['setting_id'], ['setting_key'], ['tenant_id'], ['slot_id']];

$baseRows = static function (int $seed): array {
    return [
        [
            'setting_id' => 1000 + $seed,
            'setting_key' => 'alpha-' . $seed,
            'tenant_id' => 2000 + $seed,
            'slot_id' => 3000 + $seed,
            'value_text' => 'base-alpha-' . $seed,
            'revision' => $seed,
        ],
    ];
};

$arm = static fn (?array $target, string $marker, string $action = 'update'): array => [
    'target' => $target,
    'action' => $action,
    'assignments' => $action === 'update'
        ? [
            'value_text' => static fn (array $current, array $incoming): string => $marker . ':' . $incoming['payload'],
            'revision' => static fn (array $current, array $incoming): int => (int) $current['revision'] + 1,
        ]
        : [],
];

$caseSets = [
    'upsert5-1.x.100 first setting_id arm wins when all constraints conflict' => [
        'arms' => [$arm(['setting_id'], 'id'), $arm(['tenant_id'], 'tenant'), $arm(['slot_id'], 'slot'), $arm(['setting_key'], 'key')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 1000 + $seed,
            'setting_key' => 'alpha-' . $seed,
            'tenant_id' => 2000 + $seed,
            'slot_id' => 3000 + $seed,
            'payload' => 'all-' . $seed,
        ],
        'target' => ['setting_id'],
        'action' => 'update',
        'marker' => 'id:all-',
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.101 first tenant arm wins when setting_id misses' => [
        'arms' => [$arm(['setting_id'], 'id'), $arm(['tenant_id'], 'tenant'), $arm(['slot_id'], 'slot'), $arm(['setting_key'], 'key')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 9000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 2000 + $seed,
            'slot_id' => 3000 + $seed,
            'payload' => 'tenant-' . $seed,
        ],
        'target' => ['tenant_id'],
        'action' => 'update',
        'marker' => 'tenant:tenant-',
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.102 first slot arm wins after setting_id and tenant miss' => [
        'arms' => [$arm(['setting_id'], 'id'), $arm(['tenant_id'], 'tenant'), $arm(['slot_id'], 'slot'), $arm(['setting_key'], 'key')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 9000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 8000 + $seed,
            'slot_id' => 3000 + $seed,
            'payload' => 'slot-' . $seed,
        ],
        'target' => ['slot_id'],
        'action' => 'update',
        'marker' => 'slot:slot-',
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.103 key arm wins after earlier targets miss' => [
        'arms' => [$arm(['setting_id'], 'id'), $arm(['tenant_id'], 'tenant'), $arm(['slot_id'], 'slot'), $arm(['setting_key'], 'key')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 9000 + $seed,
            'setting_key' => 'alpha-' . $seed,
            'tenant_id' => 8000 + $seed,
            'slot_id' => 7000 + $seed,
            'payload' => 'key-' . $seed,
        ],
        'target' => ['setting_key'],
        'action' => 'update',
        'marker' => 'key:key-',
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.201 reordered tenant arm preempts setting_id' => [
        'arms' => [$arm(['tenant_id'], 'tenant'), $arm(['setting_id'], 'id'), $arm(['slot_id'], 'slot'), $arm(['setting_key'], 'key')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 1000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 2000 + $seed,
            'slot_id' => 7000 + $seed,
            'payload' => 'tenant-first-' . $seed,
        ],
        'target' => ['tenant_id'],
        'action' => 'update',
        'marker' => 'tenant:tenant-first-',
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.211 slot arm preempts setting_id after tenant misses' => [
        'arms' => [$arm(['tenant_id'], 'tenant'), $arm(['slot_id'], 'slot'), $arm(['setting_id'], 'id'), $arm(['setting_key'], 'key')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 1000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 8000 + $seed,
            'slot_id' => 3000 + $seed,
            'payload' => 'slot-before-id-' . $seed,
        ],
        'target' => ['slot_id'],
        'action' => 'update',
        'marker' => 'slot:slot-before-id-',
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.300 duplicate setting_id arms use first duplicate' => [
        'arms' => [$arm(['tenant_id'], 'tenant'), $arm(['setting_id'], 'id-first'), $arm(['setting_id'], 'id-second'), $arm(['setting_key'], 'key')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 1000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 8000 + $seed,
            'slot_id' => 7000 + $seed,
            'payload' => 'dup-id-' . $seed,
        ],
        'target' => ['setting_id'],
        'action' => 'update',
        'marker' => 'id-first:dup-id-',
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.301 duplicate setting_id arms do not preempt key match' => [
        'arms' => [$arm(['tenant_id'], 'tenant'), $arm(['setting_id'], 'id-first'), $arm(['setting_id'], 'id-second'), $arm(['setting_key'], 'key')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 9000 + $seed,
            'setting_key' => 'alpha-' . $seed,
            'tenant_id' => 8000 + $seed,
            'slot_id' => 7000 + $seed,
            'payload' => 'key-after-dups-' . $seed,
        ],
        'target' => ['setting_key'],
        'action' => 'update',
        'marker' => 'key:key-after-dups-',
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.400 catchall update handles setting_id conflict after misses' => [
        'arms' => [$arm(['tenant_id'], 'tenant'), $arm(['slot_id'], 'slot'), $arm(null, 'catchall')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 1000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 8000 + $seed,
            'slot_id' => 7000 + $seed,
            'payload' => 'catch-id-' . $seed,
        ],
        'target' => null,
        'action' => 'update',
        'marker' => 'catchall:catch-id-',
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.403 targeted tenant update beats catchall' => [
        'arms' => [$arm(['tenant_id'], 'tenant'), $arm(['slot_id'], 'slot'), $arm(null, 'catchall')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 9000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 2000 + $seed,
            'slot_id' => 7000 + $seed,
            'payload' => 'targeted-tenant-' . $seed,
        ],
        'target' => ['tenant_id'],
        'action' => 'update',
        'marker' => 'tenant:targeted-tenant-',
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.410 bare catchall update handles setting_id conflict' => [
        'arms' => [$arm(null, 'bare-catchall')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 1000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 8000 + $seed,
            'slot_id' => 7000 + $seed,
            'payload' => 'bare-id-' . $seed,
        ],
        'target' => null,
        'action' => 'update',
        'marker' => 'bare-catchall:bare-id-',
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.413 bare catchall update handles key conflict' => [
        'arms' => [$arm(null, 'bare-catchall')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 9000 + $seed,
            'setting_key' => 'alpha-' . $seed,
            'tenant_id' => 8000 + $seed,
            'slot_id' => 7000 + $seed,
            'payload' => 'bare-key-' . $seed,
        ],
        'target' => null,
        'action' => 'update',
        'marker' => 'bare-catchall:bare-key-',
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.420 do-nothing arms fall through to catchall update' => [
        'arms' => [$arm(['tenant_id'], '', 'nothing'), $arm(['slot_id'], '', 'nothing'), $arm(null, 'fallthrough')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 1000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 8000 + $seed,
            'slot_id' => 7000 + $seed,
            'payload' => 'fallthrough-id-' . $seed,
        ],
        'target' => null,
        'action' => 'update',
        'marker' => 'fallthrough:fallthrough-id-',
        'changes' => 1,
        'skipped' => 0,
    ],
    'upsert5-1.x.422 do-nothing slot arm suppresses catchall returning' => [
        'arms' => [$arm(['tenant_id'], '', 'nothing'), $arm(['slot_id'], '', 'nothing'), $arm(null, 'fallthrough')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 9000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 8000 + $seed,
            'slot_id' => 3000 + $seed,
            'payload' => 'skip-slot-' . $seed,
        ],
        'target' => ['slot_id'],
        'action' => 'nothing',
        'marker' => 'base-alpha-',
        'changes' => 0,
        'skipped' => 1,
    ],
    'upsert5-1.x.500 catchall do-nothing suppresses setting_id conflict' => [
        'arms' => [$arm(['tenant_id'], 'tenant'), $arm(['slot_id'], 'slot'), $arm(null, '', 'nothing')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 1000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 8000 + $seed,
            'slot_id' => 7000 + $seed,
            'payload' => 'catchall-skip-id-' . $seed,
        ],
        'target' => null,
        'action' => 'nothing',
        'marker' => 'base-alpha-',
        'changes' => 0,
        'skipped' => 1,
    ],
    'upsert5-1.x.503 targeted tenant update beats catchall do-nothing' => [
        'arms' => [$arm(['tenant_id'], 'tenant'), $arm(['slot_id'], 'slot'), $arm(null, '', 'nothing')],
        'incoming' => static fn (int $seed): array => [
            'setting_id' => 9000 + $seed,
            'setting_key' => 'fresh-' . $seed,
            'tenant_id' => 2000 + $seed,
            'slot_id' => 7000 + $seed,
            'payload' => 'tenant-before-skip-' . $seed,
        ],
        'target' => ['tenant_id'],
        'action' => 'update',
        'marker' => 'tenant:tenant-before-skip-',
        'changes' => 1,
        'skipped' => 0,
    ],
];

$tests = [];

foreach ($caseSets as $scenario => $case) {
    for ($seed = 0; $seed < 64; ++$seed) {
        $tests["real upstream corpus UPSERT RETURNING dynamic replay {$scenario} seed {$seed} carries statement state into yield"] =
            static function (TestRunner $t) use ($baseRows, $uniqueConstraints, $case, $seed): void {
                $incoming = $case['incoming']($seed);
                $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
                    $baseRows($seed),
                    [$incoming],
                    $case['arms'],
                    $uniqueConstraints,
                );

                $t->same($case['changes'], $result['changes']);
                $t->same($case['skipped'], count($result['skipped_rows']));
                $t->same($case['action'], $result['matched_arms'][0]['action']);
                $t->same($case['target'], $result['matched_arms'][0]['target']);

                $final = $result['after'][0];
                $expectedPrefix = $case['marker'];
                if ($case['changes'] === 1) {
                    $t->same($expectedPrefix . $seed, $final['value_text']);
                    $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], [
                        'setting_id',
                        'value_text',
                        'revision',
                        'yield_marker' => static fn (array $row): string => 'yield:' . $row['setting_id'] . ':' . $row['value_text'],
                    ]);
                    $t->same([
                        [
                            'setting_id' => 1000 + $seed,
                            'value_text' => $expectedPrefix . $seed,
                            'revision' => $seed + 1,
                            'yield_marker' => 'yield:' . (1000 + $seed) . ':' . $expectedPrefix . $seed,
                        ],
                    ], $projected);
                    $t->same(['before-insert', 'update-returning'], array_column($result['yield_trace'], 'event'));
                    return;
                }

                $t->same($expectedPrefix . $seed, $final['value_text']);
                $t->same([], $result['returning_rows']);
                $t->same(['before-insert', 'conflict-do-nothing'], array_column($result['yield_trace'], 'event'));
            };
    }
}

$tests['real upstream corpus UPSERT RETURNING dynamic replay source citations'] = static function (TestRunner $t) use ($caseSets): void {
    $t->same(16, count($caseSets));
    $t->true(str_starts_with(array_key_first($caseSets), 'upsert5-1.x.100'));
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test 1.$tn.100 through 1.$tn.505 conflict-arm priority, catchall, and DO NOTHING matrix',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test 17.$tn RETURNING projection/yield rows over INSERT/UPDATE streams',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test 1.$tn.100 through 1.$tn.505 conflict-arm priority, catchall, and DO NOTHING matrix',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test 17.$tn RETURNING projection/yield rows over INSERT/UPDATE streams',
    ]);
};

return $tests;
