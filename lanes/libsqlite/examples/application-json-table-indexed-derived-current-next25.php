<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonTableDerivedIndex;

$options = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'autoload' => 'yes',
        'option_value' => '{"rules":[{"name":"seo","priority":2,"enabled":true},{"name":"cache","priority":7,"enabled":false}]}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'autoload' => 'yes',
        'option_value' => '{"rules":[{"name":"forms","priority":4,"enabled":true},{"name":"media","priority":1,"enabled":false}]}',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'autoload' => 'no',
        'option_value' => '{"rules":[]}',
    ],
];

$sql = "SELECT o.option_id AS option_id, o.option_name AS option_name, jt.key AS attr, jt.atom AS atom, jt.type AS json_type, jt.fullkey AS fullkey
          FROM wp_options AS o
          JOIN json_tree(o.option_value, '$.rules') AS jt ON jt.type IN ('text', 'integer', 'true', 'false')
         ORDER BY option_id, fullkey";

$plan = SQLiteJsonTableDerivedIndex::materialize($sql, ['wp_options' => $options], ['option_name', 'attr'], ['fullkey']);
$alphaPriorities = SQLiteJsonTableDerivedIndex::lookup($plan, [
    'option_name' => 'plugin_alpha_settings',
    'attr' => 'priority',
]);
$alphaPriorityPairs = SQLiteJsonTableDerivedIndex::adjacentFor($plan, [
    'option_name' => 'plugin_alpha_settings',
    'attr' => 'priority',
]);

$summary = [
    'scenario' => 'application-json-table-indexed-derived-current-next25',
    'derivedRows' => count($plan['rows']),
    'indexKeys' => count($plan['indexes']),
    'alphaPriorityAtoms' => array_column($alphaPriorities, 'atom'),
    'alphaPriorityCurrentNext' => array_map(
        static fn (array $pair): array => [
            'current' => $pair['current']['atom'],
            'next' => $pair['next']['atom'] ?? null,
            'currentFullkey' => $pair['current']['fullkey'],
            'nextFullkey' => $pair['next']['fullkey'] ?? null,
        ],
        $alphaPriorityPairs,
    ),
    'applicationUse' => 'Copied wp_options JSON settings can be materialized through parser-level json_tree(), indexed by option/attribute, and scanned as current/next rows for import staging without ext/sqlite.',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['derivedRows'] !== 12 || $summary['indexKeys'] !== 6 || $summary['alphaPriorityAtoms'] !== [2, 7]) {
        fwrite(STDERR, "application-json-table-indexed-derived-current-next25 self-test failed\n");
        exit(1);
    }

    echo "application-json-table-indexed-derived-current-next25 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
