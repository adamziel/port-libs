<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16CastGlobCurrentSourceNextPlan;

$tests = [];

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value_bytes' => $enc('module_cache', 2), 'text_encoding' => 2],
    ['setting_id' => 2, 'key_name' => 'landing_page', 'key_value_bytes' => $enc('module_cache ', 2), 'text_encoding' => 2],
    ['setting_id' => 3, 'key_name' => 'display_name', 'key_value_bytes' => $enc('module_cache_extra', 3), 'text_encoding' => 3],
    ['setting_id' => 4, 'key_name' => 'layout_template', 'key_value_bytes' => $enc('Module_Cache', 2), 'text_encoding' => 2],
    ['setting_id' => 5, 'key_name' => 'theme_styles', 'key_value_bytes' => $enc('module_éclair', 3), 'text_encoding' => 3],
    ['setting_id' => 6, 'key_name' => 'emoji', 'key_value_bytes' => $enc('module_😀', 2), 'text_encoding' => 2],
    ['setting_id' => 7, 'key_name' => 'blob_payload', 'key_value_bytes' => $enc('module_blob ', 2), 'text_encoding' => 2, 'storage_class' => 'blob'],
    ['setting_id' => 8, 'key_name' => 'numeric_text', 'key_value_bytes' => $enc('42 modules', 2), 'text_encoding' => 2],
    ['setting_id' => 9, 'key_name' => 'theme', 'key_value_bytes' => $enc('theme_cache', 2), 'text_encoding' => 2],
    ['setting_id' => 10, 'key_name' => 'broken_odd', 'key_value_bytes' => "p\0x", 'text_encoding' => 2],
    ['setting_id' => 11, 'key_name' => 'broken_surrogate', 'key_value_bytes' => "\x00\xd8A\0", 'text_encoding' => 2],
    ['setting_id' => 12, 'key_name' => 'utf8_text', 'key_value_bytes' => 'module_utf8', 'text_encoding' => 1],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value_bytes' => $enc('module_cache', 2), 'text_encoding' => 2],
    ['setting_id' => 2, 'key_name' => 'landing_page', 'key_value_bytes' => $enc('module_cache', 2), 'text_encoding' => 2],
    ['setting_id' => 3, 'key_name' => 'display_name', 'key_value_bytes' => $enc('module_cache_extra_v2', 3), 'text_encoding' => 3],
    ['setting_id' => 4, 'key_name' => 'layout_template', 'key_value_bytes' => $enc('Module_Cache', 2), 'text_encoding' => 2],
    ['setting_id' => 5, 'key_name' => 'unicode', 'key_value_bytes' => $enc('module_éclair', 2), 'text_encoding' => 2],
    ['setting_id' => 6, 'key_name' => 'emoji', 'key_value_bytes' => $enc('module_😀', 2), 'text_encoding' => 2],
    ['setting_id' => 7, 'key_name' => 'blob_payload', 'key_value_bytes' => $enc('module_blob', 2), 'text_encoding' => 2, 'storage_class' => 'blob'],
    ['setting_id' => 8, 'key_name' => 'numeric_text', 'key_value_bytes' => $enc('42', 2), 'text_encoding' => 2],
    ['setting_id' => 9, 'key_name' => 'theme', 'key_value_bytes' => $enc('theme_cache', 2), 'text_encoding' => 2],
    ['setting_id' => 11, 'key_name' => 'broken_surrogate', 'key_value_bytes' => "\x00\xd8A\0", 'text_encoding' => 2],
    ['setting_id' => 12, 'key_name' => 'utf8_text', 'key_value_bytes' => 'module_utf8', 'text_encoding' => 1],
    ['setting_id' => 13, 'key_name' => 'fresh', 'key_value_bytes' => $enc('module_cache_new', 3), 'text_encoding' => 3],
];

