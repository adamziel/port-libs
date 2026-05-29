<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan;

$tests = [];

$encodingNumber = static fn (string $encoding): int => match ($encoding) {
    'UTF-8' => 1,
    'UTF-16LE' => 2,
    'UTF-16BE' => 3,
};

$row = static function (int $id, string $name, string $encoding, string $autoload = 'yes') use ($encodingNumber): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => $encodingNumber($encoding),
        'autoload' => $autoload,
    ];
};

$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
    'autoload' => 'yes',
];

$bytes = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$currentRows = [
    $row(1, 'plugin_cache', 'UTF-16LE'),
    $row(2, 'plugin_cache ', 'UTF-16BE'),
    $row(3, 'plugin_cache  ', 'UTF-8', 'no'),
    $row(4, "plugin_cache\t", 'UTF-16LE'),
    $row(5, "plugin_cache\xc2\xa0", 'UTF-16BE'),
    $row(6, 'plugin_%literal', 'UTF-16LE'),
    $row(7, 'Plugin_Cache', 'UTF-8'),
    $row(8, 'plugin_éclair ', 'UTF-16LE'),
    $row(9, 'plugin_Éclair ', 'UTF-16BE'),
    $row(10, 'plugin_😀 ', 'UTF-16LE'),
    $row(11, 'theme_cache ', 'UTF-16LE'),
    $bad(12, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00c", 2),
];

$nextRows = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'plugin_cache ', 'UTF-16BE'),
    $row(3, 'plugin_cache', 'UTF-8', 'no'),
    $row(4, "plugin_cache\t", 'UTF-16LE'),
    $row(5, "plugin_cache\xc2\xa0", 'UTF-16BE'),
    $row(6, 'plugin_%literal', 'UTF-16BE'),
    $row(7, 'Plugin_Cache', 'UTF-8'),
    $row(8, 'plugin_éclair ', 'UTF-16BE'),
    $row(9, 'plugin_Éclair ', 'UTF-16BE'),
    $row(10, 'plugin_😀', 'UTF-16BE'),
    $row(13, 'plugin_cache_new', 'UTF-16LE'),
    $bad(14, "\xd8\x00", 3),
];

$plan = static fn (
    string $pattern,
    string $patternEncoding = 'UTF-16LE',
    ?string $escape = null,
    ?string $escapeEncoding = null,
    bool $caseSensitiveLike = true,
    string $currentSource = 'main.wp_options@137',
    string $nextSource = 'main.wp_options@138',
): array => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::wordpressOptionNamePlan(
    $currentRows,
    $nextRows,
    $bytes($pattern, $patternEncoding),
    $patternEncoding,
    $escape === null ? null : $bytes($escape, $escapeEncoding ?? $patternEncoding),
    $escapeEncoding,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
);

