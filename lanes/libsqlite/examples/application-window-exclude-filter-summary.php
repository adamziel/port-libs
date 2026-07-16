<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWindowFunction;

$rows = [
    ['option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 20],
    ['option_name' => 'home', 'autoload' => 'yes', 'bytes' => 20],
    ['option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 40],
];

$summary = SQLiteWindowFunction::aggregateRows(
    array_column($rows, 'bytes'),
    array_column($rows, 'autoload'),
    1,
    1,
    'TIES',
    array_map(static fn (array $row): bool => $row['autoload'] === 'yes' || str_starts_with($row['option_name'], '_transient_'), $rows),
);

echo json_encode([
    'scenario' => 'application-window-exclude-filter-summary',
    'rows' => array_map(static function (array $row, array $window): array {
        return [
            'option_name' => $row['option_name'],
            'autoload' => $row['autoload'],
            'bytes' => $row['bytes'],
            'window_count' => $window['count'],
            'window_sum' => $window['sum'],
            'window_frame' => $window['frame'],
        ];
    }, $rows, $summary),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
