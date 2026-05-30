<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc189 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row189 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc189($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad189 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current189 = [
    $row189(1, 'Plugin_Cache', 'UTF-16LE'),
    $row189(2, 'plugin_cache  ', 'UTF-16BE'),
    $row189(3, 'PLUGIN_CACHE', 'UTF-8'),
    $row189(4, "plugin_cache\t", 'UTF-16LE'),
    $row189(5, 'plugin_cache_alpha', 'UTF-16BE'),
    $row189(6, 'plugin_cache_beta', 'UTF-16LE'),
    $row189(7, 'plugin_cache%literal', 'UTF-16BE'),
    $row189(8, 'theme_cache', 'UTF-16LE'),
    $bad189(9, "\x00\xd8", 2),
];
$nextOneEightNine = [
    $row189(1, 'plugin_cache ', 'UTF-16BE'),
    $row189(2, 'plugin_cache   ', 'UTF-16LE'),
    $row189(3, 'PLUGIN_CACHE_ARCHIVE', 'UTF-8'),
    $row189(4, "plugin_cache\t", 'UTF-16BE'),
    $row189(5, 'plugin_cache_alpha  ', 'UTF-16LE'),
    $row189(6, 'plugin_cache_beta', 'UTF-16BE'),
    $row189(7, 'plugin_other', 'UTF-16BE'),
    $row189(10, 'Plugin_Cache', 'UTF-16LE'),
    $row189(11, 'plugin_cache_delta', 'UTF-16BE'),
    $bad189(12, "x\0y", 2),
];

$plan189 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $token = ['key' => 'plugin_cache', 'rowid' => 2],
    string $currentSource = 'main.app_settings@188',
    string $nextSource = 'main.app_settings@189',
    int $currentCookie = 188,
    int $nextCookie = 189,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerWindowPlan(
    $current ?? $current189,
    $next ?? $nextOneEightNine,
    'plugin!_cache%',
    '!',
    $token,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt189 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases189 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneEightNine'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* peer window */'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneEightThree'],
    'current source' => ['currentSource', 'main.app_settings@188'],
    'next source' => ['nextSource', 'main.app_settings@189'],
    'current cookie' => ['currentSchemaCookie', 188],
    'next cookie' => ['nextSchemaCookie', 189],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['rangeLowerInclusive', 'plugin_cache'],
    'range upper' => ['rangeUpperBound', 'plugin_cachf'],
    'index usable' => ['indexUsable', true],
    'prefix cursor' => ['usesPrefixRangeCursor', true],
    'token key' => ['resumeToken.key', 'plugin_cache'],
    'token rowid' => ['resumeToken.rowid', 2],
    'token canonical' => ['resumeToken.normalizationReasons', []],
    'peer key' => ['peerKey', 'plugin_cache'],
    'current peers' => ['currentPeerRowids', [1, 2, 3]],
    'next peers' => ['nextPeerRowids', [1, 2, 10]],
    'current before token' => ['currentPeerBeforeOrAtTokenRowids', [1, 2]],
    'next before token' => ['nextPeerBeforeOrAtTokenRowids', [1, 2]],
    'current after token' => ['currentPeerAfterTokenRowids', [3]],
    'next after token' => ['nextPeerAfterTokenRowids', [10]],
    'peer deleted' => ['peerDeletedRowids', [3]],
    'peer inserted' => ['peerInsertedRowids', [10]],
    'padding only stable' => ['paddingOnlyStableRowids', [2, 5]],
    'residual changed' => ['residualChangedRowids', [7, 10, 11]],
    'current matched' => ['currentMatchedRowids', [1, 2, 3, 4, 7, 5, 6]],
    'next matched' => ['nextMatchedRowids', [1, 2, 10, 4, 5, 3, 6, 11]],
    'current text one' => ['currentMatchedTexts.1', 'Plugin_Cache'],
    'next text one rtrim' => ['nextMatchedTexts.1', 'plugin_cache'],
    'tab key kept' => ['nextNocaseKeys.4', "plugin_cache\t"],
    'archive key' => ['nextNocaseKeys.3', 'plugin_cache_archive'],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [12]],
    'unsafe source' => ['peerWindowUnsafeReasons.0', 'source-or-schema-changed'],
    'unsafe malformed' => ['peerWindowUnsafeReasons.1', 'malformed-text'],
    'unsafe residual' => ['peerWindowUnsafeReasons.2', 'like-residual-rowset-changed'],
    'resume unsafe' => ['peerWindowResumeSafe', false],
    'must reprepare' => ['mustReprepareBeforePeerWindowResume', true],
    'mode' => ['replayPlanMode', 'reprepare-from-range-start'],
    'replay rowids' => ['replayPlanRowids', [1, 2, 10, 4, 5, 3, 6, 11]],
    'rtrim padding marker' => ['rtrimPaddingOnlyKeepsPeerKey', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'residual after rtrim' => ['likeResidualAppliesAfterRtrim', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency peer' => ['dependencies.2', 'sqlite-rtrim-peer-window'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nextoneEightNine'],
];

foreach ($cases189 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneEightNine ' . $name] = static function (TestRunner $t) use ($plan189, $valueAt189, $path, $expected): void {
        $t->same($expected, $valueAt189($plan189(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneEightNine stable peer window resumes after token'] = static function (TestRunner $t) use ($row189): void {
    $rows = [
        $row189(1, 'Plugin_Cache', 'UTF-16LE'),
        $row189(2, 'plugin_cache  ', 'UTF-16BE'),
        $row189(3, 'PLUGIN_CACHE', 'UTF-8'),
        $row189(4, 'plugin_cache_alpha', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerWindowPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        ['key' => 'plugin_cache', 'rowid' => 2],
        'stable',
        'stable',
        189,
        189,
    );

    $t->same([], $result['peerWindowUnsafeReasons']);
    $t->same(true, $result['peerWindowResumeSafe']);
    $t->same([3], $result['nextPeerAfterTokenRowids']);
    $t->same([3, 4], $result['replayPlanRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightNine row inserted before token is unsafe'] = static function (TestRunner $t) use ($row189): void {
    $current = [$row189(2, 'plugin_cache', 'UTF-16LE'), $row189(3, 'plugin_cache_alpha', 'UTF-16LE')];
    $next = [$row189(1, 'PLUGIN_CACHE ', 'UTF-16BE'), $row189(2, 'plugin_cache', 'UTF-16LE'), $row189(3, 'plugin_cache_alpha', 'UTF-16LE')];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerWindowPlan(
        $current,
        $next,
        'plugin!_cache%',
        '!',
        ['key' => 'plugin_cache', 'rowid' => 2],
        'stable',
        'stable',
        189,
        189,
    );

    $t->same([1], $result['peerInsertedRowids']);
    $t->same(['peer-before-token-changed', 'like-residual-rowset-changed'], $result['peerWindowUnsafeReasons']);
    $t->same(false, $result['peerWindowResumeSafe']);
};

$tests['utf16 nocase like rtrim current source nextOneEightNine canonicalizes token key before peer lookup'] = static function (TestRunner $t) use ($row189): void {
    $rows = [$row189(1, 'Plugin_Cache', 'UTF-16LE'), $row189(2, 'plugin_cache_alpha', 'UTF-16LE')];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerWindowPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        ['key' => 'PLUGIN_CACHE  ', 'rowid' => 1],
        'stable',
        'stable',
        189,
        189,
    );

    $t->same('plugin_cache', $result['resumeToken']['key']);
    $t->same(['token-key-not-canonical'], $result['resumeToken']['normalizationReasons']);
    $t->same(['yield-token-not-canonical'], $result['peerWindowUnsafeReasons']);
};

$tests['utf16 nocase like rtrim current source nextOneEightNine rejects malformed token key'] = static function (TestRunner $t) use ($row189): void {
    $rows = [$row189(1, 'plugin_cache', 'UTF-16LE')];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerWindowPlan(
        $rows,
        $rows,
        'plugin%',
        null,
        ['key' => 189, 'rowid' => 1],
    ));
};

$tests['utf16 nocase like rtrim current source nextOneEightNine rejects malformed token rowid'] = static function (TestRunner $t) use ($row189): void {
    $rows = [$row189(1, 'plugin_cache', 'UTF-16LE')];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerWindowPlan(
        $rows,
        $rows,
        'plugin%',
        null,
        ['key' => 'plugin_cache', 'rowid' => '1'],
    ));
};

return $tests;
