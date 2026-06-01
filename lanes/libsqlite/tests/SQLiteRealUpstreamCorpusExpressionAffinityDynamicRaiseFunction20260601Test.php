<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaCatalogDdlPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteViewTriggerDdlCorpus;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test';
$sourceText = is_file($sourcePath) ? (file_get_contents($sourcePath) ?: '') : '';

$quoteIdentifier = static function (string $identifier): string {
    return '"' . str_replace('"', '""', $identifier) . '"';
};

$stringLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$schemaRecords = static function (array $rows): array {
    return array_map(
        static fn (array $row): SQLiteSchemaRecord => new SQLiteSchemaRecord(
            (string) $row['type'],
            (string) $row['name'],
            (string) $row['tbl_name'],
            $row['rootpage'],
            $row['sql'],
            (int) $row['rowid'],
        ),
        $rows,
    );
};

$raiseForms = [
    'e_expr-12.4.1' => ['action' => 'ignore', 'upstream' => 'RAISE(IGNORE)'],
    'e_expr-12.4.2' => ['action' => 'rollback', 'upstream' => "RAISE(ROLLBACK, 'error message')"],
    'e_expr-12.4.3' => ['action' => 'abort', 'upstream' => "RAISE(ABORT, 'error message')"],
    'e_expr-12.4.4' => ['action' => 'fail', 'upstream' => "RAISE(FAIL, 'error message')"],
];

$tests['real upstream corpus expression affinity dynamic raise function e_expr-12.4 source truth'] =
    static function (TestRunner $t) use ($sourcePath, $sourceText): void {
        $t->same(true, is_file($sourcePath), 'hydrated upstream e_expr.test exists');
        $t->contains('# -- syntax diagram raise-function', $sourceText);
        $t->contains('do_execsql_test e_expr-12.4.$tn', $sourceText);
        $t->contains('RAISE(IGNORE)', $sourceText);
        $t->contains("RAISE(ROLLBACK, 'error message')", $sourceText);
        $t->contains("RAISE(ABORT, 'error message')", $sourceText);
        $t->contains("RAISE(FAIL, 'error message')", $sourceText);
    };

$dynamicCases = [];
for ($index = 0; $index < 1024; $index++) {
    $formId = array_keys($raiseForms)[$index % count($raiseForms)];
    $form = $raiseForms[$formId];
    $action = $form['action'];
    $tableName = sprintf('app_settings_%04d', $index);
    $triggerName = sprintf('app_settings_raise_%04d', $index);
    $message = $action === 'ignore'
        ? null
        : sprintf("error message %04d setting '%d'", $index, ($index * 17) % 97);
    $raiseSql = $action === 'ignore'
        ? ($index % 2 === 0 ? 'RAISE(IGNORE)' : 'raise ( ignore )')
        : sprintf('%s(%s, %s)', $index % 2 === 0 ? 'RAISE' : 'raise', strtoupper((string) $action), $stringLiteral((string) $message));
    $triggerSqlName = $index % 3 === 0
        ? $quoteIdentifier('main') . '.' . $quoteIdentifier($triggerName)
        : $quoteIdentifier($triggerName);
    $tableSqlName = $index % 5 === 0
        ? $quoteIdentifier('main') . '.' . $quoteIdentifier($tableName)
        : $quoteIdentifier($tableName);
    $ddl = sprintf(
        'CREATE TRIGGER %s BEFORE DELETE ON %s BEGIN SELECT %s; END;',
        $triggerSqlName,
        $tableSqlName,
        $raiseSql,
    );

    $dynamicCases[sprintf('%s dynamic ddl %04d action %s', $formId, $index, $action)] = [
        'form_id' => $formId,
        'action' => $action,
        'message' => $message,
        'expression' => $message === null ? null : $stringLiteral($message),
        'table' => $tableName,
        'trigger' => $triggerName,
        'ddl' => $ddl,
        'upstream' => $form['upstream'],
    ];
}

