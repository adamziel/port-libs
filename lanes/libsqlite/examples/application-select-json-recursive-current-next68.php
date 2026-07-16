<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteBlobValue.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonB.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonCanonical.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonSubtypeValue.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonPath.php';
require_once dirname(__DIR__) . '/src/SQLiteJson5Parser.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonValidity.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonInspection.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonEach.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonTree.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonTablePlan.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonTableDerivedIndex.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectExpression.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectPredicate.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectProjection.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectQuery.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectResult.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectSql.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectRecursiveJsonMaterialization.php';

use PortLibs\LibSqlite\SQLiteSelectRecursiveJsonMaterialization;

$options = [
    ['option_id' => 1, 'option_name' => 'menu_root', 'autoload' => 'yes', 'option_value' => '{"next":[2,3],"items":[{"label":"home","weight":1},{"label":"docs","weight":4}]}'],
    ['option_id' => 2, 'option_name' => 'menu_docs', 'autoload' => 'yes', 'option_value' => '{"next":[4],"items":[{"label":"api","weight":9},{"label":"guides","weight":6}]}'],
    ['option_id' => 3, 'option_name' => 'menu_blog', 'autoload' => 'no', 'option_value' => '{"next":[4],"items":[{"label":"news","weight":5},{"label":"events","weight":2}]}'],
    ['option_id' => 4, 'option_name' => 'menu_leaf', 'autoload' => 'yes', 'option_value' => '{"next":[2],"items":[{"label":"sync","weight":11},{"label":"cleanup","weight":0}]}'],
];

$sql = "WITH RECURSIVE crawl(option_id, depth, source) AS MATERIALIZED (
            VALUES (1, 0, 'seed')
            UNION
            SELECT CAST(edge.atom AS INTEGER), crawl.depth + 1, host.option_name
              FROM crawl
              JOIN wp_options AS host ON host.option_id = crawl.option_id
              JOIN json_each(host.option_value, '$.next') AS edge ON edge.type = 'integer'
             WHERE crawl.depth < 3
             ORDER BY depth DESC, option_id ASC
             LIMIT 5 OFFSET 1
        )
        SELECT crawl.option_id AS option_id,
               crawl.depth AS depth,
               crawl.source AS source,
               host.option_name AS option_name,
               item.key AS attr,
               item.atom AS atom,
               item.fullkey AS fullkey
          FROM crawl
          JOIN wp_options AS host ON host.option_id = crawl.option_id
          JOIN json_tree(host.option_value, '$.items') AS item ON item.type IN ('text', 'integer')
         ORDER BY crawl.depth, crawl.option_id, item.fullkey";

$plan = SQLiteSelectRecursiveJsonMaterialization::materialize($sql, ['wp_options' => $options], ['option_id', 'attr'], ['fullkey']);
$frontier = SQLiteSelectRecursiveJsonMaterialization::recursiveJsonCurrentNextFrontier($plan, ['option_id', 'depth']);

$summary = [
    'scenario' => 'copied wp_options recursive JSON current-next frontier',
    'materializedRows' => count($plan['rows']),
    'frontierKeys' => array_map(static fn (array $entry): array => $entry['currentKey'], $frontier),
    'firstAcceptedJsonCounts' => $frontier[0]['acceptedNextJsonCounts'] ?? [],
    'terminalStatus' => $frontier[count($frontier) - 1]['status'] ?? null,
    'skippedDuplicateKeys' => array_merge(...array_map(static fn (array $entry): array => $entry['skippedDuplicateKeys'], $frontier)),
];

if (in_array('--self-test', $argv, true)) {
    $expected = $summary['materializedRows'] === 20
        && ($summary['frontierKeys'][0] ?? null) === ['option_id' => 1, 'depth' => 0]
        && ($summary['firstAcceptedJsonCounts'] ?? []) === [4, 4]
        && $summary['terminalStatus'] === 'terminal-current'
        && $summary['skippedDuplicateKeys'] === [];

    if (!$expected) {
        fwrite(STDERR, 'Unexpected recursive JSON current-next68 summary: ' . json_encode($summary) . PHP_EOL);
        exit(1);
    }

    echo "application-select-json-recursive-current-next68 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
