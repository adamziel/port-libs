<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeSortCompare;
use PortLibs\LibSqlite\SQLiteVdbeSorterCursor;

$tests = [];

$adjacentCases = [
    'numeric text equals integer next' => [['value' => '10'], ['value' => 10], ['value'], 'C', [], [], [], 0],
    'integer next orders above numeric text current' => [['value' => '9'], ['value' => 10], ['value'], 'C', [], [], [], -1],
    'numeric text current orders above integer next' => [['value' => '11'], ['value' => 10], ['value'], 'C', [], [], [], 1],
    'leading zero text equals integer next' => [['value' => '010'], ['value' => 10], ['value'], 'C', [], [], [], 0],
    'decimal text equals integer next' => [['value' => '10.0'], ['value' => 10], ['value'], 'C', [], [], [], 0],
    'exponent text equals integer next' => [['value' => '1e1'], ['value' => 10], ['value'], 'C', [], [], [], 0],
    'negative exponent text equals integer next' => [['value' => '-1e1'], ['value' => -10], ['value'], 'C', [], [], [], 0],
    'malformed numeric text remains above integer next' => [['value' => '10x'], ['value' => 10], ['value'], 'C', [], [], [], 1],
    'empty text remains above integer next' => [['value' => ''], ['value' => 0], ['value'], 'C', [], [], [], 1],
    'whitespace text remains above integer next' => [['value' => '   '], ['value' => 0], ['value'], 'C', [], [], [], 1],
    'blob numeric bytes equal integer next' => [['value' => new SQLiteBlobValue('10')], ['value' => 10], ['value'], 'C', [], [], [], 0],
    'blob malformed bytes remain above integer next' => [['value' => new SQLiteBlobValue('10x')], ['value' => 10], ['value'], 'C', [], [], [], 1],
    'text affinity converts integer current lexically above next' => [['value' => 2], ['value' => '10'], ['value'], 'G', [], [], [], 1],
    'text affinity converts real current lexically above next' => [['value' => 2.5], ['value' => '10'], ['value'], 'G', [], [], [], 1],
    'text affinity converts boolean current to one' => [['value' => true], ['value' => '1'], ['value'], 'G', [], [], [], 0],
    'text affinity converts false current to zero' => [['value' => false], ['value' => '0'], ['value'], 'G', [], [], [], 0],
    'none affinity leaves integer below text next' => [['value' => 10], ['value' => '2'], ['value'], 'A', [], [], [], -1],
    'blob affinity leaves integer below blob next' => [['value' => 10], ['value' => new SQLiteBlobValue('2')], ['value'], 'B', [], [], [], -1],
    'real affinity decimal text equals real next' => [['value' => '2.25'], ['value' => 2.25], ['value'], 'E', [], [], [], 0],
    'float affinity decimal text equals real next' => [['value' => '2.25'], ['value' => 2.25], ['value'], 'F', [], [], [], 0],
    'integer affinity signed text equals integer next' => [['value' => '+8'], ['value' => 8], ['value'], 'D', [], [], [], 0],
    'overflow numeric text orders above max integer next' => [['value' => '9223372036854775808'], ['value' => 9223372036854775807], ['value'], 'C', [], [], [], 1],
    'negative overflow numeric text equals min integer next by double rounding' => [['value' => '-9223372036854775809'], ['value' => PHP_INT_MIN], ['value'], 'C', [], [], [], 0],
    'nocase text current equals next' => [['value' => 'Plugin'], ['value' => 'plugin'], ['value'], 'G', ['NOCASE'], [], [], 0],
    'binary text current orders before lowercase next' => [['value' => 'Plugin'], ['value' => 'plugin'], ['value'], 'G', ['BINARY'], [], [], -1],
    'rtrim current equals next without trailing spaces' => [['value' => 'cache   '], ['value' => 'cache'], ['value'], 'G', ['RTRIM'], [], [], 0],
    'rtrim next equals current without trailing spaces' => [['value' => 'cache'], ['value' => 'cache   '], ['value'], 'G', ['RTRIM'], [], [], 0],
    'nocase does not trim next spaces' => [['value' => 'cache'], ['value' => 'CACHE   '], ['value'], 'G', ['NOCASE'], [], [], -1],
    'multi key second numeric current equals next' => [['bucket' => 'yes', 'value' => '02'], ['bucket' => 'yes', 'value' => 2], ['bucket', 'value'], 'GC', ['BINARY', 'BINARY'], [], [], 0],
    'multi key second numeric current orders below next' => [['bucket' => 'yes', 'value' => '02'], ['bucket' => 'yes', 'value' => 3], ['bucket', 'value'], 'GC', ['BINARY', 'BINARY'], [], [], -1],
    'multi key first nocase equality reaches second key' => [['bucket' => 'Plugin', 'value' => 2], ['bucket' => 'plugin', 'value' => 3], ['bucket', 'value'], 'GC', ['NOCASE', 'BINARY'], [], [], -1],
    'multi key first binary stops before numeric key' => [['bucket' => 'Plugin', 'value' => 99], ['bucket' => 'plugin', 'value' => 1], ['bucket', 'value'], 'GC', ['BINARY', 'BINARY'], [], [], -1],
    'descending numeric reverses adjacent order' => [['value' => 2], ['value' => 3], ['value'], 'C', [], [true], [], 1],
    'descending text reverses adjacent order' => [['value' => 'a'], ['value' => 'b'], ['value'], 'G', [], [true], [], 1],
    'second key descending reverses after first equality' => [['bucket' => 'yes', 'value' => 2], ['bucket' => 'yes', 'value' => 3], ['bucket', 'value'], 'GC', [], [false, true], [], 1],
    'null current sorts before next by default' => [['value' => null], ['value' => 1], ['value'], 'C', [], [], [], -1],
    'null next sorts after current by default' => [['value' => 1], ['value' => null], ['value'], 'C', [], [], [], 1],
    'null current sorts last with explicit placement' => [['value' => null], ['value' => 1], ['value'], 'C', [], [], ['LAST'], 1],
    'null next sorts first with explicit placement' => [['value' => 1], ['value' => null], ['value'], 'C', [], [], ['FIRST'], 1],
    'both null compare equal' => [['value' => null], ['value' => null], ['value'], 'C', [], [], ['LAST'], 0],
    'blob bytes compare lexically for adjacent rows' => [['value' => new SQLiteBlobValue("a\x00")], ['value' => new SQLiteBlobValue("a\x01")], ['value'], 'B', [], [], [], -1],
    'text storage ranks below blob storage' => [['value' => 'z'], ['value' => new SQLiteBlobValue('a')], ['value'], 'B', [], [], [], -1],
    'integer storage ranks below blob storage' => [['value' => 1], ['value' => new SQLiteBlobValue('1')], ['value'], 'B', [], [], [], -1],
    'real and integer adjacent equality' => [['value' => 7.0], ['value' => 7], ['value'], 'C', [], [], [], 0],
    'real adjacent orders above integer' => [['value' => 7.5], ['value' => 7], ['value'], 'C', [], [], [], 1],
    'array affinity text then numeric equality' => [['bucket' => 2, 'value' => '02'], ['bucket' => '2', 'value' => 2], ['bucket', 'value'], ['TEXT', 'NUMERIC'], [], [], [], 0],
    'array affinity none preserves blob rank' => [['value' => new SQLiteBlobValue('2')], ['value' => '2'], ['value'], ['NONE'], [], [], [], 1],
    'array affinity text converts current integer' => [['value' => 2], ['value' => '10'], ['value'], ['TEXT'], [], [], [], 1],
    'rtrim applies only to first adjacent slot' => [['bucket' => 'a ', 'value' => 'b '], ['bucket' => 'a', 'value' => 'b'], ['bucket', 'value'], 'GG', ['RTRIM', 'BINARY'], [], [], 1],
    'nocase applies only to first adjacent slot' => [['bucket' => 'A', 'value' => 'B'], ['bucket' => 'a', 'value' => 'b'], ['bucket', 'value'], 'GG', ['NOCASE', 'BINARY'], [], [], -1],
];

