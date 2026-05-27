<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeIndexCursor;

$tests = [];

$baseEntries = [
    ['key' => ['autoload', 'Plugin_A', '02'], 'rowid' => 20, 'payload' => ['option_name' => 'Plugin_A']],
    ['key' => ['autoload', 'plugin_a', 2], 'rowid' => 10, 'payload' => ['option_name' => 'plugin_a']],
    ['key' => ['autoload', 'plugin_b', '10'], 'rowid' => 30, 'payload' => ['option_name' => 'plugin_b']],
    ['key' => ['cache', 'Cache ', null], 'rowid' => 40, 'payload' => ['option_name' => 'Cache ']],
    ['key' => ['cache', 'cache', '1'], 'rowid' => 50, 'payload' => ['option_name' => 'cache']],
    ['key' => ['network', 'SiteURL', new SQLiteBlobValue('4')], 'rowid' => 60, 'payload' => ['option_name' => 'SiteURL']],
];

$boundaryCases = [
    'nocase duplicate plugin key compares equal before rowid tie' => [0, null, null, null, null, 0],
    'plugin duplicate priority column compares equal under numeric affinity' => [0, [2], 'C', ['BINARY'], [false], 0],
    'plugin duplicate name column compares equal under nocase' => [0, [1], 'G', ['NOCASE'], [false], 0],
    'plugin duplicate name binary sees lowercase current after uppercase next' => [0, [1], 'G', ['BINARY'], [false], 1],
    'plugin duplicate full key binary decides on name slot' => [0, null, 'GGC', ['BINARY', 'BINARY', 'BINARY'], [false, false, false], 1],
    'plugin current priority less than next priority' => [1, [2], 'C', ['BINARY'], [false], -1],
    'plugin current name less than next name nocase' => [1, [1], 'G', ['NOCASE'], [false], -1],
    'plugin current bucket equals next bucket' => [1, [0], 'G', ['BINARY'], [false], 0],
    'plugin current full key less than next full key' => [1, null, null, null, null, -1],
    'plugin current priority descending reverses next boundary' => [1, [2], 'C', ['BINARY'], [true], 1],
    'plugin to cache bucket boundary ascends' => [2, [0], 'G', ['BINARY'], [false], -1],
    'plugin to cache full key ascends' => [2, null, null, null, null, -1],
    'cache name rtrim keeps lowercase current after uppercase next' => [3, [1], 'G', ['RTRIM'], [false], 1],
    'cache name binary keeps lowercase current after uppercase next' => [3, [1], 'G', ['BINARY'], [false], 1],
    'cache numeric priority sorts after next null priority' => [3, [2], 'C', ['BINARY'], [false], 1],
    'cache numeric priority descending reverses boundary' => [3, [2], 'C', ['BINARY'], [true], -1],
    'cache full key uses name slot before null priority' => [3, null, 'GGC', ['BINARY', 'RTRIM', 'BINARY'], [false, false, false], 1],
    'cache to network bucket boundary ascends' => [4, [0], 'G', ['BINARY'], [false], -1],
    'cache to network full key ascends' => [4, null, null, null, null, -1],
    'network final boundary has no next row' => [5, null, null, null, null, null],
];

foreach ($boundaryCases as $name => [$advance, $columns, $affinities, $collations, $descending, $expected]) {
    $tests['vdbe index affinity collation current next35 ' . $name] = static function (TestRunner $t) use ($baseEntries, $advance, $columns, $affinities, $collations, $descending, $expected): void {
        $cursor = new SQLiteVdbeIndexCursor($baseEntries, 'GGC', ['BINARY', 'NOCASE', 'BINARY']);
        for ($i = 0; $i < $advance; $i++) {
            $cursor->next();
        }

        $comparison = $cursor->compareCurrentToNext($columns, $affinities, $collations, $descending);
        $t->same($expected, $comparison === null ? null : $comparison <=> 0);
    };
}