$cases = [
    'operator' => ['plugin_cache', 'UTF-16LE', null, null, true, 'operator', 'LIKE'],
    'collation' => ['plugin_cache', 'UTF-16LE', null, null, true, 'collation', 'RTRIM'],
    'decoded pattern' => ['plugin_cache', 'UTF-16LE', null, null, true, 'decodedPattern', 'plugin_cache'],
    'pattern encoding' => ['plugin_cache', 'UTF-16LE', null, null, true, 'patternEncoding', 'UTF-16LE'],
    'pattern bytes' => ['plugin_cache', 'UTF-16LE', null, null, true, 'patternBytesHex', '70006c007500670069006e005f0063006100630068006500'],
    'escape null' => ['plugin_cache', 'UTF-16LE', null, null, true, 'decodedEscape', null],
    'case sensitive flag' => ['plugin_cache', 'UTF-16LE', null, null, true, 'caseSensitiveLike', true],
    'rtrim like range rejected' => ['plugin_cache', 'UTF-16LE', null, null, true, 'range', null],
    'index not usable' => ['plugin_cache', 'UTF-16LE', null, null, true, 'indexUsable', false],
    'rejected reason' => ['plugin_cache', 'UTF-16LE', null, null, true, 'rejectedReason', 'case_sensitive_like_requires_binary_index'],
    'residual scan' => ['plugin_cache', 'UTF-16LE', null, null, true, 'residualScan', true],
    'does not trim trailing spaces' => ['plugin_cache', 'UTF-16LE', null, null, true, 'likeDoesNotTrimTrailingSpaces', true],
    'pattern decode marker' => ['plugin_cache', 'UTF-16LE', null, null, true, 'patternDecodedBeforeRtrimLike', true],
    'exact current rowids' => ['plugin_cache', 'UTF-16LE', null, null, true, 'currentRowids', [1]],
    'exact next rowids' => ['plugin_cache', 'UTF-16LE', null, null, true, 'nextRowids', [1, 3]],
    'exact retained rowids' => ['plugin_cache', 'UTF-16LE', null, null, true, 'retainedRowids', [1]],
    'exact entered rowids' => ['plugin_cache', 'UTF-16LE', null, null, true, 'enteredRowids', [3]],
    'exact rejected current rowids include padded rows' => ['plugin_cache', 'UTF-16LE', null, null, true, 'currentResidualRejectedRowids', [7, 6, 2, 3, 4, 5, 9, 8, 10, 11]],
    'wildcard current rowids' => ['plugin_cache%', 'UTF-16BE', null, null, true, 'currentRowids', [1, 2, 3, 4, 5]],
    'wildcard next rowids' => ['plugin_cache%', 'UTF-16BE', null, null, true, 'nextRowids', [1, 2, 3, 4, 13, 5]],
    'wildcard next entered' => ['plugin_cache%', 'UTF-16BE', null, null, true, 'enteredRowids', [13]],
    'wildcard text changes' => ['plugin_cache%', 'UTF-16BE', null, null, true, 'changedTextRowids', [3, 10]],
    'wildcard encoding changes' => ['plugin_cache%', 'UTF-16BE', null, null, true, 'changedEncodingRowids', [1, 6, 8, 10]],
    'wildcard byte changes' => ['plugin_cache%', 'UTF-16BE', null, null, true, 'changedBytesRowids', [1, 3, 6, 8, 10]],
    'wildcard rtrim key unchanged by trailing space repair' => ['plugin_cache%', 'UTF-16BE', null, null, true, 'changedRtrimKeyRowids', []],
    'space exact only' => ['plugin_cache ', 'UTF-16LE', null, null, true, 'currentRowids', [2]],
    'two spaces exact only' => ['plugin_cache  ', 'UTF-16LE', null, null, true, 'currentRowids', [3]],
    'tab exact only' => ["plugin_cache\t", 'UTF-16LE', null, null, true, 'currentRowids', [4]],
    'nbsp exact only' => ["plugin_cache\xc2\xa0", 'UTF-16BE', null, null, true, 'currentRowids', [5]],
    'literal percent escape row' => ['plugin_!%literal', 'UTF-16BE', '!', 'UTF-16BE', true, 'currentRowids', [6]],
    'literal percent escape decoded' => ['plugin_!%literal', 'UTF-16BE', '!', 'UTF-16BE', true, 'decodedEscape', '!'],
    'literal percent escape encoding' => ['plugin_!%literal', 'UTF-16BE', '!', 'UTF-16BE', true, 'escapeEncoding', 'UTF-16BE'],
    'literal percent escape bytes' => ['plugin_!%literal', 'UTF-16BE', '!', 'UTF-16BE', true, 'escapeBytesHex', '0021'],
    'escaped underscore wildcard' => ['plugin!_cache%', 'UTF-16LE', '!', 'UTF-16LE', true, 'currentRowids', [1, 2, 3, 4, 5]],
    'case insensitive uppercase current' => ['PLUGIN_CACHE', 'UTF-16LE', null, null, false, 'currentRowids', [7, 1]],
    'case insensitive uppercase next' => ['PLUGIN_CACHE', 'UTF-16LE', null, null, false, 'nextRowids', [7, 1, 3]],
    'case insensitive rejected reason' => ['PLUGIN_CACHE', 'UTF-16LE', null, null, false, 'rejectedReason', 'default_like_requires_nocase_index'],
    'lower eclair only' => ['plugin_éclair%', 'UTF-16BE', null, null, true, 'currentRowids', [8]],
    'upper eclair only' => ['plugin_Éclair%', 'UTF-16LE', null, null, true, 'currentRowids', [9]],
    'emoji space current' => ['plugin_😀 ', 'UTF-16LE', null, null, true, 'currentRowids', [10]],
    'emoji no space next' => ['plugin_😀', 'UTF-16BE', null, null, true, 'nextRowids', [10]],
    'theme exits' => ['theme_cache%', 'UTF-16LE', null, null, true, 'exitedRowids', [11]],
    'decoded row sort first uppercase' => ['plugin_cache', 'UTF-16LE', null, null, true, 'currentDecodedRows.0.text', 'Plugin_Cache'],
    'decoded rtrim trims spaces' => ['plugin_cache', 'UTF-16LE', null, null, true, 'currentDecodedRows.3.rtrimKey', 'plugin_cache'],
    'decoded tab keeps rtrim key' => ['plugin_cache', 'UTF-16LE', null, null, true, 'currentDecodedRows.5.rtrimKey', "plugin_cache\t"],
    'decoded nbsp keeps rtrim key' => ['plugin_cache', 'UTF-16LE', null, null, true, 'currentDecodedRows.6.rtrimKey', "plugin_cache\xc2\xa0"],
    'decoded row one encoding' => ['plugin_cache', 'UTF-16LE', null, null, true, 'currentDecodedRows.1.encoding', 'UTF-16LE'],
    'next row one encoding' => ['plugin_cache', 'UTF-16LE', null, null, true, 'nextDecodedRows.1.encoding', 'UTF-16BE'],
    'malformed current' => ['plugin_cache', 'UTF-16LE', null, null, true, 'currentMalformedRowids', [12]],
    'malformed next' => ['plugin_cache', 'UTF-16LE', null, null, true, 'nextMalformedRowids', [14]],
    'current malformed error' => ['plugin_cache', 'UTF-16LE', null, null, true, 'currentErrors.12', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['plugin_cache', 'UTF-16LE', null, null, true, 'nextErrors.14', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'invalidated' => ['plugin_cache', 'UTF-16LE', null, null, true, 'cursorInvalidated', true],
    'not reusable' => ['plugin_cache', 'UTF-16LE', null, null, true, 'cursorReusable', false],
    'reason source' => ['plugin_cache', 'UTF-16LE', null, null, true, 'invalidationReasons.0', 'source-name'],
    'reason full scan' => ['plugin_cache', 'UTF-16LE', null, null, true, 'invalidationReasons.1', 'full-scan-rtrim-like'],
    'reason malformed' => ['plugin_cache', 'UTF-16LE', null, null, true, 'invalidationReasons.2', 'malformed-text'],
    'reason rowset' => ['plugin_cache', 'UTF-16LE', null, null, true, 'invalidationReasons.6', 'matched-rowset'],
    'dependency pattern decode' => ['plugin_cache', 'UTF-16LE', null, null, true, 'dependencies.0', 'sqlite-utf16-pattern-decode'],
    'dependency row decode' => ['plugin_cache', 'UTF-16LE', null, null, true, 'dependencies.1', 'sqlite-utf16-row-decode'],
    'dependency marker' => ['plugin_cache', 'UTF-16LE', null, null, true, 'dependencies.3', 'sqlite-current-source-nextoneThreeEight'],
    'dependency closure' => ['plugin_cache', 'UTF-16LE', null, null, true, 'dependency_closure', 'no new support component needed; reuses native UTF-16 text decoding, LIKE pattern planning, RTRIM collation keys, and current-source invalidation metadata'],
];

foreach ($cases as $name => [$pattern, $patternEncoding, $escape, $escapeEncoding, $caseSensitiveLike, $path, $expected]) {
    $tests['utf16 rtrim like pattern current source nextOneThreeEight ' . $name] = static function (TestRunner $t) use ($plan, $pattern, $patternEncoding, $escape, $escapeEncoding, $caseSensitiveLike, $path, $expected): void {
        $value = $plan($pattern, $patternEncoding, $escape, $escapeEncoding, $caseSensitiveLike);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['utf16 rtrim like pattern current source nextOneThreeEight stable unchanged still records rtrim full scan'] = static function (TestRunner $t) use ($row, $bytes): void {
    $rows = [$row(1, 'plugin_cache ', 'UTF-16LE'), $row(2, 'theme_cache', 'UTF-16BE')];
    $plan = SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::wordpressOptionNamePlan($rows, $rows, $bytes('plugin_cache%', 'UTF-16LE'), 'UTF-16LE', null, null, true, 'stable', 'stable');
    $t->same(['full-scan-rtrim-like'], $plan['invalidationReasons']);
    $t->same([1], $plan['currentRowids']);
    $t->same([1], $plan['nextRowids']);
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight accepts utf16 alias'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $plan = SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::wordpressOptionNamePlan($currentRows, $nextRows, $bytes('plugin_cache%', 'UTF-16LE'), 'UTF-16');
    $t->same('UTF-16LE', $plan['patternEncoding']);
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight rejects invalid pattern encoding'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::wordpressOptionNamePlan($currentRows, $nextRows, $bytes('plugin%', 'UTF-16LE'), 'UTF-32'));
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight rejects malformed pattern'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::wordpressOptionNamePlan($currentRows, $nextRows, "\x00\xd8", 'UTF-16LE'));
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight rejects malformed escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::wordpressOptionNamePlan($currentRows, $nextRows, $bytes('plugin!_%', 'UTF-16LE'), 'UTF-16LE', "\x00\xd8", 'UTF-16LE'));
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight rejects multi character escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::wordpressOptionNamePlan($currentRows, $nextRows, $bytes('plugin!!_%', 'UTF-16LE'), 'UTF-16LE', $bytes('!!', 'UTF-16LE'), 'UTF-16LE'));
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight rejects missing option id'] = static function (TestRunner $t) use ($nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::wordpressOptionNamePlan([['option_name_bytes' => 'p', 'text_encoding' => 1]], $nextRows, $bytes('p%', 'UTF-8'), 'UTF-8'));
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight rejects missing bytes'] = static function (TestRunner $t) use ($nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::wordpressOptionNamePlan([['option_id' => 1, 'text_encoding' => 1]], $nextRows, $bytes('p%', 'UTF-8'), 'UTF-8'));
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight rejects missing encoding'] = static function (TestRunner $t) use ($nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::wordpressOptionNamePlan([['option_id' => 1, 'option_name_bytes' => 'p']], $nextRows, $bytes('p%', 'UTF-8'), 'UTF-8'));
};

return $tests;
