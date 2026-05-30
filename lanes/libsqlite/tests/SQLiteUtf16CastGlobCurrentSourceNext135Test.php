<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16CastGlobCurrentSourceNextPlan;

$tests = [];

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value_bytes' => $enc('plugin_cache', 2), 'text_encoding' => 2],
    ['option_id' => 2, 'option_name' => 'home', 'option_value_bytes' => $enc('plugin_cache ', 2), 'text_encoding' => 2],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value_bytes' => $enc('plugin_cache_extra', 3), 'text_encoding' => 3],
    ['option_id' => 4, 'option_name' => 'template', 'option_value_bytes' => $enc('Plugin_Cache', 2), 'text_encoding' => 2],
    ['option_id' => 5, 'option_name' => 'stylesheet', 'option_value_bytes' => $enc('plugin_éclair', 3), 'text_encoding' => 3],
    ['option_id' => 6, 'option_name' => 'emoji', 'option_value_bytes' => $enc('plugin_😀', 2), 'text_encoding' => 2],
    ['option_id' => 7, 'option_name' => 'blob_payload', 'option_value_bytes' => $enc('plugin_blob ', 2), 'text_encoding' => 2, 'storage_class' => 'blob'],
    ['option_id' => 8, 'option_name' => 'numeric_text', 'option_value_bytes' => $enc('42 plugins', 2), 'text_encoding' => 2],
    ['option_id' => 9, 'option_name' => 'theme', 'option_value_bytes' => $enc('theme_cache', 2), 'text_encoding' => 2],
    ['option_id' => 10, 'option_name' => 'broken_odd', 'option_value_bytes' => "p\0x", 'text_encoding' => 2],
    ['option_id' => 11, 'option_name' => 'broken_surrogate', 'option_value_bytes' => "\x00\xd8A\0", 'text_encoding' => 2],
    ['option_id' => 12, 'option_name' => 'utf8_text', 'option_value_bytes' => 'plugin_utf8', 'text_encoding' => 1],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value_bytes' => $enc('plugin_cache', 2), 'text_encoding' => 2],
    ['option_id' => 2, 'option_name' => 'home', 'option_value_bytes' => $enc('plugin_cache', 2), 'text_encoding' => 2],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value_bytes' => $enc('plugin_cache_extra_v2', 3), 'text_encoding' => 3],
    ['option_id' => 4, 'option_name' => 'template', 'option_value_bytes' => $enc('Plugin_Cache', 2), 'text_encoding' => 2],
    ['option_id' => 5, 'option_name' => 'unicode', 'option_value_bytes' => $enc('plugin_éclair', 2), 'text_encoding' => 2],
    ['option_id' => 6, 'option_name' => 'emoji', 'option_value_bytes' => $enc('plugin_😀', 2), 'text_encoding' => 2],
    ['option_id' => 7, 'option_name' => 'blob_payload', 'option_value_bytes' => $enc('plugin_blob', 2), 'text_encoding' => 2, 'storage_class' => 'blob'],
    ['option_id' => 8, 'option_name' => 'numeric_text', 'option_value_bytes' => $enc('42', 2), 'text_encoding' => 2],
    ['option_id' => 9, 'option_name' => 'theme', 'option_value_bytes' => $enc('theme_cache', 2), 'text_encoding' => 2],
    ['option_id' => 11, 'option_name' => 'broken_surrogate', 'option_value_bytes' => "\x00\xd8A\0", 'text_encoding' => 2],
    ['option_id' => 12, 'option_name' => 'utf8_text', 'option_value_bytes' => 'plugin_utf8', 'text_encoding' => 1],
    ['option_id' => 13, 'option_name' => 'fresh', 'option_value_bytes' => $enc('plugin_cache_new', 3), 'text_encoding' => 3],
];

