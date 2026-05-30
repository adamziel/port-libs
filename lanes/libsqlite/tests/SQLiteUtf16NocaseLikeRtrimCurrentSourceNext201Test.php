<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc201 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row201 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc201($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad201 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows201 = [
    $row201(1, 'plugin_cache', 'UTF-16LE'),
    $row201(2, 'Plugin_Cache  ', 'UTF-16BE'),
    $row201(3, 'plugin_cache_new', 'UTF-16LE'),
    $row201(4, "plugin_cache\t", 'UTF-8'),
    $row201(5, 'plugin_config', 'UTF-16BE'),
    $row201(6, 'theme_cache', 'UTF-16LE'),
    $bad201(7, "\x00\xd8", 2),
];
$nextRows201 = [
    $row201(1, 'plugin_cache', 'UTF-16BE'),
    $row201(2, 'Plugin_Cache', 'UTF-16LE'),
    $row201(3, 'plugin_cache_new', 'UTF-16LE'),
    $row201(4, "plugin_cache\t", 'UTF-8'),
    $row201(8, 'plugin_cache_added', 'UTF-16BE'),
    $row201(9, 'plugin_cachf_border', 'UTF-16LE'),
    $bad201(10, "x\0y", 2),
];

$plan201 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?string $currentPattern = 'plugin!_cache%',
    ?string $nextPattern = null,
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@200',
    string $nextSource = 'main.wp_options@201',
    int $currentCookie = 200,
    int $nextCookie = 201,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNullPatternRebindPlan(
    $current ?? $currentRows201,
    $next ?? $nextRows201,
    $currentPattern,
    $nextPattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt201 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases201 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoZeroOne'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* NULL pattern rebind */'],
    'current pattern' => ['currentPattern', 'plugin!_cache%'],
    'next pattern null' => ['nextPattern', null],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.wp_options@200'],
    'next source' => ['nextSource', 'main.wp_options@201'],
    'current cookie' => ['currentSchemaCookie', 200],
    'next cookie' => ['nextSchemaCookie', 201],
    'current pattern not null' => ['currentPatternIsSqlNull', false],
    'next pattern is null' => ['nextPatternIsSqlNull', true],
    'current like result not null' => ['currentLikeResultIsNull', false],
    'next like result null' => ['nextLikeResultIsNull', true],
    'current prefix' => ['currentPrefix', 'plugin_cache'],
    'next prefix null' => ['nextPrefix', null],
    'current lower' => ['currentRangeLowerInclusive', 'plugin_cache'],
    'current upper' => ['currentRangeUpperBound', 'plugin_cachf'],
    'next lower null' => ['nextRangeLowerInclusive', null],
    'next upper null' => ['nextRangeUpperBound', null],
    'current index usable' => ['currentIndexUsable', true],
    'next index unusable' => ['nextIndexUsable', false],
    'current candidates' => ['currentCandidateRowids', [1, 2, 4, 3]],
    'next candidates empty' => ['nextCandidateRowids', []],
    'candidate exited' => ['candidateExitedRowids', [1, 2, 4, 3]],
    'candidate entered empty' => ['candidateEnteredRowids', []],
    'current matched' => ['currentMatchedRowids', [1, 2, 4, 3]],
    'next matched empty' => ['nextMatchedRowids', []],
    'matched exited' => ['matchedExitedRowids', [1, 2, 4, 3]],
    'matched entered empty' => ['matchedEnteredRowids', []],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'current excluded' => ['currentExcludedDecodedRowids', [5, 6]],
    'next excluded' => ['nextExcludedDecodedRowids', [1, 2, 4, 8, 3, 9]],
    'current malformed' => ['currentMalformedRowids', [7]],
    'next malformed' => ['nextMalformedRowids', [10]],
    'current malformed error' => ['currentErrors.7', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next malformed error' => ['nextErrors.10', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'rtrim strips space' => ['currentRtrimTexts.2', 'Plugin_Cache'],
    'rtrim preserves tab' => ['currentRtrimTexts.4', "plugin_cache\t"],
    'nocase folds ascii' => ['currentNocaseKeys.2', 'plugin_cache'],
    'next decoded despite null pattern' => ['nextRtrimTexts.8', 'plugin_cache_added'],
    'current matched text' => ['currentMatchedTexts.3', 'plugin_cache_new'],
    'next matched text absent' => ['nextMatchedTexts.1', null],
    'must reprepare' => ['mustReprepareForNullPattern', true],
    'null disables range' => ['nullPatternDisablesPrefixRange', true],
    'null matches no rows' => ['nullPatternMatchesNoRows', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'stale risk' => ['staleRangeCursorRisk', true],
    'rtrim ascii only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii only' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency null pattern' => ['dependencies.1', 'sqlite-like-null-pattern-rebind'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoZeroOne'],
];

foreach ($cases201 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoZeroOne ' . $name] = static function (TestRunner $t) use ($plan201, $valueAt201, $path, $expected): void {
        $t->same($expected, $valueAt201($plan201(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoZeroOne invalidation reason order'] = static function (TestRunner $t) use ($plan201): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'pattern-rebound',
        'null-like-pattern',
        'like-range',
        'malformed-text',
        'candidate-rowset',
        'matched-rowset',
    ], $plan201()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroOne stable non null pattern can reuse cursor'] = static function (TestRunner $t) use ($row201): void {
    $rows = [
        $row201(1, 'plugin_cache', 'UTF-16LE'),
        $row201(2, 'Plugin_Cache  ', 'UTF-16BE'),
        $row201(3, 'theme_cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNullPatternRebindPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        'plugin!_cache%',
        '!',
        'stable',
        'stable',
        201,
        201,
    );

    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same(false, $result['mustReprepareForNullPattern']);
    $t->same(false, $result['nullPatternDisablesPrefixRange']);
    $t->same(false, $result['cursorInvalidated']);
    $t->same(true, $result['cursorReusable']);
    $t->same([], $result['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroOne null to non null pattern repopulates range'] = static function (TestRunner $t) use ($row201): void {
    $rows = [
        $row201(1, 'plugin_cache', 'UTF-16LE'),
        $row201(2, 'Plugin_Cache  ', 'UTF-16BE'),
        $row201(3, 'plugin_config', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNullPatternRebindPlan(
        $rows,
        $rows,
        null,
        'plugin!_cache%',
        '!',
        'stable',
        'stable',
        201,
        201,
    );

    $t->same(true, $result['currentPatternIsSqlNull']);
    $t->same(false, $result['nextPatternIsSqlNull']);
    $t->same([], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same([1, 2], $result['candidateEnteredRowids']);
    $t->same([1, 2], $result['matchedEnteredRowids']);
    $t->same(['pattern-rebound', 'null-like-pattern', 'like-range', 'candidate-rowset', 'matched-rowset'], $result['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroOne both null patterns are reusable after decode'] = static function (TestRunner $t) use ($row201): void {
    $rows = [
        $row201(1, 'plugin_cache', 'UTF-16LE'),
        $row201(2, 'Plugin_Cache  ', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNullPatternRebindPlan(
        $rows,
        $rows,
        null,
        null,
        '!',
        'stable',
        'stable',
        201,
        201,
    );

    $t->same([], $result['currentMatchedRowids']);
    $t->same([], $result['nextMatchedRowids']);
    $t->same(true, $result['currentLikeResultIsNull']);
    $t->same(true, $result['nextLikeResultIsNull']);
    $t->same(false, $result['cursorInvalidated']);
    $t->same(true, $result['cursorReusable']);
    $t->same([], $result['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroOne rejects invalid escape length before scan'] = static function (TestRunner $t) use ($currentRows201, $nextRows201): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNullPatternRebindPlan($currentRows201, $nextRows201, 'plugin!_cache%', null, '!!'));
};

$tests['utf16 nocase like rtrim current source nextTwoZeroOne rejects missing option id'] = static function (TestRunner $t) use ($nextRows201): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNullPatternRebindPlan([
        ['option_name_bytes' => 'plugin_cache', 'text_encoding' => 1],
    ], $nextRows201));
};

return $tests;
