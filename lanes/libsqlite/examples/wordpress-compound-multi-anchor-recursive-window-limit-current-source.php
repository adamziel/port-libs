<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundMultiAnchorRecursiveWindowLimitCurrentSourceNextPlan;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 18],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 16],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 12],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 10],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 24],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 13],
];

$sql = <<<'SQL'
WITH RECURSIVE staged(id, label, weight) AS (
    VALUES (1, 'seed-a', 23)
    UNION
    VALUES (1, 'seed-a', 23), (2, 'seed-b', 19), (9, 'skip-me', 1)
    EXCEPT
    VALUES (9, 'skip-me', 1)
    UNION
    SELECT id + 2, label || ':' || (id + 2), weight - 5
      FROM staged
     WHERE id < 7
     ORDER BY weight DESC
     LIMIT 5 OFFSET 1
)
SELECT id,
       label,
       row_number() OVER (ORDER BY weight DESC, id) AS rank
  FROM staged
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (ORDER BY weight DESC, option_id) AS rank
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY rank, id
 LIMIT 6 OFFSET 1
SQL;

$summary = SQLiteCompoundMultiAnchorRecursiveWindowLimitCurrentSourceNextPlan::compare(
    $sql,
    ['wp_options' => $currentOptions],
    ['wp_options' => $nextOptions],
);

$smoke = [
    'status' => $summary['status'],
    'currentLabels' => array_column($summary['currentRows'], 'label'),
    'nextLabels' => array_column($summary['nextRows'], 'label'),
    'currentRecursiveLabels' => array_column($summary['recursive']['currentRows'], 'label'),
    'nextTruncatedLabels' => array_column($summary['limitTrace']['next']['truncatedAfterLimit'], 'label'),
    'replanReasons' => $summary['replanReasons'],
];

if (in_array('--self-test', $argv, true)) {
    $expected = [
        'status' => 'compound-multi-anchor-recursive-window-limit-current-source-ready',
        'currentLabels' => ['seed-b', 'home', 'seed-a:3', 'blogname', 'seed-b:4', 'seed-a:3:5'],
        'nextLabels' => ['rewrite_rules', 'siteurl', 'seed-a:3', 'home', 'seed-b:4', 'seed-a:3:5'],
        'currentRecursiveLabels' => ['seed-b', 'seed-a:3', 'seed-b:4', 'seed-a:3:5', 'seed-b:4:6'],
        'nextTruncatedLabels' => ['theme_mods', 'blogname', 'seed-b:4:6'],
        'replanReasons' => [
            'limited-compound-rowset-changed',
            'prelimit-compound-rowset-changed',
            'compound-anchor-before-recursive-arm',
            'window-before-compound-final-limit',
        ],
    ];
    if ($smoke !== $expected) {
        fwrite(STDERR, json_encode($smoke, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        exit(1);
    }

    echo "wordpress-compound-multi-anchor-recursive-window-limit-current-source self-test passed\n";
    exit(0);
}

echo json_encode($smoke, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
