<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc215 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row215 = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc215($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad215 = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current215 = [
    $row215(1, 'Plugin_Cache', 'UTF-16LE'),
    $row215(2, "plugin_cache\0shadow", 'UTF-16BE'),
    $row215(3, 'plugin_cache ', 'UTF-16LE'),
    $row215(4, "plugin_cache\0Shadow", 'UTF-8'),
    $row215(5, 'plugin_cache_extra', 'UTF-16BE'),
    $row215(6, 'plugin_case', 'UTF-16LE'),
    $row215(7, "plugin_cache\0", 'UTF-16LE'),
    $bad215(8, "\x00\xd8", 2),
];
$nextTwoOneFive = [
    $row215(1, 'Plugin_Cache', 'UTF-16BE'),
    $row215(2, "plugin_cache\0shadow", 'UTF-16LE'),
    $row215(3, 'plugin_cache', 'UTF-16BE'),
    $row215(4, "plugin_cache\0Shadow", 'UTF-8'),
    $row215(5, 'plugin_cache_extra', 'UTF-16LE'),
    $row215(7, "plugin_cache\0", 'UTF-16BE'),
    $row215(9, "plugin_cache\0later", 'UTF-16LE'),
    $row215(10, 'PLUGIN_CACHE_NEW', 'UTF-16BE'),
    $bad215(11, "\x00\xd8", 2),
];

$plan215 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $token = ['key' => "plugin_cache\0shadow", 'rowid' => 4],
    string $currentSource = 'main.app_settings@214',
    string $nextSource = 'main.app_settings@215',
    int $currentCookie = 214,
    int $nextCookie = 215,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEmbeddedNulTokenPlan(
    $current ?? $current215,
    $next ?? $nextTwoOneFive,
    'plugin!_cache%',
    '!',
    $token,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt215 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases215 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoOneFive'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE LIKE ? ESCAPE ? /* embedded NUL token fence */'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.app_settings@214'],
    'next source' => ['nextSource', 'main.app_settings@215'],
    'current cookie' => ['currentSchemaCookie', 214],
    'next cookie' => ['nextSchemaCookie', 215],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['rangeLowerInclusive', 'plugin_cache'],
    'range upper' => ['rangeUpperBound', 'plugin_cachf'],
    'current candidates' => ['currentCandidateRowids', [1, 3, 7, 2, 4, 5]],
    'next candidates' => ['nextCandidateRowids', [1, 3, 7, 9, 2, 4, 5, 10]],
    'current matched' => ['currentMatchedRowids', [1, 3, 7, 2, 4, 5]],
    'next matched' => ['nextMatchedRowids', [1, 3, 7, 9, 2, 4, 5, 10]],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [9, 10]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'current before token' => ['currentCandidateBeforeOrAtTokenRowids', [1, 3, 7, 2, 4]],
    'next before token' => ['nextCandidateBeforeOrAtTokenRowids', [1, 3, 7, 9, 2, 4]],
    'current replay after' => ['currentReplayAfterTokenRowids', [5]],
    'next replay after' => ['nextReplayAfterTokenRowids', [5, 10]],
    'current matched before' => ['currentMatchedBeforeTokenRowids', [1, 3, 7, 2, 4]],
    'next matched before' => ['nextMatchedBeforeTokenRowids', [1, 3, 7, 9, 2, 4]],
    'current nul rowids' => ['currentEmbeddedNulRowids', [2, 4, 7]],
    'next nul rowids' => ['nextEmbeddedNulRowids', [2, 4, 7, 9]],
    'truncated collisions' => ['embeddedNulTruncatedKeyCollisionRowids', [2, 4, 7, 9]],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [11]],
    'current error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.11', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current nul text' => ['currentRtrimTexts.2', "plugin_cache\0shadow"],
    'next nul text' => ['nextRtrimTexts.9', "plugin_cache\0later"],
    'current nul key hex' => ['currentNocaseKeysHex.2', '706c7567696e5f636163686500736861646f77'],
    'next nul key hex' => ['nextNocaseKeysHex.9', '706c7567696e5f6361636865006c61746572'],
    'current truncated key' => ['currentTruncatedNocaseKeys.2', 'plugin_cache'],
    'next truncated key' => ['nextTruncatedNocaseKeys.9', 'plugin_cache'],
    'token key' => ['resumeToken.key', "plugin_cache\0shadow"],
    'token rowid' => ['resumeToken.rowid', 4],
    'token canonical' => ['resumeToken.normalizationReasons', []],
    'unsafe source' => ['candidateTokenUnsafeReasons.0', 'source-or-schema-changed'],
    'unsafe malformed' => ['candidateTokenUnsafeReasons.1', 'malformed-text'],
    'unsafe before' => ['candidateTokenUnsafeReasons.2', 'candidate-before-token-changed'],
    'unsafe matched' => ['candidateTokenUnsafeReasons.3', 'matched-before-token-changed'],
    'unsafe collision' => ['candidateTokenUnsafeReasons.4', 'embedded-nul-truncated-key-collision'],
    'resume unsafe' => ['candidateTokenResumeSafe', false],
    'must reprepare' => ['mustReprepareBeforeCandidateTokenResume', true],
    'mode' => ['replayPlanMode', 'reprepare-from-range-start'],
    'replay all next' => ['replayPlanRowids', [1, 3, 7, 9, 2, 4, 5, 10]],
    'preserve nul' => ['embeddedNulPreservedInTextKeys', true],
    'not c string' => ['embeddedNulNotCStringTerminator', true],
    'full residual' => ['likeResidualChecksFullSqlText', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency nul' => ['dependencies.3', 'sqlite-embedded-nul-text-token'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoOneFive'],
];

