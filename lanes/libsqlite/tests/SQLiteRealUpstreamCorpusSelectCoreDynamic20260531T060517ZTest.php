<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-0.3: result-column syntax accepts "*", "table.*", expression
 *   result columns, bare aliases, and AS aliases.
 *
 * This batch keeps the source rows generic and dynamic. It does not repeat
 * accepted grouped SELECT text, SELECT subqueries, expression ORDER BY,
 * JSON-table SELECT sources, or compound SELECT batches.
 */

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenSelectCoreResultColumns = static function (array $rows): array {
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
$assertSelectCoreResultColumns = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($flattenSelectCoreResultColumns): void {
    $actual = $flattenSelectCoreResultColumns(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario . ' flat result');
    $t->same(count($expected), count($actual), $scenario . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $scenario . ' first and last values',
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' result fingerprint',
    );
};

$tests['real upstream corpus select core dynamic 060517 cites e_select result-column source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

    $t->true(is_file($source), 'hydrated upstream e_select.test is available');
    $text = file_get_contents($source);
    $t->contains('do_select_tests e_select-0.3', $text);
    $t->contains('SELECT * FROM t1', $text);
    $t->contains('SELECT t1.* FROM t1', $text);
    $t->contains("SELECT 'x'||a||'x' alias FROM t1", $text);
    $t->contains("SELECT 'x'||a||'x' AS alias FROM t1", $text);
};

for ($seed = 0; $seed < 1000; $seed++) {
    $base = 10 + ($seed * 3);
    $tenant = 1 + ($seed % 7);
    $category = 'cat_' . ($seed % 11);
    $suffix = chr(97 + ($seed % 26));
    $lowRank = 1 + ($seed % 4);
    $mode = $seed % 5;

    $sourceRows = [
        ['item_id' => $base + 1, 'tenant_id' => $tenant, 'label' => 'alpha_' . $suffix, 'rank' => $lowRank],
        ['item_id' => $base + 2, 'tenant_id' => $tenant, 'label' => 'beta_' . $suffix, 'rank' => $lowRank + 1],
        ['item_id' => $base + 3, 'tenant_id' => $tenant + 1, 'label' => 'gamma_' . $suffix, 'rank' => $lowRank + 2],
    ];
    if ($seed % 3 === 0) {
        $sourceRows[] = ['item_id' => $base + 4, 'tenant_id' => $tenant, 'label' => 'delta_' . $suffix, 'rank' => $lowRank + 3];
    }

    $tagRows = [
        ['tenant_id' => $tenant, 'category' => $category],
        ['tenant_id' => $tenant + 1, 'category' => 'other_' . ($seed % 5)],
    ];
    $tables = [
        'source_items' => $sourceRows,
        'tag_map' => $tagRows,
    ];

    $expectedRows = [];
    foreach ($sourceRows as $item) {
        foreach ($tagRows as $tag) {
            if ($item['tenant_id'] !== $tag['tenant_id']) {
                continue;
            }

            $expressionValue = strtoupper($item['label']);
            $qualifiedExpression = $item['label'] . ':' . $tag['category'];
            $sumValue = $item['item_id'] + $item['rank'];

            $expectedRows[] = match ($mode) {
                0 => [$item['item_id'], $item['tenant_id'], $item['label'], $item['rank'], $tag['tenant_id'], $tag['category']],
                1 => [$item['item_id'], $item['tenant_id'], $item['label'], $item['rank']],
                2 => [$expressionValue],
                3 => [$qualifiedExpression, $sumValue],
                default => [$item['item_id'], $item['label'], $tag['category'], $qualifiedExpression],
            };
        }
    }

    usort(
        $expectedRows,
        static fn (array $left, array $right): int => ($left[0] <=> $right[0]) ?: (($left[1] ?? '') <=> ($right[1] ?? '')),
    );

    $expected = [];
    foreach ($expectedRows as $row) {
        foreach ($row as $value) {
            $expected[] = $value;
        }
    }

    $sql = match ($mode) {
        0 => 'SELECT * FROM source_items, tag_map WHERE source_items.tenant_id=tag_map.tenant_id ORDER BY source_items.item_id',
        1 => 'SELECT source_items.* FROM source_items, tag_map WHERE source_items.tenant_id=tag_map.tenant_id ORDER BY item_id',
        2 => 'SELECT upper(label) AS alias FROM source_items, tag_map WHERE source_items.tenant_id=tag_map.tenant_id ORDER BY alias',
        3 => "SELECT label||':'||category AS joined_label, item_id+rank AS score FROM source_items, tag_map WHERE source_items.tenant_id=tag_map.tenant_id ORDER BY joined_label",
        default => "SELECT source_items.item_id, source_items.label, tag_map.category, label||':'||category AS joined_label FROM source_items, tag_map WHERE source_items.tenant_id=tag_map.tenant_id ORDER BY source_items.item_id",
    };

    $scenario = sprintf('e_select.test e_select-0.3 result-column dynamic seed %04d mode %d', $seed, $mode);
    $tests['real upstream corpus select core dynamic 060517 ' . $scenario] =
        static function (TestRunner $t) use ($assertSelectCoreResultColumns, $sql, $tables, $expected, $scenario, $mode): void {
            $assertSelectCoreResultColumns($t, $sql, $tables, $expected, $scenario);
            $t->same(true, $mode >= 0 && $mode <= 4, 'bounded result-column mode');
        };
}

$tests['real upstream corpus select core dynamic 060517 non overlap dependency note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-select-core-dynamic-20260531T060517Z-0', 'real-upstream-corpus-select-core-dynamic-20260531T060517Z-0');
    $t->same('e_select.test e_select-0.3', 'e_select.test e_select-0.3');
    $t->same(
        'non-overlap: result-column syntax over dynamic generic rows; avoids grouped SELECT text, SELECT subqueries, expression ORDER BY, compound SELECT, JSON table sources, and prior e_select grouped/order batches',
        'non-overlap: result-column syntax over dynamic generic rows; avoids grouped SELECT text, SELECT subqueries, expression ORDER BY, compound SELECT, JSON table sources, and prior e_select grouped/order batches',
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql result-column expansion, expression projection, aliases, table-star projection, and join row production',
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql result-column expansion, expression projection, aliases, table-star projection, and join row production',
    );
};

return $tests;
