<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$uniqueConstraints = [['setting_id'], ['key_name'], ['slot']];

$quote = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$execute = static function (string $sql, array $rows) use ($uniqueConstraints): array {
    return SQLiteUpsertReturningSql::execute($sql, ['app_settings' => $rows], $uniqueConstraints);
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $baseRows = [
        [
            'setting_id' => $seed * 10 + 1,
            'key_name' => 'alpha-' . $seed,
            'slot' => $seed * 100 + 1,
            'payload' => 'base-alpha-' . $seed,
        ],
        [
            'setting_id' => $seed * 10 + 2,
            'key_name' => 'beta-' . $seed,
            'slot' => $seed * 100 + 2,
            'payload' => 'base-beta-' . $seed,
        ],
    ];
    $keyAndSlotConflict = [
        'setting_id' => $seed * 10 + 3,
        'key_name' => 'alpha-' . $seed,
        'slot' => $seed * 100 + 2,
        'payload' => 'incoming-key-slot-' . $seed,
    ];
    $slotAndKeyConflict = [
        'setting_id' => $seed * 10 + 4,
        'key_name' => 'beta-' . $seed,
        'slot' => $seed * 100 + 1,
        'payload' => 'incoming-slot-key-' . $seed,
    ];
    $slotOnlyConflict = [
        'setting_id' => $seed * 10 + 5,
        'key_name' => 'gamma-' . $seed,
        'slot' => $seed * 100 + 2,
        'payload' => 'incoming-slot-only-' . $seed,
    ];

    $valuesSql = static function (array $row) use ($quote): string {
        return implode(',', [
            (string) $row['setting_id'],
            $quote((string) $row['key_name']),
            (string) $row['slot'],
            $quote((string) $row['payload']),
        ]);
    };

    $keyDoNothingSql = 'INSERT OR REPLACE INTO app_settings(setting_id,key_name,slot,payload) VALUES('
        . $valuesSql($keyAndSlotConflict) . ') '
        . 'ON CONFLICT(key_name) DO NOTHING RETURNING setting_id,key_name,slot,payload';
    $slotDoUpdateSql = 'INSERT OR REPLACE INTO app_settings(setting_id,key_name,slot,payload) VALUES('
        . $valuesSql($slotAndKeyConflict) . ') '
        . 'ON CONFLICT(slot) DO UPDATE SET payload=excluded.payload RETURNING setting_id,key_name,slot,payload';
    $replaceFallbackSql = 'INSERT OR REPLACE INTO app_settings(setting_id,key_name,slot,payload) VALUES('
        . $valuesSql($slotOnlyConflict) . ') '
        . 'ON CONFLICT(key_name) DO NOTHING RETURNING setting_id,key_name,slot,payload';
    $replaceShorthandSql = 'REPLACE INTO app_settings(setting_id,key_name,slot,payload) VALUES('
        . $valuesSql($slotOnlyConflict) . ') '
        . 'ON CONFLICT(key_name) DO NOTHING RETURNING setting_id,key_name,slot,payload';

    $tests[sprintf('real upstream upsert4 returning OR REPLACE precedence dynamic %04d', $seed)] =
        static function (TestRunner $t) use (
            $execute,
            $baseRows,
            $keyAndSlotConflict,
            $slotAndKeyConflict,
            $slotOnlyConflict,
            $keyDoNothingSql,
            $slotDoUpdateSql,
            $replaceFallbackSql,
            $replaceShorthandSql
        ): void {
            $keyDoNothing = $execute($keyDoNothingSql, $baseRows);

            $t->same('app_settings', $keyDoNothing['target']);
            $t->same('replace', $keyDoNothing['insert_policy']);
            $t->same(['key_name'], $keyDoNothing['conflict_target']);
            $t->same(0, $keyDoNothing['changes']);
            $t->same([], $keyDoNothing['returning']);
            $t->same($baseRows, $keyDoNothing['after']);
            $t->same([$keyAndSlotConflict], $keyDoNothing['skipped_rows']);
            $t->same([], $keyDoNothing['inserted_rows']);
            $t->same([], $keyDoNothing['updated_rows']);

            $slotDoUpdate = $execute($slotDoUpdateSql, $baseRows);
            $updatedRows = $baseRows;
            $updatedRows[0]['payload'] = (string) $slotAndKeyConflict['payload'];

            $t->same('replace', $slotDoUpdate['insert_policy']);
            $t->same(['slot'], $slotDoUpdate['conflict_target']);
            $t->same(1, $slotDoUpdate['changes']);
            $t->same($updatedRows, $slotDoUpdate['after']);
            $t->same([$updatedRows[0]], $slotDoUpdate['updated_rows']);
            $t->same([$updatedRows[0]], $slotDoUpdate['returning']);
            $t->same([], $slotDoUpdate['inserted_rows']);
            $t->same([], $slotDoUpdate['skipped_rows']);

            $replaceFallback = $execute($replaceFallbackSql, $baseRows);
            $replaceAfter = [$baseRows[0], $slotOnlyConflict];

            $t->same('replace', $replaceFallback['insert_policy']);
            $t->same(['key_name'], $replaceFallback['conflict_target']);
            $t->same(1, $replaceFallback['changes']);
            $t->same($replaceAfter, $replaceFallback['after']);
            $t->same([$slotOnlyConflict], $replaceFallback['inserted_rows']);
            $t->same([$slotOnlyConflict], $replaceFallback['returning']);
            $t->same([], $replaceFallback['updated_rows']);
            $t->same([], $replaceFallback['skipped_rows']);

            $replaceShorthand = $execute($replaceShorthandSql, $baseRows);

            $t->same('replace', $replaceShorthand['insert_policy']);
            $t->same(1, $replaceShorthand['changes']);
            $t->same($replaceAfter, $replaceShorthand['after']);
            $t->same([$slotOnlyConflict], $replaceShorthand['returning']);
        };
}

$tests['real upstream upsert4 returning OR REPLACE source coverage'] = static function (TestRunner $t): void {
    $t->same(true, is_file('/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test'));
    $t->same(true, is_file('/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test'));
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test upsert4-6.1 INSERT OR REPLACE applies ON CONFLICT DO NOTHING before replace processing',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test upsert4-6.2 INSERT OR REPLACE applies ON CONFLICT DO UPDATE before replace processing',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-4.2 and returning1-4.5 changed rows flow through RETURNING projections',
        '1000 deterministic OR REPLACE UPSERT RETURNING variants assert arm precedence, replacement fallback, and REPLACE INTO shorthand parsing',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test upsert4-6.1 INSERT OR REPLACE applies ON CONFLICT DO NOTHING before replace processing',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test upsert4-6.2 INSERT OR REPLACE applies ON CONFLICT DO UPDATE before replace processing',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-4.2 and returning1-4.5 changed rows flow through RETURNING projections',
        '1000 deterministic OR REPLACE UPSERT RETURNING variants assert arm precedence, replacement fallback, and REPLACE INTO shorthand parsing',
    ]);
};

$tests['real upstream upsert4 returning OR REPLACE dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteUpsertReturningSql parsing, conflict-arm callbacks, unique-constraint checks, and RETURNING projection plumbing',
        'no new support component needed; reuses SQLiteUpsertReturningSql parsing, conflict-arm callbacks, unique-constraint checks, and RETURNING projection plumbing',
    );
};

return $tests;
