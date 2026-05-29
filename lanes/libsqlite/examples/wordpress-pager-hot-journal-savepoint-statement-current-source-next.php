<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerHotJournalSavepointStatementCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/wp-content/database/wp-next97.sqlite';
$dirty = [
    1 => $page('wp next97 dirty sqlite header after crashed plugin import'),
    2 => $page('wp next97 dirty wp_options root after crashed plugin import'),
    3 => $page('wp next97 dirty active_plugins after crashed plugin import'),
];
$clean = [
    1 => $page('wp next97 clean sqlite header before crashed plugin import'),
    2 => $page('wp next97 clean wp_options root before crashed plugin import'),
];
$savepointRoot = $page('wp next97 savepoint rewrites wp_options root');
$failedActivePlugins = $page('wp next97 failed statement writes active_plugins');
$retryActivePlugins = $page('wp next97 retry statement writes active_plugins');

$plan = SQLitePagerHotJournalSavepointStatementCurrentSourceNextPlan::plan(
    $databasePath,
    implode('', $dirty),
    $pageSize,
    'plugin-import',
    'insert-active-plugin',
    'retry-active-plugin',
    $clean,
    $dirty,
    [2 => $savepointRoot],
    [3 => $dirty[3]],
    [3 => $failedActivePlugins],
    [2 => $savepointRoot, 3 => $dirty[3]],
    [3 => $retryActivePlugins],
    false,
    true,
    true,
);

echo json_encode([
    'scenario' => 'wordpress_pager_hot_journal_savepoint_statement_current_source_next97',
    'status' => $plan['status'],
    'hotRecovered' => $plan['hot_recovered'],
    'savepoint' => $plan['savepoint'],
    'statement' => $plan['statement'],
    'nextStatement' => $plan['next_statement'],
    'statementRestoredPages' => $plan['statement_restored_page_numbers'],
    'finalSources' => $plan['final_sources'],
    'operationReasons' => array_column($plan['operations'], 'reason'),
    'hasRetryActivePlugins' => str_contains($plan['final_database_bytes'], 'retry statement writes active_plugins'),
    'hasFailedActivePlugins' => str_contains($plan['final_database_bytes'], 'failed statement writes active_plugins'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
