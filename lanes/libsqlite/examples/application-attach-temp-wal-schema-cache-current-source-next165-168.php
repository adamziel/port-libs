<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => [
        'schema_cookie' => 165,
        'tables' => ['wp_options', 'wp_posts', 'wp_terms'],
        'indexes' => ['wp_options_name', 'wp_posts_date'],
    ],
    'temp' => [
        'schema_cookie' => 65,
        'tables' => ['wp_plugin_stage'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 95,
        'tables' => ['wp_terms_archive'],
        'indexes' => ['wp_archive_terms_slug'],
        'file' => '/srv/wp/archive-next165.sqlite',
    ],
];

$statements = [
    ['name' => 'active-temp-stage-index-reader', 'sql' => 'SELECT option_name FROM temp.wp_plugin_stage INDEXED BY wp_plugin_stage_slug WHERE option_name = ?', 'active' => true],
    ['name' => 'posts-delete-writer', 'sql' => 'DELETE FROM wp_posts WHERE post_status = ?'],
    ['name' => 'network-options-reader', 'sql' => 'SELECT option_value FROM network.wp_options WHERE option_name = ?'],
    ['name' => 'terms-reader', 'sql' => 'SELECT term_id FROM wp_terms WHERE slug = ?'],
    ['name' => 'archive-terms-reader', 'sql' => 'SELECT term_id FROM archive.wp_terms_archive WHERE slug = ?'],
];

$events = [
    ['op' => 'create_index', 'schema' => 'temp', 'index' => 'wp_plugin_stage_slug'],
    ['op' => 'attach', 'schema' => 'network', 'schema_cookie' => 167, 'tables' => ['wp_options'], 'indexes' => ['wp_network_options_name'], 'file' => '/srv/wp/network-next167.sqlite'],
    ['op' => 'rename_table', 'schema' => 'main', 'from' => 'wp_terms', 'to' => 'wp_terms_2026'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['event_count'] === 3);
    assert($plan['changed_schemas'] === ['temp', 'main', 'network']);
    assert($plan['statements']['active-temp-stage-index-reader']['index_transitions'][0]['next_found'] === true);
    assert($plan['statements']['network-options-reader']['schema_transitions'][0]['next_schema'] === 'network');
    assert($plan['statements']['terms-reader']['schema_transitions'][0]['next_found'] === false);
    assert(in_array('archive-terms-reader', $plan['stable_statements'], true));

    echo "application-attach-temp-wal-schema-cache-current-source-next165-168 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
