<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class PatchFunctionCall
{
    private PatchRenderer $renderer;

    public function __construct(?PatchRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new PatchRenderer();
    }

    /**
     * Apply Dolt's `dolt_patch()` table-function argument boundary before
     * rendering patch rows from native table-delta inputs.
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
     * @param list<mixed|PatchFunctionArgument> $arguments
     * @param array{
     *   filter?:string|null,
     *   knownTables?:list<string>,
     *   mergeBases?:array<string, string>,
     *   revisionGraph?:list<array<string, mixed>>,
     *   revisionSnapshots?:array<string, list<array<string, mixed>>>,
     *   headHash?:string,
     *   databaseName?:string,
     *   databaseTables?:list<string>,
     *   selectPrivileges?:list<string>
     * } $options
     * @param list<array{code:int, message:string}> $warnings
     * @return list<array{statement_order:int, from_commit_hash:string, to_commit_hash:string, table_name:string, diff_type:string, statement:string}>
     */
    public function rows(array $tables, array $arguments, array $options = [], array &$warnings = []): array
    {
        [$fromSpec, $toSpec, $tableName] = $this->parseArguments($arguments, $options);
        [$fromCommit, $toCommit] = $this->resolvePatchRevisions($fromSpec, $toSpec, $options);

        $snapshotKnownTables = null;
        if (array_key_exists('revisionSnapshots', $options)) {
            $tables = $this->tableDeltasFromRevisionSnapshots(
                $options['revisionSnapshots'],
                $fromCommit,
                $toCommit,
                $fromSpec,
                $toSpec,
                $snapshotKnownTables
            );
        }

        $effectiveOptions = $options;
        $knownTables = $this->mergeKnownTables($options['knownTables'] ?? null, $snapshotKnownTables);
        if ($knownTables !== null) {
            $effectiveOptions['knownTables'] = $knownTables;
        }

        $this->enforceSelectPrivileges($tables, $tableName, $effectiveOptions);

        if ($fromCommit === $toCommit) {
            return [];
        }

        $selectedTables = $tableName === null
            ? $tables
            : $this->selectTableDelta($tables, $tableName, $knownTables);

        return $this->renderer->rows($selectedTables, [
            'fromCommit' => $fromCommit,
            'toCommit' => $toCommit,
            'filter' => $options['filter'] ?? null,
        ], $warnings);
    }

    /**
     * @param list<mixed|PatchFunctionArgument> $arguments
     * @param array{mergeBases?:array<string, string>} $options
     * @return array{0:string, 1:string, 2:string|null}
     */
    public function parseArguments(array $arguments, array $options = []): array
    {
        $count = count($arguments);
        if ($count < 1 || $count > 3) {
            throw new \InvalidArgumentException("Invalid argument number for dolt_patch: expected 1 to 3, got {$count}.");
        }

        $first = $this->textArgument($arguments[0], 'revision');
        if (str_contains($first, '..')) {
            if ($count > 2) {
                throw new \InvalidArgumentException("Invalid argument number for dolt_patch: expected 1 or 2 with '..' revision syntax, got {$count}.");
            }
            $tableName = $count === 2 ? $this->textArgument($arguments[1], 'table name') : null;

            return [...$this->parseDotRange($first, $options), $tableName];
        }

        if ($count < 2) {
            throw new \InvalidArgumentException("Invalid argument number for dolt_patch: expected 2 or 3 without '..' revision syntax, got {$count}.");
        }

        $toCommit = $this->textArgument($arguments[1], 'to revision');
        $tableName = $count === 3 ? $this->textArgument($arguments[2], 'table name') : null;

        return [$first, $toCommit, $tableName];
    }

    /**
     * @param array{revisionGraph?:list<array<string, mixed>>, headHash?:string} $options
     * @return array{0:string, 1:string}
     */
    private function resolvePatchRevisions(string $fromCommit, string $toCommit, array $options): array
    {
        if (!array_key_exists('revisionGraph', $options)) {
            return [$fromCommit, $toCommit];
        }
        if (!is_array($options['revisionGraph'])) {
            throw new \InvalidArgumentException('Patch revisionGraph must be a list of commits.');
        }

        $graph = new CommitGraph();
        $headHash = $options['headHash'] ?? null;
        if ($headHash !== null && (!is_string($headHash) || $headHash === '')) {
            throw new \InvalidArgumentException('Patch headHash must be a non-empty string when supplied.');
        }

        return [
            $this->resolvePatchRevision($graph, $options['revisionGraph'], $fromCommit, $headHash),
            $this->resolvePatchRevision($graph, $options['revisionGraph'], $toCommit, $headHash),
        ];
    }

    /**
     * @param list<array<string, mixed>> $commits
     */
    private function resolvePatchRevision(CommitGraph $graph, array $commits, string $spec, ?string $headHash): string
    {
        if ($spec === 'WORKING' || $spec === 'STAGED') {
            return $spec;
        }

        try {
            return $graph->resolve($commits, $spec, $headHash);
        } catch (\InvalidArgumentException $exception) {
            throw $exception;
        } catch (\RuntimeException $exception) {
            if (str_starts_with($exception->getMessage(), 'Dolt ref or commit not found:')) {
                throw new \RuntimeException($this->revisionResolutionError($spec), 0, $exception);
            }

            throw $exception;
        }
    }

    private function revisionResolutionError(string $spec): string
    {
        try {
            $baseSpec = CommitGraph::splitAncestorSpec($spec)['commit_spec'];
        } catch (\InvalidArgumentException) {
            $baseSpec = $spec;
        }

        if ($this->isDoltHashLike($baseSpec)) {
            return 'target commit not found';
        }

        return "branch not found: {$baseSpec}";
    }

    /**
     * @param mixed $snapshots
     * @param list<string>|null $knownTables
     * @return list<array<string, mixed>>
     */
    private function tableDeltasFromRevisionSnapshots(
        mixed $snapshots,
        string $fromCommit,
        string $toCommit,
        string $fromSpec,
        string $toSpec,
        ?array &$knownTables
    ): array {
        $snapshots = $this->normalizeRevisionSnapshots($snapshots);
        $fromTables = $this->snapshotForRevision($snapshots, $fromCommit, $fromSpec);
        $toTables = $this->snapshotForRevision($snapshots, $toCommit, $toSpec);
        $knownTables = $this->knownTableNamesFromSnapshots($fromTables, $toTables);

        $fromIndex = $this->indexSnapshotTables($fromTables, 'from');
        $toIndex = $this->indexSnapshotTables($toTables, 'to');
        $matcher = new TableDeltaMatcher();
        $summaries = $matcher->summaries(
            $this->summaryTablesFromSnapshots($fromTables),
            $this->summaryTablesFromSnapshots($toTables)
        );

        $deltas = [];
        foreach ($summaries as $summary) {
            $fromName = $summary['from_table_name'];
            $toName = $summary['to_table_name'];
            $from = is_string($fromName) && isset($fromIndex[$fromName]) ? $fromIndex[$fromName] : null;
            $to = is_string($toName) && isset($toIndex[$toName]) ? $toIndex[$toName] : null;
            $schemaSource = $to ?? $from;
            if ($schemaSource === null) {
                continue;
            }

            $delta = [
                'tableName' => $summary['table_name'],
                'fromTableName' => $fromName,
                'toTableName' => $toName,
                'fromSchema' => $from['schema'] ?? null,
                'toSchema' => $to['schema'] ?? null,
                'fromRows' => $from['rows'] ?? [],
                'toRows' => $to['rows'] ?? [],
                'columns' => $schemaSource['columns'],
                'keyless' => $schemaSource['keyless'],
            ];

            if ($schemaSource['primaryKey'] !== null) {
                $delta['primaryKey'] = $schemaSource['primaryKey'];
            }

            $deltas[] = $delta;
        }

        return $deltas;
    }

    /**
     * @param mixed $snapshots
     * @return array<string, list<array{name:non-empty-string, schema:TableSchema, rows:list<array<string, scalar|null>>, primaryKey:non-empty-string|list<non-empty-string>|null, columns:list<non-empty-string>, keyless:bool, rowHash:string, rowCount:int}>>
     */
    private function normalizeRevisionSnapshots(mixed $snapshots): array
    {
        if (!is_array($snapshots)) {
            throw new \InvalidArgumentException('Patch revisionSnapshots must be a map of revision names to table snapshots.');
        }

        $normalized = [];
        foreach ($snapshots as $revision => $tables) {
            if (!is_string($revision) || $revision === '') {
                throw new \InvalidArgumentException('Patch revisionSnapshots keys must be non-empty revision strings.');
            }
            if (!is_array($tables)) {
                throw new \InvalidArgumentException("Patch revisionSnapshots entry {$revision} must be a list of table snapshots.");
            }

            $normalized[$revision] = [];
            foreach (array_values($tables) as $i => $table) {
                if (!is_array($table)) {
                    throw new \InvalidArgumentException("Patch revisionSnapshots {$revision} table {$i} must be an array.");
                }

                $name = $this->snapshotTableName($table, "{$revision} table {$i}");
                $schema = $table['schema'] ?? null;
                if (!$schema instanceof TableSchema) {
                    throw new \InvalidArgumentException("Patch revisionSnapshots {$revision} table {$name} must include a TableSchema.");
                }

                $rows = $this->snapshotRows($table['rows'] ?? [], "{$revision} table {$name} rows");
                $primaryKey = $this->snapshotPrimaryKey($table['primaryKey'] ?? null, $schema, "{$revision} table {$name}");
                $columns = array_key_exists('columns', $table)
                    ? $this->stringList($table['columns'], "{$revision} table {$name} columns")
                    : $this->schemaColumnNames($schema);
                $keyless = array_key_exists('keyless', $table) ? $table['keyless'] : $schema->isKeyless();
                if (!is_bool($keyless)) {
                    throw new \InvalidArgumentException("Patch revisionSnapshots {$revision} table {$name} keyless must be boolean.");
                }

                $rowHash = $table['rowHash'] ?? $this->rowHash($rows);
                if (!is_string($rowHash) || $rowHash === '') {
                    throw new \InvalidArgumentException("Patch revisionSnapshots {$revision} table {$name} rowHash must be a non-empty string.");
                }
                $rowCount = $table['rowCount'] ?? count($rows);
                if (!is_int($rowCount) || $rowCount < 0) {
                    throw new \InvalidArgumentException("Patch revisionSnapshots {$revision} table {$name} rowCount must be a non-negative integer.");
                }

                $normalized[$revision][] = [
                    'name' => $name,
                    'schema' => $schema,
                    'rows' => $rows,
                    'primaryKey' => $primaryKey,
                    'columns' => $columns,
                    'keyless' => $keyless,
                    'rowHash' => $rowHash,
                    'rowCount' => $rowCount,
                ];
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, list<array{name:non-empty-string, schema:TableSchema, rows:list<array<string, scalar|null>>, primaryKey:non-empty-string|list<non-empty-string>|null, columns:list<non-empty-string>, keyless:bool, rowHash:string, rowCount:int}>> $snapshots
     * @return list<array{name:non-empty-string, schema:TableSchema, rows:list<array<string, scalar|null>>, primaryKey:non-empty-string|list<non-empty-string>|null, columns:list<non-empty-string>, keyless:bool, rowHash:string, rowCount:int}>
     */
    private function snapshotForRevision(array $snapshots, string $resolvedRevision, string $originalSpec): array
    {
        foreach ([$resolvedRevision, $originalSpec] as $revision) {
            if (isset($snapshots[$revision])) {
                return $snapshots[$revision];
            }
        }

        throw new \InvalidArgumentException("Patch revisionSnapshots is missing snapshot for revision {$originalSpec}.");
    }

    /**
     * @param list<array{name:non-empty-string, schema:TableSchema, rows:list<array<string, scalar|null>>, primaryKey:non-empty-string|list<non-empty-string>|null, columns:list<non-empty-string>, keyless:bool, rowHash:string, rowCount:int}> $tables
     * @return array<string, array{name:non-empty-string, schema:TableSchema, rows:list<array<string, scalar|null>>, primaryKey:non-empty-string|list<non-empty-string>|null, columns:list<non-empty-string>, keyless:bool, rowHash:string, rowCount:int}>
     */
    private function indexSnapshotTables(array $tables, string $side): array
    {
        $indexed = [];
        foreach ($tables as $table) {
            if (isset($indexed[$table['name']])) {
                throw new \InvalidArgumentException("Duplicate {$side} revision snapshot table: {$table['name']}.");
            }
            $indexed[$table['name']] = $table;
        }

        return $indexed;
    }

    /**
     * @param list<array{name:non-empty-string, schema:TableSchema, rows:list<array<string, scalar|null>>, primaryKey:non-empty-string|list<non-empty-string>|null, columns:list<non-empty-string>, keyless:bool, rowHash:string, rowCount:int}> $tables
     * @return list<array{name:non-empty-string, schema:TableSchema, rowHash:string, rowCount:int}>
     */
    private function summaryTablesFromSnapshots(array $tables): array
    {
        return array_map(
            static fn (array $table): array => [
                'name' => $table['name'],
                'schema' => $table['schema'],
                'rowHash' => $table['rowHash'],
                'rowCount' => $table['rowCount'],
            ],
            $tables
        );
    }

    /**
     * @param list<array{name:non-empty-string}> $fromTables
     * @param list<array{name:non-empty-string}> $toTables
     * @return list<non-empty-string>
     */
    private function knownTableNamesFromSnapshots(array $fromTables, array $toTables): array
    {
        $names = [];
        foreach ([$fromTables, $toTables] as $tables) {
            foreach ($tables as $table) {
                $names[$table['name']] = $table['name'];
            }
        }

        ksort($names, SORT_STRING);

        return array_values($names);
    }

    /**
     * @param list<string>|null $explicit
     * @param list<string>|null $fromSnapshots
     * @return list<string>|null
     */
    private function mergeKnownTables(mixed $explicit, ?array $fromSnapshots): ?array
    {
        if ($explicit === null) {
            return $fromSnapshots;
        }

        $names = [];
        foreach ($this->stringList($explicit, 'knownTables') as $name) {
            $names[strtolower($name)] = $name;
        }
        foreach ($fromSnapshots ?? [] as $name) {
            $names[strtolower($name)] = $name;
        }

        return array_values($names);
    }

    /**
     * @param array<string, mixed> $table
     */
    private function snapshotTableName(array $table, string $label): string
    {
        $name = $table['name'] ?? $table['tableName'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException("Patch revisionSnapshots {$label} must include a non-empty name.");
        }

        return $name;
    }

    /**
     * @return list<array<string, scalar|null>>
     */
    private function snapshotRows(mixed $rows, string $label): array
    {
        if (!is_array($rows)) {
            throw new \InvalidArgumentException("Patch revisionSnapshots {$label} must be a list of row arrays.");
        }

        $normalized = [];
        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("Patch revisionSnapshots {$label} row {$i} must be an array.");
            }
            foreach ($row as $column => $value) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException("Patch revisionSnapshots {$label} row {$i} columns must be non-empty strings.");
                }
                if ($value !== null && !is_scalar($value)) {
                    throw new \InvalidArgumentException("Patch revisionSnapshots {$label} row {$i} values must be scalar or null.");
                }
            }
            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * @return non-empty-string|list<non-empty-string>|null
     */
    private function snapshotPrimaryKey(mixed $primaryKey, TableSchema $schema, string $label): string|array|null
    {
        if ($primaryKey === null) {
            $columns = array_map(static fn (array $column): string => $column['name'], $schema->primaryKeyColumns());

            return $columns === [] ? null : $columns;
        }

        if (is_string($primaryKey)) {
            if ($primaryKey === '') {
                throw new \InvalidArgumentException("Patch revisionSnapshots {$label} primaryKey must be non-empty.");
            }

            return $primaryKey;
        }

        $columns = $this->stringList($primaryKey, "{$label} primaryKey");
        if ($columns === []) {
            throw new \InvalidArgumentException("Patch revisionSnapshots {$label} primaryKey must include at least one column.");
        }

        return $columns;
    }

    /**
     * @return list<non-empty-string>
     */
    private function schemaColumnNames(TableSchema $schema): array
    {
        return array_map(static fn (array $column): string => $column['name'], $schema->columns());
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     */
    private function rowHash(array $rows): string
    {
        $encodedRows = [];
        foreach ($rows as $row) {
            ksort($row, SORT_STRING);
            $encodedRows[] = json_encode($row, JSON_THROW_ON_ERROR);
        }
        sort($encodedRows, SORT_STRING);

        return hash('sha256', json_encode($encodedRows, JSON_THROW_ON_ERROR));
    }

    private function isDoltHashLike(string $spec): bool
    {
        return preg_match('/^[a-z0-9]{32}$/i', $spec) === 1;
    }

    /**
     * @param list<array<string, mixed>> $tables
     * @param array{
     *   knownTables?:list<string>,
     *   databaseName?:string,
     *   databaseTables?:list<string>,
     *   selectPrivileges?:list<string>
     * } $options
     */
    private function enforceSelectPrivileges(array $tables, ?string $tableName, array $options): void
    {
        if (!array_key_exists('selectPrivileges', $options)) {
            return;
        }

        $database = $this->baseDatabaseName($options['databaseName'] ?? null);
        $privileges = $this->selectPrivileges($options['selectPrivileges']);

        if ($tableName !== null) {
            $this->assertSelectPrivilege($database, $tableName, $privileges);

            return;
        }

        foreach ($this->databaseTableNames($tables, $options) as $databaseTableName) {
            $this->assertSelectPrivilege($database, $databaseTableName, $privileges);
        }
    }

    private function baseDatabaseName(mixed $databaseName): string
    {
        if (!is_string($databaseName) || $databaseName === '') {
            throw new \InvalidArgumentException('Patch databaseName must be a non-empty string when selectPrivileges are supplied.');
        }

        $base = explode('/', $databaseName, 2)[0];
        if ($base === '') {
            throw new \InvalidArgumentException('Patch databaseName must include a base database name.');
        }

        return $base;
    }

    /**
     * @return list<string>
     */
    private function selectPrivileges(mixed $privileges): array
    {
        if (!is_array($privileges)) {
            throw new \InvalidArgumentException('Patch selectPrivileges must be a list of database-qualified SELECT grants.');
        }

        $normalized = [];
        foreach ($privileges as $privilege) {
            if (!is_string($privilege) || $privilege === '') {
                throw new \InvalidArgumentException('Patch selectPrivileges must contain non-empty strings.');
            }

            $privilege = strtolower(str_replace('`', '', trim($privilege)));
            if (!preg_match('/^[^.]+\\.(?:[^.]+|\\*)$/', $privilege) && $privilege !== '*.*') {
                throw new \InvalidArgumentException('Patch selectPrivileges entries must be database-qualified grants like db.table, db.*, or *.*.');
            }

            $normalized[] = $privilege;
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $tables
     * @param array{knownTables?:list<string>, databaseTables?:list<string>} $options
     * @return list<string>
     */
    private function databaseTableNames(array $tables, array $options): array
    {
        if (array_key_exists('databaseTables', $options)) {
            return $this->stringList($options['databaseTables'], 'databaseTables');
        }

        if (array_key_exists('knownTables', $options)) {
            return $this->stringList($options['knownTables'], 'knownTables');
        }

        $names = [];
        $seen = [];
        foreach ($tables as $table) {
            if (!is_array($table)) {
                throw new \InvalidArgumentException('Patch tables must be arrays.');
            }
            foreach ($this->tableNames($table) as $name) {
                if ($name === '') {
                    continue;
                }

                $key = strtolower($name);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("Patch {$field} must be a list of table names.");
        }

        $strings = [];
        foreach ($value as $entry) {
            if (!is_string($entry) || $entry === '') {
                throw new \InvalidArgumentException("Patch {$field} must contain non-empty strings.");
            }
            $strings[] = $entry;
        }

        return $strings;
    }

    /**
     * @param list<string> $privileges
     */
    private function assertSelectPrivilege(string $database, string $tableName, array $privileges): void
    {
        if ($this->hasSelectPrivilege($database, $tableName, $privileges)) {
            return;
        }

        if (!$this->hasAnyDatabaseAccess($database, $privileges)) {
            throw new \RuntimeException("database access denied for user on database {$database}");
        }

        throw new \RuntimeException("privilege check failed: SELECT on {$database}.{$tableName}");
    }

    /**
     * @param list<string> $privileges
     */
    private function hasSelectPrivilege(string $database, string $tableName, array $privileges): bool
    {
        $database = strtolower($database);
        $tableName = strtolower($tableName);

        return in_array('*.*', $privileges, true)
            || in_array("{$database}.*", $privileges, true)
            || in_array("{$database}.{$tableName}", $privileges, true);
    }

    /**
     * @param list<string> $privileges
     */
    private function hasAnyDatabaseAccess(string $database, array $privileges): bool
    {
        $database = strtolower($database);

        foreach ($privileges as $privilege) {
            if ($privilege === '*.*' || str_starts_with($privilege, "{$database}.")) {
                return true;
            }
        }

        return false;
    }

    private function textArgument(mixed $argument, string $field): string
    {
        if ($argument instanceof PatchFunctionArgument) {
            if (!$argument->isLiteral()) {
                throw new \InvalidArgumentException(
                    "Invalid argument to dolt_patch: {$argument->expressionString()} - only literal values supported."
                );
            }
            $argument = $argument->value();
        }

        if (!is_string($argument) || $argument === '') {
            throw new \InvalidArgumentException("Invalid argument details for dolt_patch {$field}: expected a non-empty text literal.");
        }

        return $argument;
    }

    /**
     * @param array{mergeBases?:array<string, string>} $options
     * @return array{0:string, 1:string}
     */
    private function parseDotRange(string $revision, array $options): array
    {
        if (str_contains($revision, '...')) {
            [$left, $right] = $this->splitRevisionRange($revision, '...');
            $mergeBases = $options['mergeBases'] ?? [];
            if (!is_array($mergeBases)) {
                throw new \InvalidArgumentException('Patch mergeBases option must be a map of revision ranges to merge-base revisions.');
            }
            $mergeBase = $mergeBases[$revision] ?? $mergeBases["{$left}...{$right}"] ?? null;
            if (!is_string($mergeBase) || $mergeBase === '') {
                throw new \InvalidArgumentException("Patch three-dot revision {$revision} requires a merge-base revision.");
            }

            return [$mergeBase, $right];
        }

        return $this->splitRevisionRange($revision, '..');
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function splitRevisionRange(string $revision, string $separator): array
    {
        $parts = explode($separator, $revision);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException("Invalid dolt_patch revision range: {$revision}.");
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * @param list<array<string, mixed>> $tables
     * @param list<string>|null $knownTables
     * @return list<array<string, mixed>>
     */
    private function selectTableDelta(array $tables, string $tableName, ?array $knownTables): array
    {
        foreach ($tables as $table) {
            [, $toTableName] = $this->tableNames($table);
            if ($toTableName !== '' && strcasecmp($toTableName, $tableName) === 0) {
                return [$table];
            }
        }

        foreach ($tables as $table) {
            [$fromTableName] = $this->tableNames($table);
            if ($fromTableName !== '' && strcasecmp($fromTableName, $tableName) === 0) {
                return [$table];
            }
        }

        if ($knownTables !== null) {
            foreach ($knownTables as $knownTable) {
                if (!is_string($knownTable) || $knownTable === '') {
                    throw new \InvalidArgumentException('Patch knownTables must contain non-empty strings.');
                }
                if (strcasecmp($knownTable, $tableName) === 0) {
                    return [];
                }
            }

            throw new \RuntimeException("table not found: {$tableName}");
        }

        return [];
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
            $fromTableName = $tableName;
            $toTableName = $tableName;
        } elseif ($fromTableName === '') {
            $fromTableName = $tableName !== '' ? $tableName : $toTableName;
        } elseif ($toTableName === '') {
            $toTableName = $tableName !== '' ? $tableName : $fromTableName;
        }

        return [$fromTableName, $toTableName, $toTableName !== '' ? $toTableName : $fromTableName];
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
}
