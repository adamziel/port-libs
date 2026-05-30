<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc188 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row188 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc188($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad188 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current188 = [
    $row188(1, 'Plugin_Cache  ', 'UTF-16LE'),
    $row188(2, 'plugin_cache', 'UTF-16BE'),
    $row188(3, 'PLUGIN_CACHE  ', 'UTF-8'),
    $row188(4, 'plugin_cache_alpha', 'UTF-16BE'),
    $row188(5, 'plugin_cache_beta', 'UTF-16LE'),
    $row188(6, "plugin_cache\t", 'UTF-16LE'),
    $row188(7, 'theme_cache', 'UTF-16BE'),
];
$nextOneEightEight = [
    $row188(1, 'Plugin_Cache  ', 'UTF-16LE'),
    $row188(2, 'theme_cache_reused_rowid', 'UTF-16BE'),
    $row188(3, 'PLUGIN_CACHE  ', 'UTF-8'),
    $row188(4, 'plugin_cache_alpha', 'UTF-16BE'),
    $row188(5, 'plugin_cache_beta', 'UTF-16LE'),
    $row188(6, "plugin_cache\t", 'UTF-16LE'),
    $row188(8, 'plugin_cache_delta', 'UTF-16BE'),
];
$tokenBytes188 = $enc188('plugin_cache', 'UTF-16BE');
$token188 = [
    'key' => 'plugin_cache',
    'rowid' => 2,
    'bytesHex' => bin2hex($tokenBytes188),
    'encoding' => 'UTF-16BE',
];

$plan188 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $token = null,
    string $currentSource = 'stable',
    string $nextSource = 'stable',
    int $currentCookie = 188,
    int $nextCookie = 188,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyReusedRowidResumePlan(
    $current ?? $current188,
    $next ?? $nextOneEightEight,
    'plugin!_cache%',
    '!',
    $token ?? $token188,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt188 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases188 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneEightEight'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneEightFive'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['range.lowerInclusive', 'plugin_cache'],
    'range upper' => ['range.upperBound', 'plugin_cachf'],
    'current source' => ['currentSource', 'stable'],
    'next source' => ['nextSource', 'stable'],
    'current cookie' => ['currentSchemaCookie', 188],
    'next cookie' => ['nextSchemaCookie', 188],
    'token key' => ['normalizedLastYielded.key', 'plugin_cache'],
    'token rowid' => ['normalizedLastYielded.rowid', 2],
    'token bytes' => ['normalizedLastYielded.bytesHex', bin2hex($tokenBytes188)],
    'token encoding' => ['normalizedLastYielded.encoding', 'UTF-16BE'],
    'base deleted token rowid' => ['deletedTokenRowid', 2],
    'current matched rowids' => ['currentMatchedRowids', [1, 2, 3, 6, 4, 5]],
    'next matched rowids' => ['nextMatchedRowids', [1, 3, 6, 4, 5, 8]],
    'next rowid probe rowid' => ['nextRowidProbe.rowid', 2],
    'next rowid probe text' => ['nextRowidProbe.text', 'theme_cache_reused_rowid'],
    'next rowid probe rtrim' => ['nextRowidProbe.rtrimText', 'theme_cache_reused_rowid'],
    'next rowid probe key' => ['nextRowidProbe.key', 'theme_cache_reused_rowid'],
    'next rowid probe encoding' => ['nextRowidProbe.encoding', 'UTF-16BE'],
    'next rowid probe bytes' => ['nextRowidProbe.bytesHex', bin2hex($enc188('theme_cache_reused_rowid', 'UTF-16BE'))],
    'next rowid outside range' => ['nextRowidProbe.insideRange', false],
    'next rowid outside residual' => ['nextRowidProbe.matchesResidual', false],
    'next rowid not same token' => ['nextRowidProbe.sameToken', false],
    'next rowid no decode error' => ['nextRowidProbe.decodeError', null],
    'reuse detected' => ['rowidReuseDetected', true],
    'reuse not safe' => ['rowidReuseSafeForDeletedTokenResume', false],
    'resume unsafe reasons' => ['resumeUnsafeReasons', ['yield-token-rowid-reused', 'yield-token-rowid-reused-outside-range', 'yield-token-rowid-reused-outside-like-residual']],
    'resume unsafe' => ['deletedTokenResumeSafe', false],
    'must reprepare' => ['mustReprepareBeforeDeletedTokenResume', true],
    'safe false' => ['safeToResumeAfterDeletedToken', false],
    'mode' => ['replayPlanMode', 'reprepare-from-range-start-after-rowid-reuse'],
    'replay rowids restart' => ['replayPlanRowids', [1, 3, 6, 4, 5, 8]],
    'rowid reuse invalidates boundary' => ['rowidReuseInvalidatesBeforeKeyBoundary', true],
    'rowid reuse checked' => ['rowidReuseCheckedBeforeDeletedTokenResume', true],
    'row one rtrim' => ['nextMatchedRtrimText.1', 'Plugin_Cache'],
    'row six tab retained' => ['nextMatchedRtrimText.6', "plugin_cache\t"],
    'row eight key' => ['nextMatchedKeys.8', 'plugin_cache_delta'],
    'rtrim ascii space only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii only' => ['nocaseFoldsAsciiOnly', true],
    'like residual after rtrim' => ['likeResidualAppliesAfterRtrim', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency rowid reuse' => ['dependencies.3', 'sqlite-rowid-reuse-current-source-fence'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nextoneEightEight'],
];

foreach ($cases188 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneEightEight ' . $name] = static function (TestRunner $t) use ($plan188, $valueAt188, $path, $expected): void {
        $t->same($expected, $valueAt188($plan188(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneEightEight stable deleted rowid remains resumable'] = static function (TestRunner $t) use ($current188, $row188, $token188): void {
    $next = [
        $row188(1, 'Plugin_Cache  ', 'UTF-16LE'),
        $row188(3, 'PLUGIN_CACHE  ', 'UTF-8'),
        $row188(4, 'plugin_cache_alpha', 'UTF-16BE'),
        $row188(5, 'plugin_cache_beta', 'UTF-16LE'),
        $row188(6, "plugin_cache\t", 'UTF-16LE'),
        $row188(8, 'plugin_cache_delta', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyReusedRowidResumePlan($current188, $next, 'plugin!_cache%', '!', $token188, 'stable', 'stable', 188, 188);
    $t->same(null, $result['nextRowidProbe']);
    $t->same(false, $result['rowidReuseDetected']);
    $t->same([], $result['resumeUnsafeReasons']);
    $t->same(true, $result['deletedTokenResumeSafe']);
    $t->same('continue-after-deleted-key-rowid-token', $result['replayPlanMode']);
    $t->same([3, 6, 4, 5, 8], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightEight rowid reused inside range but residual false reparses'] = static function (TestRunner $t) use ($current188, $nextOneEightEight, $row188, $token188): void {
    $next = $nextOneEightEight;
    $next[1] = $row188(2, "plugin_cache\tmiss", 'UTF-16LE');
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyReusedRowidResumePlan($current188, $next, 'plugin!_cache', '!', $token188, 'stable', 'stable', 188, 188);
    $t->same(true, $result['nextRowidProbe']['insideRange']);
    $t->same(false, $result['nextRowidProbe']['matchesResidual']);
    $t->same(['yield-token-rowid-reused', 'yield-token-rowid-reused-outside-like-residual'], $result['resumeUnsafeReasons']);
    $t->same('reprepare-from-range-start-after-rowid-reuse', $result['replayPlanMode']);
};

$tests['utf16 nocase like rtrim current source nextOneEightEight malformed reused rowid reparses'] = static function (TestRunner $t) use ($current188, $nextOneEightEight, $bad188, $token188): void {
    $next = $nextOneEightEight;
    $next[1] = $bad188(2, "\x00\xd8", 2);
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyReusedRowidResumePlan($current188, $next, 'plugin!_cache%', '!', $token188, 'stable', 'stable', 188, 188);
    $t->same(false, $result['nextRowidProbe']['insideRange']);
    $t->same(false, $result['nextRowidProbe']['matchesResidual']);
    $t->same('SQLite encoding source UTF-16 text payload ends with a high surrogate', $result['nextRowidProbe']['decodeError']);
    $t->same(['malformed-text', 'yield-token-rowid-reused', 'yield-token-rowid-reused-outside-range', 'yield-token-rowid-reused-outside-like-residual', 'yield-token-rowid-reused-malformed'], $result['resumeUnsafeReasons']);
    $t->same([2], $result['nextMalformedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightEight source change reason is preserved'] = static function (TestRunner $t) use ($plan188): void {
    $result = $plan188(null, null, null, 'main.app_settings@187', 'main.app_settings@188', 187, 188);
    $t->same('source-or-schema-changed', $result['resumeUnsafeReasons'][0]);
    $t->true(in_array('yield-token-rowid-reused', $result['resumeUnsafeReasons'], true));
    $t->same(false, $result['deletedTokenResumeSafe']);
};

$tests['utf16 nocase like rtrim current source nextOneEightEight rejects bad row shape'] = static function (TestRunner $t) use ($current188, $nextOneEightEight, $token188): void {
    $bad = $nextOneEightEight;
    $bad[] = ['option_id' => '20', 'option_name_bytes' => 'plugin_cache', 'text_encoding' => 1];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyReusedRowidResumePlan($current188, $bad, 'plugin%', null, $token188));
};

return $tests;
