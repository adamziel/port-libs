<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class ConstraintViolationsTable
{
    public const UNRESOLVED_CONSTRAINT_VIOLATIONS_ERROR = 'Committing this transaction resulted in a working set with constraint violations, transaction rolled back. This constraint violation may be the result of a previous merge or the result of transaction sequencing. Constraint violations from a merge can be resolved using the dolt_constraint_violations table before committing the transaction. To allow transactions to be committed with constraint violations from a merge or transaction sequencing set @@dolt_force_transaction_commit=1.';
    public const CONSTRAINT_VIOLATIONS_LIST_PREFIX = "\nConstraint violations: ";
    public const TYPE_FOREIGN_KEY = 'foreign key';
    public const TYPE_UNIQUE_INDEX = 'unique index';
    public const TYPE_CHECK_CONSTRAINT = 'check constraint';
    public const TYPE_NOT_NULL = 'not null';

    private const VALID_TYPES = [
        self::TYPE_FOREIGN_KEY => true,
        self::TYPE_UNIQUE_INDEX => true,
        self::TYPE_CHECK_CONSTRAINT => true,
        self::TYPE_NOT_NULL => true,
    ];

    /**
     * Project the table-of-tables shape returned by upstream
     * `dolt_constraint_violations`.
     *
     * @param array<string, list<array<string, mixed>>> $violationsByTable
     * @return list<array{table:string, num_violations:int}>
     */
    public function summaryRows(array $violationsByTable): array
    {
        $rows = [];
        foreach ($violationsByTable as $tableName => $violations) {
            if (!is_string($tableName) || $tableName === '') {
                throw new \InvalidArgumentException('Dolt constraint violation table names must be non-empty strings.');
            }
            if (!is_array($violations)) {
                throw new \InvalidArgumentException("Dolt constraint violations for {$tableName} must be a list.");
            }
            if ($violations === []) {
                continue;
            }

            $rows[] = [
                'table' => $tableName,
                'num_violations' => count($violations),
            ];
        }

        return $rows;
    }

    /**
     * Render the transaction error Dolt returns when a merge creates unresolved
     * constraint violations while autocommit is active.
     *
     * @param array<string, list<array<string, mixed>>> $violationsByTable
     */
    public function unresolvedMergeError(array $violationsByTable): string
    {
        return self::UNRESOLVED_CONSTRAINT_VIOLATIONS_ERROR
            . self::CONSTRAINT_VIOLATIONS_LIST_PREFIX
            . $this->mergeViolationSummaryText($violationsByTable);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $violationsByTable
     */
    public function mergeViolationSummaryText(array $violationsByTable): string
    {
        $chunks = [];
        foreach ($violationsByTable as $tableName => $violations) {
            if (!is_string($tableName) || $tableName === '') {
                throw new \InvalidArgumentException('Dolt constraint violation table names must be non-empty strings.');
            }
            if (!is_array($violations)) {
                throw new \InvalidArgumentException("Dolt constraint violations for {$tableName} must be a list.");
            }
            if ($violations === []) {
                continue;
            }

            $countByDescription = [];
            foreach (array_values($violations) as $violation) {
                if (!is_array($violation)) {
                    throw new \InvalidArgumentException('Dolt constraint violations must contain row arrays.');
                }

                $description = $this->mergeViolationDescription($violation);
                $countByDescription[$description] = ($countByDescription[$description] ?? 0) + 1;
            }

            $descriptions = array_keys($countByDescription);
            sort($descriptions, SORT_STRING);

            $chunk = '';
            foreach ($descriptions as $description) {
                $chunk .= $description;
                $rowCount = $countByDescription[$description];
                if ($rowCount > 1) {
                    $chunk .= " ({$rowCount} row(s))";
                }
            }

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
        }

        return implode(', ', $chunks);
    }

    /**
     * Evaluate a table's CHECK constraints and project rows in the shape of
     * `dolt_constraint_violations_<table>`.
     *
     * @param list<array<string, scalar|null>> $rows
     * @return list<array<string, mixed>>
     */
    public function checkConstraintRows(
        TableSchema $schema,
        array $rows,
        string $tableName,
        ?string $fromRootIsh = null,
    ): array {
        $violations = (new CheckConstraintValidator())->violations($schema, $rows, $tableName);

        return $this->rowsForTable($schema, $violations, $fromRootIsh);
    }

    /**
     * Project raw violation records into upstream per-table system table rows.
     *
     * The upstream schema is `from_root_ish`, `violation_type`, then either the
     * table primary-key columns or `dolt_row_hash` for keyless tables, followed
     * by all non-primary-key columns and `violation_info`.
     *
     * @param list<array<string, mixed>> $violations
     * @return list<array<string, mixed>>
     */
    public function rowsForTable(TableSchema $schema, array $violations, ?string $fromRootIsh = null): array
    {
        $this->validateFromRootIsh($fromRootIsh);

        $rows = [];
        foreach (array_values($violations) as $violation) {
            if (!is_array($violation)) {
                throw new \InvalidArgumentException('Dolt constraint violations must contain row arrays.');
            }

            $row = $violation['row'] ?? null;
            if (!is_array($row)) {
                throw new \InvalidArgumentException('Dolt constraint violation records must include a row array.');
            }

            $type = $this->violationType($violation);
            $rowFromRootIsh = $violation['from_root_ish'] ?? $fromRootIsh;
            $this->validateFromRootIsh($rowFromRootIsh);

            $projected = [
                'from_root_ish' => $rowFromRootIsh,
                'violation_type' => $type,
            ];

            if ($schema->isKeyless()) {
                $projected['dolt_row_hash'] = $this->keylessRowHash($violation);
                foreach ($schema->columns() as $column) {
                    $projected[$column['name']] = $row[$column['name']] ?? null;
                }
            } else {
                foreach ($schema->primaryKeyColumns() as $column) {
                    if (!array_key_exists($column['name'], $row) || $row[$column['name']] === null) {
                        throw new \InvalidArgumentException("Dolt constraint violation row is missing primary key {$column['name']}.");
                    }
                    $projected[$column['name']] = $row[$column['name']];
                }
                foreach ($schema->columns() as $column) {
                    if ($column['primaryKey']) {
                        continue;
                    }
                    $projected[$column['name']] = $row[$column['name']] ?? null;
                }
            }

            $projected['violation_info'] = $this->violationInfo($type, $violation);
            $rows[] = $projected;
        }

        return $rows;
    }

    /**
     * Delete rows from a projected `dolt_constraint_violations_<table>` view.
     *
     * Upstream deletes one artifact for each projected row matched by the SQL
     * DELETE predicate. For rows with multiple violations, the `violation_info`
     * hash is part of the artifact key, so a predicate that includes one
     * violation-info field removes only that violation while a row-key-only
     * predicate removes all violations on that row.
     *
     * Criteria keys are projected row column names such as `pk`,
     * `dolt_row_hash`, `violation_type`, or `from_root_ish`. The
     * `violation_info.<key>` shorthand models focused `JSON_EXTRACT` filters
     * such as `JSON_EXTRACT(violation_info, '$.Name') = 'ua'`. An empty
     * criteria array matches every violation row.
     *
     * @param list<array<string, mixed>> $violations
     * @param array<string, mixed> $criteria
     * @return array{rows_affected:int, deleted_rows:list<array<string, mixed>>, remaining_rows:list<array<string, mixed>>, remaining_violations:list<array<string, mixed>>}
     */
    public function deleteRowsForTable(
        TableSchema $schema,
        array $violations,
        array $criteria = [],
        ?string $fromRootIsh = null,
    ): array {
        $normalizedViolations = array_values($violations);
        $projectedRows = $this->rowsForTable($schema, $normalizedViolations, $fromRootIsh);
        $deletedRows = [];
        $remainingRows = [];
        $remainingViolations = [];

        foreach ($projectedRows as $i => $row) {
            if ($this->matchesDeleteCriteria($row, $criteria)) {
                $deletedRows[] = $row;
                continue;
            }

            $remainingRows[] = $row;
            $remainingViolations[] = $normalizedViolations[$i];
        }

        return [
            'rows_affected' => count($deletedRows),
            'deleted_rows' => $deletedRows,
            'remaining_rows' => $remainingRows,
            'remaining_violations' => $remainingViolations,
        ];
    }

    /**
     * @param array<string, mixed> $violation
     */
    private function violationType(array $violation): string
    {
        $type = $violation['violation_type'] ?? null;
        if (!is_string($type) || !isset(self::VALID_TYPES[$type])) {
            throw new \InvalidArgumentException('Dolt constraint violation type must be foreign key, unique index, check constraint, or not null.');
        }

        return $type;
    }

    /**
     * @param array<string, mixed> $violation
     * @return array<string, mixed>
     */
    private function violationInfo(string $type, array $violation): array
    {
        $info = $violation['violation_info'] ?? null;
        if (is_array($info)) {
            return $info;
        }

        return match ($type) {
            self::TYPE_FOREIGN_KEY => $this->foreignKeyInfo($violation),
            self::TYPE_CHECK_CONSTRAINT => $this->checkConstraintInfo($violation),
            self::TYPE_UNIQUE_INDEX => $this->uniqueIndexInfo($violation),
            self::TYPE_NOT_NULL => $this->notNullInfo($violation),
        };
    }

    /**
     * @param array<string, mixed> $violation
     */
    private function mergeViolationDescription(array $violation): string
    {
        $type = $this->violationType($violation);
        $info = $this->violationInfo($type, $violation);

        return match ($type) {
            self::TYPE_FOREIGN_KEY => $this->foreignKeyMergeDescription($info),
            self::TYPE_UNIQUE_INDEX => $this->uniqueIndexMergeDescription($info),
            self::TYPE_CHECK_CONSTRAINT => $this->checkConstraintMergeDescription($info),
            self::TYPE_NOT_NULL => $this->notNullMergeDescription($info),
        };
    }

    /**
     * @param array<string, mixed> $info
     */
    private function foreignKeyMergeDescription(array $info): string
    {
        $foreignKey = $this->requiredStringField($info, ['ForeignKey'], 'Dolt foreign key violation_info must include ForeignKey.');
        $table = $this->requiredStringField($info, ['Table'], 'Dolt foreign key violation_info must include Table.');
        $referencedTable = $this->requiredStringField($info, ['ReferencedTable'], 'Dolt foreign key violation_info must include ReferencedTable.');
        $index = $this->requiredStringField($info, ['Index'], 'Dolt foreign key violation_info must include Index.');
        $referencedIndex = $this->optionalStringField($info, ['ReferencedIndex'], true) ?? '';

        return "\nType: Foreign Key Constraint Violation\n"
            . "\tForeignKey: {$foreignKey},\n"
            . "\tTable: {$table},\n"
            . "\tReferencedTable: {$referencedTable},\n"
            . "\tIndex: {$index},\n"
            . "\tReferencedIndex: {$referencedIndex}";
    }

    /**
     * @param array<string, mixed> $info
     */
    private function uniqueIndexMergeDescription(array $info): string
    {
        $name = $this->requiredStringField($info, ['Name'], 'Dolt unique index violation_info must include Name.');
        $columns = $this->goStringList($this->stringList($info['Columns'] ?? null, 'Dolt unique index violation_info Columns'));

        return "\nType: Unique Key Constraint Violation,\n"
            . "\tName: {$name},\n"
            . "\tColumns: {$columns}";
    }

    /**
     * @param array<string, mixed> $info
     */
    private function checkConstraintMergeDescription(array $info): string
    {
        $name = $this->requiredStringField($info, ['Name'], 'Dolt check constraint violation_info must include Name.');
        $expression = $this->requiredStringField($info, ['Expression'], 'Dolt check constraint violation_info must include Expression.');

        return "\nType: Check Constraint Violation,\n"
            . "\tName: {$name},\n"
            . "\tExpression: {$expression}";
    }

    /**
     * @param array<string, mixed> $info
     */
    private function notNullMergeDescription(array $info): string
    {
        $columns = $this->goStringList($this->stringList($info['Columns'] ?? null, 'Dolt not-null violation_info Columns'));

        return "\nType: Null Constraint Violation,\n"
            . "\tColumns: {$columns}";
    }

    /**
     * @param array<string, mixed> $violation
     * @return array{Index:string, Table:string, Columns:list<string>, OnDelete:string, OnUpdate:string, ForeignKey:string, ReferencedIndex:string, ReferencedTable:string, ReferencedColumns:list<string>}
     */
    private function foreignKeyInfo(array $violation): array
    {
        $foreignKey = $this->requiredStringField(
            $violation,
            ['foreign_key', 'foreignKey', 'constraint_name', 'ForeignKey'],
            'Dolt foreign key violations must include foreign_key or violation_info.'
        );

        return [
            'Index' => $this->optionalStringField($violation, ['index_name', 'index', 'Index']) ?? $foreignKey,
            'Table' => $this->requiredStringField(
                $violation,
                ['table', 'Table'],
                'Dolt foreign key violations must include table or violation_info.'
            ),
            'Columns' => $this->stringList(
                $this->firstPresent($violation, ['columns', 'Columns']),
                'Dolt foreign key violation columns'
            ),
            'OnDelete' => $this->referentialAction(
                $this->firstPresent($violation, ['on_delete', 'onDelete', 'OnDelete']),
                'Dolt foreign key violation OnDelete'
            ),
            'OnUpdate' => $this->referentialAction(
                $this->firstPresent($violation, ['on_update', 'onUpdate', 'OnUpdate']),
                'Dolt foreign key violation OnUpdate'
            ),
            'ForeignKey' => $foreignKey,
            'ReferencedIndex' => $this->optionalStringField(
                $violation,
                ['referenced_index', 'referencedIndex', 'ReferencedIndex'],
                true
            ) ?? '',
            'ReferencedTable' => $this->requiredStringField(
                $violation,
                ['referenced_table', 'referencedTable', 'ReferencedTable'],
                'Dolt foreign key violations must include referenced_table or violation_info.'
            ),
            'ReferencedColumns' => $this->stringList(
                $this->firstPresent($violation, ['referenced_columns', 'referencedColumns', 'ReferencedColumns']),
                'Dolt foreign key violation referenced columns'
            ),
        ];
    }

    /**
     * @param array<string, mixed> $violation
     * @return array{Name:string, Expression:string}
     */
    private function checkConstraintInfo(array $violation): array
    {
        $name = $violation['constraint_name'] ?? null;
        $expression = $violation['expression'] ?? null;
        if (!is_string($name) || $name === '' || !is_string($expression) || $expression === '') {
            throw new \InvalidArgumentException('Dolt check constraint violations must include constraint_name and expression.');
        }

        return [
            'Name' => $name,
            'Expression' => $expression,
        ];
    }

    /**
     * @param array<string, mixed> $violation
     * @return array{Name:string, Columns:list<string>}
     */
    private function uniqueIndexInfo(array $violation): array
    {
        $name = $violation['index_name'] ?? $violation['constraint_name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('Dolt unique index violations must include index_name or violation_info.');
        }

        return [
            'Name' => $name,
            'Columns' => $this->stringList($violation['columns'] ?? null, 'Dolt unique index violation columns'),
        ];
    }

    /**
     * @param array<string, mixed> $violation
     * @return array{Columns:list<string>}
     */
    private function notNullInfo(array $violation): array
    {
        return [
            'Columns' => $this->stringList($violation['columns'] ?? null, 'Dolt not-null violation columns'),
        ];
    }

    private function validateFromRootIsh(mixed $fromRootIsh): void
    {
        if ($fromRootIsh !== null && (!is_string($fromRootIsh) || $fromRootIsh === '')) {
            throw new \InvalidArgumentException('Dolt constraint violation from_root_ish must be null or a non-empty string.');
        }
    }

    /**
     * @param array<string, mixed> $violation
     */
    private function keylessRowHash(array $violation): string
    {
        $hash = $violation['dolt_row_hash'] ?? null;
        if (!is_string($hash) || $hash === '') {
            throw new \InvalidArgumentException('Dolt keyless constraint violations must include dolt_row_hash.');
        }

        return $hash;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $items, string $label): array
    {
        if (!is_array($items) || $items === []) {
            throw new \InvalidArgumentException("{$label} must be a non-empty list.");
        }

        $strings = [];
        foreach (array_values($items) as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException("{$label} must contain non-empty strings.");
            }
            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * Match Go's `%v` formatting for []string in upstream merge errors.
     *
     * @param list<string> $items
     */
    private function goStringList(array $items): string
    {
        return '[' . implode(' ', $items) . ']';
    }

    /**
     * @param array<string, mixed> $items
     * @param list<string> $keys
     */
    private function firstPresent(array $items, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $items)) {
                return $items[$key];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $items
     * @param list<string> $keys
     */
    private function requiredStringField(array $items, array $keys, string $message): string
    {
        $value = $this->optionalStringField($items, $keys);
        if ($value === null) {
            throw new \InvalidArgumentException($message);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $items
     * @param list<string> $keys
     */
    private function optionalStringField(array $items, array $keys, bool $allowEmpty = false): ?string
    {
        $value = $this->firstPresent($items, $keys);
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || (!$allowEmpty && $value === '')) {
            $keyList = implode(', ', $keys);
            throw new \InvalidArgumentException("Dolt constraint violation field {$keyList} must be a string.");
        }

        return $value;
    }

    private function referentialAction(mixed $value, string $label): string
    {
        if ($value === null) {
            return 'RESTRICT';
        }
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("{$label} must be a non-empty string.");
        }

        return strtoupper($value);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $criteria
     */
    private function matchesDeleteCriteria(array $row, array $criteria): bool
    {
        foreach ($criteria as $field => $expected) {
            if (!is_string($field) || $field === '') {
                throw new \InvalidArgumentException('Dolt constraint violation delete criteria fields must be non-empty strings.');
            }

            if ($field === 'row') {
                if (!is_array($expected)) {
                    throw new \InvalidArgumentException('Dolt constraint violation row delete criteria must be an array.');
                }
                foreach ($expected as $rowField => $rowExpected) {
                    if (!is_string($rowField) || $rowField === '') {
                        throw new \InvalidArgumentException('Dolt constraint violation row delete criteria fields must be non-empty strings.');
                    }
                    if (!$this->rowFieldMatches($row, $rowField, $rowExpected)) {
                        return false;
                    }
                }
                continue;
            }

            if ($field === 'violation_info') {
                if (!is_array($expected)) {
                    throw new \InvalidArgumentException('Dolt constraint violation_info delete criteria must be an array.');
                }
                if (!$this->violationInfoMatches($row, $expected)) {
                    return false;
                }
                continue;
            }

            if (str_starts_with($field, 'violation_info.')) {
                $infoField = substr($field, strlen('violation_info.'));
                if ($infoField === '') {
                    throw new \InvalidArgumentException('Dolt constraint violation_info delete criteria fields must be non-empty strings.');
                }
                if (!$this->violationInfoMatches($row, [$infoField => $expected])) {
                    return false;
                }
                continue;
            }

            if (!$this->rowFieldMatches($row, $field, $expected)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowFieldMatches(array $row, string $field, mixed $expected): bool
    {
        if (!array_key_exists($field, $row)) {
            throw new \InvalidArgumentException("Dolt constraint violation delete criteria column {$field} is not present.");
        }

        return $this->valuesEqual($row[$field], $expected);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $expected
     */
    private function violationInfoMatches(array $row, array $expected): bool
    {
        $info = $row['violation_info'] ?? null;
        if (!is_array($info)) {
            throw new \InvalidArgumentException('Dolt constraint violation rows must include violation_info for violation_info delete criteria.');
        }

        foreach ($expected as $field => $value) {
            if (!is_string($field) || $field === '') {
                throw new \InvalidArgumentException('Dolt constraint violation_info delete criteria fields must be non-empty strings.');
            }
            if (!array_key_exists($field, $info)) {
                return false;
            }
            if (!$this->valuesEqual($info[$field], $value)) {
                return false;
            }
        }

        return true;
    }

    private function valuesEqual(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual) || is_array($expected)) {
            return $actual === $expected;
        }

        return $actual === $expected;
    }
}
