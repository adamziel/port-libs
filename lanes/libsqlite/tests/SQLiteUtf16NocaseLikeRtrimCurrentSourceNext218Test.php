<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc218 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$encodingId218 = static fn (int|string $encoding): int => match ($encoding) {
    'UTF-8', 1 => 1,
    'UTF-16LE', 2 => 2,
    'UTF-16BE', 3 => 3,
};
$row218 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc218($name, $encoding),
    'text_encoding' => $encodingId218($encoding),
];
$bad218 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];
$escapeBytes218 = static fn (string $text, int|string $encoding, bool $bom = false): string => ($bom ? match ($encoding) {
    'UTF-16LE', 2 => "\xff\xfe",
    'UTF-16BE', 3 => "\xfe\xff",
    default => "\xef\xbb\xbf",
} : '') . $enc218($text, $encoding);

$current218 = [
    $row218(1, 'Plugin_Cache', 'UTF-16LE'),
    $row218(2, 'plugin_cache  ', 'UTF-16BE'),
    $row218(3, 'plugin_cache_alpha', 'UTF-16LE'),
    $row218(4, 'plugin_cache_beta', 'UTF-16BE'),
    $row218(5, 'plugin_cache_delta', 'UTF-8'),
    $row218(6, 'plugin_cache_gamma', 'UTF-16LE'),
    $row218(7, 'plugin%cache', 'UTF-16BE'),
    $row218(8, 'pluginXcache', 'UTF-16LE'),
    $row218(9, "plugin_cache\t", 'UTF-16BE'),
    $bad218(10, "\x00\xd8", 2),
];
$next218 = [
    $row218(1, 'Plugin_Cache', 'UTF-16BE'),
    $row218(2, 'plugin_cache', 'UTF-16LE'),
    $row218(3, 'plugin_cache_alpha', 'UTF-16BE'),
    $row218(4, 'plugin_cache_beta', 'UTF-16LE'),
    $row218(5, 'plugin_cache_delta', 'UTF-16BE'),
    $row218(6, 'plugin_cache_gamma', 'UTF-16LE'),
    $row218(7, 'plugin%cache', 'UTF-8'),
    $row218(8, 'pluginXcache', 'UTF-16BE'),
    $row218(9, "plugin_cache\t", 'UTF-16BE'),
    $row218(11, 'PLUGIN_CACHE_AARDVARK', 'UTF-16LE'),
    $bad218(12, "x\0y", 2),
];

$plan218 = static fn (
    ?array $current = null,
    ?array $next = null,
    int $limit = 3,
    int $offset = 1,
    string $currentSource = 'main.wp_options@217',
    string $nextSource = 'main.wp_options@218',
    int $currentSchemaCookie = 217,
    int $nextSchemaCookie = 218,
    ?array $cursor = null,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameYieldPagePlan(
    $current ?? $current218,
    $next ?? $next218,
    'plugin!_cache%',
    $escapeBytes218('!', 'UTF-16LE'),
    'UTF-16LE',
    $escapeBytes218('!', 'UTF-16LE'),
    'UTF-16LE',
    $limit,
    $offset,
    $currentSource,
    $nextSource,
    $currentSchemaCookie,
    $nextSchemaCookie,
    $cursor,
);

$valueAt218 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases218 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-next218'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-next208'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? ORDER BY rtrim(option_name) COLLATE NOCASE, rowid LIMIT ? OFFSET ?'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'current escape' => ['currentEscape', '!'],
    'next escape' => ['nextEscape', '!'],
    'escape bytes stable' => ['currentEscapeBytesHex', '2100'],
    'next escape bytes stable' => ['nextEscapeBytesHex', '2100'],
    'current source' => ['currentSource', 'main.wp_options@217'],
    'next source' => ['nextSource', 'main.wp_options@218'],
    'current cookie' => ['currentSchemaCookie', 217],
    'next cookie' => ['nextSchemaCookie', 218],
    'collation' => ['collation', 'NOCASE'],
    'limit' => ['limit', 3],
    'offset' => ['offset', 1],
    'current prefix' => ['currentPrefix', 'plugin_cache'],
    'next prefix' => ['nextPrefix', 'plugin_cache'],
    'current lower' => ['currentRangeLowerInclusive', 'plugin_cache'],
    'next lower' => ['nextRangeLowerInclusive', 'plugin_cache'],
    'current upper' => ['currentRangeUpperBound', 'plugin_cachf'],
    'next upper' => ['nextRangeUpperBound', 'plugin_cachf'],
    'current index usable' => ['currentIndexUsable', true],
    'next index usable' => ['nextIndexUsable', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 9, 3, 4, 5, 6]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 9, 11, 3, 4, 5, 6]],
    'current matched' => ['currentMatchedRowids', [1, 2, 9, 3, 4, 5, 6]],
    'next matched' => ['nextMatchedRowids', [1, 2, 9, 11, 3, 4, 5, 6]],
    'current ordered' => ['currentOrderedRowids', [1, 2, 9, 3, 4, 5, 6]],
    'next ordered' => ['nextOrderedRowids', [1, 2, 9, 11, 3, 4, 5, 6]],
    'current before' => ['currentBeforeWindowRowids', [1]],
    'next before' => ['nextBeforeWindowRowids', [1]],
    'current page' => ['currentPageRowids', [2, 9, 3]],
    'next page' => ['nextPageRowids', [2, 9, 11]],
    'current after' => ['currentAfterWindowRowids', [4, 5, 6]],
    'next after' => ['nextAfterWindowRowids', [3, 4, 5, 6]],
    'page retained' => ['pageRetainedRowids', [2, 9]],
    'page exited' => ['pageExitedRowids', [3]],
    'page entered' => ['pageEnteredRowids', [11]],
    'before unchanged' => ['rowsBeforeWindowChanged', false],
    'page changed' => ['limitWindowChanged', true],
    'after changed' => ['rowsAfterWindowChanged', true],
    'current page tail rowid' => ['currentPageTail.rowid', 3],
    'next page tail rowid' => ['nextPageTail.rowid', 11],
    'current token source' => ['currentPageToken.source', 'main.wp_options@217'],
    'current token rowids' => ['currentPageToken.pageRowids', [2, 9, 3]],
    'current token tail rowid' => ['currentPageToken.tailRowid', 3],
    'current token tail key' => ['currentPageToken.tailKey', 'plugin_cache_alpha'],
    'next token rowids' => ['nextPageToken.pageRowids', [2, 9, 11]],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'stale page risk' => ['staleYieldPageRisk', true],
    'current malformed' => ['currentMalformedRowids', [10]],
    'next malformed' => ['nextMalformedRowids', [12]],
    'current error' => ['currentErrors.10', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.12', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'rtrim row two' => ['currentRtrimTexts.2', 'plugin_cache'],
    'tab not trimmed' => ['currentRtrimTexts.9', "plugin_cache\t"],
    'next nocase new row' => ['nextNocaseKeys.11', 'plugin_cache_aardvark'],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'escape decoded before range' => ['escapeDecodedBeforeRangePlanning', true],
    'limit after residual' => ['limitWindowAppliedAfterResidual', true],
    'order key' => ['orderUsesRtrimNocaseKeyThenRowid', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-escape-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency limit' => ['dependencies.3', 'sqlite-nocase-limit-yield-window'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-next218'],
];