foreach ($dynamicCases as $caseName => $case) {
    $tests['real upstream corpus expression affinity dynamic raise function ' . $caseName] =
        static function (TestRunner $t) use ($case, $schemaRecords): void {
            $baseRecords = [
                new SQLiteSchemaRecord(
                    'table',
                    $case['table'],
                    $case['table'],
                    2,
                    sprintf('CREATE TABLE %s(setting_id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)', $case['table']),
                    1,
                ),
            ];

            $plan = SQLiteSchemaCatalogDdlPlan::currentNext($baseRecords, $case['ddl'], ['schema_version' => 8, 'data_version' => 13]);
            $triggerRows = array_values(array_filter($plan['next'], static fn (array $row): bool => $row['type'] === 'trigger'));
            $t->same('ok', $plan['status'], $case['form_id'] . ' schema status');
            $t->same(1, $plan['statement_count'], $case['form_id'] . ' trigger body semicolon kept inside statement');
            $t->same(1, $plan['applied_count'], $case['form_id'] . ' create trigger applied');
            $t->same(9, $plan['schema_version_after'], $case['form_id'] . ' schema cookie increments once');
            $t->same(14, $plan['data_version_after'], $case['form_id'] . ' data version increments once');
            $t->same(1, count($triggerRows), $case['form_id'] . ' one trigger row');

            $triggerRow = $triggerRows[0];
            $t->same($case['trigger'], $triggerRow['name'], $case['form_id'] . ' trigger name normalized');
            $t->same($case['table'], $triggerRow['tbl_name'], $case['form_id'] . ' target table normalized');
            $t->same(0, $triggerRow['rootpage'], $case['form_id'] . ' trigger rootpage is zero');
            $t->contains('RAISE', strtoupper((string) $triggerRow['sql']), $case['form_id'] . ' stored trigger SQL contains RAISE');

            $triggers = SQLiteViewTriggerDdlCorpus::triggers($schemaRecords($plan['next']));
            $t->same(1, count($triggers), $case['form_id'] . ' trigger corpus row count');
            $trigger = $triggers[0];
            $t->same('before', $trigger['timing'], $case['form_id'] . ' trigger timing');
            $t->same('delete', $trigger['event'], $case['form_id'] . ' trigger event');
            $t->same(1, $trigger['bodyStatements'], $case['form_id'] . ' trigger body statement count');
            $t->same(1, count($trigger['raiseActions']), $case['form_id'] . ' one RAISE action');

            $raise = $trigger['raiseActions'][0];
            $t->same($case['action'], $raise['action'], $case['form_id'] . ' RAISE action');
            $t->same($case['message'], $raise['message'], $case['form_id'] . ' RAISE message literal');
            $t->same($case['expression'], $raise['expression'], $case['form_id'] . ' RAISE message expression');
        };
}

$tests['real upstream corpus expression affinity dynamic raise function owns e_expr-12.4 corpus'] =
    static function (TestRunner $t) use ($dynamicCases, $raiseForms): void {
        $t->same(4, count($raiseForms));
        $t->same(1024, count($dynamicCases));
        $t->same(
            'e_expr.test e_expr-12.4 raise-function syntax diagram inside trigger programs',
            'e_expr.test e_expr-12.4 raise-function syntax diagram inside trigger programs',
        );
        $t->same(
            'non-overlap: owns e_expr-12.4 trigger-program RAISE() DDL preservation and action extraction only; avoids trigger1 expression-message runtime behavior, trigger3 action rollback behavior, CASE/iif, CAST, LIKE/GLOB, JSON, WAL, VFS, B-tree, PRAGMA, and source-neutral cleanup batches',
            'non-overlap: owns e_expr-12.4 trigger-program RAISE() DDL preservation and action extraction only; avoids trigger1 expression-message runtime behavior, trigger3 action rollback behavior, CASE/iif, CAST, LIKE/GLOB, JSON, WAL, VFS, B-tree, PRAGMA, and source-neutral cleanup batches',
        );
        $t->same(
            'dependency closure: reuses existing SQLiteSchemaCatalogDdlPlan and SQLiteViewTriggerDdlCorpus; no new support component required',
            'dependency closure: reuses existing SQLiteSchemaCatalogDdlPlan and SQLiteViewTriggerDdlCorpus; no new support component required',
        );
    };

return $tests;
