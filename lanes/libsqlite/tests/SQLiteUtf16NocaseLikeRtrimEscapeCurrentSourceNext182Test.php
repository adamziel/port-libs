<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan;

$tests = [];

$enc182 = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row182 = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc182($name, $encoding),
    'text_encoding' => $encoding,
];

$current182 = [
    $row182(1, 'plugin_%_cache', 2),
    $row182(2, 'plugin_a_cache', 3),
    $row182(3, 'plugin_%_cache_alpha', 2),
    $row182(4, 'plugin_other', 3),
];
$nextOneEightTwo = [
    $row182(1, 'plugin_%_cache', 3),
    $row182(2, 'plugin_a_cache', 3),
    $row182(3, 'plugin_%_cache_alpha  ', 2),
    $row182(5, 'PLUGIN_%_CACHE_NEW', 2),
];
$token182 = [
    'key' => 'plugin_%_cache',
    'rowid' => 1,
    'bytesHex' => bin2hex($enc182('plugin_%_cache', 3)),
    'encoding' => 'UTF-16BE',
];

$plan182 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $currentPattern = 'plugin!_!%!_cache%',
    int $currentPatternEncoding = 2,
    string $nextPattern = 'plugin!_!%!_cache%',
    int $nextPatternEncoding = 3,
    ?string $currentEscape = '!',
    int $currentEscapeEncoding = 2,
    ?string $nextEscape = '!',
    int $nextEscapeEncoding = 3,
    ?array $token = null,
    string $currentSource = 'stable',
    string $nextSource = 'stable',
    int $currentCookie = 182,
    int $nextCookie = 182,
): array => SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan::wordpressOptionNameEscapeReplayPlan(
    $current ?? $current182,
    $next ?? $nextOneEightTwo,
    $enc182($currentPattern, $currentPatternEncoding),
    $currentPatternEncoding,
    $enc182($nextPattern, $nextPatternEncoding),
    $nextPatternEncoding,
    $currentEscape === null ? null : $enc182($currentEscape, $currentEscapeEncoding),
    $currentEscapeEncoding,
    $nextEscape === null ? null : $enc182($nextEscape, $nextEscapeEncoding),
    $nextEscapeEncoding,
    $token ?? $token182,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt182 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases182 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-escape-current-source-nextoneEightTwo'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneSevenFive'],
    'current pattern' => ['currentPattern', 'plugin!_!%!_cache%'],
    'next pattern' => ['nextPattern', 'plugin!_!%!_cache%'],
    'current pattern encoding' => ['currentPatternEncoding', 'UTF-16LE'],
    'next pattern encoding' => ['nextPatternEncoding', 'UTF-16BE'],
    'current escape' => ['currentEscape', '!'],
    'next escape' => ['nextEscape', '!'],
    'current escape width' => ['currentEscapeWidth', 1],
    'next escape width' => ['nextEscapeWidth', 1],
    'current escape encoding' => ['currentEscapeEncoding', 'UTF-16LE'],
    'next escape encoding' => ['nextEscapeEncoding', 'UTF-16BE'],
    'operand byte invalidations' => ['operandInvalidationReasons', ['pattern-encoding-changed', 'pattern-bytes-changed', 'escape-encoding-changed', 'escape-bytes-changed']],
    'base rowset reasons' => ['baseReplayInvalidationReasons', ['matched-rowset', 'encoding-changed', 'bytes-changed', 'current-next-token-bytes-changed', 'current-next-token-encoding-changed']],
    'matched current rowids' => ['currentMatchedRowids', [1, 3]],
    'matched next rowids' => ['nextMatchedRowids', [1, 3, 5]],
    'replay mode' => ['replayPlanMode', 'reprepare-from-decoded-escape-start'],
    'replay rowids' => ['replayPlanRowids', [1, 3, 5]],
    'must reprepare' => ['mustReprepareBeforeReplay', true],
    'unsafe replay' => ['safeToReplayFromToken', false],
    'token rowid tie breaker' => ['tokenIncludesRowidTieBreaker', true],
    'token bytes included' => ['tokenIncludesByteFingerprint', true],
    'escape verified' => ['escapeOperandVerifiedBeforeReplay', true],
    'dependency escape validation' => ['dependencies.1', 'sqlite-like-escape-single-character'],
];

foreach ($cases182 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim escape current source nextOneEightTwo ' . $name] = static function (TestRunner $t) use ($plan182, $valueAt182, $path, $expected): void {
        $t->same($expected, $valueAt182($plan182(), $path));
    };
}

