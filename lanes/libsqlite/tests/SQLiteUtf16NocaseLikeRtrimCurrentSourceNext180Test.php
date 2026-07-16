<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc180 = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row180 = static fn (int $id, string $name, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc180($name, $encoding),
    'text_encoding' => $encoding,
];
$bad180 = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current180 = [
    $row180(1, 'éclair_cache', 2),
    $row180(2, 'éCLAIR_cache  ', 3),
    $row180(3, 'Éclair_cache', 2),
    $row180(4, 'éclair_theme', 3),
    $row180(5, 'eclair_cache', 2),
    $row180(6, 'éclair_cache_tab' . "\t", 2),
    $bad180(7, "\x00\xd8", 2),
];
$nextOneEightZero = [
    $row180(1, 'éclair_cache  ', 3),
    $row180(2, 'éclair_cache', 2),
    $row180(3, 'ÉCLAIR_cache', 3),
    $row180(4, 'éclair_theme', 3),
    $row180(8, 'éCLAIR_cache_new', 2),
    $row180(9, 'éclair_cache_tab' . "\t", 3),
    $bad180(10, "x\0y", 2),
];

$plan180 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'éclair!_%',
    ?string $escape = '!',
    string $currentSource = 'main.app_settings@179',
    string $nextSource = 'main.app_settings@180',
    int $currentCookie = 179,
    int $nextCookie = 180,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNonAsciiPrefixPlan(
    $current ?? $current180,
    $next ?? $nextOneEightZero,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt180 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases180 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneEightZero'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE LIKE ?'],
    'pattern' => ['pattern', 'éclair!_%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'case sensitive false' => ['caseSensitiveLike', false],
    'ascii nocase only' => ['asciiNocaseOnly', true],
    'rtrim ascii space' => ['rtrimTrimsOnlyAsciiSpace', true],
    'current source' => ['currentSource', 'main.app_settings@179'],
    'next source' => ['nextSource', 'main.app_settings@180'],
    'current cookie' => ['currentSchemaCookie', 179],
    'next cookie' => ['nextSchemaCookie', 180],
    'prefix' => ['prefix', 'éclair_'],
    'prefix non ascii' => ['prefixIsAscii', false],
    'rejected reason' => ['rejectedReason', 'nocase_like_prefix_must_be_ascii_for_range'],
    'index unusable' => ['indexUsable', false],
    'full scan fallback' => ['usesFullScanFallback', true],
    'range null' => ['range', null],
    'current decoded rowids' => ['currentDecodedRowids', [5, 3, 1, 2, 6, 4]],
    'next decoded rowids' => ['nextDecodedRowids', [3, 1, 2, 8, 9, 4]],
    'current candidates full scan' => ['currentCandidateRowids', [5, 3, 1, 2, 6, 4]],
    'next candidates full scan' => ['nextCandidateRowids', [3, 1, 2, 8, 9, 4]],
    'current matched' => ['currentMatchedRowids', [1, 2, 6, 4]],
    'next matched' => ['nextMatchedRowids', [1, 2, 8, 9, 4]],
    'current false positives' => ['currentFalsePositiveRowids', [5, 3]],
    'next false positives' => ['nextFalsePositiveRowids', [3]],
    'current non ascii rowids' => ['currentNonAsciiPrefixRowids', [1, 2, 3, 4, 6]],
    'next non ascii rowids' => ['nextNonAsciiPrefixRowids', [1, 2, 3, 4, 8, 9]],
    'current ascii folded rowids' => ['currentAsciiFoldedRowids', [2]],
    'next ascii folded rowids' => ['nextAsciiFoldedRowids', [3, 8]],
    'current malformed' => ['currentMalformedRowids', [7]],
    'next malformed' => ['nextMalformedRowids', [10]],
    'current malformed error' => ['currentErrors.7', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next malformed error' => ['nextErrors.10', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'row one current rtrim' => ['currentRtrimTexts.1', 'éclair_cache'],
    'row two current rtrim' => ['currentRtrimTexts.2', 'éCLAIR_cache'],
    'row six current keeps tab' => ['currentRtrimTexts.6', 'éclair_cache_tab' . "\t"],
    'row three current nocase non ascii' => ['currentNocaseKeys.3', 'Éclair_cache'],
    'row two current nocase ascii' => ['currentNocaseKeys.2', 'éclair_cache'],
    'row eight next text' => ['nextTexts.8', 'éCLAIR_cache_new'],
    'changed text' => ['changedTextRowids', [1, 2, 3]],
    'changed rtrim' => ['changedRtrimRowids', [2, 3]],
    'changed nocase key' => ['changedNocaseKeyRowids', []],
    'changed bytes' => ['changedBytesRowids', [1, 2, 3]],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency full scan' => ['dependencies.1', 'sqlite-like-nocase-full-scan'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-nextoneEightZero'],
];

foreach ($cases180 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneEightZero ' . $name] = static function (TestRunner $t) use ($plan180, $valueAt180, $path, $expected): void {
        $t->same($expected, $valueAt180($plan180(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneEightZero invalidation reason order'] = static function (TestRunner $t) use ($plan180): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'non-ascii-nocase-prefix-full-scan',
        'decoded-text',
        'rtrim-expression',
        'encoded-bytes',
        'malformed-text',
        'matched-rowset',
    ], $plan180()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextOneEightZero stable full scan remains reusable'] = static function (TestRunner $t) use ($row180): void {
    $rows = [
        $row180(1, 'éclair_cache', 2),
        $row180(2, 'éCLAIR_cache ', 3),
        $row180(3, 'Éclair_cache', 2),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNonAsciiPrefixPlan(
        $rows,
        $rows,
        'éclair!_%',
        '!',
        'stable',
        'stable',
        12,
        12,
    );
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([3], $result['currentFalsePositiveRowids']);
    $t->same(['non-ascii-nocase-prefix-full-scan'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneEightZero ascii prefix still uses range'] = static function (TestRunner $t) use ($row180): void {
    $rows = [
        $row180(1, 'Plugin_Cache', 2),
        $row180(2, 'plugin_cache ', 3),
        $row180(3, 'theme_cache', 2),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNonAsciiPrefixPlan(
        $rows,
        $rows,
        'plugin!_%',
        '!',
        'stable',
        'stable',
        13,
        13,
    );
    $t->same(true, $result['indexUsable']);
    $t->same(false, $result['usesFullScanFallback']);
    $t->same([1, 2], $result['currentCandidateRowids']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneEightZero no fixed prefix stays empty'] = static function (TestRunner $t) use ($row180): void {
    $rows = [
        $row180(1, 'éclair_cache', 2),
        $row180(2, 'plugin_cache', 3),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNonAsciiPrefixPlan(
        $rows,
        $rows,
        '%cache',
        null,
        'stable',
        'stable',
        14,
        14,
    );
    $t->same('no_fixed_prefix', $result['rejectedReason']);
    $t->same(false, $result['usesFullScanFallback']);
    $t->same([], $result['currentCandidateRowids']);
    $t->same([], $result['currentMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightZero rejects missing bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNonAsciiPrefixPlan(
        [['setting_id' => 1, 'text_encoding' => 2]],
        [],
        'éclair%',
    ));
};

return $tests;
