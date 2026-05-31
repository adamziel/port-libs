<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteUpsertReturningFaultPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $incoming
     * @return array{source:string,scenario:string,fault:array{kind:string,step:int,checkpoint:string,recovered:bool},before:list<array<string,mixed>>,after:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,changes:int,error:null,allocations_released:bool,statement_retriable:bool,dependencies:list<string>}
     */
    public static function recoverableUpsertUpdateFault(array $rows, array $incoming, int $faultStep, string $faultKind = 'oom'): array
    {
        self::validateRows($rows, ['a', 'b', 'c', 'd'], 'target');
        self::validateRow($incoming, ['a', 'b', 'c', 'd'], 'incoming');
        $faultStep = max(0, $faultStep);
        $checkpoints = [
            'schema-record-read',
            'unique-index-probe',
            'conflict-row-load',
            'update-expression-evaluate',
            'row-image-build',
            'index-entry-rewrite',
            'statement-journal-checkpoint',
            'statement-reset',
        ];

        $after = $rows;
        $updated = [];
        $recovered = $faultKind === 'oom';
        foreach ($after as $index => $row) {
            if ($row['b'] == $incoming['b'] && $row['c'] == $incoming['c']) {
                $after[$index]['d'] = ((int) $row['d']) + 1;
                $updated[] = $after[$index];
                break;
            }
        }

        if ($updated === []) {
            throw new InvalidArgumentException('SQLite UPSERT fault plan requires an incoming row that conflicts on b,c');
        }

        return [
            'source' => 'upsertfault.test',
            'scenario' => 'upsertfault-1 recoverable OOM during ON CONFLICT(b,c) DO UPDATE',
            'fault' => [
                'kind' => $faultKind,
                'step' => $faultStep,
                'checkpoint' => $checkpoints[$faultStep % count($checkpoints)],
                'recovered' => $recovered,
            ],
            'before' => $rows,
            'after' => array_values($after),
            'updated_rows' => $updated,
            'changes' => $recovered ? 1 : 0,
            'error' => $recovered ? null : 'out of memory',
            'allocations_released' => true,
            'statement_retriable' => true,
            'dependencies' => [
                'sqlite-upsert-faultsim-retry',
                'upsertfault.test-1',
            ],
        ];
    }

    /**
     * @return array{source:string,scenario:string,fault:array{kind:string,step:int,checkpoint:string,recovered:bool},returning_projection:string,error:string,inserted_rows:list<array<string,mixed>>,changes:int,temp_schema_stable:bool,allocations_released:bool,dependencies:list<string>}
     */
    public static function returningSubqueryColumnFault(int $faultStep, string $faultKind = 'oom-t'): array
    {
        $faultStep = max(0, $faultStep);
        $checkpoints = [
            'returning-expression-parse',
            'temp-schema-open',
            'subquery-column-count-check',
            'result-column-build',
            'statement-reset',
        ];

        return [
            'source' => 'returningfault.test',
            'scenario' => 'returningfault-1 RETURNING scalar subquery rejects five-column temp schema',
            'fault' => [
                'kind' => $faultKind,
                'step' => $faultStep,
                'checkpoint' => $checkpoints[$faultStep % count($checkpoints)],
                'recovered' => true,
            ],
            'returning_projection' => '(SELECT * FROM sqlite_temp_schema) AS aaa',
            'error' => 'sub-select returns 5 columns - expected 1',
            'inserted_rows' => [],
            'changes' => 0,
            'temp_schema_stable' => true,
            'allocations_released' => true,
            'dependencies' => [
                'sqlite-returning-faultsim-error-cleanup',
                'returningfault.test-1',
            ],
        ];
    }

    /**
     * @return array{source:string,scenario:string,fault:array{kind:string,step:int,checkpoint:string,recovered:bool},virtual_table:string,result:list<array<string,mixed>>,error:null|string,changes:int,constructor_may_fail:bool,allocations_released:bool,dependencies:list<string>}
     */
    public static function returningVirtualTableFault(int $faultStep, bool $constructorFails): array
    {
        $faultStep = max(0, $faultStep);
        $checkpoints = [
            'virtual-table-connect',
            'xbestindex',
            'xfilter',
            'xupdate',
            'returning-row-build',
            'statement-reset',
        ];

        return [
            'source' => 'returningfault.test',
            'scenario' => 'returningfault-2 virtual table INSERT RETURNING either yields row or constructor fault',
            'fault' => [
                'kind' => 'oom',
                'step' => $faultStep,
                'checkpoint' => $checkpoints[$faultStep % count($checkpoints)],
                'recovered' => true,
            ],
            'virtual_table' => 'tcl',
            'result' => $constructorFails ? [] : [['a' => 'hello', 'b' => 'world']],
            'error' => $constructorFails ? 'vtable constructor failed: tcl' : null,
            'changes' => $constructorFails ? 0 : 1,
            'constructor_may_fail' => $constructorFails,
            'allocations_released' => true,
            'dependencies' => [
                'sqlite-returning-virtual-table-faultsim',
                'returningfault.test-2',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     */
    private static function validateRows(array $rows, array $columns, string $label): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite {$label} rows must be arrays");
            }
            self::validateRow($row, $columns, $label);
        }
    }

    /** @param list<string> $columns */
    private static function validateRow(array $row, array $columns, string $label): void
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new InvalidArgumentException("SQLite {$label} row is missing {$column}");
            }
        }
    }
}
