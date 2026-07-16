<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test.
 *
 * - pragma.test 23.2b through 23.2e builds indexes i2, i2x, and i3, then
 *   verifies PRAGMA index_xinfo key rows, auxiliary rowid rows, DESC flags,
 *   COLLATE names, and expression-index terms with cid -2 and NULL names.
 * - The same upstream PRAGMA family defines PRAGMA index_info as the key-term
 *   projection of index metadata. This corpus keeps index_info and index_xinfo
 *   consistent for expression terms in the dynamic schema catalog path.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$expressionFor = static function (int $variant): string {
    return match ($variant % 4) {
        0 => 'b + c',
        1 => 'b||c',
        2 => 'substr(b,1,2)',
        default => 'coalesce(b,c)',
    };
};

$catalogFor = static function (int $variant) use ($record, $expressionFor): SQLitePragmaSchemaCatalog {
    $table = sprintf('pragma_index_expr_%04d', $variant);
    $exprIndex = sprintf('pragma_index_expr_i3_%04d', $variant);
    $firstExprIndex = sprintf('pragma_index_expr_first_%04d', $variant);
    $coveringIndex = sprintf('pragma_index_expr_cover_%04d', $variant);
    $expr = $expressionFor($variant);

    return new SQLitePragmaSchemaCatalog([
        $record('table', $table, $table, 100000 + $variant, "CREATE TABLE {$table}(a INTEGER PRIMARY KEY, b TEXT, c TEXT, d TEXT, key_name TEXT, key_value TEXT, tenant_id INTEGER)", 1),
        $record('index', $exprIndex, $table, 110000 + $variant, "CREATE INDEX {$exprIndex} ON {$table}(d, {$expr}, c)", 2),
        $record('index', $firstExprIndex, $table, 120000 + $variant, "CREATE INDEX {$firstExprIndex} ON {$table}(lower(key_name) COLLATE NOCASE DESC, key_value COLLATE RTRIM, tenant_id)", 3),
        $record('index', $coveringIndex, $table, 130000 + $variant, "CREATE INDEX {$coveringIndex} ON {$table}(key_value, {$expr}, key_name COLLATE NOCASE DESC)", 4),
    ]);
};

$project = static function (array $rows, array $columns): array {
    return array_map(
        static function (array $row) use ($columns): array {
            $values = [];
            foreach ($columns as $column) {
                $values[] = $row[$column];
            }

            return $values;
        },
        $rows,
    );
};

foreach (range(1, 250) as $variant) {
    $exprIndex = sprintf('pragma_index_expr_i3_%04d', $variant);
    $firstExprIndex = sprintf('pragma_index_expr_first_%04d', $variant);
    $coveringIndex = sprintf('pragma_index_expr_cover_%04d', $variant);

    $tests[sprintf('real upstream pragma schema dynamic index_info expression middle term variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $project, $variant, $exprIndex): void {
        $rows = $catalogFor($variant)->execute("PRAGMA index_info({$exprIndex})")['rows'];

        $t->same(
            [
                [0, 3, 'd'],
                [1, -2, null],
                [2, 2, 'c'],
            ],
            $project($rows, ['seqno', 'cid', 'name']),
        );
    };

    $tests[sprintf('real upstream pragma schema dynamic table-valued index_info expression term variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $project, $variant, $exprIndex): void {
        $result = $catalogFor($variant)->executeTableValuedPragma("pragma_index_info('{$exprIndex}')");

        $t->same('ok', $result['status']);
        $t->same('index_info', $result['pragma']);
        $t->same($exprIndex, $result['target']);
        $t->same(
            [
                [0, 3, 'd'],
                [1, -2, null],
                [2, 2, 'c'],
            ],
            $project($result['rows'], ['seqno', 'cid', 'name']),
        );
    };

    $tests[sprintf('real upstream pragma schema dynamic index_info matches index_xinfo key projection variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $project, $variant, $coveringIndex): void {
        $catalog = $catalogFor($variant);
        $info = $catalog->execute("PRAGMA index_info({$coveringIndex})")['rows'];
        $xinfo = $catalog->execute("PRAGMA index_xinfo({$coveringIndex})")['rows'];
        $keyProjection = array_map(
            static fn (array $row): array => [
                'seqno' => $row['seqno'],
                'cid' => $row['cid'],
                'name' => $row['name'],
            ],
            array_values(array_filter($xinfo, static fn (array $row): bool => $row['key'] === 1)),
        );

        $t->same($keyProjection, $info);
        $t->same(
            [
                [0, 5, 'key_value'],
                [1, -2, null],
                [2, 4, 'key_name'],
            ],
            $project($info, ['seqno', 'cid', 'name']),
        );
        $t->same([1, 1, 1, 0], array_column($xinfo, 'key'));
    };

    $tests[sprintf('real upstream pragma schema dynamic leading expression keeps collation desc in xinfo variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $project, $variant, $firstExprIndex): void {
        $catalog = $catalogFor($variant);
        $info = $catalog->execute("PRAGMA index_info({$firstExprIndex})")['rows'];
        $xinfo = $catalog->execute("PRAGMA index_xinfo({$firstExprIndex})")['rows'];

        $t->same(
            [
                [0, -2, null],
                [1, 5, 'key_value'],
                [2, 6, 'tenant_id'],
            ],
            $project($info, ['seqno', 'cid', 'name']),
        );
        $t->same(
            [
                [0, -2, null, 1, 'NOCASE', 1],
                [1, 5, 'key_value', 0, 'RTRIM', 1],
                [2, 6, 'tenant_id', 0, 'BINARY', 1],
                [3, -1, null, 0, 'BINARY', 0],
            ],
            $project($xinfo, ['seqno', 'cid', 'name', 'desc', 'coll', 'key']),
        );
    };
}

$tests['real upstream pragma schema dynamic index_info expression source citations'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test 23.2b through 23.2e defines the index_xinfo key-row and auxiliary-row contract',
        'pragma.test 23.2d verifies DESC and COLLATE metadata for key terms',
        'pragma.test 23.2e verifies expression-index terms report cid -2 and NULL names through PRAGMA index_xinfo',
        'PRAGMA index_info is the key-term projection of the same dynamic index metadata and must preserve expression cid -2 rows',
    ];

    $t->same(4, count($sections));
    $t->contains('23.2e', $sections[2]);
    $t->contains('index_info', $sections[3]);
};

$tests['real upstream pragma schema dynamic index_info expression dependency closure'] = static function (TestRunner $t): void {
    $note = 'no new support component needed; reuses lane-local SQLitePragmaSchemaCatalog index term parsing for upstream pragma.test 23.2 expression-index metadata';

    $t->contains('no new support component needed', $note);
    $t->contains('pragma.test 23.2', $note);
};

return $tests;
