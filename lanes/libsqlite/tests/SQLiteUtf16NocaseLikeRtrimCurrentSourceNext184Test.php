<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc184 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row184 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc184($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$rows184 = [
    $row184(1, 'Plugin_%Cache  ', 'UTF-16LE'),
    $row184(2, 'plugin_%cache', 'UTF-16BE'),
    $row184(3, 'PLUGIN_%CACHE  ', 'UTF-8'),
    $row184(4, 'plugin_acache', 'UTF-16LE'),
    $row184(5, 'plugin_%cache_alpha', 'UTF-16BE'),
    $row184(6, "plugin_%cache\t", 'UTF-16LE'),
    $row184(7, 'plugin__cache', 'UTF-16BE'),
];
$tokenBytes184 = $enc184('plugin_%cache', 'UTF-16BE');
$token184 = [
    'key' => 'plugin_%cache',
    'rowid' => 2,
    'bytesHex' => bin2hex($tokenBytes184),
    'encoding' => 'UTF-16BE',
    'keyBytes' => $tokenBytes184,
    'keyEncoding' => 'UTF-16BE',
];

$plan184 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $token = null,
    string $pattern = 'plugin!_!%cache%',
    ?string $escape = '!',
    string $currentSource = 'stable',
    string $nextSource = 'stable',
    int $currentCookie = 184,
    int $nextCookie = 184,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameEscapedPeerReplayPlan(
    $current ?? $rows184,
    $next ?? $rows184,
    $pattern,
    $escape,
    $token ?? $token184,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt184 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases184 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-next184'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE'],
    'pattern' => ['pattern', 'plugin!_!%cache%'],
    'escape' => ['escape', '!'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-next181'],
    'base mode' => ['baseReplayPlanMode', 'continue-after-key-rowid-peer-token'],
    'current source' => ['currentSource', 'stable'],
    'next source' => ['nextSource', 'stable'],
    'current cookie' => ['currentSchemaCookie', 184],
    'next cookie' => ['nextSchemaCookie', 184],
    'prefix' => ['prefix', 'plugin_%cache'],
    'range lower' => ['range.lowerInclusive', 'plugin_%cache'],
    'range upper' => ['range.upperBound', 'plugin_%cachf'],
    'token key' => ['normalizedLastYielded.key', 'plugin_%cache'],
    'token rowid' => ['normalizedLastYielded.rowid', 2],
    'token decoded' => ['tokenDecodedText', 'plugin_%cache'],
    'token rtrim' => ['tokenRtrimText', 'plugin_%cache'],
    'token nocase key' => ['tokenNocaseKey', 'plugin_%cache'],
    'token residual true' => ['tokenMatchesEscapedLikeResidual', true],
    'token decode error null' => ['tokenDecodeError', null],
    'literal percent marker' => ['tokenEscapePreservesLiteralPercent', true],
    'literal underscore marker' => ['tokenEscapePreservesLiteralUnderscore', true],
    'peer key' => ['peerKey', 'plugin_%cache'],
    'current peers' => ['currentPeerRowids', [1, 2, 3]],
    'next peers' => ['nextPeerRowids', [1, 2, 3]],
    'before token peers' => ['peerBeforeOrAtTokenRowids', [1, 2]],
    'after token peers' => ['peerAfterTokenRowids', [3]],
    'same key replay' => ['sameKeyReplayRowids', [3]],
    'remaining after peer' => ['remainingAfterPeerRowids', [6, 5]],
    'current matched' => ['currentMatchedRowids', [1, 2, 3, 6, 5]],
    'next matched' => ['nextMatchedRowids', [1, 2, 3, 6, 5]],
    'wildcard false positive excluded' => ['nextMatchedKeys.4', null],
    'double underscore excluded' => ['nextMatchedKeys.7', null],
    'tab key preserved' => ['nextMatchedKeys.6', "plugin_%cache\t"],
    'utf16be retained' => ['nextMatchedEncodings.2', 'UTF-16BE'],
    'utf8 retained' => ['nextMatchedEncodings.3', 'UTF-8'],
    'duplicate peer key' => ['duplicateRtrimNocaseKeys.plugin_%cache', [1, 2, 3]],
    'base unsafe clear' => ['basePeerReplayUnsafeReasons', []],
    'unsafe clear' => ['peerReplayUnsafeReasons', []],
    'peer safe' => ['peerContinuationSafe', true],
    'no reprepare' => ['mustReprepareBeforePeerReplay', false],
    'safe within peer' => ['safeToContinueWithinPeerGroup', true],
    'mode' => ['replayPlanMode', 'continue-after-escaped-like-peer-token'],
    'replay rowids' => ['replayPlanRowids', [3, 6, 5]],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'escaped residual' => ['escapedLikeResidualAppliesAfterRtrim', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency residual' => ['dependencies.1', 'sqlite-like-escape-residual'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression'],
    'dependency peer' => ['dependencies.3', 'sqlite-nocase-like-peer-replay'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-next184'],
];

foreach ($cases184 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source next184 ' . $name] = static function (TestRunner $t) use ($plan184, $valueAt184, $path, $expected): void {
        $t->same($expected, $valueAt184($plan184(), $path));
    };
}

$tests['utf16 nocase like rtrim current source next184 escaped percent blocks wildcard token'] = static function (TestRunner $t) use ($rows184, $enc184, $token184): void {
    $bad = $token184;
    $bad['key'] = 'plugin_acache';
    $bad['rowid'] = 4;
    $bad['keyBytes'] = $enc184('plugin_acache', 'UTF-16LE');
    $bad['keyEncoding'] = 2;
    $bad['bytesHex'] = bin2hex($bad['keyBytes']);
    $bad['encoding'] = 'UTF-16LE';
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameEscapedPeerReplayPlan($rows184, $rows184, 'plugin!_!%cache%', '!', $bad, 'stable', 'stable', 184, 184);
    $t->same(false, $result['tokenMatchesEscapedLikeResidual']);
    $t->same(['yield-token-not-stable', 'yield-token-like-residual-mismatch'], $result['peerReplayUnsafeReasons']);
    $t->same(true, $result['mustReprepareBeforePeerReplay']);
    $t->same([1, 2, 3, 6, 5], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source next184 escaped underscore blocks wildcard token'] = static function (TestRunner $t) use ($rows184, $enc184, $token184): void {
    $bad = $token184;
    $bad['key'] = 'plugin__cache';
    $bad['rowid'] = 7;
    $bad['keyBytes'] = $enc184('plugin__cache', 'UTF-16BE');
    $bad['bytesHex'] = bin2hex($bad['keyBytes']);
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameEscapedPeerReplayPlan($rows184, $rows184, 'plugin!_!%cache%', '!', $bad, 'stable', 'stable', 184, 184);
    $t->same(false, $result['tokenMatchesEscapedLikeResidual']);
    $t->same(['yield-token-not-stable', 'yield-token-like-residual-mismatch'], $result['peerReplayUnsafeReasons']);
    $t->same('reprepare-from-range-start', $result['replayPlanMode']);
};

$tests['utf16 nocase like rtrim current source next184 token trailing spaces match after rtrim'] = static function (TestRunner $t) use ($rows184, $enc184, $token184): void {
    $token = $token184;
    $token['key'] = 'plugin_%cache';
    $token['rowid'] = 1;
    $token['keyBytes'] = $enc184('Plugin_%Cache  ', 'UTF-16LE');
    $token['keyEncoding'] = 2;
    $token['bytesHex'] = bin2hex($token['keyBytes']);
    $token['encoding'] = 'UTF-16LE';
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameEscapedPeerReplayPlan($rows184, $rows184, 'plugin!_!%cache', '!', $token, 'stable', 'stable', 184, 184);
    $t->same('Plugin_%Cache', $result['tokenRtrimText']);
    $t->same(true, $result['tokenMatchesEscapedLikeResidual']);
    $t->same([2, 3], $result['sameKeyReplayRowids']);
};

$tests['utf16 nocase like rtrim current source next184 tab suffix does not rtrim into escaped literal'] = static function (TestRunner $t) use ($rows184, $enc184, $token184): void {
    $token = $token184;
    $token['key'] = "plugin_%cache\t";
    $token['rowid'] = 6;
    $token['keyBytes'] = $enc184("plugin_%cache\t", 'UTF-16LE');
    $token['keyEncoding'] = 2;
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameEscapedPeerReplayPlan($rows184, $rows184, 'plugin!_!%cache', '!', $token, 'stable', 'stable', 184, 184);
    $t->same('plugin_%cache', $result['tokenRtrimText']);
    $t->same(true, $result['tokenMatchesEscapedLikeResidual']);
    $t->same(true, in_array('yield-token-not-stable', $result['peerReplayUnsafeReasons'], true));
};

$tests['utf16 nocase like rtrim current source next184 malformed token bytes are rejected before unsafe continuation'] = static function (TestRunner $t) use ($rows184, $token184): void {
    $bad = $token184;
    $bad['keyBytes'] = "\x00\xd8";
    $bad['keyEncoding'] = 2;
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameEscapedPeerReplayPlan($rows184, $rows184, 'plugin!_!%cache%', '!', $bad, 'stable', 'stable', 184, 184));
};

$tests['utf16 nocase like rtrim current source next184 source change still defeats escaped token continuation'] = static function (TestRunner $t) use ($plan184): void {
    $result = $plan184(null, null, null, 'plugin!_!%cache%', '!', 'main.wp_options@183', 'main.wp_options@184', 183, 184);
    $t->same(['source-or-schema-changed'], $result['basePeerReplayUnsafeReasons']);
    $t->same(['source-or-schema-changed'], $result['peerReplayUnsafeReasons']);
    $t->same([1, 2, 3, 6, 5], $result['replayPlanRowids']);
};

return $tests;
