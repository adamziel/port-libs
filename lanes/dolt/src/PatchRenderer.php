<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class PatchRenderer
{
    public const DIFF_SCHEMA = 'schema';
    public const DIFF_DATA = 'data';
    public const PRIMARY_KEY_CHANGE_WARNING_CODE = 1235;
    public const PRIMARY_KEY_CHANGE_WARNING = "Primary key sets differ between revisions for table '%s', skipping data diff";

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
     * @param list<array{code:int, message:string}> $warnings
     * @return list<array{statement_order:int, from_commit_hash:string, to_commit_hash:string, table_name:string, diff_type:string, statement:string}>
     */
    public function rows(array $tables, array $options = [], array &$warnings = []): array
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
                foreach ($this->dataStatements($table, $displayTableName, $fromSchema, $toSchema, $fromCommit, $toCommit, $warnings) as $statement) {
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
            if ($this->columnDefinitionChanged($fromColumn, $toColumn)) {
                $statements[] = 'ALTER TABLE ' . $this->quoteIdentifier($toTableName)
                    . ' MODIFY COLUMN ' . $this->columnDefinition($toColumn) . ';';
            }
        }

        if ($this->primaryKeySignature($fromSchema) !== $this->primaryKeySignature($toSchema)) {
            $statements[] = 'ALTER TABLE ' . $this->quoteIdentifier($toTableName) . ' DROP PRIMARY KEY;';
            $toPrimaryKeys = $this->primaryKeyNames($toSchema);
            if ($toPrimaryKeys !== []) {
                $statements[] = 'ALTER TABLE ' . $this->quoteIdentifier($toTableName)
                    . ' ADD PRIMARY KEY (' . implode(',', $toPrimaryKeys) . ');';
            }
        }

        foreach ($this->diffIndexes($fromSchema, $toSchema) as $diff) {
            if ($diff['diff_type'] === TableSchema::DIFF_ADDED) {
                $statements[] = $this->alterTableAddIndexStatement($toTableName, $diff['to']);
                continue;
            }
            if ($diff['diff_type'] === TableSchema::DIFF_REMOVED) {
                $statements[] = $this->alterTableDropIndexStatement($fromTableName, $diff['from']);
                continue;
            }
            if ($diff['diff_type'] === TableSchema::DIFF_MODIFIED) {
                $statements[] = $this->alterTableDropIndexStatement($fromTableName, $diff['from']);
                $statements[] = $this->alterTableAddIndexStatement($toTableName, $diff['to']);
            }
        }

        foreach ($this->diffForeignKeys($fromSchema, $toSchema) as $diff) {
            if ($diff['diff_type'] === TableSchema::DIFF_ADDED) {
                $statements[] = $this->alterTableAddForeignKeyStatement($toTableName, $diff['to']);
                continue;
            }
            if ($diff['diff_type'] === TableSchema::DIFF_REMOVED) {
                $statements[] = $this->alterTableDropForeignKeyStatement($fromTableName, $diff['from']);
                continue;
            }
            if ($diff['diff_type'] === TableSchema::DIFF_MODIFIED) {
                $statements[] = $this->alterTableDropForeignKeyStatement($fromTableName, $diff['from']);
                $statements[] = $this->alterTableAddForeignKeyStatement($toTableName, $diff['to']);
            }
        }

        foreach ($this->checkConstraintStatements($fromSchema, $toSchema) as $statement) {
            $statements[] = $statement;
        }

        if ($fromSchema->collation() !== $toSchema->collation()) {
            $statements[] = 'ALTER TABLE ' . $this->quoteIdentifier($toTableName)
                . ' COLLATE=' . $this->quoteSqlString($toSchema->collation()) . ';';
        }

        if ($fromSchema->targetRowSize() !== $toSchema->targetRowSize()) {
            $statements[] = 'ALTER TABLE ' . $this->quoteIdentifier($toTableName)
                . ' TARGET_ROW_SIZE=' . $toSchema->targetRowSize() . ';';
        }

        return $statements;
    }

    /**
     * @param array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool} $column
     */
    private function columnDefinition(array $column): string
    {
        $parts = [$this->quoteIdentifier($column['name']), $column['type']];
        if ($column['primaryKey'] || in_array('not_null', $column['constraints'], true)) {
            $parts[] = 'NOT NULL';
        }
        if ($column['autoIncrement']) {
            $parts[] = 'AUTO_INCREMENT';
        }
        foreach ($column['constraints'] as $constraint) {
            if ($constraint === 'not_null') {
                continue;
            }
            $parts[] = $constraint;
        }
        if ($column['default'] !== null) {
            $parts[] = 'DEFAULT ' . $this->columnDefault($column['default']);
        }
        if ($column['generated'] !== null) {
            $parts[] = 'GENERATED ALWAYS AS (' . $column['generated'] . ')';
            if ($column['generatedStored']) {
                $parts[] = 'STORED';
            }
        }
        if ($column['onUpdate'] !== null) {
            $parts[] = 'ON UPDATE ' . $column['onUpdate'];
        }

        return implode(' ', $parts);
    }

    /**
     * @param array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool} $fromColumn
     * @param array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool} $toColumn
     */
    private function columnDefinitionChanged(array $fromColumn, array $toColumn): bool
    {
        return $fromColumn['type'] !== $toColumn['type'];
    }

    private function columnDefault(string $default): string
    {
        $first = $default[0];
        $last = $default[strlen($default) - 1];
        if (
            $first !== '('
            && $last !== ')'
            && $first !== "'"
            && $last !== "'"
            && $default !== 'NULL'
        ) {
            return $this->quoteSqlString($default);
        }

        return $default;
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

        foreach ($schema->indexes() as $index) {
            $lines[] = '  ' . $this->createTableIndexDefinition($index);
        }

        foreach ($schema->foreignKeys() as $foreignKey) {
            $lines[] = '  ' . $this->createTableForeignKeyDefinition($foreignKey);
        }

        foreach ($schema->checks() as $check) {
            $lines[] = '  ' . $this->createTableCheckDefinition($check);
        }

        return 'CREATE TABLE ' . $this->quoteIdentifier($tableName) . " (\n"
            . implode(",\n", $lines)
            . "\n) ENGINE=InnoDB DEFAULT CHARSET={$schema->characterSet()} COLLATE={$schema->collation()};";
    }

    /**
     * @return list<non-empty-string>
     */
    private function primaryKeyNames(TableSchema $schema): array
    {
        return array_map(static fn (array $column): string => $column['name'], $schema->primaryKeyColumns());
    }

    /**
     * Dolt's non-create/drop schema patch path reprints primary-key DDL when
     * the primary-key column collection changes, including type/tag changes.
     *
     * @return list<array{name:non-empty-string, tag:int, type:non-empty-string}>
     */
    private function primaryKeySignature(TableSchema $schema): array
    {
        return array_map(
            static fn (array $column): array => [
                'name' => $column['name'],
                'tag' => $column['tag'],
                'type' => $column['type'],
            ],
            $schema->primaryKeyColumns()
        );
    }

    /**
     * @return list<array{diff_type:string, from:array{name:non-empty-string, columns:list<non-empty-string>, unique:bool}|null, to:array{name:non-empty-string, columns:list<non-empty-string>, unique:bool}|null}>
     */
    private function diffIndexes(TableSchema $fromSchema, TableSchema $toSchema): array
    {
        return $this->diffNamedObjects($fromSchema->indexes(), $toSchema->indexes());
    }

    /**
     * @return list<array{diff_type:string, from:array{name:non-empty-string, columns:list<non-empty-string>, referencedTable:non-empty-string, referencedColumns:list<non-empty-string>, onDelete:string|null, onUpdate:string|null}|null, to:array{name:non-empty-string, columns:list<non-empty-string>, referencedTable:non-empty-string, referencedColumns:list<non-empty-string>, onDelete:string|null, onUpdate:string|null}|null}>
     */
    private function diffForeignKeys(TableSchema $fromSchema, TableSchema $toSchema): array
    {
        return $this->diffNamedObjects($fromSchema->foreignKeys(), $toSchema->foreignKeys());
    }

    /**
     * Upstream `dolt_patch()` currently renders check constraints only in
     * CREATE TABLE statements; existing-table add/drop/modify check changes
     * are classified as schema metadata deltas but emit no ALTER rows.
     *
     * @return list<string>
     */
    private function checkConstraintStatements(TableSchema $fromSchema, TableSchema $toSchema): array
    {
        TableSchema::diffChecks($fromSchema, $toSchema);

        return [];
    }

    /**
     * @template T of array{name:non-empty-string}
     * @param list<T> $from
     * @param list<T> $to
     * @return list<array{diff_type:string, from:T|null, to:T|null}>
     */
    private function diffNamedObjects(array $from, array $to): array
    {
        $fromByName = $this->namedObjectsByLowerName($from);
        $toByName = $this->namedObjectsByLowerName($to);
        $names = array_keys($fromByName + $toByName);
        sort($names, SORT_STRING);

        $diffs = [];
        foreach ($names as $name) {
            $fromObject = $fromByName[$name] ?? null;
            $toObject = $toByName[$name] ?? null;
            if ($fromObject === null) {
                $diffType = TableSchema::DIFF_ADDED;
            } elseif ($toObject === null) {
                $diffType = TableSchema::DIFF_REMOVED;
            } elseif ($fromObject !== $toObject) {
                $diffType = TableSchema::DIFF_MODIFIED;
            } else {
                $diffType = TableSchema::DIFF_NONE;
            }
            if ($diffType === TableSchema::DIFF_NONE) {
                continue;
            }

            $diffs[] = [
                'diff_type' => $diffType,
                'from' => $fromObject,
                'to' => $toObject,
            ];
        }

        return $diffs;
    }

    /**
     * @template T of array{name:non-empty-string}
     * @param list<T> $objects
     * @return array<string, T>
     */
    private function namedObjectsByLowerName(array $objects): array
    {
        $byName = [];
        foreach ($objects as $object) {
            $byName[strtolower($object['name'])] = $object;
        }

        return $byName;
    }

    /**
     * @param array{name:non-empty-string, columns:list<non-empty-string>, unique:bool} $index
     */
    private function createTableIndexDefinition(array $index): string
    {
        return ($index['unique'] ? 'UNIQUE KEY ' : 'KEY ')
            . $this->quoteIdentifier($index['name'])
            . ' (' . implode(',', array_map([$this, 'quoteIdentifier'], $index['columns'])) . ')';
    }

    /**
     * @param array{name:non-empty-string, columns:list<non-empty-string>, unique:bool} $index
     */
    private function alterTableAddIndexStatement(string $tableName, array $index): string
    {
        return 'ALTER TABLE ' . $this->quoteIdentifier($tableName)
            . ($index['unique'] ? ' ADD UNIQUE INDEX ' : ' ADD INDEX ')
            . $this->quoteIdentifier($index['name'])
            . '(' . implode(',', array_map([$this, 'quoteIdentifier'], $index['columns'])) . ');';
    }

    /**
     * @param array{name:non-empty-string, columns:list<non-empty-string>, unique:bool} $index
     */
    private function alterTableDropIndexStatement(string $tableName, array $index): string
    {
        return 'ALTER TABLE ' . $this->quoteIdentifier($tableName)
            . ' DROP INDEX ' . $this->quoteIdentifier($index['name']) . ';';
    }

    /**
     * @param array{name:non-empty-string, columns:list<non-empty-string>, referencedTable:non-empty-string, referencedColumns:list<non-empty-string>, onDelete:string|null, onUpdate:string|null} $foreignKey
     */
    private function createTableForeignKeyDefinition(array $foreignKey): string
    {
        return $this->foreignKeyDefinition($foreignKey, true);
    }

    /**
     * @param array{name:non-empty-string, columns:list<non-empty-string>, referencedTable:non-empty-string, referencedColumns:list<non-empty-string>, onDelete:string|null, onUpdate:string|null} $foreignKey
     */
    private function alterTableAddForeignKeyStatement(string $tableName, array $foreignKey): string
    {
        return 'ALTER TABLE ' . $this->quoteIdentifier($tableName)
            . ' ADD ' . $this->foreignKeyDefinition($foreignKey, false) . ';';
    }

    /**
     * @param array{name:non-empty-string, columns:list<non-empty-string>, referencedTable:non-empty-string, referencedColumns:list<non-empty-string>, onDelete:string|null, onUpdate:string|null} $foreignKey
     */
    private function alterTableDropForeignKeyStatement(string $tableName, array $foreignKey): string
    {
        return 'ALTER TABLE ' . $this->quoteIdentifier($tableName)
            . ' DROP FOREIGN KEY ' . $this->quoteIdentifier($foreignKey['name']) . ';';
    }

    /**
     * Dolt's CREATE TABLE formatter preserves referential actions, while the
     * patch ALTER ADD path currently emits only child/parent columns.
     *
     * @param array{name:non-empty-string, columns:list<non-empty-string>, referencedTable:non-empty-string, referencedColumns:list<non-empty-string>, onDelete:string|null, onUpdate:string|null} $foreignKey
     */
    private function foreignKeyDefinition(array $foreignKey, bool $includeActions): string
    {
        return 'CONSTRAINT ' . $this->quoteIdentifier($foreignKey['name'])
            . ' FOREIGN KEY (' . implode(',', array_map([$this, 'quoteIdentifier'], $foreignKey['columns'])) . ')'
            . ' REFERENCES ' . $this->quoteIdentifier($foreignKey['referencedTable'])
            . ' (' . implode(',', array_map([$this, 'quoteIdentifier'], $foreignKey['referencedColumns'])) . ')'
            . ($includeActions ? $this->foreignKeyActions($foreignKey) : '');
    }

    /**
     * @param array{onDelete:string|null, onUpdate:string|null} $foreignKey
     */
    private function foreignKeyActions(array $foreignKey): string
    {
        $actions = [];
        if ($foreignKey['onDelete'] !== null) {
            $actions[] = 'ON DELETE ' . $foreignKey['onDelete'];
        }
        if ($foreignKey['onUpdate'] !== null) {
            $actions[] = 'ON UPDATE ' . $foreignKey['onUpdate'];
        }

        return $actions === [] ? '' : ' ' . implode(' ', $actions);
    }

    /**
     * @param array{name:non-empty-string, expression:non-empty-string, enforced:bool} $check
     */
    private function createTableCheckDefinition(array $check): string
    {
        return 'CONSTRAINT ' . $this->quoteIdentifier($check['name'])
            . ' CHECK (' . $check['expression'] . ')'
            . ($check['enforced'] ? '' : ' /*!80016 NOT ENFORCED */');
    }

    /**
     * @param array<string, mixed> $table
     * @return list<string>
     */
    private function dataStatements(
        array $table,
        string $tableName,
        ?TableSchema $fromSchema,
        TableSchema $schema,
        string $fromCommit,
        string $toCommit,
        array &$warnings,
    ): array {
        if ($fromSchema !== null && !TableSchema::primaryKeySetsDiffable($fromSchema, $schema)) {
            $warnings[] = [
                'code' => self::PRIMARY_KEY_CHANGE_WARNING_CODE,
                'message' => sprintf(self::PRIMARY_KEY_CHANGE_WARNING, $tableName),
            ];

            return [];
        }

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

    private function quoteSqlString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
