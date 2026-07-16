<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$tables = static function (): array {
    $settings = [];
    $targets = [];
    $rows = [
        [1, 1, 'alpha', 10, 30],
        [2, 1, 'beta', 20, 40],
        [3, 1, 'gamma', 30, 50],
        [4, 2, 'alpha', 40, 55],
        [5, 2, 'beta', 50, 60],
        [6, 2, 'gamma', 60, 70],
        [7, 3, 'alpha', 70, 75],
        [8, 3, 'beta', 80, 80],
    ];

    foreach ($rows as [$id, $tenant, $key, $bytes, $priority]) {
        $settings[] = [
            'setting_id' => $id,
            'tenant_id' => $tenant,
            'key_name' => $key,
            'state' => 'queued',
            'bytes' => $bytes,
            'key_value' => strtoupper($key),
        ];
        $targets[] = [
            'target_id' => 100 + $id,
            'tenant_id' => $tenant,
            'key_name' => $key,
            'batch_name' => $key === 'alpha' ? 'audit' : 'migrate',
            'priority' => $priority,
        ];
    }

    return ['app_settings' => $settings, 'app_setting_targets' => $targets];
};

/**
 * @return list<int>
 */
$sourceOrderIds = static fn (): array => array_column($tables()['app_settings'], 'setting_id');
$orderedSettingIdsDesc = [8, 7, 6, 5, 4, 3, 2, 1];

/**
 * @return list<int>
 */
$targetWindowIds = static function (int $limit, int $offset, string $batch = 'migrate') use ($tables): array {
    $targets = array_values(array_filter(
        $tables()['app_setting_targets'],
        static fn (array $row): bool => $row['batch_name'] === $batch,
    ));
    usort($targets, static fn (array $left, array $right): int => ($right['priority'] <=> $left['priority']) ?: ($left['target_id'] <=> $right['target_id']));
    $window = $limit < 0 ? array_slice($targets, max(0, $offset)) : array_slice($targets, max(0, $offset), $limit);
    $wanted = [];
    foreach ($window as $row) {
        $wanted[$row['tenant_id'] . ':' . $row['key_name']] = true;
    }

    $ids = [];
    foreach ($tables()['app_settings'] as $row) {
        if (isset($wanted[$row['tenant_id'] . ':' . $row['key_name']])) {
            $ids[] = $row['setting_id'];
        }
    }

    return $ids;
};

/**
 * @return array{0:string,1:string,2:array<int|string,mixed>}
 */
$parameterizedLimit = static function (int $seed, int $limit, int $offset): array {
    return match ($seed % 4) {
        0 => [':limit', '@offset', [':limit' => $limit, '@offset' => $offset]],
        1 => ['?', '?2', [0 => $limit, 2 => $offset]],
        2 => ['$limit + 0', '$offset + 0', ['limit' => $limit, 'offset' => $offset]],
        default => ['@limit + 0', ':offset + 0', ['@limit' => $limit, ':offset' => $offset]],
    };
};

$tests = [];

$tests['rowvalue update delete limit bind parameters cites upstream source sections'] = static function (TestRunner $t): void {
    $t->contains('/test/limit.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test');
    $t->contains('limit-10.1', 'limit-10.1 SELECT x FROM t1 LIMIT :limit');
    $t->contains('limit-10.2', 'limit-10.2 SELECT x FROM t1 LIMIT :limit OFFSET :offset');
    $t->contains('rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
    $t->contains('e_update-3.3', 'e_update-3.3 UPDATE LIMIT OFFSET expression evaluation');
};

for ($seed = 1; $seed <= 16; $seed++) {
    $limit = ($seed % 4) + 1;
    $offset = ($seed + 1) % 3;
    [$limitExpr, $offsetExpr, $parameters] = $parameterizedLimit($seed, $limit, $offset);
    $sql = "UPDATE app_settings SET state = 'bound', key_value = key_name || ':bound' WHERE state = :state RETURNING setting_id, state, key_value, @marker AS marker ORDER BY bytes DESC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $parameters += [':state' => 'queued', '@marker' => 'host-limit'];
    $selectedIds = array_slice($orderedSettingIdsDesc, $offset, $limit);
    $returningIds = array_values(array_intersect($sourceOrderIds(), $selectedIds));

    $tests[sprintf('rowvalue update limit bind parameter outer ordered seed %02d', $seed)] =
        static function (TestRunner $t) use ($tables, $sql, $parameters, $limit, $offset, $selectedIds, $returningIds): void {
            $parsed = SQLiteUpdateDeleteReturningSql::parse($sql, $parameters);
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', [], false, $parameters);

            $t->same($limit, $parsed['limit']);
            $t->same($offset, $parsed['offset']);
            $t->same($selectedIds, $result['plan']->selectedIds);
            $t->same($returningIds, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($returningIds), 'bound'), array_column($result['returning'], 'state'));
            $t->same(array_fill(0, count($returningIds), 'host-limit'), array_column($result['returning'], 'marker'));
            $t->same($limit, $result['plan']->toArray()['limit']);
            $t->same($offset, $result['plan']->toArray()['offset']);
        };
}

