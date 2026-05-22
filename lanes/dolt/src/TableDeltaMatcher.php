<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class TableDeltaMatcher
{
    public const DIFF_ADDED = 'added';
    public const DIFF_MODIFIED = 'modified';
    public const DIFF_RENAMED = 'renamed';
    public const DIFF_DROPPED = 'dropped';

    /**
     * @param list<array{name:string, schema:TableSchema, rowHash?:string|null, rowCount?:int}> $fromTables
     * @param list<array{name:string, schema:TableSchema, rowHash?:string|null, rowCount?:int}> $toTables
     * @return list<array{table_name:string, from_table_name:string|null, to_table_name:string|null, diff_type:string, data_change:bool, schema_change:bool, primary_key_set_changed:bool}>
     */
    public function summaries(array $fromTables, array $toTables): array
    {
        $from = $this->indexTables($fromTables, 'from');
        $to = $this->indexTables($toTables, 'to');
        $deltas = [];

        foreach (array_keys($to) as $name) {
            if (!isset($from[$name])) {
                continue;
            }
            $summary = $this->summarize($from[$name], $to[$name]);
            if (!$this->isUnmodified($summary)) {
                $deltas[] = $summary;
            }
            unset($from[$name], $to[$name]);
        }

        foreach (array_keys($from) as $fromName) {
            foreach (array_keys($to) as $toName) {
                if (!TableSchema::schemasOverlap($from[$fromName]['schema'], $to[$toName]['schema'])) {
                    continue;
                }
                $summary = $this->summarize($from[$fromName], $to[$toName]);
                if (!$this->isUnmodified($summary)) {
                    $deltas[] = $summary;
                }
                unset($from[$fromName], $to[$toName]);
                break;
            }
        }

        foreach ($from as $table) {
            $deltas[] = $this->summarize($table, null);
        }
        foreach ($to as $table) {
            $deltas[] = $this->summarize(null, $table);
        }

        usort($deltas, static function (array $a, array $b): int {
            return [$a['from_table_name'] ?? '', $a['to_table_name'] ?? ''] <=> [$b['from_table_name'] ?? '', $b['to_table_name'] ?? ''];
        });

        return $deltas;
    }

    public static function changeTypeToDiffType(string $changeType): string
    {
        return match ($changeType) {
            'added' => self::DIFF_ADDED,
            'removed' => self::DIFF_DROPPED,
            'modified_old', 'modified_new' => self::DIFF_MODIFIED,
            default => '',
        };
    }

    /**
     * @param list<array{name:string, schema:TableSchema, rowHash?:string|null, rowCount?:int}> $tables
     * @return array<string, array{name:non-empty-string, schema:TableSchema, rowHash:string|null, rowCount:int|null}>
     */
    private function indexTables(array $tables, string $side): array
    {
        $indexed = [];
        foreach ($tables as $table) {
            $name = $table['name'] ?? null;
            $schema = $table['schema'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException("{$side} table names must be non-empty strings.");
            }
            if (!$schema instanceof TableSchema) {
                throw new \InvalidArgumentException("{$side} table {$name} must include a TableSchema.");
            }
            if (isset($indexed[$name])) {
                throw new \InvalidArgumentException("Duplicate {$side} table name: {$name}");
            }
            $rowHash = $table['rowHash'] ?? null;
            if ($rowHash !== null && (!is_string($rowHash) || $rowHash === '')) {
                throw new \InvalidArgumentException("{$side} table {$name} rowHash must be a non-empty string or null.");
            }
            $rowCount = $table['rowCount'] ?? null;
            if ($rowCount !== null && (!is_int($rowCount) || $rowCount < 0)) {
                throw new \InvalidArgumentException("{$side} table {$name} rowCount must be a non-negative integer or null.");
            }

            $indexed[$name] = [
                'name' => $name,
                'schema' => $schema,
                'rowHash' => $rowHash,
                'rowCount' => $rowCount,
            ];
        }

        ksort($indexed, SORT_STRING);

        return $indexed;
    }

    /**
     * @param array{name:non-empty-string, schema:TableSchema, rowHash:string|null, rowCount:int|null}|null $from
     * @param array{name:non-empty-string, schema:TableSchema, rowHash:string|null, rowCount:int|null}|null $to
     * @return array{table_name:string, from_table_name:string|null, to_table_name:string|null, diff_type:string, data_change:bool, schema_change:bool, primary_key_set_changed:bool}
     */
    private function summarize(?array $from, ?array $to): array
    {
        $fromName = $from['name'] ?? null;
        $toName = $to['name'] ?? null;
        $isAdd = $from === null && $to !== null;
        $isDrop = $from !== null && $to === null;
        $isRename = !$isAdd && !$isDrop && $fromName !== $toName;
        $primaryKeySetChanged = !$isAdd
            && !$isDrop
            && !TableSchema::primaryKeySetsDiffable($from['schema'], $to['schema']);

        if ($isAdd) {
            $diffType = self::DIFF_ADDED;
            $schemaChange = true;
        } elseif ($isDrop) {
            $diffType = self::DIFF_DROPPED;
            $schemaChange = true;
        } elseif ($isRename) {
            $diffType = self::DIFF_RENAMED;
            $schemaChange = true;
        } else {
            $diffType = self::DIFF_MODIFIED;
            $schemaChange = $primaryKeySetChanged || $this->hasColumnDiff($from['schema'], $to['schema']);
        }

        return [
            'table_name' => $toName ?? $fromName ?? '',
            'from_table_name' => $fromName,
            'to_table_name' => $toName,
            'diff_type' => $diffType,
            'data_change' => $this->hasDataChange($from, $to),
            'schema_change' => $schemaChange,
            'primary_key_set_changed' => $primaryKeySetChanged,
        ];
    }

    private function hasColumnDiff(TableSchema $from, TableSchema $to): bool
    {
        foreach (TableSchema::diffColumns($from, $to) as $diff) {
            if ($diff['diff_type'] !== TableSchema::DIFF_NONE) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{table_name:string, from_table_name:string|null, to_table_name:string|null, diff_type:string, data_change:bool, schema_change:bool, primary_key_set_changed:bool} $summary
     */
    private function isUnmodified(array $summary): bool
    {
        return $summary['diff_type'] === self::DIFF_MODIFIED
            && !$summary['data_change']
            && !$summary['schema_change']
            && !$summary['primary_key_set_changed'];
    }

    /**
     * @param array{name:non-empty-string, schema:TableSchema, rowHash:string|null, rowCount:int|null}|null $from
     * @param array{name:non-empty-string, schema:TableSchema, rowHash:string|null, rowCount:int|null}|null $to
     */
    private function hasDataChange(?array $from, ?array $to): bool
    {
        if ($from === null) {
            return ($to['rowCount'] ?? 0) > 0;
        }
        if ($to === null) {
            return ($from['rowCount'] ?? 0) > 0;
        }
        if ($from['rowHash'] === null || $to['rowHash'] === null) {
            return false;
        }

        return $from['rowHash'] !== $to['rowHash'];
    }
}
