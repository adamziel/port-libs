<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLikeCollationPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'SiteURL', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'siteurl ', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'plugin_100%_enabled', 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'Plugin_100%_Enabled', 'autoload' => 'no'],
    ['option_id' => 6, 'option_name' => 'é_plugin', 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'É_plugin', 'autoload' => 'no'],
];

$preview = [
    'scenario' => 'application-like-collation-current-next65',
    'applicationUse' => 'Copied wp_options option_name scans can plan LIKE prefix ranges against BINARY/NOCASE/RTRIM indexes while preserving SQLite LIKE/GLOB matcher semantics for collated operands.',
    'defaultNoCasePlan' => SQLiteLikeCollationPlan::plan('site%', 'NOCASE'),
    'binaryDefaultPlan' => SQLiteLikeCollationPlan::plan('site%', 'BINARY'),
    'caseSensitiveBinaryPlan' => SQLiteLikeCollationPlan::plan('site%', 'BINARY', null, true),
    'escapedPluginMatches' => array_column(SQLiteLikeCollationPlan::filterRows($options, 'option_name', 'plugin\_100\%%', 'NOCASE', '\\'), 'option_id'),
    'collatedSqlLikeMatches' => array_column(SQLiteSelectSql::execute("SELECT option_id FROM wp_options WHERE option_name COLLATE BINARY LIKE 'site%' ORDER BY option_id", ['wp_options' => $options]), 'option_id'),
    'collatedSqlGlobMatches' => array_column(SQLiteSelectSql::execute("SELECT option_id FROM wp_options WHERE option_name COLLATE NOCASE GLOB 'site*' ORDER BY option_id", ['wp_options' => $options]), 'option_id'),
    'dependencies' => ['sqlite-like-collation-prefix-range'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($preview['defaultNoCasePlan']['indexUsable'] === true);
    assert($preview['binaryDefaultPlan']['rejectedReason'] === 'default_like_requires_nocase_index');
    assert($preview['caseSensitiveBinaryPlan']['indexUsable'] === true);
    assert($preview['escapedPluginMatches'] === [4, 5]);
    assert($preview['collatedSqlLikeMatches'] === [1, 2, 3]);
    assert($preview['collatedSqlGlobMatches'] === [2, 3]);
    echo "application-like-collation-current-next65 self-test passed\n";
    return;
}

echo json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
