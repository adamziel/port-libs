<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBulkImportSavepointPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array{name?:string,rows:list<array<string,mixed>>,on_conflict?:string,release?:bool}> $batches
     * @param array{database_path?:string,page_size?:int,journal_mode?:string,sync_mode?:string,replace_conflicts?:bool} $options
     * @return array<string,mixed>
     */
    public static function plan(array $currentRows, array $batches, array $options = []): array
    {
        if ($batches === []) {
            throw new \InvalidArgumentException('SQLite Application bulk import savepoint plan requires at least one batch');
        }

        $databasePath = (string) ($options['database_path'] ?? '/tmp/wp-bulk-import.sqlite');
        $pageSize = (int) ($options['page_size'] ?? 4096);
        $journalMode = strtolower((string) ($options['journal_mode'] ?? 'delete'));
        $syncMode = strtolower((string) ($options['sync_mode'] ?? 'full'));
        $replaceConflicts = (bool) ($options['replace_conflicts'] ?? false);

        $basePlan = SQLiteImportTransactionPlan::plan($currentRows, [], [
            'database_path' => $databasePath,
            'page_size' => $pageSize,
            'journal_mode' => $journalMode,
            'sync_mode' => $syncMode,
            'replace_conflicts' => $replaceConflicts,
        ]);

        $visibleRows = self::rowsById($basePlan['final_rows']);
        $releasedRows = $visibleRows;
        $batchPlans = [];
        $releasedNames = [];
        $rolledBackNames = [];
        $dirtyPages = [];
        $savepointStack = new SQLiteSavepointStack();
        $savepointStack->beginTransaction('wp_bulk_import');
        $maxWalFrame = 0;

        foreach (array_values($batches) as $batchIndex => $batch) {
            $name = self::batchName($batch, $batchIndex);
            $onConflict = strtolower((string) ($batch['on_conflict'] ?? 'rollback'));
            if (!in_array($onConflict, ['rollback', 'abort'], true)) {
                throw new \InvalidArgumentException('SQLite Application bulk import savepoint conflict action must be rollback or abort');
            }

            $savepointStack->savepoint($name);
            $beforeRows = $visibleRows;
            $beforeNames = array_column(array_values($beforeRows), 'option_name');
            $stagePlan = null;
            $error = null;
            try {
                $stagePlan = SQLiteImportTransactionPlan::plan(array_values($visibleRows), $batch['rows'], [
                    'database_path' => $databasePath,
                    'page_size' => $pageSize,
                    'journal_mode' => $journalMode,
                    'sync_mode' => $syncMode,
                    'replace_conflicts' => $replaceConflicts,
                ]);
                $visibleRows = self::rowsById($stagePlan['final_rows']);
                foreach ($stagePlan['dirty_pages'] as $pageNumber) {
                    $savepointStack->recordPageImageWrite((int) $pageNumber, self::pageImage($name, (int) $pageNumber));
                    $savepointStack->recordWalFrameWrite(++$maxWalFrame, (int) $pageNumber, false);
                    $dirtyPages[(int) $pageNumber] = true;
                }
            } catch (\LogicException $exception) {
                $error = $exception->getMessage();
                if ($onConflict === 'abort') {
                    throw $exception;
                }
            }

            if ($error !== null) {
                $rollbackPlan = $savepointStack->rollbackToWithPlan($name);
                $visibleRows = $beforeRows;
                $rolledBackNames[] = $name;
                $batchPlans[] = [
                    'name' => $name,
                    'status' => 'rolled_back',
                    'error' => $error,
                    'before_names' => $beforeNames,
                    'after_names' => array_column(array_values($visibleRows), 'option_name'),
                    'updated' => 0,
                    'inserted' => 0,
                    'deleted' => 0,
                    'dirty_pages' => [],
                    'rollback_page_numbers' => $rollbackPlan['rollback_page_numbers'],
                    'retained_depth' => $rollbackPlan['retained_depth'],
                    'released' => false,
                ];
                continue;
            }

            $shouldRelease = (bool) ($batch['release'] ?? true);
            if ($shouldRelease) {
                $savepointStack->release($name);
                $releasedRows = $visibleRows;
                $releasedNames[] = $name;
            }

            $batchPlans[] = [
                'name' => $name,
                'status' => $shouldRelease ? 'released' : 'open',
                'error' => null,
                'before_names' => $beforeNames,
                'after_names' => array_column(array_values($visibleRows), 'option_name'),
                'updated' => count($stagePlan['updated']),
                'inserted' => count($stagePlan['inserted']),
                'deleted' => count($stagePlan['deleted']),
                'dirty_pages' => $stagePlan['dirty_pages'],
                'rollback_page_numbers' => [],
                'retained_depth' => null,
                'released' => $shouldRelease,
            ];
        }

        ksort($dirtyPages);

        return [
            'status' => 'planned',
            'database_path' => $databasePath,
            'page_size' => $pageSize,
            'journal_mode' => $journalMode,
            'sync_mode' => $syncMode,
            'batch_count' => count($batchPlans),
            'released_batches' => $releasedNames,
            'rolled_back_batches' => $rolledBackNames,
            'batches' => $batchPlans,
            'current_rows' => array_values($currentRows),
            'final_rows' => array_values($visibleRows),
            'released_rows' => array_values($releasedRows),
            'final_option_names' => array_column(array_values($visibleRows), 'option_name'),
            'released_option_names' => array_column(array_values($releasedRows), 'option_name'),
            'dirty_pages' => array_map('intval', array_keys($dirtyPages)),
            'journal_bytes' => $dirtyPages === [] ? 0 : 28 + (count($dirtyPages) * ($pageSize + 8)),
            'dependencies' => [
                'sqlite-application-bulk-import-savepoint-current',
                'sqlite-application-import-transaction-current',
                'sqlite-savepoint-current-rollback',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $batch
     */
    private static function batchName(array $batch, int $index): string
    {
        $name = (string) ($batch['name'] ?? 'wp_bulk_' . ($index + 1));
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('SQLite Application bulk import savepoint names must be SQL identifiers');
        }

        if (!isset($batch['rows']) || !is_array($batch['rows'])) {
            throw new \InvalidArgumentException('SQLite Application bulk import savepoint batch rows must be a list');
        }

        return $name;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function rowsById(array $rows): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $id = $row['option_id'] ?? null;
            if (!is_int($id)) {
                throw new \InvalidArgumentException('SQLite Application bulk import rows require integer option_id values');
            }
            $byId[$id] = $row;
        }
        ksort($byId);

        return $byId;
    }

    private static function pageImage(string $savepoint, int $pageNumber): string
    {
        return str_pad($savepoint . ':before:' . $pageNumber, 512, '.', STR_PAD_RIGHT);
    }
}
