<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWindowFrameExcludeFilterCurrentSourceNext
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{
     *   valueColumn?:string,
     *   partitionColumns?:list<string>,
     *   orderColumns?:non-empty-list<string>,
     *   filterColumn?:string|null,
     *   preceding?:int|float,
     *   following?:int|float,
     *   partitionAffinities?:list<string>|string,
     *   partitionCollations?:list<string>,
     *   orderAffinities?:list<string>|string,
     *   orderCollations?:list<string>,
     *   orderDescending?:list<bool>,
     *   orderNulls?:list<string|null>,
     *   frameUnit?:string,
     *   exclude?:string,
     *   rowidColumn?:string,
     *   separator?:mixed,
     *   cursor?:array{current_source_id?:string,next_source_id?:string,current_offset?:int,next_offset?:int}
     * } $options
     * @return array{
     *   current_source_id:string,
     *   next_source_id:string,
     *   source_changed:bool,
     *   current_offset:int,
     *   next_offset:int,
     *   current_count:int,
     *   next_count:int,
     *   current:list<array<string,mixed>>,
     *   next:list<array<string,mixed>>,
     *   dependencies:list<string>
     * }
     */
    public static function plan(array $currentRows, array $nextRows, array $options = []): array
    {
        self::assertRows($currentRows, 'current');
        self::assertRows($nextRows, 'next');

        $config = self::config($options);
        $currentSourceId = self::sourceId($currentRows, $config);
        $nextSourceId = self::sourceId($nextRows, $config);
        $cursor = $options['cursor'] ?? null;
        $currentOffset = self::offset($cursor['current_offset'] ?? 0, count($currentRows), 'current');
        $nextOffset = self::offset($cursor['next_offset'] ?? 0, count($nextRows), 'next');

        if (is_array($cursor)) {
            self::assertCursorSource($cursor, 'current_source_id', $currentSourceId, 'current');
            self::assertCursorSource($cursor, 'next_source_id', $nextSourceId, 'next');
        }

        return [
            'current_source_id' => $currentSourceId,
            'next_source_id' => $nextSourceId,
            'source_changed' => $currentSourceId !== $nextSourceId,
            'current_offset' => $currentOffset,
            'next_offset' => $nextOffset,
            'current_count' => count($currentRows),
            'next_count' => count($nextRows),
            'current' => self::summaries($currentRows, $config, $currentOffset),
            'next' => self::summaries($nextRows, $config, $nextOffset),
            'dependencies' => [
                'sqlite-vdbe-window-frame-exclude-filter',
                'sqlite-current-source-yield-cursor',
                'sqlite-window-current-source-next',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $config
     * @return list<array<string,mixed>>
     */
    private static function summaries(array $rows, array $config, int $offset): array
    {
        if ($rows === []) {
            return [];
        }

        $cursor = self::cursor($rows, $config);
        for ($i = 0; $i < $offset; $i++) {
            $cursor->next();
        }

        $summaries = [];
        while (!$cursor->eof()) {
            $summary = $cursor->currentYieldSummary($config['rowidColumn'], $config['separator']);
            $summary['row_number'] = count($summaries) + $offset + 1;
            $summaries[] = $summary;
            $cursor->next();
        }

        return $summaries;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $config
     */
    private static function cursor(array $rows, array $config): SQLiteVdbeWindowAggregateCursor
    {
        return new SQLiteVdbeWindowAggregateCursor(
            $rows,
            $config['valueColumn'],
            $config['partitionColumns'],
            $config['orderColumns'],
            $config['filterColumn'],
            $config['preceding'],
            $config['following'],
            $config['partitionAffinities'],
            $config['partitionCollations'],
            $config['orderAffinities'],
            $config['orderCollations'],
            $config['orderDescending'],
            $config['orderNulls'],
            $config['frameUnit'],
            $config['exclude'],
        );
    }

    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private static function config(array $options): array
    {
        $config = [
            'valueColumn' => self::name($options['valueColumn'] ?? 'value', 'value column'),
            'partitionColumns' => self::columnList($options['partitionColumns'] ?? [], true, 'partition'),
            'orderColumns' => self::columnList($options['orderColumns'] ?? ['rowid'], false, 'order'),
            'filterColumn' => array_key_exists('filterColumn', $options) && $options['filterColumn'] !== null ? self::name($options['filterColumn'], 'filter column') : null,
            'preceding' => self::number($options['preceding'] ?? 0, 'preceding'),
            'following' => self::number($options['following'] ?? 0, 'following'),
            'partitionAffinities' => $options['partitionAffinities'] ?? [],
            'partitionCollations' => self::stringList($options['partitionCollations'] ?? [], 'partition collation'),
            'orderAffinities' => $options['orderAffinities'] ?? [],
            'orderCollations' => self::stringList($options['orderCollations'] ?? [], 'order collation'),
            'orderDescending' => self::boolList($options['orderDescending'] ?? [], 'order descending'),
            'orderNulls' => self::nullableStringList($options['orderNulls'] ?? [], 'order nulls'),
            'frameUnit' => strtoupper(self::name($options['frameUnit'] ?? 'ROWS', 'frame unit')),
            'exclude' => strtoupper(trim(str_replace('_', ' ', self::name($options['exclude'] ?? 'NO OTHERS', 'exclude mode')))),
            'rowidColumn' => self::name($options['rowidColumn'] ?? 'rowid', 'rowid column'),
            'separator' => $options['separator'] ?? ',',
        ];
        if (!is_array($config['partitionAffinities']) && !is_string($config['partitionAffinities'])) {
            throw new \InvalidArgumentException('SQLite window current-source partition affinities must be a string or list');
        }
        if (!is_array($config['orderAffinities']) && !is_string($config['orderAffinities'])) {
            throw new \InvalidArgumentException('SQLite window current-source order affinities must be a string or list');
        }

        return $config;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $config
     */
    private static function sourceId(array $rows, array $config): string
    {
        return hash('sha256', json_encode(['rows' => $rows, 'config' => $config], JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function assertRows(array $rows, string $label): void
    {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException("SQLite window current-source {$label} rows must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite window current-source {$label} row must be an array");
            }
        }
    }

    private static function assertCursorSource(array $cursor, string $key, string $expected, string $label): void
    {
        if (!array_key_exists($key, $cursor)) {
            return;
        }
        if ($cursor[$key] !== $expected) {
            throw new \InvalidArgumentException("SQLite window current-source {$label} cursor does not match the current source");
        }
    }

    private static function offset(mixed $offset, int $count, string $label): int
    {
        if (!is_int($offset) || $offset < 0 || $offset > $count) {
            throw new \InvalidArgumentException("SQLite window current-source {$label} cursor offset is out of range");
        }

        return $offset;
    }

    private static function name(mixed $value, string $label): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("SQLite window current-source {$label} must be a non-empty string");
        }

        return trim($value);
    }

    private static function number(mixed $value, string $label): int|float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException("SQLite window current-source {$label} must be numeric");
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
            throw new \InvalidArgumentException("SQLite window current-source {$label} columns must not be empty");
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new \InvalidArgumentException("SQLite window current-source {$label} list must be a list");
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
            throw new \InvalidArgumentException("SQLite window current-source {$label} list must be a list");
        }
        foreach ($values as $value) {
            if (!is_bool($value)) {
                throw new \InvalidArgumentException("SQLite window current-source {$label} values must be booleans");
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
            throw new \InvalidArgumentException("SQLite window current-source {$label} list must be a list");
        }
        foreach ($values as $value) {
            if ($value !== null) {
                self::name($value, $label);
            }
        }

        return $values;
    }
}
