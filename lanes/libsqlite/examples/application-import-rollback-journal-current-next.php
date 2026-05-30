<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournalCurrentNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$databasePath = '/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = $page('wp_options schema before import')
    . $page('wp_options active_plugins before import')
    . $page('wp_options autoload index before import');

$dirtyPages = [
    2 => $page('wp_options active_plugins after copied import'),
    3 => $page('wp_options autoload index after copied import'),
    4 => $page('wp_options new imported plugin option row'),
];

$plan = SQLiteRollbackJournalCurrentNextPlan::importTransaction(
    $databasePath,
    $databaseBytes,
    str_pad('rollback journal before copied wp_options import', $pageSize, "\0"),
    $dirtyPages,
    $pageSize,
    'full',
    'delete'
);

$summary = [
    'status' => $plan['status'],
    'databasePath' => $plan['database_path'],
    'dirtyPages' => $plan['dirty_pages'],
    'currentReader' => array_column($plan['current_reader'], 'image_prefix'),
    'nextReader' => array_column($plan['next_reader'], 'image_prefix'),
    'commitVisibleAt' => $plan['visibility'][6]['reason'],
    'dependencies' => $plan['dependencies'],
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
