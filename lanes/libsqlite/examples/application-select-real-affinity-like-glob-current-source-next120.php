<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 1, 'option_name' => 'plugin_real_one', 'option_value' => 1.0, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_real_micro', 'option_value' => 0.000001, 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_real_huge', 'option_value' => 1.0e20, 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'plugin_text_one', 'option_value' => '1', 'autoload' => 'yes'],
];

$preview = [
    'scenario' => 'application-select-real-affinity-like-glob-current-source-next120',
    'applicationUse' => 'Copied wp_options diagnostics that store numeric option_value data as REAL can use SQLite-compatible LIKE/GLOB text affinity, preserving decimal points and exponent spelling without ext/sqlite.',
    'realOneLikeOptionIds' => array_column(SQLiteSelectSql::execute("SELECT option_id FROM wp_options WHERE option_value LIKE '1.0' ORDER BY option_id", ['wp_options' => $rows]), 'option_id'),
    'integerTextLikeOptionIds' => array_column(SQLiteSelectSql::execute("SELECT option_id FROM wp_options WHERE option_value LIKE '1' ORDER BY option_id", ['wp_options' => $rows]), 'option_id'),
    'microExponentLikeOptionIds' => array_column(SQLiteSelectSql::execute("SELECT option_id FROM wp_options WHERE option_value LIKE '1.0e-06' ORDER BY option_id", ['wp_options' => $rows]), 'option_id'),
    'hugeExponentGlobOptionIds' => array_column(SQLiteSelectSql::execute("SELECT option_id FROM wp_options WHERE option_value GLOB '1.0e+[2][0]' ORDER BY option_id", ['wp_options' => $rows]), 'option_id'),
    'dependencies' => ['sqlite-select-predicate-real-affinity', 'sqlite-like-glob-text-coercion'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($preview['realOneLikeOptionIds'] === [1]);
    assert($preview['integerTextLikeOptionIds'] === [4]);
    assert($preview['microExponentLikeOptionIds'] === [2]);
    assert($preview['hugeExponentGlobOptionIds'] === [3]);
    echo "application-select-real-affinity-like-glob-current-source-next120 self-test passed\n";
    return;
}

echo json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
