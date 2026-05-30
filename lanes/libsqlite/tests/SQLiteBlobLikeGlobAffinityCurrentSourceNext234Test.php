<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBlobValue;

$tests = [];

$currentRows234 = [
    ['option_id' => 1, 'option_name' => 'plugin_text', 'option_value' => 'plugin:cache', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin:cache'), 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_blob_upper', 'option_value' => new SQLiteBlobValue('PLUGIN:CACHE'), 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'plugin_spaces', 'option_value' => 'plugin:cache  ', 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'plugin_tab', 'option_value' => "Plugin:Cache\t", 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => 'plugin_number', 'option_value' => 42, 'autoload' => 'no'],
    ['option_id' => 7, 'option_name' => 'plugin_null', 'option_value' => null, 'autoload' => 'no'],
    ['option_id' => 8, 'option_name' => 'plugin_bad_text', 'option_value' => "\xffplugin", 'autoload' => 'no'],
    ['option_id' => 9, 'option_name' => 'plugin_bad_blob', 'option_value' => new SQLiteBlobValue("\xffplugin"), 'autoload' => 'no'],
    ['option_id' => 10, 'option_name' => 'theme_text', 'option_value' => 'theme:cache', 'autoload' => 'yes'],
];

$nextRows234 = [
    ['option_id' => 1, 'option_name' => 'plugin_text', 'option_value' => 'plugin:cache', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_blob', 'option_value' => 'plugin:cache', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_blob_upper', 'option_value' => new SQLiteBlobValue('plugin:cache-extra'), 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'plugin_spaces', 'option_value' => 'plugin:cache', 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'plugin_tab', 'option_value' => "Plugin:Cache\t", 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => 'plugin_number', 'option_value' => 43, 'autoload' => 'no'],
    ['option_id' => 7, 'option_name' => 'plugin_null', 'option_value' => null, 'autoload' => 'no'],
    ['option_id' => 8, 'option_name' => 'plugin_bad_text', 'option_value' => "\xffplugin", 'autoload' => 'no'],
    ['option_id' => 9, 'option_name' => 'plugin_bad_blob', 'option_value' => new SQLiteBlobValue("\xffplugin"), 'autoload' => 'no'],
    ['option_id' => 10, 'option_name' => 'theme_text', 'option_value' => 'theme:cache', 'autoload' => 'yes'],
    ['option_id' => 11, 'option_name' => 'plugin_added_text', 'option_value' => 'plugin:added', 'autoload' => 'yes'],
    ['option_id' => 12, 'option_name' => 'plugin_added_blob', 'option_value' => new SQLiteBlobValue('plugin:blob'), 'autoload' => 'yes'],
];

$valueAt234 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && ctype_digit($part)) {
            $part = (int) $part;
        }
        $value = $value[$part];
    }

    return $value;
};

$rowById234 = static function (array $trace, int $rowid): array {
    foreach ($trace as $row) {
        if ($row['rowid'] === $rowid) {
            return $row;
        }
    }

    throw new RuntimeException("Missing trace row {$rowid}");
};

$implicitLike234 = static fn (): array => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan(
    $currentRows234,
    $nextRows234,
    'plugin:%',
    'LIKE',
    'RTRIM',
    null,
    false,
    false,
);

$castLike234 = static fn (): array => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan(
    $currentRows234,
    $nextRows234,
    'plugin:%',
    'LIKE',
    'NOCASE',
    null,
    false,
    true,
);

$implicitGlob234 = static fn (): array => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan(
    $currentRows234,
    $nextRows234,
    'plugin:*',
    'GLOB',
    'BINARY',
    null,
    false,
    false,
);

$castGlob234 = static fn (): array => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan(
    $currentRows234,
    $nextRows234,
    'plugin:*',
    'GLOB',
    'BINARY',
    null,
    false,
    true,
);

