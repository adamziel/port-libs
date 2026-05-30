<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonUpsertMigrationPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param array<string,mixed> $jsonSetValues
     * @param callable(array<string,mixed>,array<string,mixed>):bool|null $where
     * @return array{before:list<array<string,mixed>>,after:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,decoded_returning:list<array<string,mixed>>,changes:int}
     */
    public static function execute(array $rows, array $incomingRows, array $jsonSetValues, ?callable $where = null): array
    {
        self::validateJsonSetValues($jsonSetValues);

        $preparedIncoming = array_map(
            static fn (array $incoming): array => self::prepareIncoming($incoming, $jsonSetValues),
            $incomingRows,
        );

        $plan = SQLiteUpsertDoUpdateWherePlan::execute(
            $rows,
            $preparedIncoming,
            ['option_name'],
            [
                'option_value' => static fn (array $current, array $excluded): string => self::applyJsonSetValues(
                    self::requireString($current, 'option_value', 'current'),
                    $excluded,
                    $jsonSetValues,
                    $current,
                ),
                'autoload' => static fn (array $current, array $excluded): mixed => $excluded['autoload'] ?? ($current['autoload'] ?? null),
                'migration_generation' => static fn (array $current, array $excluded): int => max(
                    (int) ($current['migration_generation'] ?? 0),
                    (int) ($excluded['migration_generation'] ?? 0),
                ) + 1,
            ],
            $where,
        );

        return $plan + [
            'decoded_returning' => array_map(
                static fn (array $row): array => self::decodeReturningRow($row),
                $plan['returning_rows'],
            ),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $jsonSetValues
     * @return array<string,mixed>
     */
    private static function prepareIncoming(array $row, array $jsonSetValues): array
    {
        $row['option_value'] = self::applyJsonSetValues(
            self::requireString($row, 'option_value', 'incoming'),
            $row,
            $jsonSetValues,
            null,
        );

        return $row;
    }

    /**
     * @param array<string,mixed> $excluded
     * @param array<string,mixed>|null $current
     */
    private static function applyJsonSetValues(string $json, array $excluded, array $jsonSetValues, ?array $current): string
    {
        $arguments = [$json];
        foreach ($jsonSetValues as $path => $value) {
            $arguments[] = $path;
            $arguments[] = self::resolveJsonSetValue($value, $excluded, $current);
        }

        $mutated = SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', $arguments);
        if (!is_string($mutated)) {
            throw new \RuntimeException('SQLite Application JSON UPSERT migration expected text JSON output');
        }

        return $mutated;
    }

    /**
     * @param array<string,mixed> $excluded
     * @param array<string,mixed>|null $current
     */
    private static function resolveJsonSetValue(mixed $value, array $excluded, ?array $current): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_key_exists('literal', $value)) {
            return $value['literal'];
        }
        if (array_key_exists('json', $value)) {
            return new SQLiteJsonSubtypeValue(self::requireJsonString($value['json'], 'json literal'));
        }
        if (array_key_exists('excluded_column', $value)) {
            return self::requireColumnValue($excluded, $value['excluded_column'], 'excluded');
        }
        if (array_key_exists('current_column', $value)) {
            if ($current === null) {
                return null;
            }

            return self::requireColumnValue($current, $value['current_column'], 'current');
        }
        if (array_key_exists('excluded_json', $value)) {
            return SQLiteJsonExtract::extractJsonArgument(
                self::requireString($excluded, 'option_value', 'excluded'),
                self::requireJsonPath($value['excluded_json']),
            );
        }
        if (array_key_exists('current_json', $value)) {
            if ($current === null) {
                return null;
            }

            return SQLiteJsonExtract::extractJsonArgument(
                self::requireString($current, 'option_value', 'current'),
                self::requireJsonPath($value['current_json']),
            );
        }

        throw new \InvalidArgumentException('SQLite Application JSON UPSERT migration value expression is unsupported');
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function decodeReturningRow(array $row): array
    {
        $decoded = json_decode(self::requireString($row, 'option_value', 'returning'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('SQLite Application JSON UPSERT migration option_value must decode to an object or array');
        }

        return $row + ['decoded_option_value' => $decoded];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function requireString(array $row, string $column, string $label): string
    {
        if (!array_key_exists($column, $row) || !is_string($row[$column])) {
            throw new \InvalidArgumentException("SQLite Application JSON UPSERT migration {$label} {$column} must be text JSON");
        }

        return $row[$column];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function requireColumnValue(array $row, mixed $column, string $label): mixed
    {
        if (!is_string($column) || $column === '') {
            throw new \InvalidArgumentException("SQLite Application JSON UPSERT migration {$label} column reference is malformed");
        }
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite Application JSON UPSERT migration {$label} column {$column} is missing");
        }

        return $row[$column];
    }

    private static function requireJsonPath(mixed $path): string
    {
        if (!is_string($path) || $path === '') {
            throw new \InvalidArgumentException('SQLite Application JSON UPSERT migration JSON path is malformed');
        }

        return $path;
    }

    private static function requireJsonString(mixed $json, string $label): string
    {
        if (!is_string($json)) {
            throw new \InvalidArgumentException("SQLite Application JSON UPSERT migration {$label} must be text JSON");
        }

        return $json;
    }

    /**
     * @param array<string,mixed> $jsonSetValues
     */
    private static function validateJsonSetValues(array $jsonSetValues): void
    {
        if ($jsonSetValues === []) {
            throw new \InvalidArgumentException('SQLite Application JSON UPSERT migration requires at least one json_set path');
        }

        foreach ($jsonSetValues as $path => $_) {
            if (!is_string($path) || $path === '' || $path[0] !== '$') {
                throw new \InvalidArgumentException('SQLite Application JSON UPSERT migration json_set paths must be JSON paths');
            }
        }
    }
}
