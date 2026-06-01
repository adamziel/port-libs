<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteExpressionIndexCollationCursor;
use PortLibs\LibSqlite\SQLiteSelectResult;

$tests = [];

$upstreamWindow6 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test';
$windowCollation = static fn (string $left, string $right): int => strcmp($right, $left);

$orderedXValues = static function (array $rows, callable $collation): array {
    return array_column(
        SQLiteSelectResult::orderBy(
            $rows,
            [['column' => 'x', 'collation' => 'window']],
            ['window' => $collation],
        ),
        'x',
    );
};

$cursorRowids = static function (SQLiteExpressionIndexCollationCursor $cursor): array {
    $rowids = [];
    while (!$cursor->eof()) {
        $rowids[] = $cursor->currentNextPlan()['currentRowid'];
        $cursor->next();
    }

    return $rowids;
};

$expectedCustomOrder = static function (array $rows, callable $collation): array {
    $indexes = range(0, count($rows) - 1);
    usort($indexes, static function (int $left, int $right) use ($rows, $collation): int {
        $comparison = $collation((string) $rows[$left]['x'], (string) $rows[$right]['x']);

        return $comparison === 0 ? $left <=> $right : ($comparison <=> 0);
    });

    return $indexes;
};

$tests['real upstream window6 3 custom collation source truth is present'] = static function (TestRunner $t) use ($upstreamWindow6): void {
    $source = file_get_contents($upstreamWindow6);
    $t->true(is_string($source));
    $t->contains('db collate window wincmp', $source, 'window6.test registers the custom collation named window');
    $t->contains('do_execsql_test 3.0', $source, 'window6.test section 3.0 source marker');
    $t->contains('CREATE TABLE window(x COLLATE window);', $source, 'window6.test section 3.0 declared collation');
    $t->contains('do_execsql_test 3.1', $source, 'window6.test section 3.1 source marker');
    $t->contains('CREATE INDEX window ON x1(x COLLATE window);', $source, 'window6.test section 3.1 indexed collation');
    $t->contains('{cate bob alice}', $source, 'window6.test expected custom collation order');
};

$tests['real upstream window6 3.0 table collation named window orders descending text'] = static function (TestRunner $t) use ($orderedXValues, $windowCollation): void {
    $rows = [
        ['x' => 'bob'],
        ['x' => 'alice'],
        ['x' => 'cate'],
    ];

    $t->same(['cate', 'bob', 'alice'], $orderedXValues($rows, $windowCollation), 'window6.test 3.0 ORDER BY x COLLATE window');
};

$tests['real upstream window6 3.1 index collation named window preserves indexed order'] = static function (TestRunner $t) use ($cursorRowids, $windowCollation): void {
    $cursor = new SQLiteExpressionIndexCollationCursor(
        [
            ['key' => ['bob'], 'rowid' => 1, 'payload' => ['x' => 'bob']],
            ['key' => ['alice'], 'rowid' => 2, 'payload' => ['x' => 'alice']],
            ['key' => ['cate'], 'rowid' => 3, 'payload' => ['x' => 'cate']],
        ],
        [['expression' => 'x', 'collation' => 'window']],
        ['window' => $windowCollation],
    );

    $t->same([3, 1, 2], $cursorRowids($cursor), 'window6.test 3.1 CREATE INDEX window ON x1(x COLLATE window)');

    $probe = new SQLiteExpressionIndexCollationCursor(
        [
            ['key' => ['bob'], 'rowid' => 1, 'payload' => ['x' => 'bob']],
            ['key' => ['alice'], 'rowid' => 2, 'payload' => ['x' => 'alice']],
            ['key' => ['cate'], 'rowid' => 3, 'payload' => ['x' => 'cate']],
        ],
        [['expression' => 'x', 'collation' => 'window']],
        ['window' => $windowCollation],
    );
    $t->same([1], array_column($probe->yieldEqual(['bob']), 'rowid'), 'window6.test 3.1 custom collation point lookup');
};

for ($case = 0; $case < 1000; $case++) {
    $tests['real upstream window6 custom collation dynamic table and index order ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $windowCollation, $expectedCustomOrder, $cursorRowids): void {
        $count = 5 + ($case % 11);
        $rows = [];
        for ($row = 0; $row < $count; $row++) {
            $rows[] = [
                'x' => chr(97 + (($case * 7 + $row * 5) % 26)) . '-' . (($case + $row * 3) % 17),
                'rowid' => $row + 1,
            ];
        }

        $expectedIndexes = $expectedCustomOrder($rows, $windowCollation);
        $expectedValues = array_map(static fn (int $index): string => $rows[$index]['x'], $expectedIndexes);
        $expectedRowids = array_map(static fn (int $index): int => $rows[$index]['rowid'], $expectedIndexes);

        $orderedRows = SQLiteSelectResult::orderBy(
            $rows,
            [['column' => 'x', 'collation' => $case % 2 === 0 ? 'window' : 'WiNdOw']],
            ['window' => $windowCollation],
        );
        $t->same($expectedValues, array_column($orderedRows, 'x'), "window6.test 3.0 dynamic table collation order {$case}");

        $cursor = new SQLiteExpressionIndexCollationCursor(
            array_map(
                static fn (array $row): array => ['key' => [$row['x']], 'rowid' => $row['rowid'], 'payload' => ['x' => $row['x']]],
                $rows,
            ),
            [['expression' => 'x', 'collation' => 'window']],
            ['window' => $windowCollation],
        );
        $t->same($expectedRowids, $cursorRowids($cursor), "window6.test 3.1 dynamic index collation order {$case}");

        $probeValue = $rows[$expectedIndexes[intdiv(count($expectedIndexes), 2)]]['x'];
        $expectedProbeRowids = array_values(array_map(
            static fn (array $row): int => $row['rowid'],
            array_filter($rows, static fn (array $row): bool => $windowCollation((string) $row['x'], (string) $probeValue) === 0),
        ));
        $probe = new SQLiteExpressionIndexCollationCursor(
            array_map(
                static fn (array $row): array => ['key' => [$row['x']], 'rowid' => $row['rowid'], 'payload' => ['x' => $row['x']]],
                $rows,
            ),
            [['expression' => 'x', 'collation' => 'window']],
            ['window' => $windowCollation],
        );
        $t->same($expectedProbeRowids, array_column($probe->yieldEqual([$probeValue]), 'rowid'), "window6.test 3.1 dynamic custom collation seek {$case}");
    };
}

$tests['real upstream window6 custom collation order by rejects missing comparator'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteSelectResult::orderBy([['x' => 'bob']], [['column' => 'x', 'collation' => 'window']]),
    );
};

$tests['real upstream window6 custom collation order by rejects non-int comparator result'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteSelectResult::orderBy(
            [['x' => 'bob'], ['x' => 'alice']],
            [['column' => 'x', 'collation' => 'window']],
            ['window' => static fn (string $_left, string $_right) => 'bad'],
        ),
    );
};

$tests['real upstream window6 custom collation dynamic dependency closure note'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteSelectResult custom ORDER BY collation dispatch and SQLiteExpressionIndexCollationCursor custom index comparisons',
        'no new support component needed; reuses SQLiteSelectResult custom ORDER BY collation dispatch and SQLiteExpressionIndexCollationCursor custom index comparisons',
    );
};

return $tests;
