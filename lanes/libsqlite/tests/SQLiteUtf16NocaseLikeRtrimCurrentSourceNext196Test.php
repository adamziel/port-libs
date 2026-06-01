<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc196 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row196 = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc196($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad196 = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current196 = [
    $row196(1, 'Plugin_Cache', 'UTF-16LE'),
    $row196(2, 'plugin_cache  ', 'UTF-16BE'),
    $row196(3, 'PLUGIN_CACHE', 'UTF-8'),
    $row196(4, "plugin_cache\t", 'UTF-16LE'),
    $row196(5, 'plugin_cache_alpha', 'UTF-16BE'),
    $row196(6, 'plugin_cache', 'UTF-16LE'),
    $row196(7, 'plugin_cache_zip', 'UTF-16BE'),
    $row196(8, 'plugin_cachezz', 'UTF-8'),
    $row196(9, 'theme_cache', 'UTF-16LE'),
    $bad196(10, "\x00\xd8", 2),
];
$nextOneNineSix = [
    $row196(1, 'plugin_cache', 'UTF-16BE'),
    $row196(2, 'Plugin_Cache   ', 'UTF-16LE'),
    $row196(3, 'plugin_cache', 'UTF-8'),
    $row196(4, "plugin_cache\t", 'UTF-16BE'),
    $row196(5, 'plugin_cache_alpha', 'UTF-16LE'),
    $row196(6, 'PLUGIN_CACHE  ', 'UTF-16BE'),
    $row196(11, 'plugin_cache', 'UTF-16LE'),
    $row196(12, 'plugin_cache_zip', 'UTF-16BE'),
    $bad196(13, "x\0y", 2),
];

$plan196 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $token = ['key' => 'plugin_cache', 'rowid' => 6],
    string $currentSource = 'main.app_settings@195',
    string $nextSource = 'main.app_settings@196',
    int $currentCookie = 195,
    int $nextCookie = 196,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDuplicatePeerResumePlan(
    $current ?? $current196,
    $next ?? $nextOneNineSix,
    'plugin!_cache',
    '!',
    $token,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt196 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases196 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneNineSix'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE LIKE ? ESCAPE ? /* duplicate comparison-key peers */'],
    'pattern' => ['pattern', 'plugin!_cache'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneNineTwo'],
    'current source' => ['currentSource', 'main.app_settings@195'],
    'next source' => ['nextSource', 'main.app_settings@196'],
    'current cookie' => ['currentSchemaCookie', 195],
    'next cookie' => ['nextSchemaCookie', 196],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['rangeLowerInclusive', 'plugin_cache'],
    'range upper' => ['rangeUpperBound', 'plugin_cachf'],
    'prefix cursor' => ['usesPrefixRangeCursor', true],
    'token key' => ['resumeToken.key', 'plugin_cache'],
    'token rowid' => ['resumeToken.rowid', 6],
    'token canonical' => ['resumeToken.normalizationReasons', []],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 6, 4, 5, 7, 8]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 3, 6, 11, 4, 5, 12]],
    'current matched' => ['currentMatchedRowids', [1, 2, 3, 6]],
    'next matched' => ['nextMatchedRowids', [1, 2, 3, 6, 11]],
    'current false positives' => ['currentRangeFalsePositiveRowids', [4, 5, 7, 8]],
    'next false positives' => ['nextRangeFalsePositiveRowids', [4, 5, 12]],
    'current duplicate peers' => ['currentDuplicatePeerRowids', [1, 2, 3, 6]],
    'next duplicate peers' => ['nextDuplicatePeerRowids', [1, 2, 3, 6, 11]],
    'current peers before' => ['currentDuplicatePeersBeforeOrAtTokenRowids', [1, 2, 3, 6]],
    'next peers before' => ['nextDuplicatePeersBeforeOrAtTokenRowids', [1, 2, 3, 6]],
    'current peer matches' => ['currentDuplicatePeerMatchedRowids', [1, 2, 3, 6]],
    'next peer matches' => ['nextDuplicatePeerMatchedRowids', [1, 2, 3, 6, 11]],
    'current peer false positives' => ['currentDuplicatePeerFalsePositiveRowids', []],
    'next peer false positives' => ['nextDuplicatePeerFalsePositiveRowids', []],
    'next peers after token' => ['nextDuplicatePeersAfterTokenRowids', [11]],
    'peer unsafe matched' => ['duplicatePeerUnsafeReasons.0', 'duplicate-key-matched-peers-changed'],
    'candidate unsafe source' => ['candidateTokenUnsafeReasons.0', 'source-or-schema-changed'],
    'candidate unsafe malformed' => ['candidateTokenUnsafeReasons.1', 'malformed-text'],
    'candidate unsafe duplicate matched' => ['candidateTokenUnsafeReasons.2', 'duplicate-key-matched-peers-changed'],
    'resume unsafe' => ['candidateTokenResumeSafe', false],
    'must reprepare' => ['mustReprepareBeforeCandidateTokenResume', true],
    'mode' => ['replayPlanMode', 'reprepare-from-range-start'],
    'replay all candidates' => ['replayPlanRowids', [1, 2, 3, 6, 11, 4, 5, 12]],
    'current row four tab key' => ['currentNocaseKeys.4', "plugin_cache\t"],
    'next row eleven duplicate key' => ['nextNocaseKeys.11', 'plugin_cache'],
    'row two rtrim' => ['nextRtrimTexts.2', 'Plugin_Cache'],
    'row three matched text' => ['currentMatchedTexts.3', 'PLUGIN_CACHE'],
    'next malformed' => ['nextMalformedRowids', [13]],
    'current malformed' => ['currentMalformedRowids', [10]],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'duplicate rowid order' => ['duplicatePeersOrderedByRowidWithinComparisonKey', true],
    'residual peer recheck' => ['residualRecheckRequiredForDuplicatePeers', false],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency peer' => ['dependencies.2', 'sqlite-rtrim-duplicate-peer-key'],
    'dependency residual' => ['dependencies.3', 'sqlite-like-residual-recheck'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nextoneNineSix'],
];

