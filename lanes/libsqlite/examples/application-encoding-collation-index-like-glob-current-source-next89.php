<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationIndexLikeGlobCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationIndexLikeGlobCurrentSourceNextPlan.php';

$makeRow = static function (int $id, string $name, string $encoding, string $autoload = 'yes'): array {
    return [
        'option_id' => $id,
        'option_name' => $name,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
        'autoload' => $autoload,
    ];
};

$currentRows = [
    $makeRow(1, 'Plugin_Alpha', 'UTF-8', 'no'),
    $makeRow(2, 'plugin_alpha', 'UTF-16LE'),
    $makeRow(3, 'plugin_beta', 'UTF-16BE'),
    $makeRow(4, 'plugin_100%_enabled', 'UTF-16LE'),
    $makeRow(5, 'plugin_100x_enabled', 'UTF-16BE'),
    $makeRow(6, 'plugin_éclair', 'UTF-8'),
    $makeRow(7, 'plugin_😀_cache', 'UTF-16LE'),
    $makeRow(9, 'plugin_old', 'UTF-8'),
    $makeRow(10, 'plugin_beta ', 'UTF-8'),
];

$nextRows = [
    $makeRow(1, 'Plugin_Alpha', 'UTF-16LE', 'no'),
    $makeRow(2, 'plugin_alpha', 'UTF-16LE'),
    $makeRow(3, 'plugin_beta', 'UTF-8'),
    $makeRow(4, 'plugin_100%_enabled', 'UTF-16BE'),
    $makeRow(5, 'plugin_100x_enabled', 'UTF-16BE'),
    $makeRow(6, 'plugin_éclair', 'UTF-8'),
    $makeRow(7, 'plugin_😀_cache_v2', 'UTF-16LE'),
    $makeRow(11, 'plugin_new', 'UTF-16BE'),
    $makeRow(12, 'Plugin_Éclair', 'UTF-16LE', 'no'),
];

$likePlan = SQLiteEncodingCollationIndexLikeGlobCurrentSourceNextPlan::optionRowNameIndexPlan(
    $currentRows,
    $nextRows,
    'plugin!_100!%%',
    'LIKE',
    'NOCASE',
    '!',
    false,
    'main.wp_options',
    'main.wp_options',
    21,
    22,
    3,
    4,
);

$globPlan = SQLiteEncodingCollationIndexLikeGlobCurrentSourceNextPlan::optionRowNameIndexPlan(
    $currentRows,
    $nextRows,
    'plugin_😀*',
    'GLOB',
    'BINARY',
);

$summary = [
    'scenario' => 'application-encoding-collation-index-like-glob-current-source-next89',
    'like' => [
        'range' => $likePlan['range'],
        'currentRowids' => $likePlan['currentRowids'],
        'nextRowids' => $likePlan['nextRowids'],
        'changedEncodingRowids' => $likePlan['changedEncodingRowids'],
        'invalidationReasons' => $likePlan['invalidationReasons'],
    ],
    'glob' => [
        'range' => $globPlan['range'],
        'currentRowids' => $globPlan['currentRowids'],
        'nextRowids' => $globPlan['nextRowids'],
        'changedBytesRowids' => $globPlan['changedBytesRowids'],
    ],
    'dependencies' => $likePlan['dependencies'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['like']['range']['lowerInclusive'] === 'plugin_100%');
    assert($summary['like']['currentRowids'] === [4]);
    assert($summary['like']['nextRowids'] === [4]);
    assert(in_array('schema-cookie', $summary['like']['invalidationReasons'], true));
    assert(in_array('collation-version', $summary['like']['invalidationReasons'], true));
    assert($summary['glob']['currentRowids'] === [7]);
    assert($summary['glob']['nextRowids'] === [7]);
    assert($summary['glob']['changedBytesRowids'] === [7]);
    echo "application-encoding-collation-index-like-glob-current-source-next89 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
