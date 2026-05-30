<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUtf16GlobRangeCurrentSourceNextPlan;
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

$currentRows = [
    $row(1, 'Plugin_Alpha', 'UTF-16LE', 'no'),
    $row(2, 'plugin_alpha', 'UTF-16LE'),
    $row(3, 'plugin_beta', 'UTF-16LE'),
    $row(4, 'plugin_cache', 'UTF-16LE'),
    $row(5, 'plugin_cache_old', 'UTF-16LE'),
    $row(6, 'plugin_delta', 'UTF-16LE'),
    $row(7, 'plugin_éclair', 'UTF-16LE'),
    $row(8, 'plugin_😀_cache', 'UTF-16LE'),
    $row(9, 'theme_alpha', 'UTF-16LE'),
    $row(10, 'plugin_cache ', 'UTF-16LE'),
];

$nextRows = [
    $row(1, 'Plugin_Alpha', 'UTF-16LE', 'no'),
    $row(2, 'plugin_alpha', 'UTF-16LE'),
    $row(3, 'plugin_beta', 'UTF-16LE'),
    $row(4, 'plugin_cache', 'UTF-16LE'),
    $row(5, 'plugin_cache_new', 'UTF-16LE'),
    $row(6, 'plugin_delta', 'UTF-16LE'),
    $row(7, 'plugin_éclair', 'UTF-16LE'),
    $row(8, 'plugin_😀_cache_v2', 'UTF-16LE'),
    $row(9, 'theme_alpha', 'UTF-16LE'),
    $row(11, 'plugin_enabled', 'UTF-16LE'),
    $row(12, 'plugin_Éclair', 'UTF-16LE', 'no'),
];

