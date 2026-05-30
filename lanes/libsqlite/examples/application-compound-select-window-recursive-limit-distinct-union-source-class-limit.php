<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 30],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 26],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 20],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 35],
    ['option_id' => 5, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 18],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 34],
    ['option_id' => 7, 'option_name' => 'plugin_queue', 'autoload' => 'yes', 'weight' => 17],
];

$sql = <<<'SQL'
WITH RECURSIVE staged(id, label, weight) AS (
    VALUES (1, 'seed', 40)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 4
      FROM staged
     WHERE id < 8
     ORDER BY weight DESC
     LIMIT 5
)
SELECT id,
       label,
       lead(label, 1, 'tail') OVER (ORDER BY weight DESC, id) AS window_label,
       cume_dist() OVER (ORDER BY weight DESC, id) AS window_rank
  FROM staged
UNION
SELECT option_id AS id,
       option_name AS label,
       lead(option_name, 1, 'tail') OVER (ORDER BY weight DESC, option_id) AS window_label,
       cume_dist() OVER (ORDER BY weight DESC, option_id) AS window_rank
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY window_rank DESC, id
 LIMIT 6 OFFSET 2
SQL;

$summary = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareDistinctUnionSourceClassLimit(
    $sql,
    ['wp_options' => $currentOptions],
    ['wp_options' => $nextOptions],
);

$smoke = [
    'status' => $summary['status'],
    'currentLabels' => array_column($summary['currentRows'], 'label'),
    'nextLabels' => array_column($summary['nextRows'], 'label'),
    'currentWindowLabels' => array_column($summary['currentRows'], 'window_label'),
    'nextWindowLabels' => array_column($summary['nextRows'], 'window_label'),
    'recursiveLimitRemaining' => $summary['recursive']['currentLimitRemaining'],
    'currentSkipped' => array_column($summary['limitTrace']['current']['skippedBeforeOffset'], 'label'),
    'nextTruncated' => array_column($summary['limitTrace']['next']['truncatedAfterLimit'], 'label'),
    'replanReasons' => $summary['replanReasons'],
];

if (in_array('--self-test', $argv, true)) {
    $expected = [
        'status' => 'compound-select-window-recursive-limit-current-source-distinct-union-source-class-limit-ready',
        'currentLabels' => ['seed:2:3:4', 'blogname', 'seed:2:3', 'home', 'seed:2', 'siteurl'],
        'nextLabels' => ['theme_mods', 'seed:2:3:4', 'blogname', 'seed:2:3', 'home', 'seed:2'],
        'currentWindowLabels' => ['seed:2:3:4:5', 'theme_mods', 'seed:2:3:4', 'blogname', 'seed:2:3', 'home'],
        'nextWindowLabels' => ['plugin_queue', 'seed:2:3:4:5', 'theme_mods', 'seed:2:3:4', 'blogname', 'seed:2:3'],
        'recursiveLimitRemaining' => 0,
        'currentSkipped' => ['seed:2:3:4:5', 'theme_mods'],
        'nextTruncated' => ['siteurl', 'seed', 'rewrite_rules'],
        'replanReasons' => [
            'distinct-union-after-window-arm-values',
            'recursive-limit-exhausted-before-final-compound-limit',
            'limited-compound-rowset-changed',
            'prelimit-compound-rowset-changed',
            'recursive-limit-exhausted',
        ],
    ];
    if ($smoke !== $expected) {
        fwrite(STDERR, json_encode($smoke, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        exit(1);
    }

    echo "application-compound-select-window-recursive-limit-current-source-distinct-union-source-class-limit self-test passed\n";
    exit(0);
}

echo json_encode($smoke, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
