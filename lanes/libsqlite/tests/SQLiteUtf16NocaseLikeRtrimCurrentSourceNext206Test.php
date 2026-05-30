<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc206 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row206 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc206($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad206 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current206 = [
    $row206(1, 'Plugin_Cache', 'UTF-16LE'),
    $row206(2, 'plugin_cache  ', 'UTF-16BE'),
    $row206(3, 'plugin_cache_alpha', 'UTF-16LE'),
    $row206(4, 'plugin-cache', 'UTF-8'),
    $row206(5, 'theme_cache', 'UTF-16BE'),
    $bad206(6, "\x00\xd8", 2),
];
$nextTwoZeroSix = [
    $row206(1, 'Plugin_Cache', 'UTF-16BE'),
    $row206(2, 'plugin_cache', 'UTF-16LE'),
    $row206(3, 'plugin_cache_alpha', 'UTF-16BE'),
    $row206(4, 'plugin-cache', 'UTF-8'),
    $row206(7, 'PLUGIN_CACHE_NEW', 'UTF-16LE'),
    $row206(8, 'plugin_cache\t', 'UTF-16BE'),
    $bad206(9, "\x00\xd8", 2),
];

$pattern206 = static fn (string $text, int|string $encoding, bool $bom = false): string => ($bom ? match ($encoding) {
    'UTF-16LE', 2 => "\xff\xfe",
    'UTF-16BE', 3 => "\xfe\xff",
    default => "\xef\xbb\xbf",
} : '') . SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$plan206 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?string $currentPatternBytes = null,
    int|string $currentEncoding = 'UTF-16LE',
    ?string $nextPatternBytes = null,
    int|string $nextEncoding = 'UTF-16BE',
    ?string $escape = '!',
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPreparedBomPatternPlan(
    $current ?? $current206,
    $next ?? $nextTwoZeroSix,
    $currentPatternBytes ?? $pattern206('plugin!_cache%', 'UTF-16LE', false),
    $currentEncoding,
    $nextPatternBytes ?? $pattern206('plugin!_cache%', 'UTF-16BE', true),
    $nextEncoding,
    $escape,
);

$valueAt206 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases206 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoZeroSix'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 BOM pattern */'],
    'current pattern' => ['currentPattern', 'plugin!_cache%'],
    'next pattern' => ['nextPattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'current bom absent' => ['currentPatternHadBom', false],
    'next bom present' => ['nextPatternHadBom', true],
    'current encoding' => ['currentPatternEncoding', 'UTF-16LE'],
    'next encoding' => ['nextPatternEncoding', 'UTF-16BE'],
    'current pattern hex' => ['currentPatternBytesHex', '70006c007500670069006e0021005f00630061006300680065002500'],
    'next pattern hex' => ['nextPatternBytesHex', 'feff0070006c007500670069006e0021005f006300610063006800650025'],
    'current source' => ['currentSource', 'main.app_settings@205'],
    'next source' => ['nextSource', 'main.app_settings@206'],
    'current cookie' => ['currentSchemaCookie', 205],
    'next cookie' => ['nextSchemaCookie', 206],
    'current prefix' => ['currentPrefix', 'plugin_cache'],
    'next prefix' => ['nextPrefix', 'plugin_cache'],
    'raw bom prefix' => ['rawBomPrefix', "\xef\xbb\xbfplugin_cache"],
    'current lower' => ['currentRangeLowerInclusive', 'plugin_cache'],
    'next lower' => ['nextRangeLowerInclusive', 'plugin_cache'],
    'raw lower' => ['rawBomRangeLowerInclusive', null],
    'current upper' => ['currentRangeUpperBound', 'plugin_cachf'],
    'next upper' => ['nextRangeUpperBound', 'plugin_cachf'],
    'raw upper' => ['rawBomRangeUpperBound', null],
    'current index' => ['currentIndexUsable', true],
    'next index' => ['nextIndexUsable', true],
    'raw index' => ['rawBomIndexUsable', false],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 8, 3, 7]],
    'raw candidates' => ['rawBomCandidateRowids', []],
    'current matched' => ['currentMatchedRowids', [1, 2, 3]],
    'next matched' => ['nextMatchedRowids', [1, 2, 8, 3, 7]],
    'raw matched' => ['rawBomMatchedRowids', []],
    'bom rescued' => ['bomRescuedMatchedRowids', [1, 2, 3, 7, 8]],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [8, 7]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'raw false positives' => ['rawBomFalsePositiveRowids', []],
    'current excluded' => ['currentExcludedDecodedRowids', [4, 5]],
    'next excluded' => ['nextExcludedDecodedRowids', [4]],
    'raw excluded' => ['rawBomExcludedDecodedRowids', [4, 1, 2, 8, 3, 7]],
    'current rtrim row two' => ['currentRtrimTexts.2', 'plugin_cache'],
    'next rtrim tab remains' => ['nextRtrimTexts.8', 'plugin_cache\\t'],
    'next nocase row seven' => ['nextNocaseKeys.7', 'plugin_cache_new'],
    'next matched text seven' => ['nextMatchedTexts.7', 'PLUGIN_CACHE_NEW'],
    'current malformed' => ['currentMalformedRowids', [6]],
    'next malformed' => ['nextMalformedRowids', [9]],
    'current error' => ['currentErrors.6', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'must reprepare' => ['mustReprepareForPreparedBom', true],
    'bom before prefix' => ['bomStrippedBeforePrefixPlanning', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency bom' => ['dependencies.1', 'sqlite-prepared-like-pattern-bom-normalization'],
    'dependency range' => ['dependencies.2', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.3', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoZeroSix'],
];

foreach ($cases206 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoZeroSix ' . $name] = static function (TestRunner $t) use ($plan206, $valueAt206, $path, $expected): void {
        $t->same($expected, $valueAt206($plan206(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoZeroSix invalidation reasons include prepared bom'] = static function (TestRunner $t) use ($plan206): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'prepared-pattern-bom',
        'candidate-rowset',
        'matched-rowset',
        'bom-prefix-residual-rowset',
        'malformed-text',
    ], $plan206()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroSix stable no bom can reuse cursor'] = static function (TestRunner $t) use ($row206, $pattern206): void {
    $rows = [
        $row206(1, 'plugin_cache', 'UTF-16LE'),
        $row206(2, 'Plugin_Cache  ', 'UTF-16BE'),
        $row206(3, 'plugin-cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPreparedBomPatternPlan(
        $rows,
        $rows,
        $pattern206('plugin!_cache%', 'UTF-16LE'),
        'UTF-16LE',
        $pattern206('plugin!_cache%', 'UTF-16LE'),
        'UTF-16LE',
        '!',
        'stable',
        'stable',
        206,
        206,
    );

    $t->same(false, $result['currentPatternHadBom']);
    $t->same(false, $result['nextPatternHadBom']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroSix utf8 bom pattern is stripped too'] = static function (TestRunner $t) use ($row206, $pattern206): void {
    $rows = [
        $row206(1, 'plugin_cache', 'UTF-8'),
        $row206(2, 'plugin_cache_new', 'UTF-16LE'),
        $row206(3, "\xef\xbb\xbfplugin_cache", 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPreparedBomPatternPlan(
        $rows,
        $rows,
        $pattern206('plugin!_cache%', 'UTF-8', true),
        'UTF-8',
        $pattern206('plugin!_cache%', 'UTF-8', true),
        'UTF-8',
        '!',
        'stable',
        'stable',
        206,
        206,
    );

    $t->same(true, $result['currentPatternHadBom']);
    $t->same(true, $result['nextPatternHadBom']);
    $t->same('plugin_cache', $result['currentPrefix']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same([1, 2], $result['bomRescuedMatchedRowids']);
    $t->same([3], $result['nextExcludedDecodedRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroSix pattern change after bom still invalidates'] = static function (TestRunner $t) use ($row206, $pattern206): void {
    $rows = [
        $row206(1, 'plugin_cache', 'UTF-16LE'),
        $row206(2, 'plugin_option', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPreparedBomPatternPlan(
        $rows,
        $rows,
        $pattern206('plugin!_cache%', 'UTF-16LE', true),
        'UTF-16LE',
        $pattern206('plugin!_option%', 'UTF-16BE', true),
        'UTF-16BE',
        '!',
        'stable',
        'stable',
        206,
        206,
    );

    $t->same('plugin!_cache%', $result['currentPattern']);
    $t->same('plugin!_option%', $result['nextPattern']);
    $t->same(['prepared-pattern-bom', 'decoded-pattern', 'candidate-rowset', 'matched-rowset', 'bom-prefix-residual-rowset'], $result['invalidationReasons']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([2], $result['nextMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroSix rejects malformed prepared pattern'] = static function (TestRunner $t) use ($current206, $nextTwoZeroSix, $pattern206): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPreparedBomPatternPlan(
        $current206,
        $nextTwoZeroSix,
        $pattern206('plugin%', 'UTF-16LE'),
        'UTF-16LE',
        "\xfe\xff\xd8\x00",
        'UTF-16BE',
    ));
};

return $tests;
