<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc191 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row191 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc191($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad191 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows191 = [
    $row191(1, 'Plugin_Cache', 'UTF-16LE'),
    $row191(2, 'plugin_cache  ', 'UTF-16BE'),
    $row191(3, 'plugin_config', 'UTF-16LE'),
    $row191(4, 'plugin_other', 'UTF-8'),
    $row191(5, 'plugin-cache', 'UTF-16BE'),
    $row191(6, "plugin_cache\t", 'UTF-16LE'),
    $row191(7, 'theme_cache', 'UTF-16BE'),
    $bad191(8, "\x00\xd8", 2),
];
$nextRows191 = [
    $row191(1, 'Plugin_Cache', 'UTF-16BE'),
    $row191(2, 'plugin_cache', 'UTF-16LE'),
    $row191(3, 'plugin_config', 'UTF-16LE'),
    $row191(4, 'plugin_other', 'UTF-8'),
    $row191(5, 'plugin-cache', 'UTF-16BE'),
    $row191(6, "plugin_cache\t", 'UTF-16LE'),
    $row191(9, 'plugin_cache_new', 'UTF-16BE'),
    $row191(10, 'plugin_cachf_border', 'UTF-16LE'),
    $bad191(11, "x\0y", 2),
];
$currentPatternBytes191 = $enc191('plugin!_%', 'UTF-16LE');
$nextPatternBytes191 = $enc191('plugin!_cache%', 'UTF-16BE');
$currentEscapeBytes191 = $enc191('!', 'UTF-16LE');
$nextEscapeBytes191 = $enc191('!', 'UTF-16BE');

$plan191 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?string $currentPatternBytes = null,
    int|string $currentPatternEncoding = 'UTF-16LE',
    ?string $nextPatternBytes = null,
    int|string $nextPatternEncoding = 'UTF-16BE',
    ?string $currentEscapeBytes = null,
    int|string|null $currentEscapeEncoding = 'UTF-16LE',
    ?string $nextEscapeBytes = null,
    int|string|null $nextEscapeEncoding = 'UTF-16BE',
    string $currentSource = 'main.wp_options@190',
    string $nextSource = 'main.wp_options@191',
    int $currentCookie = 190,
    int $nextCookie = 191,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePreparedPatternRebindPlan(
    $current ?? $currentRows191,
    $next ?? $nextRows191,
    $currentPatternBytes ?? $currentPatternBytes191,
    $currentPatternEncoding,
    $nextPatternBytes ?? $nextPatternBytes191,
    $nextPatternEncoding,
    $currentEscapeBytes ?? $currentEscapeBytes191,
    $currentEscapeEncoding,
    $nextEscapeBytes ?? $nextEscapeBytes191,
    $nextEscapeEncoding,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt191 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases191 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneNineOne'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 rebind */'],
    'current pattern' => ['currentPattern', 'plugin!_%'],
    'next pattern' => ['nextPattern', 'plugin!_cache%'],
    'current escape' => ['currentEscape', '!'],
    'next escape' => ['nextEscape', '!'],
    'current pattern encoding' => ['currentPatternEncoding', 'UTF-16LE'],
    'next pattern encoding' => ['nextPatternEncoding', 'UTF-16BE'],
    'current escape encoding' => ['currentEscapeEncoding', 'UTF-16LE'],
    'next escape encoding' => ['nextEscapeEncoding', 'UTF-16BE'],
    'current pattern bytes' => ['currentPatternBytesHex', bin2hex($currentPatternBytes191)],
    'next pattern bytes' => ['nextPatternBytesHex', bin2hex($nextPatternBytes191)],
    'current escape bytes' => ['currentEscapeBytesHex', bin2hex($currentEscapeBytes191)],
    'next escape bytes' => ['nextEscapeBytesHex', bin2hex($nextEscapeBytes191)],
    'decoded pattern changed' => ['sameDecodedPatternAndEscape', false],
    'prepared bytes changed' => ['samePreparedPatternBytes', false],
    'current source' => ['currentSource', 'main.wp_options@190'],
    'next source' => ['nextSource', 'main.wp_options@191'],
    'current cookie' => ['currentSchemaCookie', 190],
    'next cookie' => ['nextSchemaCookie', 191],
    'current prefix' => ['currentPrefix', 'plugin_'],
    'next prefix' => ['nextPrefix', 'plugin_cache'],
    'current range lower' => ['currentRangeLowerInclusive', 'plugin_'],
    'next range lower' => ['nextRangeLowerInclusive', 'plugin_cache'],
    'current range upper' => ['currentRangeUpperBound', 'plugin`'],
    'next range upper' => ['nextRangeUpperBound', 'plugin_cachf'],
    'current index usable' => ['currentIndexUsable', true],
    'next index usable' => ['nextIndexUsable', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 6, 3, 4]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 6, 9]],
    'candidate retained' => ['candidateRetainedRowids', [1, 2, 6]],
    'candidate exited' => ['candidateExitedRowids', [3, 4]],
    'candidate entered' => ['candidateEnteredRowids', [9]],
    'current matched' => ['currentMatchedRowids', [1, 2, 6, 3, 4]],
    'next matched' => ['nextMatchedRowids', [1, 2, 6, 9]],
    'matched retained' => ['matchedRetainedRowids', [1, 2, 6]],
    'matched exited' => ['matchedExitedRowids', [3, 4]],
    'matched entered' => ['matchedEnteredRowids', [9]],
    'current false positives' => ['currentRangeFalsePositiveRowids', []],
    'next false positives' => ['nextRangeFalsePositiveRowids', []],
    'current rtrim row two' => ['currentRtrimTexts.2', 'plugin_cache'],
    'next rtrim row two' => ['nextRtrimTexts.2', 'plugin_cache'],
    'tab survives rtrim' => ['currentRtrimTexts.6', "plugin_cache\t"],
    'current nocase row one' => ['currentNocaseKeys.1', 'plugin_cache'],
    'next nocase row one' => ['nextNocaseKeys.1', 'plugin_cache'],
    'current matched text four' => ['currentMatchedTexts.4', 'plugin_other'],
    'next matched text nine' => ['nextMatchedTexts.9', 'plugin_cache_new'],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [11]],
    'current malformed error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next malformed error' => ['nextErrors.11', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'must reprepare' => ['mustReprepareForPatternChange', true],
    'cannot reuse residual' => ['canReuseResidualForByteOrderOnlyRebind', false],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency rebind' => ['dependencies.1', 'sqlite-prepared-like-pattern-rebind'],
    'dependency range' => ['dependencies.2', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.3', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nextoneNineOne'],
];

