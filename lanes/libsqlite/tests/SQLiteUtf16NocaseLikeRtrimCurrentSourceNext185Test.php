<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc185 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row185 = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc185($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad185 = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current185 = [
    $row185(1, 'Plugin_Cache  ', 'UTF-16LE'),
    $row185(2, 'plugin_cache', 'UTF-16BE'),
    $row185(3, 'PLUGIN_CACHE  ', 'UTF-8'),
    $row185(4, "plugin_cache\t", 'UTF-16LE'),
    $row185(5, 'plugin_cache_alpha', 'UTF-16BE'),
    $row185(6, 'plugin_cache_beta', 'UTF-16LE'),
    $row185(7, 'plugin_other', 'UTF-16BE'),
    $row185(8, 'theme_cache', 'UTF-16LE'),
];
$nextOneEightFive = [
    $row185(1, 'Plugin_Cache  ', 'UTF-16LE'),
    $row185(3, 'PLUGIN_CACHE  ', 'UTF-8'),
    $row185(4, "plugin_cache\t", 'UTF-16LE'),
    $row185(5, 'plugin_cache_alpha', 'UTF-16BE'),
    $row185(6, 'plugin_cache_beta', 'UTF-16LE'),
    $row185(7, 'plugin_other', 'UTF-16BE'),
    $row185(9, 'plugin_cache_delta', 'UTF-16BE'),
];
$tokenBytes185 = $enc185('plugin_cache', 'UTF-16BE');
$token185 = [
    'key' => 'plugin_cache',
    'rowid' => 2,
    'bytesHex' => bin2hex($tokenBytes185),
    'encoding' => 'UTF-16BE',
];

$plan185 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $token = null,
    string $currentSource = 'stable',
    string $nextSource = 'stable',
    int $currentCookie = 185,
    int $nextCookie = 185,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDeletedTokenResumePlan(
    $current ?? $current185,
    $next ?? $nextOneEightFive,
    'plugin!_cache%',
    '!',
    $token ?? $token185,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt185 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases185 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneEightFive'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['range.lowerInclusive', 'plugin_cache'],
    'range upper' => ['range.upperBound', 'plugin_cachf'],
    'index usable' => ['indexUsable', true],
    'rejected reason' => ['rejectedReason', null],
    'current source' => ['currentSource', 'stable'],
    'next source' => ['nextSource', 'stable'],
    'current cookie' => ['currentSchemaCookie', 185],
    'next cookie' => ['nextSchemaCookie', 185],
    'token key' => ['normalizedLastYielded.key', 'plugin_cache'],
    'token rowid' => ['normalizedLastYielded.rowid', 2],
    'token bytes' => ['normalizedLastYielded.bytesHex', bin2hex($tokenBytes185)],
    'token encoding' => ['normalizedLastYielded.encoding', 'UTF-16BE'],
    'token normalization clear' => ['tokenNormalizationReasons', []],
    'deleted token rowid' => ['deletedTokenRowid', 2],
    'current matched rowids' => ['currentMatchedRowids', [1, 2, 3, 4, 5, 6]],
    'next matched rowids' => ['nextMatchedRowids', [1, 3, 4, 5, 6, 9]],
    'current peer rowids' => ['currentPeerRowids', [1, 2, 3]],
    'next peer rowids' => ['nextPeerRowids', [1, 3]],
    'current before token' => ['currentPeerBeforeOrAtTokenRowids', [1, 2]],
    'next before token' => ['nextPeerBeforeOrAtTokenRowids', [1]],
    'expected next before token' => ['expectedNextPeerBeforeOrAtTokenRowids', [1]],
    'current after token' => ['currentPeerAfterTokenRowids', [3]],
    'next after token' => ['nextPeerAfterTokenRowids', [3]],
    'same peer replay' => ['samePeerReplayRowids', [3]],
    'after peer replay' => ['afterPeerReplayRowids', [4, 5, 6, 9]],
    'replay rowids' => ['replayPlanRowids', [3, 4, 5, 6, 9]],
    'resume reasons clear' => ['resumeUnsafeReasons', []],
    'resume safe' => ['deletedTokenResumeSafe', true],
    'must not reprepare' => ['mustReprepareBeforeDeletedTokenResume', false],
    'safe to resume' => ['safeToResumeAfterDeletedToken', true],
    'mode' => ['replayPlanMode', 'continue-after-deleted-key-rowid-token'],
    'tab key retained' => ['nextMatchedKeys.4', "plugin_cache\t"],
    'alpha key retained' => ['nextMatchedKeys.5', 'plugin_cache_alpha'],
    'delta key retained' => ['nextMatchedKeys.9', 'plugin_cache_delta'],
    'row one rtrim' => ['nextMatchedRtrimText.1', 'Plugin_Cache'],
    'row three utf8 encoding' => ['nextMatchedEncodings.3', 'UTF-8'],
    'row five utf16be encoding' => ['nextMatchedEncodings.5', 'UTF-16BE'],
    'malformed current empty' => ['currentMalformedRowids', []],
    'malformed next empty' => ['nextMalformedRowids', []],
    'rowid boundary flag' => ['tokenUsesRowidBoundaryAfterDeletion', true],
    'rtrim ascii space only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii only' => ['nocaseFoldsAsciiOnly', true],
    'residual after rtrim' => ['likeResidualAppliesAfterRtrim', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency rtrim' => ['dependencies.1', 'sqlite-rtrim-expression'],
    'dependency deleted token' => ['dependencies.2', 'sqlite-nocase-like-deleted-token-resume'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nextoneEightFive'],
];

foreach ($cases185 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneEightFive ' . $name] = static function (TestRunner $t) use ($plan185, $valueAt185, $path, $expected): void {
        $t->same($expected, $valueAt185($plan185(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneEightFive source change reparses deleted token'] = static function (TestRunner $t) use ($plan185): void {
    $result = $plan185(null, null, null, 'main.app_settings@184', 'main.app_settings@185', 184, 185);
    $t->same(['source-or-schema-changed'], $result['resumeUnsafeReasons']);
    $t->same(false, $result['deletedTokenResumeSafe']);
    $t->same('reprepare-from-range-start', $result['replayPlanMode']);
    $t->same([1, 3, 4, 5, 6, 9], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightFive token still present cannot use deleted boundary'] = static function (TestRunner $t) use ($plan185, $current185): void {
    $result = $plan185($current185, $current185);
    $t->same(['yield-token-not-deleted'], $result['resumeUnsafeReasons']);
    $t->same(null, $result['deletedTokenRowid']);
    $t->same(true, $result['mustReprepareBeforeDeletedTokenResume']);
    $t->same([1, 2, 3, 4, 5, 6], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightFive peer inserted before token reparses'] = static function (TestRunner $t) use ($current185, $nextOneEightFive, $row185, $token185): void {
    $next = $nextOneEightFive;
    $next[] = $row185(0, 'plugin_cache', 'UTF-16LE');
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDeletedTokenResumePlan($current185, $next, 'plugin!_cache%', '!', $token185, 'stable', 'stable', 185, 185);
    $t->same(['peer-before-token-changed'], $result['resumeUnsafeReasons']);
    $t->same([0, 1], $result['nextPeerBeforeOrAtTokenRowids']);
    $t->same([1], $result['expectedNextPeerBeforeOrAtTokenRowids']);
    $t->same('reprepare-from-range-start', $result['replayPlanMode']);
};

$tests['utf16 nocase like rtrim current source nextOneEightFive lost later peer reparses'] = static function (TestRunner $t) use ($current185, $row185, $token185): void {
    $next = [
        $row185(1, 'Plugin_Cache  ', 'UTF-16LE'),
        $row185(5, 'plugin_cache_alpha', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDeletedTokenResumePlan($current185, $next, 'plugin!_cache%', '!', $token185, 'stable', 'stable', 185, 185);
    $t->same(['peer-after-token-lost-row'], $result['resumeUnsafeReasons']);
    $t->same([], $result['nextPeerAfterTokenRowids']);
    $t->same([1, 5], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightFive malformed next row reparses'] = static function (TestRunner $t) use ($current185, $nextOneEightFive, $bad185, $token185): void {
    $next = $nextOneEightFive;
    $next[] = $bad185(10, "\x00\xd8", 2);
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDeletedTokenResumePlan($current185, $next, 'plugin!_cache%', '!', $token185, 'stable', 'stable', 185, 185);
    $t->same(['malformed-text'], $result['resumeUnsafeReasons']);
    $t->same([10], $result['nextMalformedRowids']);
    $t->same('SQLite encoding source UTF-16 text payload ends with a high surrogate', $result['nextErrors'][10]);
};

$tests['utf16 nocase like rtrim current source nextOneEightFive non canonical token reparses'] = static function (TestRunner $t) use ($plan185, $token185): void {
    $token = $token185;
    $token['key'] = 'Plugin_Cache  ';
    $result = $plan185(null, null, $token);
    $t->same(['token-key-not-canonical'], $result['tokenNormalizationReasons']);
    $t->same(['yield-token-not-canonical'], $result['resumeUnsafeReasons']);
    $t->same(true, $result['mustReprepareBeforeDeletedTokenResume']);
};

$tests['utf16 nocase like rtrim current source nextOneEightFive null token reparses'] = static function (TestRunner $t) use ($current185, $nextOneEightFive): void {
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDeletedTokenResumePlan($current185, $nextOneEightFive, 'plugin!_cache%', '!', null, 'stable', 'stable', 185, 185);
    $t->same(['yield-token-missing', 'yield-token-not-deleted'], $result['resumeUnsafeReasons']);
    $t->same(null, $result['normalizedLastYielded']);
    $t->same([1, 3, 4, 5, 6, 9], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightFive rejects missing bytes'] = static function (TestRunner $t) use ($current185, $nextOneEightFive, $token185): void {
    $bad = $current185;
    $bad[] = ['setting_id' => 20, 'text_encoding' => 2];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDeletedTokenResumePlan($bad, $nextOneEightFive, 'plugin%', null, $token185));
};

$tests['utf16 nocase like rtrim current source nextOneEightFive rejects missing encoding'] = static function (TestRunner $t) use ($current185, $nextOneEightFive, $token185): void {
    $bad = $current185;
    $bad[] = ['setting_id' => 20, 'key_name_bytes' => 'plugin_cache'];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDeletedTokenResumePlan($bad, $nextOneEightFive, 'plugin%', null, $token185));
};

$tests['utf16 nocase like rtrim current source nextOneEightFive rejects non integer rowid'] = static function (TestRunner $t) use ($current185, $nextOneEightFive, $token185): void {
    $bad = $current185;
    $bad[] = ['setting_id' => '20', 'key_name_bytes' => 'plugin_cache', 'text_encoding' => 1];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDeletedTokenResumePlan($bad, $nextOneEightFive, 'plugin%', null, $token185));
};

return $tests;
