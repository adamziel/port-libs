<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test';

$tests = [
    'real upstream e_fkey54 create table validation cites parent unchecked evidence' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'The parent key definitions of foreign key'));
        $t->true(is_string($source) && str_contains($source, 'There is nothing stopping the user'));
        $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-54.$tn.off'));
    },
    'real upstream e_fkey54 create table validation cites child key checks' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'unknown column "c" in foreign key definition'));
        $t->true(is_string($source) && str_contains($source, 'number of columns in foreign key does not match'));
        $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-54.$tn.on'));
    },
];

$definitions = static function (int $seed): array {
    $table = 'app_child_' . $seed;
    $parent = 'app_parent_' . $seed;
    $missingParent = 'missing_parent_' . $seed;
    $altParent = 'archive_parent_' . $seed;

    return [
        [
            'case' => 'e_fkey-54.1',
            'sql' => "CREATE TABLE {$table}(a, b REFERENCES {$table})",
            'table_columns' => ['a', 'b'],
            'child_columns' => ['b'],
            'parent_table' => $table,
            'parent_columns' => null,
            'expected_status' => 'commit-ok',
            'expected_error' => null,
            'parent_columns_explicit' => false,
            'reason' => 'self reference parent definition unchecked',
        ],
        [
            'case' => 'e_fkey-54.2',
            'sql' => "CREATE TABLE {$table}(a, b REFERENCES {$missingParent})",
            'table_columns' => ['a', 'b'],
            'child_columns' => ['b'],
            'parent_table' => $missingParent,
            'parent_columns' => null,
            'expected_status' => 'commit-ok',
            'expected_error' => null,
            'parent_columns_explicit' => false,
            'reason' => 'missing parent table allowed at create time',
        ],
        [
            'case' => 'e_fkey-54.3',
            'sql' => "CREATE TABLE {$table}(a, b, FOREIGN KEY(a,b) REFERENCES {$table})",
            'table_columns' => ['a', 'b'],
            'child_columns' => ['a', 'b'],
            'parent_table' => $table,
            'parent_columns' => null,
            'expected_status' => 'commit-ok',
            'expected_error' => null,
            'parent_columns_explicit' => false,
            'reason' => 'implicit composite parent key allowed at create time',
        ],
        [
            'case' => 'e_fkey-54.4',
            'sql' => "CREATE TABLE {$table}(a, b, FOREIGN KEY(a,b) REFERENCES {$missingParent})",
            'table_columns' => ['a', 'b'],
            'child_columns' => ['a', 'b'],
            'parent_table' => $missingParent,
            'parent_columns' => null,
            'expected_status' => 'commit-ok',
            'expected_error' => null,
            'parent_columns_explicit' => false,
            'reason' => 'implicit composite missing parent allowed at create time',
        ],
        [
            'case' => 'e_fkey-54.5',
            'sql' => "CREATE TABLE {$table}(a, b, FOREIGN KEY(a,b) REFERENCES {$missingParent})",
            'table_columns' => ['a', 'b'],
            'child_columns' => ['a', 'b'],
            'parent_table' => $missingParent,
            'parent_columns' => null,
            'expected_status' => 'commit-ok',
            'expected_error' => null,
            'parent_columns_explicit' => false,
            'reason' => 'repeat missing parent definition still allowed',
        ],
        [
            'case' => 'e_fkey-54.6',
            'sql' => "CREATE TABLE {$table}(a, b, FOREIGN KEY(a,b) REFERENCES {$missingParent}(n,d))",
            'table_columns' => ['a', 'b'],
            'child_columns' => ['a', 'b'],
            'parent_table' => $missingParent,
            'parent_columns' => ['n', 'd'],
            'expected_status' => 'commit-ok',
            'expected_error' => null,
            'parent_columns_explicit' => true,
            'reason' => 'explicit missing parent columns are not validated',
        ],
        [
            'case' => 'e_fkey-54.7',
            'sql' => "CREATE TABLE {$table}(a, b, FOREIGN KEY(a,b) REFERENCES {$parent}(a,b))",
            'table_columns' => ['a', 'b'],
            'child_columns' => ['a', 'b'],
            'parent_table' => $parent,
            'parent_columns' => ['a', 'b'],
            'expected_status' => 'commit-ok',
            'expected_error' => null,
            'parent_columns_explicit' => true,
            'reason' => 'explicit same-width parent key accepted at create time',
        ],
        [
            'case' => 'e_fkey-54.A',
            'sql' => "CREATE TABLE {$table}(a, b, FOREIGN KEY(c,b) REFERENCES {$altParent})",
            'table_columns' => ['a', 'b'],
            'child_columns' => ['c', 'b'],
            'parent_table' => $altParent,
            'parent_columns' => null,
            'expected_status' => 'schema-error',
            'expected_error' => 'unknown column "c" in foreign key definition',
            'parent_columns_explicit' => false,
            'reason' => 'child key column must exist in child table',
        ],
        [
            'case' => 'e_fkey-54.B',
            'sql' => "CREATE TABLE {$table}(a, b, FOREIGN KEY(c,b) REFERENCES {$altParent}(d))",
            'table_columns' => ['a', 'b'],
            'child_columns' => ['c', 'b'],
            'parent_table' => $altParent,
            'parent_columns' => ['d'],
            'expected_status' => 'schema-error',
            'expected_error' => 'number of columns in foreign key does not match the number of columns in the referenced table',
            'parent_columns_explicit' => true,
            'reason' => 'explicit parent child key arity mismatch is rejected',
        ],
    ];
};