$plan = static fn (
    string $pattern,
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@134',
    string $nextSource = 'main.app_settings@135',
    int $currentCookie = 134,
    int $nextCookie = 135,
): array => SQLiteUtf16CastGlobCurrentSourceNextPlan::keyValueRowValuePlan(
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
    'operator' => ['module_*', 'operator', 'GLOB'],
    'collation' => ['module_*', 'collation', 'BINARY'],
    'cast target' => ['module_*', 'castTarget', 'TEXT'],
    'pattern' => ['module_*', 'pattern', 'module_*'],
    'range lower' => ['module_*', 'range.lowerInclusive', 'module_'],
    'range upper' => ['module_*', 'range.upperBound', 'module`'],
    'index usable' => ['module_*', 'indexUsable', true],
    'residual scan' => ['module_*', 'residualScan', true],
    'glob ignores collation marker' => ['module_*', 'globDoesNotUseCollation', true],
    'cast decodes utf16 marker' => ['module_*', 'castDecodesUtf16BeforeGlob', true],
    'current source' => ['module_*', 'currentSource', 'main.app_settings@134'],
    'next source' => ['module_*', 'nextSource', 'main.app_settings@135'],
    'current cookie' => ['module_*', 'currentSchemaCookie', 134],
    'next cookie' => ['module_*', 'nextSchemaCookie', 135],
    'current candidates' => ['module_*', 'currentCandidateRowids', [1, 2, 3, 5, 6, 7, 12]],
    'next candidates' => ['module_*', 'nextCandidateRowids', [1, 2, 3, 5, 6, 7, 12, 13]],
    'current residual rejects none for wildcard' => ['module_*', 'currentResidualRejectedRowids', []],
    'next residual rejects none for wildcard' => ['module_*', 'nextResidualRejectedRowids', []],
    'current matched wildcard' => ['module_*', 'currentRowids', [1, 2, 3, 5, 6, 7, 12]],
    'next matched wildcard' => ['module_*', 'nextRowids', [1, 2, 3, 5, 6, 7, 12, 13]],
    'retained wildcard' => ['module_*', 'retainedRowids', [1, 2, 3, 5, 6, 7, 12]],
    'entered wildcard' => ['module_*', 'enteredRowids', [13]],
    'exited wildcard' => ['module_*', 'exitedRowids', []],
    'changed cast rowids wildcard' => ['module_*', 'changedCastRowids', [2, 3, 7, 8, 13]],
    'changed bytes rowids wildcard' => ['module_*', 'changedBytesRowids', [2, 3, 5, 7, 8, 13]],
    'changed encoding rowids wildcard' => ['module_*', 'changedEncodingRowids', [5, 13]],
    'changed candidate rowids wildcard' => ['module_*', 'changedCandidateRowids', [13]],
    'changed match rowids wildcard' => ['module_*', 'changedMatchRowids', [13]],
    'current malformed rowids' => ['module_*', 'currentMalformedRowids', [10, 11]],
    'next malformed rowids' => ['module_*', 'nextMalformedRowids', [11]],
    'current odd error' => ['module_*', 'currentErrors.10', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'surrogate error' => ['module_*', 'currentErrors.11', 'SQLite encoding source UTF-16 text payload has an unpaired high surrogate'],
    'invalidated wildcard' => ['module_*', 'cursorInvalidated', true],
    'not reusable wildcard' => ['module_*', 'cursorReusable', false],
    'reason source' => ['module_*', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['module_*', 'invalidationReasons.1', 'schema-cookie'],
    'reason malformed' => ['module_*', 'invalidationReasons.2', 'malformed-text'],
    'reason cast' => ['module_*', 'invalidationReasons.3', 'cast-result'],
    'reason bytes' => ['module_*', 'invalidationReasons.4', 'encoded-bytes'],
    'reason encoding' => ['module_*', 'invalidationReasons.5', 'text-encoding'],
    'reason candidate' => ['module_*', 'invalidationReasons.6', 'candidate-rowset'],
    'reason matched' => ['module_*', 'invalidationReasons.7', 'matched-rowset'],
    'trace row one encoding' => ['module_*', 'currentTrace.0.encoding', 'UTF-16LE'],
    'trace row three encoding' => ['module_*', 'currentTrace.2.encoding', 'UTF-16BE'],
    'trace row five text' => ['module_*', 'currentTrace.4.castText', 'module_éclair'],
    'trace row six emoji' => ['module_*', 'currentTrace.5.castText', 'module_😀'],
    'trace row seven storage blob' => ['module_*', 'currentTrace.6.originalStorage', 'blob'],
    'trace row seven casts text' => ['module_*', 'currentTrace.6.castStorage', 'text'],
    'trace row twelve utf8 encoding' => ['module_*', 'currentTrace.9.encoding', 'UTF-8'],
    'binary exact current candidates include prefix followers' => ['module_cache', 'currentCandidateRowids', [1, 2, 3]],
    'binary exact next candidates include repaired and fresh prefix followers' => ['module_cache', 'nextCandidateRowids', [1, 2, 3, 13]],
    'binary exact current rowids' => ['module_cache', 'currentRowids', [1]],
    'binary exact next rowids' => ['module_cache', 'nextRowids', [1, 2]],
    'binary exact entered' => ['module_cache', 'enteredRowids', [2]],
    'binary exact row two candidate before residual' => ['module_cache', 'currentTrace.1.candidate', true],
    'binary exact row two matches next' => ['module_cache', 'nextTrace.1.matched', true],
    'case sensitive glob keeps uppercase out of range' => ['module_*', 'currentTrace.3.candidate', false],
    'unicode exact current rowids' => ['module_éclair', 'currentRowids', [5]],
    'unicode exact next rowids' => ['module_éclair', 'nextRowids', [5]],
    'emoji exact current rowids' => ['module_😀', 'currentRowids', [6]],
    'emoji exact next rowids' => ['module_😀', 'nextRowids', [6]],
    'blob padded exact current rejected' => ['module_blob', 'currentRowids', []],
    'blob trimmed exact next matched' => ['module_blob', 'nextRowids', [7]],
    'blob wildcard current matched' => ['module_blob*', 'currentRowids', [7]],
    'blob wildcard next matched' => ['module_blob*', 'nextRowids', [7]],
    'numeric text current matched' => ['4*', 'currentRowids', [8]],
    'numeric text next matched' => ['4*', 'nextRowids', [8]],
    'leading class no range' => ['[Mm]odule_*', 'range', null],
    'leading class no candidates' => ['[Mm]odule_*', 'currentCandidateRowids', []],
    'leading class no matches due no range' => ['[Mm]odule_*', 'currentRowids', []],
    'leading class reason no prefix' => ['[Mm]odule_*', 'invalidationReasons.2', 'no-prefix-range'],
    'dependency utf16 decode' => ['module_*', 'dependencies.0', 'sqlite-utf16-decode'],
    'dependency cast expression' => ['module_*', 'dependencies.1', 'sqlite-select-cast-expression'],
    'dependency glob range' => ['module_*', 'dependencies.2', 'sqlite-glob-prefix-range'],
    'dependency current source' => ['module_*', 'dependencies.3', 'sqlite-current-source-nextoneThreeFive'],
];

foreach ($cases as $name => [$pattern, $path, $expected]) {
    $tests['utf16 cast glob current source nextOneThreeFive ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $pattern, $path, $expected): void {
        $t->same($expected, $valueAt($plan($pattern), $path));
    };
}

$tests['utf16 cast glob current source nextOneThreeFive stable rows are reusable'] = static function (TestRunner $t) use ($enc): void {
    $rows = [
        ['setting_id' => 1, 'key_value_bytes' => $enc('module_cache', 2), 'text_encoding' => 2],
        ['setting_id' => 2, 'key_value_bytes' => $enc('module_cache_extra', 3), 'text_encoding' => 3],
    ];
    $plan = SQLiteUtf16CastGlobCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'module_*', 'stable', 'stable', 7, 7);
    $t->same([1, 2], $plan['currentRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['utf16 cast glob current source nextOneThreeFive stable malformed row keeps blocker reason'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value_bytes' => "p\0x", 'text_encoding' => 2],
    ];
    $plan = SQLiteUtf16CastGlobCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'module_*', 'stable', 'stable', 7, 7);
    $t->same([1], $plan['currentMalformedRowids']);
    $t->same([], $plan['currentRowids']);
    $t->same(['malformed-text'], $plan['invalidationReasons']);
};

$tests['utf16 cast glob current source nextOneThreeFive rejects missing setting id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CastGlobCurrentSourceNextPlan::keyValueRowValuePlan([['key_value_bytes' => 'p', 'text_encoding' => 1]], $nextRows, 'p*'));
};

$tests['utf16 cast glob current source nextOneThreeFive rejects missing value bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CastGlobCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => 1, 'text_encoding' => 1]], $nextRows, 'p*'));
};

$tests['utf16 cast glob current source nextOneThreeFive rejects missing encoding'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CastGlobCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => 1, 'key_value_bytes' => 'p']], $nextRows, 'p*'));
};

$tests['utf16 cast glob current source nextOneThreeFive rejects unsupported storage class'] = static function (TestRunner $t): void {
    $rows = [['setting_id' => 1, 'key_value_bytes' => 'p', 'text_encoding' => 1, 'storage_class' => 'integer']];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16CastGlobCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'p*'));
};

return $tests;
