<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonbGeneratedPartialUpsertPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<array{name?:string,sql:string,rootPage?:int,unique?:bool}> $indexes
     * @param array<string,mixed> $jsonSetValues
     * @param callable(array<string,mixed>,array<string,mixed>):bool|null $where
     * @param array{keyColumn?:string,jsonColumn?:string,copyColumns?:list<string>} $options
     * @return array<string,mixed>
     */
    public static function plan(
        string $createTableSql,
        array $rows,
        array $incomingRows,
        array $indexes,
        array $jsonSetValues,
        ?callable $where = null,
        int $pageSize = 512,
        array $options = [],
    ): array {
        if ($jsonSetValues === []) {
            throw new \InvalidArgumentException('SQLite JSONB generated partial UPSERT requires at least one jsonb_set path');
        }

        $keyColumn = self::identifier((string) ($options['keyColumn'] ?? 'key_name'), 'key column');
        $jsonColumn = self::identifier((string) ($options['jsonColumn'] ?? 'key_value'), 'JSON column');
        $copyColumns = self::columnList($options['copyColumns'] ?? ['load_policy', 'updated_at', 'migration_generation']);

        $beforePlan = SQLiteGeneratedJsonPathIndexPlan::btreeYieldPlan($createTableSql, $rows, $indexes, [], $pageSize);
        $workingRows = $beforePlan['before'];
        $positions = self::positions($workingRows, $keyColumn);
        $inserted = [];
        $updated = [];
        $skipped = [];
        $matched = [];

        foreach ($incomingRows as $incoming) {
            self::requireColumn($incoming, $keyColumn, 'incoming');
            self::requireColumn($incoming, $jsonColumn, 'incoming');
            $key = (string) $incoming[$keyColumn];
            if (!array_key_exists($key, $positions)) {
                $workingRows[] = $incoming;
                $positions[$key] = count($workingRows) - 1;
                $inserted[] = $incoming;
                continue;
            }

            $position = $positions[$key];
            $current = $workingRows[$position];
            $matched[] = ['current' => $current, 'excluded' => $incoming];
            if ($where !== null && !$where($current, $incoming)) {
                $skipped[] = $incoming;
                continue;
            }

            $updatedRow = $current;
            $updatedRow[$jsonColumn] = self::applyJsonbSetValues($current, $incoming, $jsonSetValues, $jsonColumn);
            foreach ($copyColumns as $column) {
                if (array_key_exists($column, $incoming)) {
                    $updatedRow[$column] = $incoming[$column];
                }
            }

            $workingRows[$position] = $updatedRow;
            $updated[] = $updatedRow;
        }

        $afterPlan = SQLiteGeneratedJsonPathIndexPlan::btreeYieldPlan($createTableSql, $workingRows, $indexes, [], $pageSize);
        $actions = self::diffIndexEntries($beforePlan['btree_indexes'], $afterPlan['btree_indexes']);

        return [
            'table' => $beforePlan['table'],
            'generated_columns' => $beforePlan['generated_columns'],
            'before' => $beforePlan['before'],
            'after' => $afterPlan['before'],
            'inserted_rows' => $inserted,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped,
            'matched_rows' => $matched,
            'index_actions' => $actions,
            'index_action_count' => count($actions),
            'before_indexes' => $beforePlan['btree_indexes'],
            'after_indexes' => $afterPlan['btree_indexes'],
            'changes' => count($inserted) + count($updated),
            'pageSize' => $pageSize,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,int>
     */
    private static function positions(array $rows, string $column): array
    {
        $positions = [];
        foreach ($rows as $position => $row) {
            self::requireColumn($row, $column, 'target');
            $key = (string) $row[$column];
            if (array_key_exists($key, $positions)) {
                throw new \InvalidArgumentException("SQLite JSONB generated partial UPSERT needs unique {$column} target rows");
            }
            $positions[$key] = $position;
        }

        return $positions;
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $incoming
     * @param array<string,mixed> $jsonSetValues
     */
    private static function applyJsonbSetValues(array $current, array $incoming, array $jsonSetValues, string $jsonColumn): SQLiteBlobValue
    {
        $json = $current[$jsonColumn] ?? null;
        if (!$json instanceof SQLiteBlobValue && !is_string($json)) {
            throw new \InvalidArgumentException("SQLite JSONB generated partial UPSERT current {$jsonColumn} must be JSONB or text JSON");
        }

        $arguments = [$json];
        foreach ($jsonSetValues as $path => $value) {
            if (!is_string($path) || !SQLiteJsonPath::isWellFormed($path)) {
                throw new \InvalidArgumentException('SQLite JSONB generated partial UPSERT jsonb_set path is malformed');
            }

            $arguments[] = $path;
            $arguments[] = self::resolveValue($value, $current, $incoming, $jsonColumn);
        }

        $mutated = SQLiteJsonMutation::mutateSqlFunctionArguments('jsonb_set', $arguments);
        if ($mutated instanceof SQLiteBlobValue) {
            return $mutated;
        }
        if (is_string($mutated)) {
            return new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($mutated, true, 512, JSON_THROW_ON_ERROR)));
        }

        throw new \RuntimeException('SQLite JSONB generated partial UPSERT expected JSONB mutation output');
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $incoming
     */
    private static function resolveValue(mixed $value, array $current, array $incoming, string $jsonColumn): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_key_exists('literal', $value)) {
            return $value['literal'];
        }
        if (array_key_exists('json', $value)) {
            if (!is_string($value['json'])) {
                throw new \InvalidArgumentException('SQLite JSONB generated partial UPSERT JSON literal must be text JSON');
            }

            return new SQLiteJsonSubtypeValue($value['json']);
        }
        if (array_key_exists('excluded_column', $value)) {
            return self::requireColumn($incoming, (string) $value['excluded_column'], 'incoming');
        }
        if (array_key_exists('current_column', $value)) {
            return self::requireColumn($current, (string) $value['current_column'], 'target');
        }
        if (array_key_exists('excluded_json', $value)) {
            return SQLiteJsonExtract::extractJsonArgument(
                self::jsonInput($incoming, $jsonColumn, 'incoming'),
                (string) $value['excluded_json'],
            );
        }
        if (array_key_exists('current_json', $value)) {
            return SQLiteJsonExtract::extractJsonArgument(
                self::jsonInput($current, $jsonColumn, 'target'),
                (string) $value['current_json'],
            );
        }

        throw new \InvalidArgumentException('SQLite JSONB generated partial UPSERT value expression is unsupported');
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function jsonInput(array $row, string $column, string $label): string|SQLiteBlobValue|null
    {
        $value = self::requireColumn($row, $column, $label);
        if ($value !== null && !is_string($value) && !$value instanceof SQLiteBlobValue) {
            throw new \InvalidArgumentException("SQLite JSONB generated partial UPSERT {$label} {$column} must be JSONB or text JSON");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function requireColumn(array $row, string $column, string $label): mixed
    {
        if ($column === '' || !array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite JSONB generated partial UPSERT {$label} column {$column} is missing");
        }

        return $row[$column];
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite JSONB generated partial UPSERT {$label} must be an identifier");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function columnList(mixed $columns): array
    {
        if (!is_array($columns)) {
            throw new \InvalidArgumentException('SQLite JSONB generated partial UPSERT copyColumns must be a list of identifiers');
        }

        $result = [];
        foreach ($columns as $column) {
            $result[] = self::identifier((string) $column, 'copy column');
        }

        return $result;
    }

    /**
     * @param array<string,array<string,mixed>> $beforeIndexes
     * @param array<string,array<string,mixed>> $afterIndexes
     * @return list<array<string,mixed>>
     */
    private static function diffIndexEntries(array $beforeIndexes, array $afterIndexes): array
    {
        $actions = [];
        foreach ($afterIndexes as $name => $afterIndex) {
            $beforeIndex = $beforeIndexes[$name] ?? null;
            if (!is_array($beforeIndex)) {
                continue;
            }

            $before = self::entryMap($beforeIndex['current_entries'] ?? []);
            $after = self::entryMap($afterIndex['current_entries'] ?? []);
            foreach ($before as $fingerprint => $entry) {
                if (!array_key_exists($fingerprint, $after)) {
                    $actions[] = self::action('delete', $name, $beforeIndex, $entry);
                }
            }
            foreach ($after as $fingerprint => $entry) {
                if (!array_key_exists($fingerprint, $before)) {
                    $actions[] = self::action('insert', $name, $afterIndex, $entry);
                }
            }
        }

        return $actions;
    }

    /**
     * @param list<array<string,mixed>> $entries
     * @return array<string,array<string,mixed>>
     */
    private static function entryMap(array $entries): array
    {
        $mapped = [];
        foreach ($entries as $entry) {
            $mapped[self::fingerprint($entry['record'] ?? [])] = $entry;
        }

        return $mapped;
    }

    /**
     * @param list<mixed> $record
     */
    private static function fingerprint(array $record): string
    {
        return json_encode($record, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string,mixed> $index
     * @param array<string,mixed> $entry
     * @return array<string,mixed>
     */
    private static function action(string $action, string $name, array $index, array $entry): array
    {
        return [
            'action' => $action,
            'index' => $name,
            'rootPage' => $index['rootPage'] ?? null,
            'column' => $index['column'] ?? null,
            'path' => $index['path'] ?? null,
            'partial' => $index['partial'] ?? false,
            'unique' => $index['unique'] ?? false,
            'collation' => $index['collation'] ?? 'BINARY',
            'descending' => $index['descending'] ?? false,
            'key' => $entry['key'] ?? null,
            'rowid' => $entry['rowid'] ?? null,
            'record' => $entry['record'] ?? [],
            'record_hex' => $entry['record_hex'] ?? '',
        ];
    }
}