$plan = static fn (
    string $pattern,
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.wp_options@134',
    string $nextSource = 'main.wp_options@135',
    int $currentCookie = 134,
    int $nextCookie = 135,
): array => SQLiteUtf16CastGlobCurrentSourceNextPlan::optionRowValuePlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && ctype_digit($part)) {
            $part = (int) $part;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'operator' => ['plugin_*', 'operator', 'GLOB'],
    'collation' => ['plugin_*', 'collation', 'BINARY'],
    'cast target' => ['plugin_*', 'castTarget', 'TEXT'],
    'pattern' => ['plugin_*', 'pattern', 'plugin_*'],
    'range lower' => ['plugin_*', 'range.lowerInclusive', 'plugin_'],
    'range upper' => ['plugin_*', 'range.upperBound', 'plugin`'],
    'index usable' => ['plugin_*', 'indexUsable', true],
    'residual scan' => ['plugin_*', 'residualScan', true],
    'glob ignores collation marker' => ['plugin_*', 'globDoesNotUseCollation', true],
    'cast decodes utf16 marker' => ['plugin_*', 'castDecodesUtf16BeforeGlob', true],
    'current source' => ['plugin_*', 'currentSource', 'main.wp_options@134'],
    'next source' => ['plugin_*', 'nextSource', 'main.wp_options@135'],
    'current cookie' => ['plugin_*', 'currentSchemaCookie', 134],
    'next cookie' => ['plugin_*', 'nextSchemaCookie', 135],
    'current candidates' => ['plugin_*', 'currentCandidateRowids', [1, 2, 3, 5, 6, 7, 12]],
    'next candidates' => ['plugin_*', 'nextCandidateRowids', [1, 2, 3, 5, 6, 7, 12, 13]],
    'current residual rejects none for wildcard' => ['plugin_*', 'currentResidualRejectedRowids', []],
    'next residual rejects none for wildcard' => ['plugin_*', 'nextResidualRejectedRowids', []],
    'current matched wildcard' => ['plugin_*', 'currentRowids', [1, 2, 3, 5, 6, 7, 12]],
    'next matched wildcard' => ['plugin_*', 'nextRowids', [1, 2, 3, 5, 6, 7, 12, 13]],
    'retained wildcard' => ['plugin_*', 'retainedRowids', [1, 2, 3, 5, 6, 7, 12]],
    'entered wildcard' => ['plugin_*', 'enteredRowids', [13]],
    'exited wildcard' => ['plugin_*', 'exitedRowids', []],
    'changed cast rowids wildcard' => ['plugin_*', 'changedCastRowids', [2, 3, 7, 8, 13]],
    'changed bytes rowids wildcard' => ['plugin_*', 'changedBytesRowids', [2, 3, 5, 7, 8, 13]],
    'changed encoding rowids wildcard' => ['plugin_*', 'changedEncodingRowids', [5, 13]],
    'changed candidate rowids wildcard' => ['plugin_*', 'changedCandidateRowids', [13]],
    'changed match rowids wildcard' => ['plugin_*', 'changedMatchRowids', [13]],
    'current malformed rowids' => ['plugin_*', 'currentMalformedRowids', [10, 11]],
    'next malformed rowids' => ['plugin_*', 'nextMalformedRowids', [11]],
    'current odd error' => ['plugin_*', 'currentErrors.10', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'surrogate error' => ['plugin_*', 'currentErrors.11', 'SQLite encoding source UTF-16 text payload has an unpaired high surrogate'],
    'invalidated wildcard' => ['plugin_*', 'cursorInvalidated', true],
    'not reusable wildcard' => ['plugin_*', 'cursorReusable', false],
    'reason source' => ['plugin_*', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['plugin_*', 'invalidationReasons.1', 'schema-cookie'],
    'reason malformed' => ['plugin_*', 'invalidationReasons.2', 'malformed-text'],
    'reason cast' => ['plugin_*', 'invalidationReasons.3', 'cast-result'],
    'reason bytes' => ['plugin_*', 'invalidationReasons.4', 'encoded-bytes'],
    'reason encoding' => ['plugin_*', 'invalidationReasons.5', 'text-encoding'],
    'reason candidate' => ['plugin_*', 'invalidationReasons.6', 'candidate-rowset'],
    'reason matched' => ['plugin_*', 'invalidationReasons.7', 'matched-rowset'],
    'trace row one encoding' => ['plugin_*', 'currentTrace.0.encoding', 'UTF-16LE'],
    'trace row three encoding' => ['plugin_*', 'currentTrace.2.encoding', 'UTF-16BE'],
    'trace row five text' => ['plugin_*', 'currentTrace.4.castText', 'plugin_éclair'],
    'trace row six emoji' => ['plugin_*', 'currentTrace.5.castText', 'plugin_😀'],
    'trace row seven storage blob' => ['plugin_*', 'currentTrace.6.originalStorage', 'blob'],
    'trace row seven casts text' => ['plugin_*', 'currentTrace.6.castStorage', 'text'],
    'trace row twelve utf8 encoding' => ['plugin_*', 'currentTrace.9.encoding', 'UTF-8'],
    'binary exact current candidates include prefix followers' => ['plugin_cache', 'currentCandidateRowids', [1, 2, 3]],
    'binary exact next candidates include repaired and fresh prefix followers' => ['plugin_cache', 'nextCandidateRowids', [1, 2, 3, 13]],
    'binary exact current rowids' => ['plugin_cache', 'currentRowids', [1]],
    'binary exact next rowids' => ['plugin_cache', 'nextRowids', [1, 2]],
    'binary exact entered' => ['plugin_cache', 'enteredRowids', [2]],
    'binary exact row two candidate before residual' => ['plugin_cache', 'currentTrace.1.candidate', true],
    'binary exact row two matches next' => ['plugin_cache', 'nextTrace.1.matched', true],
    'case sensitive glob keeps uppercase out of range' => ['plugin_*', 'currentTrace.3.candidate', false],
    'unicode exact current rowids' => ['plugin_éclair', 'currentRowids', [5]],
    'unicode exact next rowids' => ['plugin_éclair', 'nextRowids', [5]],
    'emoji exact current rowids' => ['plugin_😀', 'currentRowids', [6]],
    'emoji exact next rowids' => ['plugin_😀', 'nextRowids', [6]],
    'blob padded exact current rejected' => ['plugin_blob', 'currentRowids', []],
    'blob trimmed exact next matched' => ['plugin_blob', 'nextRowids', [7]],
    'blob wildcard current matched' => ['plugin_blob*', 'currentRowids', [7]],
    'blob wildcard next matched' => ['plugin_blob*', 'nextRowids', [7]],
    'numeric text current matched' => ['4*', 'currentRowids', [8]],
    'numeric text next matched' => ['4*', 'nextRowids', [8]],
    'leading class no range' => ['[Pp]lugin_*', 'range', null],
    'leading class no candidates' => ['[Pp]lugin_*', 'currentCandidateRowids', []],
    'leading class no matches due no range' => ['[Pp]lugin_*', 'currentRowids', []],
    'leading class reason no prefix' => ['[Pp]lugin_*', 'invalidationReasons.2', 'no-prefix-range'],
    'dependency utf16 decode' => ['plugin_*', 'dependencies.0', 'sqlite-utf16-decode'],
    'dependency cast expression' => ['plugin_*', 'dependencies.1', 'sqlite-select-cast-expression'],
    'dependency glob range' => ['plugin_*', 'dependencies.2', 'sqlite-glob-prefix-range'],
    'dependency current source' => ['plugin_*', 'dependencies.3', 'sqlite-current-source-nextoneThreeFive'],
];

foreach ($cases as $name => [$pattern, $path, $expected]) {
    $tests['utf16 cast glob current source nextOneThreeFive ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $pattern, $path, $expected): void {
        $t->same($expected, $valueAt($plan($pattern), $path));
    };
}

$tests['utf16 cast glob current source nextOneThreeFive stable rows are reusable'] = static function (TestRunner $t) use ($enc): void {
    $rows = [
        ['option_id' => 1, 'option_value_bytes' => $enc('plugin_cache', 2), 'text_encoding' => 2],
        ['option_id' => 2, 'option_value_bytes' => $enc('plugin_cache_extra', 3), 'text_encoding' => 3],
    ];
    $plan = SQLiteUtf16CastGlobCurrentSourceNextPlan::optionRowValuePlan($rows, $rows, 'plugin_*', 'stable', 'stable', 7, 7);
    $t->same([1, 2], $plan['currentRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['utf16 cast glob current source nextOneThreeFive stable malformed row keeps blocker reason'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value_bytes' => "p\0x", 'text_encoding' => 2],
    ];
    $plan = SQLiteUtf16CastGlobCurrentSourceNextPlan::optionRowValuePlan($rows, $rows, 'plugin_*', 'stable', 'stable', 7, 7);
    $t->same([1], $plan['currentMalformedRowids']);
    $t->same([], $plan['currentRowids']);
    $t->same(['malformed-text'], $plan['invalidationReasons']);
};

$tests['utf16 cast glob current source nextOneThreeFive rejects missing option id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CastGlobCurrentSourceNextPlan::optionRowValuePlan([['option_value_bytes' => 'p', 'text_encoding' => 1]], $nextRows, 'p*'));
};

$tests['utf16 cast glob current source nextOneThreeFive rejects missing value bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CastGlobCurrentSourceNextPlan::optionRowValuePlan([['option_id' => 1, 'text_encoding' => 1]], $nextRows, 'p*'));
};

$tests['utf16 cast glob current source nextOneThreeFive rejects missing encoding'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CastGlobCurrentSourceNextPlan::optionRowValuePlan([['option_id' => 1, 'option_value_bytes' => 'p']], $nextRows, 'p*'));
};

$tests['utf16 cast glob current source nextOneThreeFive rejects unsupported storage class'] = static function (TestRunner $t): void {
    $rows = [['option_id' => 1, 'option_value_bytes' => 'p', 'text_encoding' => 1, 'storage_class' => 'integer']];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CastGlobCurrentSourceNextPlan::optionRowValuePlan($rows, $rows, 'p*'));
};

return $tests;
