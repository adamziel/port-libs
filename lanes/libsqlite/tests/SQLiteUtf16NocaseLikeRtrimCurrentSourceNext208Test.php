<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc208 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$encodingId208 = static fn (int|string $encoding): int => match ($encoding) {
    'UTF-8', 1 => 1,
    'UTF-16LE', 2 => 2,
    'UTF-16BE', 3 => 3,
};
$row208 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc208($name, $encoding),
    'text_encoding' => $encodingId208($encoding),
];
$bad208 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current208 = [
    $row208(1, 'Plugin_Cache', 'UTF-16LE'),
    $row208(2, 'plugin_cache  ', 'UTF-16BE'),
    $row208(3, 'plugin_cache_alpha', 'UTF-16LE'),
    $row208(4, 'plugin%cache', 'UTF-8'),
    $row208(5, 'pluginXcache', 'UTF-16BE'),
    $row208(6, "plugin_cache\t", 'UTF-16LE'),
    $row208(7, 'theme_cache', 'UTF-16BE'),
    $bad208(8, "\x00\xd8", 2),
];
$nextTwoZeroEight = [
    $row208(1, 'Plugin_Cache', 'UTF-16BE'),
    $row208(2, 'plugin_cache', 'UTF-16LE'),
    $row208(3, 'plugin_cache_alpha', 'UTF-16BE'),
    $row208(4, 'plugin%cache', 'UTF-8'),
    $row208(5, 'pluginXcache', 'UTF-16LE'),
    $row208(6, "plugin_cache\t", 'UTF-16LE'),
    $row208(9, 'PLUGIN_CACHE_ZETA', 'UTF-16BE'),
    $bad208(10, "x\0y", 2),
];

$escapeBytes208 = static fn (string $text, int|string $encoding, bool $bom = false): string => ($bom ? match ($encoding) {
    'UTF-16LE', 2 => "\xff\xfe",
    'UTF-16BE', 3 => "\xfe\xff",
    default => "\xef\xbb\xbf",
} : '') . $enc208($text, $encoding);

$plan208 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?string $pattern = null,
    ?string $currentEscapeBytes = null,
    int|string $currentEscapeEncoding = 'UTF-16LE',
    ?string $nextEscapeBytes = null,
    int|string $nextEscapeEncoding = 'UTF-16BE',
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePreparedEscapePlan(
    $current ?? $current208,
    $next ?? $nextTwoZeroEight,
    $pattern ?? 'plugin!_cache%',
    $currentEscapeBytes ?? $escapeBytes208('!', 'UTF-16LE'),
    $currentEscapeEncoding,
    $nextEscapeBytes ?? $escapeBytes208('~', 'UTF-16BE', true),
    $nextEscapeEncoding,
);

