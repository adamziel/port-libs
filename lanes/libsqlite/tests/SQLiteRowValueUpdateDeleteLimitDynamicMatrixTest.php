<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$settingTables = static function (): array {
    $settings = [];
    $batches = [];
    $keys = ['alpha', 'beta', 'gamma', 'delta', 'epsilon', 'zeta'];
    $states = ['queued', 'queued', 'live', 'queued', 'stale', 'queued'];
    $id = 1;
    foreach ([10, 20, 30] as $tenant) {
        foreach ($keys as $ordinal => $key) {
            $settings[] = [
                'setting_id' => $id,
                'tenant_id' => $tenant,
                'key_name' => $key,
                'state' => $states[$ordinal],
                'value_size' => 100 + $id,
                'payload' => "{$tenant}:{$key}",
            ];
            $batches[] = [
                'batch_id' => 1000 + $id,
                'tenant_id' => $tenant,
                'key_name' => $key,
                'batch_name' => $ordinal % 2 === 0 ? 'migrate' : 'cleanup',
                'priority' => ($tenant / 10) * 100 - ($ordinal * 7),
                'rank_hint' => $ordinal + 1,
            ];
            $id++;
        }
    }

    return ['app_settings' => $settings, 'app_setting_batches' => $batches];
};

/**
 * @return list<array<string,mixed>>
 */
$orderedBatchRows = static function (string $batchName) use ($settingTables): array {
    $rows = array_values(array_filter(
        $settingTables()['app_setting_batches'],
        static fn (array $row): bool => $row['batch_name'] === $batchName,
    ));
    usort($rows, static function (array $left, array $right): int {
        $comparison = $right['priority'] <=> $left['priority'];
        return $comparison !== 0 ? $comparison : ($left['key_name'] <=> $right['key_name']);
    });

    return $rows;
};

/**
 * @return list<list{int,string}>
 */
$tupleWindow = static function (string $batchName, int $limit, int $offset) use ($orderedBatchRows): array {
    $slice = $limit < 0
        ? array_slice($orderedBatchRows($batchName), $offset)
        : array_slice($orderedBatchRows($batchName), $offset, $limit);

    return array_map(
        static fn (array $row): array => [$row['tenant_id'], $row['key_name']],
        $slice,
    );
};

/**
 * @param list<list{int,string}> $tuples
 * @return list<int>
 */
$expectedSettingIds = static function (array $tuples) use ($settingTables): array {
    $wanted = [];
    foreach ($tuples as [$tenant, $key]) {
        $wanted[$tenant . ':' . $key] = true;
    }

    $ids = [];
    foreach ($settingTables()['app_settings'] as $row) {
        if (isset($wanted[$row['tenant_id'] . ':' . $row['key_name']])) {
            $ids[] = $row['setting_id'];
        }
    }

    return $ids;
};

/**
 * @param list<int> $ids
 * @return list<int>
 */
$expectedRemainingIds = static function (array $ids) use ($settingTables): array {
    return array_values(array_filter(
        array_column($settingTables()['app_settings'], 'setting_id'),
        static fn (int $id): bool => !in_array($id, $ids, true),
    ));
};

$tests = [];
$unique = [['tenant_id', 'key_name']];
$windows = [
    'limit zero' => [0, 0],
    'limit one' => [1, 0],
    'limit one offset one' => [1, 1],
    'limit two offset one' => [2, 1],
    'limit two offset two' => [2, 2],
    'limit three offset two' => [3, 2],
    'limit four offset zero' => [4, 0],
    'limit four offset three' => [4, 3],
    'limit negative offset four' => [-1, 4],
    'limit wide offset five' => [99, 5],
];

