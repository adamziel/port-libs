<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc205 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row205 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc205($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad205 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current205 = [
    $row205(1, 'plüg_cache', 'UTF-16LE'),
    $row205(2, 'PLüG_Cache  ', 'UTF-16BE'),
    $row205(3, 'plüg-cache', 'UTF-8'),
    $row205(4, 'plüg!cache', 'UTF-16LE'),
    $row205(5, 'plugin_cache', 'UTF-16BE'),
    $row205(6, "plüg_cache\t", 'UTF-16LE'),
    $row205(7, 'plüg_cache_extra', 'UTF-16BE'),
    $row205(8, 'plÜg_cache', 'UTF-8'),
    $bad205(9, "\x00\xd8", 2),
];
$nextTwoZeroFive = [
    $row205(1, 'plüg_cache', 'UTF-16BE'),
    $row205(2, 'PLüG_Cache', 'UTF-16LE'),
    $row205(3, 'plüg-cache', 'UTF-8'),
    $row205(4, 'plüg!cache', 'UTF-16LE'),
    $row205(5, 'plugin_cache', 'UTF-16BE'),
    $row205(6, "plüg_cache\t", 'UTF-16BE'),
    $row205(7, 'plüg_cache_extra', 'UTF-16LE'),
    $row205(8, 'plÜg_cache', 'UTF-8'),
    $row205(10, 'plüg_new', 'UTF-16LE'),
    $bad205(11, "\x00\xd8", 2),
];

$plan205 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plüg!_%',
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@204',
    string $nextSource = 'main.wp_options@205',
    int $currentCookie = 204,
    int $nextCookie = 205,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameNonAsciiFullScanPlan(
    $current ?? $current205,
    $next ?? $nextTwoZeroFive,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt205 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases205 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoZeroFive'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* non-ASCII prefix fallback */'],
    'pattern' => ['pattern', 'plüg!_%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.wp_options@204'],
    'next source' => ['nextSource', 'main.wp_options@205'],
    'current cookie' => ['currentSchemaCookie', 204],
    'next cookie' => ['nextSchemaCookie', 205],
    'prefix' => ['prefix', 'plüg_'],
    'prefix chars' => ['prefixCharacters', 5],
    'prefix non ascii' => ['prefixIsAscii', false],
    'range rejection' => ['rangeRejectedReason', 'nocase_like_prefix_must_be_ascii_for_range'],
    'current index suppressed' => ['currentIndexUsable', false],
    'next index suppressed' => ['nextIndexUsable', false],
    'current scan mode' => ['currentScanMode', 'full-residual-scan'],
    'next scan mode' => ['nextScanMode', 'full-residual-scan'],
    'current candidates all decoded' => ['currentCandidateRowids', [5, 8, 4, 3, 1, 2, 6, 7]],
    'next candidates all decoded' => ['nextCandidateRowids', [5, 8, 4, 3, 1, 2, 6, 7, 10]],
    'current matched' => ['currentMatchedRowids', [1, 2, 6, 7]],
    'next matched' => ['nextMatchedRowids', [1, 2, 6, 7, 10]],
    'retained matched' => ['matchedRetainedRowids', [1, 2, 6, 7]],
    'exited matched' => ['matchedExitedRowids', []],
    'entered matched' => ['matchedEnteredRowids', [10]],
    'current false positives' => ['currentFalsePositiveRowids', [5, 8, 4, 3]],
    'next false positives' => ['nextFalsePositiveRowids', [5, 8, 4, 3]],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [11]],
    'malformed current error' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'malformed next error' => ['nextErrors.11', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current rtrim strips ascii spaces' => ['currentRtrimTexts.2', 'PLüG_Cache'],
    'current rtrim keeps tab' => ['currentRtrimTexts.6', "plüg_cache\t"],
    'next rtrim strips ascii spaces' => ['nextRtrimTexts.2', 'PLüG_Cache'],
    'ascii nocase leaves capital unicode' => ['currentNocaseKeys.8', 'plÜg_cache'],
    'ascii nocase folds ascii' => ['currentNocaseKeys.2', 'plüg_cache'],
    'current matched text upper ascii' => ['currentMatchedTexts.2', 'PLüG_Cache'],
    'next matched text new' => ['nextMatchedTexts.10', 'plüg_new'],
    'range suppressed flag' => ['rangeCursorSuppressedForNonAsciiPrefix', true],
    'residual required' => ['residualScanRequired', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency prefix' => ['dependencies.1', 'sqlite-like-nocase-non-ascii-prefix'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoZeroFive'],
];

foreach ($cases205 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoZeroFive ' . $name] = static function (TestRunner $t) use ($plan205, $valueAt205, $path, $expected): void {
        $t->same($expected, $valueAt205($plan205(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoZeroFive invalidation reasons include full scan fallback'] = static function (TestRunner $t) use ($plan205): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'non-ascii-nocase-prefix-full-scan',
        'malformed-text',
        'matched-rowset',
    ], $plan205()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroFive stable non-ascii full scan can be reusable'] = static function (TestRunner $t) use ($row205): void {
    $rows = [
        $row205(1, 'plüg_cache', 'UTF-16LE'),
        $row205(2, 'PLüG_Cache  ', 'UTF-16BE'),
        $row205(3, 'plÜg_cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameNonAsciiFullScanPlan(
        $rows,
        $rows,
        'plüg!_%',
        '!',
        'stable',
        'stable',
        205,
        205,
    );

    $t->same(false, $result['currentIndexUsable']);
    $t->same([3, 1, 2], $result['currentCandidateRowids']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([3], $result['currentFalsePositiveRowids']);
    $t->same(['non-ascii-nocase-prefix-full-scan'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroFive ascii prefix still uses range'] = static function (TestRunner $t) use ($row205): void {
    $rows = [
        $row205(1, 'plugin_cache', 'UTF-16LE'),
        $row205(2, 'plugin!cache', 'UTF-16BE'),
        $row205(3, 'other_cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameNonAsciiFullScanPlan(
        $rows,
        $rows,
        'plugin!_%',
        '!',
        'stable',
        'stable',
        205,
        205,
    );

    $t->same(true, $result['currentIndexUsable']);
    $t->same('nocase-rtrim-range', $result['currentScanMode']);
    $t->same([1], $result['currentCandidateRowids']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same(false, $result['rangeCursorSuppressedForNonAsciiPrefix']);
    $t->same([], $result['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroFive rejects invalid escape length'] = static function (TestRunner $t) use ($current205, $nextTwoZeroFive): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameNonAsciiFullScanPlan($current205, $nextTwoZeroFive, 'plüg!!_%', '!!'));
};

$tests['utf16 nocase like rtrim current source nextTwoZeroFive rejects missing encoding'] = static function (TestRunner $t) use ($nextTwoZeroFive): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameNonAsciiFullScanPlan([
        ['option_id' => 1, 'option_name_bytes' => 'plüg_cache'],
    ], $nextTwoZeroFive));
};

return $tests;