foreach ($cases215 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoOneFive ' . $name] = static function (TestRunner $t) use ($plan215, $valueAt215, $path, $expected): void {
        $t->same($expected, $valueAt215($plan215(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoOneFive stable embedded nul token can resume by full key'] = static function (TestRunner $t) use ($row215): void {
    $rows = [
        $row215(1, 'plugin_cache', 'UTF-16LE'),
        $row215(2, "plugin_cache\0shadow", 'UTF-16BE'),
        $row215(3, 'plugin_cache_extra', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEmbeddedNulTokenPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        ['key' => "plugin_cache\0shadow", 'rowid' => 2],
        'stable',
        'stable',
        215,
        215,
    );

    $t->same([1, 2], $result['currentCandidateBeforeOrAtTokenRowids']);
    $t->same([3], $result['replayPlanRowids']);
    $t->same([], $result['candidateTokenUnsafeReasons']);
    $t->same(true, $result['candidateTokenResumeSafe']);
    $t->same('continue-after-embedded-nul-safe-token', $result['replayPlanMode']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneFive canonicalizes resume token before comparing'] = static function (TestRunner $t) use ($row215): void {
    $rows = [
        $row215(1, "Plugin_Cache\0Shadow", 'UTF-16LE'),
        $row215(2, 'plugin_cache_zip', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEmbeddedNulTokenPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        ['key' => "PLUGIN_CACHE\0SHADOW  ", 'rowid' => 1],
        'stable',
        'stable',
        215,
        215,
    );

    $t->same("plugin_cache\0shadow", $result['resumeToken']['key']);
    $t->same(['token-key-not-canonical'], $result['resumeToken']['normalizationReasons']);
    $t->same(['yield-token-not-canonical'], $result['candidateTokenUnsafeReasons']);
    $t->same(false, $result['candidateTokenResumeSafe']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneFive null token replays full next range'] = static function (TestRunner $t) use ($row215): void {
    $rows = [
        $row215(1, 'plugin_cache', 'UTF-16LE'),
        $row215(2, "plugin_cache\0later", 'UTF-16BE'),
        $row215(3, 'theme_cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEmbeddedNulTokenPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        null,
        'stable',
        'stable',
        215,
        215,
    );

    $t->same([], $result['currentCandidateBeforeOrAtTokenRowids']);
    $t->same([1, 2], $result['nextReplayAfterTokenRowids']);
    $t->same([1, 2], $result['replayPlanRowids']);
    $t->same(true, $result['candidateTokenResumeSafe']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneFive rejects missing key bytes'] = static function (TestRunner $t) use ($nextTwoOneFive): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEmbeddedNulTokenPlan([
        ['setting_id' => 1, 'text_encoding' => 1],
    ], $nextTwoOneFive));
};

return $tests;
