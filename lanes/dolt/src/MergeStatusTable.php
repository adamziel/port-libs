<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class MergeStatusTable
{
    public const UNMERGED_PATHS_HEADER = 'Unmerged paths:';
    public const UNMERGED_PATHS_HELP = '  (use "dolt add <table>..." to mark resolution)';
    public const ALL_MERGED_HEADER = "All conflicts and constraint violations fixed but you are still merging.\n  (use \"dolt commit\" to conclude merge)";
    public const MERGE_CONFLICTS_HELP = "Use 'dolt conflicts' to investigate and resolve conflicts.";
    public const MERGE_UP_TO_DATE_MESSAGE = 'Everything up-to-date';
    public const MERGE_FAST_FORWARD_MESSAGE = 'Fast-forward';
    public const MERGE_SQUASH_MESSAGE = 'Squash commit -- not updating HEAD';
    public const MERGE_NO_COMMIT_MESSAGE = 'Automatic merge went well; stopped before committing as requested';
    public const MERGE_ABORT_NO_ACTIVE_ERROR = 'fatal: There is no merge to abort';
    public const MERGE_FF_ONLY_NOT_POSSIBLE_ERROR = 'fatal: Not possible to fast-forward, aborting';
    public const MERGE_SUCCESS_MESSAGE = 'merge successful';
    public const MERGE_CONFLICTS_FOUND_MESSAGE = 'conflicts found';
    public const MERGE_ABORTED_MESSAGE = 'merge aborted';
    public const MERGE_AHEAD_MESSAGE = 'cannot fast forward from a to b. a is ahead of b already';
    public const MERGE_COMMIT_NO_COMMIT_ERROR = "cannot define both 'commit' and 'no-commit' flags at the same time";
    public const UNRESOLVED_CONFLICTS_TRANSACTION_ERROR = 'Merge conflict detected, transaction rolled back. Merge conflicts must be resolved using the dolt_conflicts and dolt_schema_conflicts tables before committing a transaction. To commit transactions with merge conflicts, set @@dolt_allow_commit_conflicts = 1';
    public const UNRESOLVED_CONFLICTS_AUTOCOMMIT_ERROR = 'Merge conflict detected, @autocommit transaction rolled back. @autocommit must be disabled so that merge conflicts can be resolved using the dolt_conflicts and dolt_schema_conflicts tables before manually committing the transaction. Alternatively, to commit transactions with merge conflicts, set @@dolt_allow_commit_conflicts = 1';
    public const OPERATION_ADDED = 'added';
    public const OPERATION_DELETED = 'deleted';
    public const OPERATION_MODIFIED = 'modified';
    public const OPERATION_UNMODIFIED = 'unmodified';

    /**
     * Project the single row returned by upstream `dolt_merge_status`.
     *
     * @param list<string> $dataConflictTables
     * @param list<string> $constraintViolationTables
     * @param list<string> $schemaConflictTables
     * @return array{is_merging:bool, source:string|null, source_commit:string|null, target:string|null, unmerged_tables:string|null}
     */
    public function statusRow(
        bool $isMerging,
        ?string $source = null,
        ?string $sourceCommit = null,
        ?string $target = null,
        array $dataConflictTables = [],
        array $constraintViolationTables = [],
        array $schemaConflictTables = [],
    ): array {
        if (!$isMerging) {
            return [
                'is_merging' => false,
                'source' => null,
                'source_commit' => null,
                'target' => null,
                'unmerged_tables' => null,
            ];
        }

        foreach ([
            'source' => $source,
            'source_commit' => $sourceCommit,
            'target' => $target,
        ] as $field => $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("Active Dolt merge status requires a non-empty {$field}.");
            }
        }

        return [
            'is_merging' => true,
            'source' => $source,
            'source_commit' => $sourceCommit,
            'target' => $target,
            'unmerged_tables' => $this->tableNamesAsString($this->uniqueTableNames(
                $dataConflictTables,
                $constraintViolationTables,
                $schemaConflictTables
            )),
        ];
    }

    /**
     * Project the row shape returned by upstream `dolt_conflicts`.
     *
     * Data and schema conflict table names share the same table-name set in
     * upstream before partition rows are produced. Root-object conflicts are
     * appended separately for schema objects such as views and procedures.
     *
     * @param list<string|array{name:string, numConflicts?:int}> $dataConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $schemaConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $rootObjectConflicts
     * @return list<array{table:string, num_conflicts:int}>
     */
    public function conflictRows(
        array $dataConflictTables,
        array $schemaConflictTables = [],
        array $rootObjectConflicts = [],
    ): array {
        $tableRows = [];
        foreach ([$dataConflictTables, $schemaConflictTables] as $items) {
            foreach ($items as $item) {
                $normalized = $this->normalizeConflictItem($item);
                if (isset($tableRows[$normalized['name']])) {
                    $tableRows[$normalized['name']] = max($tableRows[$normalized['name']], $normalized['numConflicts']);
                    continue;
                }
                $tableRows[$normalized['name']] = $normalized['numConflicts'];
            }
        }

        $rows = [];
        foreach ($tableRows as $name => $count) {
            $rows[] = ['table' => $name, 'num_conflicts' => $count];
        }

        foreach ($rootObjectConflicts as $item) {
            $normalized = $this->normalizeConflictItem($item);
            $rows[] = ['table' => $normalized['name'], 'num_conflicts' => $normalized['numConflicts']];
        }

        return $rows;
    }

    /**
     * Project root-object conflict detail rows for schema objects such as
     * views, triggers, events, and stored procedures.
     *
     * @param list<array<string,mixed>> $conflicts
     * @return list<array{object_type:string, name:string, base_definition:string, our_definition:string, their_definition:string, description:string}>
     */
    public function rootObjectConflictRows(array $conflicts): array
    {
        $rows = [];
        foreach ($conflicts as $conflict) {
            $type = $this->rootObjectString($conflict['object_type'] ?? $conflict['type'] ?? null, 'root object type');
            $name = $this->rootObjectString($conflict['name'] ?? $conflict['object_name'] ?? null, 'root object name');
            $description = $conflict['description'] ?? null;
            if ($description === null) {
                $description = "root object {$type}:{$name} conflicts between branches";
            }
            if (!is_string($description) || $description === '') {
                throw new \InvalidArgumentException("Dolt root object conflict {$type}:{$name} must include a non-empty description.");
            }

            $rows[] = [
                'object_type' => $type,
                'name' => $name,
                'base_definition' => $this->rootObjectDefinition($conflict['base_definition'] ?? $conflict['baseDefinition'] ?? null),
                'our_definition' => $this->rootObjectDefinition($conflict['our_definition'] ?? $conflict['ourDefinition'] ?? null),
                'their_definition' => $this->rootObjectDefinition($conflict['their_definition'] ?? $conflict['theirDefinition'] ?? null),
                'description' => $description,
            ];
        }

        return $rows;
    }

    /**
     * Project the visible merge state after `dolt add <table>...` marks
     * schema conflict tables as resolved.
     *
     * @param list<string|array{name:string, numConflicts?:int}> $schemaConflictTables
     * @param list<string> $resolvedTables
     * @return array{remaining_schema_conflicts:list<array{table:string,num_conflicts:int}>, status_guidance:string|null, commit_guidance:string|null}
     */
    public function resolveSchemaConflicts(array $schemaConflictTables, array $resolvedTables): array
    {
        $resolved = [];
        foreach ($this->uniqueTableNames($resolvedTables) as $tableName) {
            $resolved[$tableName] = true;
        }

        $remaining = [];
        foreach ($this->conflictRows([], $schemaConflictTables) as $row) {
            if (isset($resolved[$row['table']])) {
                continue;
            }
            $remaining[] = $row;
        }

        $remainingTables = array_map(
            static fn (array $row): string => $row['table'],
            $remaining
        );

        return [
            'remaining_schema_conflicts' => $remaining,
            'status_guidance' => $this->statusGuidance(true, [], $remainingTables),
            'commit_guidance' => $this->commitUnmergedPaths([], $remainingTables),
        ];
    }

    /**
     * Project the visible state after `dolt_conflicts_resolve('--ours'|'--theirs', <table>)`
     * resolves one schema-conflict table by choosing a schema side.
     *
     * @param list<array<string,mixed>> $schemaConflicts
     * @return array{table:string, resolution:string, selected_schema:string, remaining_schema_conflicts:list<array{table:string,num_conflicts:int}>, status_guidance:string|null, commit_guidance:string|null}
     */
    public function resolveSchemaConflictSide(array $schemaConflicts, string $tableName, string $resolution): array
    {
        $tableName = $this->rootObjectString($tableName, 'schema conflict table name');
        $resolution = strtolower($this->rootObjectString($resolution, 'schema conflict resolution'));
        if ($resolution !== 'ours' && $resolution !== 'theirs') {
            throw new \InvalidArgumentException("Dolt schema conflict resolution must be 'ours' or 'theirs'.");
        }

        $selectedSchema = null;
        $remaining = [];
        foreach ($schemaConflicts as $conflict) {
            $conflictTable = $this->rootObjectString(
                $conflict['table_name'] ?? $conflict['table'] ?? $conflict['name'] ?? null,
                'schema conflict table name'
            );
            if ($conflictTable === $tableName) {
                $schema = $conflict[$resolution === 'ours' ? 'our_schema' : 'their_schema'] ?? null;
                if (!is_string($schema) || $schema === '') {
                    throw new \InvalidArgumentException("Dolt schema conflict {$tableName} must include a non-empty selected {$resolution} schema.");
                }
                $selectedSchema = $schema;
                continue;
            }

            $remaining[] = ['name' => $conflictTable];
        }

        if ($selectedSchema === null) {
            throw new \InvalidArgumentException("Dolt schema conflict table {$tableName} was not found.");
        }

        $remainingRows = $this->conflictRows([], $remaining);
        $remainingTables = array_map(
            static fn (array $row): string => $row['table'],
            $remainingRows
        );

        return [
            'table' => $tableName,
            'resolution' => $resolution,
            'selected_schema' => $selectedSchema,
            'remaining_schema_conflicts' => $remainingRows,
            'status_guidance' => $this->statusGuidance(true, [], $remainingTables),
            'commit_guidance' => $this->commitUnmergedPaths([], $remainingTables),
        ];
    }

    /**
     * Project the visible conflict-table state after root-object conflicts
     * such as views or stored procedures are marked resolved.
     *
     * @param list<string|array{name:string, numConflicts?:int}> $rootObjectConflicts
     * @param list<string> $resolvedObjects
     * @return array{remaining_root_object_conflicts:list<array{table:string,num_conflicts:int}>, conflict_rows:list<array{table:string,num_conflicts:int}>, merge_failure_summary:string|null}
     */
    public function resolveRootObjectConflicts(array $rootObjectConflicts, array $resolvedObjects): array
    {
        $resolved = [];
        foreach ($this->uniqueTableNames($resolvedObjects) as $objectName) {
            $resolved[$objectName] = true;
        }

        $remaining = [];
        foreach ($this->conflictRows([], [], $rootObjectConflicts) as $row) {
            if (isset($resolved[$row['table']])) {
                continue;
            }
            $remaining[] = $row;
        }
        $remainingObjects = array_map(
            static fn (array $row): array => ['name' => $row['table'], 'numConflicts' => $row['num_conflicts']],
            $remaining
        );

        return [
            'remaining_root_object_conflicts' => $remaining,
            'conflict_rows' => $remaining,
            'merge_failure_summary' => $this->mergeFailureSummary([], [], [], $remainingObjects),
        ];
    }

    /**
     * Project the visible merge state after a partial resolution pass removes
     * selected row, schema, constraint, and root-object blockers.
     *
     * @param list<string|array{name:string, numConflicts?:int}> $dataConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $schemaConflictTables
     * @param list<string> $constraintViolationTables
     * @param list<string|array{name:string, numConflicts?:int}> $rootObjectConflicts
     * @param array{data?:list<string>, schema?:list<string>, constraints?:list<string>, rootObjects?:list<string>} $resolved
     * @return array{remaining_data_conflicts:list<array{table:string,num_conflicts:int}>, remaining_schema_conflicts:list<array{table:string,num_conflicts:int}>, remaining_constraint_violations:list<non-empty-string>, remaining_root_object_conflicts:list<array{table:string,num_conflicts:int}>, conflict_rows:list<array{table:string,num_conflicts:int}>, status_guidance:string|null, commit_guidance:string|null, merge_failure_summary:string|null}
     */
    public function resolveMergeArtifacts(
        array $dataConflictTables = [],
        array $schemaConflictTables = [],
        array $constraintViolationTables = [],
        array $rootObjectConflicts = [],
        array $resolved = [],
    ): array {
        $remainingData = $this->unresolvedConflictRows($dataConflictTables, $resolved['data'] ?? []);
        $remainingSchema = $this->unresolvedConflictRows($schemaConflictTables, $resolved['schema'] ?? []);
        $remainingConstraints = $this->unresolvedTableNames($constraintViolationTables, $resolved['constraints'] ?? []);
        $remainingRootObjects = $this->unresolvedConflictRows($rootObjectConflicts, $resolved['rootObjects'] ?? []);

        $dataItems = $this->conflictRowsToItems($remainingData);
        $schemaItems = $this->conflictRowsToItems($remainingSchema);
        $rootObjectItems = $this->conflictRowsToItems($remainingRootObjects);

        return [
            'remaining_data_conflicts' => $remainingData,
            'remaining_schema_conflicts' => $remainingSchema,
            'remaining_constraint_violations' => $remainingConstraints,
            'remaining_root_object_conflicts' => $remainingRootObjects,
            'conflict_rows' => $this->conflictRows($dataItems, $schemaItems, $rootObjectItems),
            'status_guidance' => $this->statusGuidance(true, $dataItems, $schemaItems, $remainingConstraints),
            'commit_guidance' => $this->commitUnmergedPaths($dataItems, $schemaItems, $remainingConstraints),
            'merge_failure_summary' => $this->mergeFailureSummary($dataItems, $schemaItems, $remainingConstraints, $rootObjectItems),
        ];
    }

    /**
     * Render the artifact prelude printed by upstream `dolt merge` before its
     * final failure summary.
     *
     * @param list<string|array{name:string, numConflicts?:int}> $dataConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $schemaConflictTables
     * @param list<string> $constraintViolationTables
     * @param list<string|array{name:string, numConflicts?:int}> $rootObjectConflicts
     */
    public function mergeArtifactPrelude(
        array $dataConflictTables = [],
        array $schemaConflictTables = [],
        array $constraintViolationTables = [],
        array $rootObjectConflicts = [],
    ): ?string {
        $stats = [];
        $order = [];

        foreach ($dataConflictTables as $item) {
            $normalized = $this->normalizeConflictItem($item);
            $this->markArtifact($stats, $order, $normalized['name'], 'data');
        }
        foreach ($schemaConflictTables as $item) {
            $normalized = $this->normalizeConflictItem($item);
            $this->markArtifact($stats, $order, $normalized['name'], 'schema');
        }
        foreach ($this->uniqueTableNames($constraintViolationTables) as $tableName) {
            $this->markArtifact($stats, $order, $tableName, 'constraint');
        }
        foreach ($rootObjectConflicts as $item) {
            $normalized = $this->normalizeConflictItem($item);
            $this->markArtifact($stats, $order, $normalized['name'], 'root');
        }

        $lines = [];
        foreach ($order as $tableName) {
            $row = $stats[$tableName];
            $lines[] = "Auto-merging {$tableName}";
            if ($row['data'] || $row['root']) {
                $lines[] = "CONFLICT (content): Merge conflict in {$tableName}";
            }
            if ($row['schema']) {
                $lines[] = "CONFLICT (schema): Merge conflict in {$tableName}";
            }
            if ($row['constraint']) {
                $lines[] = "CONSTRAINT VIOLATION (content): Merge created constraint violation in {$tableName}";
            }
        }

        if ($lines === []) {
            return null;
        }

        return implode("\n", $lines);
    }

    /**
     * Render upstream `dolt merge` success stat lines for modified, added, and
     * deleted tables.
     *
     * @param list<array<string,mixed>> $tableStats
     */
    public function mergeSuccessStats(array $tableStats): ?string
    {
        $modified = [];
        $added = [];
        $deleted = [];
        $rowsAdded = 0;
        $rowsDeleted = 0;
        $rowsChanged = 0;
        $maxNameLen = 0;
        $maxModCount = 0;

        foreach ($tableStats as $row) {
            $stat = $this->normalizeMergeStat($row);
            if ($stat['operation'] === self::OPERATION_ADDED) {
                $added[] = $stat['table'];
                continue;
            }
            if ($stat['operation'] === self::OPERATION_DELETED) {
                $deleted[] = $stat['table'];
                continue;
            }
            if ($stat['operation'] !== self::OPERATION_MODIFIED) {
                continue;
            }
            if ($stat['dataConflicts'] > 0 || $stat['constraintViolations'] > 0) {
                continue;
            }

            $modCount = $stat['adds'] + $stat['modifications'] + $stat['deletes'] + $stat['dataConflicts'];
            $modified[] = $stat + ['modCount' => $modCount];
            $maxNameLen = max($maxNameLen, strlen($stat['table']));
            $maxModCount = max($maxModCount, $modCount);
            $rowsAdded += $stat['adds'];
            $rowsChanged += $stat['modifications'] + $stat['dataConflicts'];
            $rowsDeleted += $stat['deletes'];
        }

        usort($modified, static fn (array $a, array $b): int => strcmp($a['table'], $b['table']));

        $lines = [];
        foreach ($modified as $stat) {
            $modCount = (string) $stat['modCount'];
            $lines[] = str_pad($stat['table'], $maxNameLen, ' ', STR_PAD_RIGHT)
                . ' | '
                . str_pad($modCount, strlen((string) $maxModCount), ' ', STR_PAD_RIGHT)
                . ' '
                . $this->visualizeMergeChanges($stat, $maxModCount);
        }

        if ($modified !== []) {
            $lines[] = count($modified)
                . " tables changed, {$rowsAdded} rows added(+), {$rowsChanged} rows modified(*), {$rowsDeleted} rows deleted(-)";
        }

        foreach ($added as $tableName) {
            $lines[] = "{$tableName} added";
        }
        foreach ($deleted as $tableName) {
            $lines[] = "{$tableName} deleted";
        }

        if ($lines === []) {
            return null;
        }

        return implode("\n", $lines);
    }

    /**
     * Render the visible CLI result for validation errors, fast-forward-only
     * failures, and successful upstream `dolt merge` executions.
     *
     * @param list<array<string,mixed>> $tableStats
     * @param array{upToDate?:bool, fastForward?:bool, headHash?:string, mergeHash?:string, squash?:bool, noCommit?:bool, commit?:bool, ffOnly?:bool, noFf?:bool, canFastForward?:bool} $options
     */
    public function mergeCliTranscript(array $tableStats = [], array $options = []): string
    {
        $flagError = $this->mergeFlagError($options);
        if ($flagError !== null) {
            return $flagError;
        }

        $ffOnly = $this->boolOption($options, 'ffOnly');
        $canFastForward = $this->optionalBoolOption($options, 'canFastForward');
        if ($ffOnly && $canFastForward === false) {
            return self::MERGE_FF_ONLY_NOT_POSSIBLE_ERROR;
        }

        if ($ffOnly && $canFastForward === true && !$this->boolOption($options, 'upToDate') && !array_key_exists('fastForward', $options)) {
            $options['fastForward'] = true;
        }

        return $this->mergeSuccessTranscript($tableStats, $options);
    }

    /**
     * Project the four-column row returned by upstream `CALL DOLT_MERGE(...)`.
     *
     * The `conflicts` column is Dolt's upstream 0/1 unresolved-conflict or
     * constraint-violation indicator, not the count from `dolt_conflicts`.
     *
     * @param array{commitHash?:string,hash?:string,fastForward?:bool,ffOnly?:bool,canFastForward?:bool,noCommit?:bool,commit?:bool,noFf?:bool,squash?:bool,abort?:bool,hadActiveMerge?:bool,upToDate?:bool,ahead?:bool,conflicts?:int,hasConflicts?:bool,message?:string|null} $options
     * @return array{hash:string, fast_forward:int, conflicts:int, message:string|null}
     */
    public function mergeProcedureRow(array $options = []): array
    {
        $flagError = $this->mergeProcedureFlagError($options);
        if ($flagError !== null) {
            throw new \InvalidArgumentException($flagError);
        }

        if ($this->boolOption($options, 'abort')) {
            if (($this->optionalBoolOption($options, 'hadActiveMerge') ?? true) === false) {
                throw new \InvalidArgumentException(self::MERGE_ABORT_NO_ACTIVE_ERROR);
            }

            return $this->procedureRow('', 0, 0, self::MERGE_ABORTED_MESSAGE);
        }

        if ($this->boolOption($options, 'upToDate')) {
            return $this->procedureRow('', 0, 0, self::MERGE_UP_TO_DATE_MESSAGE);
        }

        if ($this->boolOption($options, 'ahead')) {
            return $this->procedureRow(
                '',
                0,
                0,
                $this->procedureMessage($options['message'] ?? self::MERGE_AHEAD_MESSAGE)
            );
        }

        $ffOnly = $this->boolOption($options, 'ffOnly');
        $canFastForward = $this->optionalBoolOption($options, 'canFastForward');
        if ($ffOnly && $canFastForward === false) {
            throw new \InvalidArgumentException(self::MERGE_FF_ONLY_NOT_POSSIBLE_ERROR);
        }

        $conflicts = $this->mergeProcedureConflictIndicator($options);
        if ($conflicts === 1) {
            return $this->procedureRow('', 0, 1, self::MERGE_CONFLICTS_FOUND_MESSAGE);
        }

        $fastForward = $this->boolOption($options, 'fastForward') || ($ffOnly && $canFastForward === true);
        if ($this->boolOption($options, 'noFf') && $fastForward) {
            throw new \InvalidArgumentException('Dolt merge procedure row cannot return fast_forward=1 when --no-ff is set.');
        }

        $commitHash = $this->optionalNonEmptyString($options['commitHash'] ?? $options['hash'] ?? null, 'commitHash');
        $noCommit = $this->boolOption($options, 'noCommit');
        if ($noCommit && !$fastForward) {
            if ($commitHash !== null) {
                throw new \InvalidArgumentException('Dolt merge procedure row cannot include a commit hash for a non-fast-forward --no-commit merge.');
            }

            return $this->procedureRow('', 0, 0, $this->procedureMessage($options['message'] ?? self::MERGE_SUCCESS_MESSAGE));
        }

        if ($commitHash === null) {
            throw new \InvalidArgumentException('Dolt merge procedure row requires commitHash for committed or fast-forward merge results.');
        }

        return $this->procedureRow(
            $commitHash,
            $fastForward ? 1 : 0,
            0,
            $this->procedureMessage($options['message'] ?? self::MERGE_SUCCESS_MESSAGE)
        );
    }

    /**
     * Project upstream SQL transaction error text for unresolved merge conflicts.
     *
     * @return self::UNRESOLVED_CONFLICTS_TRANSACTION_ERROR|self::UNRESOLVED_CONFLICTS_AUTOCOMMIT_ERROR|null
     */
    public function mergeTransactionConflictError(
        bool $hasUnresolvedConflicts,
        bool $autocommit,
        bool $allowCommitConflicts = false,
    ): ?string {
        if (!$hasUnresolvedConflicts || $allowCommitConflicts) {
            return null;
        }

        return $autocommit
            ? self::UNRESOLVED_CONFLICTS_AUTOCOMMIT_ERROR
            : self::UNRESOLVED_CONFLICTS_TRANSACTION_ERROR;
    }

    /**
     * Project SQL-visible merge artifact state after an unresolved-conflict
     * merge attempt either rolls back or remains queryable for resolution.
     *
     * @param list<string|array{name:string, numConflicts?:int}> $dataConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $schemaConflictTables
     * @param list<string> $constraintViolationTables
     * @param list<string|array{name:string, numConflicts?:int}> $rootObjectConflicts
     * @param array{source?:string, sourceCommit?:string, target?:string, autocommit?:bool, allowCommitConflicts?:bool} $options
     * @return array{error:string|null, rolled_back:bool, merge_status:array{is_merging:bool, source:string|null, source_commit:string|null, target:string|null, unmerged_tables:string|null}, conflict_rows:list<array{table:string,num_conflicts:int}>, status_guidance:string|null, commit_guidance:string|null, merge_failure_summary:string|null}
     */
    public function mergeRollbackState(
        array $dataConflictTables = [],
        array $schemaConflictTables = [],
        array $constraintViolationTables = [],
        array $rootObjectConflicts = [],
        array $options = [],
    ): array {
        $artifactState = $this->resolveMergeArtifacts(
            $dataConflictTables,
            $schemaConflictTables,
            $constraintViolationTables,
            $rootObjectConflicts,
        );
        $hasUnresolvedConflicts = $artifactState['conflict_rows'] !== [] || $artifactState['remaining_constraint_violations'] !== [];
        $autocommit = $this->optionalBoolOption($options, 'autocommit') ?? true;
        $allowCommitConflicts = $this->boolOption($options, 'allowCommitConflicts');
        $error = $this->mergeTransactionConflictError($hasUnresolvedConflicts, $autocommit, $allowCommitConflicts);
        $rolledBack = $error !== null && $autocommit;

        if ($rolledBack) {
            return [
                'error' => $error,
                'rolled_back' => true,
                'merge_status' => $this->statusRow(false),
                'conflict_rows' => [],
                'status_guidance' => null,
                'commit_guidance' => null,
                'merge_failure_summary' => null,
            ];
        }

        $source = $this->optionalNonEmptyString($options['source'] ?? null, 'source') ?? 'MERGE_HEAD';
        $sourceCommit = $this->optionalNonEmptyString($options['sourceCommit'] ?? null, 'sourceCommit') ?? 'MERGE_COMMIT';
        $target = $this->optionalNonEmptyString($options['target'] ?? null, 'target') ?? 'HEAD';

        return [
            'error' => $error,
            'rolled_back' => false,
            'merge_status' => $this->statusRow(
                $hasUnresolvedConflicts,
                $source,
                $sourceCommit,
                $target,
                array_map(static fn (array $row): string => $row['table'], $artifactState['remaining_data_conflicts']),
                $artifactState['remaining_constraint_violations'],
                array_map(static fn (array $row): string => $row['table'], $artifactState['remaining_schema_conflicts']),
            ),
            'conflict_rows' => $artifactState['conflict_rows'],
            'status_guidance' => $artifactState['status_guidance'],
            'commit_guidance' => $artifactState['commit_guidance'],
            'merge_failure_summary' => $artifactState['merge_failure_summary'],
        ];
    }

    /**
     * Project SQL-visible state after @@dolt_allow_commit_conflicts lets a
     * transaction commit unresolved merge artifacts instead of rolling back.
     *
     * @param list<string|array{name:string, numConflicts?:int}> $dataConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $schemaConflictTables
     * @param list<string> $constraintViolationTables
     * @param list<string|array{name:string, numConflicts?:int}> $rootObjectConflicts
     * @return array{error:null, committed:bool, merge_status:array{is_merging:bool, source:string|null, source_commit:string|null, target:string|null, unmerged_tables:string|null}, conflict_rows:list<array{table:string,num_conflicts:int}>, constraint_violation_tables:list<non-empty-string>, post_commit_review_summary:string|null}
     */
    public function mergeAllowedCommitState(
        array $dataConflictTables = [],
        array $schemaConflictTables = [],
        array $constraintViolationTables = [],
        array $rootObjectConflicts = [],
    ): array {
        $artifactState = $this->resolveMergeArtifacts(
            $dataConflictTables,
            $schemaConflictTables,
            $constraintViolationTables,
            $rootObjectConflicts,
        );
        $hasArtifacts = $artifactState['conflict_rows'] !== [] || $artifactState['remaining_constraint_violations'] !== [];

        return [
            'error' => null,
            'committed' => $hasArtifacts,
            'merge_status' => $this->statusRow(false),
            'conflict_rows' => $artifactState['conflict_rows'],
            'constraint_violation_tables' => $artifactState['remaining_constraint_violations'],
            'post_commit_review_summary' => $hasArtifacts
                ? $this->mergeFailureSummary(
                    $this->conflictRowsToItems($artifactState['remaining_data_conflicts']),
                    $this->conflictRowsToItems($artifactState['remaining_schema_conflicts']),
                    $artifactState['remaining_constraint_violations'],
                    $this->conflictRowsToItems($artifactState['remaining_root_object_conflicts']),
                )
                : null,
        ];
    }

    /**
     * Project upstream's pre-merge incompatible flag validation.
     *
     * @param array{squash?:bool, noFf?:bool, ffOnly?:bool, commit?:bool, noCommit?:bool} $options
     */
    public function mergeFlagError(array $options): ?string
    {
        if ($this->boolOption($options, 'squash') && $this->boolOption($options, 'noFf')) {
            return $this->incompatibleFlagError('squash', 'no-ff');
        }
        if ($this->boolOption($options, 'ffOnly') && $this->boolOption($options, 'noFf')) {
            return $this->incompatibleFlagError('ff-only', 'no-ff');
        }
        if ($this->boolOption($options, 'ffOnly') && $this->boolOption($options, 'squash')) {
            return $this->incompatibleFlagError('ff-only', 'squash');
        }
        if ($this->boolOption($options, 'commit') && $this->boolOption($options, 'noCommit')) {
            return $this->incompatibleFlagError('commit', 'no-commit');
        }

        return null;
    }

    /**
     * Render the non-error transcript prefix printed by upstream `dolt merge`
     * before merge stats.
     *
     * @param list<array<string,mixed>> $tableStats
     * @param array{upToDate?:bool, fastForward?:bool, headHash?:string, mergeHash?:string, squash?:bool, noCommit?:bool, commit?:bool, ffOnly?:bool, noFf?:bool, canFastForward?:bool} $options
     */
    public function mergeSuccessTranscript(array $tableStats = [], array $options = []): string
    {
        $flagError = $this->mergeFlagError($options);
        if ($flagError !== null) {
            throw new \InvalidArgumentException($flagError);
        }
        if ($this->boolOption($options, 'ffOnly') && $this->optionalBoolOption($options, 'canFastForward') === false) {
            throw new \InvalidArgumentException(self::MERGE_FF_ONLY_NOT_POSSIBLE_ERROR);
        }
        if ($this->boolOption($options, 'noFf') && $this->boolOption($options, 'fastForward')) {
            throw new \InvalidArgumentException('Dolt merge transcript cannot render Fast-forward output when --no-ff is set.');
        }

        if ($this->boolOption($options, 'upToDate')) {
            return self::MERGE_UP_TO_DATE_MESSAGE;
        }

        $lines = [];
        if ($this->boolOption($options, 'fastForward')) {
            $lines[] = self::MERGE_FAST_FORWARD_MESSAGE;
        }

        $headHash = $this->optionalNonEmptyString($options['headHash'] ?? null, 'headHash');
        $mergeHash = $this->optionalNonEmptyString($options['mergeHash'] ?? null, 'mergeHash');
        if (($headHash === null) !== ($mergeHash === null)) {
            throw new \InvalidArgumentException('Dolt merge transcript requires both headHash and mergeHash when rendering Updating output.');
        }
        if ($headHash !== null && $mergeHash !== null) {
            $lines[] = "Updating {$headHash}..{$mergeHash}";
        }

        if ($this->boolOption($options, 'squash')) {
            $lines[] = self::MERGE_SQUASH_MESSAGE;
        }
        if ($this->boolOption($options, 'noCommit')) {
            $lines[] = self::MERGE_NO_COMMIT_MESSAGE;
        }

        $stats = $this->mergeSuccessStats($tableStats);
        if ($stats !== null) {
            $lines[] = $stats;
        }

        return implode("\n", $lines);
    }

    /**
     * Project the visible state after upstream `dolt merge --abort`.
     *
     * The CLI prints no success text for a valid abort; the stored procedure's
     * internal "merge aborted" message is not echoed by `dolt merge --abort`.
     *
     * @param list<string> $preservedWorkingTables
     * @return array{output:string, merge_status:array{is_merging:bool, source:string|null, source_commit:string|null, target:string|null, unmerged_tables:string|null}, preserved_working_tables:list<non-empty-string>}
     */
    public function abortMergeState(array $preservedWorkingTables = [], bool $hadActiveMerge = true): array
    {
        if (!$hadActiveMerge) {
            throw new \InvalidArgumentException(self::MERGE_ABORT_NO_ACTIVE_ERROR);
        }

        return [
            'output' => '',
            'merge_status' => $this->statusRow(false),
            'preserved_working_tables' => $this->uniqueTableNames($preservedWorkingTables),
        ];
    }

    /**
     * Render the immediate failure summary printed by upstream `dolt merge`.
     *
     * @param list<string|array{name:string, numConflicts?:int}> $dataConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $schemaConflictTables
     * @param list<string> $constraintViolationTables
     * @param list<string|array{name:string, numConflicts?:int}> $rootObjectConflicts
     */
    public function mergeFailureSummary(
        array $dataConflictTables = [],
        array $schemaConflictTables = [],
        array $constraintViolationTables = [],
        array $rootObjectConflicts = [],
    ): ?string {
        $conflictRows = $this->conflictRows($dataConflictTables, $schemaConflictTables, $rootObjectConflicts);
        $unmergedConflictRows = $this->conflictRows($dataConflictTables, $schemaConflictTables);
        $constraintTables = $this->uniqueTableNames($constraintViolationTables);
        $hasConflicts = $conflictRows !== [];
        $hasConstraintViolations = $constraintTables !== [];

        if (!$hasConflicts && !$hasConstraintViolations) {
            return null;
        }

        $unmergedCount = count($this->uniqueUnmergedNames($unmergedConflictRows, $constraintTables));
        $header = "Automatic merge failed; {$unmergedCount} table(s) are unmerged.";

        if ($hasConflicts && $hasConstraintViolations) {
            return $header . "\n"
                . "Fix conflicts and constraint violations and then commit the result.\n"
                . self::MERGE_CONFLICTS_HELP;
        }

        if ($hasConflicts) {
            return $header . "\n" . self::MERGE_CONFLICTS_HELP;
        }

        return $header . "\n"
            . "Fix constraint violations and then commit the result.\n"
            . "Constraint violations for the working set may be viewed using the 'dolt_constraint_violations' system table.\n"
            . "They may be queried and removed per-table using the 'dolt_constraint_violations_TABLENAME' system table.";
    }

    /**
     * Render the merge-state guidance printed by upstream `dolt status`.
     *
     * @param list<string|array{name:string, numConflicts?:int}> $dataConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $schemaConflictTables
     * @param list<string> $constraintViolationTables
     */
    public function statusGuidance(
        bool $isMerging,
        array $dataConflictTables = [],
        array $schemaConflictTables = [],
        array $constraintViolationTables = [],
    ): ?string {
        if (!$isMerging) {
            return null;
        }

        $conflictRows = $this->conflictRows($dataConflictTables, $schemaConflictTables);
        $constraintTables = $this->uniqueTableNames($constraintViolationTables);

        if ($conflictRows === [] && $constraintTables === []) {
            return self::ALL_MERGED_HEADER;
        }

        $lines = [$this->unmergedTablesHeader($conflictRows !== [], $constraintTables !== []), ''];
        foreach ($this->unmergedPathLines($dataConflictTables, $schemaConflictTables, $constraintTables) as $line) {
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Render the unresolved-path block upstream `dolt commit` adds when a
     * commit is blocked by unresolved conflicts or constraint violations.
     *
     * @param list<string|array{name:string, numConflicts?:int}> $dataConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $schemaConflictTables
     * @param list<string> $constraintViolationTables
     */
    public function commitUnmergedPaths(
        array $dataConflictTables = [],
        array $schemaConflictTables = [],
        array $constraintViolationTables = [],
    ): ?string {
        $constraintTables = $this->uniqueTableNames($constraintViolationTables);
        $lines = $this->unmergedPathLines($dataConflictTables, $schemaConflictTables, $constraintTables);
        if ($lines === []) {
            return null;
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> ...$groups
     * @return list<non-empty-string>
     */
    private function uniqueTableNames(array ...$groups): array
    {
        $names = [];
        $seen = [];
        foreach ($groups as $group) {
            foreach ($group as $tableName) {
                if (!is_string($tableName) || $tableName === '') {
                    throw new \InvalidArgumentException('Dolt merge status table names must be non-empty strings.');
                }
                if (isset($seen[$tableName])) {
                    continue;
                }
                $seen[$tableName] = true;
                $names[] = $tableName;
            }
        }

        return $names;
    }

    /**
     * @param list<non-empty-string> $names
     */
    private function tableNamesAsString(array $names): string
    {
        return implode(', ', $names);
    }

    /**
     * @param list<string|array{name:string, numConflicts?:int}> $conflictTables
     * @param list<string> $resolvedTables
     * @return list<array{table:string,num_conflicts:int}>
     */
    private function unresolvedConflictRows(array $conflictTables, array $resolvedTables): array
    {
        $resolved = [];
        foreach ($this->uniqueTableNames($resolvedTables) as $tableName) {
            $resolved[$tableName] = true;
        }

        $remaining = [];
        foreach ($this->conflictRows($conflictTables) as $row) {
            if (isset($resolved[$row['table']])) {
                continue;
            }
            $remaining[] = $row;
        }

        return $remaining;
    }

    /**
     * @param list<string> $tableNames
     * @param list<string> $resolvedTables
     * @return list<non-empty-string>
     */
    private function unresolvedTableNames(array $tableNames, array $resolvedTables): array
    {
        $resolved = [];
        foreach ($this->uniqueTableNames($resolvedTables) as $tableName) {
            $resolved[$tableName] = true;
        }

        $remaining = [];
        foreach ($this->uniqueTableNames($tableNames) as $tableName) {
            if (isset($resolved[$tableName])) {
                continue;
            }
            $remaining[] = $tableName;
        }

        return $remaining;
    }

    /**
     * @param list<array{table:string,num_conflicts:int}> $rows
     * @return list<array{name:string,numConflicts:int}>
     */
    private function conflictRowsToItems(array $rows): array
    {
        return array_map(
            static fn (array $row): array => ['name' => $row['table'], 'numConflicts' => $row['num_conflicts']],
            $rows
        );
    }

    /**
     * @param list<array{table:string, num_conflicts:int}> $conflictRows
     * @param list<non-empty-string> $constraintViolationTables
     * @return list<non-empty-string>
     */
    private function uniqueUnmergedNames(array $conflictRows, array $constraintViolationTables): array
    {
        $names = [];
        $seen = [];
        foreach ($conflictRows as $row) {
            $tableName = $row['table'];
            if ($tableName === '') {
                throw new \InvalidArgumentException('Dolt merge conflict rows must include a non-empty table name.');
            }
            if (isset($seen[$tableName])) {
                continue;
            }
            $seen[$tableName] = true;
            $names[] = $tableName;
        }

        foreach ($constraintViolationTables as $tableName) {
            if (isset($seen[$tableName])) {
                continue;
            }
            $seen[$tableName] = true;
            $names[] = $tableName;
        }

        return $names;
    }

    /**
     * @param string|array{name:string, numConflicts?:int} $item
     * @return array{name:non-empty-string, numConflicts:int}
     */
    private function normalizeConflictItem(string|array $item): array
    {
        if (is_string($item)) {
            if ($item === '') {
                throw new \InvalidArgumentException('Dolt conflict table names must be non-empty strings.');
            }

            return ['name' => $item, 'numConflicts' => 0];
        }

        $name = $item['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('Dolt conflict rows must include a non-empty table name.');
        }

        $count = $item['numConflicts'] ?? 0;
        if (!is_int($count) || $count < 0) {
            throw new \InvalidArgumentException("Dolt conflict count for {$name} must be a non-negative integer.");
        }

        return ['name' => $name, 'numConflicts' => $count];
    }

    private function rootObjectString(mixed $value, string $field): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("Dolt {$field} must be a non-empty string.");
        }

        return $value;
    }

    private function rootObjectDefinition(mixed $value): string
    {
        if ($value === null) {
            return '<deleted>';
        }
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('Dolt root object conflict definitions must be non-empty strings or null.');
        }

        return $value;
    }

    private function unmergedTablesHeader(bool $hasConflicts, bool $hasConstraintViolations): string
    {
        $fix = match (true) {
            $hasConflicts && $hasConstraintViolations => 'conflicts and constraint violations',
            $hasConflicts => 'conflicts',
            default => 'constraint violations',
        };

        return "You have unmerged tables.\n"
            . "  (fix {$fix} and run \"dolt commit\")\n"
            . "  (use \"dolt merge --abort\" to abort the merge)";
    }

    /**
     * @param list<string|array{name:string, numConflicts?:int}> $dataConflictTables
     * @param list<string|array{name:string, numConflicts?:int}> $schemaConflictTables
     * @param list<non-empty-string> $constraintViolationTables
     * @return list<string>
     */
    private function unmergedPathLines(
        array $dataConflictTables,
        array $schemaConflictTables,
        array $constraintViolationTables,
    ): array {
        $schemaRows = $this->conflictRows([], $schemaConflictTables);
        $dataRows = $this->conflictRows($dataConflictTables);
        $hasConflictByTable = [];
        $lines = [];

        foreach ($schemaRows as $row) {
            $hasConflictByTable[$row['table']] = true;
            $lines[] = $this->statusLine('schema conflict:', $row['table']);
        }
        foreach ($dataRows as $row) {
            $hasConflictByTable[$row['table']] = true;
            $lines[] = $this->statusLine('both modified:', $row['table']);
        }

        $violationOnly = [];
        foreach ($constraintViolationTables as $tableName) {
            if (!isset($hasConflictByTable[$tableName])) {
                $violationOnly[] = $tableName;
            }
        }
        sort($violationOnly, SORT_STRING);

        foreach ($violationOnly as $tableName) {
            $lines[] = $this->statusLine('modified', $tableName);
        }

        if ($lines === []) {
            return [];
        }

        return array_merge([self::UNMERGED_PATHS_HEADER, self::UNMERGED_PATHS_HELP], $lines);
    }

    private function statusLine(string $label, string $tableName): string
    {
        return "\t" . str_pad($label, 18, ' ', STR_PAD_RIGHT) . $tableName;
    }

    /**
     * @param array<string,array{data:bool,schema:bool,constraint:bool,root:bool}> $stats
     * @param list<non-empty-string> $order
     */
    private function markArtifact(array &$stats, array &$order, string $tableName, string $kind): void
    {
        if (!isset($stats[$tableName])) {
            $stats[$tableName] = [
                'data' => false,
                'schema' => false,
                'constraint' => false,
                'root' => false,
            ];
            $order[] = $tableName;
        }

        $stats[$tableName][$kind] = true;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{table:non-empty-string,operation:string,adds:int,modifications:int,deletes:int,dataConflicts:int,constraintViolations:int}
     */
    private function normalizeMergeStat(array $row): array
    {
        $tableName = $row['table'] ?? $row['name'] ?? null;
        if (!is_string($tableName) || $tableName === '') {
            throw new \InvalidArgumentException('Dolt merge stats must include a non-empty table name.');
        }

        $operation = $row['operation'] ?? self::OPERATION_MODIFIED;
        if (!is_string($operation) || $operation === '') {
            throw new \InvalidArgumentException("Dolt merge operation for {$tableName} must be a non-empty string.");
        }
        $operation = strtolower($operation);
        $operation = match ($operation) {
            self::OPERATION_ADDED => self::OPERATION_ADDED,
            self::OPERATION_DELETED, 'dropped', 'removed' => self::OPERATION_DELETED,
            self::OPERATION_MODIFIED => self::OPERATION_MODIFIED,
            self::OPERATION_UNMODIFIED => self::OPERATION_UNMODIFIED,
            default => throw new \InvalidArgumentException("Unsupported Dolt merge operation {$operation} for {$tableName}."),
        };

        return [
            'table' => $tableName,
            'operation' => $operation,
            'adds' => $this->nonNegativeInt($row['rows_added'] ?? $row['adds'] ?? 0, "Dolt merge rows_added for {$tableName}"),
            'modifications' => $this->nonNegativeInt($row['rows_modified'] ?? $row['modifications'] ?? 0, "Dolt merge rows_modified for {$tableName}"),
            'deletes' => $this->nonNegativeInt($row['rows_deleted'] ?? $row['deletes'] ?? 0, "Dolt merge rows_deleted for {$tableName}"),
            'dataConflicts' => $this->nonNegativeInt($row['data_conflicts'] ?? $row['dataConflicts'] ?? 0, "Dolt merge data_conflicts for {$tableName}"),
            'constraintViolations' => $this->nonNegativeInt($row['constraint_violations'] ?? $row['constraintViolations'] ?? 0, "Dolt merge constraint_violations for {$tableName}"),
        ];
    }

    /**
     * @param array{adds:int,modifications:int,deletes:int} $stat
     */
    private function visualizeMergeChanges(array $stat, int $maxModCount): string
    {
        return $this->mergeChangeBar('+', $stat['adds'], $maxModCount)
            . $this->mergeChangeBar('*', $stat['modifications'], $maxModCount)
            . $this->mergeChangeBar('-', $stat['deletes'], $maxModCount);
    }

    private function mergeChangeBar(string $char, int $count, int $maxModCount): string
    {
        if ($count <= 0 || $maxModCount <= 0) {
            return '';
        }

        $length = (int) floor(30 * ($count / $maxModCount));
        if ($length > $count) {
            $length = $count;
        }
        if ($length === 0) {
            $length = 1;
        }

        return str_repeat($char, $length);
    }

    private function nonNegativeInt(mixed $value, string $description): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("{$description} must be a non-negative integer.");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $options
     */
    private function mergeProcedureFlagError(array $options): ?string
    {
        if ($this->boolOption($options, 'commit') && $this->boolOption($options, 'noCommit')) {
            return self::MERGE_COMMIT_NO_COMMIT_ERROR;
        }

        return $this->mergeFlagError($options);
    }

    /**
     * @param array<string,mixed> $options
     */
    private function mergeProcedureConflictIndicator(array $options): int
    {
        if (array_key_exists('hasConflicts', $options)) {
            $hasConflicts = $options['hasConflicts'];
            if (!is_bool($hasConflicts)) {
                throw new \InvalidArgumentException('Dolt merge procedure hasConflicts option must be a boolean.');
            }

            return $hasConflicts ? 1 : 0;
        }

        $conflicts = $this->nonNegativeInt($options['conflicts'] ?? 0, 'Dolt merge procedure conflicts column');
        if ($conflicts > 1) {
            throw new \InvalidArgumentException('Dolt merge procedure conflicts column is a 0/1 indicator.');
        }

        return $conflicts;
    }

    private function procedureMessage(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Dolt merge procedure message must be a string or null.');
        }

        return $value;
    }

    /**
     * @return array{hash:string, fast_forward:int, conflicts:int, message:string|null}
     */
    private function procedureRow(string $hash, int $fastForward, int $conflicts, ?string $message): array
    {
        return [
            'hash' => $hash,
            'fast_forward' => $fastForward,
            'conflicts' => $conflicts,
            'message' => $message,
        ];
    }

    /**
     * @param array<string,mixed> $options
     */
    private function boolOption(array $options, string $key): bool
    {
        return $this->optionalBoolOption($options, $key) ?? false;
    }

    /**
     * @param array<string,mixed> $options
     */
    private function optionalBoolOption(array $options, string $key): ?bool
    {
        if (!array_key_exists($key, $options)) {
            return null;
        }

        $value = $options[$key];
        if (!is_bool($value)) {
            throw new \InvalidArgumentException("Dolt merge transcript option {$key} must be a boolean.");
        }

        return $value;
    }

    private function optionalNonEmptyString(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("Dolt merge transcript {$field} must be a non-empty string.");
        }

        return $value;
    }

    private function incompatibleFlagError(string $left, string $right): string
    {
        return "error: Flags '--{$left}' and '--{$right}' cannot be used together";
    }
}