foreach ($cases196 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneNineSix ' . $name] = static function (TestRunner $t) use ($plan196, $valueAt196, $path, $expected): void {
        $t->same($expected, $valueAt196($plan196(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneNineSix stable duplicate peers resume after token'] = static function (TestRunner $t) use ($row196): void {
    $rows = [
        $row196(1, 'Plugin_Cache', 'UTF-16LE'),
        $row196(2, 'plugin_cache  ', 'UTF-16BE'),
        $row196(3, 'PLUGIN_CACHE', 'UTF-8'),
        $row196(6, 'plugin_cache', 'UTF-16LE'),
        $row196(11, 'plugin_cache', 'UTF-16BE'),
        $row196(12, 'plugin_cache_alpha', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDuplicatePeerResumePlan(
        $rows,
        $rows,
        'plugin!_cache',
        '!',
        ['key' => 'plugin_cache', 'rowid' => 6],
        'stable',
        'stable',
        196,
        196,
    );

    $t->same([1, 2, 3, 6, 11], $result['currentDuplicatePeerRowids']);
    $t->same([1, 2, 3, 6], $result['currentDuplicatePeersBeforeOrAtTokenRowids']);
    $t->same([11], $result['nextDuplicatePeersAfterTokenRowids']);
    $t->same([], $result['duplicatePeerUnsafeReasons']);
    $t->same([], $result['candidateTokenUnsafeReasons']);
    $t->same(true, $result['candidateTokenResumeSafe']);
    $t->same('continue-after-duplicate-peer-token', $result['replayPlanMode']);
    $t->same([11, 12], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneNineSix duplicate peer inserted before token blocks resume'] = static function (TestRunner $t) use ($row196): void {
    $current = [
        $row196(2, 'plugin_cache  ', 'UTF-16BE'),
        $row196(6, 'plugin_cache', 'UTF-16LE'),
        $row196(12, 'plugin_cache_alpha', 'UTF-16LE'),
    ];
    $next = [
        $row196(2, 'plugin_cache  ', 'UTF-16BE'),
        $row196(4, 'PLUGIN_CACHE', 'UTF-8'),
        $row196(6, 'plugin_cache', 'UTF-16LE'),
        $row196(12, 'plugin_cache_alpha', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDuplicatePeerResumePlan(
        $current,
        $next,
        'plugin!_cache',
        '!',
        ['key' => 'plugin_cache', 'rowid' => 6],
        'stable',
        'stable',
        196,
        196,
    );

    $t->same([2, 6], $result['currentDuplicatePeersBeforeOrAtTokenRowids']);
    $t->same([2, 4, 6], $result['nextDuplicatePeersBeforeOrAtTokenRowids']);
    $t->same(['candidate-before-token-changed', 'matched-before-token-changed', 'duplicate-key-peers-before-token-changed', 'duplicate-key-matched-peers-changed'], $result['candidateTokenUnsafeReasons']);
    $t->same(false, $result['candidateTokenResumeSafe']);
};

$tests['utf16 nocase like rtrim current source nextOneNineSix false positive duplicate peer change blocks resume'] = static function (TestRunner $t) use ($row196): void {
    $current = [
        $row196(1, 'plugin_cache', 'UTF-16LE'),
        $row196(6, 'plugin_cache', 'UTF-16BE'),
        $row196(8, "plugin_cache\t", 'UTF-16LE'),
    ];
    $next = [
        $row196(1, 'plugin_cache', 'UTF-16LE'),
        $row196(6, "plugin_cache\t", 'UTF-16BE'),
        $row196(8, "plugin_cache\t", 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDuplicatePeerResumePlan(
        $current,
        $next,
        'plugin!_cache',
        '!',
        ['key' => "plugin_cache\t", 'rowid' => 8],
        'stable',
        'stable',
        196,
        196,
    );

    $t->same([], $result['currentDuplicatePeerMatchedRowids']);
    $t->same([], $result['nextDuplicatePeerMatchedRowids']);
    $t->same([8], $result['currentDuplicatePeerFalsePositiveRowids']);
    $t->same([6, 8], $result['nextDuplicatePeerFalsePositiveRowids']);
    $t->same(['false-positive-before-token-changed', 'matched-before-token-changed', 'duplicate-key-peers-before-token-changed', 'duplicate-key-false-positive-peers-changed'], $result['candidateTokenUnsafeReasons']);
};

$tests['utf16 nocase like rtrim current source nextOneNineSix canonical token key is unsafe'] = static function (TestRunner $t) use ($row196): void {
    $rows = [
        $row196(1, 'Plugin_Cache', 'UTF-16LE'),
        $row196(2, 'plugin_cache_zip', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDuplicatePeerResumePlan(
        $rows,
        $rows,
        'plugin!_cache',
        '!',
        ['key' => 'PLUGIN_CACHE  ', 'rowid' => 1],
        'stable',
        'stable',
        196,
        196,
    );

    $t->same('plugin_cache', $result['resumeToken']['key']);
    $t->same(['token-key-not-canonical'], $result['resumeToken']['normalizationReasons']);
    $t->same(['yield-token-not-canonical'], $result['candidateTokenUnsafeReasons']);
};

$tests['utf16 nocase like rtrim current source nextOneNineSix missing token blocks duplicate peer resume'] = static function (TestRunner $t) use ($row196): void {
    $rows = [
        $row196(1, 'plugin_cache', 'UTF-16LE'),
        $row196(2, 'plugin_cache', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDuplicatePeerResumePlan(
        $rows,
        $rows,
        'plugin!_cache',
        '!',
        null,
        'stable',
        'stable',
        196,
        196,
    );

    $t->same(null, $result['resumeToken']);
    $t->same([], $result['currentDuplicatePeerRowids']);
    $t->same(['yield-token-missing'], $result['candidateTokenUnsafeReasons']);
    $t->same('reprepare-from-range-start', $result['replayPlanMode']);
};

$tests['utf16 nocase like rtrim current source nextOneNineSix rejects malformed token key'] = static function (TestRunner $t) use ($row196): void {
    $rows = [$row196(1, 'plugin_cache', 'UTF-16LE')];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDuplicatePeerResumePlan(
        $rows,
        $rows,
        'plugin%',
        null,
        ['key' => 196, 'rowid' => 1],
    ));
};

$tests['utf16 nocase like rtrim current source nextOneNineSix rejects malformed token rowid'] = static function (TestRunner $t) use ($row196): void {
    $rows = [$row196(1, 'plugin_cache', 'UTF-16LE')];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDuplicatePeerResumePlan(
        $rows,
        $rows,
        'plugin%',
        null,
        ['key' => 'plugin_cache', 'rowid' => '1'],
    ));
};

return $tests;
