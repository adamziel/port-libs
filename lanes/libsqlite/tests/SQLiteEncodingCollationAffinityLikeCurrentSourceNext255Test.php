<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$tests = [];

$enc255 = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row255 = static fn (int $id, string $name, int $encoding, mixed $value): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc255($name, $encoding),
    'text_encoding' => $encoding,
    'option_value' => $value,
];

$current255 = [
    $row255(1, '_site_transient_update_plugins', 1, 'keep'),
    $row255(2, 'plugin_cache', 2, 'drop'),
    $row255(3, '9plugin_cache', 3, 'numeric-prefix'),
    $row255(4, 'Äplugin_cache', 2, 'umlaut'),
    $row255(5, 'Ωplugin_cache', 3, 'omega'),
    $row255(6, 'aplain', 1, 'ascii'),
    ['option_id' => 7, 'option_name' => 7, 'option_value' => 'integer-name'],
    ['option_id' => 8, 'option_name' => null, 'option_value' => 'null-name'],
    ['option_id' => 9, 'option_name' => new SQLiteBlobValue('plugin'), 'option_value' => 'blob-name'],
];

$nextTwoFiveFive = [
    $row255(1, '_site_transient_update_plugins', 1, 'keep-next'),
    $row255(2, 'plugin_cache', 2, 'drop'),
    $row255(3, '9plugin_cache', 3, 'numeric-prefix'),
    $row255(4, 'äplugin_cache', 2, 'lower-umlaut'),
    $row255(5, 'Ωplugin_cache', 3, 'omega'),
    $row255(6, 'aplain', 1, 'ascii'),
    ['option_id' => 7, 'option_name' => 7, 'option_value' => 'integer-name'],
    $row255(10, 'Éplugin_cache', 3, 'accented-new'),
];

