<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc187 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row187 = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc187($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad187 = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current187 = [
    $row187(1, 'plugin', 'UTF-16LE'),
    $row187(2, 'Plugin  ', 'UTF-16BE'),
    $row187(3, 'plugin_extra', 'UTF-8'),
    $row187(4, 'plugio', 'UTF-16LE'),
    $row187(5, 'theme_plugin', 'UTF-16BE'),
    $row187(6, "plugin\t", 'UTF-16LE'),
    $bad187(7, "\x00\xd8", 2),
];
$nextOneEightSeven = [
    $row187(1, 'plugin', 'UTF-16BE'),
    $row187(2, 'Plugin', 'UTF-16LE'),
    $row187(3, 'plugin_extra', 'UTF-8'),
    $row187(6, "plugin\t", 'UTF-16LE'),
    $row187(8, 'plugin_new', 'UTF-16BE'),
    $row187(9, 'plugin!', 'UTF-16LE'),
    $row187(10, 'plugj', 'UTF-16LE'),
    $bad187(11, "x\0y", 2),
];

$plan187 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!',
    ?string $escape = '!',
    string $currentSource = 'main.app_settings@186',
    string $nextSource = 'main.app_settings@187',
    int $currentCookie = 186,
    int $nextCookie = 187,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDanglingEscapePlan(
    $current ?? $current187,
    $next ?? $nextOneEightSeven,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt187 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases187 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneEightSeven'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE LIKE ? ESCAPE ?'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneEightThree'],
    'pattern' => ['pattern', 'plugin!'],
    'escape' => ['escape', '!'],
    'prefix' => ['prefix', 'plugin'],
    'range lower' => ['rangeLowerInclusive', 'plugin'],
    'range upper' => ['rangeUpperBound', 'plugio'],
    'dangling escape detected' => ['patternEndsWithEscape', true],
    'sqlite no rows' => ['sqliteDanglingEscapeMatchesNoRows', true],
    'uses prefix cursor' => ['usesPrefixRangeCursor', true],
    'current source' => ['currentSource', 'main.app_settings@186'],
    'next source' => ['nextSource', 'main.app_settings@187'],
    'current cookie' => ['currentSchemaCookie', 186],
    'next cookie' => ['nextSchemaCookie', 187],
    'current candidates' => ['currentCandidateRowids', [1, 2, 6, 3]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 6, 9, 3, 8]],
    'current matched empty' => ['currentMatchedRowids', []],
    'next matched empty' => ['nextMatchedRowids', []],
    'current misses all candidates' => ['currentDanglingEscapeResidualMissRowids', [1, 2, 6, 3]],
    'next misses all candidates' => ['nextDanglingEscapeResidualMissRowids', [1, 2, 6, 9, 3, 8]],
    'current matched texts empty' => ['currentMatchedTexts', []],
    'next matched texts empty' => ['nextMatchedTexts', []],
    'current rtrim row two' => ['currentRtrimTexts.2', 'Plugin'],
    'current tab not trimmed' => ['currentRtrimTexts.6', "plugin\t"],
    'next bang remains candidate false positive' => ['nextRtrimTexts.9', 'plugin!'],
    'row two current nocase key' => ['currentNocaseKeys.2', 'plugin'],
    'excluded current range upper row' => ['currentExcludedDecodedRowids', [4, 5]],
    'excluded next range upper row' => ['nextExcludedDecodedRowids', [10]],
    'current malformed rowids' => ['currentMalformedRowids', [7]],
    'next malformed rowids' => ['nextMalformedRowids', [11]],
    'current malformed error' => ['currentErrors.7', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next malformed error' => ['nextErrors.11', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'range retained' => ['rangeRetainedRowids', [1, 2, 6, 3]],
    'range exited' => ['rangeExitedRowids', []],
    'range entered' => ['rangeEnteredRowids', [9, 8]],
    'candidate changed' => ['candidateRowsetChanged', true],
    'matched stable empty' => ['matchedRowsetChanged', false],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'stale range risk' => ['staleRangeCursorRisk', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency dangling residual' => ['dependencies.1', 'sqlite-like-dangling-escape-residual'],
    'dependency range recheck' => ['dependencies.2', 'sqlite-nocase-prefix-range-recheck'],
    'dependency rtrim' => ['dependencies.3', 'sqlite-rtrim-expression-key'],
    'dependency current source' => ['dependencies.4', 'sqlite-current-source-nextoneEightSeven'],
];

foreach ($cases187 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneEightSeven ' . $name] = static function (TestRunner $t) use ($plan187, $valueAt187, $path, $expected): void {
        $t->same($expected, $valueAt187($plan187(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneEightSeven invalidation reasons include dangling escape residual'] = static function (TestRunner $t) use ($plan187): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-text',
        'encoded-bytes',
        'malformed-text',
        'dangling-like-escape-residual',
        'residual-recheck-required',
    ], $plan187()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextOneEightSeven stable source still requires residual recheck'] = static function (TestRunner $t) use ($row187): void {
    $rows = [
        $row187(1, 'plugin', 'UTF-16LE'),
        $row187(2, 'Plugin  ', 'UTF-16BE'),
        $row187(3, 'plugin_extra', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDanglingEscapePlan(
        $rows,
        $rows,
        'plugin!',
        '!',
        'stable',
        'stable',
        187,
        187,
    );
    $t->same([1, 2, 3], $result['currentCandidateRowids']);
    $t->same([], $result['currentMatchedRowids']);
    $t->same([1, 2, 3], $result['currentDanglingEscapeResidualMissRowids']);
    $t->same(['dangling-like-escape-residual', 'residual-recheck-required'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneEightSeven escaped bang before wildcard is not dangling'] = static function (TestRunner $t) use ($row187): void {
    $rows = [
        $row187(1, 'plugin!', 'UTF-16LE'),
        $row187(2, 'plugin!alpha', 'UTF-16BE'),
        $row187(3, 'plugin', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDanglingEscapePlan(
        $rows,
        $rows,
        'plugin!!%',
        '!',
        'stable',
        'stable',
        187,
        187,
    );
    $t->same(false, $result['patternEndsWithEscape']);
    $t->same('plugin!', $result['prefix']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([], $result['currentDanglingEscapeResidualMissRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneEightSeven no escape treats bang literally'] = static function (TestRunner $t) use ($row187): void {
    $rows = [
        $row187(1, 'plugin!', 'UTF-16LE'),
        $row187(2, 'Plugin!  ', 'UTF-16BE'),
        $row187(3, 'plugin', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDanglingEscapePlan(
        $rows,
        $rows,
        'plugin!',
        null,
        'stable',
        'stable',
        187,
        187,
    );
    $t->same(false, $result['patternEndsWithEscape']);
    $t->same('plugin!', $result['prefix']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([], $result['currentDanglingEscapeResidualMissRowids']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneEightSeven invalid escape length rejected by base planner'] = static function (TestRunner $t) use ($current187, $nextOneEightSeven): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDanglingEscapePlan($current187, $nextOneEightSeven, 'plugin!!', '!!'));
};

return $tests;
