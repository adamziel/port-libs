<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc224 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$encodingId224 = static fn (int|string $encoding): int => match ($encoding) {
    'UTF-8', 1 => 1,
    'UTF-16LE', 2 => 2,
    'UTF-16BE', 3 => 3,
};
$row224 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc224($name, $encoding),
    'text_encoding' => $encodingId224($encoding),
];
$bad224 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];
$escapeBytes224 = static fn (string $text, int|string $encoding): string => $enc224($text, $encoding);

$current224 = [
    $row224(1, 'Plugin_Cache', 'UTF-16LE'),
    $row224(2, 'plugin_cache  ', 'UTF-16BE'),
    $row224(3, 'plugin_cache_alpha', 'UTF-16LE'),
    $row224(4, 'plugin_cache_beta', 'UTF-16BE'),
    $row224(5, 'plugin_cache_delta', 'UTF-8'),
    $row224(6, 'plugin_cache_gamma', 'UTF-16LE'),
    $row224(7, 'plugin%cache', 'UTF-16BE'),
    $row224(8, 'pluginXcache', 'UTF-16LE'),
    $row224(9, "plugin_cache\t", 'UTF-16BE'),
    $bad224(10, "\x00\xd8", 2),
];
$nextTwoTwoFour = [
    $row224(1, 'Plugin_Cache', 'UTF-16BE'),
    $row224(2, 'plugin_cache', 'UTF-16LE'),
    $row224(3, 'plugin_cache_alpha', 'UTF-16BE'),
    $row224(4, 'plugin_cache_beta', 'UTF-16LE'),
    $row224(5, 'plugin_cache_delta', 'UTF-16BE'),
    $row224(6, 'plugin_cache_gamma', 'UTF-16LE'),
    $row224(7, 'plugin%cache', 'UTF-8'),
    $row224(8, 'pluginXcache', 'UTF-16BE'),
    $row224(9, "plugin_cache\t", 'UTF-16BE'),
    $row224(11, 'PLUGIN_CACHE_AARDVARK', 'UTF-16LE'),
    $bad224(12, "x\0y", 2),
];