foreach ($cases218 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source next218 ' . $name] = static function (TestRunner $t) use ($plan218, $valueAt218, $path, $expected): void {
        $t->same($expected, $valueAt218($plan218(), $path));
    };
}

$tests['utf16 nocase like rtrim current source next218 invalidation reasons include limit window'] = static function (TestRunner $t) use ($plan218): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'malformed-text',
        'matched-rowset',
        'limit-window-rowset',
        'rows-after-limit-window',
    ], $plan218()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source next218 accepts matching current page cursor'] = static function (TestRunner $t) use ($plan218): void {
    $first = $plan218();
    $second = $plan218(cursor: $first['currentPageToken']);
    $t->same([2, 9, 3], $second['currentPageToken']['pageRowids']);
    $t->same([2, 9, 11], $second['nextPageToken']['pageRowids']);
};

$tests['utf16 nocase like rtrim current source next218 rejects stale page cursor'] = static function (TestRunner $t) use ($plan218): void {
    $first = $plan218();
    $cursor = $first['currentPageToken'];
    $cursor['tailRowid'] = 9;
    $t->throws(InvalidArgumentException::class, static fn () => $plan218(cursor: $cursor));
};

$tests['utf16 nocase like rtrim current source next218 stable source can reuse page'] = static function (TestRunner $t) use ($row218, $plan218): void {
    $rows = [
        $row218(1, 'Plugin_Cache', 'UTF-16LE'),
        $row218(2, 'plugin_cache  ', 'UTF-16BE'),
        $row218(3, "plugin_cache\t", 'UTF-8'),
        $row218(4, 'plugin_cache_alpha', 'UTF-16LE'),
    ];
    $result = $plan218($rows, $rows, 2, 1, 'stable', 'stable', 218, 218);
    $t->same([2, 3], $result['currentPageRowids']);
    $t->same([2, 3], $result['nextPageRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source next218 rows before window force restart'] = static function (TestRunner $t) use ($row218, $plan218): void {
    $current = [
        $row218(1, 'plugin_cache_alpha', 'UTF-16LE'),
        $row218(2, 'plugin_cache_beta', 'UTF-16BE'),
        $row218(3, 'plugin_cache_gamma', 'UTF-8'),
    ];
    $next = [
        $row218(1, 'plugin_cache_alpha', 'UTF-16LE'),
        $row218(2, 'plugin_cache_beta', 'UTF-16BE'),
        $row218(3, 'plugin_cache_gamma', 'UTF-8'),
        $row218(4, 'plugin_cache_aardvark', 'UTF-16LE'),
    ];
    $result = $plan218($current, $next, 2, 1, 'stable', 'stable', 218, 218);
    $t->same([1], $result['currentBeforeWindowRowids']);
    $t->same([4], $result['nextBeforeWindowRowids']);
    $t->same(true, $result['rowsBeforeWindowChanged']);
    $t->same(true, in_array('rows-before-limit-window', $result['invalidationReasons'], true));
};

$tests['utf16 nocase like rtrim current source next218 rejects invalid limit and offset'] = static function (TestRunner $t) use ($plan218): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan218(limit: 0));
    $t->throws(InvalidArgumentException::class, static fn () => $plan218(offset: -1));
};

return $tests;
