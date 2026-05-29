<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc223 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$encodingId223 = static fn (int|string $encoding): int => match ($encoding) {
    'UTF-8', 1 => 1,
    'UTF-16LE', 2 => 2,
    'UTF-16BE', 3 => 3,
};
$row223 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc223($name, $encoding),
    'text_encoding' => $encodingId223($encoding),
];
$bad223 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];
$escapeBytes223 = static fn (string $text, int|string $encoding): string => $enc223($text, $encoding);

$current223 = [
    $row223(1, 'Plugin_Cache', 'UTF-16LE'),
    $row223(2, 'plugin_cache  ', 'UTF-16BE'),
    $row223(3, 'plugin_cache_alpha', 'UTF-16LE'),
    $row223(4, 'plugin_cache_beta', 'UTF-16BE'),
    $row223(5, 'plugin_cache_delta', 'UTF-8'),
    $row223(6, 'plugin_cache_gamma', 'UTF-16LE'),
    $row223(7, 'plugin_cache_zeta', 'UTF-16BE'),
    $row223(8, "plugin_cache\t", 'UTF-16LE'),
    $row223(9, 'plugin%cache', 'UTF-16BE'),
    $row223(10, 'pluginXcache', 'UTF-16LE'),
    $bad223(11, "\x00\xd8", 2),
];
$nextTwoTwoThree = [
    $row223(1, 'Plugin_Cache', 'UTF-16BE'),
    $row223(2, 'plugin_cache', 'UTF-16LE'),
    $row223(3, 'plugin_cache_alpha', 'UTF-16BE'),
    $row223(4, 'plugin_cache_beta', 'UTF-16LE'),
    $row223(5, 'plugin_cache_delta', 'UTF-16BE'),
    $row223(6, 'plugin_cache_gamma', 'UTF-8'),
    $row223(7, 'plugin_cache_zeta', 'UTF-16LE'),
    $row223(8, "plugin_cache\t", 'UTF-16BE'),
    $row223(12, 'plugin_cache_omega', 'UTF-16BE'),
    $row223(13, 'PLUGIN_CACHE_ZULU', 'UTF-16LE'),
    $row223(9, 'plugin%cache', 'UTF-8'),
    $bad223(14, "x\0y", 2),
];

