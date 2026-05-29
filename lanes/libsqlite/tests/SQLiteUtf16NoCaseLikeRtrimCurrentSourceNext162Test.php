<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$code = static fn (string $encoding): int => match ($encoding) {
    'UTF-8' => 1,
    'UTF-16LE' => 2,
    'UTF-16BE' => 3,
};
$row = static function (int $id, string $name, string $encoding) use ($enc, $code): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => $enc($name, $encoding),
        'text_encoding' => $code($encoding),
    ];
};
$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$stableRows = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', 'UTF-8'),
    $row(4, 'plugin-cache', 'UTF-16LE'),
];
$changedRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', 'UTF-8'),
    $row(5, 'PLUGIN_CACHE_NEW', 'UTF-16LE'),
    $bad(9, "\xd8\x00", 3),
];

$plan = static function (
    ?array $currentRows = null,
    ?array $nextRows = null,
    string $currentPattern = 'plugin\\_cache%',
    string $currentPatternEncoding = 'UTF-16LE',
    string $nextPattern = 'plugin\\_cache%',
    string $nextPatternEncoding = 'UTF-16BE',
    ?string $currentEscape = '\\',
    string $currentEscapeEncoding = 'UTF-16LE',
    ?string $nextEscape = '\\',
    string $nextEscapeEncoding = 'UTF-16BE',
    string $currentSource = 'stable',
    string $nextSource = 'stable',
    int $currentSchemaCookie = 7,
    int $nextSchemaCookie = 7,
) use ($stableRows, $enc, $code): array {
    return SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameNormalizedPatternPlan(
        $currentRows ?? $stableRows,
        $nextRows ?? $stableRows,
        $enc($currentPattern, $currentPatternEncoding),
        $code($currentPatternEncoding),
        $enc($nextPattern, $nextPatternEncoding),
        $code($nextPatternEncoding),
        $currentEscape === null ? null : $enc($currentEscape, $currentEscapeEncoding),
        $code($currentEscapeEncoding),
        $nextEscape === null ? null : $enc($nextEscape, $nextEscapeEncoding),
        $code($nextEscapeEncoding),
        $currentSource,
        $nextSource,
        $currentSchemaCookie,
        $nextSchemaCookie,
    );
};

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneSixTwo'],
    'operator' => ['operator', 'LIKE'],
    'index collation' => ['indexCollation', 'RTRIM'],
    'residual collation' => ['residualCollation', 'NOCASE'],
    'case insensitive like' => ['caseSensitiveLike', false],
    'normalizes prepared pattern bytes' => ['normalizesPreparedPatternBytes', true],
    'pattern bytes not semantic' => ['rawPatternByteChangeIsSemantic', false],
    'escape bytes not semantic' => ['rawEscapeByteChangeIsSemantic', false],
    'current source stable' => ['currentSource', 'stable'],
    'next source stable' => ['nextSource', 'stable'],
    'current schema cookie stable' => ['currentSchemaCookie', 7],
    'next schema cookie stable' => ['nextSchemaCookie', 7],
    'current pattern decoded' => ['currentPattern', 'plugin\\_cache%'],
    'next pattern decoded' => ['nextPattern', 'plugin\\_cache%'],
    'same decoded pattern' => ['sameDecodedPattern', true],
    'current pattern encoding' => ['currentPatternEncoding', 'UTF-16LE'],
    'next pattern encoding' => ['nextPatternEncoding', 'UTF-16BE'],
    'current pattern bytes' => ['currentPatternBytesHex', '70006c007500670069006e005c005f00630061006300680065002500'],
    'next pattern bytes' => ['nextPatternBytesHex', '0070006c007500670069006e005c005f006300610063006800650025'],
    'current escape' => ['currentEscape', '\\'],
    'next escape' => ['nextEscape', '\\'],
    'same decoded escape' => ['sameDecodedEscape', true],
    'current escape bytes' => ['currentEscapeBytesHex', '5c00'],
    'next escape bytes' => ['nextEscapeBytesHex', '005c'],
    'current prefix' => ['currentPrefix', 'plugin_cache'],
    'next prefix' => ['nextPrefix', 'plugin_cache'],
    'current range lower' => ['currentRtrimRange.lowerInclusive', 'plugin_cache'],
    'next range upper' => ['nextRtrimRange.upperBound', 'plugin_cachf'],
    'current index usable' => ['currentIndexUsable', true],
    'next index usable' => ['nextIndexUsable', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 3]],
    'current matches' => ['currentMatchedRowids', [1, 2, 3]],
    'next matches' => ['nextMatchedRowids', [1, 2, 3]],
    'current rtrim false positives' => ['currentFalsePositiveRowids', []],
    'next rtrim false positives' => ['nextFalsePositiveRowids', []],
    'retained matches' => ['retainedMatchedRowids', [1, 2, 3]],
    'entered matches' => ['enteredMatchedRowids', []],
    'exited matches' => ['exitedMatchedRowids', []],
    'byte reasons' => ['byteReprepareReasons', ['pattern-encoding', 'pattern-bytes', 'escape-bytes']],
    'semantic reasons empty' => ['semanticInvalidationReasons', []],
    'byte only reprepare' => ['byteOnlyReprepare', true],
    'cursor not invalidated semantically' => ['cursorInvalidated', false],
    'cursor reusable' => ['cursorReusable', true],
    'base reason encoding' => ['baseInvalidationReasons.0', 'pattern-encoding'],
    'base reason bytes' => ['baseInvalidationReasons.1', 'pattern-bytes'],
    'base reason escape bytes' => ['baseInvalidationReasons.2', 'escape-bytes'],
    'dependency normalization' => ['dependencies.0', 'sqlite-utf16-pattern-normalization'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-nextoneSixTwo'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneSixTwo ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneSixTwo source change remains semantic'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(null, null, currentSource: 'main.wp_options@161', nextSource: 'main.wp_options@162');
    $t->same(['pattern-encoding', 'pattern-bytes', 'escape-bytes'], $result['byteReprepareReasons']);
    $t->same(['source-name'], $result['semanticInvalidationReasons']);
    $t->same(true, $result['cursorInvalidated']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneSixTwo schema cookie change remains semantic'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(null, null, currentSchemaCookie: 161, nextSchemaCookie: 162);
    $t->same(['schema-cookie'], $result['semanticInvalidationReasons']);
    $t->same(true, $result['cursorInvalidated']);
};

$tests['utf16 nocase like rtrim current source nextOneSixTwo decoded pattern text change remains semantic'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(nextPattern: 'plugin\\_cache');
    $t->same(false, $result['sameDecodedPattern']);
    $t->same(['pattern-encoding', 'pattern-bytes', 'escape-bytes'], $result['byteReprepareReasons']);
    $t->same(['pattern-text', 'matched-rowset', 'rtrim-false-positive-rowset'], $result['semanticInvalidationReasons']);
    $t->same([1], $result['nextMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSixTwo decoded escape text change remains semantic'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(currentPattern: 'plugin!_cache%', currentEscape: '!', nextPattern: 'plugin\\_cache%', nextEscape: '\\');
    $t->same(false, $result['sameDecodedEscape']);
    $t->same(['pattern-encoding', 'pattern-bytes', 'escape-bytes'], $result['byteReprepareReasons']);
    $t->same(['pattern-text', 'escape-text'], array_slice($result['semanticInvalidationReasons'], 0, 2));
    $t->same(true, $result['cursorInvalidated']);
};

$tests['utf16 nocase like rtrim current source nextOneSixTwo rowset changes remain semantic'] = static function (TestRunner $t) use ($plan, $stableRows, $changedRows): void {
    $result = $plan($stableRows, $changedRows);
    $t->same(['pattern-encoding', 'pattern-bytes', 'escape-bytes'], $result['byteReprepareReasons']);
    $t->same(['candidate-rowset', 'matched-rowset', 'malformed-text'], $result['semanticInvalidationReasons']);
    $t->same([5, 1, 2, 3], $result['nextCandidateRowids']);
    $t->same([5, 1, 2, 3], $result['nextMatchedRowids']);
    $t->same([9], $result['nextMalformedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSixTwo ascii nocase does not fold unicode'] = static function (TestRunner $t) use ($plan, $row): void {
    $rows = [
        $row(1, 'plugin_Éclair', 'UTF-16LE'),
        $row(2, 'plugin_éclair', 'UTF-16BE'),
    ];
    $result = $plan($rows, $rows, 'plugin\\_éclair%', 'UTF-16LE', 'plugin\\_éclair%', 'UTF-16BE');
    $t->same([2], $result['currentMatchedRowids']);
    $t->same([2], $result['nextMatchedRowids']);
    $t->same([2], $result['currentCandidateRowids']);
    $t->same([], $result['currentFalsePositiveRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSixTwo no escape keeps wildcard underscore'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(currentPattern: 'plugin_cache%', nextPattern: 'plugin_cache%', currentEscape: null, nextEscape: null);
    $t->same(null, $result['currentEscape']);
    $t->same(null, $result['nextEscape']);
    $t->same([], $result['semanticInvalidationReasons']);
    $t->same([1, 4, 2, 3], $result['currentMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSixTwo malformed pattern bytes throw before planning'] = static function (TestRunner $t) use ($stableRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameNormalizedPatternPlan(
        $stableRows,
        $stableRows,
        "p\0x",
        2,
        "p\0%",
        2,
    ));
};

$tests['utf16 nocase like rtrim current source nextOneSixTwo multi-character escape bytes throw before planning'] = static function (TestRunner $t) use ($stableRows, $enc): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameNormalizedPatternPlan(
        $stableRows,
        $stableRows,
        $enc('plugin%', 'UTF-8'),
        1,
        $enc('plugin%', 'UTF-8'),
        1,
        $enc('xx', 'UTF-8'),
        1,
    ));
};

return $tests;
