<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test
 * - selectC-4.2: projecting one column from a derived SELECT DISTINCT over
 *   two columns preserves one output row for each distinct inner pair.
 * - selectC-4.2b: the same distinct-derived result survives view-like reuse.
 *
 * The native PHP port does not materialize CREATE VIEW in this focused slice,
 * so the view case is represented by executing the same derived SELECT text
 * through multiple generic application row sets.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlatSelect = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label);
    $t->same(count($expected), count($actual), 'flat value count for ' . $label);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last value guard for ' . $label,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat value fingerprint for ' . $label,
    );
};

/**
 * @return list<array{tenant_id:string, key_group:string, payload:string}>
 */
$distinctDerivedRows = static function (int $seed): array {
    $tenant = (string) (($seed % 5) + 1);
    $otherTenant = (string) (($seed % 7) + 20);
    $groups = 2 + ($seed % 5);
    $duplicates = 1 + ($seed % 4);
    $rows = [];

    for ($group = 1; $group <= $groups; $group++) {
        for ($copy = 0; $copy < $duplicates; $copy++) {
            $rows[] = [
                'tenant_id' => $tenant,
                'key_group' => 'g' . $group,
                'payload' => 'p' . $seed . '_' . $group . '_' . $copy,
            ];
        }
    }

    for ($group = 1; $group <= 3; $group++) {
        $rows[] = [
            'tenant_id' => $otherTenant,
            'key_group' => 'h' . (($seed + $group) % 4),
            'payload' => 'q' . $seed . '_' . $group,
        ];
    }

    return $rows;
};

/**
 * @param list<array{tenant_id:string, key_group:string, payload:string}> $rows
 * @return list<mixed>
 */
$expectedDistinctTenantProjection = static function (array $rows, ?string $tenantFilter = null, ?int $limit = null, int $offset = 0): array {
    $seen = [];
    $pairs = [];

    foreach ($rows as $row) {
        if ($tenantFilter !== null && $row['tenant_id'] !== $tenantFilter) {
            continue;
        }

        $key = $row['tenant_id'] . "\0" . $row['key_group'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $pairs[] = ['tenant_id' => $row['tenant_id'], 'key_group' => $row['key_group']];
    }

    usort($pairs, static function (array $left, array $right): int {
        $tenantOrder = strcmp($left['tenant_id'], $right['tenant_id']);
        if ($tenantOrder !== 0) {
            return $tenantOrder;
        }

        return strcmp($left['key_group'], $right['key_group']);
    });

    $slice = array_slice($pairs, $offset, $limit);

    return array_map(static fn (array $row): string => $row['tenant_id'], $slice);
};

$tests = [];

$tests['real upstream selectC.test selectC-4.2 distinct derived source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test';
    $t->true(is_file($source), 'hydrated upstream selectC.test is available');
    $sourceText = file_get_contents($source);
    $t->contains('selectC-4.2', $sourceText);
    $t->contains('select a from (select distinct a, b from t_distinct_bug)', $sourceText);
    $t->contains('selectC-4.2b', $sourceText);
};

$tests['real upstream selectC.test selectC-4.2 canonical distinct derived projection'] =
    static function (TestRunner $t) use ($assertFlatSelect): void {
        $tables = [
            'app_distinct_source' => [
                ['tenant_id' => '1', 'key_group' => '1', 'payload' => 'a'],
                ['tenant_id' => '1', 'key_group' => '2', 'payload' => 'b'],
                ['tenant_id' => '1', 'key_group' => '3', 'payload' => 'c'],
                ['tenant_id' => '1', 'key_group' => '1', 'payload' => 'd'],
                ['tenant_id' => '1', 'key_group' => '2', 'payload' => 'e'],
                ['tenant_id' => '1', 'key_group' => '3', 'payload' => 'f'],
            ],
        ];

        $assertFlatSelect(
            $t,
            'SELECT tenant_id FROM (SELECT DISTINCT tenant_id, key_group FROM app_distinct_source)',
            $tables,
            ['1', '1', '1'],
            'selectC-4.2 canonical derived distinct projection',
        );
    };

for ($seed = 0; $seed < 1000; $seed++) {
    $rows = $distinctDerivedRows($seed);
    $tables = ['app_distinct_source' => $rows];
    $tenant = $rows[0]['tenant_id'];
    $limit = 1 + ($seed % 4);
    $offset = $seed % 3;
    $expectedAll = $expectedDistinctTenantProjection($rows);
    $expectedFiltered = $expectedDistinctTenantProjection($rows, $tenant);
    $expectedWindow = $expectedDistinctTenantProjection($rows, null, $limit, $offset);

    $tests[sprintf('real upstream selectC.test selectC-4.2 dynamic distinct derived all pairs %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $tables, $expectedAll, $seed): void {
            $assertFlatSelect(
                $t,
                'SELECT tenant_id FROM (SELECT DISTINCT tenant_id, key_group FROM app_distinct_source) ORDER BY tenant_id, key_group',
                $tables,
                $expectedAll,
                'selectC-4.2 dynamic all pairs ' . $seed,
            );
        };

    $tests[sprintf('real upstream selectC.test selectC-4.2 dynamic distinct derived filtered tenant %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $tables, $tenant, $expectedFiltered, $seed): void {
            $assertFlatSelect(
                $t,
                "SELECT tenant_id FROM (SELECT DISTINCT tenant_id, key_group FROM app_distinct_source WHERE tenant_id='{$tenant}') ORDER BY tenant_id, key_group",
                $tables,
                $expectedFiltered,
                'selectC-4.2 dynamic filtered tenant ' . $seed,
            );
        };

    $tests[sprintf('real upstream selectC.test selectC-4.2b dynamic distinct derived reused window %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $tables, $limit, $offset, $expectedWindow, $seed): void {
            $assertFlatSelect(
                $t,
                "SELECT tenant_id FROM (SELECT DISTINCT tenant_id, key_group FROM app_distinct_source) ORDER BY tenant_id, key_group LIMIT {$limit} OFFSET {$offset}",
                $tables,
                $expectedWindow,
                'selectC-4.2b dynamic reused window ' . $seed,
            );
        };
}

return $tests;