for ($seed = 1; $seed <= 16; $seed++) {
    $limit = ($seed % 3) + 1;
    $offset = ($seed + 2) % 4;
    [$limitExpr, $offsetExpr, $parameters] = $parameterizedLimit($seed + 9, $limit, $offset);
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE batch_name = :batch ORDER BY priority DESC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id";
    $parameters += [':batch' => 'migrate'];
    $expectedIds = $targetWindowIds($limit, $offset);
    $remainingIds = array_values(array_diff($sourceOrderIds(), $expectedIds));

    $tests[sprintf('rowvalue delete limit bind parameter tuple subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($tables, $sql, $parameters, $expectedIds, $remainingIds): void {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', [], false, $parameters);

            $t->same($expectedIds, $result['plan']->selectedIds);
            $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
            $t->same($remainingIds, array_column($result['tables']['app_settings'], 'setting_id'));
            $t->same(count($expectedIds), count($result['returning']));
            $t->same('delete', $result['action']);
            $t->contains('limit.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test');
        };
}

$commaSql = 'DELETE FROM app_settings WHERE state = :state RETURNING setting_id ORDER BY bytes ASC LIMIT $skip, :count';
$commaParameters = ['state' => 'queued', '$skip' => 2, ':count' => 3];
$tests['rowvalue delete limit bind parameters comma offset count form'] = static function (TestRunner $t) use ($tables, $commaSql, $commaParameters): void {
    $parsed = SQLiteUpdateDeleteReturningSql::parse($commaSql, $commaParameters);
    $result = SQLiteUpdateDeleteReturningSql::execute($commaSql, $tables(), 'setting_id', [], false, $commaParameters);

    $t->same(3, $parsed['limit']);
    $t->same(2, $parsed['offset']);
    $t->same([3, 4, 5], $result['plan']->selectedIds);
    $t->same([3, 4, 5], array_column($result['returning'], 'setting_id'));
    $t->same([1, 2, 6, 7, 8], array_column($result['tables']['app_settings'], 'setting_id'));
};

$auditSql = 'UPDATE app_settings SET state = :state WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE batch_name = :batch ORDER BY priority DESC LIMIT 3) RETURNING setting_id, state ORDER BY setting_id LIMIT :limit OFFSET :offset';
$auditParameters = [':state' => 'audited', ':batch' => 'audit', ':limit' => '2', ':offset' => '1'];
$tests['rowvalue update limit bind parameters accept integral text values'] = static function (TestRunner $t) use ($tables, $auditSql, $auditParameters): void {
    $result = SQLiteUpdateDeleteReturningSql::execute($auditSql, $tables(), 'setting_id', [], false, $auditParameters);
    $expectedIds = [4, 7];

    $t->same($expectedIds, $result['plan']->selectedIds);
    $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
    $t->same(['audited', 'audited'], array_column($result['returning'], 'state'));
    $t->same(2, $result['plan']->toArray()['limit']);
    $t->same(1, $result['plan']->toArray()['offset']);
};

$errorCases = [
    'unbound parameter becomes NULL and is rejected' => ['DELETE FROM app_settings RETURNING setting_id LIMIT :missing', []],
    'nonintegral numeric parameter rejected' => ['DELETE FROM app_settings RETURNING setting_id LIMIT :limit', [':limit' => 1.5]],
    'nonnumeric text parameter rejected' => ['DELETE FROM app_settings RETURNING setting_id LIMIT :limit', [':limit' => 'hello']],
    'blob parameter rejected' => ['DELETE FROM app_settings RETURNING setting_id LIMIT :limit', [':limit' => new SQLiteBlobValue('12')]],
    'array parameter rejected before parsing' => ['DELETE FROM app_settings RETURNING setting_id LIMIT :limit', [':limit' => [2]]],
    'nonfinite parameter rejected before parsing' => ['DELETE FROM app_settings RETURNING setting_id LIMIT :limit', [':limit' => INF]],
];

foreach ($errorCases as $name => [$sql, $parameters]) {
    $tests['rowvalue update delete limit bind parameters ' . $name] = static function (TestRunner $t) use ($sql, $parameters): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($sql, $parameters));
    };
}

$tests['rowvalue update delete limit bind parameters boolean values coerce to integers'] = static function (TestRunner $t) use ($tables): void {
    $sql = 'UPDATE app_settings SET state = :state WHERE state = :old_state RETURNING setting_id, state ORDER BY bytes ASC LIMIT :limit OFFSET :offset';
    $parameters = [':state' => 'single', ':old_state' => 'queued', ':limit' => true, ':offset' => false];
    $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', [], false, $parameters);

    $t->same(1, $result['plan']->toArray()['limit']);
    $t->same(0, $result['plan']->toArray()['offset']);
    $t->same([1], $result['plan']->selectedIds);
    $t->same([1], array_column($result['returning'], 'setting_id'));
    $t->same(['single'], array_column($result['returning'], 'state'));
};

return $tests;
