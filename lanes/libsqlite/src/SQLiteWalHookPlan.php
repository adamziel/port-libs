<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHookPlan
{
    /**
     * @return list<array{database:string,frame_count:int,database_page_count:int,transaction_index:int,first_frame:int,last_frame:int,page_numbers:list<int>,callback_return:int}>
     */
    public static function commitHookEvents(SQLiteWal $wal, string $databaseName = 'main', int $callbackReturn = 0): array
    {
        self::assertDatabaseName($databaseName);

        $events = [];
        foreach ($wal->committedTransactions() as $index => $transaction) {
            $events[] = [
                'database' => $databaseName,
                'frame_count' => $transaction['last_frame'],
                'database_page_count' => $transaction['database_page_count'],
                'transaction_index' => $index + 1,
                'first_frame' => $transaction['first_frame'],
                'last_frame' => $transaction['last_frame'],
                'page_numbers' => $transaction['page_numbers'],
                'callback_return' => $callbackReturn,
            ];
        }

        return $events;
    }

    /**
     * @return array{threshold:int,mode:string,database:string,event_count:int,events:list<array<string,mixed>>,checkpoint_events:list<array<string,mixed>>,final_database_page_count:int,final_wal_action:string,dependencies:list<string>}
     */
    public static function autocheckpointEvents(
        SQLiteWal $wal,
        string $databaseBytes,
        int $threshold = 1000,
        string $mode = 'passive',
        string $databaseName = 'main'
    ): array {
        if ($threshold < 0) {
            throw new \InvalidArgumentException('SQLite WAL autocheckpoint threshold must be non-negative');
        }
        self::assertDatabaseName($databaseName);

        $hookEvents = self::commitHookEvents($wal, $databaseName);
        $checkpointEvents = [];
        $lastResult = $wal->durableCheckpointResult($databaseBytes, $mode);

        foreach ($hookEvents as $event) {
            $autocheckpoint = $threshold > 0 && $event['frame_count'] >= $threshold;
            $row = $event + [
                'autocheckpoint' => $autocheckpoint,
                'threshold' => $threshold,
                'mode' => $mode,
                'checkpoint' => null,
            ];

            if ($autocheckpoint) {
                $checkpoint = $wal->checkpointModeResult($databaseBytes, $mode);
                $row['checkpoint'] = [
                    'busy' => $checkpoint['busy'],
                    'reason' => $checkpoint['reason'],
                    'checkpointed_frame_count' => $checkpoint['checkpointed_frame_count'],
                    'remaining_committed_frame_count' => $checkpoint['remaining_committed_frame_count'],
                    'wal_action' => $checkpoint['wal_action'],
                    'database_page_count' => $checkpoint['database_page_count'],
                ];
                $checkpointEvents[] = $row;
                $lastResult = $wal->durableCheckpointResult($databaseBytes, $mode);
            }

            $events[] = $row;
        }

        return [
            'threshold' => $threshold,
            'mode' => strtolower($mode),
            'database' => $databaseName,
            'event_count' => count($events ?? []),
            'events' => $events ?? [],
            'checkpoint_events' => $checkpointEvents,
            'final_database_page_count' => $lastResult['database_page_count'],
            'final_wal_action' => $lastResult['wal_action'],
            'dependencies' => [
                'sqlite-upstream-walhook-test',
                'sqlite-wal-commit-hook-events',
                'sqlite-wal-autocheckpoint-events',
            ],
        ];
    }

    private static function assertDatabaseName(string $databaseName): void
    {
        if ($databaseName === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $databaseName) !== 1) {
            throw new \InvalidArgumentException('SQLite WAL hook database name must be a simple SQLite schema identifier');
        }
    }
}
