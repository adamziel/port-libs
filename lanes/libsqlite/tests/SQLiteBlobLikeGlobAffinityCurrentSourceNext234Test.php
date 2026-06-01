<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBlobValue;

$tests = [];

$currentRows234 = [
    ['setting_id' => 1, 'key_name' => 'service_text', 'key_value' => 'module:cache', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'module_blob', 'key_value' => new SQLiteBlobValue('module:cache'), 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'module_blob_upper', 'key_value' => new SQLiteBlobValue('MODULE:CACHE'), 'load_policy' => 'yes'],
    ['setting_id' => 4, 'key_name' => 'module_spaces', 'key_value' => 'module:cache  ', 'load_policy' => 'yes'],
    ['setting_id' => 5, 'key_name' => 'module_tab', 'key_value' => "Module:Cache\t", 'load_policy' => 'yes'],
    ['setting_id' => 6, 'key_name' => 'module_number', 'key_value' => 42, 'load_policy' => 'no'],
    ['setting_id' => 7, 'key_name' => 'module_null', 'key_value' => null, 'load_policy' => 'no'],
    ['setting_id' => 8, 'key_name' => 'module_bad_text', 'key_value' => "\xffmodule", 'load_policy' => 'no'],
    ['setting_id' => 9, 'key_name' => 'module_bad_blob', 'key_value' => new SQLiteBlobValue("\xffmodule"), 'load_policy' => 'no'],
    ['setting_id' => 10, 'key_name' => 'profile_text', 'key_value' => 'profile:cache', 'load_policy' => 'yes'],
];

$nextRows234 = [
    ['setting_id' => 1, 'key_name' => 'service_text', 'key_value' => 'module:cache', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'module_blob', 'key_value' => 'module:cache', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'module_blob_upper', 'key_value' => new SQLiteBlobValue('module:cache-extra'), 'load_policy' => 'yes'],
    ['setting_id' => 4, 'key_name' => 'module_spaces', 'key_value' => 'module:cache', 'load_policy' => 'yes'],
    ['setting_id' => 5, 'key_name' => 'module_tab', 'key_value' => "Module:Cache\t", 'load_policy' => 'yes'],
    ['setting_id' => 6, 'key_name' => 'module_number', 'key_value' => 43, 'load_policy' => 'no'],
    ['setting_id' => 7, 'key_name' => 'module_null', 'key_value' => null, 'load_policy' => 'no'],
    ['setting_id' => 8, 'key_name' => 'module_bad_text', 'key_value' => "\xffmodule", 'load_policy' => 'no'],
    ['setting_id' => 9, 'key_name' => 'module_bad_blob', 'key_value' => new SQLiteBlobValue("\xffmodule"), 'load_policy' => 'no'],
    ['setting_id' => 10, 'key_name' => 'profile_text', 'key_value' => 'profile:cache', 'load_policy' => 'yes'],
    ['setting_id' => 11, 'key_name' => 'module_added_text', 'key_value' => 'module:added', 'load_policy' => 'yes'],
    ['setting_id' => 12, 'key_name' => 'module_added_blob', 'key_value' => new SQLiteBlobValue('module:blob'), 'load_policy' => 'yes'],
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

$implicitLike234 = static fn (): array => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows234,
    $nextRows234,
    'module:%',
    'LIKE',
    'RTRIM',
    null,
    false,
    false,
);

$castLike234 = static fn (): array => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows234,
    $nextRows234,
    'module:%',
    'LIKE',
    'NOCASE',
    null,
    false,
    true,
);

$implicitGlob234 = static fn (): array => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows234,
    $nextRows234,
    'module:*',
    'GLOB',
    'BINARY',
    null,
    false,
    false,
);

$castGlob234 = static fn (): array => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows234,
    $nextRows234,
    'module:*',
    'GLOB',
    'BINARY',
    null,
    false,
    true,
);

$implicitCases234 = [
    'status' => ['status', 'blob-like-glob-affinity-current-source-next234'],
    'operator' => ['operator', 'LIKE'],
    'pattern' => ['pattern', 'module:%'],
    'collation' => ['collation', 'RTRIM'],
    'explicit cast flag' => ['explicitCastToText', false],
    'like blob compile option' => ['likeDoesNotMatchBlobs', true],
    'glob blob compile option' => ['globDoesNotMatchBlobs', true],
    'current source' => ['currentSource', 'main.app_settings@233'],
    'next source' => ['nextSource', 'main.app_settings@234'],
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
    'dependency blob behavior' => ['dependencies.0', 'sqlite-like-does-not-match-blobs'],
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
    'rtrim collation trims spaces' => [$implicitLike234, 'currentTrace', 4, 'collationKey', 'module:cache'],
    'rtrim collation keeps tab' => [$implicitLike234, 'currentTrace', 5, 'collationKey', "Module:Cache\t"],
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
    'cast current blob text' => [$castLike234, 'currentTrace', 2, 'likeText', 'module:cache'],
    'cast current blob uppercase matches like nocase' => [$castLike234, 'currentTrace', 3, 'matched', true],
    'cast current blob uppercase collation key' => [$castLike234, 'currentTrace', 3, 'collationKey', 'module:cache'],
    'cast current blob bytes hex' => [$castLike234, 'currentTrace', 2, 'bytesHex', '6D6F64756C653A6361636865'],
    'cast next added blob text' => [$castLike234, 'nextTrace', 12, 'likeText', 'module:blob'],
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
        ['setting_id' => 1, 'key_value' => 'module:cache'],
        ['setting_id' => 2, 'key_value' => new SQLiteBlobValue('module:cache')],
    ];
    $plan = SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'module:%', 'LIKE', 'BINARY', null, false, false, 'stable', 'stable', 7, 7);
    $t->same(true, $plan['cursorReusable']);
    $t->same([1], $plan['currentRowids']);
    $t->same([2], $plan['currentBlobSkippedRowids']);
};

$tests['blob like glob affinity current source next234 stable cast scan admits blob'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_value' => 'module:cache'],
        ['setting_id' => 2, 'key_value' => new SQLiteBlobValue('module:cache')],
    ];
    $plan = SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($rows, $rows, 'module:%', 'LIKE', 'BINARY', null, false, true, 'stable', 'stable', 7, 7);
    $t->same(true, $plan['cursorReusable']);
    $t->same([1, 2], $plan['currentRowids']);
    $t->same([], $plan['currentBlobSkippedRowids']);
};

$tests['blob like glob affinity current source next234 rejects bad operator'] = static function (TestRunner $t) use ($currentRows234): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows234, [], '%', 'REGEXP'));
};

$tests['blob like glob affinity current source next234 rejects glob escape'] = static function (TestRunner $t) use ($currentRows234): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows234, [], '*', 'GLOB', 'BINARY', '\\'));
};

$tests['blob like glob affinity current source next234 rejects bad collation'] = static function (TestRunner $t) use ($currentRows234): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($currentRows234, [], '%', 'LIKE', 'UNICODE'));
};

$tests['blob like glob affinity current source next234 rejects missing rowid'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan([['key_value' => 'module']], [], '%'));
};

$tests['blob like glob affinity current source next234 rejects missing value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => 1]], [], '%'));
};

$tests['blob like glob affinity current source next234 nonscalar value becomes malformed row'] = static function (TestRunner $t): void {
    $plan = SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan([['setting_id' => 1, 'key_value' => []]], [], '%');
    $t->same([1], $plan['currentMalformedRowids']);
    $t->same('SQLite BLOB LIKE/GLOB affinity next234 rows require scalar key_value values', $plan['currentErrors'][1]);
};

return $tests;