$valueAt208 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases208 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoZeroEight'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nexttwoZeroZero'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 escape */'],
    'template' => ['patternTemplate', 'plugin!_cache%'],
    'current pattern' => ['currentPattern', 'plugin!_cache%'],
    'next pattern' => ['nextPattern', 'plugin~_cache%'],
    'current escape decoded' => ['currentEscapeDecoded', '!'],
    'next escape decoded' => ['nextEscapeDecoded', "\xef\xbb\xbf~"],
    'current escape' => ['currentEscape', '!'],
    'next escape' => ['nextEscape', '~'],
    'current escape bom' => ['currentEscapeHadBom', false],
    'next escape bom' => ['nextEscapeHadBom', true],
    'current escape encoding' => ['currentEscapeEncoding', 'UTF-16LE'],
    'next escape encoding' => ['nextEscapeEncoding', 'UTF-16BE'],
    'current escape hex' => ['currentEscapeBytesHex', '2100'],
    'next escape hex' => ['nextEscapeBytesHex', 'feff007e'],
    'escape changed' => ['preparedEscapeChanged', true],
    'escape bytes changed' => ['preparedEscapeBytesChanged', true],
    'escape bom stripped' => ['preparedEscapeBomStrippedBeforeValidation', true],
    'raw bom rejected' => ['rawBomEscapeRejected', true],
    'current source' => ['currentSource', 'main.wp_options@207'],
    'next source' => ['nextSource', 'main.wp_options@208'],
    'current cookie' => ['currentSchemaCookie', 207],
    'next cookie' => ['nextSchemaCookie', 208],
    'collation' => ['collation', 'NOCASE'],
    'current prefix' => ['currentPrefix', 'plugin_cache'],
    'next prefix' => ['nextPrefix', 'plugin_cache'],
    'current lower' => ['currentRangeLowerInclusive', 'plugin_cache'],
    'next lower' => ['nextRangeLowerInclusive', 'plugin_cache'],
    'current upper' => ['currentRangeUpperBound', 'plugin_cachf'],
    'next upper' => ['nextRangeUpperBound', 'plugin_cachf'],
    'current index' => ['currentIndexUsable', true],
    'next index' => ['nextIndexUsable', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 6, 3]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 6, 3, 9]],
    'current matched' => ['currentMatchedRowids', [1, 2, 6, 3]],
    'next matched' => ['nextMatchedRowids', [1, 2, 6, 3, 9]],
    'next with current escape' => ['nextMatchedWithCurrentEscapeRowids', [1, 2, 6, 3, 9]],
    'current with next escape' => ['currentMatchedWithNextEscapeRowids', [1, 2, 6, 3]],
    'escape flip' => ['escapeResidualFlipRowids', []],
    'current escape flip' => ['currentEscapeResidualFlipRowids', []],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [9]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'current excluded' => ['currentExcludedDecodedRowids', [4, 5, 7]],
    'next excluded' => ['nextExcludedDecodedRowids', [4, 5]],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [10]],
    'current error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.10', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'current rtrim two' => ['currentRtrimTexts.2', 'plugin_cache'],
    'next tab not trimmed' => ['nextRtrimTexts.6', "plugin_cache\t"],
    'next nocase nine' => ['nextNocaseKeys.9', 'plugin_cache_zeta'],
    'next matched nine' => ['nextMatchedTexts.9', 'PLUGIN_CACHE_ZETA'],
    'base escape changed' => ['escapeChanged', true],
    'prefix changed false' => ['prefixChangedByEscape', false],
    'range changed false' => ['rangeChangedByEscape', false],
    'residual changed false' => ['residualChangedByEscape', false],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'must reprepare' => ['mustReprepareForPreparedEscape', true],
    'stale risk false' => ['staleRangeCursorRisk', false],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'decoded before range' => ['escapeDecodedBeforeRangePlanning', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency escape' => ['dependencies.1', 'sqlite-prepared-like-escape-decode'],
    'dependency range' => ['dependencies.2', 'sqlite-like-escape-prefix-range'],
    'dependency rtrim' => ['dependencies.3', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoZeroEight'],
];

foreach ($cases208 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoZeroEight ' . $name] = static function (TestRunner $t) use ($plan208, $valueAt208, $path, $expected): void {
        $t->same($expected, $valueAt208($plan208(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoZeroEight invalidation reasons include prepared escape bytes'] = static function (TestRunner $t) use ($plan208): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'pattern',
        'escape-rebound',
        'malformed-text',
        'matched-rowset',
        'prepared-escape-bom',
        'prepared-escape-bytes',
    ], $plan208()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroEight stable escaped parameter can reuse cursor'] = static function (TestRunner $t) use ($row208, $escapeBytes208): void {
    $rows = [
        $row208(1, 'plugin_cache', 'UTF-16LE'),
        $row208(2, 'Plugin_Cache  ', 'UTF-16BE'),
        $row208(3, 'pluginXcache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePreparedEscapePlan(
        $rows,
        $rows,
        'plugin!_cache%',
        $escapeBytes208('!', 'UTF-16LE'),
        'UTF-16LE',
        $escapeBytes208('!', 'UTF-16LE'),
        'UTF-16LE',
        'stable',
        'stable',
        208,
        208,
    );

    $t->same('!', $result['currentEscape']);
    $t->same('!', $result['nextEscape']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroEight escaped percent residual differs from wildcard'] = static function (TestRunner $t) use ($row208, $escapeBytes208): void {
    $rows = [
        $row208(1, 'plugin%cache', 'UTF-16LE'),
        $row208(2, 'pluginXcache', 'UTF-16BE'),
        $row208(3, 'plugin_cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePreparedEscapePlan(
        $rows,
        $rows,
        'plugin!%cache',
        $escapeBytes208('!', 'UTF-16LE'),
        'UTF-16LE',
        $escapeBytes208('~', 'UTF-16BE', true),
        'UTF-16BE',
        'stable',
        'stable',
        208,
        208,
    );

    $t->same('plugin!%cache', $result['currentPattern']);
    $t->same('plugin~%cache', $result['nextPattern']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([1], $result['nextMatchedRowids']);
    $t->same([], $result['escapeResidualFlipRowids']);
    $t->same(['pattern', 'escape-rebound', 'prepared-escape-bom', 'prepared-escape-bytes'], $result['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroEight rejects malformed escape bytes'] = static function (TestRunner $t) use ($current208, $nextTwoZeroEight, $escapeBytes208): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePreparedEscapePlan(
        $current208,
        $nextTwoZeroEight,
        'plugin!_cache%',
        $escapeBytes208('!', 'UTF-16LE'),
        'UTF-16LE',
        "\xfe\xff\xd8\x00",
        'UTF-16BE',
    ));
};

$tests['utf16 nocase like rtrim current source nextTwoZeroEight rejects multi character escape after bom'] = static function (TestRunner $t) use ($current208, $nextTwoZeroEight, $escapeBytes208): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePreparedEscapePlan(
        $current208,
        $nextTwoZeroEight,
        'plugin!_cache%',
        $escapeBytes208('!', 'UTF-16LE'),
        'UTF-16LE',
        $escapeBytes208('~~', 'UTF-16BE', true),
        'UTF-16BE',
    ));
};

return $tests;
