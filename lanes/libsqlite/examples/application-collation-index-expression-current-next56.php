<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteExpressionIndexCollationCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$normalizeSlug = static function (string $value): string {
    return str_replace('_', '-', strtolower(rtrim($value, ' ')));
};

$cursor = new SQLiteExpressionIndexCollationCursor(
    [
        ['key' => ['yes', 'plugin_mode', 'plugin  ', '08'], 'rowid' => 4, 'payload' => ['option_name' => 'Plugin_Mode']],
        ['key' => ['yes', 'plugin-mode', 'plugin', 8], 'rowid' => 2, 'payload' => ['option_name' => 'plugin-mode']],
        ['key' => ['yes', 'plugin_beta', 'Plugin_B', 4], 'rowid' => 9, 'payload' => ['option_name' => 'Plugin_Beta']],
        ['key' => ['no', 'theme-mode', 'theme   ', 5], 'rowid' => 11, 'payload' => ['option_name' => 'theme-mode']],
    ],
    [
        ['expression' => 'autoload', 'collation' => 'BINARY'],
        ['expression' => 'lower(option_name)', 'collation' => 'WPSLUG'],
        ['expression' => 'substr(option_name,1,8)', 'collation' => 'RTRIM'],
        ['expression' => 'length(option_value)', 'affinity' => 'INTEGER'],
    ],
    [
        'WPSLUG' => static fn (string $left, string $right): int => strcmp($normalizeSlug($left), $normalizeSlug($right)),
    ],
);

$matched = $cursor->yieldEqual(['yes', 'PLUGIN-MODE']);
$cursor->rewind();
$firstBoundary = $cursor->currentNextPlan();
$cursor->next();
$pluginBoundary = $cursor->currentNextPlan();

echo json_encode([
    'scenario' => 'application-collation-index-expression-current-next56',
    'matchedPluginModeRowids' => array_column($matched, 'rowid'),
    'firstBoundary' => [
        'currentRowid' => $firstBoundary['currentRowid'],
        'nextRowid' => $firstBoundary['nextRowid'],
        'decidingExpression' => $firstBoundary['decidingExpression'],
        'decidingCollation' => $firstBoundary['decidingCollation'],
    ],
    'pluginBoundary' => [
        'currentRowid' => $pluginBoundary['currentRowid'],
        'nextRowid' => $pluginBoundary['nextRowid'],
        'comparison' => $pluginBoundary['comparison'],
        'decidingExpression' => $pluginBoundary['decidingExpression'],
    ],
    'applicationUse' => 'Copied wp_options expression-index scans preserve current/next cursor boundaries under custom expression collations before rowid tie-breaks.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