$plan223 = static fn (
    ?array $current = null,
    ?array $next = null,
    int $limit = 3,
    int $offset = 1,
    string $currentSource = 'main.wp_options@222',
    string $nextSource = 'main.wp_options@223',
    int $currentSchemaCookie = 222,
    int $nextSchemaCookie = 223,
    ?array $cursor = null,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameDescYieldPagePlan(
    $current ?? $current223,
    $next ?? $nextTwoTwoThree,
    'plugin!_cache%',
    $escapeBytes223('!', 'UTF-16LE'),
    'UTF-16LE',
    $escapeBytes223('!', 'UTF-16LE'),
    'UTF-16LE',
    $limit,
    $offset,
    $currentSource,
    $nextSource,
    $currentSchemaCookie,
    $nextSchemaCookie,
    $cursor,
);

$valueAt223 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases223 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoTwoThree'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nexttwoOneEight'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? ORDER BY rtrim(option_name) COLLATE NOCASE DESC, rowid DESC LIMIT ? OFFSET ?'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'current escape' => ['currentEscape', '!'],
    'next escape' => ['nextEscape', '!'],
    'current escape bytes' => ['currentEscapeBytesHex', '2100'],
    'next escape bytes' => ['nextEscapeBytesHex', '2100'],
    'current source' => ['currentSource', 'main.wp_options@222'],
    'next source' => ['nextSource', 'main.wp_options@223'],
    'current cookie' => ['currentSchemaCookie', 222],
    'next cookie' => ['nextSchemaCookie', 223],
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
    'current matched' => ['currentMatchedRowids', [1, 2, 8, 3, 4, 5, 6, 7]],
    'next matched' => ['nextMatchedRowids', [1, 2, 8, 3, 4, 5, 6, 12, 7, 13]],
    'current desc ordered' => ['currentDescOrderedRowids', [7, 6, 5, 4, 3, 8, 2, 1]],
    'next desc ordered' => ['nextDescOrderedRowids', [13, 7, 12, 6, 5, 4, 3, 8, 2, 1]],
    'current before' => ['currentBeforeWindowRowids', [7]],
    'next before' => ['nextBeforeWindowRowids', [13]],
    'current page' => ['currentPageRowids', [6, 5, 4]],
    'next page' => ['nextPageRowids', [7, 12, 6]],
    'current after' => ['currentAfterWindowRowids', [3, 8, 2, 1]],
    'next after' => ['nextAfterWindowRowids', [5, 4, 3, 8, 2, 1]],
    'page retained' => ['pageRetainedRowids', [6]],
    'page exited' => ['pageExitedRowids', [4, 5]],
    'page entered' => ['pageEnteredRowids', [7, 12]],
    'before changed' => ['rowsBeforeWindowChanged', true],
    'page changed' => ['limitWindowChanged', true],
    'after changed' => ['rowsAfterWindowChanged', true],
    'current head rowid' => ['currentPageHead.rowid', 6],
    'next head rowid' => ['nextPageHead.rowid', 7],
    'current tail rowid' => ['currentPageTail.rowid', 4],
    'next tail rowid' => ['nextPageTail.rowid', 6],
    'current token source' => ['currentPageToken.source', 'main.wp_options@222'],
    'current token order' => ['currentPageToken.order', 'DESC'],
    'current token rowids' => ['currentPageToken.pageRowids', [6, 5, 4]],
    'current token head' => ['currentPageToken.headRowid', 6],
    'current token tail' => ['currentPageToken.tailRowid', 4],
    'next token rowids' => ['nextPageToken.pageRowids', [7, 12, 6]],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'stale desc risk' => ['staleDescYieldPageRisk', true],
    'current malformed' => ['currentMalformedRowids', [11]],
    'next malformed' => ['nextMalformedRowids', [14]],
    'current error' => ['currentErrors.11', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.14', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'rtrim row two' => ['currentRtrimTexts.2', 'plugin_cache'],
    'tab preserved' => ['currentRtrimTexts.8', "plugin_cache\t"],
    'next nocase zulu' => ['nextNocaseKeys.13', 'plugin_cache_zulu'],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'escape decoded before range' => ['escapeDecodedBeforeRangePlanning', true],
    'desc limit after residual' => ['descLimitWindowAppliedAfterResidual', true],
    'desc order key' => ['descOrderUsesRtrimNocaseKeyThenRowid', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-escape-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency desc limit' => ['dependencies.3', 'sqlite-nocase-desc-limit-yield-window'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoTwoThree'],
];

foreach ($cases223 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoTwoThree ' . $name] = static function (TestRunner $t) use ($plan223, $valueAt223, $path, $expected): void {
        $t->same($expected, $valueAt223($plan223(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoTwoThree invalidation reasons include desc window'] = static function (TestRunner $t) use ($plan223): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'malformed-text',
        'matched-rowset',
        'desc-rows-before-limit-window',
        'desc-limit-window-rowset',
        'desc-rows-after-limit-window',
    ], $plan223()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoThree accepts matching desc page cursor'] = static function (TestRunner $t) use ($plan223): void {
    $first = $plan223();
    $second = $plan223(cursor: $first['currentPageToken']);
    $t->same([6, 5, 4], $second['currentPageToken']['pageRowids']);
    $t->same([7, 12, 6], $second['nextPageToken']['pageRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoThree rejects stale desc page cursor'] = static function (TestRunner $t) use ($plan223): void {
    $first = $plan223();
    $cursor = $first['currentPageToken'];
    $cursor['order'] = 'ASC';
    $t->throws(InvalidArgumentException::class, static fn () => $plan223(cursor: $cursor));
};

$tests['utf16 nocase like rtrim current source nextTwoTwoThree stable source can reuse desc page'] = static function (TestRunner $t) use ($row223, $plan223): void {
    $rows = [
        $row223(1, 'Plugin_Cache', 'UTF-16LE'),
        $row223(2, 'plugin_cache  ', 'UTF-16BE'),
        $row223(3, 'plugin_cache_alpha', 'UTF-8'),
        $row223(4, 'plugin_cache_beta', 'UTF-16LE'),
    ];
    $result = $plan223($rows, $rows, 2, 1, 'stable', 'stable', 223, 223);
    $t->same([3, 2], $result['currentPageRowids']);
    $t->same([3, 2], $result['nextPageRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoThree rows before desc window force restart'] = static function (TestRunner $t) use ($row223, $plan223): void {
    $current = [
        $row223(1, 'plugin_cache_alpha', 'UTF-16LE'),
        $row223(2, 'plugin_cache_beta', 'UTF-16BE'),
        $row223(3, 'plugin_cache_gamma', 'UTF-8'),
    ];
    $next = [
        $row223(1, 'plugin_cache_alpha', 'UTF-16LE'),
        $row223(2, 'plugin_cache_beta', 'UTF-16BE'),
        $row223(3, 'plugin_cache_gamma', 'UTF-8'),
        $row223(4, 'plugin_cache_zulu', 'UTF-16LE'),
    ];
    $result = $plan223($current, $next, 2, 1, 'stable', 'stable', 223, 223);
    $t->same([3], $result['currentBeforeWindowRowids']);
    $t->same([4], $result['nextBeforeWindowRowids']);
    $t->same(true, $result['rowsBeforeWindowChanged']);
    $t->same(true, in_array('desc-rows-before-limit-window', $result['invalidationReasons'], true));
};

$tests['utf16 nocase like rtrim current source nextTwoTwoThree rejects invalid limit and offset'] = static function (TestRunner $t) use ($plan223): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan223(limit: 0));
    $t->throws(InvalidArgumentException::class, static fn () => $plan223(offset: -1));
};

return $tests;