$plan = static fn (
    string $pattern = 'plugin_*',
    string $collation = 'BINARY',
    string $currentEncoding = 'UTF-16LE',
    string $nextEncoding = 'UTF-16LE',
    string $currentSource = 'main.wp_options@cookie99',
    string $nextSource = 'main.wp_options@cookie100',
    int $currentSchemaCookie = 99,
    int $nextSchemaCookie = 100,
    ?array $current = null,
    ?array $next = null,
): array => SQLiteUtf16GlobRangeCurrentSourceNextPlan::optionRowNameGlobRange(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $collation,
    $currentEncoding,
    $nextEncoding,
    $currentSource,
    $nextSource,
    $currentSchemaCookie,
    $nextSchemaCookie,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'records pattern' => ['plugin_*', 'BINARY', 'currentSource', 'main.wp_options@cookie99'],
    'records next source' => ['plugin_*', 'BINARY', 'nextSource', 'main.wp_options@cookie100'],
    'records schema cookie' => ['plugin_*', 'BINARY', 'currentSchemaCookie', 99],
    'records next schema cookie' => ['plugin_*', 'BINARY', 'nextSchemaCookie', 100],
    'source changed on source or cookie' => ['plugin_*', 'BINARY', 'sourceChanged', true],
    'cursor is not reusable after source switch' => ['plugin_*', 'BINARY', 'cursorReusable', false],
    'source reason first' => ['plugin_*', 'BINARY', 'reprepareReasons.0', 'source-name'],
    'schema cookie reason second' => ['plugin_*', 'BINARY', 'reprepareReasons.1', 'schema-cookie'],
    'matched rowset reason third' => ['plugin_*', 'BINARY', 'reprepareReasons.2', 'matched-rowset'],
    'key bytes reason fourth' => ['plugin_*', 'BINARY', 'reprepareReasons.3', 'key-bytes'],
    'current binary rowids skip uppercase plugin' => ['plugin_*', 'BINARY', 'current.rowids', [2, 3, 4, 10, 5, 6, 7, 8]],
    'next binary rowids include new enabled row' => ['plugin_*', 'BINARY', 'next.rowids', [2, 3, 4, 5, 6, 11, 12, 7, 8]],
    'retained rowids preserve current cursor order' => ['plugin_*', 'BINARY', 'retainedRowids', [2, 3, 4, 5, 6, 7, 8]],
    'exited rowids expose stale current range row' => ['plugin_*', 'BINARY', 'exitedRowids', [10]],
    'entered rowids expose next source rows' => ['plugin_*', 'BINARY', 'enteredRowids', [11, 12]],
    'changed bytes rowids expose renamed rows' => ['plugin_*', 'BINARY', 'changedBytesRowids', [5, 8]],
    'current first row is alpha under binary order' => ['plugin_*', 'BINARY', 'current.firstRowid', 2],
    'current last row is emoji row' => ['plugin_*', 'BINARY', 'current.lastRowid', 8],
    'next first row is alpha row' => ['plugin_*', 'BINARY', 'next.firstRowid', 2],
    'next last row is renamed emoji row' => ['plugin_*', 'BINARY', 'next.lastRowid', 8],
    'range lower is fixed glob prefix' => ['plugin_*', 'BINARY', 'current.range.lowerInclusive', 'plugin_'],
    'range upper is next byte prefix' => ['plugin_*', 'BINARY', 'current.range.upperBound', 'plugin`'],
    'current lower bound bytes are utf16le' => ['plugin_*', 'BINARY', 'current.rangeBytesHex.lowerInclusive', '70006c007500670069006e005f00'],
    'current upper bound bytes are utf16le' => ['plugin_*', 'BINARY', 'current.rangeBytesHex.upperBound', '70006c007500670069006e006000'],
    'next lower bound bytes are utf16le' => ['plugin_*', 'BINARY', 'next.rangeBytesHex.lowerInclusive', '70006c007500670069006e005f00'],
    'current cursor residual sees padded row' => ['plugin_*', 'BINARY', 'current.cursor.residualMatch', true],
    'current cursor next rowid is beta' => ['plugin_*', 'BINARY', 'current.cursor.nextRowid', 3],
    'current bytes map preserves padded cache bytes' => ['plugin_*', 'BINARY', 'current.bytesHexByRowid.10', '70006c007500670069006e005f00630061006300680065002000'],
    'next bytes map preserves renamed cache bytes' => ['plugin_*', 'BINARY', 'next.bytesHexByRowid.5', '70006c007500670069006e005f00630061006300680065005f006e0065007700'],
    'dependency records current source next range' => ['plugin_*', 'BINARY', 'dependencies.2', 'sqlite-current-source-next-range-reprepare'],
    'nocase range still residual-filters uppercase plugin' => ['plugin_*', 'NOCASE', 'current.rowids', [2, 3, 4, 10, 5, 6, 7, 8]],
    'nocase residual still keeps glob case sensitive for uppercase' => ['plugin_*', 'NOCASE', 'current.cursor.residualMatch', false],
    'nocase next includes lowercase e acute after new row' => ['plugin_*', 'NOCASE', 'next.rowids', [2, 3, 4, 5, 6, 11, 12, 7, 8]],
    'rtrim range includes alpha row first' => ['plugin_*', 'RTRIM', 'current.firstRowid', 2],
    'rtrim comparison key follows beta as next' => ['plugin_*', 'RTRIM', 'current.cursor.nextComparisonKey', 'plugin_beta'],
    'rtrim matched rows include padded current row' => ['plugin_*', 'RTRIM', 'current.rowids', [2, 3, 4, 10, 5, 6, 7, 8]],
    'latin class current rowids are lowercase e acute' => ['plugin_[À-ÿ]*', 'BINARY', 'current.rowids', [7]],
    'latin class next rowids include uppercase and lowercase e acute' => ['plugin_[À-ÿ]*', 'BINARY', 'next.rowids', [12, 7]],
    'latin class entered uppercase e acute row' => ['plugin_[À-ÿ]*', 'BINARY', 'enteredRowids', [12]],
    'emoji literal current row remains retained' => ['plugin_😀*', 'BINARY', 'retainedRowids', [8]],
    'emoji literal changed bytes detects suffix rename' => ['plugin_😀*', 'BINARY', 'changedBytesRowids', [8]],
    'emoji literal current range lower is emoji prefix' => ['plugin_😀*', 'BINARY', 'current.range.lowerInclusive', 'plugin_😀'],
    'emoji literal lower bytes include surrogate pair' => ['plugin_😀*', 'BINARY', 'current.rangeBytesHex.lowerInclusive', '70006c007500670069006e005f003dd800de'],
    'leading class has no current range' => ['[Pp]lugin_*', 'BINARY', 'current.range', null],
    'leading class has no current rowids' => ['[Pp]lugin_*', 'BINARY', 'current.rowids', []],
    'leading class has no next rowids' => ['[Pp]lugin_*', 'BINARY', 'next.rowids', []],
    'leading class source change still invalidates' => ['[Pp]lugin_*', 'BINARY', 'reprepareReasons', ['source-name', 'schema-cookie']],
    'theme stable row is retained' => ['theme_*', 'BINARY', 'retainedRowids', [9]],
    'theme current first text' => ['theme_*', 'BINARY', 'current.firstText', 'theme_alpha'],
    'theme next last text' => ['theme_*', 'BINARY', 'next.lastText', 'theme_alpha'],
];

