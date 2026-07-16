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

$row = static function (int $id, string $name, string $encoding, string $load_policy = 'yes') use ($encodingNumber): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => $encodingNumber($encoding),
        'load_policy' => $load_policy,
    ];
};

$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
    'load_policy' => 'yes',
];

$bytes = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$currentRows = [
    $row(1, 'module_cache', 'UTF-16LE'),
    $row(2, 'module_cache ', 'UTF-16BE'),
    $row(3, 'module_cache  ', 'UTF-8', 'no'),
    $row(4, "module_cache\t", 'UTF-16LE'),
    $row(5, "module_cache\xc2\xa0", 'UTF-16BE'),
    $row(6, 'module_%literal', 'UTF-16LE'),
    $row(7, 'Module_Cache', 'UTF-8'),
    $row(8, 'module_éclair ', 'UTF-16LE'),
    $row(9, 'module_Éclair ', 'UTF-16BE'),
    $row(10, 'module_😀 ', 'UTF-16LE'),
    $row(11, 'theme_cache ', 'UTF-16LE'),
    $bad(12, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00c", 2),
];

$nextRows = [
    $row(1, 'module_cache', 'UTF-16BE'),
    $row(2, 'module_cache ', 'UTF-16BE'),
    $row(3, 'module_cache', 'UTF-8', 'no'),
    $row(4, "module_cache\t", 'UTF-16LE'),
    $row(5, "module_cache\xc2\xa0", 'UTF-16BE'),
    $row(6, 'module_%literal', 'UTF-16BE'),
    $row(7, 'Module_Cache', 'UTF-8'),
    $row(8, 'module_éclair ', 'UTF-16BE'),
    $row(9, 'module_Éclair ', 'UTF-16BE'),
    $row(10, 'module_😀', 'UTF-16BE'),
    $row(13, 'module_cache_new', 'UTF-16LE'),
    $bad(14, "\xd8\x00", 3),
];

$plan = static fn (
    string $pattern,
    string $patternEncoding = 'UTF-16LE',
    ?string $escape = null,
    ?string $escapeEncoding = null,
    bool $caseSensitiveLike = true,
    string $currentSource = 'main.app_settings@137',
    string $nextSource = 'main.app_settings@138',
): array => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::keyValueRowKeyPlan(
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
    'operator' => ['module_cache', 'UTF-16LE', null, null, true, 'operator', 'LIKE'],
    'collation' => ['module_cache', 'UTF-16LE', null, null, true, 'collation', 'RTRIM'],
    'decoded pattern' => ['module_cache', 'UTF-16LE', null, null, true, 'decodedPattern', 'module_cache'],
    'pattern encoding' => ['module_cache', 'UTF-16LE', null, null, true, 'patternEncoding', 'UTF-16LE'],
    'pattern bytes' => ['module_cache', 'UTF-16LE', null, null, true, 'patternBytesHex', '6d006f00640075006c0065005f0063006100630068006500'],
    'escape null' => ['module_cache', 'UTF-16LE', null, null, true, 'decodedEscape', null],
    'case sensitive flag' => ['module_cache', 'UTF-16LE', null, null, true, 'caseSensitiveLike', true],
    'rtrim like range rejected' => ['module_cache', 'UTF-16LE', null, null, true, 'range', null],
    'index not usable' => ['module_cache', 'UTF-16LE', null, null, true, 'indexUsable', false],
    'rejected reason' => ['module_cache', 'UTF-16LE', null, null, true, 'rejectedReason', 'case_sensitive_like_requires_binary_index'],
    'residual scan' => ['module_cache', 'UTF-16LE', null, null, true, 'residualScan', true],
    'does not trim trailing spaces' => ['module_cache', 'UTF-16LE', null, null, true, 'likeDoesNotTrimTrailingSpaces', true],
    'pattern decode marker' => ['module_cache', 'UTF-16LE', null, null, true, 'patternDecodedBeforeRtrimLike', true],
    'exact current rowids' => ['module_cache', 'UTF-16LE', null, null, true, 'currentRowids', [1]],
    'exact next rowids' => ['module_cache', 'UTF-16LE', null, null, true, 'nextRowids', [1, 3]],
    'exact retained rowids' => ['module_cache', 'UTF-16LE', null, null, true, 'retainedRowids', [1]],
    'exact entered rowids' => ['module_cache', 'UTF-16LE', null, null, true, 'enteredRowids', [3]],
    'exact rejected current rowids include padded rows' => ['module_cache', 'UTF-16LE', null, null, true, 'currentResidualRejectedRowids', [7, 6, 2, 3, 4, 5, 9, 8, 10, 11]],
    'wildcard current rowids' => ['module_cache%', 'UTF-16BE', null, null, true, 'currentRowids', [1, 2, 3, 4, 5]],
    'wildcard next rowids' => ['module_cache%', 'UTF-16BE', null, null, true, 'nextRowids', [1, 2, 3, 4, 13, 5]],
    'wildcard next entered' => ['module_cache%', 'UTF-16BE', null, null, true, 'enteredRowids', [13]],
    'wildcard text changes' => ['module_cache%', 'UTF-16BE', null, null, true, 'changedTextRowids', [3, 10]],
    'wildcard encoding changes' => ['module_cache%', 'UTF-16BE', null, null, true, 'changedEncodingRowids', [1, 6, 8, 10]],
    'wildcard byte changes' => ['module_cache%', 'UTF-16BE', null, null, true, 'changedBytesRowids', [1, 3, 6, 8, 10]],
    'wildcard rtrim key unchanged by trailing space repair' => ['module_cache%', 'UTF-16BE', null, null, true, 'changedRtrimKeyRowids', []],
    'space exact only' => ['module_cache ', 'UTF-16LE', null, null, true, 'currentRowids', [2]],
    'two spaces exact only' => ['module_cache  ', 'UTF-16LE', null, null, true, 'currentRowids', [3]],
    'tab exact only' => ["module_cache\t", 'UTF-16LE', null, null, true, 'currentRowids', [4]],
    'nbsp exact only' => ["module_cache\xc2\xa0", 'UTF-16BE', null, null, true, 'currentRowids', [5]],
    'literal percent escape row' => ['module_!%literal', 'UTF-16BE', '!', 'UTF-16BE', true, 'currentRowids', [6]],
    'literal percent escape decoded' => ['module_!%literal', 'UTF-16BE', '!', 'UTF-16BE', true, 'decodedEscape', '!'],
    'literal percent escape encoding' => ['module_!%literal', 'UTF-16BE', '!', 'UTF-16BE', true, 'escapeEncoding', 'UTF-16BE'],
    'literal percent escape bytes' => ['module_!%literal', 'UTF-16BE', '!', 'UTF-16BE', true, 'escapeBytesHex', '0021'],
    'escaped underscore wildcard' => ['module!_cache%', 'UTF-16LE', '!', 'UTF-16LE', true, 'currentRowids', [1, 2, 3, 4, 5]],
    'case insensitive uppercase current' => ['MODULE_CACHE', 'UTF-16LE', null, null, false, 'currentRowids', [7, 1]],
    'case insensitive uppercase next' => ['MODULE_CACHE', 'UTF-16LE', null, null, false, 'nextRowids', [7, 1, 3]],
    'case insensitive rejected reason' => ['MODULE_CACHE', 'UTF-16LE', null, null, false, 'rejectedReason', 'default_like_requires_nocase_index'],
    'lower eclair only' => ['module_éclair%', 'UTF-16BE', null, null, true, 'currentRowids', [8]],
    'upper eclair only' => ['module_Éclair%', 'UTF-16LE', null, null, true, 'currentRowids', [9]],
    'emoji space current' => ['module_😀 ', 'UTF-16LE', null, null, true, 'currentRowids', [10]],
    'emoji no space next' => ['module_😀', 'UTF-16BE', null, null, true, 'nextRowids', [10]],
    'theme exits' => ['theme_cache%', 'UTF-16LE', null, null, true, 'exitedRowids', [11]],
    'decoded row sort first uppercase' => ['module_cache', 'UTF-16LE', null, null, true, 'currentDecodedRows.0.text', 'Module_Cache'],
    'decoded rtrim trims spaces' => ['module_cache', 'UTF-16LE', null, null, true, 'currentDecodedRows.3.rtrimKey', 'module_cache'],
    'decoded tab keeps rtrim key' => ['module_cache', 'UTF-16LE', null, null, true, 'currentDecodedRows.5.rtrimKey', "module_cache\t"],
    'decoded nbsp keeps rtrim key' => ['module_cache', 'UTF-16LE', null, null, true, 'currentDecodedRows.6.rtrimKey', "module_cache\xc2\xa0"],
    'decoded row one encoding' => ['module_cache', 'UTF-16LE', null, null, true, 'currentDecodedRows.1.encoding', 'UTF-16LE'],
    'next row one encoding' => ['module_cache', 'UTF-16LE', null, null, true, 'nextDecodedRows.1.encoding', 'UTF-16BE'],
    'malformed current' => ['module_cache', 'UTF-16LE', null, null, true, 'currentMalformedRowids', [12]],
    'malformed next' => ['module_cache', 'UTF-16LE', null, null, true, 'nextMalformedRowids', [14]],
    'current malformed error' => ['module_cache', 'UTF-16LE', null, null, true, 'currentErrors.12', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['module_cache', 'UTF-16LE', null, null, true, 'nextErrors.14', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'invalidated' => ['module_cache', 'UTF-16LE', null, null, true, 'cursorInvalidated', true],
    'not reusable' => ['module_cache', 'UTF-16LE', null, null, true, 'cursorReusable', false],
    'reason source' => ['module_cache', 'UTF-16LE', null, null, true, 'invalidationReasons.0', 'source-name'],
    'reason full scan' => ['module_cache', 'UTF-16LE', null, null, true, 'invalidationReasons.1', 'full-scan-rtrim-like'],
    'reason malformed' => ['module_cache', 'UTF-16LE', null, null, true, 'invalidationReasons.2', 'malformed-text'],
    'reason rowset' => ['module_cache', 'UTF-16LE', null, null, true, 'invalidationReasons.6', 'matched-rowset'],
    'dependency pattern decode' => ['module_cache', 'UTF-16LE', null, null, true, 'dependencies.0', 'sqlite-utf16-pattern-decode'],
    'dependency row decode' => ['module_cache', 'UTF-16LE', null, null, true, 'dependencies.1', 'sqlite-utf16-row-decode'],
    'dependency marker' => ['module_cache', 'UTF-16LE', null, null, true, 'dependencies.3', 'sqlite-current-source-nextoneThreeEight'],
    'dependency closure' => ['module_cache', 'UTF-16LE', null, null, true, 'dependency_closure', 'no new support component needed; reuses native UTF-16 text decoding, LIKE pattern planning, RTRIM collation keys, and current-source invalidation metadata'],
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
    $rows = [$row(1, 'module_cache ', 'UTF-16LE'), $row(2, 'theme_cache', 'UTF-16BE')];
    $plan = SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::keyValueRowKeyPlan($rows, $rows, $bytes('module_cache%', 'UTF-16LE'), 'UTF-16LE', null, null, true, 'stable', 'stable');
    $t->same(['full-scan-rtrim-like'], $plan['invalidationReasons']);
    $t->same([1], $plan['currentRowids']);
    $t->same([1], $plan['nextRowids']);
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight accepts utf16 alias'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $plan = SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, $nextRows, $bytes('module_cache%', 'UTF-16LE'), 'UTF-16');
    $t->same('UTF-16LE', $plan['patternEncoding']);
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight rejects invalid pattern encoding'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, $nextRows, $bytes('module%', 'UTF-16LE'), 'UTF-32'));
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight rejects malformed pattern'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, $nextRows, "\x00\xd8", 'UTF-16LE'));
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight rejects malformed escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, $nextRows, $bytes('module!_%', 'UTF-16LE'), 'UTF-16LE', "\x00\xd8", 'UTF-16LE'));
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight rejects multi character escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, $nextRows, $bytes('module!!_%', 'UTF-16LE'), 'UTF-16LE', $bytes('!!', 'UTF-16LE'), 'UTF-16LE'));
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight rejects missing setting id'] = static function (TestRunner $t) use ($nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::keyValueRowKeyPlan([['key_name_bytes' => 'p', 'text_encoding' => 1]], $nextRows, $bytes('p%', 'UTF-8'), 'UTF-8'));
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight rejects missing bytes'] = static function (TestRunner $t) use ($nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::keyValueRowKeyPlan([['setting_id' => 1, 'text_encoding' => 1]], $nextRows, $bytes('p%', 'UTF-8'), 'UTF-8'));
};

$tests['utf16 rtrim like pattern current source nextOneThreeEight rejects missing encoding'] = static function (TestRunner $t) use ($nextRows, $bytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::keyValueRowKeyPlan([['setting_id' => 1, 'key_name_bytes' => 'p']], $nextRows, $bytes('p%', 'UTF-8'), 'UTF-8'));
};

return $tests;
