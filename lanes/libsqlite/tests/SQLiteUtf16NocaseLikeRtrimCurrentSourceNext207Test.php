<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc207 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row207 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc207($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad207 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$rows207 = [
    $row207(1, 'Plugin_Cache', 'UTF-16LE'),
    $row207(2, 'plugin_cache  ', 'UTF-16BE'),
    $row207(3, 'plugin_cache   ', 'UTF-8'),
    $row207(4, 'plugin_cache_extra', 'UTF-16LE'),
    $row207(5, 'plugin_cache_extra  ', 'UTF-16BE'),
    $row207(6, 'plugin-case', 'UTF-16LE'),
    $row207(7, 'plugin_cachex', 'UTF-8'),
    $bad207(8, "\x00\xd8", 2),
];
$nextRows207 = [
    $row207(1, 'Plugin_Cache', 'UTF-16BE'),
    $row207(2, 'plugin_cache  ', 'UTF-16LE'),
    $row207(3, 'plugin_cache   ', 'UTF-8'),
    $row207(4, 'plugin_cache_extra', 'UTF-16BE'),
    $row207(5, 'plugin_cache_extra  ', 'UTF-16LE'),
    $row207(6, 'plugin-case', 'UTF-16LE'),
    $row207(7, 'plugin_cachex', 'UTF-8'),
    $bad207(8, "\x00\xd8", 2),
    $row207(9, 'PLUGIN_CACHE_NEW  ', 'UTF-16BE'),
];

$plan207 = static fn (
    ?array $current = null,
    ?array $next = null,
    bool $currentRtrim = true,
    bool $nextRtrim = false,
    string $source = 'main.wp_options@206',
    string $nextSource = 'main.wp_options@207',
    int $cookie = 206,
    int $nextCookie = 207,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyRtrimCollationRebindPlan(
    $current ?? $rows207,
    $next ?? $nextRows207,
    'plugin!_cache%',
    '!',
    $currentRtrim,
    $nextRtrim,
    $source,
    $nextSource,
    $cookie,
    $nextCookie,
);

$valueAt207 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases207 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoZeroSeven'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* rtrim collation rebind */'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'current uses rtrim' => ['currentUsesRtrim', true],
    'next skips rtrim' => ['nextUsesRtrim', false],
    'current source' => ['currentSource', 'main.wp_options@206'],
    'next source' => ['nextSource', 'main.wp_options@207'],
    'current cookie' => ['currentSchemaCookie', 206],
    'next cookie' => ['nextSchemaCookie', 207],
    'current prefix' => ['currentPrefix', 'plugin_cache'],
    'next prefix' => ['nextPrefix', 'plugin_cache'],
    'current lower' => ['currentRangeLowerInclusive', 'plugin_cache'],
    'current upper' => ['currentRangeUpperBound', 'plugin_cachf'],
    'next lower' => ['nextRangeLowerInclusive', 'plugin_cache'],
    'next upper' => ['nextRangeUpperBound', 'plugin_cachf'],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 4, 5, 7]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 3, 4, 5, 9, 7]],
    'current matched' => ['currentMatchedRowids', [1, 2, 3, 4, 5, 7]],
    'next matched' => ['nextMatchedRowids', [1, 2, 3, 4, 5, 9, 7]],
    'current matched no rtrim' => ['currentMatchedWithNextRtrimRowids', [1, 2, 3, 4, 5, 7]],
    'next matched rtrim' => ['nextMatchedWithCurrentRtrimRowids', [1, 2, 3, 4, 5, 9, 7]],
    'rtrim residual flip' => ['rtrimResidualFlipRowids', []],
    'next rtrim residual flip' => ['nextRtrimResidualFlipRowids', []],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [9]],
    'false positives current' => ['currentFalsePositiveRowids', []],
    'false positives next' => ['nextFalsePositiveRowids', []],
    'decoded current' => ['currentDecodedRowids', [6, 1, 2, 3, 4, 5, 7]],
    'decoded next' => ['nextDecodedRowids', [6, 1, 2, 3, 4, 5, 9, 7]],
    'malformed current' => ['currentMalformedRowids', [8]],
    'malformed next' => ['nextMalformedRowids', [8]],
    'error current' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'error next' => ['nextErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current text retained' => ['currentTexts.2', 'plugin_cache  '],
    'current probe rtrimmed' => ['currentProbeTexts.2', 'plugin_cache'],
    'next probe untrimmed' => ['nextProbeTexts.2', 'plugin_cache  '],
    'current folded key' => ['currentNocaseKeys.1', 'plugin_cache'],
    'next folded key spaces' => ['nextNocaseKeys.9', 'plugin_cache_new  '],
    'rtrim changed' => ['rtrimChanged', true],
    'prefix stable' => ['prefixChangedByRtrim', false],
    'range stable' => ['rangeChangedByRtrim', false],
    'residual stable' => ['residualChangedByRtrim', false],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'must reprepare' => ['mustReprepareForRtrimRebind', true],
    'stale risk' => ['staleRangeCursorRisk', false],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'rtrim checked before range reuse' => ['rtrimRebindCheckedBeforeRangeReuse', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoZeroSeven'],
];

foreach ($cases207 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoZeroSeven ' . $name] = static function (TestRunner $t) use ($plan207, $valueAt207, $path, $expected): void {
        $t->same($expected, $valueAt207($plan207(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoZeroSeven invalidation reasons include rtrim rebind'] = static function (TestRunner $t) use ($plan207): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'rtrim-collation-rebound',
        'malformed-text',
        'matched-rowset',
    ], $plan207()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroSeven stable rtrim cursor is reusable'] = static function (TestRunner $t) use ($row207): void {
    $rows = [
        $row207(1, 'Plugin_Cache', 'UTF-16LE'),
        $row207(2, 'plugin_cache  ', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyRtrimCollationRebindPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        true,
        true,
        'stable',
        'stable',
        207,
        207,
    );

    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same(false, $result['rtrimChanged']);
    $t->same(false, $result['cursorInvalidated']);
    $t->same(true, $result['cursorReusable']);
    $t->same([], $result['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroSeven detects exact-pattern trailing-space residual flip'] = static function (TestRunner $t) use ($row207): void {
    $rows = [
        $row207(1, 'plugin_cache', 'UTF-16LE'),
        $row207(2, 'plugin_cache  ', 'UTF-16BE'),
        $row207(3, 'Plugin_Cache   ', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyRtrimCollationRebindPlan(
        $rows,
        $rows,
        'plugin!_cache',
        '!',
        true,
        false,
        'stable',
        'stable',
        207,
        207,
    );

    $t->same([1, 2, 3], $result['currentMatchedRowids']);
    $t->same([1], $result['nextMatchedRowids']);
    $t->same([2, 3], $result['rtrimResidualFlipRowids']);
    $t->same([2, 3], $result['nextRtrimResidualFlipRowids']);
    $t->same(true, $result['residualChangedByRtrim']);
    $t->same(true, $result['staleRangeCursorRisk']);
    $t->same(['rtrim-collation-rebound', 'rtrim-residual-rowset', 'matched-rowset'], $result['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroSeven rejects missing option id'] = static function (TestRunner $t) use ($nextRows207): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyRtrimCollationRebindPlan([
        ['option_name_bytes' => 'plugin_cache', 'text_encoding' => 1],
    ], $nextRows207));
};

$tests['utf16 nocase like rtrim current source nextTwoZeroSeven rejects non integer encoding'] = static function (TestRunner $t) use ($rows207): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyRtrimCollationRebindPlan($rows207, [
        ['option_id' => 1, 'option_name_bytes' => 'plugin_cache', 'text_encoding' => 'UTF-8'],
    ]));
};

return $tests;
