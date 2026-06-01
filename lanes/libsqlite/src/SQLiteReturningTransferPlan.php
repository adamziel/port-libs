<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteReturningTransferPlan
{
    /**
     * @param array<int,array<string,mixed>> $targetRows
     * @param array<int,array<string,mixed>> $sourceRows
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    public static function insertSelectReturning(
        array $targetRows,
        array $sourceRows,
        array $columns,
        string $sourceTable = 'source_rows',
        string $targetTable = 'target_rows'
    ): array {
        self::validateIdentifier($sourceTable, 'source table');
        self::validateIdentifier($targetTable, 'target table');
        $columns = self::validateColumns($columns);

        $before = self::projectRows($targetRows, $columns, 'target row');
        $insertedRows = self::projectRows($sourceRows, $columns, 'source row');

        return [
            'source' => 'returning1.test',
            'scenario' => 'returning1-16.0 INSERT INTO target SELECT * FROM source RETURNING *',
            'source_table' => $sourceTable,
            'target_table' => $targetTable,
            'columns' => $columns,
            'before' => $before,
            'inserted_rows' => $insertedRows,
            'returning_rows' => $insertedRows,
            'after' => array_merge($before, $insertedRows),
            'changes' => count($insertedRows),
            'dependencies' => [
                'source-select-row-order',
                'returning-emits-inserted-row-image',
                'target-append-preserves-existing-rows',
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $targetRows
     * @param array<int,array<string,mixed>> $sourceRows
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    public static function insertSelectXferOptimizationDecision(
        array $targetRows,
        array $sourceRows,
        array $columns,
        bool $returning = false,
        bool $integrityCheckRan = false,
        string $sourceTable = 'source_rows',
        string $targetTable = 'target_rows'
    ): array {
        self::validateIdentifier($sourceTable, 'source table');
        self::validateIdentifier($targetTable, 'target table');
        $columns = self::validateColumns($columns);

        $before = self::projectRows($targetRows, $columns, 'target row');
        $insertedRows = self::projectRows($sourceRows, $columns, 'source row');
        $usesTransferOptimization = !$returning;

        return [
            'source' => 'insert4.test',
            'scenario' => 'insert4-10.2/10.3/10.4 INSERT INTO target SELECT * FROM source transfer optimization and RETURNING',
            'source_table' => $sourceTable,
            'target_table' => $targetTable,
            'columns' => $columns,
            'before' => $before,
            'inserted_rows' => $insertedRows,
            'returning_rows' => $returning ? $insertedRows : [],
            'after' => array_merge($before, $insertedRows),
            'changes' => count($insertedRows),
            'returning' => $returning,
            'integrity_check_ran' => $integrityCheckRan,
            'integrity_check_preserves_transfer_eligibility' => true,
            'xfer_optimization_used' => $usesTransferOptimization,
            'xferopt_count' => $usesTransferOptimization ? 1 : 0,
            'optimization_blocker' => $returning ? 'returning-clause-requires-row-image-emission' : null,
            'dependencies' => [
                'insert4-10.2 transfer optimization without RETURNING',
                'insert4-10.3 integrity_check preserves transfer optimization',
                'insert4-10.4 RETURNING disables transfer optimization',
                'returning-emits-inserted-row-image',
                'target-append-preserves-existing-rows',
            ],
        ];
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private static function validateColumns(array $columns): array
    {
        if ($columns === [] || !array_is_list($columns)) {
            throw new InvalidArgumentException('columns must be a non-empty list');
        }

        $seen = [];
        foreach ($columns as $column) {
            if (!is_string($column) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $column)) {
                throw new InvalidArgumentException('columns must contain valid identifiers');
            }

            if (isset($seen[$column])) {
                throw new InvalidArgumentException('columns must be unique');
            }

            $seen[$column] = true;
        }

        return $columns;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param list<string> $columns
     * @return array<int,array<string,mixed>>
     */
    private static function projectRows(array $rows, array $columns, string $label): array
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException($label . ' set must be a list');
        }

        $projected = [];
        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException($label . ' must be an array');
            }

            $projectedRow = [];
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new InvalidArgumentException($label . ' ' . $rowIndex . ' is missing column ' . $column);
                }

                $projectedRow[$column] = $row[$column];
            }

            $projected[] = $projectedRow;
        }

        return $projected;
    }

    private static function validateIdentifier(string $identifier, string $label): void
    {
        if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $identifier)) {
            throw new InvalidArgumentException($label . ' must be a valid identifier');
        }
    }
}
