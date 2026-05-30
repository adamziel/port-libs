<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteFts5Corpus;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_cache_settings',
        'option_value' => 'Cache plugin stores option values and refreshes cache entries quickly.',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_search_index',
        'option_value' => 'Search plugin builds an index for posts, pages, and product content.',
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_cache_search_bridge',
        'option_value' => 'Search cache bridge links plugin search results to cached option metadata.',
        'autoload' => 'yes',
    ],
];

$query = 'search cach*';
$rows = SQLiteFts5Corpus::search($options, ['option_name', 'option_value'], $query, [
    'columnWeights' => ['option_name' => 3.0, 'option_value' => 1.0],
    'snippetColumn' => 'option_value',
    'snippetTokens' => 8,
]);

$payload = [
    'wordpressUse' => 'Preview copied wp_options text through bounded FTS5-style MATCH ranking and snippet diagnostics before a native virtual-table executor is available, without requiring ext/sqlite.',
    'query' => $query,
    'selectedOptionIds' => array_column($rows, 'option_id'),
    'snippets' => array_column($rows, 'fts5_snippet', 'option_name'),
    'ranks' => array_column($rows, 'fts5_rank', 'option_name'),
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['selectedOptionIds'] !== [3]) {
        fwrite(STDERR, "wordpress-fts5-option-search self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-fts5-option-search self-test passed\n");
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
