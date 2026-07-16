<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class PreviewMergeConflictsTable
{
    public const DIFF_ADDED = 'added';
    public const DIFF_MODIFIED = 'modified';
    public const DIFF_REMOVED = 'removed';

    /**
     * Project rows returned by upstream `dolt_preview_merge_conflicts_summary`.
     *
     * When a table has schema conflicts, upstream returns `NULL` for
     * `num_data_conflicts` because the row-level preview cannot be calculated.
     *
     * @param list<string|array<string,mixed>> $dataConflictTables
     * @param list<string|array<string,mixed>> $schemaConflictTables
     * @return list<array{table:string, num_data_conflicts:int|null, num_schema_conflicts:int}>
     */
    public function summaryRows(array $dataConflictTables = [], array $schemaConflictTables = []): array
    {
        $stats = [];
        $order = [];

        foreach ($dataConflictTables as $item) {
            $conflict = $this->normalizeSummaryItem($item, 'data');
            if ($conflict['count'] === 0) {
                continue;
            }
            $this->markSummary($stats, $order, $conflict['table'], 'data', $conflict['count']);
        }

        foreach ($schemaConflictTables as $item) {
            $conflict = $this->normalizeSummaryItem($item, 'schema');
            if ($conflict['count'] === 0) {
                continue;
            }
            $this->markSummary($stats, $order, $conflict['table'], 'schema', $conflict['count']);
        }

        $rows = [];
        foreach ($order as $tableName) {
            $row = $stats[$tableName];
            $rows[] = [
                'table' => $tableName,
                'num_data_conflicts' => $row['schema'] > 0 ? null : $row['data'],
                'num_schema_conflicts' => $row['schema'],
            ];
        }

        return $rows;
    }

    /**
     * Project rows returned by upstream `dolt_preview_merge_conflicts`.
     *
     * This covers keyed table data conflicts: divergent modify/modify,
     * add/add, and delete/modify rows. Schema conflict handling is represented
     * by the upstream error boundary, because the data preview cannot be
     * calculated when `dolt_preview_merge_conflicts_summary` reports schema
     * conflicts for the table.
     *
     * @param list<array<string, scalar|null>> $baseRows
     * @param list<array<string, scalar|null>> $ourRows
     * @param list<array<string, scalar|null>> $theirRows
     * @param non-empty-string|list<non-empty-string> $primaryKey
     * @param list<non-empty-string>|null $columns
     * @return list<array<string, scalar|null>>
     */
    public function conflictRows(
        array $baseRows,
        array $ourRows,
        array $theirRows,
        string|array $primaryKey,
        ?array $columns = null,
        string $theirRootish = 'THEIRS',
        int $schemaConflictCount = 0,
    ): array {
        if ($schemaConflictCount < 0) {
            throw new \InvalidArgumentException('Dolt preview schema conflict count must be a non-negative integer.');
        }
        if ($schemaConflictCount > 0) {
            throw new \InvalidArgumentException($this->schemaConflictError($schemaConflictCount));
        }
        if ($theirRootish === '') {
            throw new \InvalidArgumentException('Dolt preview from_root_ish must be a non-empty string.');
        }

        $primaryKey = $this->normalizePrimaryKey($primaryKey);
        $columns = $columns === null
            ? $this->inferColumns($baseRows, $ourRows, $theirRows)
            : $this->validateColumns($columns);

        $base = $this->index($baseRows, $primaryKey);
        $ours = $this->index($ourRows, $primaryKey);
        $theirs = $this->index($theirRows, $primaryKey);

        $rows = [];
        foreach ($this->orderedKeys($base, $ours, $theirs) as $key) {
            $baseRow = $base[$key] ?? null;
            $ourRow = $ours[$key] ?? null;
            $theirRow = $theirs[$key] ?? null;

            $ourDiff = $this->diffType($baseRow, $ourRow);
            $theirDiff = $this->diffType($baseRow, $theirRow);
            if ($ourDiff === null || $theirDiff === null || $this->rowsSame($ourRow, $theirRow)) {
                continue;
            }

            $rows[] = $this->formatConflictRow(
                $key,
                $theirRootish,
                $baseRow,
                $ourRow,
                $theirRow,
                $ourDiff,
                $theirDiff,
                $columns
            );
        }

        return $rows;
    }

    public function schemaConflictError(int $schemaConflictCount): string
    {
        if ($schemaConflictCount < 0) {
            throw new \InvalidArgumentException('Dolt preview schema conflict count must be a non-negative integer.');
        }

        return "schema conflicts found: {$schemaConflictCount}";
    }

    /**
     * Project rows shaped like upstream `dolt_schema_conflicts`.
     *
     * @param list<array<string,mixed>> $conflicts
     * @return list<array{table_name:string, base_schema:string, our_schema:string, their_schema:string, description:string}>
     */
    public function schemaConflictRows(array $conflicts): array
    {
        $rows = [];
        foreach ($conflicts as $conflict) {
            $tableName = $this->requiredString($conflict, ['table_name', 'table', 'name'], 'schema conflict table name');
            $baseSchema = $this->schemaText($conflict['base_schema'] ?? $conflict['baseSchema'] ?? null);
            $ourSchema = $this->schemaText($conflict['our_schema'] ?? $conflict['ourSchema'] ?? null);
            $theirSchema = $this->schemaText($conflict['their_schema'] ?? $conflict['theirSchema'] ?? null);
            $description = $conflict['description'] ?? null;

            if ($description === null) {
                $description = $this->schemaConflictDescription($conflict);
            }
            if (!is_string($description) || $description === '') {
                throw new \InvalidArgumentException("Dolt schema conflict for {$tableName} must include a non-empty description.");
            }

            $rows[] = [
                'table_name' => $tableName,
                'base_schema' => $baseSchema,
                'our_schema' => $ourSchema,
                'their_schema' => $theirSchema,
                'description' => $description,
            ];
        }

        return $rows;
    }

    /**
     * Project keyless/cardinality conflicts returned by upstream
     * `dolt_preview_merge_conflicts`.
     *
     * Keyless tables compare duplicate row cardinality instead of primary-key
     * identity. Dolt exposes the synthetic `dolt_row_hash` so clients can
     * distinguish duplicate-value groups without inventing a WordPress key.
     *
     * @param list<array<string, scalar|null>> $baseRows
     * @param list<array<string, scalar|null>> $ourRows
     * @param list<array<string, scalar|null>> $theirRows
     * @param list<non-empty-string>|null $columns
     * @return list<array<string, scalar|null>>
     */
    public function keylessConflictRows(
        array $baseRows,
        array $ourRows,
        array $theirRows,
        ?array $columns = null,
        string $theirRootish = 'THEIRS',
        int $schemaConflictCount = 0,
    ): array {
        if ($schemaConflictCount < 0) {
            throw new \InvalidArgumentException('Dolt preview schema conflict count must be a non-negative integer.');
        }
        if ($schemaConflictCount > 0) {
            throw new \InvalidArgumentException($this->schemaConflictError($schemaConflictCount));
        }
        if ($theirRootish === '') {
            throw new \InvalidArgumentException('Dolt preview from_root_ish must be a non-empty string.');
        }

        $columns = $columns === null
            ? $this->inferColumns($baseRows, $ourRows, $theirRows)
            : $this->validateColumns($columns);

        $base = $this->cardinalityIndex($baseRows, $columns);
        $ours = $this->cardinalityIndex($ourRows, $columns);
        $theirs = $this->cardinalityIndex($theirRows, $columns);

        $rows = [];
        foreach ($this->orderedKeys($base, $ours, $theirs) as $key) {
            $baseGroup = $base[$key] ?? null;
            $ourGroup = $ours[$key] ?? null;
            $theirGroup = $theirs[$key] ?? null;

            $baseCount = $baseGroup['count'] ?? 0;
            $ourCount = $ourGroup['count'] ?? 0;
            $theirCount = $theirGroup['count'] ?? 0;
            if ($ourCount === $baseCount || $theirCount === $baseCount || $ourCount === $theirCount) {
                continue;
            }

            $rowValues = ($ourGroup ?? $theirGroup ?? $baseGroup)['row'];
            $rows[] = $this->formatKeylessConflictRow(
                $key,
                $theirRootish,
                $rowValues,
                $baseCount,
                $ourCount,
                $theirCount,
                $columns,
            );
        }

        return $rows;
    }

    /**
     * @param array<string,array{data:int,schema:int}> $stats
     * @param list<non-empty-string> $order
     */
    private function markSummary(array &$stats, array &$order, string $tableName, string $kind, int $count): void
    {
        if (!isset($stats[$tableName])) {
            $stats[$tableName] = ['data' => 0, 'schema' => 0];
            $order[] = $tableName;
        }

        $stats[$tableName][$kind] += $count;
    }

    /**
     * @param string|array<string,mixed> $item
     * @return array{table:non-empty-string, count:int}
     */
    private function normalizeSummaryItem(string|array $item, string $kind): array
    {
        if (is_string($item)) {
            if ($item === '') {
                throw new \InvalidArgumentException('Dolt preview conflict table names must be non-empty strings.');
            }

            return ['table' => $item, 'count' => 1];
        }

        $tableName = $item['table'] ?? $item['name'] ?? null;
        if (!is_string($tableName) || $tableName === '') {
            throw new \InvalidArgumentException('Dolt preview conflict rows must include a non-empty table name.');
        }

        $candidateKeys = $kind === 'schema'
            ? ['num_schema_conflicts', 'numSchemaConflicts', 'num_conflicts', 'numConflicts', 'count']
            : ['num_data_conflicts', 'numDataConflicts', 'num_conflicts', 'numConflicts', 'count'];

        $count = 1;
        foreach ($candidateKeys as $key) {
            if (array_key_exists($key, $item)) {
                $count = $item[$key];
                break;
            }
        }
        if (!is_int($count) || $count < 0) {
            throw new \InvalidArgumentException("Dolt preview conflict count for {$tableName} must be a non-negative integer.");
        }

        return ['table' => $tableName, 'count' => $count];
    }

    /**
     * @param array<string,mixed> $row
     * @param list<non-empty-string> $keys
     */
    private function requiredString(array $row, array $keys, string $label): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $value = $row[$key];
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        throw new \InvalidArgumentException("Dolt {$label} must be a non-empty string.");
    }

    private function schemaText(mixed $schema): string
    {
        if ($schema === null) {
            return '<deleted>';
        }
        if (!is_string($schema) || $schema === '') {
            throw new \InvalidArgumentException('Dolt schema conflict schema text must be a non-empty string or null.');
        }

        return $schema;
    }

    /**
     * @param array<string,mixed> $conflict
     */
    private function schemaConflictDescription(array $conflict): string
    {
        if (($conflict['modify_delete_conflict'] ?? $conflict['modifyDeleteConflict'] ?? false) === true) {
            return 'table was modified in one branch and deleted in the other';
        }

        $messages = [];
        foreach (($conflict['column_conflicts'] ?? $conflict['columnConflicts'] ?? []) as $columnConflict) {
            if (!is_array($columnConflict)) {
                throw new \InvalidArgumentException('Dolt column schema conflicts must be arrays.');
            }
            $messages[] = $this->columnConflictDescription($columnConflict);
        }
        foreach (($conflict['index_conflicts'] ?? $conflict['indexConflicts'] ?? []) as $indexConflict) {
            if (!is_array($indexConflict)) {
                throw new \InvalidArgumentException('Dolt index schema conflicts must be arrays.');
            }
            $messages[] = $this->indexConflictDescription($indexConflict);
        }
        foreach (($conflict['check_conflicts'] ?? $conflict['checkConflicts'] ?? []) as $checkConflict) {
            if (!is_array($checkConflict)) {
                throw new \InvalidArgumentException('Dolt check schema conflicts must be arrays.');
            }
            $messages[] = $this->checkConflictDescription($checkConflict);
        }

        $messages = array_values(array_filter($messages, static fn (string $message): bool => $message !== ''));
        if ($messages === []) {
            throw new \InvalidArgumentException('Dolt schema conflict description could not be derived from an empty conflict set.');
        }

        return implode("\n", $messages);
    }

    /**
     * @param array<string,mixed> $conflict
     */
    private function columnConflictDescription(array $conflict): string
    {
        $kind = $conflict['kind'] ?? '';
        $ours = $conflict['ours'] ?? [];
        $theirs = $conflict['theirs'] ?? [];
        if (!is_array($ours) || !is_array($theirs)) {
            throw new \InvalidArgumentException('Dolt column schema conflict sides must be arrays.');
        }

        if ($kind === 'name_collision' || $kind === 'nameCollision') {
            $name = $this->requiredString($ours + $theirs, ['name'], 'column conflict name');
            $ourType = $ours['type'] ?? '';
            $theirType = $theirs['type'] ?? '';
            if (!is_string($ourType) || $ourType === '' || !is_string($theirType) || $theirType === '') {
                throw new \InvalidArgumentException("Dolt column schema conflict for {$name} must include both column types.");
            }

            return "incompatible column types for column '{$name}': {$ourType} and {$theirType}";
        }
        if ($kind === 'tag_collision' || $kind === 'tagCollision') {
            $ourName = $this->requiredString($ours, ['name'], 'our column conflict name');
            $theirName = $this->requiredString($theirs, ['name'], 'their column conflict name');

            return "different column definitions for our column {$ourName} and their column {$theirName}";
        }

        throw new \InvalidArgumentException("Unsupported Dolt column schema conflict kind: {$kind}");
    }

    /**
     * @param array<string,mixed> $conflict
     */
    private function indexConflictDescription(array $conflict): string
    {
        $kind = $conflict['kind'] ?? '';
        if ($kind === 'duplicateIndexColumnSet') {
            $kind = 'duplicate_index_column_set';
        }
        if ($kind !== 'duplicate_index_column_set') {
            throw new \InvalidArgumentException("Unsupported Dolt index schema conflict kind: {$kind}");
        }

        $ours = $conflict['ours'] ?? [];
        $theirs = $conflict['theirs'] ?? [];
        if (!is_array($ours) || !is_array($theirs)) {
            throw new \InvalidArgumentException('Dolt index schema conflict sides must be arrays.');
        }

        return sprintf(
            "multiple indexes covering the same column set cannot be merged: '%s' and '%s'",
            $this->requiredString($ours, ['name'], 'our index conflict name'),
            $this->requiredString($theirs, ['name'], 'their index conflict name'),
        );
    }

    /**
     * @param array<string,mixed> $conflict
     */
    private function checkConflictDescription(array $conflict): string
    {
        $kind = $conflict['kind'] ?? '';
        $ours = $conflict['ours'] ?? [];
        $theirs = $conflict['theirs'] ?? [];
        if (!is_array($ours) || !is_array($theirs)) {
            throw new \InvalidArgumentException('Dolt check schema conflict sides must be arrays.');
        }

        if ($kind === 'name_collision' || $kind === 'nameCollision') {
            return sprintf(
                "two checks with the name '%s' but different definitions",
                $this->requiredString($ours + $theirs, ['name'], 'check conflict name'),
            );
        }
        if ($kind === 'column_check_collision' || $kind === 'columnCheckCollision') {
            return sprintf(
                "our check '%s' and their check '%s' both reference the same column(s)",
                $this->requiredString($ours, ['name'], 'our check conflict name'),
                $this->requiredString($theirs, ['name'], 'their check conflict name'),
            );
        }
        if ($kind === 'invalid_check_collision' || $kind === 'invalidCheckCollision') {
            return sprintf(
                "check '%s' references a column that will be deleted after merge",
                $this->requiredString($ours + $theirs, ['name'], 'check conflict name'),
            );
        }
        if ($kind === 'deleted_check_collision' || $kind === 'deletedCheckCollision') {
            if ($theirs === []) {
                return sprintf(
                    "check '%s' was deleted in theirs but modified in ours",
                    $this->requiredString($ours, ['name'], 'our check conflict name'),
                );
            }

            return sprintf(
                "check '%s' was deleted in ours but modified in theirs",
                $this->requiredString($theirs, ['name'], 'their check conflict name'),
            );
        }

        throw new \InvalidArgumentException("Unsupported Dolt check schema conflict kind: {$kind}");
    }

    /**
     * @param non-empty-string|list<non-empty-string> $primaryKey
     * @return list<non-empty-string>
     */
    private function normalizePrimaryKey(string|array $primaryKey): array
    {
        $columns = is_string($primaryKey) ? [$primaryKey] : $primaryKey;
        if ($columns === []) {
            throw new \InvalidArgumentException('At least one primary key column is required.');
        }

        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('Primary key columns must be non-empty strings.');
            }
        }

        return array_values($columns);
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @param list<non-empty-string> $primaryKey
     * @return array<string, array<string, scalar|null>>
     */
    private function index(array $rows, array $primaryKey): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $key = $this->rowKey($row, $primaryKey);
            if (array_key_exists($key, $indexed)) {
                throw new \InvalidArgumentException('Duplicate primary key in Dolt preview row set: ' . implode(', ', $primaryKey));
            }
            $indexed[$key] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<string, scalar|null> $row
     * @param list<non-empty-string> $primaryKey
     */
    private function rowKey(array $row, array $primaryKey): string
    {
        $values = [];
        foreach ($primaryKey as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("Row is missing primary key: {$column}");
            }
            if ($row[$column] === null) {
                throw new \InvalidArgumentException("Primary key column cannot be null: {$column}");
            }
            $values[$column] = $row[$column];
        }

        return json_encode($values, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, array<string, scalar|null>> ...$groups
     * @return list<string>
     */
    private function orderedKeys(array ...$groups): array
    {
        $keys = [];
        foreach ($groups as $group) {
            foreach (array_keys($group) as $key) {
                $keys[$key] = true;
            }
        }

        $keys = array_keys($keys);
        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * @param array<string, scalar|null>|null $base
     * @param array<string, scalar|null>|null $row
     */
    private function diffType(?array $base, ?array $row): ?string
    {
        if ($base === null && $row === null) {
            return null;
        }
        if ($base === null) {
            return self::DIFF_ADDED;
        }
        if ($row === null) {
            return self::DIFF_REMOVED;
        }
        if ($this->rowsSame($base, $row)) {
            return null;
        }

        return self::DIFF_MODIFIED;
    }

    /**
     * @param array<string, scalar|null>|null $left
     * @param array<string, scalar|null>|null $right
     */
    private function rowsSame(?array $left, ?array $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }
        if (count($left) !== count($right)) {
            return false;
        }
        foreach ($left as $column => $value) {
            if (!array_key_exists($column, $right) || $right[$column] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, scalar|null>> ...$rowSets
     * @return list<non-empty-string>
     */
    private function inferColumns(array ...$rowSets): array
    {
        $columns = [];
        foreach ($rowSets as $rows) {
            foreach ($rows as $row) {
                foreach (array_keys($row) as $column) {
                    if ($column !== '' && !isset($columns[$column])) {
                        $columns[$column] = true;
                    }
                }
            }
        }

        return $this->validateColumns(array_keys($columns));
    }

    /**
     * @param list<non-empty-string> $columns
     * @return list<non-empty-string>
     */
    private function validateColumns(array $columns): array
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('At least one Dolt preview conflict column is required.');
        }

        $seen = [];
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('Dolt preview conflict columns must be non-empty strings.');
            }
            if (isset($seen[$column])) {
                throw new \InvalidArgumentException("Duplicate Dolt preview conflict column: {$column}");
            }
            $seen[$column] = true;
        }

        return array_values($columns);
    }

    /**
     * @param array<string, scalar|null>|null $baseRow
     * @param array<string, scalar|null>|null $ourRow
     * @param array<string, scalar|null>|null $theirRow
     * @param list<non-empty-string> $columns
     * @return array<string, scalar|null>
     */
    private function formatConflictRow(
        string $key,
        string $theirRootish,
        ?array $baseRow,
        ?array $ourRow,
        ?array $theirRow,
        string $ourDiff,
        string $theirDiff,
        array $columns,
    ): array {
        $row = ['from_root_ish' => $theirRootish];

        foreach ($columns as $column) {
            $row["base_{$column}"] = $baseRow[$column] ?? null;
        }
        foreach ($columns as $column) {
            $row["our_{$column}"] = $ourRow[$column] ?? null;
        }
        $row['our_diff_type'] = $ourDiff;
        foreach ($columns as $column) {
            $row["their_{$column}"] = $theirRow[$column] ?? null;
        }
        $row['their_diff_type'] = $theirDiff;
        $row['dolt_conflict_id'] = $this->conflictId($key, $theirRootish);

        return $row;
    }

    private function conflictId(string $key, string $theirRootish): string
    {
        return rtrim(base64_encode(substr(hash('sha256', $key . "\0" . $theirRootish, true), 0, 16)), '=');
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @param list<non-empty-string> $columns
     * @return array<string, array{row: array<string, scalar|null>, count: int}>
     */
    private function cardinalityIndex(array $rows, array $columns): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $column) {
                $values[$column] = $row[$column] ?? null;
            }

            $key = json_encode($values, JSON_THROW_ON_ERROR);
            if (!isset($indexed[$key])) {
                $indexed[$key] = ['row' => $values, 'count' => 0];
            }
            $indexed[$key]['count']++;
        }

        return $indexed;
    }

    /**
     * @param array<string, scalar|null> $rowValues
     * @param list<non-empty-string> $columns
     * @return array<string, scalar|null>
     */
    private function formatKeylessConflictRow(
        string $key,
        string $theirRootish,
        array $rowValues,
        int $baseCount,
        int $ourCount,
        int $theirCount,
        array $columns,
    ): array {
        $row = ['from_root_ish' => $theirRootish];
        foreach ($columns as $column) {
            $row["base_{$column}"] = $baseCount > 0 ? $rowValues[$column] : null;
        }
        $row['base_cardinality'] = $baseCount;
        foreach ($columns as $column) {
            $row["our_{$column}"] = $ourCount > 0 ? $rowValues[$column] : null;
        }
        $row['our_cardinality'] = $ourCount;
        $row['our_diff_type'] = $this->cardinalityDiffType($baseCount, $ourCount);
        foreach ($columns as $column) {
            $row["their_{$column}"] = $theirCount > 0 ? $rowValues[$column] : null;
        }
        $row['their_cardinality'] = $theirCount;
        $row['their_diff_type'] = $this->cardinalityDiffType($baseCount, $theirCount);
        $row['dolt_row_hash'] = substr(hash('sha256', $key), 0, 32);
        $row['dolt_conflict_id'] = $this->conflictId($key, $theirRootish);

        return $row;
    }

    private function cardinalityDiffType(int $baseCount, int $rowCount): string
    {
        if ($baseCount === 0) {
            return self::DIFF_ADDED;
        }
        if ($rowCount === 0) {
            return self::DIFF_REMOVED;
        }

        return self::DIFF_MODIFIED;
    }
}
