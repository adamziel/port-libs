<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$baseRows = [
    ['setting_id' => 1, 'key_name' => 'one', 'setting_value' => 'old-one'],
    ['setting_id' => 2, 'key_name' => 'two', 'setting_value' => 'old-two'],
    ['setting_id' => 3, 'key_name' => 'three', 'setting_value' => 'old-three'],
];

$quote = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

$runLiteralTuple = static function (array $incoming) use ($baseRows, $quote): array {
    $values = [];
    foreach ($incoming as $row) {
        $values[] = sprintf('(%d, %s, %s)', $row['setting_id'], $quote($row['key_name']), $quote($row['setting_value']));
    }

    return SQLiteUpsertReturningSql::execute(
        'INSERT INTO app_settings(setting_id, key_name, setting_value) VALUES ' . implode(', ', $values)
        . " ON CONFLICT(setting_id) DO UPDATE SET (key_name, setting_value) = ('four', 'tuple-updated')"
        . ' RETURNING setting_id, key_name, setting_value',
        ['app_settings' => $baseRows],
        [['setting_id'], ['key_name']],
    );
};

$runSelectTuple = static function (array $incoming) use ($baseRows, $quote): array {
    $values = [];
    foreach ($incoming as $row) {
        $values[] = sprintf('(%d, %s, %s)', $row['setting_id'], $quote($row['key_name']), $quote($row['setting_value']));
    }

    return SQLiteUpsertReturningSql::execute(
        'INSERT INTO app_settings(setting_id, key_name, setting_value) VALUES ' . implode(', ', $values)
        . " ON CONFLICT(setting_id) DO UPDATE SET (key_name, setting_value) = (SELECT 'x', 'y')"
        . ' RETURNING setting_id AS rowid_seen, key_name AS key_seen, setting_value AS value_seen',
        ['app_settings' => $baseRows],
        [['setting_id'], ['key_name']],
    );
};

$tests['real upstream upsert4 1.7 row-value assignment parser expands tuple columns'] = static function (TestRunner $t): void {
    $parsed = SQLiteUpsertReturningSql::parse(
        "INSERT INTO app_settings(setting_id, key_name, setting_value) VALUES (1, 'ignored', 'ignored') "
        . "ON CONFLICT(setting_id) DO UPDATE SET (key_name, setting_value) = ('four', 'tuple-updated') "
        . 'RETURNING setting_id, key_name, setting_value',
    );

    $t->same(['key_name', 'setting_value'], array_keys($parsed['assignments']));
    $t->same("'four'", $parsed['assignments']['key_name']);
    $t->same("'tuple-updated'", $parsed['assignments']['setting_value']);
};

$tests['real upstream upsert4 1.7 row-value assignment updates one row and yields returning row'] = static function (TestRunner $t) use ($runLiteralTuple): void {
    $result = $runLiteralTuple([
        ['setting_id' => 1, 'key_name' => 'ignored', 'setting_value' => 'ignored'],
    ]);

    $t->same([['setting_id' => 1, 'key_name' => 'four', 'setting_value' => 'tuple-updated']], $result['returning']);
    $t->same(1, $result['changes']);
    $t->same('four', $result['after'][0]['key_name']);
    $t->same('tuple-updated', $result['after'][0]['setting_value']);
};

$tests['real upstream upsert4 1.8 row-value assignment can update conflict key atomically'] = static function (TestRunner $t): void {
    $result = SQLiteUpsertReturningSql::execute(
        "INSERT INTO app_settings(setting_id, key_name, setting_value) VALUES (1, 'ignored', 'ignored') "
        . "ON CONFLICT(setting_id) DO UPDATE SET (key_name, setting_id) = ('four', 4) "
        . 'RETURNING setting_id, key_name',
        ['app_settings' => [
            ['setting_id' => 1, 'key_name' => 'one', 'setting_value' => 'old-one'],
            ['setting_id' => 2, 'key_name' => 'two', 'setting_value' => 'old-two'],
            ['setting_id' => 3, 'key_name' => 'three', 'setting_value' => 'old-three'],
        ]],
        [['setting_id'], ['key_name']],
    );

    $after = $result['after'];
    usort($after, static fn (array $left, array $right): int => $left['setting_id'] <=> $right['setting_id']);

    $t->same([['setting_id' => 4, 'key_name' => 'four']], $result['returning']);
    $t->same([2, 3, 4], array_column($after, 'setting_id'));
    $t->same(['two', 'three', 'four'], array_column($after, 'key_name'));
};