foreach ($cases as $name => [$pattern, $collation, $path, $expected]) {
    $tests['utf16 glob range current source nextOneZeroTwo ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $pattern, $collation, $path, $expected): void {
        $t->same($expected, $valueAt($plan($pattern, $collation), $path));
    };
}

$stableRows = [
    $row(1, 'plugin_alpha', 'UTF-16LE'),
    $row(2, 'plugin_beta', 'UTF-16LE'),
    $row(3, 'theme_alpha', 'UTF-16LE'),
];

$stableCases = [
    'stable source reuses cursor' => ['cursorReusable', true],
    'stable source has no reasons' => ['reprepareReasons', []],
    'stable source rowids retained' => ['retainedRowids', [1, 2]],
    'stable source has no changed bytes' => ['changedBytesRowids', []],
    'stable source is not source changed' => ['sourceChanged', false],
];

foreach ($stableCases as $name => [$path, $expected]) {
    $tests['utf16 glob range current source nextOneZeroTwo ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $stableRows, $path, $expected): void {
        $result = $plan('plugin_*', 'BINARY', 'UTF-16LE', 'UTF-16LE', 'stable', 'stable', 7, 7, $stableRows, $stableRows);
        $t->same($expected, $valueAt($result, $path));
    };
}

$beRows = [
    $row(1, 'plugin_alpha', 'UTF-16BE'),
    $row(2, 'plugin_beta', 'UTF-16BE'),
];

$tests['utf16 glob range current source nextOneZeroTwo utf16be range bytes force reprepare'] = static function (TestRunner $t) use ($plan, $valueAt, $stableRows, $beRows): void {
    $result = $plan('plugin_*', 'BINARY', 'UTF-16LE', 'UTF-16BE', 'stable', 'stable', 7, 7, $stableRows, $beRows);
    $t->same(['text-encoding', 'range-bytes', 'key-bytes'], $result['reprepareReasons']);
    $t->same('0070006c007500670069006e005f', $valueAt($result, 'next.rangeBytesHex.lowerInclusive'));
};

$tests['utf16 glob range current source nextOneZeroTwo rejects unsupported collation'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin_*', 'UNICODE'));
};

$tests['utf16 glob range current source nextOneZeroTwo rejects unsupported next encoding'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin_*', 'BINARY', 'UTF-16LE', 'UTF-32LE'));
};

$tests['utf16 glob range current source nextOneZeroTwo rejects missing current rowid'] = static function (TestRunner $t) use ($plan, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin_*', 'BINARY', 'UTF-16LE', 'UTF-16LE', 'stable', 'stable', 1, 1, [['option_name_utf16' => 'p']], $nextRows));
};

$tests['utf16 glob range current source nextOneZeroTwo rejects missing next utf16 bytes'] = static function (TestRunner $t) use ($plan, $currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin_*', 'BINARY', 'UTF-16LE', 'UTF-16LE', 'stable', 'stable', 1, 1, $currentRows, [['option_id' => 1]]));
};

$tests['utf16 glob range current source nextOneZeroTwo rejects malformed next utf16 bytes'] = static function (TestRunner $t) use ($plan, $currentRows): void {
    $bad = [['option_id' => 1, 'option_name_utf16' => "\x3d\xd8"]];
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin_*', 'BINARY', 'UTF-16LE', 'UTF-16LE', 'stable', 'stable', 1, 1, $currentRows, $bad));
};

return $tests;
