<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUtf16LikeGlobCurrentNextCursor;

$tests = [];

$enc = static fn (string $text, string $encoding = 'UTF-16LE'): string => SQLiteUtf16LikeGlobCurrentNextCursor::encodeUtf16($text, $encoding);
$row = static fn (int $id, string $name, string $encoding = 'UTF-16LE', string $autoload = 'yes'): array => [
    'option_id' => $id,
    'option_name' => $name,
    'option_name_utf16' => $enc($name, $encoding),
    'encoding' => $encoding,
    'autoload' => $autoload,
];

$rows = [
    $row(1, 'plugin-cache', 'UTF-16LE'),
    $row(2, 'plugin-cache ', 'UTF-16LE'),
    $row(3, 'plugin-cache  ', 'UTF-16LE'),
    $row(4, 'plugin-cache-hard', 'UTF-16LE'),
    $row(5, 'plugin-cache-hard ', 'UTF-16LE'),
    $row(6, 'plugin-cache.' , 'UTF-16LE'),
    $row(7, 'plugin-cache-nbsp' . "\u{00a0}", 'UTF-16LE'),
    $row(8, 'plugin-cache-tab' . "\t", 'UTF-16LE'),
    $row(9, 'Plugin-cache', 'UTF-16LE', 'no'),
    $row(10, 'plugin-cachf', 'UTF-16LE'),
    $row(11, 'plugin-cachd', 'UTF-16LE'),
    $row(12, 'plugin-cach', 'UTF-16LE'),
    $row(13, 'theme-cache', 'UTF-16LE'),
];

$cursor = static fn (string $pattern, string $operator = 'GLOB'): SQLiteUtf16LikeGlobCurrentNextCursor => new SQLiteUtf16LikeGlobCurrentNextCursor(
    array_map(static fn (array $row): array => [
        'keyBytes' => $row['option_name_utf16'],
        'rowid' => $row['option_id'],
        'payload' => $row,
    ], $rows),
    $pattern,
    $operator,
    'UTF-16LE',
    'RTRIM',
    null,
    true,
);

