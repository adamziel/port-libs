<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};

$currentRows = [
    $row(1, 'Plugin_Cache ', 'UTF-16LE'),
    $row(2, 'plugin_cache' . "\xc2\xa0", 'UTF-16BE'),
    $row(3, 'plugin_Éclair', 'UTF-16LE'),
    $row(4, 'plugin_éclair', 'UTF-16BE'),
];

$nextRows = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'plugin_cache' . "\xc2\xa0", 'UTF-16LE'),
    $row(3, 'plugin_Éclair ', 'UTF-16BE'),
    $row(4, 'plugin_éclair', 'UTF-16LE'),
    $row(5, 'PLUGIN_CACHE', 'UTF-16LE'),
];

$plan = static fn (string $pattern, ?string $escape = '\\'): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyUtf16NocaseRtrimPlan(
    $currentRows,
    $nextRows,
    $pattern,
    $escape,
    'main.app_settings@canonical',
    'main.app_settings@canonical',
    153,
    153,
);

$tests['utf16 nocase like rtrim canonical plan aliases existing implementation'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $alias = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyUtf16NocaseRtrimPlan($currentRows, $nextRows, 'plugin\\_cache', '\\', 'stable', 'stable', 1, 1);
    $legacy = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNoCasePlan($currentRows, $nextRows, 'plugin\\_cache', '\\', 'stable', 'stable', 1, 1);

    $t->same($legacy['status'], $alias['status']);
    $t->same($legacy['currentMatchedRowids'], $alias['currentMatchedRowids']);
    $t->same($legacy['nextMatchedRowids'], $alias['nextMatchedRowids']);
    $t->same($legacy['invalidationReasons'], $alias['invalidationReasons']);
};

$tests['utf16 nocase like rtrim canonical plan keeps sqlite rtrim space semantics'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('plugin\\_cache');

    $t->same([1, 2], $result['currentCandidateRowids']);
    $t->same([5, 1, 2], $result['nextCandidateRowids']);
    $t->same([], $result['currentMatchedRowids']);
    $t->same([5, 1], $result['nextMatchedRowids']);
    $t->same([1, 2], $result['currentFalsePositiveRowids']);
    $t->same([2], $result['nextFalsePositiveRowids']);
};

$tests['utf16 nocase like rtrim canonical plan remains ascii nocase only'] = static function (TestRunner $t) use ($plan): void {
    $lower = $plan('plugin\\_éclair');
    $upper = $plan('plugin\\_Éclair');

    $t->same([4], $lower['currentMatchedRowids']);
    $t->same([4], $lower['nextMatchedRowids']);
    $t->same([3], $upper['currentMatchedRowids']);
    $t->same([], $upper['nextMatchedRowids']);
};

$tests['utf16 nocase like rtrim canonical plan rejects malformed utf16 source rows'] = static function (TestRunner $t) use ($row, $nextRows): void {
    $badRows = [
        $row(1, 'plugin_cache', 'UTF-16LE'),
        [
            'option_id' => 2,
            'option_name_bytes' => "\xd8\x00",
            'text_encoding' => 3,
        ],
    ];

    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyUtf16NocaseRtrimPlan($badRows, $nextRows, 'plugin%');

    $t->same([2], $result['currentMalformedRowids']);
    $t->same('SQLite encoding source UTF-16 text payload ends with a high surrogate', $result['currentErrors'][2]);
    $t->same(true, in_array('malformed-text', $result['invalidationReasons'], true));
};

return $tests;
