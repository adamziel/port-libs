<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc213 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row213 = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc213($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad213 = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$escape213 = "\xef\xbc\x81";
$currentPattern213 = "plugin{$escape213}{$escape213}%";
$nextPattern213 = "plugin{$escape213}{$escape213}{$escape213}_%";
$currentRows213 = [
    $row213(1, "plugin{$escape213}cache", 'UTF-16LE'),
    $row213(2, "Plugin{$escape213}Cache  ", 'UTF-16BE'),
    $row213(3, "plugin{$escape213}_cache", 'UTF-16LE'),
    $row213(4, 'plugin_cache', 'UTF-16BE'),
    $row213(5, "plugin{$escape213}", 'UTF-8'),
    $row213(6, "plugin{$escape213}\t", 'UTF-16LE'),
    $row213(7, "theme_plugin{$escape213}_cache", 'UTF-16BE'),
    $bad213(8, "\x00\xd8", 2),
];
$nextRows213 = [
    $row213(1, "plugin{$escape213}cache", 'UTF-16BE'),
    $row213(2, "Plugin{$escape213}Cache", 'UTF-16LE'),
    $row213(3, "plugin{$escape213}_cache", 'UTF-16BE'),
    $row213(4, 'plugin_cache', 'UTF-8'),
    $row213(9, "PLUGIN{$escape213}_SETTINGS  ", 'UTF-16LE'),
    $row213(10, "plugin{$escape213}%literal", 'UTF-16BE'),
    $bad213(11, "\x00\xd8", 2),
];

$plan213 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?string $currentPattern = null,
    ?string $nextPattern = null,
    int|string $currentPatternEncoding = 'UTF-16LE',
    int|string $nextPatternEncoding = 'UTF-16BE',
    int|string $escapeEncoding = 'UTF-16LE',
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySelfEscapedEscapePlan(
    $current ?? $currentRows213,
    $next ?? $nextRows213,
    $enc213($currentPattern ?? $currentPattern213, $currentPatternEncoding),
    $currentPatternEncoding,
    $enc213($nextPattern ?? $nextPattern213, $nextPatternEncoding),
    $nextPatternEncoding,
    $enc213($escape213, $escapeEncoding),
    $escapeEncoding,
);

$valueAt213 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases213 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoOneThree'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE LIKE ? ESCAPE ? /* UTF-16 self-escaped Unicode ESCAPE */'],
    'current pattern' => ['currentPattern', $currentPattern213],
    'next pattern' => ['nextPattern', $nextPattern213],
    'escape' => ['escape', $escape213],
    'escape encoding' => ['escapeEncoding', 'UTF-16LE'],
    'current pattern encoding' => ['currentPatternEncoding', 'UTF-16LE'],
    'next pattern encoding' => ['nextPatternEncoding', 'UTF-16BE'],
    'self escaped escape' => ['selfEscapedEscapeCharacter', true],
    'unicode escape' => ['unicodeEscapeCharacter', true],
    'current escape length' => ['currentEscapeTextLength', 1],
    'next escape length' => ['nextEscapeTextLength', 1],
    'current ascii equivalent' => ['currentAsciiEquivalentPattern', 'plugin!!%'],
    'next ascii equivalent' => ['nextAsciiEquivalentPattern', 'plugin!!!_%'],
    'current source' => ['currentSource', 'main.app_settings@212'],
    'next source' => ['nextSource', 'main.app_settings@213'],
    'current cookie' => ['currentSchemaCookie', 212],
    'next cookie' => ['nextSchemaCookie', 213],
    'prefix' => ['prefix', "plugin{$escape213}"],
    'next prefix' => ['nextPrefix', "plugin{$escape213}_"],
    'range lower' => ['rangeLowerInclusive', null],
    'range upper' => ['rangeUpperBound', null],
    'next range lower' => ['nextRangeLowerInclusive', null],
    'next range upper' => ['nextRangeUpperBound', null],
    'current index usable' => ['currentIndexUsable', false],
    'next index usable' => ['nextIndexUsable', false],
    'current candidates' => ['currentCandidateRowids', []],
    'next candidates' => ['nextCandidateRowids', []],
    'current matched' => ['currentMatchedRowids', []],
    'next matched' => ['nextMatchedRowids', []],
    'current ascii matched' => ['currentAsciiEquivalentMatchedRowids', []],
    'next ascii matched' => ['nextAsciiEquivalentMatchedRowids', []],
    'current ascii candidates' => ['currentAsciiEquivalentCandidateRowids', []],
    'next ascii candidates' => ['nextAsciiEquivalentCandidateRowids', []],
    'current equivalent' => ['unicodeEscapeNormalizedCurrentEquivalent', true],
    'next equivalent' => ['unicodeEscapeNormalizedNextEquivalent', true],
    'current escaped escape offsets' => ['currentEscapedEscapeOffsets', [7]],
    'next escaped escape offsets' => ['nextEscapedEscapeOffsets', [7]],
    'current escaped wildcard offsets' => ['currentEscapedWildcardOffsets', []],
    'next escaped wildcard offsets' => ['nextEscapedWildcardOffsets', [9]],
    'current first wildcard' => ['currentFirstWildcardOffset', 8],
    'next first wildcard' => ['nextFirstWildcardOffset', 10],
    'current prefix chars' => ['currentPrefixCharacters', 7],
    'next prefix chars' => ['nextPrefixCharacters', 8],
    'current prefix has escape literal' => ['currentPrefixContainsEscapeLiteral', true],
    'next prefix has escape literal' => ['nextPrefixContainsEscapeLiteral', true],
    'current prefix has wildcard literal' => ['currentPrefixContainsEscapedWildcardLiteral', false],
    'next prefix has wildcard literal' => ['nextPrefixContainsEscapedWildcardLiteral', true],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', []],
    'current excluded' => ['currentExcludedDecodedRowids', [4, 5, 6, 3, 1, 2, 7]],
    'next excluded' => ['nextExcludedDecodedRowids', [4, 10, 3, 9, 1, 2]],
    'current rtrim row two' => ['currentRtrimTexts.2', "Plugin{$escape213}Cache"],
    'next rtrim row nine' => ['nextRtrimTexts.9', "PLUGIN{$escape213}_SETTINGS"],
    'current key two' => ['currentNocaseKeys.2', "plugin{$escape213}cache"],
    'next key nine' => ['nextNocaseKeys.9', "plugin{$escape213}_settings"],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [11]],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'decode before self escape' => ['mustDecodeEscapeBeforeSelfEscapePlanning', true],
    'keep escape in prefix' => ['mustKeepEscapedEscapeInPrefix', true],
    'keep wildcard in prefix' => ['mustKeepEscapedWildcardInPrefix', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency self escape' => ['dependencies.1', 'sqlite-prepared-like-self-escaped-unicode-escape'],
    'dependency range' => ['dependencies.2', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.3', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoOneThree'],
];

