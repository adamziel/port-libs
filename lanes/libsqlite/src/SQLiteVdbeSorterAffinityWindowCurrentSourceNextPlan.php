<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param non-empty-list<string> $sortColumns
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function compare(array $currentRows, array $nextRows, array $sortColumns, string $rowidColumn, array $options = []): array
    {
        $sortAffinities = $options['sortAffinities'] ?? [];
        $sortCollations = self::stringList($options['sortCollations'] ?? [], 'sort collation');
        $sortDescending = self::boolList($options['sortDescending'] ?? [], 'sort descending');
        $sortNulls = self::nullableStringList($options['sortNulls'] ?? [], 'sort nulls');
        $valueColumn = self::name($options['valueColumn'] ?? 'value', 'value column');
        $partitionColumns = self::columnList($options['partitionColumns'] ?? [], true, 'partition');
        $orderColumns = self::columnList($options['orderColumns'] ?? $sortColumns, false, 'order');
        $filterColumn = array_key_exists('filterColumn', $options) && $options['filterColumn'] !== null ? self::name($options['filterColumn'], 'filter column') : null;
        $preceding = self::number($options['preceding'] ?? 0, 'preceding');
        $following = self::number($options['following'] ?? 0, 'following');
        $partitionAffinities = $options['partitionAffinities'] ?? [];
        $orderAffinities = $options['orderAffinities'] ?? $sortAffinities;
        $partitionCollations = self::stringList($options['partitionCollations'] ?? [], 'partition collation');
        $orderCollations = self::stringList($options['orderCollations'] ?? $sortCollations, 'order collation');
        $orderDescending = self::boolList($options['orderDescending'] ?? $sortDescending, 'order descending');
        $orderNulls = self::nullableStringList($options['orderNulls'] ?? $sortNulls, 'order nulls');
        $frameUnit = strtoupper(self::name($options['frameUnit'] ?? 'GROUPS', 'frame unit'));
        $exclude = strtoupper(trim(str_replace('_', ' ', self::name($options['exclude'] ?? 'NO OTHERS', 'exclude mode'))));
        $separator = $options['separator'] ?? ',';

        if (!is_array($sortAffinities) && !is_string($sortAffinities)) {
            throw new \InvalidArgumentException('SQLite VDBE sorter affinity window current-source next sort affinities must be a string or list');
        }
        if (!is_array($partitionAffinities) && !is_string($partitionAffinities)) {
            throw new \InvalidArgumentException('SQLite VDBE sorter affinity window current-source next partition affinities must be a string or list');
        }
        if (!is_array($orderAffinities) && !is_string($orderAffinities)) {
            throw new \InvalidArgumentException('SQLite VDBE sorter affinity window current-source next order affinities must be a string or list');
        }

        $sorter = SQLiteVdbeAffinityCollationSorterSourcePlan::compareSources(
            $currentRows,
            $nextRows,
            $sortColumns,
            $rowidColumn,
            $sortAffinities,
            $sortCollations,
            $sortDescending,
            $sortNulls
        );

        $currentSortedRows = array_map(static fn (array $entry): array => $entry['row'], $sorter['currentTrace']);
        $nextSortedRows = array_map(static fn (array $entry): array => $entry['row'], $sorter['nextTrace']);
        $currentWindow = self::windowSummaries(
            $currentSortedRows,
            $valueColumn,
            $partitionColumns,
            $orderColumns,
            $filterColumn,
            $preceding,
            $following,
            $partitionAffinities,
            $partitionCollations,
            $orderAffinities,
            $orderCollations,
            $orderDescending,
            $orderNulls,
            $frameUnit,
            $exclude,
            $rowidColumn,
            $separator
        );
        $nextWindow = self::windowSummaries(
            $nextSortedRows,
            $valueColumn,
            $partitionColumns,
            $orderColumns,
            $filterColumn,
            $preceding,
            $following,
            $partitionAffinities,
            $partitionCollations,
            $orderAffinities,
            $orderCollations,
            $orderDescending,
            $orderNulls,
            $frameUnit,
            $exclude,
            $rowidColumn,
            $separator
        );

        $currentById = self::byId($currentWindow, $rowidColumn);
        $peerChanges = [];
        foreach ($nextWindow as $summary) {
            $id = $summary['currentRowid'];
            $key = self::idKey($id);
            if (!isset($currentById[$key])) {
                continue;
            }
            $before = $currentById[$key];
            if ($before['partitionKey'] !== $summary['partitionKey'] || $before['orderKey'] !== $summary['orderKey'] || $before['frameRowids'] !== $summary['frameRowids']) {
                $peerChanges[] = [
                    'id' => $id,
                    'currentPartitionKey' => $before['partitionKey'],
                    'nextPartitionKey' => $summary['partitionKey'],
                    'currentOrderKey' => $before['orderKey'],
                    'nextOrderKey' => $summary['orderKey'],
                    'currentFrameRowids' => $before['frameRowids'],
                    'nextFrameRowids' => $summary['frameRowids'],
                ];
            }
        }

        return [
            'status' => $sorter['changed'] || $peerChanges !== [] ? 'sorter-affinity-window-current-source-changed' : 'sorter-affinity-window-current-source-stable',
            'currentOrder' => $sorter['currentOrder'],
            'nextOrder' => $sorter['nextOrder'],
            'inserted' => $sorter['inserted'],
            'deleted' => $sorter['deleted'],
            'moved' => $sorter['moved'],
            'stableTieIds' => $sorter['stableTieIds'],
            'currentWindow' => $currentWindow,
            'nextWindow' => $nextWindow,
            'peerChanges' => $peerChanges,
            'sortColumns' => $sortColumns,
            'partitionColumns' => $partitionColumns,
            'orderColumns' => $orderColumns,
            'dependencies' => [
                'sqlite-vdbe-sorter-affinity-current-source-next',
                'sqlite-vdbe-window-current-source-yield',
                'sqlite-affinity-comparison',
                'sqlite-collation-sequence',
            ],
            'dependency_closure' => 'no new support component needed; reuses lane-local VDBE sorter affinity/collation comparison and window aggregate cursor primitives',
            'non_overlap' => 'avoids accepted DISTINCT sorter, expression ORDER BY, standalone window frame/exclude/filter, compound recursive affinity window, and JSON/WAL/B-tree/VFS clusters; this slice composes sorter yield affinity with next-source window frame recalculation',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $partitionColumns
     * @param non-empty-list<string> $orderColumns
     * @param list<string>|string $partitionAffinities
     * @param list<string> $partitionCollations
     * @param list<string>|string $orderAffinities
     * @param list<string> $orderCollations
     * @param list<bool> $orderDescending
     * @param list<string|null> $orderNulls
     * @return list<array<string,mixed>>
     */
    private static function windowSummaries(
        array $rows,
        string $valueColumn,
        array $partitionColumns,
        array $orderColumns,
        ?string $filterColumn,
        int|float $preceding,
        int|float $following,
        array|string $partitionAffinities,
        array $partitionCollations,
        array|string $orderAffinities,
        array $orderCollations,
        array $orderDescending,
        array $orderNulls,
        string $frameUnit,
        string $exclude,
        string $rowidColumn,
        mixed $separator
    ): array {
        if ($rows === []) {
            return [];
        }
        $cursor = new SQLiteVdbeWindowAggregateCursor(
            $rows,
            $valueColumn,
            $partitionColumns,
            $orderColumns,
            $filterColumn,
            $preceding,
            $following,
            $partitionAffinities,
            $partitionCollations,
            $orderAffinities,
            $orderCollations,
            $orderDescending,
            $orderNulls,
            $frameUnit,
            $exclude
        );
        $summaries = [];
        while (!$cursor->eof()) {
            $summaries[] = $cursor->currentYieldSummary($rowidColumn, $separator);
            $cursor->next();
        }

        return $summaries;
    }

    /**
     * @param list<array<string,mixed>> $summaries
     * @return array<string,array<string,mixed>>
     */
    private static function byId(array $summaries, string $rowidColumn): array
    {
        $byId = [];
        foreach ($summaries as $summary) {
            $byId[self::idKey($summary['currentRowid'])] = $summary;
        }

        return $byId;
    }

    private static function idKey(mixed $id): string
    {
        return get_debug_type($id) . ':' . (string) $id;
    }

    private static function name(mixed $value, string $label): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("SQLite VDBE sorter affinity window current-source next {$label} must be a non-empty string");
        }

        return trim($value);
    }

    private static function number(mixed $value, string $label): int|float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException("SQLite VDBE sorter affinity window current-source next {$label} must be numeric");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function columnList(mixed $columns, bool $allowEmpty, string $label): array
    {
        $columns = self::stringList($columns, $label);
        if (!$allowEmpty && $columns === []) {
            throw new \InvalidArgumentException("SQLite VDBE sorter affinity window current-source next {$label} columns must not be empty");
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new \InvalidArgumentException("SQLite VDBE sorter affinity window current-source next {$label} list must be a list");
        }
        foreach ($values as $value) {
            self::name($value, $label);
        }

        return $values;
    }

    /**
     * @return list<bool>
     */
    private static function boolList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new \InvalidArgumentException("SQLite VDBE sorter affinity window current-source next {$label} list must be a list");
        }
        foreach ($values as $value) {
            if (!is_bool($value)) {
                throw new \InvalidArgumentException("SQLite VDBE sorter affinity window current-source next {$label} values must be booleans");
            }
        }

        return $values;
    }

    /**
     * @return list<string|null>
     */
    private static function nullableStringList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new \InvalidArgumentException("SQLite VDBE sorter affinity window current-source next {$label} list must be a list");
        }
        foreach ($values as $value) {
            if ($value !== null) {
                self::name($value, $label);
            }
        }

        return $values;
    }
}
