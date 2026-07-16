<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan;

$tests = [];

$bytes = static fn (string $value, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($value, $encoding);
$row = static fn (int $id, string $value, string $encoding = 'UTF-16LE'): array => [
    'option_id' => $id,
    'option_value_bytes' => $bytes($value, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8' => 1,
        'UTF-16LE' => 2,
        'UTF-16BE' => 3,
    },
];

$currentRows = [
    $row(1, 'plugin_[draft]'),
    $row(2, 'plugin_[draft]_cache'),
    $row(3, 'plugin_[draft_extra'),
    $row(4, 'plugin_[drafted'),
    $row(5, 'plugin_a_cache'),
    $row(6, 'plugin_b_cache'),
    $row(7, 'plugin_[stable'),
    $row(8, 'plugin_α_cache', 'UTF-16BE'),
    $row(9, 'plugin_[draft😀', 'UTF-16LE'),
];

$nextRows = [
    $row(1, 'plugin_[draft]'),
    $row(2, 'plugin_[draft]_cache', 'UTF-16BE'),
    $row(3, 'plugin_[draft_extra'),
    $row(4, 'plugin_[drafted_v2'),
    $row(5, 'plugin_a_cache'),
    $row(6, 'plugin_b_cache'),
    $row(7, 'plugin_[stable'),
    $row(8, 'plugin_α_cache', 'UTF-16BE'),
    $row(9, 'plugin_[draft😀', 'UTF-16BE'),
    $row(10, 'plugin_[draft_new'),
    $row(11, 'plugin_[draft🙂', 'UTF-16BE'),
];

$plan = static fn (
    string $pattern,
    string $encoding = 'UTF-16LE',
    string $currentRangeEncoding = 'UTF-16LE',
    string $nextRangeEncoding = 'UTF-16BE',
): array => SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    $bytes($pattern, $encoding),
    $encoding,
    'GLOB',
    'BINARY',
    null,
    null,
    false,
    'main.app_settings@current',
    'main.app_settings@next',
    $currentRangeEncoding,
    $nextRangeEncoding,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'unmatched bracket lower bound keeps literal bracket prefix' => ['plugin_[draft*', 'rangePlan.range.lowerInclusive', 'plugin_[draft'],
    'unmatched bracket upper bound advances full literal prefix' => ['plugin_[draft*', 'rangePlan.range.upperBound', 'plugin_[drafu'],
    'unmatched bracket prefix recorded' => ['plugin_[draft*', 'rangePlan.prefix', 'plugin_[draft'],
    'unmatched bracket index usable' => ['plugin_[draft*', 'rangePlan.indexUsable', true],
    'unmatched bracket has no rejection' => ['plugin_[draft*', 'rangePlan.rejectedReason', null],
    'unmatched bracket current rowids are narrowed' => ['plugin_[draft*', 'currentRowids', [1, 2, 3, 4, 9]],
    'unmatched bracket next rowids include fresh literals' => ['plugin_[draft*', 'nextRowids', [1, 2, 3, 4, 9, 10, 11]],
    'unmatched bracket retained rowids' => ['plugin_[draft*', 'retainedRowids', [1, 2, 3, 4, 9]],
    'unmatched bracket entered rowids' => ['plugin_[draft*', 'enteredRowids', [10, 11]],
    'unmatched bracket exited rowids empty' => ['plugin_[draft*', 'exitedRowids', []],
    'unmatched bracket current range lower utf16le bytes' => ['plugin_[draft*', 'currentRangeBytesHex.lowerInclusive', '70006c007500670069006e005f005b0064007200610066007400'],
    'unmatched bracket next range lower utf16be bytes' => ['plugin_[draft*', 'nextRangeBytesHex.lowerInclusive', '0070006c007500670069006e005f005b00640072006100660074'],
    'unmatched bracket next range upper utf16be bytes' => ['plugin_[draft*', 'nextRangeBytesHex.upperBound', '0070006c007500670069006e005f005b00640072006100660075'],
    'unmatched bracket current first text' => ['plugin_[draft*', 'currentMatchedRows.0.textValue', 'plugin_[draft]'],
    'unmatched bracket current last emoji text' => ['plugin_[draft*', 'currentMatchedRows.4.textValue', 'plugin_[draft😀'],
    'unmatched bracket next changed encoding row' => ['plugin_[draft*', 'changedEncodingRowids', [2, 9]],
    'unmatched bracket next changed bytes row' => ['plugin_[draft*', 'changedBytesRowids', [2, 4, 9]],
    'unmatched bracket invalidates on rowset and bytes' => ['plugin_[draft*', 'cursorInvalidated', true],
    'unmatched bracket source reason' => ['plugin_[draft*', 'invalidationReasons.0', 'source-name'],
    'unmatched bracket encoding reason' => ['plugin_[draft*', 'invalidationReasons.1', 'text-encoding'],
    'unmatched bracket bytes reason' => ['plugin_[draft*', 'invalidationReasons.2', 'value-bytes'],
    'unmatched bracket rowset reason' => ['plugin_[draft*', 'invalidationReasons.3', 'matched-rowset'],
    'unmatched bracket range encoding reason' => ['plugin_[draft*', 'invalidationReasons.4', 'range-encoding'],
    'unmatched bracket range bytes reason' => ['plugin_[draft*', 'invalidationReasons.5', 'range-bytes'],
    'valid class still stops before bracket lower' => ['plugin_[ab]*', 'rangePlan.range.lowerInclusive', 'plugin_'],
    'valid class still stops before bracket upper' => ['plugin_[ab]*', 'rangePlan.range.upperBound', 'plugin`'],
    'valid class current rowids use residual class' => ['plugin_[ab]*', 'currentRowids', [5, 6]],
    'valid class next rowids use residual class' => ['plugin_[ab]*', 'nextRowids', [5, 6]],
    'valid class prefix is preclass text' => ['plugin_[ab]*', 'rangePlan.prefix', 'plugin_'],
    'valid negated class stops before bracket' => ['plugin_[^[]*', 'rangePlan.prefix', 'plugin_'],
    'valid negated class excludes literal bracket rows' => ['plugin_[^[]*', 'currentRowids', [5, 6, 8]],
    'literal opening bracket exact lower' => ['plugin_[stable', 'rangePlan.range.lowerInclusive', 'plugin_[stable'],
    'literal opening bracket exact upper' => ['plugin_[stable', 'rangePlan.range.upperBound', 'plugin_[stablf'],
    'literal opening bracket exact row' => ['plugin_[stable', 'currentRowids', [7]],
    'literal opening bracket exact row next' => ['plugin_[stable', 'nextRowids', [7]],
    'literal opening bracket exact pattern bytes' => ['plugin_[stable', 'patternBytesHex', '70006c007500670069006e005f005b0073007400610062006c006500'],
    'literal opening bracket exact prefix has no wildcard' => ['plugin_[stable', 'rangePlan.prefix', 'plugin_[stable'],
    'literal opening bracket utf16be pattern encoding' => ['plugin_[draft*', 'patternEncoding', 'UTF-16BE', 'UTF-16BE'],
    'literal opening bracket utf16be pattern bytes' => ['plugin_[draft*', 'patternBytesHex', '0070006c007500670069006e005f005b00640072006100660074002a', 'UTF-16BE'],
    'unicode literal bracket range keeps emoji rows' => ['plugin_[draft😀', 'currentRowids', [9]],
    'unicode literal bracket next keeps emoji rows' => ['plugin_[draft😀', 'nextRowids', [9]],
    'unicode literal bracket upper increments emoji bytes safely' => ['plugin_[draft😀', 'rangePlan.range.upperBound', 'plugin_[draft😁'],
    'unicode literal bracket current range lower bytes' => ['plugin_[draft😀', 'currentRangeBytesHex.lowerInclusive', '70006c007500670069006e005f005b00640072006100660074003dd800de'],
    'unicode literal bracket current range upper bytes' => ['plugin_[draft😀', 'currentRangeBytesHex.upperBound', '70006c007500670069006e005f005b00640072006100660074003dd801de'],
    'dependencies decode' => ['plugin_[draft*', 'dependencies.0', 'sqlite-utf16-pattern-decode'],
    'dependencies affinity' => ['plugin_[draft*', 'dependencies.1', 'sqlite-like-glob-affinity'],
    'dependencies collation' => ['plugin_[draft*', 'dependencies.2', 'sqlite-collation-range-current-source-nextoneOneEight'],
];