$tests['utf16 nocase like rtrim escape current source nextOneEightTwo clean same bytes can continue'] = static function (TestRunner $t) use ($row182, $enc182): void {
    $rows = [
        $row182(1, 'plugin_%_cache', 2),
        $row182(3, 'plugin_%_cache_alpha', 2),
    ];
    $token = [
        'key' => 'plugin_%_cache',
        'rowid' => 1,
        'bytesHex' => bin2hex($enc182('plugin_%_cache', 2)),
        'encoding' => 'UTF-16LE',
    ];
    $result = SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan::wordpressOptionNameEscapeReplayPlan(
        $rows,
        $rows,
        $enc182('plugin!_!%!_cache%', 2),
        2,
        $enc182('plugin!_!%!_cache%', 2),
        2,
        $enc182('!', 2),
        2,
        $enc182('!', 2),
        2,
        $token,
        'stable',
        'stable',
        5,
        5,
    );
    $t->same([], $result['operandInvalidationReasons']);
    $t->same([], $result['baseReplayInvalidationReasons']);
    $t->same([], $result['replayInvalidationReasons']);
    $t->same(false, $result['mustReprepareBeforeReplay']);
    $t->same(true, $result['safeToReplayFromToken']);
    $t->same('continue-after-key-rowid-byte-token', $result['replayPlanMode']);
    $t->same([3], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim escape current source nextOneEightTwo changed escape text restarts with escaped wildcard semantics'] = static function (TestRunner $t) use ($plan182): void {
    $result = $plan182(nextEscape: '#');
    $t->same('#', $result['nextEscape']);
    $t->same(['pattern-encoding-changed', 'pattern-bytes-changed', 'escape-text-changed', 'escape-encoding-changed', 'escape-bytes-changed'], $result['operandInvalidationReasons']);
    $t->same([], $result['nextMatchedRowids']);
    $t->same(true, $result['mustReprepareBeforeReplay']);
};

$tests['utf16 nocase like rtrim escape current source nextOneEightTwo two character escape blocks stale replay'] = static function (TestRunner $t) use ($plan182): void {
    $result = $plan182(nextEscape: '!!');
    $t->same('!!', $result['nextEscape']);
    $t->same(2, $result['nextEscapeWidth']);
    $t->same(null, $result['baseStatus']);
    $t->same(['pattern-encoding-changed', 'pattern-bytes-changed', 'escape-text-changed', 'escape-encoding-changed', 'escape-bytes-changed', 'next-escape-not-single-character'], $result['operandInvalidationReasons']);
    $t->same('reprepare-from-decoded-escape-start', $result['replayPlanMode']);
};

$tests['utf16 nocase like rtrim escape current source nextOneEightTwo nul escape remains single character'] = static function (TestRunner $t) use ($plan182): void {
    $result = $plan182(currentPattern: "plugin\0_\0%\0_cache%", nextPattern: "plugin\0_\0%\0_cache%", currentEscape: "\0", nextEscape: "\0");
    $t->same("\0", $result['nextEscape']);
    $t->same(1, $result['nextEscapeWidth']);
    $t->same([], array_values(array_filter($result['operandInvalidationReasons'], static fn (string $reason): bool => str_contains($reason, 'not-single'))));
};

$tests['utf16 nocase like rtrim escape current source nextOneEightTwo malformed escape bytes are isolated'] = static function (TestRunner $t) use ($current182, $nextOneEightTwo, $enc182, $token182): void {
    $result = SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan::wordpressOptionNameEscapeReplayPlan(
        $current182,
        $nextOneEightTwo,
        $enc182('plugin!_!%!_cache%', 2),
        2,
        $enc182('plugin!_!%!_cache%', 2),
        2,
        $enc182('!', 2),
        2,
        "\x00\xd8",
        2,
        $token182,
        'stable',
        'stable',
        5,
        5,
    );
    $t->same(null, $result['nextEscape']);
    $t->same('next-escape: SQLite encoding source UTF-16 text payload ends with a high surrogate', $result['operandErrors']['nextEscape']);
    $t->same(['escape-text-changed', 'escape-bytes-changed', 'malformed-pattern-or-escape'], $result['operandInvalidationReasons']);
    $t->same(true, $result['mustReprepareBeforeReplay']);
};

$tests['utf16 nocase like rtrim escape current source nextOneEightTwo rejects unsupported encoding'] = static function (TestRunner $t) use ($current182, $nextOneEightTwo, $enc182, $token182): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan::wordpressOptionNameEscapeReplayPlan(
        $current182,
        $nextOneEightTwo,
        $enc182('plugin!_!%!_cache%', 2),
        2,
        $enc182('plugin!_!%!_cache%', 2),
        9,
        $enc182('!', 2),
        2,
        $enc182('!', 2),
        2,
        $token182,
    ));
};

return $tests;
