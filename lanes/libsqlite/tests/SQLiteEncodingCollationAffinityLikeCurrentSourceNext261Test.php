<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$tests = [];

$enc261 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$current261 = [
    ['option_id' => 1, 'option_name_bytes' => $enc261('Plugin_cache', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => 'enabled:core'],
    ['option_id' => 2, 'option_name_bytes' => $enc261('plugin_cache_timeout', 'UTF-16BE'), 'name_text_encoding' => 'UTF-16BE', 'option_value' => 'disabled:15'],
    ['option_id' => 3, 'option_name_bytes' => $enc261('plugin-cache', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => 'enabled:dash'],
    ['option_id' => 4, 'option_name_bytes' => $enc261('plugin_theme', 'UTF-8'), 'name_text_encoding' => 'UTF-8', 'option_value' => 'enabled:theme'],
    ['option_id' => 5, 'option_name_bytes' => $enc261('plugin_über', 'UTF-16BE'), 'name_text_encoding' => 'UTF-16BE', 'option_value' => 'ENABLED:unicode'],
    ['option_id' => 6, 'option_name_bytes' => $enc261('plugin_blob', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => new SQLiteBlobValue('enabled:blob')],
    ['option_id' => 7, 'option_name_bytes' => $enc261('plugin_null', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => null],
    ['option_id' => 8, 'option_name_bytes' => $enc261('theme_plugin', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => 'enabled:other'],
];

$nextTwoSixOne = [
    ['option_id' => 1, 'option_name_bytes' => $enc261('Plugin_cache', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => 'enabled:core'],
    ['option_id' => 2, 'option_name_bytes' => $enc261('plugin_cache_timeout', 'UTF-16BE'), 'name_text_encoding' => 'UTF-16BE', 'option_value' => 'enabled:15'],
    ['option_id' => 3, 'option_name_bytes' => $enc261('plugin-cache', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => 'enabled:dash'],
    ['option_id' => 4, 'option_name_bytes' => $enc261('plugin_theme', 'UTF-8'), 'name_text_encoding' => 'UTF-8', 'option_value' => 'disabled:theme'],
    ['option_id' => 5, 'option_name_bytes' => $enc261('plugin_über', 'UTF-16BE'), 'name_text_encoding' => 'UTF-16BE', 'option_value' => 'ENABLED:unicode'],
    ['option_id' => 6, 'option_name_bytes' => $enc261('plugin_blob', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => new SQLiteBlobValue('enabled:blob')],
    ['option_id' => 7, 'option_name_bytes' => $enc261('plugin_null', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => null],
    ['option_id' => 9, 'option_name_bytes' => $enc261('plugin_new', 'UTF-16LE'), 'name_text_encoding' => 'UTF-16LE', 'option_value' => true],
];

$pattern261 = $enc261('plugin!_%', 'UTF-16LE');

$plan261 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $valuePattern = 'enabled:%',
    bool $caseSensitive = false,
    string $currentSource = 'main.wp_options@260',
    string $nextSource = 'main.wp_options@261',
    int $currentCookie = 260,
    int $nextCookie = 261,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressUtf16NameAndValueLikePlan(
    $current ?? $current261,
    $next ?? $nextTwoSixOne,
    $pattern261,
    'UTF-16LE',
    $valuePattern,
    '!',
    null,
    $caseSensitive,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt261 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases261 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoSixOne'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_name LIKE utf16(?) ESCAPE ? AND option_value LIKE ? /* text affinity current-source fence */'],
    'name pattern' => ['namePattern', 'plugin!_%'],
    'name pattern hex' => ['namePatternHex', '706c7567696e215f25'],
    'name pattern bytes' => ['namePatternBytesHex', '70006c007500670069006e0021005f002500'],
    'name pattern encoding' => ['namePatternEncoding', 'UTF-16LE'],
    'name escape' => ['nameEscape', '!'],
    'name escape hex' => ['nameEscapeHex', '21'],
    'name prefix' => ['namePrefix', 'plugin_'],
    'name prefix hex' => ['namePrefixHex', '706c7567696e5f'],
    'name binary lower' => ['nameBinaryRange.lowerInclusive', 'plugin_'],
    'name binary upper' => ['nameBinaryRange.upperBound', 'plugin`'],
    'name nocase lower' => ['nameNoCaseRange.lowerInclusive', 'plugin_'],
    'name nocase upper' => ['nameNoCaseRange.upperBound', 'plugin`'],
    'value pattern' => ['valuePattern', 'enabled:%'],
    'value pattern hex' => ['valuePatternHex', '656e61626c65643a25'],
    'value escape null' => ['valueEscape', null],
    'value prefix' => ['valuePrefix', 'enabled:'],
    'value binary lower' => ['valueBinaryRange.lowerInclusive', 'enabled:'],
    'value binary upper' => ['valueBinaryRange.upperBound', 'enabled;'],
    'collation' => ['collation', 'NOCASE'],
    'case flag' => ['caseSensitiveLike', false],
    'current source' => ['currentSource', 'main.wp_options@260'],
    'next source' => ['nextSource', 'main.wp_options@261'],
    'current cookie' => ['currentSchemaCookie', 260],
    'next cookie' => ['nextSchemaCookie', 261],
    'current candidates' => ['currentCandidateRowids', [6, 1, 2, 7, 4, 5]],
    'next candidates' => ['nextCandidateRowids', [6, 1, 2, 9, 7, 4, 5]],
    'current matched' => ['currentMatchedRowids', [1, 4, 5]],
    'next matched' => ['nextMatchedRowids', [1, 2, 5]],
    'retained matched' => ['retainedMatchedRowids', [1, 5]],
    'exited matched' => ['exitedMatchedRowids', [4]],
    'entered matched' => ['enteredMatchedRowids', [2]],
    'current unknown values' => ['currentUnknownValueRowids', [6, 7]],
    'next unknown values' => ['nextUnknownValueRowids', [6, 7]],
    'changed value text' => ['changedValueTextRowids', [2, 4]],
    'changed value storage' => ['changedValueStorageClassRowids', []],
    'changed truth' => ['changedCompositeTruthRowids', [2, 4]],
    'changed name text' => ['changedNameTextRowids', []],
    'current name text 1' => ['currentNameText.1', 'Plugin_cache'],
    'current name text 5' => ['currentNameText.5', 'plugin_über'],
    'current name hex 5' => ['currentNameTextHex.5', '706c7567696e5fc3bc626572'],
    'current name encoding 2' => ['currentNameEncoding.2', 'UTF-16BE'],
    'current value text 5' => ['currentValueText.5', 'ENABLED:unicode'],
    'next value text 2' => ['nextValueText.2', 'enabled:15'],
    'current value hex 1' => ['currentValueHex.1', '656e61626c65643a636f7265'],
    'current storage 6' => ['currentValueStorageClasses.6', 'unknown'],
    'next storage 9' => ['nextValueStorageClasses.9', 'integer'],
    'current composite 2' => ['currentCompositeMatches.2', false],
    'next composite 2' => ['nextCompositeMatches.2', true],
    'current name residual 3' => ['currentNameResidualMatches.3', false],
    'current value residual 5' => ['currentValueResidualMatches.5', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason rowset' => ['invalidationReasons.2', 'matched-rowset'],
    'reason truth' => ['invalidationReasons.3', 'composite-predicate-truth'],
    'reason value' => ['invalidationReasons.4', 'value-affinity-text'],
    'utf16 decoded flag' => ['utf16PatternDecodedBeforeLikeTokenization', true],
    'ascii nocase flag' => ['nameLikeUsesAsciiNoCaseOnly', true],
    'value affinity flag' => ['valueLikeAppliesTextAffinityBeforeResidual', true],
    'unknown flag' => ['blobAndNullValuesRemainUnknownForLike', true],
    'escaped underscore flag' => ['escapedUnderscoreInUtf16PatternIsLiteral', true],
    'dependency source cursor' => ['dependencies.0', 'sqlite-encoding-source-cursor'],
    'dependency tokenizer' => ['dependencies.1', 'sqlite-like-escape-tokenizer'],
    'dependency affinity' => ['dependencies.2', 'sqlite-text-affinity'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-nexttwoSixOne'],
];

foreach ($cases261 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoSixOne ' . $name] = static function (TestRunner $t) use ($plan261, $valueAt261, $path, $expected): void {
        $t->same($expected, $valueAt261($plan261(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoSixOne stable source is reusable'] = static function (TestRunner $t) use ($current261, $plan261): void {
    $stable = $plan261(current: $current261, next: $current261, currentSource: 'same', nextSource: 'same', currentCookie: 261, nextCookie: 261);
    $t->same(false, $stable['cursorInvalidated']);
    $t->same(true, $stable['cursorReusable']);
    $t->same([], $stable['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoSixOne case sensitive mode keeps uppercase name out'] = static function (TestRunner $t) use ($plan261): void {
    $plan = $plan261(caseSensitive: true);
    $t->same('BINARY', $plan['collation']);
    $t->same([6, 2, 7, 4, 5], $plan['currentCandidateRowids']);
    $t->same([4], $plan['currentMatchedRowids']);
};

$tests['encoding collation affinity like current source nextTwoSixOne boolean value uses integer text affinity'] = static function (TestRunner $t) use ($plan261): void {
    $plan = $plan261(valuePattern: '1');
    $t->same([9], $plan['nextMatchedRowids']);
    $t->same('1', $plan['nextValueText'][9]);
};

$tests['encoding collation affinity like current source nextTwoSixOne rejects malformed utf16 pattern'] = static function (TestRunner $t) use ($current261, $nextTwoSixOne): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressUtf16NameAndValueLikePlan($current261, $nextTwoSixOne, "\x00", 'UTF-16LE'));
};

$tests['encoding collation affinity like current source nextTwoSixOne rejects missing encoded name metadata'] = static function (TestRunner $t) use ($current261, $nextTwoSixOne, $pattern261): void {
    $bad = $current261;
    unset($bad[0]['name_text_encoding']);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressUtf16NameAndValueLikePlan($bad, $nextTwoSixOne, $pattern261, 'UTF-16LE'));
};

$tests['encoding collation affinity like current source nextTwoSixOne rejects array value'] = static function (TestRunner $t) use ($current261, $nextTwoSixOne, $pattern261): void {
    $bad = $current261;
    $bad[0]['option_value'] = ['enabled:core'];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressUtf16NameAndValueLikePlan($bad, $nextTwoSixOne, $pattern261, 'UTF-16LE'));
};

$tests['encoding collation affinity like current source nextTwoSixOne records dependency closure'] = static function (TestRunner $t) use ($plan261): void {
    $plan = $plan261();
    $t->contains('no new support component needed', $plan['dependency_closure']);
    $t->contains('avoids accepted nextTwoFourZero', $plan['non_overlap']);
};

return $tests;
