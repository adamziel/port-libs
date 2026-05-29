<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceNextPlan;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 18],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 16],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 12],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 40],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 24],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 13],
];

$sql = <<<'SQL'
WITH RECURSIVE staged(id, label, weight) AS (
    VALUES (1, 'seed-empty', 30)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 1
      FROM staged
     WHERE id < 5
     ORDER BY weight DESC
     LIMIT 0
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
 LIMIT 4 OFFSET 1
SQL;

$summary = SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceNextPlan::compare(
    $sql,
    ['wp_options' => $currentOptions],
    ['wp_options' => $nextOptions],
);

$smoke = [
    'status' => $summary['status'],
    'currentLabels' => array_column($summary['currentRows'], 'label'),
    'nextLabels' => array_column($summary['nextRows'], 'label'),
    'recursiveRows' => $summary['recursive']['currentRows'],
    'currentSkippedLabels' => array_column($summary['limitTrace']['current']['skippedBeforeOffset'], 'label'),
    'nextSkippedLabels' => array_column($summary['limitTrace']['next']['skippedBeforeOffset'], 'label'),
    'replanReasons' => $summary['replanReasons'],
];

if (in_array('--self-test', $argv, true)) {
    $expected = [
        'status' => 'compound-exhausted-recursive-window-limit-current-source-ready',
        'currentLabels' => ['home', 'blogname'],
        'nextLabels' => ['siteurl', 'home', 'theme_mods', 'blogname'],
        'recursiveRows' => [],
        'currentSkippedLabels' => ['siteurl'],
        'nextSkippedLabels' => ['rewrite_rules'],
        'replanReasons' => [
            'recursive-limit-zero-exhausted-before-window-arm',
            'limited-compound-rowset-changed',
            'prelimit-compound-rowset-changed',
            'recursive-arm-empty-in-current-and-next',
            'window-before-compound-final-limit',
        ],
    ];
    if ($smoke !== $expected) {
        fwrite(STDERR, json_encode($smoke, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        exit(1);
    }

    echo "wordpress-compound-exhausted-recursive-window-limit-current-source self-test passed\n";
    exit(0);
}

echo json_encode($smoke, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
