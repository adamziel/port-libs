<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc193 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row193 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc193($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad193 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current193 = [
    $row193(1, 'Plugin_Cache', 'UTF-16LE'),
    $row193(2, 'plugin_cache  ', 'UTF-16BE'),
    $row193(3, 'plugin_cache_alpha', 'UTF-16LE'),
    $row193(4, 'plugin_cache_beta', 'UTF-16BE'),
    $row193(5, 'plugin_cache_delta', 'UTF-8'),
    $row193(6, 'plugin_cache_zeta', 'UTF-16LE'),
    $row193(7, 'plugin_config', 'UTF-16BE'),
    $bad193(8, "\x00\xd8", 2),
];
$nextOneNineThree = [
    $row193(1, 'Plugin_Cache ', 'UTF-16BE'),
    $row193(2, 'plugin_cache   ', 'UTF-16LE'),
    $row193(9, 'plugin_cache_aardvark', 'UTF-16LE'),
    $row193(3, 'plugin_cache_alpha', 'UTF-16BE'),
    $row193(4, 'plugin_cache_beta  ', 'UTF-16LE'),
    $row193(10, 'plugin_cache_charlie', 'UTF-8'),
    $row193(5, 'plugin_cache_other', 'UTF-8'),
    $row193(6, 'plugin_cache_zeta', 'UTF-16BE'),
    $bad193(11, "x\0y", 2),
];

$plan193 = static fn (
    ?array $current = null,
    ?array $next = null,
    int $limit = 3,
    int $offset = 2,
    string $currentSource = 'main.wp_options@192',
    string $nextSource = 'main.wp_options@193',
    int $currentCookie = 192,
    int $nextCookie = 193,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameLimitOffsetPlan(
    $current ?? $current193,
    $next ?? $nextOneNineThree,
    'plugin!_cache%',
    '!',
    $limit,
    $offset,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt193 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases193 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneNineThree'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? LIMIT ? OFFSET ?'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'limit' => ['limit', 3],
    'offset' => ['offset', 2],
    'collation' => ['collation', 'NOCASE'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneEightThree'],
    'current source' => ['currentSource', 'main.wp_options@192'],
    'next source' => ['nextSource', 'main.wp_options@193'],
    'current cookie' => ['currentSchemaCookie', 192],
    'next cookie' => ['nextSchemaCookie', 193],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['rangeLowerInclusive', 'plugin_cache'],
    'range upper' => ['rangeUpperBound', 'plugin_cachf'],
    'prefix cursor' => ['usesPrefixRangeCursor', true],
    'current ordered' => ['currentOrderedMatchedRowids', [1, 2, 3, 4, 5, 6]],
    'next ordered' => ['nextOrderedMatchedRowids', [1, 2, 9, 3, 4, 10, 5, 6]],
    'current skipped' => ['currentSkippedRowids', [1, 2]],
    'next skipped' => ['nextSkippedRowids', [1, 2]],
    'current window' => ['currentLimitWindowRowids', [3, 4, 5]],
    'next window' => ['nextLimitWindowRowids', [9, 3, 4]],
    'current after window' => ['currentAfterWindowRowids', [6]],
    'next after window' => ['nextAfterWindowRowids', [10, 5, 6]],
    'skipped entered' => ['skippedEnteredRowids', []],
    'skipped exited' => ['skippedExitedRowids', []],
    'window entered' => ['limitWindowEnteredRowids', [9]],
    'window exited' => ['limitWindowExitedRowids', [5]],
    'offset prefix changed' => ['offsetPrefixChanged', false],
    'current window text three' => ['currentWindowTexts.3', 'plugin_cache_alpha'],
    'current window text four' => ['currentWindowTexts.4', 'plugin_cache_beta'],
    'current window text five' => ['currentWindowTexts.5', 'plugin_cache_delta'],
    'next window text nine' => ['nextWindowTexts.9', 'plugin_cache_aardvark'],
    'next window text four rtrim' => ['nextWindowTexts.4', 'plugin_cache_beta'],
    'current skipped text one' => ['currentSkippedTexts.1', 'Plugin_Cache'],
    'next skipped text two' => ['nextSkippedTexts.2', 'plugin_cache'],
    'next key nine' => ['nextNocaseKeys.9', 'plugin_cache_aardvark'],
    'next rtrim four' => ['nextRtrimTexts.4', 'plugin_cache_beta'],
    'current matched' => ['currentMatchedRowids', [1, 2, 3, 4, 5, 6]],
    'next matched' => ['nextMatchedRowids', [1, 2, 9, 3, 4, 10, 5, 6]],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [11]],
    'unsafe source' => ['limitOffsetUnsafeReasons.0', 'source-or-schema-changed'],
    'unsafe malformed' => ['limitOffsetUnsafeReasons.1', 'malformed-text'],
    'unsafe window' => ['limitOffsetUnsafeReasons.2', 'limit-window-rowset-changed'],
    'unsafe residual' => ['limitOffsetUnsafeReasons.3', 'rtrim-like-residual-changed'],
    'resume unsafe' => ['limitOffsetResumeSafe', false],
    'must reprepare' => ['mustReprepareBeforeLimitOffsetResume', true],
    'mode' => ['replayPlanMode', 'recompute-limit-offset-window'],
    'replay rowids' => ['replayPlanRowids', [9, 3, 4]],
    'offset count marker' => ['offsetCountsDecodedRowsNotBytes', true],
    'window order marker' => ['limitWindowUsesRtrimNocaseOrder', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'residual before limit' => ['likeResidualAppliesBeforeLimitOffset', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency window' => ['dependencies.2', 'sqlite-rtrim-limit-offset-window'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nextoneNineThree'],
];

foreach ($cases193 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneNineThree ' . $name] = static function (TestRunner $t) use ($plan193, $valueAt193, $path, $expected): void {
        $t->same($expected, $valueAt193($plan193(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneNineThree stable limit window resumes after window'] = static function (TestRunner $t) use ($row193): void {
    $rows = [
        $row193(1, 'plugin_cache', 'UTF-16LE'),
        $row193(2, 'plugin_cache_alpha', 'UTF-16BE'),
        $row193(3, 'plugin_cache_beta', 'UTF-8'),
        $row193(4, 'plugin_cache_delta', 'UTF-16LE'),
        $row193(5, 'plugin_cache_zeta', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameLimitOffsetPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        2,
        1,
        'stable',
        'stable',
        193,
        193,
    );

    $t->same([], $result['limitOffsetUnsafeReasons']);
    $t->same(true, $result['limitOffsetResumeSafe']);
    $t->same([2, 3], $result['nextLimitWindowRowids']);
    $t->same([4, 5], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneNineThree inserted offset row is unsafe even when window text repeats'] = static function (TestRunner $t) use ($row193): void {
    $current = [
        $row193(2, 'plugin_cache_alpha', 'UTF-16LE'),
        $row193(3, 'plugin_cache_beta', 'UTF-16LE'),
        $row193(4, 'plugin_cache_delta', 'UTF-16LE'),
    ];
    $next = [
        $row193(1, 'plugin_cache', 'UTF-16BE'),
        $row193(2, 'plugin_cache_alpha', 'UTF-16LE'),
        $row193(3, 'plugin_cache_beta', 'UTF-16LE'),
        $row193(4, 'plugin_cache_delta', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameLimitOffsetPlan(
        $current,
        $next,
        'plugin!_cache%',
        '!',
        2,
        1,
        'stable',
        'stable',
        193,
        193,
    );

    $t->same([1], $result['skippedEnteredRowids']);
    $t->same([2], $result['skippedExitedRowids']);
    $t->same(true, $result['offsetPrefixChanged']);
    $t->same(['offset-prefix-rowset-changed', 'limit-window-rowset-changed', 'rtrim-like-residual-changed'], $result['limitOffsetUnsafeReasons']);
};

$tests['utf16 nocase like rtrim current source nextOneNineThree zero limit tracks offset without row output'] = static function (TestRunner $t) use ($row193): void {
    $rows = [
        $row193(1, 'plugin_cache', 'UTF-16LE'),
        $row193(2, 'plugin_cache_alpha', 'UTF-16LE'),
        $row193(3, 'plugin_cache_beta', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameLimitOffsetPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        0,
        2,
        'stable',
        'stable',
        193,
        193,
    );

    $t->same([1, 2], $result['currentSkippedRowids']);
    $t->same([], $result['currentLimitWindowRowids']);
    $t->same([3], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneNineThree rejects negative limit'] = static function (TestRunner $t) use ($row193): void {
    $rows = [$row193(1, 'plugin_cache', 'UTF-16LE')];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameLimitOffsetPlan(
        $rows,
        $rows,
        'plugin%',
        null,
        -1,
        0,
    ));
};

$tests['utf16 nocase like rtrim current source nextOneNineThree rejects negative offset'] = static function (TestRunner $t) use ($row193): void {
    $rows = [$row193(1, 'plugin_cache', 'UTF-16LE')];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameLimitOffsetPlan(
        $rows,
        $rows,
        'plugin%',
        null,
        1,
        -1,
    ));
};

return $tests;
