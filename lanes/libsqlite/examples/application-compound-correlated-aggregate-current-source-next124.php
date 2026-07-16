<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'base_score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'base_score' => 200],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'autoload' => 'no', 'base_score' => 300],
    ['option_id' => 4, 'option_name' => 'missing_option', 'autoload' => 'no', 'base_score' => 400],
];
$meta = [
    ['option_name' => 'siteurl', 'meta_key' => 'size', 'bytes' => 10],
    ['option_name' => 'siteurl', 'meta_key' => 'autoload', 'bytes' => 5],
    ['option_name' => 'home', 'meta_key' => 'size', 'bytes' => 7],
    ['option_name' => 'active_plugins', 'meta_key' => 'size', 'bytes' => 12],
    ['option_name' => 'active_plugins', 'meta_key' => 'autoload', 'bytes' => 3],
];

$sql = "
SELECT option_name,
       (
           SELECT sum(bytes) + wp_options.option_id AS metric
           FROM wp_optionmeta
           WHERE wp_optionmeta.option_name = wp_options.option_name
           UNION ALL
           SELECT count(*) + wp_options.base_score AS metric
           FROM wp_optionmeta
           WHERE wp_optionmeta.option_name = wp_options.option_name
           ORDER BY metric DESC
           LIMIT 1
       ) AS import_metric
FROM wp_options
ORDER BY import_metric DESC, option_id
";

$rows = SQLiteSelectSql::execute($sql, [
    'wp_options' => $options,
    'wp_optionmeta' => $meta,
]);

$summary = [
    'scenario' => 'compound-correlated-aggregate-current-source-next124',
    'applicationUse' => 'Copied wp_options import audits can rank current options by a scalar compound subquery whose aggregate arms reference the current source row.',
    'rows' => $rows,
];

if (in_array('--self-test', $argv, true)) {
    $expected = [
        ['option_name' => 'missing_option', 'import_metric' => 400],
        ['option_name' => 'active_plugins', 'import_metric' => 302],
        ['option_name' => 'home', 'import_metric' => 201],
        ['option_name' => 'siteurl', 'import_metric' => 102],
    ];
    if ($rows !== $expected) {
        fwrite(STDERR, json_encode(['expected' => $expected, 'actual' => $summary], JSON_PRETTY_PRINT) . PHP_EOL);
        exit(1);
    }
    echo "application-compound-correlated-aggregate-current-source-next124 self-test passed\n";

    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
