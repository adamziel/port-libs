<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":[{"name":"seo","enabled":true,"priority":2},{"name":"cache","enabled":false,"priority":7}]}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'rules' => [
                ['name' => 'forms', 'enabled' => true, 'priority' => 4],
                ['name' => 'media', 'enabled' => false, 'priority' => 1],
            ],
        ])),
    ],
];

$sql = "SELECT o.option_name AS option_name, j.key AS rule_attr, j.atom AS rule_value, j.fullkey AS fullkey
          FROM wp_options AS o
          JOIN json_tree(o.option_value, '$.rules') AS j
            ON j.key IN ('name', 'priority') AND j.atom IS NOT NULL
         ORDER BY option_name, fullkey";

$result = SQLiteSelectSql::execute($sql, ['wp_options' => $rows]);
$plan = SQLiteSelectSql::plan($sql, ['wp_options' => $rows]);

$payload = [
    'scenario' => 'application-json-table-lateral-index-current-next33',
    'applicationUse' => 'Query copied wp_options plugin settings through lateral json_tree() joins whose ON predicates are advertised as JSON virtual-table index constraints without requiring ext/sqlite.',
    'rowCount' => count($result),
    'firstRule' => $result[0] ?? null,
    'lastRule' => $result[count($result) - 1] ?? null,
    'jsonTableIndex' => $plan['joins'][0]['jsonTableIndex'] ?? null,
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['rowCount'] !== 8) {
        fwrite(STDERR, "Expected 8 lateral JSON rows\n");
        exit(1);
    }
    if (($payload['jsonTableIndex']['constraintCount'] ?? null) !== 2) {
        fwrite(STDERR, "Expected two JSON table ON index constraints\n");
        exit(1);
    }
    if (($payload['firstRule']['rule_value'] ?? null) !== 'seo' || ($payload['lastRule']['rule_value'] ?? null) !== 1) {
        fwrite(STDERR, "Unexpected JSON lateral current/next rule boundary\n");
        exit(1);
    }

    fwrite(STDOUT, "application-json-table-lateral-index-current-next33 self-test passed\n");
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
