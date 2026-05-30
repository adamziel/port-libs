<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectResult;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'payload' => new SQLiteBlobValue('url')],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'payload' => new SQLiteBlobValue('url')],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'payload' => new SQLiteBlobValue('text')],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'payload' => null],
    ['option_id' => 5, 'option_name' => 'orphaned', 'autoload' => null, 'payload' => true],
];

$optionMeta = [
    ['option_id' => 1, 'meta_key' => 'public', 'meta_value' => '1'],
    ['option_id' => 1, 'meta_key' => 'network', 'meta_value' => '1'],
    ['option_id' => 2, 'meta_key' => 'public', 'meta_value' => '0'],
    ['option_id' => 4, 'meta_key' => 'cache', 'meta_value' => null],
];

$publicMeta = SQLiteSelectResult::innerJoin(
    $options,
    $optionMeta,
    static fn (array $option, array $meta): bool => $option['option_id'] === $meta['option_id']
        && $meta['meta_key'] === 'public'
);

$leftMeta = SQLiteSelectResult::leftJoin(
    $options,
    $optionMeta,
    static fn (array $option, array $meta): bool => $option['option_id'] === $meta['option_id']
        && $meta['meta_key'] === 'public',
    ['option_id', 'meta_key', 'meta_value']
);

$allMetadata = SQLiteSelectResult::execute(
    SQLiteSelectResult::joinUsing($options, $optionMeta, ['option_id']),
    null,
    [
        ['column' => 'option_name'],
        ['column' => 'meta_key'],
    ]
);

$report = [
    'publicInnerJoinOptions' => array_column($publicMeta, 'option_name'),
    'leftJoinMetaKeys' => array_map(
        static fn (array $row): array => [
            'option' => $row['option_name'],
            'metaKey' => $row['meta_key'],
            'rightOptionId' => $row['right.option_id'],
        ],
        $leftMeta
    ),
    'usingJoinOrderPreview' => array_map(
        static fn (array $row): string => $row['option_name'] . ':' . $row['meta_key'],
        $allMetadata
    ),
    'applicationUse' => 'Preview copied wp_options rows joined to option metadata with SQLite INNER/LEFT/USING row-production semantics before import tooling runs SELECT ordering.',
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
