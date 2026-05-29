<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc175 = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row175 = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc175($name, $encoding),
    'text_encoding' => $encoding,
];
$bad175 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current175 = [
    $row175(1, 'Plugin_Cache  ', 2),
    $row175(2, 'plugin_cache_alpha', 3),
    $row175(3, 'plugin_cache_beta', 2),
    $row175(4, "plugin_cache_tab\t", 3),
    $row175(5, 'plugin_other', 2),
];
$nextOneSevenFive = [
    $row175(1, 'Plugin_Cache', 3),
    $row175(2, 'plugin_cache_alpha', 3),
    $row175(3, 'plugin_cache_beta  ', 2),
    $row175(4, "plugin_cache_tab\t", 3),
    $row175(6, 'PLUGIN_CACHE_GAMMA', 2),
];
$token175 = [
    'key' => 'plugin_cache_alpha',
    'rowid' => 2,
    'bytesHex' => bin2hex($enc175('plugin_cache_alpha', 3)),
    'encoding' => 'UTF-16BE',
];

$plan175 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $token = null,
    string $currentSource = 'stable',
    string $nextSource = 'stable',
    int $currentCookie = 175,
    int $nextCookie = 175,
    string $pattern = 'plugin!_cache%',
    ?string $escape = '!',
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameTokenFingerprintPlan(
    $current ?? $current175,
    $next ?? $nextOneSevenFive,
    $pattern,
    $escape,
    $token ?? $token175,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt175 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases175 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneSevenFive'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneSevenOne'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'current source' => ['currentSource', 'stable'],
    'next source' => ['nextSource', 'stable'],
    'current cookie' => ['currentSchemaCookie', 175],
    'next cookie' => ['nextSchemaCookie', 175],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['range.lowerInclusive', 'plugin_cache'],
    'range upper' => ['range.upperBound', 'plugin_cachf'],
    'last token rowid' => ['lastYielded.rowid', 2],
    'last token encoding' => ['lastYielded.encoding', 'UTF-16BE'],
    'current token encoding' => ['currentTokenFingerprint.encoding', 'UTF-16BE'],
    'next token encoding' => ['nextTokenFingerprint.encoding', 'UTF-16BE'],
    'current matched' => ['currentMatchedRowids', [1, 2, 3, 4]],
    'next matched' => ['nextMatchedRowids', [1, 2, 3, 6, 4]],
    'next bytes row two unchanged' => ['nextMatchedBytesHex.2', bin2hex($enc175('plugin_cache_alpha', 3))],
    'token reasons empty' => ['tokenFingerprintReasons', []],
    'base replay reasons matched rowset' => ['baseReplayInvalidationReasons', ['matched-rowset', 'encoding-changed', 'bytes-changed']],
    'replay reasons matched rowset' => ['replayInvalidationReasons', ['matched-rowset', 'encoding-changed', 'bytes-changed']],
    'must reprepare for rowset' => ['mustReprepareBeforeReplay', true],
    'unsafe token replay' => ['safeToReplayFromToken', false],
    'replay mode' => ['replayPlanMode', 'reprepare-from-range-start'],
    'replay rowids' => ['replayPlanRowids', [1, 2, 3, 6, 4]],
    'rowid tie breaker' => ['tokenIncludesRowidTieBreaker', true],
    'byte fingerprint included' => ['tokenIncludesByteFingerprint', true],
    'fingerprint verified' => ['tokenFingerprintVerifiedAgainstNextSource', true],
    'dependency fingerprint' => ['dependencies.2', 'sqlite-yield-token-byte-fingerprint'],
];