$plan224 = static fn (
    ?array $current = null,
    ?array $next = null,
    int $pageSize = 3,
    int $lastRowid = 2,
    ?string $lastKey = 'plugin_cache',
    string $currentSource = 'main.wp_options@223',
    string $nextSource = 'main.wp_options@224',
    int $currentSchemaCookie = 223,
    int $nextSchemaCookie = 224,
    ?array $resumeToken = null,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameKeysetResumePlan(
    $current ?? $current224,
    $next ?? $nextTwoTwoFour,
    'plugin!_cache%',
    $escapeBytes224('!', 'UTF-16LE'),
    'UTF-16LE',
    $escapeBytes224('!', 'UTF-16LE'),
    'UTF-16LE',
    $pageSize,
    $lastRowid,
    $lastKey,
    $currentSource,
    $nextSource,
    $currentSchemaCookie,
    $nextSchemaCookie,
    $resumeToken,
);

$valueAt224 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases224 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoTwoFour'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nexttwoZeroEight'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? AND (rtrim(option_name) COLLATE NOCASE, rowid) > (?, ?) ORDER BY rtrim(option_name) COLLATE NOCASE, rowid LIMIT ?'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'current escape' => ['currentEscape', '!'],
    'next escape' => ['nextEscape', '!'],
    'current escape bytes' => ['currentEscapeBytesHex', '2100'],
    'next escape bytes' => ['nextEscapeBytesHex', '2100'],
    'current source' => ['currentSource', 'main.wp_options@223'],
    'next source' => ['nextSource', 'main.wp_options@224'],
    'current cookie' => ['currentSchemaCookie', 223],
    'next cookie' => ['nextSchemaCookie', 224],
    'collation' => ['collation', 'NOCASE'],
    'page size' => ['pageSize', 3],
    'last key' => ['lastKey', 'plugin_cache'],
    'last rowid' => ['lastRowid', 2],
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
    'current before resume' => ['currentRowsAtOrBeforeResume', [1, 2]],
    'next before resume' => ['nextRowsAtOrBeforeResume', [1, 2]],
    'current remaining' => ['currentRemainingRowids', [9, 3, 4, 5, 6]],
    'next remaining' => ['nextRemainingRowids', [9, 11, 3, 4, 5, 6]],
    'current page' => ['currentResumePageRowids', [9, 3, 4]],
    'next page' => ['nextResumePageRowids', [9, 11, 3]],
    'page retained' => ['resumePageRetainedRowids', [9, 3]],
    'page exited' => ['resumePageExitedRowids', [4]],
    'page entered' => ['resumePageEnteredRowids', [11]],
    'current token source' => ['currentResumeToken.source', 'main.wp_options@223'],
    'current token last key' => ['currentResumeToken.lastKey', 'plugin_cache'],
    'current token last rowid' => ['currentResumeToken.lastRowid', 2],
    'current token page size' => ['currentResumeToken.pageSize', 3],
    'current token rowids' => ['currentResumeToken.pageRowids', [9, 3, 4]],
    'current token tail rowid' => ['currentResumeToken.tailRowid', 4],
    'current token tail key' => ['currentResumeToken.tailKey', 'plugin_cache_beta'],
    'next token rowids' => ['nextResumeToken.pageRowids', [9, 11, 3]],
    'prefix unchanged' => ['resumePrefixChanged', false],
    'page changed' => ['resumePageChanged', true],
    'tail changed' => ['resumeTailChanged', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'stale keyset risk' => ['staleKeysetResumeRisk', true],
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
    'resume after residual' => ['keysetResumeAppliedAfterResidual', true],
    'order key' => ['orderUsesRtrimNocaseKeyThenRowid', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-escape-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency resume' => ['dependencies.3', 'sqlite-nocase-keyset-resume'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoTwoFour'],
];

foreach ($cases224 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoTwoFour ' . $name] = static function (TestRunner $t) use ($plan224, $valueAt224, $path, $expected): void {
        $t->same($expected, $valueAt224($plan224(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoTwoFour invalidation reasons include keyset rowsets'] = static function (TestRunner $t) use ($plan224): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'malformed-text',
        'matched-rowset',
        'resume-page-rowset',
        'resume-tail-rowset',
    ], $plan224()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoFour accepts matching current resume token'] = static function (TestRunner $t) use ($plan224): void {
    $first = $plan224();
    $second = $plan224(resumeToken: $first['currentResumeToken']);
    $t->same([9, 3, 4], $second['currentResumeToken']['pageRowids']);
    $t->same([9, 11, 3], $second['nextResumeToken']['pageRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoFour rejects stale resume token'] = static function (TestRunner $t) use ($plan224): void {
    $first = $plan224();
    $token = $first['currentResumeToken'];
    $token['lastRowid'] = 1;
    $t->throws(InvalidArgumentException::class, static fn () => $plan224(resumeToken: $token));
};

$tests['utf16 nocase like rtrim current source nextTwoTwoFour detects inserted row before resume key'] = static function (TestRunner $t) use ($row224, $plan224): void {
    $current = [
        $row224(1, 'plugin_cache', 'UTF-16LE'),
        $row224(2, 'plugin_cache_alpha', 'UTF-16BE'),
        $row224(3, 'plugin_cache_beta', 'UTF-8'),
    ];
    $next = [
        $row224(1, 'plugin_cache', 'UTF-16LE'),
        $row224(2, 'plugin_cache_alpha', 'UTF-16BE'),
        $row224(3, 'plugin_cache_beta', 'UTF-8'),
        $row224(4, 'plugin_cache_000', 'UTF-16LE'),
    ];
    $result = $plan224($current, $next, 2, 2, 'plugin_cache_alpha', 'stable', 'stable', 224, 224);
    $t->same([1, 2], $result['currentRowsAtOrBeforeResume']);
    $t->same([1, 4, 2], $result['nextRowsAtOrBeforeResume']);
    $t->same(true, $result['resumePrefixChanged']);
    $t->same(true, in_array('resume-prefix-rowset', $result['invalidationReasons'], true));
};

$tests['utf16 nocase like rtrim current source nextTwoTwoFour stable source can reuse keyset page'] = static function (TestRunner $t) use ($row224, $plan224): void {
    $rows = [
        $row224(1, 'Plugin_Cache', 'UTF-16LE'),
        $row224(2, 'plugin_cache  ', 'UTF-16BE'),
        $row224(3, "plugin_cache\t", 'UTF-8'),
        $row224(4, 'plugin_cache_alpha', 'UTF-16LE'),
    ];
    $result = $plan224($rows, $rows, 2, 2, 'plugin_cache', 'stable', 'stable', 224, 224);
    $t->same([3, 4], $result['currentResumePageRowids']);
    $t->same([3, 4], $result['nextResumePageRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoFour defaults resume key to first ordered row'] = static function (TestRunner $t) use ($row224): void {
    $rows = [
        $row224(4, 'plugin_cache_beta', 'UTF-16LE'),
        $row224(1, 'Plugin_Cache', 'UTF-16BE'),
        $row224(2, 'plugin_cache_alpha', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameKeysetResumePlan(
        $rows,
        $rows,
        'plugin!_cache%',
        SQLiteEncodingCollationSourceCursor::encodeText('!', 'UTF-16LE'),
        'UTF-16LE',
        SQLiteEncodingCollationSourceCursor::encodeText('!', 'UTF-16LE'),
        'UTF-16LE',
        2,
        0,
        null,
        'stable',
        'stable',
        224,
        224,
    );
    $t->same('plugin_cache', $result['lastKey']);
    $t->same(1, $result['lastRowid']);
    $t->same([2, 4], $result['currentResumePageRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoFour rejects invalid page size'] = static function (TestRunner $t) use ($plan224): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan224(pageSize: 0));
};

return $tests;
