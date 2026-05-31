<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteForeignKeySchemaRequirementPlan
{
    /**
     * @return array<string,mixed>
     */
    public static function eFkeyRequiredIndexCorpus(int $variant): array
    {
        if ($variant < 1) {
            throw new \InvalidArgumentException('SQLite e_fkey schema variant must be positive');
        }

        return [
            'source' => 'e_fkey.test',
            'variant' => $variant,
            'scenarios' => [
                'e_fkey-18.1..18.9',
                'e_fkey-19.1..19.5',
                'e_fkey-20.1..20.8.6',
                'e_fkey-21.1..21.8',
                'e_fkey-22.OFF.1..22.ON.4',
                'e_fkey-23.1..23.7',
                'e_fkey-24.1..24.4.3',
            ],
            'parent_key_cases' => self::parentKeyCases(),
            'example_children' => self::exampleChildren(),
            'dml_error_cases' => self::dmlErrorCases(),
            'implicit_width_cases' => self::implicitWidthCases($variant),
            'child_definition_errors' => self::childDefinitionErrors(),
            'implicit_references' => self::implicitReferenceCases($variant),
            'child_index_cases' => self::childIndexCases($variant),
            'dependencies' => [
                'sqlite-efkey-parent-key-requires-primary-key-or-unique-index',
                'sqlite-efkey-parent-unique-index-must-use-parent-collation',
                'sqlite-efkey-cross-table-schema-errors-surface-at-dml-prepare',
                'sqlite-efkey-child-definition-errors-surface-at-create-table',
                'sqlite-efkey-implicit-references-map-to-parent-primary-key',
                'sqlite-efkey-child-key-index-is-optional-and-need-not-be-unique',
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function parentKeyCases(): array
    {
        $cases = [
            ['case' => 'e_fkey-18.1', 'label' => 'child declared before parent table', 'parent' => null, 'child' => ['name' => 't2', 'referenced' => 't1', 'parent_columns' => ['x']], 'reason' => 'parent-table-lookup-deferred-until-dml'],
            ['case' => 'e_fkey-18.2', 'label' => 'parent primary key covers reference', 'parent' => ['name' => 't1', 'columns' => ['x'], 'primary_key' => ['x']], 'child' => ['name' => 't2', 'referenced' => 't1', 'parent_columns' => ['x']], 'reason' => 'parent-primary-key'],
            ['case' => 'e_fkey-18.3', 'label' => 'parent unique constraint covers reference', 'parent' => ['name' => 't1', 'columns' => ['x'], 'unique' => [['columns' => ['x']]]], 'child' => ['name' => 't2', 'referenced' => 't1', 'parent_columns' => ['x']], 'reason' => 'parent-unique-constraint'],
            ['case' => 'e_fkey-18.4', 'label' => 'parent unique index covers reference', 'parent' => ['name' => 't1', 'columns' => ['x'], 'unique' => [['columns' => ['x'], 'index' => true]]], 'child' => ['name' => 't2', 'referenced' => 't1', 'parent_columns' => ['x']], 'reason' => 'parent-unique-index'],
            ['case' => 'e_fkey-18.5', 'label' => 'unique index collation differs from parent column', 'parent' => ['name' => 't1', 'columns' => ['x'], 'collation' => ['x' => 'binary'], 'unique' => [['columns' => ['x'], 'collation' => ['x' => 'nocase']]]], 'child' => ['name' => 't2', 'referenced' => 't1', 'parent_columns' => ['x']], 'reason' => 'unique-index-collation-mismatch'],
            ['case' => 'e_fkey-18.6', 'label' => 'no primary or unique key covers reference', 'parent' => ['name' => 't1', 'columns' => ['x']], 'child' => ['name' => 't2', 'referenced' => 't1', 'parent_columns' => ['x']], 'reason' => 'missing-unique-parent-key'],
            ['case' => 'e_fkey-18.7', 'label' => 'composite primary key not fully referenced', 'parent' => ['name' => 't1', 'columns' => ['x', 'y'], 'primary_key' => ['x', 'y']], 'child' => ['name' => 't2', 'referenced' => 't1', 'parent_columns' => ['x']], 'reason' => 'parent-key-not-exact-unique-index'],
            ['case' => 'e_fkey-18.8', 'label' => 'composite unique constraint not fully referenced', 'parent' => ['name' => 't1', 'columns' => ['x', 'y'], 'unique' => [['columns' => ['x', 'y']]]], 'child' => ['name' => 't2', 'referenced' => 't1', 'parent_columns' => ['x']], 'reason' => 'parent-key-not-exact-unique-index'],
            ['case' => 'e_fkey-18.9', 'label' => 'composite unique index not fully referenced', 'parent' => ['name' => 't1', 'columns' => ['x', 'y'], 'unique' => [['columns' => ['x', 'y'], 'index' => true]]], 'child' => ['name' => 't2', 'referenced' => 't1', 'parent_columns' => ['x']], 'reason' => 'parent-key-not-exact-unique-index'],
        ];

        return array_map(static fn (array $case): array => self::diagnoseParentKey($case), $cases);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function exampleChildren(): array
    {
        $parent = [
            'name' => 'parent',
            'columns' => ['a', 'b', 'c', 'd', 'e', 'f'],
            'primary_key' => ['a'],
            'unique' => [
                ['columns' => ['b']],
                ['columns' => ['c', 'd'], 'index' => true],
                ['columns' => ['f'], 'collation' => ['f' => 'nocase'], 'index' => true],
            ],
            'index' => [['columns' => ['e']]],
        ];

        $children = [
            ['case' => 'e_fkey-19.2 child1', 'name' => 'child1', 'referenced' => 'parent', 'parent_columns' => ['a'], 'reason' => 'parent-primary-key'],
            ['case' => 'e_fkey-19.2 child2', 'name' => 'child2', 'referenced' => 'parent', 'parent_columns' => ['b'], 'reason' => 'parent-unique-constraint'],
            ['case' => 'e_fkey-19.2 child3', 'name' => 'child3', 'referenced' => 'parent', 'parent_columns' => ['c', 'd'], 'reason' => 'parent-unique-index'],
            ['case' => 'e_fkey-19.2 child4', 'name' => 'child4', 'referenced' => 'parent', 'parent_columns' => ['e'], 'reason' => 'missing-unique-parent-key'],
            ['case' => 'e_fkey-19.3 child5', 'name' => 'child5', 'referenced' => 'parent', 'parent_columns' => ['f'], 'reason' => 'unique-index-collation-mismatch'],
            ['case' => 'e_fkey-19.4 child6', 'name' => 'child6', 'referenced' => 'parent', 'parent_columns' => ['b', 'c'], 'reason' => 'parent-key-not-exact-unique-index'],
            ['case' => 'e_fkey-19.5 child7', 'name' => 'child7', 'referenced' => 'parent', 'parent_columns' => ['c'], 'reason' => 'parent-key-not-exact-unique-index'],
        ];

        return array_map(static function (array $child) use ($parent): array {
            return self::diagnoseParentKey(['case' => $child['case'], 'label' => $child['name'], 'parent' => $parent, 'child' => $child, 'reason' => $child['reason']]);
        }, $children);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function dmlErrorCases(): array
    {
        $cases = [
            ['case' => 'e_fkey-20.2', 'child' => 'c1', 'parent' => 'nosuchtable', 'error' => 'no such table: main.nosuchtable', 'reason' => 'missing-parent-table', 'parent_ops' => 0],
            ['case' => 'e_fkey-20.3', 'child' => 'c2', 'parent' => 'p2', 'error' => 'foreign key mismatch - "c2" referencing "p2"', 'reason' => 'missing-parent-key-column', 'parent_ops' => 3],
            ['case' => 'e_fkey-20.4', 'child' => 'c3', 'parent' => 'p3', 'error' => 'foreign key mismatch - "c3" referencing "p3"', 'reason' => 'referenced-parent-column-not-unique', 'parent_ops' => 3],
            ['case' => 'e_fkey-20.5', 'child' => 'c4', 'parent' => 'p4', 'error' => 'foreign key mismatch - "c4" referencing "p4"', 'reason' => 'unique-index-collation-mismatch', 'parent_ops' => 3],
            ['case' => 'e_fkey-20.6', 'child' => 'c5', 'parent' => 'p5', 'error' => 'foreign key mismatch - "c5" referencing "p5"', 'reason' => 'parent-column-collation-mismatch', 'parent_ops' => 3],
            ['case' => 'e_fkey-20.7', 'child' => 'c6', 'parent' => 'p6', 'error' => 'foreign key mismatch - "c6" referencing "p6"', 'reason' => 'implicit-parent-primary-key-width-mismatch', 'parent_ops' => 3],
            ['case' => 'e_fkey-20.8', 'child' => 'c7', 'parent' => 'p7', 'error' => 'foreign key mismatch - "c7" referencing "p7"', 'reason' => 'implicit-parent-primary-key-width-mismatch', 'parent_ops' => 3],
        ];

        return array_map(static function (array $case): array {
            $childOps = ['insert-child', 'update-child', 'insert-select-child'];
            $parentOps = $case['parent_ops'] === 0 ? [] : ['delete-parent', 'update-parent', 'insert-select-parent'];

            return $case + [
                'source' => 'e_fkey.test e_fkey-20',
                'status' => str_starts_with((string) $case['error'], 'no such table') ? 'no-such-parent-table' : 'foreign-key-mismatch',
                'child_operations' => $childOps,
                'parent_operations' => $parentOps,
                'operation_count' => count($childOps) + count($parentOps),
                'detected_at' => 'dml-prepare',
            ];
        }, $cases);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function implicitWidthCases(int $variant): array
    {
        $left = 'I' . $variant;
        $right = 'II' . $variant;

        return [
            [
                'case' => 'e_fkey-21.2 child8',
                'child' => 'child8',
                'status' => 'commit-ok',
                'implicit_parent_columns' => ['a', 'b'],
                'child_columns' => ['x', 'y'],
                'insert_values' => [$left, $right],
                'null_child_still_mismatch' => false,
                'error' => null,
            ],
            [
                'case' => 'e_fkey-21.3..21.5 child9',
                'child' => 'child9',
                'status' => 'foreign-key-mismatch',
                'implicit_parent_columns' => ['a', 'b'],
                'child_columns' => ['x'],
                'insert_values' => [$left],
                'null_child_still_mismatch' => true,
                'error' => 'foreign key mismatch - "child9" referencing "parent2"',
            ],
            [
                'case' => 'e_fkey-21.6..21.8 child10',
                'child' => 'child10',
                'status' => 'foreign-key-mismatch',
                'implicit_parent_columns' => ['a', 'b'],
                'child_columns' => ['x', 'y', 'z'],
                'insert_values' => [$left, $right, 'III' . $variant],
                'null_child_still_mismatch' => true,
                'error' => 'foreign key mismatch - "child10" referencing "parent2"',
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function childDefinitionErrors(): array
    {
        $definitions = [
            ['definition' => 'CREATE TABLE child1(a, b, FOREIGN KEY(a, b) REFERENCES p(c))', 'error' => 'number of columns in foreign key does not match the number of columns in the referenced table'],
            ['definition' => 'CREATE TABLE child2(a, b, FOREIGN KEY(a, b) REFERENCES p(c, d, e))', 'error' => 'number of columns in foreign key does not match the number of columns in the referenced table'],
            ['definition' => 'CREATE TABLE child2(a, b, FOREIGN KEY(a, c) REFERENCES p(c, d))', 'error' => 'unknown column "c" in foreign key definition'],
            ['definition' => 'CREATE TABLE child2(a, b, FOREIGN KEY(c, b) REFERENCES p(c, d))', 'error' => 'unknown column "c" in foreign key definition'],
        ];

        $cases = [];
        foreach ([false, true] as $foreignKeys) {
            foreach ($definitions as $index => $definition) {
                $cases[] = $definition + [
                    'case' => 'e_fkey-22.' . ($foreignKeys ? 'ON' : 'OFF') . '.' . ($index + 1),
                    'source' => 'e_fkey.test e_fkey-22',
                    'foreign_keys_enabled' => $foreignKeys,
                    'status' => 'definition-error',
                    'detected_at' => 'create-table',
                ];
            }
        }

        return $cases;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function implicitReferenceCases(int $variant): array
    {
        $a = 239 + $variant;
        $b = 231 + $variant;

        return [
            [
                'case' => 'e_fkey-23.2..23.4',
                'child' => 'c1',
                'parent' => 'p1',
                'implicit_parent_columns' => ['a', 'b'],
                'child_values' => [$a, $b],
                'status_before_parent' => 'constraint-failed',
                'status_after_parent' => 'commit-ok',
                'error_before_parent' => 'FOREIGN KEY constraint failed',
            ],
            [
                'case' => 'e_fkey-23.5..23.7',
                'child' => 'c2',
                'parent' => 'p2',
                'implicit_parent_columns' => ['b'],
                'child_values' => [$a, $b],
                'status_before_parent' => 'constraint-failed',
                'status_after_parent' => 'commit-ok',
                'error_before_parent' => 'FOREIGN KEY constraint failed',
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function childIndexCases(int $variant): array
    {
        $value = [1 + $variant, 2 + $variant];
        $cases = [];
        foreach ([['c1', 'none'], ['c2', 'index'], ['c3', 'unique-index']] as $entry) {
            [$child, $indexType] = $entry;
            $cases[] = [
                'case' => 'e_fkey-24.' . (count($cases) + 2) . '.1..' . (count($cases) + 2) . '.3',
                'child' => $child,
                'child_index_type' => $indexType,
                'parent_unique_columns' => ['y', 'x'],
                'foreign_key_parent_columns' => ['x', 'y'],
                'child_values' => $value,
                'child_index_required' => false,
                'status_before_parent' => 'constraint-failed',
                'status_after_parent' => 'commit-ok',
                'error_before_parent' => 'FOREIGN KEY constraint failed',
            ];
        }

        return $cases;
    }

    /**
     * @param array<string,mixed> $case
     * @return array<string,mixed>
     */
    private static function diagnoseParentKey(array $case): array
    {
        $child = $case['child'];
        $parent = $case['parent'];
        $status = 'definition-deferred';
        $matchedBy = null;
        $valid = false;
        $error = null;

        if (is_array($parent)) {
            [$valid, $matchedBy] = self::parentKeyCovered($parent, $child['parent_columns']);
            $status = $valid ? 'dml-ok' : 'foreign-key-mismatch';
            $error = $valid ? null : 'foreign key mismatch - "' . $child['name'] . '" referencing "' . $child['referenced'] . '"';
        }

        return [
            'case' => $case['case'],
            'source' => 'e_fkey.test ' . $case['case'],
            'label' => $case['label'],
            'status' => $status,
            'error' => $error,
            'child_table' => $child['name'],
            'parent_table' => $child['referenced'],
            'parent_columns' => $child['parent_columns'],
            'parent_key_valid' => $valid,
            'matched_by' => $matchedBy,
            'reason' => $case['reason'],
            'detected_at' => $parent === null ? 'create-child-table' : 'dml-prepare',
        ];
    }

    /**
     * @param array<string,mixed> $parent
     * @param list<string> $parentColumns
     * @return array{0:bool,1:?string}
     */
    private static function parentKeyCovered(array $parent, array $parentColumns): array
    {
        if (self::sameColumnSet($parent['primary_key'] ?? [], $parentColumns)) {
            return [true, 'primary-key'];
        }

        foreach (($parent['unique'] ?? []) as $unique) {
            if (!self::sameColumnSet($unique['columns'] ?? [], $parentColumns)) {
                continue;
            }
            if (!self::collationsMatch($parent['collation'] ?? [], $unique['collation'] ?? [], $parentColumns)) {
                return [false, null];
            }

            return [true, ($unique['index'] ?? false) ? 'unique-index' : 'unique-constraint'];
        }

        return [false, null];
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private static function sameColumnSet(array $left, array $right): bool
    {
        sort($left);
        sort($right);

        return $left === $right;
    }

    /**
     * @param array<string,string> $parentCollations
     * @param array<string,string> $indexCollations
     * @param list<string> $columns
     */
    private static function collationsMatch(array $parentCollations, array $indexCollations, array $columns): bool
    {
        foreach ($columns as $column) {
            $parent = strtolower($parentCollations[$column] ?? 'binary');
            $index = strtolower($indexCollations[$column] ?? $parent);
            if ($parent !== $index) {
                return false;
            }
        }

        return true;
    }
}