$implicitCases234 = [
    'status' => ['status', 'blob-like-glob-affinity-current-source-next234'],
    'operator' => ['operator', 'LIKE'],
    'pattern' => ['pattern', 'plugin:%'],
    'collation' => ['collation', 'RTRIM'],
    'explicit cast flag' => ['explicitCastToText', false],
    'like blob compile option' => ['likeDoesNotMatchBlobs', true],
    'glob blob compile option' => ['globDoesNotMatchBlobs', true],
    'current source' => ['currentSource', 'main.wp_options@233'],
    'next source' => ['nextSource', 'main.wp_options@234'],
    'current cookie' => ['currentSchemaCookie', 233],
    'next cookie' => ['nextSchemaCookie', 234],
    'implicit current rowids skip blobs' => ['currentRowids', [5, 1, 4]],
    'implicit next rowids include text conversion and new text only' => ['nextRowids', [5, 11, 1, 2, 4]],
    'retained rowids' => ['retainedRowids', [5, 1, 4]],
    'entered rowids' => ['enteredRowids', [11, 2]],
    'exited rowids' => ['exitedRowids', []],
    'current blob skipped rowids' => ['currentBlobSkippedRowids', [2, 3, 9]],
    'next blob skipped rowids' => ['nextBlobSkippedRowids', [3, 9, 12]],
    'current malformed text rowids' => ['currentMalformedRowids', [8]],
    'next malformed text rowids' => ['nextMalformedRowids', [8]],
    'changed storage rowids' => ['changedStorageRowids', [2, 11, 12]],
    'changed bytes rowids' => ['changedBytesRowids', [3, 4, 11, 12]],
    'changed like text rowids' => ['changedLikeTextRowids', [2, 4, 6, 11, 12]],
    'changed blob skip rowids' => ['changedBlobSkipRowids', [2, 11, 12]],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason schema' => ['invalidationReasons.1', 'schema-cookie'],
    'reason storage' => ['invalidationReasons.2', 'value-storage'],
    'reason bytes' => ['invalidationReasons.3', 'value-bytes'],
    'reason like text' => ['invalidationReasons.4', 'like-text'],
    'reason blob skip' => ['invalidationReasons.5', 'blob-skip-state'],
    'reason matched' => ['invalidationReasons.6', 'matched-rowset'],
    'reason malformed' => ['invalidationReasons.7', 'malformed-text'],
    'dependency blob option' => ['dependencies.0', 'sqlite-like-does-not-match-blobs'],
    'dependency cast admission' => ['dependencies.1', 'sqlite-explicit-cast-text-admission'],
    'dependency collation' => ['dependencies.2', 'sqlite-like-glob-collation'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-next234'],
];

foreach ($implicitCases234 as $name => [$path, $expected]) {
    $tests['blob like glob affinity current source next234 implicit ' . $name] = static function (TestRunner $t) use ($implicitLike234, $valueAt234, $path, $expected): void {
        $t->same($expected, $valueAt234($implicitLike234(), $path));
    };
}

$traceCases234 = [
    'blob row skipped has null like text' => [$implicitLike234, 'currentTrace', 2, 'likeText', null],
    'blob row skipped flag' => [$implicitLike234, 'currentTrace', 2, 'blobLikeSkipped', true],
    'blob row skipped does not match' => [$implicitLike234, 'currentTrace', 2, 'matched', false],
    'text row matches' => [$implicitLike234, 'currentTrace', 1, 'matched', true],
    'rtrim collation trims spaces' => [$implicitLike234, 'currentTrace', 4, 'collationKey', 'plugin:cache'],
    'rtrim collation keeps tab' => [$implicitLike234, 'currentTrace', 5, 'collationKey', "Plugin:Cache\t"],
    'integer value text' => [$implicitLike234, 'currentTrace', 6, 'likeText', '42'],
    'null value text' => [$implicitLike234, 'currentTrace', 7, 'likeText', ''],
    'malformed text error' => [$implicitLike234, 'currentErrors', 8, null, 'SQLite BLOB LIKE/GLOB affinity next234 text value is malformed UTF-8'],
    'next converted blob storage text' => [$implicitLike234, 'nextTrace', 2, 'storage', 'text'],
    'next converted blob matches' => [$implicitLike234, 'nextTrace', 2, 'matched', true],
    'next added blob remains skipped' => [$implicitLike234, 'nextTrace', 12, 'blobLikeSkipped', true],
];

foreach ($traceCases234 as $name => [$planFactory, $traceKey, $rowid, $field, $expected]) {
    $tests['blob like glob affinity current source next234 trace ' . $name] = static function (TestRunner $t) use ($planFactory, $rowById234, $traceKey, $rowid, $field, $expected): void {
        $plan = $planFactory();
        $row = $field === null ? $plan[$traceKey] : $rowById234($plan[$traceKey], $rowid);
        $t->same($expected, $field === null ? $row[$rowid] : $row[$field]);
    };
}

$castCases234 = [
    'cast flag' => ['explicitCastToText', true],
    'cast current rowids include valid blobs' => ['currentRowids', [1, 2, 3, 5, 4]],
    'cast next rowids include valid blobs' => ['nextRowids', [11, 12, 1, 2, 4, 5, 3]],
    'cast skips no valid blobs current' => ['currentBlobSkippedRowids', []],
    'cast skips no valid blobs next' => ['nextBlobSkippedRowids', []],
    'cast malformed current includes bad blob and bad text' => ['currentMalformedRowids', [8, 9]],
    'cast malformed next includes bad blob and bad text' => ['nextMalformedRowids', [8, 9]],
    'cast changed storage only text-converted row' => ['changedStorageRowids', [2, 11, 12]],
    'cast changed bytes includes blob rewrite' => ['changedBytesRowids', [3, 4, 11, 12]],
    'cast changed like text includes blob rewrite' => ['changedLikeTextRowids', [3, 4, 6, 11, 12]],
    'cast changed blob skip records inserted rows' => ['changedBlobSkipRowids', [11, 12]],
    'cast malformed reason remains' => ['invalidationReasons.7', 'malformed-text'],
];

foreach ($castCases234 as $name => [$path, $expected]) {
    $tests['blob like glob affinity current source next234 cast ' . $name] = static function (TestRunner $t) use ($castLike234, $valueAt234, $path, $expected): void {
        $t->same($expected, $valueAt234($castLike234(), $path));
    };
}

$castTraceCases234 = [
    'cast current blob text' => [$castLike234, 'currentTrace', 2, 'likeText', 'plugin:cache'],
    'cast current blob uppercase matches like nocase' => [$castLike234, 'currentTrace', 3, 'matched', true],
    'cast current blob uppercase collation key' => [$castLike234, 'currentTrace', 3, 'collationKey', 'plugin:cache'],
    'cast current blob bytes hex' => [$castLike234, 'currentTrace', 2, 'bytesHex', '706C7567696E3A6361636865'],
    'cast next added blob text' => [$castLike234, 'nextTrace', 12, 'likeText', 'plugin:blob'],
    'cast next added blob matches' => [$castLike234, 'nextTrace', 12, 'matched', true],
    'cast bad blob error' => [$castLike234, 'currentErrors', 9, null, 'SQLite BLOB LIKE/GLOB affinity next234 explicit CAST blob bytes are malformed UTF-8'],
];

foreach ($castTraceCases234 as $name => [$planFactory, $traceKey, $rowid, $field, $expected]) {
    $tests['blob like glob affinity current source next234 cast trace ' . $name] = static function (TestRunner $t) use ($planFactory, $rowById234, $traceKey, $rowid, $field, $expected): void {
        $plan = $planFactory();
        $row = $field === null ? $plan[$traceKey] : $rowById234($plan[$traceKey], $rowid);
        $t->same($expected, $field === null ? $row[$rowid] : $row[$field]);
    };
}

$globCases234 = [
    'implicit glob operator' => [$implicitGlob234, 'operator', 'GLOB'],
    'implicit glob current rowids' => [$implicitGlob234, 'currentRowids', [1, 4]],
    'implicit glob next rowids' => [$implicitGlob234, 'nextRowids', [11, 1, 2, 4]],
    'implicit glob skips blobs' => [$implicitGlob234, 'currentBlobSkippedRowids', [2, 3, 9]],
    'cast glob current rowids' => [$castGlob234, 'currentRowids', [1, 2, 4]],
    'cast glob next rowids' => [$castGlob234, 'nextRowids', [11, 12, 1, 2, 4, 3]],
    'cast glob current uppercase blob rejected by case sensitive glob' => [$castGlob234, 'currentTrace.1.matched', false],
    'cast glob next rewritten blob matches' => [$castGlob234, 'nextTrace.6.matched', true],
];

foreach ($globCases234 as $name => [$planFactory, $path, $expected]) {
    $tests['blob like glob affinity current source next234 glob ' . $name] = static function (TestRunner $t) use ($planFactory, $valueAt234, $path, $expected): void {
        $t->same($expected, $valueAt234($planFactory(), $path));
    };
}

$tests['blob like glob affinity current source next234 stable implicit scan is reusable'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 'plugin:cache'],
        ['option_id' => 2, 'option_value' => new SQLiteBlobValue('plugin:cache')],
    ];
    $plan = SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan($rows, $rows, 'plugin:%', 'LIKE', 'BINARY', null, false, false, 'stable', 'stable', 7, 7);
    $t->same(true, $plan['cursorReusable']);
    $t->same([1], $plan['currentRowids']);
    $t->same([2], $plan['currentBlobSkippedRowids']);
};