$valueAt = static function (array $plan, string $path): mixed {
    $value = $plan;
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$planCases = [
    'exact glob starts on unpadded key' => ['plugin-cache', 0, 'currentRowid', 1],
    'exact glob current comparison key is trimmed' => ['plugin-cache', 0, 'currentComparisonKey', 'plugin-cache'],
    'exact glob next padded peer shares comparison key' => ['plugin-cache', 0, 'nextComparisonKey', 'plugin-cache'],
    'exact glob current residual matches' => ['plugin-cache', 0, 'residualMatch', true],
    'exact glob padded peer remains in range' => ['plugin-cache', 0, 'nextInRange', true],
    'exact glob padded peer residual is false' => ['plugin-cache', 1, 'residualMatch', false],
    'exact glob double padded peer residual is false' => ['plugin-cache', 2, 'residualMatch', false],
    'exact glob padded peer lower comparison ties' => ['plugin-cache', 1, 'comparisonToLower', 0],
    'exact glob padded peer upper comparison stays below' => ['plugin-cache', 1, 'comparisonToUpper', -1],
    'exact glob upper bound is next prefix byte' => ['plugin-cache', 0, 'range.upperBound', 'plugin-cachf'],
    'wildcard glob starts on unpadded key' => ['plugin-cache*', 0, 'currentRowid', 1],
    'wildcard glob padded peer residual is true' => ['plugin-cache*', 1, 'residualMatch', true],
    'wildcard glob double padded peer residual is true' => ['plugin-cache*', 2, 'residualMatch', true],
    'wildcard glob hyphen suffix is after padded peers' => ['plugin-cache*', 3, 'currentRowid', 4],
    'wildcard glob hard padded suffix keeps trimmed comparison key' => ['plugin-cache*', 4, 'currentComparisonKey', 'plugin-cache-hard'],
    'wildcard glob non breaking space is not trimmed' => ['plugin-cache*', 5, 'currentComparisonKey', "plugin-cache-nbsp\u{00a0}"],
    'wildcard glob tab is not trimmed' => ['plugin-cache*', 6, 'currentComparisonKey', "plugin-cache-tab\t"],
    'wildcard glob punctuation suffix follows hyphen suffixes' => ['plugin-cache*', 7, 'currentRowid', 6],
    'wildcard glob next after punctuation exits range' => ['plugin-cache*', 7, 'nextInRange', false],
    'hard exact glob starts on hard key' => ['plugin-cache-hard', 0, 'currentRowid', 4],
    'hard exact glob padded hard peer residual false' => ['plugin-cache-hard', 1, 'residualMatch', false],
    'hard wildcard glob padded hard peer residual true' => ['plugin-cache-hard*', 1, 'residualMatch', true],
    'case sensitive rtrim glob skips uppercase source' => ['plugin-cache*', 0, 'currentText', 'plugin-cache'],
    'range lower is literal prefix' => ['plugin-cache*', 0, 'range.lowerInclusive', 'plugin-cache'],
    'range collation reports rtrim' => ['plugin-cache*', 0, 'collation', 'RTRIM'],
    'range operator reports glob' => ['plugin-cache*', 0, 'operator', 'GLOB'],
    'range encoding reports utf16le scan decode' => ['plugin-cache*', 0, 'encoding', 'UTF-16LE'],
    'no fixed prefix keeps null range' => ['*cache', 0, 'range', null],
    'prefix before cache starts on shorter cach row' => ['plugin-cach*', 0, 'currentRowid', 12],
    'prefix before cache next is cachd row' => ['plugin-cach*', 0, 'nextRowid', 11],
    'prefix after cache starts on upper-bound peer' => ['plugin-cachf*', 0, 'currentRowid', 10],
    'prefix after cache current comparison key is cachf' => ['plugin-cachf*', 0, 'currentComparisonKey', 'plugin-cachf'],
];

foreach ($planCases as $name => [$pattern, $advance, $path, $expected]) {
    $tests['utf16 rtrim like glob current source nextNineZero plan ' . $name] = static function (TestRunner $t) use ($cursor, $valueAt, $pattern, $advance, $path, $expected): void {
        $scan = $cursor($pattern);
        for ($i = 0; $i < $advance; $i++) {
            $scan->next();
        }
        $t->same($expected, $valueAt($scan->currentNextPlan(), $path));
    };
}

$matchCases = [
    'exact glob matches only unpadded text after rtrim seek' => ['plugin-cache', [1]],
    'wildcard glob includes space padded peers' => ['plugin-cache*', [1, 2, 3, 4, 5, 7, 8, 6]],
    'hard exact glob excludes hard padded peer' => ['plugin-cache-hard', [4]],
    'hard wildcard glob includes hard padded peer' => ['plugin-cache-hard*', [4, 5]],
    'prefix before cache includes all cache and cachf rows' => ['plugin-cach*', [12, 11, 1, 2, 3, 4, 5, 7, 8, 6, 10]],
    'prefix upper cache excludes cachf row' => ['plugin-cache*', [1, 2, 3, 4, 5, 7, 8, 6]],
    'tab suffix exact matches tab row only' => ["plugin-cache-tab\t", [8]],
    'nbsp suffix exact matches nbsp row only' => ["plugin-cache-nbsp\u{00a0}", [7]],
    'uppercase literal glob matches uppercase row only' => ['Plugin-cache*', [9]],
    'leading wildcard remains cursor-unusable' => ['*cache', []],
];

foreach ($matchCases as $name => [$pattern, $expectedRowids]) {
    $tests['utf16 rtrim like glob current source nextNineZero matched rows ' . $name] = static function (TestRunner $t) use ($cursor, $pattern, $expectedRowids): void {
        $t->same($expectedRowids, array_column($cursor($pattern)->matchedRows(), 'rowid'));
    };
}

$tests['utf16 rtrim like glob current source nextNineZero matched rows preserve source bytes'] = static function (TestRunner $t) use ($cursor): void {
    $rows = $cursor('plugin-cache')->matchedRows();
    $t->same('70006c007500670069006e002d0063006100630068006500', $rows[0]['keyBytesHex']);
};

$tests['utf16 rtrim like glob current source nextNineZero matched rows preserve padded payload'] = static function (TestRunner $t) use ($cursor): void {
    $rows = $cursor('plugin-cache*')->matchedRows();
    $t->same('UTF-16LE', $rows[1]['payload']['encoding']);
    $t->same('plugin-cache ', $rows[1]['payload']['option_name']);
};

$tests['utf16 rtrim like glob current source nextNineZero matched rows keep autoload payload'] = static function (TestRunner $t) use ($cursor): void {
    $rows = $cursor('Plugin-cache*')->matchedRows();
    $t->same('no', $rows[0]['payload']['autoload']);
};

$tests['utf16 rtrim like glob current source nextNineZero application scan exact padded option returns only unpadded'] = static function (TestRunner $t) use ($rows): void {
    $matched = SQLiteUtf16LikeGlobCurrentNextCursor::optionRowNameScan($rows, 'plugin-cache', 'GLOB', 'UTF-16LE', 'RTRIM');
    $t->same([1], array_column($matched, 'rowid'));
};

$tests['utf16 rtrim like glob current source nextNineZero application scan wildcard returns padded peers'] = static function (TestRunner $t) use ($rows): void {
    $matched = SQLiteUtf16LikeGlobCurrentNextCursor::optionRowNameScan($rows, 'plugin-cache*', 'GLOB', 'UTF-16LE', 'RTRIM');
    $t->same([1, 2, 3, 4, 5, 7, 8, 6], array_column($matched, 'rowid'));
};

$tests['utf16 rtrim like glob current source nextNineZero application scan rejects missing utf16 bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeGlobCurrentNextCursor::optionRowNameScan([['option_id' => 1, 'option_name' => 'plugin-cache']], 'plugin-cache*', 'GLOB', 'UTF-16LE', 'RTRIM'));
};

$tests['utf16 rtrim like glob current source nextNineZero rejects odd utf16 bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobCurrentNextCursor([['keyBytes' => "\x70", 'rowid' => 1, 'payload' => []]], 'p*', 'GLOB', 'UTF-16LE', 'RTRIM'));
};

$tests['utf16 rtrim like glob current source nextNineZero rejects high surrogate bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobCurrentNextCursor([['keyBytes' => "\x3d\xd8", 'rowid' => 1, 'payload' => []]], 'p*', 'GLOB', 'UTF-16LE', 'RTRIM'));
};

$tests['utf16 rtrim like glob current source nextNineZero rejects unsupported collation'] = static function (TestRunner $t) use ($rows): void {
    $entries = [['keyBytes' => $rows[0]['option_name_utf16'], 'rowid' => 1, 'payload' => []]];
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobCurrentNextCursor($entries, 'p*', 'GLOB', 'UTF-16LE', 'UNICODE'));
};

$tests['utf16 rtrim like glob current source nextNineZero rejects unsupported operator'] = static function (TestRunner $t) use ($rows): void {
    $entries = [['keyBytes' => $rows[0]['option_name_utf16'], 'rowid' => 1, 'payload' => []]];
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteUtf16LikeGlobCurrentNextCursor($entries, 'p*', 'REGEXP', 'UTF-16LE', 'RTRIM'));
};

return $tests;