foreach ($adjacentCases as $name => [$current, $next, $columns, $affinities, $collations, $descending, $nulls, $expected]) {
    $tests['vdbe affinity current next28 ' . $name] = static function (TestRunner $t) use ($current, $next, $columns, $affinities, $collations, $descending, $nulls, $expected): void {
        $cursor = new SQLiteVdbeSorterCursor([$current, $next]);
        $comparison = $cursor->compareCurrentToNext($columns, $affinities, $collations, $descending, $nulls);
        $t->same($expected, $comparison <=> 0);
        $t->same(0, $cursor->position());
        $t->same(array_values($current), $cursor->currentRecord(array_keys($current)));
        $t->same(array_values($next), $cursor->nextRecord(array_keys($next)));
    };
}

$tests['vdbe affinity current next28 detects copied option boundary groups'] = static function (TestRunner $t): void {
    $rows = [
        ['ordinal' => 1, 'autoload' => 'YES', 'priority' => '01', 'option_name' => 'siteurl'],
        ['ordinal' => 2, 'autoload' => 'yes', 'priority' => 1, 'option_name' => 'home'],
        ['ordinal' => 3, 'autoload' => 'yes', 'priority' => '2', 'option_name' => 'blogname'],
        ['ordinal' => 4, 'autoload' => 'no', 'priority' => null, 'option_name' => 'plugin_a'],
    ];

    $cursor = SQLiteVdbeSortCompare::cursor($rows, ['ordinal'], 'D');
    $t->same(0, $cursor->compareCurrentToNext(['autoload', 'priority'], 'GC', ['NOCASE', 'BINARY']));
    $cursor->next();
    $t->same(-1, $cursor->compareCurrentToNext(['autoload', 'priority'], 'GC', ['NOCASE', 'BINARY']) <=> 0);
    $cursor->next();
    $t->same(1, $cursor->compareCurrentToNext(['autoload', 'priority'], 'GC', ['NOCASE', 'BINARY'], [], ['LAST', 'FIRST']) <=> 0);
};

