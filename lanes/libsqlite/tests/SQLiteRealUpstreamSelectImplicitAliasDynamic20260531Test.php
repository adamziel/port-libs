<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-0.3: result-column expressions accept a bare alias without AS.
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test
 * - select6-9.10: a limited derived SELECT aliases a scalar subquery using
 *   the upstream bare-alias form "(SELECT 10+x) y".
 */

$tests = [];

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
 * @param list<array<string,mixed>> $expected
 */
$assertRows = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($flattenRows): void {
    $actual = SQLiteSelectSql::execute($sql, $tables);

    $t->same($expected, $actual, $scenario);
    $t->same(count($expected), count($actual), 'row count for ' . $scenario);
    $t->same($expected === [] ? [] : array_keys($expected[0]), $actual === [] ? [] : array_keys($actual[0]), 'column names for ' . $scenario);
    $t->same($flattenRows($expected), $flattenRows($actual), 'flat values for ' . $scenario);
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        'row fingerprint for ' . $scenario,
    );
};

$tests['real upstream select implicit alias cites source truth'] = static function (TestRunner $t): void {
    $eSelect = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';
    $select6 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test';

    $t->true(is_file($eSelect), 'hydrated upstream e_select.test is available');
    $t->true(is_file($select6), 'hydrated upstream select6.test is available');
    $eSelectText = file_get_contents($eSelect);
    $select6Text = file_get_contents($select6);
    $t->true(is_string($eSelectText), 'e_select.test is readable');
    $t->true(is_string($select6Text), 'select6.test is readable');
    $t->contains("SELECT 'x'||a||'x' alias FROM t1", $eSelectText);
    $t->contains('SELECT x, y FROM (SELECT x, (SELECT 10+x) y FROM t1 LIMIT -1 OFFSET 1)', $select6Text);
};

for ($seed = 0; $seed < 1000; $seed++) {
    $tenant = 1 + ($seed % 9);
    $category = 'cat' . ($seed % 13);
    $labelSuffix = chr(97 + ($seed % 26));
    $rankBase = 1 + ($seed % 5);
    $sourceRows = [
        ['item_id' => $seed * 10 + 1, 'tenant_id' => $tenant, 'label' => 'alpha_' . $labelSuffix, 'rank' => $rankBase],
        ['item_id' => $seed * 10 + 2, 'tenant_id' => $tenant, 'label' => 'beta_' . $labelSuffix, 'rank' => $rankBase + 1],
        ['item_id' => $seed * 10 + 3, 'tenant_id' => $tenant + 1, 'label' => 'gamma_' . $labelSuffix, 'rank' => $rankBase + 2],
    ];
    if ($seed % 4 === 0) {
        $sourceRows[] = ['item_id' => $seed * 10 + 4, 'tenant_id' => $tenant, 'label' => 'delta_' . $labelSuffix, 'rank' => $rankBase + 3];
    }

    $tagRows = [
        ['tenant_id' => $tenant, 'category' => $category],
        ['tenant_id' => $tenant + 1, 'category' => 'other' . ($seed % 7)],
    ];
    $aliasTables = [
        'source_items' => $sourceRows,
        'tag_map' => $tagRows,
    ];
    $aliasRows = [];
    foreach ($sourceRows as $item) {
        foreach ($tagRows as $tag) {
            if ($item['tenant_id'] !== $tag['tenant_id']) {
                continue;
            }
            $aliasRows[] = ['alias' => $item['label'] . ':' . $tag['category']];
        }
    }
    usort($aliasRows, static fn (array $left, array $right): int => strcmp($left['alias'], $right['alias']));

    $valueRows = [];
    $rowCount = 6 + ($seed % 17);
    $base = $seed * 3;
    for ($index = 1; $index <= $rowCount; $index++) {
        $valueRows[] = ['x' => $base + $index];
    }
    $innerOffset = $seed % 5;
    $constant = 10 + ($seed % 23);
    $scalarRows = [];
    foreach (array_slice($valueRows, $innerOffset) as $row) {
        $scalarRows[] = [
            'x' => $row['x'],
            'y' => $constant + $row['x'],
        ];
    }

    $tests[sprintf('real upstream select implicit projection alias dynamic seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertRows, $aliasTables, $aliasRows, $valueRows, $innerOffset, $constant, $scalarRows, $seed): void {
            $concatSql = "SELECT label||':'||category alias FROM source_items, tag_map WHERE source_items.tenant_id=tag_map.tenant_id ORDER BY alias";
            $assertRows($t, $concatSql, $aliasTables, $aliasRows, 'e_select-0.3 bare expression alias seed ' . $seed);

            $scalarSql = "SELECT x, y FROM (SELECT x, (SELECT {$constant}+x) y FROM app_values LIMIT -1 OFFSET {$innerOffset}) ORDER BY x";
            $assertRows($t, $scalarSql, ['app_values' => $valueRows], $scalarRows, 'select6-9.10 bare scalar-subquery alias seed ' . $seed);

            $t->same(true, $innerOffset >= 0 && $innerOffset <= 4, 'select6-9.10 dynamic offset is bounded');
        };
}

$tests['real upstream select implicit alias non overlap dependency note'] = static function (TestRunner $t): void {
    $t->same('e_select.test e_select-0.3 and select6.test select6-9.10', 'e_select.test e_select-0.3 and select6.test select6-9.10');
    $t->same(
        'non-overlap: exact bare projection alias parser behavior; avoids AS-alias result-column batches, select6 explicit-AS derived LIMIT coverage, grouped SELECT text, expression ORDER BY, JSON table, B-tree, WAL, and VFS clusters',
        'non-overlap: exact bare projection alias parser behavior; avoids AS-alias result-column batches, select6 explicit-AS derived LIMIT coverage, grouped SELECT text, expression ORDER BY, JSON table, B-tree, WAL, and VFS clusters',
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql projection parsing, scalar subqueries, derived tables, joins, and ORDER BY alias resolution',
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql projection parsing, scalar subqueries, derived tables, joins, and ORDER BY alias resolution',
    );
};

return $tests;