foreach ($cases as $name => $case) {
    $tests['utf16 glob literal bracket current source nextOneTwoTwo ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $case): void {
        $pattern = $case[0];
        $path = $case[1];
        $expected = $case[2];
        $encoding = $case[3] ?? 'UTF-16LE';
        $t->same($expected, $valueAt($plan($pattern, $encoding), $path));
    };
}

$tests['utf16 glob literal bracket current source nextOneTwoTwo static glob prefix includes unmatched bracket'] = static function (TestRunner $t): void {
    $t->same(['lowerInclusive' => 'plugin_[draft', 'upperBound' => 'plugin_[drafu'], SQLiteDatabase::globPrefixRangeBounds('plugin_[draft*'));
};

$tests['utf16 glob literal bracket current source nextOneTwoTwo static glob prefix stops at valid bracket class'] = static function (TestRunner $t): void {
    $t->same(['lowerInclusive' => 'plugin_', 'upperBound' => 'plugin`'], SQLiteDatabase::globPrefixRangeBounds('plugin_[ab]*'));
};

$tests['utf16 glob literal bracket current source nextOneTwoTwo stable same range encoding is reusable'] = static function (TestRunner $t) use ($currentRows, $bytes): void {
    $stable = SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan::keyValueRowValuePlan(
        $currentRows,
        $currentRows,
        $bytes('plugin_[draft*', 'UTF-16LE'),
        'UTF-16LE',
        'GLOB',
        'BINARY',
        null,
        null,
        false,
        'stable',
        'stable',
        'UTF-16LE',
        'UTF-16LE',
    );
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
};

return $tests;
