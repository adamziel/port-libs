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
     * @return array{source:string,journal_mode:string,database:string,callback_context:string,replacement:string,hook_active:bool,replaced_previous_hook:bool,autocheckpoint_replaced_hook:bool,callback_invoked_count:int,events:list<array<string,mixed>>,commit_persisted:bool,post_commit_readable:bool,statement_result:string,statement_error:?string,callback_return:int,dependencies:list<string>}
     */
    public static function hookDispatchPlan(
        SQLiteWal $wal,
        string $databaseName = 'main',
        string $journalMode = 'wal',
        int $callbackReturn = 0,
        string $replacement = 'none',
        string $callbackContext = 'registered-wal-hook'
    ): array {
        self::assertDatabaseName($databaseName);
        if ($callbackReturn < 0) {
            throw new \InvalidArgumentException('SQLite WAL hook callback return code must be non-negative');
        }

        $journalMode = strtolower(trim($journalMode));
        if ($journalMode === '') {
            throw new \InvalidArgumentException('SQLite WAL hook journal mode must be non-empty');
        }

        $replacement = strtolower(trim($replacement));
        if (!in_array($replacement, ['none', 'wal_hook', 'wal_autocheckpoint'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL hook replacement: {$replacement}");
        }

        $callbackContext = trim($callbackContext);
        if ($callbackContext === '') {
            throw new \InvalidArgumentException('SQLite WAL hook callback context must be non-empty');
        }

        $walMode = $journalMode === 'wal';
        $replaced = $replacement !== 'none';
        $hookActive = $walMode && !$replaced;
        $transactions = $wal->committedTransactions();
        $commitPersisted = count($transactions) > 0;
        $events = $hookActive ? self::commitHookEvents($wal, $databaseName, $callbackReturn) : [];
        $statementError = $hookActive && $callbackReturn !== 0 && $commitPersisted
            ? self::callbackReturnMessage($callbackReturn)
            : null;

        if ($statementError !== null) {
            $statementResult = 'callback-error-after-commit';
            $source = 'upstream e_walhook.test 4.1 through 4.4 callback return code propagates while commit persists';
        } elseif (!$walMode) {
            $statementResult = 'rollback-mode-no-wal-hook';
            $source = 'upstream e_walhook.test 1.1.1 through 1.1.2 WAL hook is not invoked before WAL mode';
        } elseif ($replacement === 'wal_hook') {
            $statementResult = 'callback-replaced-by-wal-hook';
            $source = 'upstream e_walhook.test 5.1 through 5.2 sqlite3_wal_hook replaces a prior callback';
        } elseif ($replacement === 'wal_autocheckpoint') {
            $statementResult = 'callback-replaced-by-autocheckpoint';
            $source = 'upstream e_walhook.test 6.1.1 through 6.1.2 wal_autocheckpoint overwrites a prior WAL hook';
        } else {
            $statementResult = 'ok';
            $source = $databaseName === 'aux'
                ? 'upstream e_walhook.test 3.1.1 through 3.1.2 hook receives attached database name and frame count'
                : 'upstream e_walhook.test 1.3, 1.4, 2.1, and 3.2 hook fires after WAL commit with frame count';
        }

        return [
            'source' => $source,
            'journal_mode' => $journalMode,
            'database' => $databaseName,
            'callback_context' => $callbackContext,
            'replacement' => $replacement,
            'hook_active' => $hookActive,
            'replaced_previous_hook' => $replaced,
            'autocheckpoint_replaced_hook' => $replacement === 'wal_autocheckpoint',
            'callback_invoked_count' => count($events),
            'events' => $events,
            'commit_persisted' => $commitPersisted,
            'post_commit_readable' => $hookActive && $commitPersisted,
            'statement_result' => $statementResult,
            'statement_error' => $statementError,
            'callback_return' => $callbackReturn,
            'dependencies' => [
                'sqlite-upstream-e-walhook-test',
                'sqlite-wal-hook-commit-events',
                'sqlite-wal-hook-dispatch',
            ],
        ];
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

    private static function callbackReturnMessage(int $code): string
    {
        return match ($code) {
            1 => 'SQL logic error',
            5 => 'database is locked',
            14 => 'unable to open database file',
            default => "SQLite error code {$code}",
        };
    }
}