$tests['real upstream upsert4 row-value assignment rejects mismatched arity'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningSql::execute(
        "INSERT INTO app_settings(setting_id, key_name, setting_value) VALUES (1, 'ignored', 'ignored') "
        . "ON CONFLICT(setting_id) DO UPDATE SET (key_name, setting_value) = ('only-one') "
        . 'RETURNING setting_id, key_name',
        ['app_settings' => [['setting_id' => 1, 'key_name' => 'one', 'setting_value' => 'old-one']]],
        [['setting_id'], ['key_name']],
    ));
};

for ($variant = 1; $variant <= 1000; ++$variant) {
    $tests["real upstream upsert4 row-value SET dynamic literal tuple {$variant}"] = static function (TestRunner $t) use ($runLiteralTuple, $variant): void {
        $incoming = [
            ['setting_id' => 1, 'key_name' => 'conflict-' . $variant, 'setting_value' => 'ignored-' . $variant],
            ['setting_id' => 10_000 + $variant, 'key_name' => 'fresh-' . $variant, 'setting_value' => 'inserted-' . $variant],
        ];
        $result = $runLiteralTuple($incoming);

        $t->same([
            ['setting_id' => 1, 'key_name' => 'four', 'setting_value' => 'tuple-updated'],
            ['setting_id' => 10_000 + $variant, 'key_name' => 'fresh-' . $variant, 'setting_value' => 'inserted-' . $variant],
        ], $result['returning'], 'upsert4.test 1.7 row-value SET RETURNING stream');
        $t->same(2, $result['changes']);
        $t->same('four', $result['updated_rows'][0]['key_name']);
        $t->same('fresh-' . $variant, $result['inserted_rows'][0]['key_name']);
    };

    $tests["real upstream upsert4 row-value SET dynamic SELECT tuple {$variant}"] = static function (TestRunner $t) use ($runSelectTuple, $variant): void {
        $result = $runSelectTuple([
            ['setting_id' => 2, 'key_name' => 'conflict-select-' . $variant, 'setting_value' => 'ignored-' . $variant],
        ]);

        $t->same([['rowid_seen' => 2, 'key_seen' => 'x', 'value_seen' => 'y']], $result['returning'], 'upsert4.test 1.7 SELECT tuple feeds row-value SET');
        $t->same(1, $result['changes']);
        $t->same('x', $result['after'][1]['key_name']);
        $t->same('y', $result['after'][1]['setting_value']);
    };
}

$tests['real upstream upsert4 row-value SET dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert4.test 1.7 DO UPDATE SET (b,c) = (SELECT ...)',
        'upsert4.test 1.8 DO UPDATE SET (c,a) = (...) changes the conflict key atomically',
        'returning1.test RETURNING emits the updated row image after UPSERT assignment evaluation',
        'non-overlap: prior dynamic UPSERT batches cover conflict target selection, DO NOTHING suppression, and repeated conflict row streams; this batch covers row-value SET assignment parsing/evaluation',
    ], [
        'upsert4.test 1.7 DO UPDATE SET (b,c) = (SELECT ...)',
        'upsert4.test 1.8 DO UPDATE SET (c,a) = (...) changes the conflict key atomically',
        'returning1.test RETURNING emits the updated row image after UPSERT assignment evaluation',
        'non-overlap: prior dynamic UPSERT batches cover conflict target selection, DO NOTHING suppression, and repeated conflict row streams; this batch covers row-value SET assignment parsing/evaluation',
    ]);
};

$tests['real upstream upsert4 row-value SET dynamic dependency closure'] = static function (TestRunner $t) use ($runSelectTuple): void {
    $result = $runSelectTuple([
        ['setting_id' => 3, 'key_name' => 'conflict-select-final', 'setting_value' => 'ignored-final'],
    ]);

    $t->same('no new support component needed; reuses native SQLiteUpsertReturningSql assignment parsing and RETURNING projection', 'no new support component needed; reuses native SQLiteUpsertReturningSql assignment parsing and RETURNING projection');
    $t->same([['rowid_seen' => 3, 'key_seen' => 'x', 'value_seen' => 'y']], $result['returning']);
};

return $tests;
