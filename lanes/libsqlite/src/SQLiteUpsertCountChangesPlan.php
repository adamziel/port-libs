<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpsertCountChangesPlan
{
    /**
     * @param list<array{a:string,b?:int}> $baseRows
     * @param list<array{a:string,b?:int}> $incomingRows
     * @return array{
     *     status:string,
     *     source:string,
     *     scenarios:list<string>,
     *     count_changes_enabled:bool,
     *     count_changes_column:string,
     *     count_changes_result:list<int>,
     *     changes_function_result:int,
     *     inserted_count:int,
     *     updated_count:int,
     *     skipped_count:int,
     *     before:list<array{a:string,b:int}>,
     *     after:list<array{a:string,b:int}>,
     *     ordered_after:list<array{a:string,b:int}>,
     *     changed_row_images:list<array{a:string,b:int}>,
     *     dependencies:list<string>
     * }
     */
    public static function upsert1CountChangesScenario(array $baseRows, array $incomingRows): array
    {
        $baseRows = self::normalizeRows($baseRows, 'base');
        $incomingRows = self::normalizeRows($incomingRows, 'incoming');
        self::assertUniqueKeys($baseRows);

        $plan = SQLiteUpsertDoUpdateWherePlan::execute(
            $baseRows,
            $incomingRows,
            ['a'],
            ['b' => static fn (array $current, array $incoming): int => (int) $current['b'] + 1],
        );

        $after = self::typedRows($plan['after']);
        $ordered = $after;
        usort($ordered, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);

        return [
            'status' => 'ok',
            'source' => 'SQLite test/upsert1.test upsert1-400 through upsert1-410',
            'scenarios' => ['upsert1-400', 'upsert1-410'],
            'count_changes_enabled' => true,
            'count_changes_column' => 'rows inserted',
            'count_changes_result' => [count($plan['inserted_rows'])],
            'changes_function_result' => $plan['changes'],
            'inserted_count' => count($plan['inserted_rows']),
            'updated_count' => count($plan['updated_rows']),
            'skipped_count' => count($plan['skipped_rows']),
            'before' => self::typedRows($plan['before']),
            'after' => $after,
            'ordered_after' => $ordered,
            'changed_row_images' => self::typedRows($plan['returning_rows']),
            'dependencies' => [
                'upsert1.test-400',
                'upsert1.test-410',
                'sqlite-pragma-count-changes-upsert-insert-result',
                'sqlite-upsert-do-update-row-image',
            ],
        ];
    }

    /**
     * @param list<array{a:string,b?:int}> $rows
     * @return list<array{a:string,b:int}>
     */
    private static function normalizeRows(array $rows, string $label): array
    {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException("SQLite UPSERT count_changes {$label} rows must be a list");
        }

        $normalized = [];
        foreach ($rows as $index => $row) {
            if (!isset($row['a']) || !is_string($row['a']) || $row['a'] === '') {
                throw new \InvalidArgumentException("SQLite UPSERT count_changes {$label} row {$index} must have a non-empty text key");
            }
            if (array_key_exists('b', $row) && !is_int($row['b'])) {
                throw new \InvalidArgumentException("SQLite UPSERT count_changes {$label} row {$index} b value must be an integer");
            }
            $normalized[] = [
                'a' => $row['a'],
                'b' => $row['b'] ?? 1,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{a:string,b:int}> $rows
     */
    private static function assertUniqueKeys(array $rows): void
    {
        $seen = [];
        foreach ($rows as $row) {
            if (isset($seen[$row['a']])) {
                throw new \InvalidArgumentException('SQLite UPSERT count_changes base rows must be unique by a');
            }
            $seen[$row['a']] = true;
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{a:string,b:int}>
     */
    private static function typedRows(array $rows): array
    {
        $typed = [];
        foreach ($rows as $index => $row) {
            if (!isset($row['a']) || !is_string($row['a']) || !isset($row['b']) || !is_int($row['b'])) {
                throw new \InvalidArgumentException("SQLite UPSERT count_changes produced invalid row {$index}");
            }
            $typed[] = ['a' => $row['a'], 'b' => $row['b']];
        }

        return $typed;
    }
}