foreach ($cases191 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneNineOne ' . $name] = static function (TestRunner $t) use ($plan191, $valueAt191, $path, $expected): void {
        $t->same($expected, $valueAt191($plan191(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneNineOne invalidation reason order'] = static function (TestRunner $t) use ($plan191): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-pattern-or-escape',
        'range-bound',
        'candidate-rowset',
        'matched-rowset',
        'malformed-text',
    ], $plan191()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextOneNineOne byte order only pattern can keep residual semantics'] = static function (TestRunner $t) use ($row191, $enc191): void {
    $rows = [
        $row191(1, 'Plugin_Cache', 'UTF-16LE'),
        $row191(2, 'plugin_cache  ', 'UTF-16BE'),
        $row191(3, 'plugin_cache_new', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePreparedPatternRebindPlan(
        $rows,
        $rows,
        $enc191('plugin!_cache%', 'UTF-16LE'),
        'UTF-16LE',
        $enc191('plugin!_cache%', 'UTF-16BE'),
        'UTF-16BE',
        $enc191('!', 'UTF-16LE'),
        'UTF-16LE',
        $enc191('!', 'UTF-16BE'),
        'UTF-16BE',
        'stable',
        'stable',
        191,
        191,
    );
    $t->same(true, $result['sameDecodedPatternAndEscape']);
    $t->same(false, $result['samePreparedPatternBytes']);
    $t->same(false, $result['mustReprepareForPatternChange']);
    $t->same(true, $result['canReuseResidualForByteOrderOnlyRebind']);
    $t->same(['prepared-pattern-byte-order-refresh'], $result['invalidationReasons']);
    $t->same([1, 2, 3], $result['currentMatchedRowids']);
    $t->same([1, 2, 3], $result['nextMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneNineOne escaped wildcard tightening removes broad prefix rows'] = static function (TestRunner $t) use ($plan191): void {
    $result = $plan191();
    $t->same([3, 4], $result['candidateExitedRowids']);
    $t->same([3, 4], $result['matchedExitedRowids']);
    $t->same('plugin_config', $result['currentMatchedTexts'][3]);
    $t->same('plugin_other', $result['currentMatchedTexts'][4]);
};

$tests['utf16 nocase like rtrim current source nextOneNineOne stable byte-identical source is reusable'] = static function (TestRunner $t) use ($row191, $enc191): void {
    $rows = [
        $row191(1, 'plugin_cache', 'UTF-16LE'),
        $row191(2, 'Plugin_Cache  ', 'UTF-16BE'),
    ];
    $pattern = $enc191('plugin!_cache%', 'UTF-16LE');
    $escape = $enc191('!', 'UTF-16LE');
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePreparedPatternRebindPlan(
        $rows,
        $rows,
        $pattern,
        'UTF-16LE',
        $pattern,
        'UTF-16LE',
        $escape,
        'UTF-16LE',
        $escape,
        'UTF-16LE',
        'stable',
        'stable',
        7,
        7,
    );
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
    $t->same(false, $result['cursorInvalidated']);
    $t->same([1, 2], $result['nextMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneNineOne malformed prepared pattern is rejected'] = static function (TestRunner $t) use ($currentRows191, $nextRows191, $nextPatternBytes191, $currentEscapeBytes191, $nextEscapeBytes191): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePreparedPatternRebindPlan(
        $currentRows191,
        $nextRows191,
        "\x00\xd8",
        'UTF-16LE',
        $nextPatternBytes191,
        'UTF-16BE',
        $currentEscapeBytes191,
        'UTF-16LE',
        $nextEscapeBytes191,
        'UTF-16BE',
    ));
};

$tests['utf16 nocase like rtrim current source nextOneNineOne malformed prepared escape is rejected'] = static function (TestRunner $t) use ($currentRows191, $nextRows191, $currentPatternBytes191, $nextPatternBytes191, $nextEscapeBytes191): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePreparedPatternRebindPlan(
        $currentRows191,
        $nextRows191,
        $currentPatternBytes191,
        'UTF-16LE',
        $nextPatternBytes191,
        'UTF-16BE',
        "\x00\xd8",
        'UTF-16LE',
        $nextEscapeBytes191,
        'UTF-16BE',
    ));
};

return $tests;