$recordCases = [
    'current full record exposes lower rowid peer first' => [0, 'currentRecord', null, ['autoload', 'plugin_a', 2]],
    'next full record exposes rowid tie peer' => [0, 'nextRecord', null, ['autoload', 'Plugin_A', '02']],
    'current selected name and priority columns' => [0, 'currentRecord', [1, 2], ['plugin_a', 2]],
    'next selected name and priority columns' => [0, 'nextRecord', [1, 2], ['Plugin_A', '02']],
    'current record after next advances to second peer' => [1, 'currentRecord', null, ['autoload', 'Plugin_A', '02']],
    'next record after next advances to plugin b' => [1, 'nextRecord', null, ['autoload', 'plugin_b', '10']],
    'current cache record preserves numeric text slot' => [3, 'currentRecord', null, ['cache', 'cache', '1']],
    'next cache record preserves null key slot' => [3, 'nextRecord', null, ['cache', 'Cache ', null]],
    'selected bucket only record' => [3, 'currentRecord', [0], ['cache']],
    'selected priority only next record' => [3, 'nextRecord', [2], [null]],
    'final current record exposes network blob key' => [5, 'currentRecord', [0, 1], ['network', 'SiteURL']],
    'final next record is null' => [5, 'nextRecord', null, null],
];

foreach ($recordCases as $name => [$advance, $method, $columns, $expected]) {
    $tests['vdbe index affinity collation current next35 ' . $name] = static function (TestRunner $t) use ($baseEntries, $advance, $method, $columns, $expected): void {
        $cursor = new SQLiteVdbeIndexCursor($baseEntries, 'GGC', ['BINARY', 'NOCASE', 'BINARY']);
        for ($i = 0; $i < $advance; $i++) {
            $cursor->next();
        }

        $t->same($expected, $cursor->{$method}($columns));
    };
}

$scanCases = [
    'yield autoload prefix keeps rowid tie order after nocase equality' => [['autoload'], [10, 20, 30]],
    'yield autoload plugin a prefix matches both case forms' => [['autoload', 'plugin_a'], [10, 20]],
    'yield autoload plugin a numeric priority matches both storage classes' => [['autoload', 'plugin_a', 2], [10, 20]],
    'yield cache prefix includes lexical name before padded key' => [['cache'], [50, 40]],
    'yield cache exact nocase probe reaches unpadded key only' => [['cache', 'cache'], [50]],
    'yield absent plugin priority returns empty list' => [['autoload', 'plugin_a', 3], []],
    'yield network blob probe matches copied SiteURL row' => [['network', 'siteurl', new SQLiteBlobValue('4')], [60]],
    'yield beyond final prefix returns empty list' => [['zz'], []],
];

foreach ($scanCases as $name => [$probe, $expected]) {
    $tests['vdbe index affinity collation current next35 ' . $name] = static function (TestRunner $t) use ($baseEntries, $probe, $expected): void {
        $cursor = new SQLiteVdbeIndexCursor($baseEntries, 'GGC', ['BINARY', 'NOCASE', 'RTRIM']);
        $t->same($expected, array_column($cursor->yieldEqual($probe), 'rowid'));
    };
}

$seekCases = [
    'seek plugin a lands on lower rowid peer' => [['autoload', 'plugin_a'], 10],
    'seek plugin a numeric lands on lower rowid peer' => [['autoload', 'plugin_a', 2], 10],
    'seek plugin b lands on plugin b' => [['autoload', 'plugin_b'], 30],
    'seek cache null lands on first cache row before null peer' => [['cache', 'cache', null], 50],
    'seek cache numeric skips null priority row' => [['cache', 'cache', 1], 50],
    'seek network lower-case siteurl lands through nocase collation' => [['network', 'siteurl'], 60],
    'seek after network moves to eof' => [['network', 'zzz'], null],
    'seek past all rows moves to eof' => [['zz'], null],
];

