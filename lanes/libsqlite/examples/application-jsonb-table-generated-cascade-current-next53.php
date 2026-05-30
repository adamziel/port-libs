<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbGeneratedCascadePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$plan = SQLiteJsonbGeneratedCascadePlan::plan(
    [
        ['option_id' => 1, 'option_name' => 'site_one_settings', 'option_value' => $jsonb(['site' => ['id' => 'site-1', 'theme' => 'twentysixteen'], 'source' => 'current'])],
        ['option_id' => 2, 'option_name' => 'site_two_settings', 'option_value' => $jsonb(['site' => ['id' => 'site-2', 'theme' => 'twentytwenty'], 'source' => 'current'])],
    ],
    [
        ['meta_id' => 10, 'site_key' => 'site-1', 'meta_key' => 'home_url'],
        ['meta_id' => 11, 'site_key' => 'site-1', 'meta_key' => 'upload_path'],
        ['meta_id' => 12, 'site_key' => 'site-2', 'meta_key' => 'rewrite_rules'],
    ],
    [['site_key' => 'site-1', 'new_site_key' => 'site-1-imported']],
    ['site-2'],
    [
        'parent_column' => 'site_key',
        'source_column' => 'option_value',
        'json_path' => '$.site.id',
        'child_column' => 'site_key',
        'on_update' => 'CASCADE',
        'on_delete' => 'CASCADE',
    ],
);

echo json_encode([
    'changes' => $plan['changes'],
    'after_parent_keys' => array_column($plan['after_parent'], 'site_key'),
    'after_child_keys' => array_column($plan['after_child'], 'site_key'),
    'actions' => array_column($plan['actions'], 'action'),
    'violations' => $plan['violations'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