foreach (range(1, 125) as $seed) {
    foreach ($definitions($seed) as $definition) {
        foreach ([false, true] as $foreignKeys) {
            $caseName = sprintf(
                'real upstream e_fkey54 create table validation seed %03d %s fk-%s',
                $seed,
                strtolower(str_replace('.', '-', (string) $definition['case'])),
                $foreignKeys ? 'on' : 'off'
            );
            $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyCreateTableValidationPlan([$definition], $foreignKeys);

            $tests[$caseName] = static function (TestRunner $t) use ($plan, $definition, $foreignKeys, $value): void {
                $actual = $plan();
                $t->same('e_fkey.test e_fkey-54.1..54.B', $actual['source']);
                $t->same('foreign-key-create-table-definition-validation', $actual['operation']);
                $t->same(1, $actual['case_count']);
                $t->same(0, $actual['foreign_keys_changed_result_count']);
                $t->same($foreignKeys, $actual['foreign_keys_enabled']);
                $t->same(false, $actual['parent_definition_checked_at_create']);
                $t->same(false, $actual['parent_table_required_at_create']);
                $t->same($definition['case'], $value($actual, 'cases.0.case'));
                $t->same($definition['expected_status'], $value($actual, 'cases.0.status'));
                $t->same($definition['expected_error'], $value($actual, 'cases.0.error'));
                $t->same($definition['parent_table'], $value($actual, 'cases.0.parent_table'));
                $t->same($definition['child_columns'], $value($actual, 'cases.0.child_columns'));
                $t->same($definition['parent_columns'], $value($actual, 'cases.0.parent_columns'));
                $t->same($definition['parent_columns_explicit'], $value($actual, 'cases.0.parent_columns_explicit'));
                $t->same($foreignKeys, $value($actual, 'cases.0.foreign_keys_enabled'));
                $t->same(false, $value($actual, 'cases.0.parent_definition_checked'));
                $t->same($definition['expected_status'] === 'commit-ok', $value($actual, 'cases.0.create_table_allowed'));
                $t->same($definition['reason'], $value($actual, 'cases.0.reason'));
                $t->same('sqlite-efkey54-create-table-checks-child-key-shape-only', $value($actual, 'dependencies.1'));
            };
        }
    }

    foreach ([false, true] as $foreignKeys) {
        $caseName = sprintf(
            'real upstream e_fkey54 create table validation aggregate seed %03d fk-%s',
            $seed,
            $foreignKeys ? 'on' : 'off'
        );
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyCreateTableValidationPlan($definitions($seed), $foreignKeys);

        $tests[$caseName] = static function (TestRunner $t) use ($plan, $foreignKeys): void {
            $actual = $plan();
            $t->same('e_fkey.test e_fkey-54.1..54.B', $actual['source']);
            $t->same($foreignKeys, $actual['foreign_keys_enabled']);
            $t->same(9, $actual['case_count']);
            $t->same(7, $actual['ok_count']);
            $t->same(2, $actual['schema_error_count']);
            $t->same(0, $actual['foreign_keys_changed_result_count']);
            $t->same(['e_fkey-54.A', 'e_fkey-54.B'], $actual['schema_error_cases']);
            $t->same(['e_fkey-54.1', 'e_fkey-54.2', 'e_fkey-54.3', 'e_fkey-54.4', 'e_fkey-54.5', 'e_fkey-54.6', 'e_fkey-54.7'], $actual['ok_cases']);
            $t->same('sqlite-efkey54-foreign-keys-pragma-does-not-change-create-table-validation', $actual['dependencies'][0]);
        };
    }
}

$tests['real upstream e_fkey54 create table validation rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyCreateTableValidationPlan([], true));
};

return $tests;