foreach ($seekCases as $name => [$probe, $expected]) {
    $tests['vdbe index affinity collation current next35 ' . $name] = static function (TestRunner $t) use ($baseEntries, $probe, $expected): void {
        $cursor = new SQLiteVdbeIndexCursor($baseEntries, 'GGC', ['BINARY', 'NOCASE', 'RTRIM']);
        $found = $cursor->seekGreaterOrEqual($probe);
        $t->same($expected, $found ? $cursor->currentRowid() : null);
    };
}

$tests['vdbe index affinity collation current next35 compare rejects empty selected columns'] = static function (TestRunner $t) use ($baseEntries): void {
    $cursor = new SQLiteVdbeIndexCursor($baseEntries, 'GGC', ['BINARY', 'NOCASE', 'BINARY']);
    $t->throws(InvalidArgumentException::class, static fn () => $cursor->compareCurrentToNext([]));
};

$tests['vdbe index affinity collation current next35 current record rejects associative columns'] = static function (TestRunner $t) use ($baseEntries): void {
    $cursor = new SQLiteVdbeIndexCursor($baseEntries, 'GGC', ['BINARY', 'NOCASE', 'BINARY']);
    $t->throws(InvalidArgumentException::class, static fn () => $cursor->currentRecord(['column' => 1]));
};

$tests['vdbe index affinity collation current next35 next record rejects negative column'] = static function (TestRunner $t) use ($baseEntries): void {
    $cursor = new SQLiteVdbeIndexCursor($baseEntries, 'GGC', ['BINARY', 'NOCASE', 'BINARY']);
    $t->throws(InvalidArgumentException::class, static fn () => $cursor->nextRecord([-1]));
};

$tests['vdbe index affinity collation current next35 current record rejects missing key slot'] = static function (TestRunner $t) use ($baseEntries): void {
    $cursor = new SQLiteVdbeIndexCursor($baseEntries, 'GGC', ['BINARY', 'NOCASE', 'BINARY']);
    $t->throws(OutOfBoundsException::class, static fn () => $cursor->currentRecord([3]));
};

$tests['vdbe index affinity collation current next35 compare rejects unsupported affinity override'] = static function (TestRunner $t) use ($baseEntries): void {
    $cursor = new SQLiteVdbeIndexCursor($baseEntries, 'GGC', ['BINARY', 'NOCASE', 'BINARY']);
    $t->throws(InvalidArgumentException::class, static fn () => $cursor->compareCurrentToNext(null, 'Z'));
};

$tests['vdbe index affinity collation current next35 compare rejects unsupported collation override'] = static function (TestRunner $t) use ($baseEntries): void {
    $cursor = new SQLiteVdbeIndexCursor($baseEntries, 'GGC', ['BINARY', 'NOCASE', 'BINARY']);
    $t->throws(InvalidArgumentException::class, static fn () => $cursor->compareCurrentToNext([1], 'G', ['LOCALIZED']));
};

$tests['vdbe index affinity collation current next35 eof current and next records are null'] = static function (TestRunner $t) use ($baseEntries): void {
    $cursor = new SQLiteVdbeIndexCursor($baseEntries, 'GGC', ['BINARY', 'NOCASE', 'BINARY']);
    while (!$cursor->eof()) {
        $cursor->next();
    }
    $t->same([null, null, null], [$cursor->currentRecord(), $cursor->nextRecord(), $cursor->compareCurrentToNext()]);
};

$tests['vdbe index affinity collation current next35 compare after seek preserves cursor position'] = static function (TestRunner $t) use ($baseEntries): void {
    $cursor = new SQLiteVdbeIndexCursor($baseEntries, 'GGC', ['BINARY', 'NOCASE', 'BINARY']);
    $cursor->seekGreaterOrEqual(['cache']);
    $cursor->compareCurrentToNext([1], 'G', ['RTRIM']);
    $t->same(50, $cursor->currentRowid());
};

return $tests;
