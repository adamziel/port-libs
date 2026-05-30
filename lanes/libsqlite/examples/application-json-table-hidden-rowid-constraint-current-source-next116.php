<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = SQLiteSelectSql::execute(
    "SELECT key AS option_index,
            atom AS plugin_slug,
            rowid AS json_rowid
       FROM json_each('[\"cache\",\"seo\",\"forms\"]') AS plugin_rows
      WHERE plugin_rows.rowid = 2",
    [],
);

if (($argv[1] ?? null) === '--self-test') {
    if ($rows !== [['option_index' => 1, 'plugin_slug' => 'seo', 'json_rowid' => 2]]) {
        fwrite(STDERR, 'Unexpected JSON table hidden rowid constraint result' . PHP_EOL);
        exit(1);
    }

    echo "application-json-table-hidden-rowid-constraint-current-source-next116 self-test passed\n";
    exit(0);
}

echo json_encode($rows, JSON_PRETTY_PRINT) . PHP_EOL;