$tests['vdbe affinity current next28 returns null without next row'] = static function (TestRunner $t): void {
    $cursor = SQLiteVdbeSortCompare::cursor([['ordinal' => 1, 'value' => '10']], ['ordinal'], 'D');
    $t->same(null, $cursor->compareCurrentToNext(['value'], 'C'));
    $cursor->next();
    $t->same(null, $cursor->compareCurrentToNext(['value'], 'C'));
    $t->same(null, $cursor->currentRecord(['value']));
    $t->same(null, $cursor->nextRecord(['value']));
};

$tests['vdbe affinity current next28 rejects missing next record column'] = static function (TestRunner $t): void {
    $cursor = SQLiteVdbeSortCompare::cursor([
        ['ordinal' => 1, 'value' => 1],
        ['ordinal' => 2, 'other' => 1],
    ], ['ordinal'], 'D');

    $t->throws(InvalidArgumentException::class, static fn () => $cursor->compareCurrentToNext(['value'], 'C'));
};

$tests['vdbe affinity current next28 rejects missing current record column'] = static function (TestRunner $t): void {
    $cursor = SQLiteVdbeSortCompare::cursor([
        ['ordinal' => 1, 'other' => 1],
        ['ordinal' => 2, 'value' => 1],
    ], ['ordinal'], 'D');

    $t->throws(InvalidArgumentException::class, static fn () => $cursor->compareCurrentToNext(['value'], 'C'));
};

$tests['vdbe affinity current next28 rejects empty comparison columns'] = static function (TestRunner $t): void {
    $cursor = SQLiteVdbeSortCompare::cursor([
        ['ordinal' => 1, 'value' => 1],
        ['ordinal' => 2, 'value' => 2],
    ], ['ordinal'], 'D');

    $t->throws(InvalidArgumentException::class, static fn () => $cursor->compareCurrentToNext([], 'C'));
};

$tests['vdbe affinity current next28 rejects unsupported collation during boundary check'] = static function (TestRunner $t): void {
    $cursor = SQLiteVdbeSortCompare::cursor([
        ['ordinal' => 1, 'value' => 'a'],
        ['ordinal' => 2, 'value' => 'A'],
    ], ['ordinal'], 'D');

    $t->throws(InvalidArgumentException::class, static fn () => $cursor->compareCurrentToNext(['value'], 'G', ['WPCASE']));
};

return $tests;
