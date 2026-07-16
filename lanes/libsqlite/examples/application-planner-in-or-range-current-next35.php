<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteMultiColumnRangePlan;

$indexes = [
    [
        'name' => 'idx_blog_name_autoload',
        'rootPage' => 91,
        'estimatedRows' => 30000,
        'sql' => 'CREATE INDEX idx_blog_name_autoload ON wp_options(blog_id, option_name, autoload)',
    ],
    [
        'name' => 'idx_blog_autoload_name',
        'rootPage' => 92,
        'estimatedRows' => 24000,
        'sql' => 'CREATE INDEX idx_blog_autoload_name ON wp_options(blog_id, autoload, option_name)',
    ],
    [
        'name' => 'idx_autoload_name',
        'rootPage' => 93,
        'estimatedRows' => 12000,
        'sql' => 'CREATE INDEX idx_autoload_name ON wp_options(autoload, option_name)',
    ],
];

$predicate = [
    'operator' => 'OR',
    'terms' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => 'IN', 'left' => ['column' => 'blog_id'], 'values' => [1, 2]],
                ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => '_transient_'],
            ],
        ],
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
                ['operator' => 'BETWEEN', 'left' => ['column' => 'option_name'], 'lower' => 'plugin_', 'upper' => 'plugin_zzzz'],
            ],
        ],
    ],
];

$plan = SQLiteMultiColumnRangePlan::chooseOrRange($indexes, $predicate, [['column' => 'option_name']]);

if (($argv[1] ?? null) === '--self-test') {
    if (!is_array($plan) || ($plan['strategy'] ?? null) !== 'multi-index-or') {
        fwrite(STDERR, "expected multi-index OR range plan\n");
        exit(1);
    }
    if (($plan['currentNextLoops'] ?? null) !== 3) {
        fwrite(STDERR, "expected two blog-id IN seeks plus one autoload seek\n");
        exit(1);
    }
    if (($plan['indexNames'] ?? null) !== ['idx_blog_name_autoload', 'idx_autoload_name']) {
        fwrite(STDERR, "expected separate wp_options indexes for OR arms\n");
        exit(1);
    }

    echo "application-planner-in-or-range-current-next35 self-test passed\n";
    exit(0);
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
