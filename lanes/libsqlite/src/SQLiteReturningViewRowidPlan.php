<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteReturningViewRowidPlan
{
    private const SOURCE = 'returning1.test returning1-10.1..10.4';
    private const ROWID_ERROR = 'no such column: new.rowid';

    /**
     * @param list<array{a:mixed,b:mixed}> $baseRows
     * @return array<string,mixed>
     */
    public static function insertViewReturningRowid(array $baseRows, mixed $a, mixed $b, bool $allowRowidInView): array
    {
        $rows = self::normalizeRows($baseRows);

        if (!$allowRowidInView) {
            return self::statementResult(
                'insert',
                false,
                [],
                [],
                $rows,
                0,
                self::ROWID_ERROR
            );
        }

        $after = $rows;
        $after[] = ['a' => $a, 'b' => $b];

        return self::statementResult(
            'insert',
            true,
            [['rowid' => -1]],
            [['op' => 'insert', 'rowid' => -1, 'a' => $a, 'b' => $b]],
            $after,
            1,
            null
        );
    }

    /**
     * @param list<array{a:mixed,b:mixed}> $baseRows
     * @return array<string,mixed>
     */
    public static function updateViewReturningRowid(array $baseRows, mixed $newA, mixed $whereB, bool $allowRowidInView): array
    {
        $rows = self::normalizeRows($baseRows);

        if (!$allowRowidInView) {
            return self::statementResult(
                'update',
                false,
                [],
                [],
                $rows,
                0,
                self::ROWID_ERROR
            );
        }

        $after = [];
        $returning = [];
        $log = [];

        foreach ($rows as $row) {
            if ($row['b'] === $whereB) {
                $row['a'] = $newA;
                $returning[] = ['rowid' => null];
                $log[] = ['op' => 'update', 'rowid' => null, 'a' => $newA, 'b' => $whereB];
            }

            $after[] = $row;
        }

        return self::statementResult(
            'update',
            true,
            $returning,
            $log,
            $after,
            count($returning),
            null
        );
    }

    /**
     * @param list<array{a:mixed,b:mixed}> $baseRows
     * @param array{a:mixed,b:mixed} $insertRow
     * @return array<string,mixed>
     */
    public static function returning1Section10Plan(
        array $baseRows,
        array $insertRow,
        mixed $updateA,
        mixed $updateWhereB,
        bool $allowRowidInView
    ): array {
        if (!array_key_exists('a', $insertRow) || !array_key_exists('b', $insertRow)) {
            throw new \InvalidArgumentException('SQLite returning view insert row is malformed');
        }

        $insert = self::insertViewReturningRowid($baseRows, $insertRow['a'], $insertRow['b'], $allowRowidInView);
        $update = self::updateViewReturningRowid(
            $insert['rows_after_statement'],
            $updateA,
            $updateWhereB,
            $allowRowidInView
        );

        $errors = [];
        foreach ([$insert['error'], $update['error']] as $error) {
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return [
            'source' => self::SOURCE,
            'operation' => 'returning-view-rowid-instead-of-trigger',
            'status' => $errors === [] ? 'commit-ok' : 'schema-error',
            'allow_rowid_in_view' => $allowRowidInView,
            'insert' => $insert,
            'update' => $update,
            'returning' => [
                'insert' => $insert['returning'],
                'update' => $update['returning'],
            ],
            'trigger_log' => array_merge($insert['trigger_log'], $update['trigger_log']),
            'rows_after_statement' => $update['rows_after_statement'],
            'changes' => $insert['changes'] + $update['changes'],
            'errors' => $errors,
            'dependencies' => self::dependencies(),
        ];
    }

    /**
     * @param list<array{a:mixed,b:mixed}> $rows
     * @return list<array{a:mixed,b:mixed}>
     */
    private static function normalizeRows(array $rows): array
    {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite returning view rows must be a list');
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists('a', $row) || !array_key_exists('b', $row)) {
                throw new \InvalidArgumentException('SQLite returning view row is malformed');
            }

            $normalized[] = ['a' => $row['a'], 'b' => $row['b']];
        }

        return $normalized;
    }

    /**
     * @param list<array{rowid:mixed}> $returning
     * @param list<array{op:string,rowid:mixed,a:mixed,b:mixed}> $triggerLog
     * @param list<array{a:mixed,b:mixed}> $after
     * @return array<string,mixed>
     */
    private static function statementResult(
        string $statement,
        bool $allowRowidInView,
        array $returning,
        array $triggerLog,
        array $after,
        int $changes,
        ?string $error
    ): array {
        return [
            'source' => self::SOURCE,
            'statement' => $statement,
            'status' => $error === null ? 'commit-ok' : 'schema-error',
            'allow_rowid_in_view' => $allowRowidInView,
            'returning' => $returning,
            'trigger_log' => $triggerLog,
            'rows_after_statement' => $after,
            'changes' => $changes,
            'error' => $error,
            'dependencies' => self::dependencies(),
        ];
    }

    /**
     * @return list<string>
     */
    private static function dependencies(): array
    {
        return [
            'sqlite-returning-view-rowid-instead-of-insert-trigger',
            'sqlite-returning-view-rowid-instead-of-update-trigger',
            'sqlite-returning-view-rowid-disabled-new-rowid-error',
            'sqlite-returning-view-rowid-enabled-insert-minus-one',
            'sqlite-returning-view-rowid-enabled-update-null-rowids',
            'sqlite-returning-view-rowid-trigger-log-order',
        ];
    }
}
