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
    ['option_id' => 1, 'meta_key' => 'public'],
    ['option_id' => 1, 'meta_key' => 'network'],
    ['option_id' => 3, 'meta_key' => 'public'],
    ['option_id' => null, 'meta_key' => 'null-does-not-match'],
];

$publicOptions = SQLiteSelectResult::whereExists(
    $options,
    static fn (array $row): array => array_values(array_filter(
        $optionMeta,
        static fn (array $meta): bool => $meta['option_id'] === $row['option_id'] && $meta['meta_key'] === 'public'
    ))
);

$selectedNames = SQLiteSelectResult::whereIn($options, 'option_name', ['siteurl', 'home', '_transient_feed']);
$notPublicWhenNullAppears = SQLiteSelectResult::whereIn($options, 'option_id', array_column($optionMeta, 'option_id'), true);
$orderedPublic = SQLiteSelectResult::execute($publicOptions, null, [['column' => 'option_name']], 10);

$report = [
    'publicExistsOptions' => array_column($publicOptions, 'option_name'),
    'selectedNameInOptions' => array_column($selectedNames, 'option_name'),
    'notInWithNullOptions' => array_column($notPublicWhenNullAppears, 'option_name'),
    'orderedPublicPreview' => array_column($orderedPublic, 'option_name'),
    'applicationUse' => 'Preview copied wp_options rows filtered by correlated EXISTS and IN subqueries without ext/sqlite before import or repair tooling applies SELECT result ordering.',
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
