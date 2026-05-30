<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'autoload_threshold', 'option_value' => '9223372036854775807'],
    ['option_id' => 2, 'option_name' => 'overflow_threshold', 'option_value' => '9223372036854775808'],
    ['option_id' => 3, 'option_name' => 'negative_overflow_threshold', 'option_value' => '-9223372036854775809'],
    ['option_id' => 4, 'option_name' => 'unsigned_threshold', 'option_value' => '18446744073709551615'],
];

$integerSql = "SELECT option_name, CAST(option_value AS INTEGER) AS threshold FROM wp_options ORDER BY threshold DESC, option_id LIMIT 3";
$numericSql = "SELECT option_name, CAST(option_value AS NUMERIC) AS threshold FROM wp_options WHERE CAST(option_value AS NUMERIC) > CAST('9223372036854775807' AS NUMERIC) ORDER BY threshold DESC";

$formatRows = static fn (array $rows): array => array_map(
    static fn (array $row): string => $row['option_name'] . ':' . (is_float($row['threshold']) ? sprintf('%.13E', $row['threshold']) : (string) $row['threshold']),
    $rows,
);

$summary = [
    'applicationUse' => 'Preview copied wp_options numeric threshold settings where parser-level CAST() follows SQLite int64 clamp and NUMERIC overflow promotion without requiring ext/sqlite.',
    'integerSql' => $integerSql,
    'integerCastRows' => $formatRows(SQLiteSelectSql::execute($integerSql, ['wp_options' => $options])),
    'numericSql' => $numericSql,
    'numericOverflowRows' => $formatRows(SQLiteSelectSql::execute($numericSql, ['wp_options' => $options])),
];

if (($argv[1] ?? null) === '--self-test') {
    $expectedInteger = [
        'autoload_threshold:9223372036854775807',
        'overflow_threshold:9223372036854775807',
        'unsigned_threshold:9223372036854775807',
    ];
    $expectedNumeric = [
        'unsigned_threshold:1.8446744073710E+19',
        'overflow_threshold:9.2233720368548E+18',
    ];

    if ($summary['integerCastRows'] !== $expectedInteger || $summary['numericOverflowRows'] !== $expectedNumeric) {
        fwrite(STDERR, "Unexpected CAST overflow summary\n");
        exit(1);
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
