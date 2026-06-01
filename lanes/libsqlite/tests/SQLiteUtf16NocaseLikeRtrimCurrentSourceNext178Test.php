<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc178 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row178 = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc178($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad178 = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current178 = [
    $row178(1, 'Plugin_Cache  ', 'UTF-16LE'),
    $row178(2, 'plugin_cache_alpha', 'UTF-16BE'),
    $row178(3, 'plugin_cache_beta', 'UTF-8'),
    $row178(4, "plugin_cache_tab\t", 'UTF-16LE'),
    $row178(5, 'plugin_other', 'UTF-16BE'),
];
$nextOneSevenEight = [
    $row178(1, 'Plugin_Cache', 'UTF-16BE'),
    $row178(2, 'plugin_cache_alpha', 'UTF-16BE'),
    $row178(3, 'plugin_cache_beta  ', 'UTF-8'),
    $row178(4, "plugin_cache_tab\t", 'UTF-16LE'),
    $row178(6, 'PLUGIN_CACHE_GAMMA', 'UTF-16LE'),
    $bad178(9, "\x00\xd8", 2),
];
$rawTokenBytes178 = $enc178('Plugin_Cache  ', 'UTF-16LE');
$token178 = [
    'key' => 'Plugin_Cache  ',
    'rowid' => 1,
    'bytesHex' => bin2hex($rawTokenBytes178),
    'encoding' => 'UTF-16LE',
    'keyBytes' => $rawTokenBytes178,
    'keyEncoding' => 'UTF-16LE',
];

$plan178 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $token = null,
    string $currentSource = 'main.app_settings@177',
    string $nextSource = 'main.app_settings@178',
    int $currentCookie = 177,
    int $nextCookie = 178,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCanonicalTokenPlan(
    $current ?? $current178,
    $next ?? $nextOneSevenEight,
    'plugin!_cache%',
    '!',
    $token ?? $token178,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt178 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases178 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneSevenEight'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneSevenFive'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'current source' => ['currentSource', 'main.app_settings@177'],
    'next source' => ['nextSource', 'main.app_settings@178'],
    'current cookie' => ['currentSchemaCookie', 177],
    'next cookie' => ['nextSchemaCookie', 178],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['range.lowerInclusive', 'plugin_cache'],
    'range upper' => ['range.upperBound', 'plugin_cachf'],
    'raw token key' => ['rawLastYielded.key', 'Plugin_Cache  '],
    'normalized token key' => ['normalizedLastYielded.key', 'plugin_cache'],
    'normalized token rowid' => ['normalizedLastYielded.rowid', 1],
    'normalized token bytes' => ['normalizedLastYielded.bytesHex', bin2hex($rawTokenBytes178)],
    'normalized token encoding' => ['normalizedLastYielded.encoding', 'UTF-16LE'],
    'token raw text' => ['tokenRawText', 'Plugin_Cache  '],
    'token rtrim text' => ['tokenRtrimText', 'Plugin_Cache'],
    'token canonical key' => ['tokenCanonicalKey', 'plugin_cache'],
    'token canonical encoding' => ['tokenCanonicalEncoding', 'UTF-16LE'],
    'token canonical bytes' => ['tokenCanonicalBytesHex', bin2hex($rawTokenBytes178)],
    'normalization reason' => ['tokenNormalizationReasons.0', 'token-key-not-canonical'],
    'base reason source' => ['baseReplayInvalidationReasons.0', 'source-name'],
    'base reason schema' => ['baseReplayInvalidationReasons.1', 'schema-cookie'],
    'base reason matched rowset' => ['baseReplayInvalidationReasons.2', 'matched-rowset'],
    'base reason malformed' => ['baseReplayInvalidationReasons.3', 'malformed-text'],
    'base reason encoding' => ['baseReplayInvalidationReasons.4', 'encoding-changed'],
    'base reason bytes' => ['baseReplayInvalidationReasons.5', 'bytes-changed'],
    'replay includes fingerprint reason' => ['replayInvalidationReasons.6', 'yielded-token-bytes-changed'],
    'replay includes canonical reason' => ['replayInvalidationReasons.10', 'token-key-not-canonical'],
    'current rowids' => ['currentMatchedRowids', [1, 2, 3, 4]],
    'next rowids' => ['nextMatchedRowids', [1, 2, 3, 6, 4]],
    'current token fingerprint rowid' => ['currentTokenFingerprint.rowid', 1],
    'next token fingerprint encoding' => ['nextTokenFingerprint.encoding', 'UTF-16BE'],
    'next token fingerprint bytes' => ['nextTokenFingerprint.bytesHex', bin2hex($enc178('Plugin_Cache', 'UTF-16BE'))],
    'fingerprint reason bytes' => ['tokenFingerprintReasons.0', 'yielded-token-bytes-changed'],
    'fingerprint reason encoding' => ['tokenFingerprintReasons.1', 'yielded-token-encoding-changed'],
    'fingerprint reason current bytes' => ['tokenFingerprintReasons.2', 'current-next-token-bytes-changed'],
    'fingerprint reason current encoding' => ['tokenFingerprintReasons.3', 'current-next-token-encoding-changed'],
    'malformed row isolated' => ['nextMalformedRowids', [9]],
    'must reprepare' => ['mustReprepareBeforeReplay', true],
    'not safe' => ['safeToReplayFromToken', false],
    'replay mode' => ['replayPlanMode', 'reprepare-from-range-start'],
    'replay rowids' => ['replayPlanRowids', [1, 2, 3, 6, 4]],
    'rowid tie breaker' => ['tokenIncludesRowidTieBreaker', true],
    'byte fingerprint' => ['tokenIncludesByteFingerprint', true],
    'canonicalizes raw token' => ['tokenCanonicalizesRawUtf16Key', true],
    'rtrim ascii space only' => ['tokenRtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii only' => ['tokenNocaseFoldsAsciiOnly', true],
    'dependency utf16' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency rtrim' => ['dependencies.1', 'sqlite-rtrim-expression'],
    'dependency canonical' => ['dependencies.2', 'sqlite-nocase-like-rtrim-token-canonicalization'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-nextoneSevenEight'],
];

foreach ($cases178 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneSevenEight ' . $name] = static function (TestRunner $t) use ($plan178, $valueAt178, $path, $expected): void {
        $t->same($expected, $valueAt178($plan178(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneSevenEight canonical token can continue on stable source'] = static function (TestRunner $t) use ($row178, $enc178): void {
    $rows = [
        $row178(1, 'Plugin_Cache  ', 'UTF-16LE'),
        $row178(2, 'plugin_cache_alpha', 'UTF-16BE'),
        $row178(3, 'plugin_cache_beta', 'UTF-8'),
    ];
    $bytes = $enc178('Plugin_Cache  ', 'UTF-16LE');
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCanonicalTokenPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        [
            'key' => 'plugin_cache',
            'rowid' => 1,
            'bytesHex' => bin2hex($bytes),
            'encoding' => 'UTF-16LE',
            'keyBytes' => $bytes,
            'keyEncoding' => 2,
        ],
        'stable',
        'stable',
        178,
        178,
    );
    $t->same([], $result['tokenNormalizationReasons']);
    $t->same([], $result['replayInvalidationReasons']);
    $t->same(false, $result['mustReprepareBeforeReplay']);
    $t->same(true, $result['safeToReplayFromToken']);
    $t->same('continue-after-key-rowid-byte-token', $result['replayPlanMode']);
    $t->same([2, 3], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenEight canonicalizes stale raw key before replay'] = static function (TestRunner $t) use ($row178, $enc178): void {
    $rows = [
        $row178(1, 'Plugin_Cache  ', 'UTF-16LE'),
        $row178(2, 'plugin_cache_alpha', 'UTF-16BE'),
        $row178(3, 'plugin_cache_beta', 'UTF-8'),
    ];
    $bytes = $enc178('Plugin_Cache  ', 'UTF-16LE');
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCanonicalTokenPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        [
            'key' => 'Plugin_Cache  ',
            'rowid' => 1,
            'bytesHex' => bin2hex($bytes),
            'encoding' => 'UTF-16LE',
            'keyBytes' => $bytes,
            'keyEncoding' => 'UTF-16LE',
        ],
        'stable',
        'stable',
        178,
        178,
    );
    $t->same('plugin_cache', $result['normalizedLastYielded']['key']);
    $t->same(['token-key-not-canonical'], $result['tokenNormalizationReasons']);
    $t->same(['token-key-not-canonical'], $result['replayInvalidationReasons']);
    $t->same([1, 2, 3], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenEight raw byte mismatch is explicit'] = static function (TestRunner $t) use ($row178, $enc178): void {
    $rows = [
        $row178(1, 'plugin_cache', 'UTF-16BE'),
        $row178(2, 'plugin_cache_alpha', 'UTF-16BE'),
    ];
    $rawBytes = $enc178('plugin_cache', 'UTF-16BE');
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCanonicalTokenPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        [
            'key' => 'plugin_cache',
            'rowid' => 1,
            'bytesHex' => bin2hex($enc178('plugin_cache', 'UTF-16LE')),
            'encoding' => 'UTF-16BE',
            'keyBytes' => $rawBytes,
            'keyEncoding' => 'UTF-16BE',
        ],
        'stable',
        'stable',
        178,
        178,
    );
    $t->same(['token-raw-bytes-fingerprint-mismatch'], $result['tokenNormalizationReasons']);
    $t->same(true, $result['mustReprepareBeforeReplay']);
    $t->same([1, 2], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenEight raw encoding mismatch is explicit'] = static function (TestRunner $t) use ($row178, $enc178): void {
    $rows = [
        $row178(1, 'plugin_cache', 'UTF-16LE'),
        $row178(2, 'plugin_cache_alpha', 'UTF-16LE'),
    ];
    $rawBytes = $enc178('plugin_cache', 'UTF-16LE');
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCanonicalTokenPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        [
            'key' => 'plugin_cache',
            'rowid' => 1,
            'bytesHex' => bin2hex($rawBytes),
            'encoding' => 'UTF-16BE',
            'keyBytes' => $rawBytes,
            'keyEncoding' => 'UTF-16LE',
        ],
        'stable',
        'stable',
        178,
        178,
    );
    $t->same(['token-raw-encoding-mismatch'], $result['tokenNormalizationReasons']);
    $t->same(true, $result['mustReprepareBeforeReplay']);
    $t->same([1, 2], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenEight missing token still reparses'] = static function (TestRunner $t) use ($row178): void {
    $rows = [
        $row178(1, 'plugin_cache', 'UTF-16LE'),
        $row178(2, 'plugin_cache_alpha', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCanonicalTokenPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        null,
        'stable',
        'stable',
        178,
        178,
    );
    $t->same(['no-yield-token'], $result['tokenNormalizationReasons']);
    $t->same(['no-yield-token'], $result['replayInvalidationReasons']);
    $t->same(true, $result['mustReprepareBeforeReplay']);
    $t->same([1, 2], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenEight rejects malformed raw token bytes'] = static function (TestRunner $t) use ($row178): void {
    $rows = [
        $row178(1, 'plugin_cache', 'UTF-16LE'),
    ];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCanonicalTokenPlan(
        $rows,
        $rows,
        'plugin%',
        null,
        [
            'key' => 'plugin_cache',
            'rowid' => 1,
            'keyBytes' => "\x00\xd8",
            'keyEncoding' => 'UTF-16LE',
        ],
    ));
};

$tests['utf16 nocase like rtrim current source nextOneSevenEight rejects partial raw token metadata'] = static function (TestRunner $t) use ($row178): void {
    $rows = [
        $row178(1, 'plugin_cache', 'UTF-16LE'),
    ];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCanonicalTokenPlan(
        $rows,
        $rows,
        'plugin%',
        null,
        [
            'key' => 'plugin_cache',
            'rowid' => 1,
            'keyEncoding' => 'UTF-16LE',
        ],
    ));
};

return $tests;