foreach ($cases213 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoOneThree ' . $name] = static function (TestRunner $t) use ($plan213, $valueAt213, $path, $expected): void {
        $t->same($expected, $valueAt213($plan213(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoOneThree invalidation reasons include escaped wildcard'] = static function (TestRunner $t) use ($plan213): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'pattern',
        'like-prefix',
        'malformed-text',
        'unicode-escape-character',
        'decoded-pattern',
        'escaped-wildcard-prefix',
    ], $plan213()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneThree ascii prefix self escaped wildcard can scan'] = static function (TestRunner $t) use ($enc213, $row213): void {
    $rows = [
        $row213(1, 'plugin!_cache', 'UTF-16LE'),
        $row213(2, 'Plugin!_Settings  ', 'UTF-16BE'),
        $row213(3, 'plugin!cache', 'UTF-8'),
        $row213(4, 'plugin_cache', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySelfEscapedEscapePlan(
        $rows,
        $rows,
        $enc213('plugin!!!_%', 'UTF-16LE'),
        'UTF-16LE',
        $enc213('plugin!!!_%', 'UTF-16BE'),
        'UTF-16BE',
        $enc213('!', 'UTF-16LE'),
        'UTF-16LE',
        'stable',
        'stable',
        213,
        213,
    );

    $t->same('plugin!_', $result['prefix']);
    $t->same([1, 2], $result['currentCandidateRowids']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same([7], $result['currentEscapedEscapeOffsets']);
    $t->same([9], $result['currentEscapedWildcardOffsets']);
    $t->same([], $result['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneThree escaped escape literal does not match missing escape'] = static function (TestRunner $t) use ($plan213, $row213, $escape213): void {
    $rows = [
        $row213(1, "plugin{$escape213}cache", 'UTF-16LE'),
        $row213(2, 'plugincache', 'UTF-16BE'),
        $row213(3, "plugin{$escape213}", 'UTF-8'),
    ];
    $result = $plan213($rows, $rows, "plugin{$escape213}{$escape213}%", "plugin{$escape213}{$escape213}%");

    $t->same([], $result['currentCandidateRowids']);
    $t->same([], $result['currentMatchedRowids']);
    $t->same([2, 3, 1], $result['currentExcludedDecodedRowids']);
    $t->same(true, $result['currentPrefixContainsEscapeLiteral']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneThree rejects malformed escape bytes'] = static function (TestRunner $t) use ($currentRows213, $nextRows213, $enc213, $currentPattern213, $nextPattern213): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySelfEscapedEscapePlan(
        $currentRows213,
        $nextRows213,
        $enc213($currentPattern213, 'UTF-16LE'),
        'UTF-16LE',
        $enc213($nextPattern213, 'UTF-16BE'),
        'UTF-16BE',
        "\x00\xd8",
        'UTF-16LE',
    ));
};

$tests['utf16 nocase like rtrim current source nextTwoOneThree rejects two character escape'] = static function (TestRunner $t) use ($currentRows213, $nextRows213, $enc213, $currentPattern213, $nextPattern213): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySelfEscapedEscapePlan(
        $currentRows213,
        $nextRows213,
        $enc213($currentPattern213, 'UTF-16LE'),
        'UTF-16LE',
        $enc213($nextPattern213, 'UTF-16BE'),
        'UTF-16BE',
        $enc213('!!', 'UTF-16LE'),
        'UTF-16LE',
    ));
};

return $tests;
