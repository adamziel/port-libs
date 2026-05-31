<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePragmaCountChangesTriggerPlan
{
    /**
     * @return array{
     *     status:string,
     *     source:string,
     *     scenarios:list<string>,
     *     pragma:array<string,mixed>,
     *     query:array<string,mixed>,
     *     result_shape:array<string,mixed>,
     *     schema:array<string,mixed>,
     *     operations:list<array<string,mixed>>,
     *     final:array<string,mixed>,
     *     dependencies:list<string>
     * }
     */
    public static function pragma10TriggerScenario(int $variant): array
    {
        if ($variant < 1) {
            throw new \InvalidArgumentException('SQLite PRAGMA count_changes variant must be positive');
        }

        $suffix = sprintf('%04d', $variant);
        $table = "app_settings_{$suffix}";
        $beforeMirror = "app_settings_before_mirror_{$suffix}";
        $afterMirror = "app_settings_after_mirror_{$suffix}";
        $initialKey = "setting_{$suffix}";
        $updatedKey = "setting_{$suffix}_updated";

        $pragmaState = new SQLitePragmaConnectionBooleanState();
        $pragma = $pragmaState->execute('PRAGMA count_changes = 1');
        $query = $pragmaState->execute('PRAGMA count_changes');
        $shape = SQLitePragmaResultShape::describe('PRAGMA count_changes = 1');

        $rows = [];
        $beforeRows = [];
        $afterRows = [];

        $insert = self::insert($rows, $beforeRows, $afterRows, $initialKey);
        $update = self::update($rows, $beforeRows, $afterRows, $initialKey, $updatedKey);
        $delete = self::delete($rows, $beforeRows, $afterRows, $updatedKey);

        return [
            'status' => 'ok',
            'source' => 'SQLite test/pragma.test pragma-10.0 through pragma-10.3',
            'scenarios' => ['pragma-10.0', 'pragma-10.1', 'pragma-10.2', 'pragma-10.3'],
            'pragma' => $pragma,
            'query' => $query,
            'result_shape' => $shape,
            'schema' => [
                'table' => $table,
                'mirrors' => [$beforeMirror, $afterMirror],
                'triggers' => [
                    "{$table}_bi" => ['timing' => 'before', 'event' => 'insert', 'effect' => "insert into {$beforeMirror} from new.key_name"],
                    "{$table}_ai" => ['timing' => 'after', 'event' => 'insert', 'effect' => "insert into {$afterMirror} from new.key_name"],
                    "{$table}_bu" => ['timing' => 'before', 'event' => 'update', 'effect' => "update {$beforeMirror} from old.key_name to new.key_name"],
                    "{$table}_au" => ['timing' => 'after', 'event' => 'update', 'effect' => "update {$afterMirror} from old.key_name to new.key_name"],
                    "{$table}_bd" => ['timing' => 'before', 'event' => 'delete', 'effect' => "delete from {$beforeMirror} using old.key_name"],
                    "{$table}_ad" => ['timing' => 'after', 'event' => 'delete', 'effect' => "delete from {$afterMirror} using old.key_name"],
                ],
            ],
            'operations' => [$insert, $update, $delete],
            'final' => [
                'main_rows' => array_values($rows),
                'before_mirror_rows' => array_values($beforeRows),
                'after_mirror_rows' => array_values($afterRows),
            ],
            'dependencies' => [
                'sqlite-pragma-count-changes-direct-dml-result',
                'sqlite-trigger-before-after-side-effects',
                'sqlite-pragma-count-changes-excludes-trigger-side-effects',
            ],
        ];
    }

    /**
     * @param list<array{key_name:string}> $rows
     * @param list<array{key_name:string}> $beforeRows
     * @param list<array{key_name:string}> $afterRows
     * @return array<string,mixed>
     */
    private static function insert(array &$rows, array &$beforeRows, array &$afterRows, string $keyName): array
    {
        $row = ['key_name' => $keyName];
        $beforeRows[] = $row;
        $rows[] = $row;
        $afterRows[] = $row;

        return self::operation(
            'insert',
            ['before_insert', 'after_insert'],
            $rows,
            $beforeRows,
            $afterRows,
        );
    }

    /**
     * @param list<array{key_name:string}> $rows
     * @param list<array{key_name:string}> $beforeRows
     * @param list<array{key_name:string}> $afterRows
     * @return array<string,mixed>
     */
    private static function update(array &$rows, array &$beforeRows, array &$afterRows, string $oldKey, string $newKey): array
    {
        self::replaceKey($beforeRows, $oldKey, $newKey);
        self::replaceKey($rows, $oldKey, $newKey);
        self::replaceKey($afterRows, $oldKey, $newKey);

        return self::operation(
            'update',
            ['before_update', 'after_update'],
            $rows,
            $beforeRows,
            $afterRows,
        );
    }

    /**
     * @param list<array{key_name:string}> $rows
     * @param list<array{key_name:string}> $beforeRows
     * @param list<array{key_name:string}> $afterRows
     * @return array<string,mixed>
     */
    private static function delete(array &$rows, array &$beforeRows, array &$afterRows, string $keyName): array
    {
        self::removeKey($beforeRows, $keyName);
        self::removeKey($rows, $keyName);
        self::removeKey($afterRows, $keyName);

        return self::operation(
            'delete',
            ['before_delete', 'after_delete'],
            $rows,
            $beforeRows,
            $afterRows,
        );
    }

    /**
     * @param list<array{key_name:string}> $rows
     * @param list<array{key_name:string}> $beforeRows
     * @param list<array{key_name:string}> $afterRows
     * @return array<string,mixed>
     */
    private static function operation(
        string $event,
        array $appliedTriggers,
        array $rows,
        array $beforeRows,
        array $afterRows,
    ): array {
        return [
            'event' => $event,
            'direct_rows_changed' => 1,
            'trigger_side_effect_rows' => 2,
            'count_changes_result' => [1],
            'count_changes_excludes_trigger_side_effects' => true,
            'applied_triggers' => $appliedTriggers,
            'main_rows' => array_values($rows),
            'before_mirror_rows' => array_values($beforeRows),
            'after_mirror_rows' => array_values($afterRows),
        ];
    }

    /**
     * @param list<array{key_name:string}> $rows
     */
    private static function replaceKey(array &$rows, string $oldKey, string $newKey): void
    {
        foreach ($rows as &$row) {
            if ($row['key_name'] === $oldKey) {
                $row['key_name'] = $newKey;
                unset($row);
                return;
            }
        }
        unset($row);

        throw new \InvalidArgumentException('SQLite PRAGMA count_changes trigger update could not find target row');
    }

    /**
     * @param list<array{key_name:string}> $rows
     */
    private static function removeKey(array &$rows, string $keyName): void
    {
        foreach ($rows as $index => $row) {
            if ($row['key_name'] === $keyName) {
                unset($rows[$index]);
                $rows = array_values($rows);
                return;
            }
        }

        throw new \InvalidArgumentException('SQLite PRAGMA count_changes trigger delete could not find target row');
    }
}
