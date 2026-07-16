<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteNocaseLikeRtrimCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};

$currentRows = [
    $row(1, 'plugin_alpha   ', 'UTF-8'),
    $row(2, 'Plugin_Beta', 'UTF-16LE'),
    $row(3, 'plugin_cache%literal', 'UTF-16BE'),
    $row(4, 'theme_plugin', 'UTF-8'),
];
$nextRows = [
    $row(1, 'plugin_alpha', 'UTF-16LE'),
    $row(2, 'plugin_beta ', 'UTF-16LE'),
    $row(3, 'plugin_cache%literal ', 'UTF-16BE'),
    $row(5, 'plugin_fresh ', 'UTF-8'),
];

$plan = SQLiteNocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPlan(
    $currentRows,
    $nextRows,
    'plugin\_%',
    '\\',
);

$summary = [
    'expression' => $plan['expression'],
    'range' => $plan['range'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'enteredMatchedRowids' => $plan['enteredMatchedRowids'],
    'changedKeys' => $plan['changedNocaseRtrimKeyRowids'],
    'invalidated' => $plan['cursorInvalidated'],
    'dependencyClosure' => $plan['dependency_closure'],
];

assert($summary['expression'] === 'rtrim(key_name) COLLATE NOCASE');
assert($summary['range'] === ['lowerInclusive' => 'plugin_', 'upperBound' => 'plugin`']);
assert($summary['currentMatchedRowids'] === [1, 2, 3]);
assert($summary['nextMatchedRowids'] === [1, 2, 3, 5]);
assert($summary['enteredMatchedRowids'] === [5]);
assert($summary['changedKeys'] === [4, 5]);
assert($summary['invalidated'] === true);

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
