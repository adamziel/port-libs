<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding, string $load_policy = 'yes'): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => $encoding === 'UTF-16LE' ? 2 : 3,
        'load_policy' => $load_policy,
    ];
};

$raw = static fn (int $id, string $bytes, int $encoding, string $load_policy = 'yes'): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
    'load_policy' => $load_policy,
];

$currentRows = [
    $row(1, 'plugin_alpha', 'UTF-16LE'),
    $row(2, 'Plugin_Beta', 'UTF-16BE', 'no'),
    $row(3, 'plugin_😀_cache', 'UTF-16LE'),
    $row(4, 'theme_alpha', 'UTF-16LE'),
    $row(5, 'plugin_éclair', 'UTF-16BE'),
    $raw(6, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00=\xd8", 2),
    $raw(7, "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xdc\x00", 3, 'no'),
    $raw(8, "p\x00l", 2),
    $raw(9, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00=\xd8A\x00", 2),
];

$nextRows = [
    $row(1, 'plugin_alpha', 'UTF-16LE'),
    $row(2, 'Plugin_Beta', 'UTF-16BE', 'no'),
    $row(3, 'plugin_😀_cache_v2', 'UTF-16LE'),
    $row(5, 'plugin_éclair', 'UTF-16BE'),
    $row(10, 'plugin_new', 'UTF-16BE'),
    $raw(6, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00=\xd8", 2),
    $raw(7, "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xdc\x00", 3, 'no'),
    $raw(11, "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xd8=", 3),
    $raw(12, "p\x00l", 2),
];

$plan = static fn (
    string $pattern = 'plugin%',
    string $collation = 'NOCASE',
    ?string $escape = null,
    bool $caseSensitive = false,
    string $currentSource = 'main.app_settings@cookie92',
    string $nextSource = 'main.app_settings@cookie93',
): array => SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan::keyValueRowKeyLikeRange(
    $currentRows,
    $nextRows,
    $pattern,
    $collation,
    $escape,
    $caseSensitive,
    $currentSource,
    $nextSource,
);

$cases = [
    'records nocase collation' => ['plugin%', 'NOCASE', null, false, 'collation', 'NOCASE'],
    'records lower like range' => ['plugin%', 'NOCASE', null, false, 'range.lowerInclusive', 'plugin'],
    'records upper like range' => ['plugin%', 'NOCASE', null, false, 'range.upperBound', 'plugio'],
    'source change is visible' => ['plugin%', 'NOCASE', null, false, 'sourceChanged', true],
    'cursor invalidates on malformed rowset' => ['plugin%', 'NOCASE', null, false, 'cursorInvalidated', true],
    'source reason is first' => ['plugin%', 'NOCASE', null, false, 'invalidationReasons.0', 'source-name'],
    'malformed utf16 reason is present' => ['plugin%', 'NOCASE', null, false, 'invalidationReasons.1', 'malformed-utf16'],
    'omitted malformed reason is present' => ['plugin%', 'NOCASE', null, false, 'invalidationReasons.2', 'omitted-malformed-range-row'],
    'matched rowset reason is present' => ['plugin%', 'NOCASE', null, false, 'invalidationReasons.3', 'matched-rowset'],
    'current rowids omit malformed utf16 rows' => ['plugin%', 'NOCASE', null, false, 'currentRowids', [1, 2, 5, 3]],
    'next rowids omit malformed utf16 rows' => ['plugin%', 'NOCASE', null, false, 'nextRowids', [1, 2, 10, 5, 3]],
    'retained rowids preserve current order' => ['plugin%', 'NOCASE', null, false, 'retainedRowids', [1, 2, 5, 3]],
    'entered rowids expose next valid source' => ['plugin%', 'NOCASE', null, false, 'enteredRowids', [10]],
    'exited rowids are empty when only malformed rows changed' => ['plugin%', 'NOCASE', null, false, 'exitedRowids', []],
    'current malformed rowids include all malformed forms' => ['plugin%', 'NOCASE', null, false, 'malformedCurrentRowids', [6, 7, 8, 9]],
    'next malformed rowids include all malformed forms' => ['plugin%', 'NOCASE', null, false, 'malformedNextRowids', [6, 7, 11, 12]],
    'current omitted rowids match malformed rowids' => ['plugin%', 'NOCASE', null, false, 'omittedMalformedCurrentRowids', [6, 7, 8, 9]],
    'next omitted rowids match malformed rowids' => ['plugin%', 'NOCASE', null, false, 'omittedMalformedNextRowids', [6, 7, 11, 12]],
    'current valid utf16le decodes alpha' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.1.decoded', 'plugin_alpha'],
    'current valid utf16le is in range' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.1.inRange', true],
    'current valid utf16le residual matches' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.1.residualMatch', true],
    'current valid utf16le is not omitted' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.1.omitted', false],
    'current utf16be mixed case decodes beta' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.2.decoded', 'Plugin_Beta'],
    'current utf16be mixed case matches nocase' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.2.residualMatch', true],
    'theme row decodes outside range' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.4.inRange', false],
    'theme row residual does not match' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.4.residualMatch', false],
    'trailing high surrogate reason' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.6.malformedReason', 'trailing-high-surrogate'],
    'unpaired low surrogate reason' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.7.malformedReason', 'unpaired-low-surrogate'],
    'odd byte length reason' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.8.malformedReason', 'odd-byte-length'],
    'unpaired high surrogate reason' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.9.malformedReason', 'unpaired-high-surrogate'],
    'malformed rows do not report decoded text' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.6.decoded', null],
    'malformed rows are outside range' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.6.inRange', false],
    'malformed rows do not residual match' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.6.residualMatch', false],
    'malformed rows are omitted' => ['plugin%', 'NOCASE', null, false, 'currentDiagnostics.6.omitted', true],
    'next valid new row decodes' => ['plugin%', 'NOCASE', null, false, 'nextDiagnostics.10.decoded', 'plugin_new'],
    'next big endian trailing high reason' => ['plugin%', 'NOCASE', null, false, 'nextDiagnostics.11.malformedReason', 'trailing-high-surrogate'],
    'next odd byte length reason' => ['plugin%', 'NOCASE', null, false, 'nextDiagnostics.12.malformedReason', 'odd-byte-length'],
    'binary case sensitive skips uppercase beta' => ['plugin%', 'BINARY', null, true, 'currentRowids', [1, 5, 3]],
    'binary case sensitive next skips uppercase beta' => ['plugin%', 'BINARY', null, true, 'nextRowids', [1, 10, 5, 3]],
    'binary case sensitive entered row survives' => ['plugin%', 'BINARY', null, true, 'enteredRowids', [10]],
    'escaped underscore literal keeps prefixed matches' => ['plugin\_%', 'NOCASE', '\\', false, 'currentRowids', [1, 2, 5, 3]],
    'same source still invalidates on malformed utf16' => ['plugin%', 'NOCASE', null, false, 'cursorInvalidated', true, 'stable', 'stable'],
    'same source removes source reason' => ['plugin%', 'NOCASE', null, false, 'invalidationReasons.0', 'malformed-utf16', 'stable', 'stable'],
    'leading wildcard has no range' => ['%plugin', 'NOCASE', null, false, 'range', null],
    'leading wildcard has no range matches' => ['%plugin', 'NOCASE', null, false, 'currentRowids', []],
    'dependency records tolerant decoder' => ['plugin%', 'NOCASE', null, false, 'dependencies.1', 'sqlite-tolerant-utf16-source-decode'],
];

foreach ($cases as $name => $case) {
    $tests['malformed utf16 like range current source next93 ' . $name] = static function (TestRunner $t) use ($plan, $case): void {
        [$pattern, $collation, $escape, $caseSensitive, $path, $expected] = $case;
        $currentSource = $case[6] ?? 'main.app_settings@cookie92';
        $nextSource = $case[7] ?? 'main.app_settings@cookie93';
        $value = $plan($pattern, $collation, $escape, $caseSensitive, $currentSource, $nextSource);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['malformed utf16 like range current source next93 rejects utf8 rows'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $bad = $currentRows;
    $bad[] = ['setting_id' => 20, 'key_name_bytes' => 'plugin_utf8', 'text_encoding' => 1];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan::keyValueRowKeyLikeRange($bad, $nextRows, 'plugin%'));
};

$tests['malformed utf16 like range current source next93 rejects missing bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan::keyValueRowKeyLikeRange([['setting_id' => 1, 'text_encoding' => 2]], $nextRows, 'plugin%'));
};

$tests['malformed utf16 like range current source next93 rejects non integer rowid'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan::keyValueRowKeyLikeRange([['setting_id' => '1', 'key_name_bytes' => 'p', 'text_encoding' => 2]], $nextRows, 'plugin%'));
};

$tests['malformed utf16 like range current source next93 rejects bad collation'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan::keyValueRowKeyLikeRange($currentRows, $nextRows, 'plugin%', 'APP_LOCALE'));
};

$tests['malformed utf16 like range current source next93 rejects bad escape'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan::keyValueRowKeyLikeRange($currentRows, $nextRows, 'plugin%', 'NOCASE', 'xx'));
};

return $tests;
