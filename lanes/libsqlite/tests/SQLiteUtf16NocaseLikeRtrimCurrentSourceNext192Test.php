<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc192 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row192 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc192($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad192 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current192 = [
    $row192(1, 'Plugin_Cache   ', 'UTF-16LE'),
    $row192(2, 'plugin_cache', 'UTF-16BE'),
    $row192(3, "plugin_cache\t", 'UTF-16LE'),
    $row192(4, 'plugin_cache_alpha', 'UTF-16BE'),
    $row192(5, 'plugin_cache_new', 'UTF-8'),
    $row192(6, 'plugin_cache_old', 'UTF-16LE'),
    $row192(7, 'plugin_caches', 'UTF-16BE'),
    $row192(8, 'theme_cache', 'UTF-16LE'),
    $bad192(9, "\x00\xd8", 2),
];
$nextOneNineTwo = [
    $row192(1, 'plugin_cache', 'UTF-16BE'),
    $row192(2, 'PLUGIN_CACHE  ', 'UTF-16LE'),
    $row192(3, "plugin_cache\t", 'UTF-16BE'),
    $row192(4, 'plugin_cache_alpha', 'UTF-16LE'),
    $row192(5, 'plugin_cache', 'UTF-8'),
    $row192(6, 'plugin_cache_old', 'UTF-16BE'),
    $row192(10, 'Plugin_Cache   ', 'UTF-16LE'),
    $row192(11, 'plugin_cache_zip', 'UTF-16BE'),
    $bad192(12, "x\0y", 2),
];

$plan192 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $token = ['key' => 'plugin_cache_old', 'rowid' => 6],
    string $currentSource = 'main.app_settings@191',
    string $nextSource = 'main.app_settings@192',
    int $currentCookie = 191,
    int $nextCookie = 192,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCandidateTokenPlan(
    $current ?? $current192,
    $next ?? $nextOneNineTwo,
    'plugin!_cache',
    '!',
    $token,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt192 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases192 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneNineTwo'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* candidate residual token */'],
    'pattern' => ['pattern', 'plugin!_cache'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneEightThree'],
    'current source' => ['currentSource', 'main.app_settings@191'],
    'next source' => ['nextSource', 'main.app_settings@192'],
    'current cookie' => ['currentSchemaCookie', 191],
    'next cookie' => ['nextSchemaCookie', 192],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['rangeLowerInclusive', 'plugin_cache'],
    'range upper' => ['rangeUpperBound', 'plugin_cachf'],
    'prefix cursor' => ['usesPrefixRangeCursor', true],
    'token key' => ['resumeToken.key', 'plugin_cache_old'],
    'token rowid' => ['resumeToken.rowid', 6],
    'token canonical' => ['resumeToken.normalizationReasons', []],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 4, 5, 6, 7]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 5, 10, 3, 4, 6, 11]],
    'current matched' => ['currentMatchedRowids', [1, 2]],
    'next matched' => ['nextMatchedRowids', [1, 2, 5, 10]],
    'current false positives' => ['currentRangeFalsePositiveRowids', [3, 4, 5, 6, 7]],
    'next false positives' => ['nextRangeFalsePositiveRowids', [3, 4, 6, 11]],
    'current before token' => ['currentCandidateBeforeOrAtTokenRowids', [1, 2, 3, 4, 5, 6]],
    'next before token' => ['nextCandidateBeforeOrAtTokenRowids', [1, 2, 5, 10, 3, 4, 6]],
    'current false before token' => ['currentFalsePositiveBeforeOrAtTokenRowids', [3, 4, 5, 6]],
    'next false before token' => ['nextFalsePositiveBeforeOrAtTokenRowids', [3, 4, 6]],
    'current matched before token' => ['currentMatchedBeforeOrAtTokenRowids', [1, 2]],
    'next matched before token' => ['nextMatchedBeforeOrAtTokenRowids', [1, 2, 5, 10]],
    'next replay after unsafe token' => ['nextReplayCandidateRowidsAfterToken', [11]],
    'current row three rtrim keeps tab' => ['currentRtrimTexts.3', "plugin_cache\t"],
    'next row five now exact' => ['nextMatchedTexts.5', 'plugin_cache'],
    'next row six false positive key' => ['nextNocaseKeys.6', 'plugin_cache_old'],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [12]],
    'unsafe source' => ['candidateTokenUnsafeReasons.0', 'source-or-schema-changed'],
    'unsafe malformed' => ['candidateTokenUnsafeReasons.1', 'malformed-text'],
    'unsafe candidate before' => ['candidateTokenUnsafeReasons.2', 'candidate-before-token-changed'],
    'unsafe false before' => ['candidateTokenUnsafeReasons.3', 'false-positive-before-token-changed'],
    'unsafe matched before' => ['candidateTokenUnsafeReasons.4', 'matched-before-token-changed'],
    'resume unsafe' => ['candidateTokenResumeSafe', false],
    'must reprepare' => ['mustReprepareBeforeCandidateTokenResume', true],
    'mode' => ['replayPlanMode', 'reprepare-from-range-start'],
    'replay all next candidates' => ['replayPlanRowids', [1, 2, 5, 10, 3, 4, 6, 11]],
    'residual recheck' => ['residualRecheckRequiredForCandidates', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'residual after rtrim' => ['likeResidualAppliesAfterRtrim', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency token' => ['dependencies.2', 'sqlite-rtrim-candidate-token'],
    'dependency residual' => ['dependencies.3', 'sqlite-like-residual-recheck'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nextoneNineTwo'],
];

foreach ($cases192 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneNineTwo ' . $name] = static function (TestRunner $t) use ($plan192, $valueAt192, $path, $expected): void {
        $t->same($expected, $valueAt192($plan192(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneNineTwo stable false-positive token can resume after token'] = static function (TestRunner $t) use ($row192): void {
    $rows = [
        $row192(1, 'Plugin_Cache', 'UTF-16LE'),
        $row192(2, "plugin_cache\t", 'UTF-16BE'),
        $row192(3, 'plugin_cache_alpha', 'UTF-16LE'),
        $row192(4, 'plugin_cache_zip', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCandidateTokenPlan(
        $rows,
        $rows,
        'plugin!_cache',
        '!',
        ['key' => "plugin_cache\t", 'rowid' => 2],
        'stable',
        'stable',
        192,
        192,
    );

    $t->same([1, 2], $result['currentCandidateBeforeOrAtTokenRowids']);
    $t->same([2], $result['currentFalsePositiveBeforeOrAtTokenRowids']);
    $t->same([1], $result['currentMatchedBeforeOrAtTokenRowids']);
    $t->same([], $result['candidateTokenUnsafeReasons']);
    $t->same(true, $result['candidateTokenResumeSafe']);
    $t->same('continue-after-candidate-token', $result['replayPlanMode']);
    $t->same([3, 4], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneNineTwo matched row becoming false positive forces reprepare'] = static function (TestRunner $t) use ($row192): void {
    $current = [$row192(1, 'plugin_cache', 'UTF-16LE'), $row192(2, 'plugin_cache_alpha', 'UTF-16LE')];
    $next = [$row192(1, "plugin_cache\t", 'UTF-16BE'), $row192(2, 'plugin_cache_alpha', 'UTF-16LE')];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCandidateTokenPlan(
        $current,
        $next,
        'plugin!_cache',
        '!',
        ['key' => 'plugin_cache_alpha', 'rowid' => 2],
        'stable',
        'stable',
        192,
        192,
    );

    $t->same([2], $result['currentFalsePositiveBeforeOrAtTokenRowids']);
    $t->same([1, 2], $result['nextFalsePositiveBeforeOrAtTokenRowids']);
    $t->same(['false-positive-before-token-changed', 'matched-before-token-changed'], $result['candidateTokenUnsafeReasons']);
    $t->same('false-positive-before-token-changed', $result['candidateTokenUnsafeReasons'][0]);
    $t->same(false, $result['candidateTokenResumeSafe']);
};

$tests['utf16 nocase like rtrim current source nextOneNineTwo canonicalizes token key'] = static function (TestRunner $t) use ($row192): void {
    $rows = [$row192(1, 'Plugin_Cache', 'UTF-16LE'), $row192(2, 'plugin_cache_zip', 'UTF-16BE')];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCandidateTokenPlan(
        $rows,
        $rows,
        'plugin!_cache',
        '!',
        ['key' => 'PLUGIN_CACHE  ', 'rowid' => 1],
        'stable',
        'stable',
        192,
        192,
    );

    $t->same('plugin_cache', $result['resumeToken']['key']);
    $t->same(['token-key-not-canonical'], $result['resumeToken']['normalizationReasons']);
    $t->same(['yield-token-not-canonical'], $result['candidateTokenUnsafeReasons']);
};

$tests['utf16 nocase like rtrim current source nextOneNineTwo missing token blocks resume'] = static function (TestRunner $t) use ($row192): void {
    $rows = [$row192(1, 'plugin_cache', 'UTF-16LE')];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCandidateTokenPlan(
        $rows,
        $rows,
        'plugin!_cache',
        '!',
        null,
        'stable',
        'stable',
        192,
        192,
    );

    $t->same(['yield-token-missing'], $result['candidateTokenUnsafeReasons']);
    $t->same(false, $result['candidateTokenResumeSafe']);
    $t->same('reprepare-from-range-start', $result['replayPlanMode']);
};

$tests['utf16 nocase like rtrim current source nextOneNineTwo rejects malformed token key'] = static function (TestRunner $t) use ($row192): void {
    $rows = [$row192(1, 'plugin_cache', 'UTF-16LE')];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCandidateTokenPlan(
        $rows,
        $rows,
        'plugin%',
        null,
        ['key' => 192, 'rowid' => 1],
    ));
};

$tests['utf16 nocase like rtrim current source nextOneNineTwo rejects malformed token rowid'] = static function (TestRunner $t) use ($row192): void {
    $rows = [$row192(1, 'plugin_cache', 'UTF-16LE')];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCandidateTokenPlan(
        $rows,
        $rows,
        'plugin%',
        null,
        ['key' => 'plugin_cache', 'rowid' => '1'],
    ));
};

return $tests;
