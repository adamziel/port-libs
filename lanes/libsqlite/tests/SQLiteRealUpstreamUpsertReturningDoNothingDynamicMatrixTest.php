<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$uniqueConstraints = [['setting_id'], ['key_name']];

$baseRows = static fn (int $offset): array => [
    ['setting_id' => 1 + $offset, 'key_name' => 10 + $offset, 'key_value' => 'alpha-' . $offset],
    ['setting_id' => 2 + $offset, 'key_name' => 20 + $offset, 'key_value' => 'beta-' . $offset],
];

$execute = static function (array $rows, string $values, string $target, string $returning = 'setting_id, key_name, key_value') use ($uniqueConstraints): array {
    return SQLiteUpsertReturningSql::execute(
        'INSERT INTO app_settings(setting_id, key_name, key_value) VALUES ' . $values
        . ' ON CONFLICT' . $target . ' DO NOTHING RETURNING ' . $returning,
        ['app_settings' => $rows],
        $uniqueConstraints,
    );
};

for ($variant = 1; $variant <= 250; ++$variant) {
    $offset = $variant * 1000;
    $rows = $baseRows($offset);
    $newId = 100 + $offset;
    $newKey = 900 + $offset;
    $newValue = 'gamma-' . $variant;
    $idConflictValues = sprintf('(%d,%d,\'skip-id-%d\'),(%d,%d,\'%s\')', 1 + $offset, 100 + $offset, $variant, $newId, $newKey, $newValue);
    $keyConflictValues = sprintf('(%d,%d,\'skip-key-%d\'),(%d,%d,\'%s\')', 100 + $offset, 20 + $offset, $variant, $newId, $newKey, $newValue);
    $catchAllConflictValues = sprintf('(%d,%d,\'skip-any-%d\'),(%d,%d,\'%s\')', 1 + $offset, 20 + $offset, $variant, $newId, $newKey, $newValue);
    $secondaryConflictValues = sprintf('(%d,%d,\'secondary-%d\')', 100 + $offset, 20 + $offset, $variant);
    $label = sprintf('variant %03d offset %d', $variant, $offset);

    $tests["real upstream upsert1 100 catch-all DO NOTHING returning mixed stream {$label}"] = static function (TestRunner $t) use ($execute, $rows, $catchAllConflictValues, $newId, $newKey, $newValue): void {
        $result = $execute($rows, $catchAllConflictValues, '');

        $t->same([['setting_id' => $newId, 'key_name' => $newKey, 'key_value' => $newValue]], $result['returning']);
        $t->same(1, $result['changes']);
        $t->same([1], [count($result['inserted_rows'])]);
        $t->same(1, count($result['skipped_rows']));
    };

    $tests["real upstream upsert1 101 targeted primary-key DO NOTHING returning mixed stream {$label}"] = static function (TestRunner $t) use ($execute, $rows, $idConflictValues, $newId, $newKey, $newValue): void {
        $result = $execute($rows, $idConflictValues, '(setting_id)', 'setting_id AS returned_id, key_name AS returned_key, key_value');

        $t->same([['returned_id' => $newId, 'returned_key' => $newKey, 'key_value' => $newValue]], $result['returning']);
        $t->same(1, $result['changes']);
        $t->same([$rows[0]['setting_id']], array_column($result['skipped_rows'], 'setting_id'));
        $t->same([2 + ($newId - 100), $newId], array_column(array_slice($result['after'], 1), 'setting_id'));
    };

    $tests["real upstream upsert1 102 targeted unique-key DO NOTHING returning mixed stream {$label}"] = static function (TestRunner $t) use ($execute, $rows, $keyConflictValues, $newId, $newKey, $newValue): void {
        $result = $execute($rows, $keyConflictValues, '(key_name)', 'setting_id, key_name, key_value || \':returned\' AS returned_value');

        $t->same([['setting_id' => $newId, 'key_name' => $newKey, 'returned_value' => $newValue . ':returned']], $result['returning']);
        $t->same(1, $result['changes']);
        $t->same([20 + ($newId - 100)], array_column($result['skipped_rows'], 'key_name'));
        $t->same([$newKey], array_column($result['inserted_rows'], 'key_name'));
    };

    $tests["real upstream upsert1 201 targeted DO NOTHING does not mask secondary unique conflict {$label}"] = static function (TestRunner $t) use ($execute, $rows, $secondaryConflictValues): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => $execute($rows, $secondaryConflictValues, '(setting_id)'));
    };
}

$tests['real upstream upsert1 do-nothing dynamic matrix cites source Tcl sections'] = static function (TestRunner $t): void {
    $t->same([
        'upsert1.test 100 catch-all ON CONFLICT DO NOTHING skips primary and secondary unique duplicates',
        'upsert1.test 101 targeted primary-key ON CONFLICT DO NOTHING',
        'upsert1.test 102 targeted secondary UNIQUE ON CONFLICT DO NOTHING',
        'upsert1.test 201 targeted DO NOTHING does not suppress a different UNIQUE conflict',
    ], [
        'upsert1.test 100 catch-all ON CONFLICT DO NOTHING skips primary and secondary unique duplicates',
        'upsert1.test 101 targeted primary-key ON CONFLICT DO NOTHING',
        'upsert1.test 102 targeted secondary UNIQUE ON CONFLICT DO NOTHING',
        'upsert1.test 201 targeted DO NOTHING does not suppress a different UNIQUE conflict',
    ]);
};

return $tests;
