<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class PatchRenderer
{
    public const DIFF_SCHEMA = 'schema';
    public const DIFF_DATA = 'data';

    /**
     * Render native rows matching upstream `dolt_patch()` output:
     * statement_order, from_commit_hash, to_commit_hash, table_name, diff_type,
     * and statement. Schema statements are emitted before data statements for
     * each table, and filtered schema/data partitions restart statement_order.
     *
     * @param list<array{
     *   tableName?:string,
     *   fromTableName?:string|null,
     *   toTableName?:string|null,
     *   fromSchema?:TableSchema|null,
     *   toSchema?:TableSchema|null,
     *   fromRows?:list<array<string, scalar|null>>,
     *   toRows?:list<array<string, scalar|null>>,
     *   diffRows?:list<array<string, scalar|null>>,
     *   primaryKey?:non-empty-string|list<non-empty-string>|null,
     *   columns?:list<non-empty-string>|null,
     *   keyless?:bool
     * }> $tables
     * @param array{fromCommit?:string, toCommit?:string, filter?:string|null} $options
     * @return list<array{statement_order:int, from_commit_hash:string, to_commit_hash:string, table_name:string, diff_type:string, statement:string}>
     */
    public function rows(array $tables, array $options = []): array
    {
        $fromCommit = $this->commitName($options['fromCommit'] ?? 'FROM', 'fromCommit');
        $toCommit = $this->commitName($options['toCommit'] ?? 'TO', 'toCommit');
        $filter = $this->normalizeFilter($options['filter'] ?? null);
        $includeSchema = $filter === null || $filter === self::DIFF_SCHEMA;
        $includeData = $filter === null || $filter === self::DIFF_DATA;

        $orderedTables = $this->sortTables($tables);
        $rows = [];
        $statementOrder = 1;
        foreach ($orderedTables as $table) {
            [$fromTableName, $toTableName, $displayTableName] = $this->tableNames($table);
            $fromSchema = $this->optionalSchema($table['fromSchema'] ?? null, 'fromSchema');
            $toSchema = $this->optionalSchema($table['toSchema'] ?? null, 'toSchema');

            if ($fromSchema === null && $toSchema === null) {
                throw new \InvalidArgumentException("Patch table {$displayTableName} must include fromSchema or toSchema.");
            }

            if ($includeSchema) {
                foreach ($this->schemaStatements($fromTableName, $toTableName, $fromSchema, $toSchema) as $statement) {
                    $rows[] = $this->row($statementOrder++, $fromCommit, $toCommit, $displayTableName, self::DIFF_SCHEMA, $statement);
                }
            }

            if ($includeData && $toSchema !== null) {
                foreach ($this->dataStatements($table, $displayTableName, $toSchema, $fromCommit, $toCommit) as $statement) {
                    $rows[] = $this->row($statementOrder++, $fromCommit, $toCommit, $displayTableName, self::DIFF_DATA, $statement);
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $table
     * @return array{0:string, 1:string, 2:string}
     */
    private function tableNames(array $table): array
    {
        $tableName = $this->optionalName($table['tableName'] ?? null, 'tableName');
        $fromTableName = $this->optionalName($table['fromTableName'] ?? null, 'fromTableName');
        $toTableName = $this->optionalName($table['toTableName'] ?? null, 'toTableName');

        if ($fromTableName === '' && $toTableName === '') {
            if ($tableName === '') {
                throw new \InvalidArgumentException('Patch tables must include tableName, fromTableName, or toTableName.');
            }
            $fromTableName = $tableName;
            $toTableName = $tableName;
        } elseif ($fromTableName === '') {
            $fromTableName = $tableName !== '' ? $tableName : $toTableName;
        } elseif ($toTableName === '') {
            $toTableName = $tableName !== '' ? $tableName : $fromTableName;
        }

        $displayTableName = $toTableName !== '' ? $toTableName : $fromTableName;

        return [$fromTableName, $toTableName, $displayTableName];
    }

    private function normalizeFilter(mixed $filter): ?string
    {
        if ($filter === null || $filter === '' || $filter === 'all') {
            return null;
        }
        if (!is_string($filter)) {
            throw new \InvalidArgumentException('Patch diff_type filter must be a string.');
        }
        if ($filter === self::DIFF_SCHEMA || $filter === self::DIFF_DATA) {
            return $filter;
        }

        throw new \InvalidArgumentException('Patch diff_type filter must be schema, data, or all.');
    }

    private function commitName(mixed $commit, string $field): string
    {
        if (!is_string($commit) || $commit === '') {
            throw new \InvalidArgumentException("Patch {$field} must be a non-empty string.");
        }

        return $commit;
    }

    private function optionalName(mixed $name, string $field): string
    {
        if ($name === null || $name === '') {
            return '';
        }
        if (!is_string($name)) {
            throw new \InvalidArgumentException("Patch {$field} must be a string or null.");
        }

        return $name;
    }

    private function optionalSchema(mixed $schema, string $field): ?TableSchema
    {
        if ($schema === null) {
            return null;
        }
        if (!$schema instanceof TableSchema) {
            throw new \InvalidArgumentException("Patch {$field} must be a TableSchema or null.");
        }

        return $schema;
    }

    /**
     * @param list<array<string, mixed>> $tables
     * @return list<array<string, mixed>>
     */
    private function sortTables(array $tables): array
    {
        foreach ($tables as $table) {
            if (!is_array($table)) {
                throw new \InvalidArgumentException('Patch tables must be arrays.');
            }
        }

        usort($tables, function (array $a, array $b): int {
            [, $aTo, $aDisplay] = $this->tableNames($a);
            [, $bTo, $bDisplay] = $this->tableNames($b);

            return [$aTo, $aDisplay] <=> [$bTo, $bDisplay];
        });

        return $tables;
    }

    /**
     * @return list<string>
     */
    private function schemaStatements(string $fromTableName, string $toTableName, ?TableSchema $fromSchema, ?TableSchema $toSchema): array
    {
        if ($fromSchema === null) {
            if ($toSchema === null) {
                return [];
            }

            return [$this->createTableStatement($toTableName, $toSchema)];
        }

        if ($toSchema === null) {
            return ['DROP TABLE ' . $this->quoteIdentifier($fromTableName) . ';'];
        }

        $statements = [];
        if ($fromTableName !== $toTableName) {
            $statements[] = 'RENAME TABLE ' . $this->quoteIdentifier($fromTableName)
                . ' TO ' . $this->quoteIdentifier($toTableName) . ';';
        }

        foreach (TableSchema::diffColumns($fromSchema, $toSchema) as $diff) {
            if ($diff['diff_type'] === TableSchema::DIFF_ADDED) {
                $statements[] = 'ALTER TABLE ' . $this->quoteIdentifier($toTableName)
                    . ' ADD ' . $this->columnDefinition($diff['to']) . ';';
                continue;
            }
            if ($diff['diff_type'] === TableSchema::DIFF_REMOVED) {
                $statements[] = 'ALTER TABLE ' . $this->quoteIdentifier($toTableName)
                    . ' DROP ' . $this->quoteIdentifier($diff['from']['name']) . ';';
                continue;
            }
            if ($diff['diff_type'] !== TableSchema::DIFF_MODIFIED) {
                continue;
            }

            $fromColumn = $diff['from'];
            $toColumn = $diff['to'];
            if ($fromColumn['primaryKey'] !== $toColumn['primaryKey']) {
                continue;
            }
            if ($fromColumn['name'] !== $toColumn['name']) {
                $statements[] = 'ALTER TABLE ' . $this->quoteIdentifier($toTableName)
                    . ' RENAME COLUMN ' . $this->quoteIdentifier($fromColumn['name'])
                    . ' TO ' . $this->quoteIdentifier($toColumn['name']) . ';';
            }
            if ($fromColumn['type'] !== $toColumn['type'] || $fromColumn['constraints'] !== $toColumn['constraints']) {
                $statements[] = 'ALTER TABLE ' . $this->quoteIdentifier($toTableName)
                    . ' MODIFY COLUMN ' . $this->columnDefinition($toColumn) . ';';
            }
        }

        if ($this->primaryKeyNames($fromSchema) !== $this->primaryKeyNames($toSchema)) {
            $statements[] = 'ALTER TABLE ' . $this->quoteIdentifier($toTableName) . ' DROP PRIMARY KEY;';
            $toPrimaryKeys = $this->primaryKeyNames($toSchema);
            if ($toPrimaryKeys !== []) {
                $statements[] = 'ALTER TABLE ' . $this->quoteIdentifier($toTableName)
                    . ' ADD PRIMARY KEY (' . implode(',', array_map([$this, 'quoteIdentifier'], $toPrimaryKeys)) . ');';
            }
        }

        return $statements;
    }

    /**
     * @param array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>} $column
     */
    private function columnDefinition(array $column): string
    {
        $parts = [$this->quoteIdentifier($column['name']), $column['type']];
        if ($column['primaryKey'] || in_array('not_null', $column['constraints'], true)) {
            $parts[] = 'NOT NULL';
        }
        foreach ($column['constraints'] as $constraint) {
            if ($constraint === 'not_null') {
                continue;
            }
            $parts[] = $constraint;
        }

        return implode(' ', $parts);
    }

    private function createTableStatement(string $tableName, TableSchema $schema): string
    {
        $lines = [];
        foreach ($schema->columns() as $column) {
            $lines[] = '  ' . $this->columnDefinition($column);
        }

        $primaryKeys = $this->primaryKeyNames($schema);
        if ($primaryKeys !== []) {
            $lines[] = '  PRIMARY KEY (' . implode(',', array_map([$this, 'quoteIdentifier'], $primaryKeys)) . ')';
        }

        return 'CREATE TABLE ' . $this->quoteIdentifier($tableName) . " (\n"
            . implode(",\n", $lines)
            . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;";
    }

    /**
     * @return list<non-empty-string>
     */
    private function primaryKeyNames(TableSchema $schema): array
    {
        return array_map(static fn (array $column): string => $column['name'], $schema->primaryKeyColumns());
    }

    /**
     * @param array<string, mixed> $table
     * @return list<string>
     */
    private function dataStatements(array $table, string $tableName, TableSchema $schema, string $fromCommit, string $toCommit): array
    {
        if (isset($table['diffRows'])) {
            $diffRows = $table['diffRows'];
            if (!is_array($diffRows)) {
                throw new \InvalidArgumentException("Patch table {$tableName} diffRows must be a list.");
            }

            return (new DiffSqlRenderer())->statements($tableName, $schema, $diffRows);
        }

        $fromRows = $this->rowsList($table['fromRows'] ?? [], "{$tableName} fromRows");
        $toRows = $this->rowsList($table['toRows'] ?? [], "{$tableName} toRows");
        if ($fromRows === [] && $toRows === []) {
            return [];
        }

        $columns = $table['columns'] ?? null;
        if ($columns !== null && !is_array($columns)) {
            throw new \InvalidArgumentException("Patch table {$tableName} columns must be a list or null.");
        }

        $differ = new TableDiff();
        if (($table['keyless'] ?? false) || $schema->isKeyless()) {
            $diffRows = $differ->keylessDiffTableRows($fromRows, $toRows, $columns, $fromCommit, null, $toCommit, null);
        } else {
            $primaryKey = $table['primaryKey'] ?? null;
            if (!is_string($primaryKey) && !is_array($primaryKey)) {
                throw new \InvalidArgumentException("Patch table {$tableName} must include a primaryKey for data diffs.");
            }
            $diffRows = $differ->diffTableRows($fromRows, $toRows, $primaryKey, $columns, $fromCommit, null, $toCommit, null);
        }

        return (new DiffSqlRenderer())->statements($tableName, $schema, $diffRows);
    }

    /**
     * @return list<array<string, scalar|null>>
     */
    private function rowsList(mixed $rows, string $field): array
    {
        if (!is_array($rows)) {
            throw new \InvalidArgumentException("Patch {$field} must be a list of row arrays.");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("Patch {$field} must contain row arrays.");
            }
        }

        return array_values($rows);
    }

    /**
     * @return array{statement_order:int, from_commit_hash:string, to_commit_hash:string, table_name:string, diff_type:string, statement:string}
     */
    private function row(int $order, string $fromCommit, string $toCommit, string $tableName, string $diffType, string $statement): array
    {
        return [
            'statement_order' => $order,
            'from_commit_hash' => $fromCommit,
            'to_commit_hash' => $toCommit,
            'table_name' => $tableName,
            'diff_type' => $diffType,
            'statement' => $statement,
        ];
    }

    private function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '') {
            throw new \InvalidArgumentException('SQL identifiers must be non-empty strings.');
        }

        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
