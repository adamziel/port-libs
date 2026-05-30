<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

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
    $row(1, 'Plugin_Cache   ', 'UTF-16LE'),
    $row(2, 'plugin_cache_extra ', 'UTF-16BE'),
    $row(3, 'theme_cache', 'UTF-16LE'),
];
$nextRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache_extra_v2 ', 'UTF-16BE'),
    $row(4, 'plugin_cache_new ', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNamePlan(
    $currentRows,
    $nextRows,
    'plugin\_%',
    '\\',
);

$summary = [
    'scenario' => 'application-utf16-nocase-like-rtrim-current-source-next157',
    'applicationUse' => 'Copied wp_options scans can keep UTF-16 option_name source rows on an ASCII NOCASE LIKE prefix range over RTRIM index keys, while detecting byte-order changes that invalidate stale import cursors.',
    'expression' => $plan['expression'],
    'range' => $plan['range'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'enteredMatchedRowids' => $plan['enteredMatchedRowids'],
    'changedByteOrderRowids' => $plan['changedByteOrderRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
];

assert($summary['expression'] === 'rtrim(option_name) COLLATE NOCASE /* UTF-16 source */');
assert($summary['range'] === ['lowerInclusive' => 'plugin_', 'upperBound' => 'plugin`']);
assert($summary['currentMatchedRowids'] === [1, 2]);
assert($summary['nextMatchedRowids'] === [1, 2, 4]);
assert($summary['enteredMatchedRowids'] === [4]);
assert($summary['changedByteOrderRowids'] === [1, 3, 4]);
assert(in_array('utf16-byte-order', $summary['invalidationReasons'], true));

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
