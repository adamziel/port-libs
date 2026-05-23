<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class InformationSchema
{
    /**
     * @param array<string, TableSchema> $tables
     * @return list<array{constraint_catalog:string, constraint_schema:string, constraint_name:string, table_schema:string, table_name:string, check_clause:string, enforced:string}>
     */
    public function checkConstraints(array $tables, string $schemaName): array
    {
        $schemaName = $this->schemaName($schemaName);
        $rows = [];
        foreach ($this->tables($tables) as $tableName => $schema) {
            foreach ($schema->checks() as $check) {
                $rows[] = [
                    'constraint_catalog' => 'def',
                    'constraint_schema' => $schemaName,
                    'constraint_name' => $check['name'],
                    'table_schema' => $schemaName,
                    'table_name' => $tableName,
                    'check_clause' => $check['expression'],
                    'enforced' => $check['enforced'] ? 'YES' : 'NO',
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, TableSchema> $tables
     * @return list<array{constraint_catalog:string, constraint_schema:string, constraint_name:string, table_schema:string, table_name:string, constraint_type:string, enforced:string}>
     */
    public function tableConstraints(array $tables, string $schemaName): array
    {
        $schemaName = $this->schemaName($schemaName);
        $rows = [];
        foreach ($this->tables($tables) as $tableName => $schema) {
            if ($schema->primaryKeyColumns() !== []) {
                $rows[] = $this->tableConstraintRow($schemaName, $tableName, 'PRIMARY', 'PRIMARY KEY', 'YES');
            }
            foreach ($schema->foreignKeys() as $foreignKey) {
                $rows[] = $this->tableConstraintRow($schemaName, $tableName, $foreignKey['name'], 'FOREIGN KEY', 'YES');
            }
            foreach ($schema->checks() as $check) {
                $rows[] = $this->tableConstraintRow(
                    $schemaName,
                    $tableName,
                    $check['name'],
                    'CHECK',
                    $check['enforced'] ? 'YES' : 'NO'
                );
            }
        }

        return $rows;
    }

    /**
     * @return array{constraint_catalog:string, constraint_schema:string, constraint_name:string, table_schema:string, table_name:string, constraint_type:string, enforced:string}
     */
    private function tableConstraintRow(
        string $schemaName,
        string $tableName,
        string $constraintName,
        string $constraintType,
        string $enforced,
    ): array {
        return [
            'constraint_catalog' => 'def',
            'constraint_schema' => $schemaName,
            'constraint_name' => $constraintName,
            'table_schema' => $schemaName,
            'table_name' => $tableName,
            'constraint_type' => $constraintType,
            'enforced' => $enforced,
        ];
    }

    private function schemaName(string $schemaName): string
    {
        if ($schemaName === '') {
            throw new \InvalidArgumentException('Information schema name must be non-empty.');
        }

        return $schemaName;
    }

    /**
     * @param array<string, TableSchema> $tables
     * @return array<string, TableSchema>
     */
    private function tables(array $tables): array
    {
        foreach ($tables as $tableName => $schema) {
            if (!is_string($tableName) || $tableName === '') {
                throw new \InvalidArgumentException('Information schema table names must be non-empty strings.');
            }
            if (!$schema instanceof TableSchema) {
                throw new \InvalidArgumentException("Information schema table {$tableName} must be a TableSchema.");
            }
        }

        return $tables;
    }
}