foreach ($windows as $label => [$limit, $offset]) {
    $limitClause = $limit < 0
        ? "LIMIT {$limit} OFFSET {$offset}"
        : ($offset % 2 === 0 ? "LIMIT {$limit} OFFSET {$offset}" : "LIMIT {$offset}, {$limit}");

    $tests["rowvalue dynamic update ordered subquery {$label}"] = static function (TestRunner $t) use ($settingTables, $tupleWindow, $expectedSettingIds, $limitClause, $limit, $offset, $unique): void {
        $sql = "UPDATE app_settings SET (state, value_size) = ('migrated', value_size + 5) WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_batches WHERE batch_name = 'migrate' ORDER BY priority DESC, key_name ASC {$limitClause}) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id";
        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $settingTables(), 'setting_id', $unique);
        $expectedIds = $expectedSettingIds($tupleWindow('migrate', $limit, $offset));

        $t->same($expectedIds, $result['plan']->selectedIds);
        $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
        $t->same(array_fill(0, count($expectedIds), 'migrated'), array_column($result['returning'], 'state'));
        $t->same(count($expectedIds), count(array_filter(
            $result['tables']['app_settings'],
            static fn (array $row): bool => $row['state'] === 'migrated',
        )));
        $t->true(str_contains('/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test', '/test/e_update.test'));
    };

    $tests["rowvalue dynamic delete ordered subquery {$label}"] = static function (TestRunner $t) use ($settingTables, $tupleWindow, $expectedSettingIds, $expectedRemainingIds, $limitClause, $limit, $offset, $unique): void {
        $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_batches WHERE batch_name = 'cleanup' ORDER BY priority DESC, key_name ASC {$limitClause}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id";
        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $settingTables(), 'setting_id', $unique);
        $expectedIds = $expectedSettingIds($tupleWindow('cleanup', $limit, $offset));

        $t->same($expectedIds, $result['plan']->selectedIds);
        $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
        $t->same($expectedRemainingIds($expectedIds), array_column($result['tables']['app_settings'], 'setting_id'));
        $t->same(count($settingTables()['app_settings']) - count($expectedIds), count($result['tables']['app_settings']));
        $t->true(str_contains('/home/claude/port-libs/.upstream-cache/libsqlite/test/delete.test', '/test/delete.test'));
    };
}

$tests['rowvalue dynamic update rejects non-integral limit expression'] = static function (TestRunner $t) use ($settingTables, $unique): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute(
        "UPDATE app_settings SET state = 'bad' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_batches WHERE batch_name = 'migrate' ORDER BY priority DESC LIMIT 1.5) RETURNING setting_id",
        $settingTables(),
        'setting_id',
        $unique,
    ));
};

$tests['rowvalue dynamic delete rejects offset without limit'] = static function (TestRunner $t) use ($settingTables, $unique): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute(
        "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_batches WHERE batch_name = 'cleanup' ORDER BY priority DESC OFFSET 1) RETURNING setting_id",
        $settingTables(),
        'setting_id',
        $unique,
    ));
};

for ($seed = 1; $seed <= 12; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = ($seed + 1) % 3;
    $formatFunction = $seed % 2 === 0 ? 'printf' : 'format';
    $offsetFunction = $seed % 2 === 0 ? 'format' : 'printf';
    $sql = "UPDATE app_settings SET (state, value_size) = ('formatted', value_size + {$seed}) WHERE state = 'queued' RETURNING setting_id, state, value_size ORDER BY value_size ASC LIMIT {$formatFunction}('%d', {$limitValue}) OFFSET {$offsetFunction}('%02d', {$offsetValue})";
    $expectedIds = array_slice([1, 2, 4, 6, 7, 8, 10, 12, 13, 14, 16, 18], $offsetValue, $limitValue);

    $tests[sprintf('rowvalue dynamic update printf format outer limit seed %02d', $seed)] =
        static function (TestRunner $t) use ($settingTables, $unique, $sql, $expectedIds, $limitValue, $offsetValue): void {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $settingTables(), 'setting_id', $unique);

            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expectedIds, $result['plan']->selectedIds);
            $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($expectedIds), 'formatted'), array_column($result['returning'], 'state'));
            $t->true(str_contains('/home/claude/port-libs/.upstream-cache/libsqlite/test/printf.test', '/test/printf.test'));
        };
}

for ($seed = 1; $seed <= 12; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 2) % 4;
    $formatFunction = $seed % 2 === 0 ? 'printf' : 'format';
    $offsetFunction = $seed % 2 === 0 ? 'format' : 'printf';
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_batches WHERE batch_name = 'cleanup' ORDER BY priority DESC, key_name ASC LIMIT {$formatFunction}('%d', {$limitValue}) OFFSET {$offsetFunction}('%d', {$offsetValue})) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id";
    $expectedIds = $expectedSettingIds($tupleWindow('cleanup', $limitValue, $offsetValue));

    $tests[sprintf('rowvalue dynamic delete printf format tuple limit seed %02d', $seed)] =
        static function (TestRunner $t) use ($settingTables, $unique, $expectedRemainingIds, $sql, $expectedIds): void {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $settingTables(), 'setting_id', $unique);

            $t->same($expectedIds, $result['plan']->selectedIds);
            $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
            $t->same($expectedRemainingIds($expectedIds), array_column($result['tables']['app_settings'], 'setting_id'));
            $t->same(count($expectedIds), count($result['returning']));
            $t->true(str_contains('/home/claude/port-libs/.upstream-cache/libsqlite/test/printf.test', '/test/printf.test'));
        };
}

$tests['rowvalue dynamic delete rejects malformed printf limit format'] = static function (TestRunner $t) use ($settingTables, $unique): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute(
        "DELETE FROM app_settings WHERE state = 'queued' RETURNING setting_id LIMIT printf('%d %d', 2)",
        $settingTables(),
        'setting_id',
        $unique,
    ));
};

return $tests;
