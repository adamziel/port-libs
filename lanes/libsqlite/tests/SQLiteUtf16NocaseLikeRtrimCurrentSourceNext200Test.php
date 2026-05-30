<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc200 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row200 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc200($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad200 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$rows200 = [
    $row200(1, 'plugin_cache', 'UTF-16LE'),
    $row200(2, 'Plugin_Cache  ', 'UTF-16BE'),
    $row200(3, 'plugin!cache', 'UTF-16LE'),
    $row200(4, 'plugin!_cache', 'UTF-16BE'),
    $row200(5, 'plugin-cache', 'UTF-8'),
    $row200(6, 'plugin_', 'UTF-16LE'),
    $row200(7, 'plugin!', 'UTF-16BE'),
    $row200(8, 'plugin!x', 'UTF-8'),
    $bad200(9, "\x00\xd8", 2),
    $row200(10, 'PLUGIN!NEW', 'UTF-16BE'),
    $row200(11, 'plugin%cache', 'UTF-16LE'),
];

$nextRows200 = [
    $row200(1, 'plugin_cache', 'UTF-16BE'),
    $row200(2, 'Plugin_Cache', 'UTF-16LE'),
    $row200(3, 'plugin!cache', 'UTF-16LE'),
    $row200(4, 'plugin!_cache', 'UTF-16BE'),
    $row200(5, 'plugin-cache', 'UTF-8'),
    $row200(6, 'plugin_', 'UTF-16LE'),
    $row200(7, 'plugin!', 'UTF-16BE'),
    $row200(8, 'plugin!x', 'UTF-8'),
    $bad200(9, "\x00\xd8", 2),
    $row200(10, 'PLUGIN!NEW', 'UTF-16BE'),
    $row200(11, 'plugin%cache', 'UTF-16LE'),
    $row200(12, 'plugin!later', 'UTF-16LE'),
];

$plan200 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $currentPattern = 'plugin!_%',
    ?string $currentEscape = '!',
    string $nextPattern = 'plugin!_%',
    ?string $nextEscape = '~',
    string $currentSource = 'main.wp_options@199',
    string $nextSource = 'main.wp_options@200',
    int $currentCookie = 199,
    int $nextCookie = 200,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameEscapeRebindPlan(
    $current ?? $rows200,
    $next ?? $nextRows200,
    $currentPattern,
    $currentEscape,
    $nextPattern,
    $nextEscape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt200 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases200 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoZeroZero'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* escape rebind */'],
    'current pattern' => ['currentPattern', 'plugin!_%'],
    'next pattern' => ['nextPattern', 'plugin!_%'],
    'current escape' => ['currentEscape', '!'],
    'next escape' => ['nextEscape', '~'],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.wp_options@199'],
    'next source' => ['nextSource', 'main.wp_options@200'],
    'current cookie' => ['currentSchemaCookie', 199],
    'next cookie' => ['nextSchemaCookie', 200],
    'current prefix' => ['currentPrefix', 'plugin_'],
    'next prefix' => ['nextPrefix', 'plugin!'],
    'current lower' => ['currentRangeLowerInclusive', 'plugin_'],
    'current upper' => ['currentRangeUpperBound', 'plugin`'],
    'next lower' => ['nextRangeLowerInclusive', 'plugin!'],
    'next upper' => ['nextRangeUpperBound', 'plugin"'],
    'current index' => ['currentIndexUsable', true],
    'next index' => ['nextIndexUsable', true],
    'current candidates' => ['currentCandidateRowids', [6, 1, 2]],
    'next candidates' => ['nextCandidateRowids', [7, 4, 3, 12, 10, 8]],
    'current matched' => ['currentMatchedRowids', [6, 1, 2]],
    'next matched' => ['nextMatchedRowids', [4, 3, 12, 10, 8]],
    'next with current escape' => ['nextMatchedWithCurrentEscapeRowids', [6, 1, 2]],
    'current with next escape' => ['currentMatchedWithNextEscapeRowids', [4, 3, 10, 8]],
    'escape residual flip next' => ['escapeResidualFlipRowids', [1, 2, 3, 4, 6, 8, 10, 12]],
    'escape residual flip current' => ['currentEscapeResidualFlipRowids', [1, 2, 3, 4, 6, 8, 10]],
    'matched exited' => ['matchedExitedRowids', [1, 2, 6]],
    'matched entered' => ['matchedEnteredRowids', [3, 4, 8, 10, 12]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', [7]],
    'current excluded' => ['currentExcludedDecodedRowids', [7, 4, 3, 10, 8, 11, 5]],
    'next excluded' => ['nextExcludedDecodedRowids', [11, 5, 6, 1, 2]],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [9]],
    'malformed error current' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'malformed error next' => ['nextErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current rtrim strips spaces' => ['currentRtrimTexts.2', 'Plugin_Cache'],
    'next rtrim strips spaces' => ['nextRtrimTexts.2', 'Plugin_Cache'],
    'current key folded' => ['currentNocaseKeys.2', 'plugin_cache'],
    'next key folded' => ['nextNocaseKeys.10', 'plugin!new'],
    'current matched text one' => ['currentMatchedTexts.1', 'plugin_cache'],
    'next matched text ten' => ['nextMatchedTexts.10', 'PLUGIN!NEW'],
    'escape changed' => ['escapeChanged', true],
    'prefix changed' => ['prefixChangedByEscape', true],
    'range changed' => ['rangeChangedByEscape', true],
    'residual changed' => ['residualChangedByEscape', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'must reprepare' => ['mustReprepareForEscapeRebind', true],
    'stale risk' => ['staleRangeCursorRisk', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'escape checked first' => ['escapeRebindCheckedBeforeRangeReuse', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency escape range' => ['dependencies.1', 'sqlite-like-escape-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoZeroZero'],
];

foreach ($cases200 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoZeroZero ' . $name] = static function (TestRunner $t) use ($plan200, $valueAt200, $path, $expected): void {
        $t->same($expected, $valueAt200($plan200(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoZeroZero invalidation reasons include escape rebind'] = static function (TestRunner $t) use ($plan200): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'escape-rebound',
        'like-prefix',
        'like-range',
        'malformed-text',
        'escape-residual-rowset',
        'matched-rowset',
    ], $plan200()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroZero same escape stable cursor is reusable'] = static function (TestRunner $t) use ($row200): void {
    $rows = [
        $row200(1, 'plugin_cache', 'UTF-16LE'),
        $row200(2, 'Plugin_Cache  ', 'UTF-16BE'),
        $row200(3, 'plugin-cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameEscapeRebindPlan(
        $rows,
        $rows,
        'plugin!_%',
        '!',
        'plugin!_%',
        '!',
        'stable',
        'stable',
        200,
        200,
    );

    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same(false, $result['escapeChanged']);
    $t->same(false, $result['cursorInvalidated']);
    $t->same(true, $result['cursorReusable']);
    $t->same(false, $result['mustReprepareForEscapeRebind']);
    $t->same([], $result['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroZero escape rebind without prefix shift still fences residual'] = static function (TestRunner $t) use ($row200): void {
    $rows = [
        $row200(1, 'plugin!cache', 'UTF-16LE'),
        $row200(2, 'plugin%cache', 'UTF-16BE'),
        $row200(3, 'plugin_cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameEscapeRebindPlan(
        $rows,
        $rows,
        'plugin!%%',
        '!',
        'plugin!%%',
        '#',
        'stable',
        'stable',
        200,
        200,
    );

    $t->same('plugin%', $result['currentPrefix']);
    $t->same('plugin!', $result['nextPrefix']);
    $t->same([2], $result['currentMatchedRowids']);
    $t->same([1], $result['nextMatchedRowids']);
    $t->same([1, 2], $result['escapeResidualFlipRowids']);
    $t->same(true, $result['mustReprepareForEscapeRebind']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroZero rejects invalid current escape length'] = static function (TestRunner $t) use ($rows200, $nextRows200): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameEscapeRebindPlan($rows200, $nextRows200, 'plugin!_%', '!!'));
};

$tests['utf16 nocase like rtrim current source nextTwoZeroZero rejects invalid next escape length'] = static function (TestRunner $t) use ($rows200, $nextRows200): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameEscapeRebindPlan($rows200, $nextRows200, 'plugin!_%', '!', 'plugin!_%', '~~'));
};

$tests['utf16 nocase like rtrim current source nextTwoZeroZero rejects missing option id'] = static function (TestRunner $t) use ($nextRows200): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameEscapeRebindPlan([
        ['option_name_bytes' => 'plugin_cache', 'text_encoding' => 1],
    ], $nextRows200));
};

return $tests;