foreach ($cases175 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneSevenFive ' . $name] = static function (TestRunner $t) use ($plan175, $valueAt175, $path, $expected): void {
        $t->same($expected, $valueAt175($plan175(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneSevenFive clean byte token can continue'] = static function (TestRunner $t) use ($row175, $enc175): void {
    $current = [
        $row175(1, 'Plugin_Cache', 2),
        $row175(2, 'plugin_cache_alpha', 3),
        $row175(3, 'plugin_cache_beta', 2),
    ];
    $next = $current;
    $token = [
        'key' => 'plugin_cache_alpha',
        'rowid' => 2,
        'bytesHex' => bin2hex($enc175('plugin_cache_alpha', 3)),
        'encoding' => 'UTF-16BE',
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameTokenFingerprintPlan($current, $next, 'plugin!_cache%', '!', $token, 'stable', 'stable', 5, 5);
    $t->same([], $result['tokenFingerprintReasons']);
    $t->same([], $result['replayInvalidationReasons']);
    $t->same(false, $result['mustReprepareBeforeReplay']);
    $t->same(true, $result['safeToReplayFromToken']);
    $t->same('continue-after-key-rowid-byte-token', $result['replayPlanMode']);
    $t->same([3], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenFive token byte mismatch reparses'] = static function (TestRunner $t) use ($row175, $enc175): void {
    $current = [
        $row175(1, 'Plugin_Cache', 2),
        $row175(2, 'plugin_cache_alpha', 3),
        $row175(3, 'plugin_cache_beta', 2),
    ];
    $next = [
        $row175(1, 'Plugin_Cache', 2),
        $row175(2, 'plugin_cache_alpha  ', 3),
        $row175(3, 'plugin_cache_beta', 2),
    ];
    $token = [
        'key' => 'plugin_cache_alpha',
        'rowid' => 2,
        'bytesHex' => bin2hex($enc175('plugin_cache_alpha', 3)),
        'encoding' => 'UTF-16BE',
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameTokenFingerprintPlan($current, $next, 'plugin!_cache%', '!', $token, 'stable', 'stable', 5, 5);
    $t->same(['yielded-token-bytes-changed', 'current-next-token-bytes-changed'], $result['tokenFingerprintReasons']);
    $t->same(['bytes-changed', 'yielded-token-bytes-changed', 'current-next-token-bytes-changed'], $result['replayInvalidationReasons']);
    $t->same(true, $result['mustReprepareBeforeReplay']);
    $t->same(false, $result['safeToReplayFromToken']);
    $t->same([1, 2, 3], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenFive token encoding mismatch reparses'] = static function (TestRunner $t) use ($row175, $enc175): void {
    $current = [
        $row175(1, 'Plugin_Cache', 2),
        $row175(2, 'plugin_cache_alpha', 3),
        $row175(3, 'plugin_cache_beta', 2),
    ];
    $next = [
        $row175(1, 'Plugin_Cache', 2),
        $row175(2, 'plugin_cache_alpha', 2),
        $row175(3, 'plugin_cache_beta', 2),
    ];
    $token = [
        'key' => 'plugin_cache_alpha',
        'rowid' => 2,
        'bytesHex' => bin2hex($enc175('plugin_cache_alpha', 3)),
        'encoding' => 'UTF-16BE',
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameTokenFingerprintPlan($current, $next, 'plugin!_cache%', '!', $token, 'stable', 'stable', 5, 5);
    $t->same(['yielded-token-bytes-changed', 'yielded-token-encoding-changed', 'current-next-token-bytes-changed', 'current-next-token-encoding-changed'], $result['tokenFingerprintReasons']);
    $t->same('UTF-16LE', $result['nextTokenFingerprint']['encoding']);
    $t->same(true, $result['mustReprepareBeforeReplay']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenFive token row exit reparses'] = static function (TestRunner $t) use ($row175, $enc175): void {
    $current = [
        $row175(1, 'Plugin_Cache', 2),
        $row175(2, 'plugin_cache_alpha', 3),
        $row175(3, 'plugin_cache_beta', 2),
    ];
    $next = [
        $row175(1, 'Plugin_Cache', 2),
        $row175(3, 'plugin_cache_beta', 2),
    ];
    $token = [
        'key' => 'plugin_cache_alpha',
        'rowid' => 2,
        'bytesHex' => bin2hex($enc175('plugin_cache_alpha', 3)),
        'encoding' => 'UTF-16BE',
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameTokenFingerprintPlan($current, $next, 'plugin!_cache%', '!', $token, 'stable', 'stable', 5, 5);
    $t->same(['yielded-token-row-exited'], $result['tokenFingerprintReasons']);
    $t->same(null, $result['nextTokenFingerprint']);
    $t->same([1, 3], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenFive source cookie invalidation composes with fingerprint'] = static function (TestRunner $t) use ($row175, $enc175): void {
    $current = [
        $row175(1, 'Plugin_Cache', 2),
        $row175(2, 'plugin_cache_alpha', 3),
    ];
    $next = [
        $row175(1, 'Plugin_Cache', 2),
        $row175(2, 'plugin_cache_alpha  ', 3),
    ];
    $token = [
        'key' => 'plugin_cache_alpha',
        'rowid' => 2,
        'bytesHex' => bin2hex($enc175('plugin_cache_alpha', 3)),
        'encoding' => 'UTF-16BE',
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameTokenFingerprintPlan($current, $next, 'plugin!_cache%', '!', $token);
    $t->same(['source-name', 'schema-cookie', 'bytes-changed'], $result['baseReplayInvalidationReasons']);
    $t->same(['yielded-token-bytes-changed', 'current-next-token-bytes-changed'], $result['tokenFingerprintReasons']);
    $t->same(['source-name', 'schema-cookie', 'bytes-changed', 'yielded-token-bytes-changed', 'current-next-token-bytes-changed'], $result['replayInvalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenFive malformed row is still isolated'] = static function (TestRunner $t) use ($row175, $bad175, $enc175): void {
    $current = [
        $row175(1, 'Plugin_Cache', 2),
        $row175(2, 'plugin_cache_alpha', 3),
    ];
    $next = [
        $row175(1, 'Plugin_Cache', 2),
        $row175(2, 'plugin_cache_alpha', 3),
        $bad175(7, "\x00\xd8", 2),
    ];
    $token = [
        'key' => 'plugin_cache_alpha',
        'rowid' => 2,
        'bytesHex' => bin2hex($enc175('plugin_cache_alpha', 3)),
        'encoding' => 'UTF-16BE',
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameTokenFingerprintPlan($current, $next, 'plugin!_cache%', '!', $token, 'stable', 'stable', 5, 5);
    $t->same([7], $result['nextMalformedRowids']);
    $t->same(['malformed-text'], $result['baseReplayInvalidationReasons']);
    $t->same([], $result['tokenFingerprintReasons']);
    $t->same(true, $result['mustReprepareBeforeReplay']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenFive rejects bad token'] = static function (TestRunner $t) use ($current175, $nextOneSevenFive): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameTokenFingerprintPlan(
        $current175,
        $nextOneSevenFive,
        'plugin%',
        null,
        ['key' => 'plugin_cache'],
    ));
};

return $tests;
