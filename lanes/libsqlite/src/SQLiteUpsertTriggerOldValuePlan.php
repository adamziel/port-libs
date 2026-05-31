<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpsertTriggerOldValuePlan
{
    /**
     * @param list<array{x:int,y:string}> $sourceRows
     * @return array{after:list<array{x:int,y:string>>,inserted:list<array{x:int,y:string>>,updated:list<array{x:int,y:string>>,returning:list<array{x:int,y:string,event:string>>,trigger_events:list<array{x:int,old_y:string,new_y:string,matched:bool>>,changes:int}
     */
    public static function execute(array $sourceRows): array
    {
        self::validateSourceRows($sourceRows);

        $targetRows = [];
        $inserted = [];
        $updated = [];
        $returning = [];
        $triggerEvents = [];

        foreach ($sourceRows as $source) {
            $index = self::findByKey($targetRows, $source['x']);
            if ($index === null) {
                $targetRows[] = $source;
                $inserted[] = $source;
                $returning[] = $source + ['event' => 'insert'];
                continue;
            }

            $old = $targetRows[$index];
            $new = ['x' => $old['x'], 'y' => $source['y']];
            $matched = $old['y'] === $new['y'];
            $triggerEvents[] = [
                'x' => $old['x'],
                'old_y' => $old['y'],
                'new_y' => $new['y'],
                'matched' => $matched,
            ];
            if (!$matched) {
                throw new \RuntimeException('Incorrect old.y value passed to trigger');
            }

            $targetRows[$index] = $new;
            $updated[] = $new;
            $returning[] = $new + ['event' => 'update'];
        }

        usort($targetRows, static fn (array $left, array $right): int => $left['x'] <=> $right['x']);

        return [
            'after' => $targetRows,
            'inserted' => $inserted,
            'updated' => $updated,
            'returning' => $returning,
            'trigger_events' => $triggerEvents,
            'changes' => count($inserted) + count($updated),
        ];
    }

    /**
     * @param list<array{x:int,y:string}> $rows
     */
    private static function findByKey(array $rows, int $key): ?int
    {
        foreach ($rows as $index => $row) {
            if ($row['x'] === $key) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<array{x:int,y:string}> $rows
     */
    private static function validateSourceRows(array $rows): void
    {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite UPSERT trigger source rows must be a list');
        }

        foreach ($rows as $row) {
            if (!array_key_exists('x', $row) || !is_int($row['x'])) {
                throw new \InvalidArgumentException('SQLite UPSERT trigger source row requires integer x');
            }
            if (!array_key_exists('y', $row) || !is_string($row['y'])) {
                throw new \InvalidArgumentException('SQLite UPSERT trigger source row requires text y');
            }
        }
    }
}
