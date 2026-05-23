<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class ConstraintViolationsTable
{
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
            self::TYPE_CHECK_CONSTRAINT => $this->checkConstraintInfo($violation),
            self::TYPE_UNIQUE_INDEX => $this->uniqueIndexInfo($violation),
            self::TYPE_NOT_NULL => $this->notNullInfo($violation),
            default => throw new \InvalidArgumentException("Dolt {$type} violations must include violation_info."),
        };
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
}
