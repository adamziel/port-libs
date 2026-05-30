<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc181 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row181 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc181($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad181 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$rows181 = [
    $row181(1, 'Plugin_Cache  ', 'UTF-16LE'),
    $row181(2, 'plugin_cache', 'UTF-16BE'),
    $row181(3, 'PLUGIN_CACHE  ', 'UTF-8'),
    $row181(4, "plugin_cache\t", 'UTF-16LE'),
    $row181(5, 'plugin_cache_alpha', 'UTF-16BE'),
    $row181(6, 'plugin_cache_beta', 'UTF-16LE'),
    $row181(7, 'plugin_other', 'UTF-16BE'),
];
$tokenBytes181 = $enc181('plugin_cache', 'UTF-16BE');
$token181 = [
    'key' => 'plugin_cache',
    'rowid' => 2,
    'bytesHex' => bin2hex($tokenBytes181),
    'encoding' => 'UTF-16BE',
    'keyBytes' => $tokenBytes181,
    'keyEncoding' => 'UTF-16BE',
];

$plan181 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $token = null,
    string $currentSource = 'stable',
    string $nextSource = 'stable',
    int $currentCookie = 181,
    int $nextCookie = 181,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerReplayPlan(
    $current ?? $rows181,
    $next ?? $rows181,
    'plugin!_cache%',
    '!',
    $token ?? $token181,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt181 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases181 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneEightOne'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneSevenEight'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'current source' => ['currentSource', 'stable'],
    'next source' => ['nextSource', 'stable'],
    'current cookie' => ['currentSchemaCookie', 181],
    'next cookie' => ['nextSchemaCookie', 181],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['range.lowerInclusive', 'plugin_cache'],
    'range upper' => ['range.upperBound', 'plugin_cachf'],
    'token key' => ['normalizedLastYielded.key', 'plugin_cache'],
    'token rowid' => ['normalizedLastYielded.rowid', 2],
    'peer key' => ['peerKey', 'plugin_cache'],
    'current peers' => ['currentPeerRowids', [1, 2, 3]],
    'next peers' => ['nextPeerRowids', [1, 2, 3]],
    'before token peers' => ['peerBeforeOrAtTokenRowids', [1, 2]],
    'after token peers' => ['peerAfterTokenRowids', [3]],
    'same key replay' => ['sameKeyReplayRowids', [3]],
    'remaining after peer' => ['remainingAfterPeerRowids', [4, 5, 6]],
    'all matched' => ['nextMatchedRowids', [1, 2, 3, 4, 5, 6]],
    'tab not peer' => ['nextMatchedKeys.4', "plugin_cache\t"],
    'alpha key' => ['nextMatchedKeys.5', 'plugin_cache_alpha'],
    'beta key' => ['nextMatchedKeys.6', 'plugin_cache_beta'],
    'utf8 encoding retained' => ['nextMatchedEncodings.3', 'UTF-8'],
    'utf16be encoding retained' => ['nextMatchedEncodings.2', 'UTF-16BE'],
    'duplicate peer key' => ['duplicateRtrimNocaseKeys.plugin_cache', [1, 2, 3]],
    'token normalization clear' => ['tokenNormalizationReasons', []],
    'token fingerprint clear' => ['tokenFingerprintReasons', []],
    'base duplicate invalidation visible' => ['baseReplayInvalidationReasons', ['duplicate-rtrim-nocase-key']],
    'peer unsafe clear' => ['peerReplayUnsafeReasons', []],
    'peer continuation safe' => ['peerContinuationSafe', true],
    'no reprepare' => ['mustReprepareBeforePeerReplay', false],
    'safe within peer' => ['safeToContinueWithinPeerGroup', true],
    'mode' => ['replayPlanMode', 'continue-after-key-rowid-peer-token'],
    'replay rowids' => ['replayPlanRowids', [3, 4, 5, 6]],
    'rowid tie breaker' => ['tokenUsesRowidTieBreakerForEqualRtrimNocaseKeys', true],
    'rtrim ascii space only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii only' => ['nocaseFoldsAsciiOnly', true],
    'residual after rtrim' => ['likeResidualAppliesAfterRtrim', true],
    'dependency utf16' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency rtrim' => ['dependencies.1', 'sqlite-rtrim-expression'],
    'dependency peer' => ['dependencies.2', 'sqlite-nocase-like-peer-replay'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nextoneEightOne'],
];

foreach ($cases181 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneEightOne ' . $name] = static function (TestRunner $t) use ($plan181, $valueAt181, $path, $expected): void {
        $t->same($expected, $valueAt181($plan181(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneEightOne rowid tie breaker skips only yielded peer'] = static function (TestRunner $t) use ($plan181): void {
    $result = $plan181();
    $t->same([1, 2], $result['peerBeforeOrAtTokenRowids']);
    $t->same([3], $result['sameKeyReplayRowids']);
    $t->same([3, 4, 5, 6], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightOne source change reparses duplicate peers'] = static function (TestRunner $t) use ($plan181): void {
    $result = $plan181(null, null, null, 'main.app_settings@180', 'main.app_settings@181', 180, 181);
    $t->same(['source-or-schema-changed'], $result['peerReplayUnsafeReasons']);
    $t->same(true, $result['mustReprepareBeforePeerReplay']);
    $t->same('reprepare-from-range-start', $result['replayPlanMode']);
    $t->same([1, 2, 3, 4, 5, 6], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightOne peer insertion before token reparses'] = static function (TestRunner $t) use ($row181, $token181): void {
    $current = [
        $row181(1, 'Plugin_Cache  ', 'UTF-16LE'),
        $row181(2, 'plugin_cache', 'UTF-16BE'),
        $row181(3, 'PLUGIN_CACHE  ', 'UTF-8'),
        $row181(4, 'plugin_cache_alpha', 'UTF-16BE'),
    ];
    $next = [
        $row181(1, 'Plugin_Cache  ', 'UTF-16LE'),
        $row181(2, 'plugin_cache', 'UTF-16BE'),
        $row181(3, 'PLUGIN_CACHE  ', 'UTF-8'),
        $row181(4, 'plugin_cache_alpha', 'UTF-16BE'),
        $row181(0, 'plugin_cache', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerReplayPlan($current, $next, 'plugin!_cache%', '!', $token181, 'stable', 'stable', 181, 181);
    $t->same(['peer-rowset-changed'], $result['peerReplayUnsafeReasons']);
    $t->same([0, 1, 2, 3], $result['nextPeerRowids']);
    $t->same(true, $result['mustReprepareBeforePeerReplay']);
    $t->same([0, 1, 2, 3, 4], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightOne canonical token mismatch uses base reprepare'] = static function (TestRunner $t) use ($rows181, $enc181, $token181): void {
    $badToken = $token181;
    $badToken['key'] = 'Plugin_Cache  ';
    $badToken['keyBytes'] = $enc181('plugin_cache', 'UTF-16BE');
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerReplayPlan($rows181, $rows181, 'plugin!_cache%', '!', $badToken, 'stable', 'stable', 181, 181);
    $t->same(['yield-token-not-stable'], $result['peerReplayUnsafeReasons']);
    $t->same(['token-key-not-canonical'], $result['tokenNormalizationReasons']);
    $t->same(true, $result['mustReprepareBeforePeerReplay']);
};

$tests['utf16 nocase like rtrim current source nextOneEightOne no token cannot continue within peer group'] = static function (TestRunner $t) use ($rows181): void {
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerReplayPlan($rows181, $rows181, 'plugin!_cache%', '!', null, 'stable', 'stable', 181, 181);
    $t->same(null, $result['peerKey']);
    $t->same(['yield-token-not-stable'], $result['peerReplayUnsafeReasons']);
    $t->same(true, $result['mustReprepareBeforePeerReplay']);
    $t->same([1, 2, 3, 4, 5, 6], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightOne malformed peer source reparses'] = static function (TestRunner $t) use ($rows181, $bad181, $token181): void {
    $next = $rows181;
    $next[] = $bad181(9, "\x00\xd8", 2);
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerReplayPlan($rows181, $next, 'plugin!_cache%', '!', $token181, 'stable', 'stable', 181, 181);
    $t->same(['malformed-text'], $result['peerReplayUnsafeReasons']);
    $t->same(true, $result['mustReprepareBeforePeerReplay']);
    $t->same([1, 2, 3, 4, 5, 6], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightOne non ascii nocase remains sqlite ascii only'] = static function (TestRunner $t) use ($row181, $enc181): void {
    $rows = [
        $row181(1, 'plugin_cacheé', 'UTF-16LE'),
        $row181(2, 'plugin_cacheÉ', 'UTF-16BE'),
        $row181(3, 'plugin_cachex', 'UTF-8'),
    ];
    $tokenBytes = $enc181('plugin_cacheé', 'UTF-16LE');
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerReplayPlan(
        $rows,
        $rows,
        'plugin!_cache_',
        '!',
        ['key' => 'plugin_cacheé', 'rowid' => 1, 'bytesHex' => bin2hex($tokenBytes), 'encoding' => 'UTF-16LE', 'keyBytes' => $tokenBytes, 'keyEncoding' => 2],
        'stable',
        'stable',
        181,
        181,
    );
    $t->same(['plugin_cachex', 'plugin_cacheÉ', 'plugin_cacheé'], array_values($result['nextMatchedKeys']));
    $t->same([], $result['duplicateRtrimNocaseKeys']);
    $t->same([], $result['replayPlanRowids']);
    $t->same(true, $result['nocaseFoldsAsciiOnly']);
};

$tests['utf16 nocase like rtrim current source nextOneEightOne rejects row without encoding'] = static function (TestRunner $t) use ($row181, $token181): void {
    $rows = [
        $row181(1, 'plugin_cache', 'UTF-16LE'),
        ['option_id' => 2, 'option_name_bytes' => 'plugin_cache'],
    ];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerReplayPlan($rows, $rows, 'plugin%', null, $token181));
};

$tests['utf16 nocase like rtrim current source nextOneEightOne rejects row without bytes'] = static function (TestRunner $t) use ($row181, $token181): void {
    $rows = [
        $row181(1, 'plugin_cache', 'UTF-16LE'),
        ['option_id' => 2, 'text_encoding' => 2],
    ];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerReplayPlan($rows, $rows, 'plugin%', null, $token181));
};

$tests['utf16 nocase like rtrim current source nextOneEightOne rejects row without integer rowid'] = static function (TestRunner $t) use ($row181, $token181): void {
    $rows = [
        $row181(1, 'plugin_cache', 'UTF-16LE'),
        ['option_id' => '2', 'option_name_bytes' => 'plugin_cache', 'text_encoding' => 1],
    ];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerReplayPlan($rows, $rows, 'plugin%', null, $token181));
};

return $tests;