$plan255 = static fn (
    string $pattern = '[^A-Za-z]plugin*',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@254',
    string $nextSource = 'main.app_settings@255',
    int $currentCookie = 254,
    int $nextCookie = 255,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationGlobClassFallbackPlan(
    $current ?? $current255,
    $next ?? $nextTwoFiveFive,
    $pattern,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt255 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases255 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFiveFive'],
    'operator' => ['operator', 'GLOB'],
    'expression' => ['expression', 'option_name GLOB ? /* bracket class has no prefix range */'],
    'pattern' => ['pattern', '[^A-Za-z]plugin*'],
    'pattern hex' => ['patternHex', '5b5e412d5a612d7a5d706c7567696e2a'],
    'range null' => ['range', null],
    'range unusable' => ['rangeUsable', false],
    'full scan required' => ['fullScanResidualRequired', true],
    'class pattern' => ['globCharacterClassPattern', true],
    'current source' => ['currentSource', 'main.app_settings@254'],
    'next source' => ['nextSource', 'main.app_settings@255'],
    'current cookie' => ['currentSchemaCookie', 254],
    'next cookie' => ['nextSchemaCookie', 255],
    'current rowids' => ['currentRowids', [3, 4, 5]],
    'next rowids' => ['nextRowids', [3, 10, 4, 5]],
    'retained rowids' => ['retainedRowids', [3, 4, 5]],
    'entered rowids' => ['enteredRowids', [10]],
    'exited rowids' => ['exitedRowids', []],
    'changed text rowids' => ['changedTextRowids', [4]],
    'changed bytes rowids' => ['changedBytesRowids', [4]],
    'changed encoding empty' => ['changedEncodingRowids', []],
    'changed storage empty' => ['changedStorageClassRowids', []],
    'changed residual empty' => ['changedResidualTruthRowids', []],
    'current row3 text' => ['currentText.3', '9plugin_cache'],
    'current row4 text' => ['currentText.4', 'Äplugin_cache'],
    'next row4 text' => ['nextText.4', 'äplugin_cache'],
    'next row10 text' => ['nextText.10', 'Éplugin_cache'],
    'current row4 utf16le bytes' => ['currentBytesHex.4', 'c40070006c007500670069006e005f0063006100630068006500'],
    'next row4 utf16le bytes' => ['nextBytesHex.4', 'e40070006c007500670069006e005f0063006100630068006500'],
    'next row10 utf16be bytes' => ['nextBytesHex.10', '00c90070006c007500670069006e005f00630061006300680065'],
    'current row3 encoding' => ['currentTextEncodings.3', 'UTF-16BE'],
    'next row10 encoding' => ['nextTextEncodings.10', 'UTF-16BE'],
    'current row3 storage' => ['currentStorageClasses.3', 'text'],
    'next row10 storage' => ['nextStorageClasses.10', 'text'],
    'current option value row5' => ['currentOptionValues.5', 'omega'],
    'next option value row10' => ['nextOptionValues.10', 'accented-new'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason cookie' => ['invalidationReasons.1', 'schema-cookie'],
    'reason full scan' => ['invalidationReasons.2', 'glob-class-full-scan'],
    'reason text value' => ['invalidationReasons.3', 'text-value'],
    'reason text bytes' => ['invalidationReasons.4', 'text-bytes'],
    'reason matched rowset' => ['invalidationReasons.5', 'matched-rowset'],
    'dependency class' => ['dependencies.0', 'sqlite-glob-character-class'],
    'dependency decoder' => ['dependencies.1', 'sqlite-mixed-utf-source-decoder'],
    'dependency affinity' => ['dependencies.2', 'sqlite-text-affinity'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoFiveFive'],
    'dependency closure' => ['dependency_closure', 'no new support component needed; reuses native UTF-8/UTF-16 decode, scalar text-affinity coercion, and GLOB bracket-class residual matching'],
];

foreach ($cases255 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFiveFive ' . $name] = static function (TestRunner $t) use ($plan255, $valueAt255, $path, $expected): void {
        $t->same($expected, $valueAt255($plan255(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFiveFive stable same source still records full scan fallback'] = static function (TestRunner $t) use ($current255, $plan255): void {
    $plan = $plan255(current: $current255, next: $current255, currentSource: 'same', nextSource: 'same', currentCookie: 255, nextCookie: 255);
    $t->same(['glob-class-full-scan'], $plan['invalidationReasons']);
    $t->same([3, 4, 5], $plan['currentRowids']);
    $t->same([3, 4, 5], $plan['nextRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveFive prefix pattern uses range and can be reusable'] = static function (TestRunner $t) use ($current255, $plan255): void {
    $plan = $plan255(pattern: 'plugin*', current: $current255, next: $current255, currentSource: 'same', nextSource: 'same', currentCookie: 255, nextCookie: 255);
    $t->same(['lowerInclusive' => 'plugin', 'upperBound' => 'plugio'], $plan['range']);
    $t->same(true, $plan['rangeUsable']);
    $t->same(false, $plan['fullScanResidualRequired']);
    $t->same([], $plan['invalidationReasons']);
    $t->same([2], $plan['currentRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveFive positive unicode class matches accents only'] = static function (TestRunner $t) use ($plan255): void {
    $plan = $plan255('[À-ÿ]plugin*');
    $t->same([4], $plan['currentRowids']);
    $t->same([10, 4], $plan['nextRowids']);
    $t->same([10], $plan['enteredRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveFive negated underscore class admits transient prefix'] = static function (TestRunner $t) use ($plan255): void {
    $plan = $plan255('[^A-Za-z0-9]site*');
    $t->same([1], $plan['currentRowids']);
    $t->same([1], $plan['nextRowids']);
    $t->same('_site_transient_update_plugins', $plan['currentText'][1]);
};

$tests['encoding collation affinity like current source nextTwoFiveFive integer option name uses text affinity'] = static function (TestRunner $t) use ($plan255): void {
    $plan = $plan255('[0-9]');
    $t->same([7], $plan['currentRowids']);
    $t->same('7', $plan['currentText'][7]);
    $t->same('integer', $plan['currentStorageClasses'][7]);
};

$tests['encoding collation affinity like current source nextTwoFiveFive null and blob names stay outside residual'] = static function (TestRunner $t) use ($plan255): void {
    $plan = $plan255('*');
    $t->same(false, in_array(8, $plan['currentRowids'], true));
    $t->same(false, in_array(9, $plan['currentRowids'], true));
};

$tests['encoding collation affinity like current source nextTwoFiveFive rejects missing option name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationGlobClassFallbackPlan([['option_id' => 1]], [], '*'));
};

$tests['encoding collation affinity like current source nextTwoFiveFive rejects malformed utf8 scalar'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationGlobClassFallbackPlan([['option_id' => 1, 'option_name' => "plugin_\xc3"]], [], '*'));
};

$tests['encoding collation affinity like current source nextTwoFiveFive rejects bad byte row'] = static function (TestRunner $t) use ($enc255): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationGlobClassFallbackPlan([
        ['option_id' => 1, 'option_name_bytes' => $enc255('plugin', 1), 'text_encoding' => 4],
    ], [], '*'));
};

return $tests;
