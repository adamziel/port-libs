<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointInterfacePlan
{
    /**
     * @param array<string, array<string, mixed>> $databaseStates
     * @return array<string, mixed>
     */
    public static function checkpointInterfacePlan(string|int|null $databaseName, string|int $mode, array $databaseStates): array
    {
        $modePlan = self::checkpointModeValidation($mode);
        if (!$modePlan['accepted_by_sqlite']) {
            return [
                'status' => 'checkpoint-mode-misuse',
                'mode' => $modePlan['mode_name'],
                'mode_value' => $modePlan['mode_value'],
                'target_database' => $databaseName,
                'target_databases' => [],
                'attempted_databases' => [],
                'changed_files' => [],
                'wal_sizes_after' => [],
                'result_code' => 'SQLITE_MISUSE',
                'result' => [1, -1, -1],
                'error_message' => 'SQLITE_MISUSE - not an error',
                'busy_handler_invoked' => false,
                'aborted_on' => null,
                'unknown_database' => null,
                'results_by_database' => [],
                'source' => 'upstream e_walckpt.test 4.* checkpoint mode validation',
                'dependencies' => ['real-upstream-corpus-e-walckpt', 'sqlite-wal-checkpoint-interface'],
            ];
        }

        $states = self::normalizeDatabaseStates($databaseStates);
        $target = $databaseName === '' ? null : ($databaseName === null ? null : (string) $databaseName);
        if ($target !== null && !array_key_exists($target, $states)) {
            return [
                'status' => 'checkpoint-unknown-database',
                'mode' => $modePlan['mode_name'],
                'mode_value' => $modePlan['mode_value'],
                'target_database' => $target,
                'target_databases' => [],
                'attempted_databases' => [],
                'changed_files' => [],
                'wal_sizes_after' => self::walSizes($states),
                'result_code' => 'SQLITE_ERROR',
                'result' => [1, -1, -1],
                'error_message' => 'unknown database: ' . $target,
                'busy_handler_invoked' => false,
                'aborted_on' => null,
                'unknown_database' => $target,
                'results_by_database' => [],
                'source' => 'upstream e_walckpt.test 2.1 unknown checkpoint target database',
                'dependencies' => ['real-upstream-corpus-e-walckpt', 'sqlite-wal-checkpoint-interface'],
            ];
        }

        $targets = $target === null
            ? array_values(array_filter(array_keys($states), static fn (string $name): bool => $states[$name]['wal_mode']))
            : [$target];

        $attempted = [];
        $changed = [];
        $results = [];
        $resultCode = 'SQLITE_OK';
        $status = 'checkpoint-ok';
        $abortedOn = null;
        $logFrames = -1;
        $checkpointedFrames = -1;
        $walSizes = self::walSizes($states);

        foreach ($targets as $name) {
            $state = $states[$name];
            $attempted[] = $name;

            if (!$state['wal_mode']) {
                $results[$name] = [
                    'result_code' => 'SQLITE_OK',
                    'log_frames' => -1,
                    'checkpointed_frames' => -1,
                    'changed' => false,
                    'wal_size_after' => 0,
                ];
                continue;
            }

            $logFrames = max($logFrames, $state['wal_frames']);

            if ($state['io_error']) {
                $resultCode = 'SQLITE_IOERR';
                $status = 'checkpoint-io-error';
                $abortedOn = $name;
                $results[$name] = [
                    'result_code' => 'SQLITE_IOERR',
                    'log_frames' => $state['wal_frames'],
                    'checkpointed_frames' => $state['checkpointed_frames'],
                    'changed' => false,
                    'wal_size_after' => $walSizes[$name],
                ];
                break;
            }

            if ($state['busy']) {
                $resultCode = 'SQLITE_BUSY';
                $status = 'checkpoint-busy';
                $checkpointedFrames = max($checkpointedFrames, $state['checkpointed_frames']);
                $results[$name] = [
                    'result_code' => 'SQLITE_BUSY',
                    'log_frames' => $state['wal_frames'],
                    'checkpointed_frames' => $state['checkpointed_frames'],
                    'changed' => false,
                    'wal_size_after' => $walSizes[$name],
                ];
                continue;
            }

            $checkpointed = $state['wal_frames'];
            $checkpointedFrames = max($checkpointedFrames, $checkpointed);
            $changed[] = $state['filename'];
            $walSizes[$name] = $modePlan['mode_name'] === 'truncate' ? 0 : self::walFileSize($state['wal_frames'], $state['page_size']);
            $results[$name] = [
                'result_code' => 'SQLITE_OK',
                'log_frames' => $state['wal_frames'],
                'checkpointed_frames' => $checkpointed,
                'changed' => true,
                'wal_size_after' => $walSizes[$name],
            ];
        }

        if ($targets === [] || ($target !== null && isset($states[$target]) && !$states[$target]['wal_mode'])) {
            $status = 'checkpoint-target-not-wal';
            $logFrames = -1;
            $checkpointedFrames = -1;
        }

        if ($resultCode === 'SQLITE_OK' && $modePlan['mode_name'] === 'truncate' && $checkpointedFrames >= 0) {
            $logFrames = 0;
            $checkpointedFrames = 0;
        }

        return [
            'status' => $status,
            'mode' => $modePlan['mode_name'],
            'mode_value' => $modePlan['mode_value'],
            'target_database' => $target,
            'target_databases' => $targets,
            'attempted_databases' => $attempted,
            'changed_files' => $changed,
            'wal_sizes_after' => $walSizes,
            'result_code' => $resultCode,
            'result' => [self::resultCode($resultCode), $logFrames, $checkpointedFrames],
            'error_message' => $resultCode === 'SQLITE_OK' ? null : ($resultCode . ' - ' . ($resultCode === 'SQLITE_IOERR' ? 'disk I/O error' : 'database is locked')),
            'busy_handler_invoked' => false,
            'aborted_on' => $abortedOn,
            'unknown_database' => null,
            'results_by_database' => $results,
            'source' => 'upstream e_walckpt.test 1.* 2.* 4.* 5.* attached WAL checkpoint interface behavior',
            'dependencies' => ['real-upstream-corpus-e-walckpt', 'sqlite-wal-checkpoint-interface'],
        ];
    }

    /**
     * @return array{status:string,mode_value:int,mode_name:string|null,documented_valid_mode:bool,accepted_by_sqlite:bool,result_code:string,result:array<int, int|string>,source:string,dependencies:list<string>}
     */
    public static function checkpointModeValidation(string|int $mode): array
    {
        $value = is_int($mode) ? $mode : (is_numeric($mode) ? (int) $mode : self::modeValue($mode));
        $names = [
            -1 => 'passive',
            0 => 'passive',
            1 => 'full',
            2 => 'restart',
            3 => 'truncate',
        ];
        $accepted = array_key_exists($value, $names);
        $documented = in_array($value, [0, 1, 2, 3], true);

        return [
            'status' => $accepted ? 'checkpoint-mode-accepted' : 'checkpoint-mode-misuse',
            'mode_value' => $value,
            'mode_name' => $names[$value] ?? null,
            'documented_valid_mode' => $documented,
            'accepted_by_sqlite' => $accepted,
            'result_code' => $accepted ? 'SQLITE_OK' : 'SQLITE_MISUSE',
            'result' => $accepted ? [0, -1, -1] : [1, 'SQLITE_MISUSE - not an error'],
            'source' => 'upstream e_walckpt.test 4.0 through 4.9 checkpoint mode validation',
            'dependencies' => ['real-upstream-corpus-e-walckpt', 'sqlite-wal-checkpoint-interface'],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $databaseStates
     * @return array<string, array{filename:string,wal_mode:bool,wal_frames:int,checkpointed_frames:int,busy:bool,io_error:bool,page_size:int}>
     */
    private static function normalizeDatabaseStates(array $databaseStates): array
    {
        $states = [];
        foreach ($databaseStates as $name => $state) {
            $walFrames = (int) ($state['wal_frames'] ?? 0);
            $checkpointedFrames = (int) ($state['checkpointed_frames'] ?? 0);
            $pageSize = (int) ($state['page_size'] ?? 1024);
            if ($walFrames < 0 || $checkpointedFrames < 0 || $pageSize < 512) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint interface states require non-negative frame counts and a valid page size');
            }

            $states[(string) $name] = [
                'filename' => (string) ($state['filename'] ?? ((string) $name . '.db')),
                'wal_mode' => (bool) ($state['wal_mode'] ?? false),
                'wal_frames' => $walFrames,
                'checkpointed_frames' => min($checkpointedFrames, $walFrames),
                'busy' => (bool) ($state['busy'] ?? false),
                'io_error' => (bool) ($state['io_error'] ?? false),
                'page_size' => $pageSize,
            ];
        }

        return $states;
    }

    /**
     * @param array<string, array{filename:string,wal_mode:bool,wal_frames:int,checkpointed_frames:int,busy:bool,io_error:bool,page_size:int}> $states
     * @return array<string, int>
     */
    private static function walSizes(array $states): array
    {
        $sizes = [];
        foreach ($states as $name => $state) {
            $sizes[$name] = $state['wal_mode'] ? self::walFileSize($state['wal_frames'], $state['page_size']) : 0;
        }

        return $sizes;
    }

    private static function walFileSize(int $frames, int $pageSize): int
    {
        return $frames <= 0 ? 0 : 32 + ($frames * ($pageSize + 24));
    }

    private static function resultCode(string $resultCode): int
    {
        return $resultCode === 'SQLITE_OK' ? 0 : 1;
    }

    private static function modeValue(string $mode): int
    {
        return match (strtolower(trim($mode))) {
            'passive' => 0,
            'full' => 1,
            'restart' => 2,
            'truncate' => 3,
            default => throw new \InvalidArgumentException("Unsupported SQLite WAL checkpoint mode: {$mode}"),
        };
    }
}