$tests['blob like glob affinity current source next234 stable cast scan admits blob'] = static function (TestRunner $t): void {
    $rows = [
        ['option_id' => 1, 'option_value' => 'plugin:cache'],
        ['option_id' => 2, 'option_value' => new SQLiteBlobValue('plugin:cache')],
    ];
    $plan = SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan($rows, $rows, 'plugin:%', 'LIKE', 'BINARY', null, false, true, 'stable', 'stable', 7, 7);
    $t->same(true, $plan['cursorReusable']);
    $t->same([1, 2], $plan['currentRowids']);
    $t->same([], $plan['currentBlobSkippedRowids']);
};

$tests['blob like glob affinity current source next234 rejects bad operator'] = static function (TestRunner $t) use ($currentRows234): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan($currentRows234, [], '%', 'REGEXP'));
};

$tests['blob like glob affinity current source next234 rejects glob escape'] = static function (TestRunner $t) use ($currentRows234): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan($currentRows234, [], '*', 'GLOB', 'BINARY', '\\'));
};

$tests['blob like glob affinity current source next234 rejects bad collation'] = static function (TestRunner $t) use ($currentRows234): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan($currentRows234, [], '%', 'LIKE', 'UNICODE'));
};

$tests['blob like glob affinity current source next234 rejects missing rowid'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan([['option_value' => 'plugin']], [], '%'));
};

$tests['blob like glob affinity current source next234 rejects missing value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan([['option_id' => 1]], [], '%'));
};

$tests['blob like glob affinity current source next234 nonscalar value becomes malformed row'] = static function (TestRunner $t): void {
    $plan = SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan([['option_id' => 1, 'option_value' => []]], [], '%');
    $t->same([1], $plan['currentMalformedRowids']);
    $t->same('SQLite BLOB LIKE/GLOB affinity next234 rows require scalar option_value values', $plan['currentErrors'][1]);
};

return $tests;
