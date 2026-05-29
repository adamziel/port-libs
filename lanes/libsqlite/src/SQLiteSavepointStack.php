<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSavepointStack
{
    /**
     * @var list<array{name:string,transaction:bool,pages:array<int,true>,page_images:array<int,string>,wal_start_frame:int,wal_frames:array<int,array{page_number:int,commit_frame:bool}>}>
     */
    private array $frames = [];
    private int $maxWalFrame = 0;
    /**
     * @var array<string,array{name:string,frame_index:int,savepoint_name:string,wal_start_frame:int,prior_pages:array<int,true>,prior_page_images:array<int,string>,page_images:array<int,string>,wal_frames:array<int,array{page_number:int,commit_frame:bool}>}>
     */
    private array $statementJournals = [];

    public function beginTransaction(string $name = 'transaction'): void
    {
        if ($this->frames !== []) {
            throw new \LogicException('SQLite transaction is already active');
        }

        $this->frames[] = [
            'name' => $name,
            'transaction' => true,
            'pages' => [],
            'page_images' => [],
            'wal_start_frame' => $this->maxWalFrame,
            'wal_frames' => [],
        ];
    }

    public function savepoint(string $name): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite savepoint name must not be empty');
        }

        if ($this->frames === []) {
            $this->beginTransaction($name);
            return;
        }

        $this->frames[] = [
            'name' => $name,
            'transaction' => false,
            'pages' => [],
            'page_images' => [],
            'wal_start_frame' => $this->maxWalFrame,
            'wal_frames' => [],
        ];
    }

    public function recordPageWrite(int $pageNumber): void
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }

        if ($this->frames === []) {
            $this->beginTransaction();
        }

        $this->frames[array_key_last($this->frames)]['pages'][$pageNumber] = true;
    }

    public function recordPageImageWrite(int $pageNumber, string $beforeImage): void
    {
        if ($beforeImage === '') {
            throw new \InvalidArgumentException('SQLite savepoint page images must not be empty');
        }

        $this->recordPageWrite($pageNumber);
        $lastKey = array_key_last($this->frames);
        if (!isset($this->frames[$lastKey]['page_images'][$pageNumber])) {
            $this->frames[$lastKey]['page_images'][$pageNumber] = $beforeImage;
        }
    }

    public function recordWalFrameWrite(int $frameIndex, int $pageNumber, bool $commitFrame = false): void
    {
        if ($frameIndex < 1) {
            throw new \InvalidArgumentException('SQLite WAL frame indexes are one-based');
        }
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }
        if ($frameIndex <= $this->maxWalFrame) {
            throw new \InvalidArgumentException('SQLite WAL frame indexes must be recorded in increasing order');
        }

        if ($this->frames === []) {
            $this->beginTransaction();
        }

        $this->maxWalFrame = $frameIndex;
        $lastKey = array_key_last($this->frames);
        $this->frames[$lastKey]['pages'][$pageNumber] = true;
        $this->frames[$lastKey]['wal_frames'][$frameIndex] = [
            'page_number' => $pageNumber,
            'commit_frame' => $commitFrame,
        ];
    }

    public function rollbackTo(string $name): void
    {
        $index = $this->findFrame($name);
        $this->maxWalFrame = $this->frames[$index]['wal_start_frame'];
        $this->frames = array_slice($this->frames, 0, $index + 1);
        $this->frames[$index]['pages'] = [];
        $this->frames[$index]['page_images'] = [];
        $this->frames[$index]['wal_frames'] = [];
        $this->clearStatementJournalsFromFrame($index);
    }

    /**
     * @return array{savepoint:string,found_index:int,retained_depth:int,discarded_frame_names:list<string>,rollback_page_numbers:list<int>,target_frame_cleared:bool,transaction_active_after:bool}
     */
    public function rollbackToWithPlan(string $name): array
    {
        $plan = $this->rollbackToPlan($name);
        $this->rollbackTo($name);

        return $plan;
    }

    /**
     * @return array{savepoint:string,found_index:int,retained_depth:int,discarded_frame_names:list<string>,rollback_page_numbers:list<int>,target_frame_cleared:bool,transaction_active_after:bool}
     */
    public function rollbackToPlan(string $name): array
    {
        $index = $this->findFrame($name);
        $discardedFrameNames = [];
        for ($frameIndex = $index + 1; $frameIndex < count($this->frames); $frameIndex++) {
            $discardedFrameNames[] = $this->frames[$frameIndex]['name'];
        }

        return [
            'savepoint' => $name,
            'found_index' => $index,
            'retained_depth' => $index + 1,
            'discarded_frame_names' => $discardedFrameNames,
            'rollback_page_numbers' => $this->rollbackToPageNumbers($name),
            'target_frame_cleared' => true,
            'transaction_active_after' => true,
        ];
    }

    /**
     * @return array{savepoint:string,rollback_to_frame:int,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool,frame_name:string}>,discarded_page_numbers:list<int>,transaction_active_after:bool}
     */
    public function walRollbackToPlan(string $name): array
    {
        $index = $this->findFrame($name);
        $discardedFrames = [];
        $discardedPages = [];
        for ($frameIndex = $index; $frameIndex < count($this->frames); $frameIndex++) {
            foreach ($this->frames[$frameIndex]['wal_frames'] as $walFrameIndex => $walFrame) {
                $discardedFrames[] = [
                    'frame_index' => $walFrameIndex,
                    'page_number' => $walFrame['page_number'],
                    'commit_frame' => $walFrame['commit_frame'],
                    'frame_name' => $this->frames[$frameIndex]['name'],
                ];
                $discardedPages[$walFrame['page_number']] = true;
            }
        }

        usort($discardedFrames, static fn (array $left, array $right): int => $left['frame_index'] <=> $right['frame_index']);
        $pageNumbers = array_keys($discardedPages);
        sort($pageNumbers, SORT_NUMERIC);

        return [
            'savepoint' => $name,
            'rollback_to_frame' => $this->frames[$index]['wal_start_frame'],
            'discarded_wal_frames' => $discardedFrames,
            'discarded_page_numbers' => $pageNumbers,
            'transaction_active_after' => true,
        ];
    }

    /**
     * @return array{savepoint:string,rollback_to_frame:int,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool,frame_name:string}>,discarded_page_numbers:list<int>,transaction_active_after:bool}
     */
    public function walRollbackToWithPlan(string $name): array
    {
        $plan = $this->walRollbackToPlan($name);
        $this->rollbackTo($name);

        return $plan;
    }

    /**
     * @return array{savepoint:string,rollback_to_frame:int,original_frame_count:int,retained_frame_count:int,discarded_frame_count:int,truncate_to_bytes:int,original_wal_bytes:int,truncated_wal_bytes:int,needs_truncate:bool,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool,frame_name:string}>,transaction_active_after:bool}
     */
    public function walRollbackToByteTruncationPlan(string $name, SQLiteWal $wal, string $walBytes): array
    {
        if ($walBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint rollback requires WAL bytes');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL savepoint rollback bytes do not match the parsed WAL');
        }

        $rollbackPlan = $this->walRollbackToPlan($name);
        $rollbackToFrame = $rollbackPlan['rollback_to_frame'];
        $pageSize = $wal->header->pageSize;
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite WAL savepoint rollback requires a concrete WAL page size');
        }

        $originalFrameCount = $wal->frameCount();
        if ($rollbackToFrame > $originalFrameCount) {
            throw new \InvalidArgumentException('SQLite savepoint rollback frame is beyond the WAL frame count');
        }

        $truncateToBytes = 32 + ($rollbackToFrame * (24 + $pageSize));
        $originalBytes = strlen($walBytes);

        return [
            'savepoint' => $name,
            'rollback_to_frame' => $rollbackToFrame,
            'original_frame_count' => $originalFrameCount,
            'retained_frame_count' => $rollbackToFrame,
            'discarded_frame_count' => $originalFrameCount - $rollbackToFrame,
            'truncate_to_bytes' => $truncateToBytes,
            'original_wal_bytes' => $originalBytes,
            'truncated_wal_bytes' => min($truncateToBytes, $originalBytes),
            'needs_truncate' => $truncateToBytes < $originalBytes,
            'discarded_wal_frames' => $rollbackPlan['discarded_wal_frames'],
            'transaction_active_after' => true,
        ];
    }

    public function walRollbackToWalBytes(string $name, SQLiteWal $wal, string $walBytes): string
    {
        $plan = $this->walRollbackToByteTruncationPlan($name, $wal, $walBytes);

        return substr($walBytes, 0, $plan['truncate_to_bytes']);
    }

    /**
     * @return array{savepoint:string,found_index:int,rollback_to_frame:int,next_wal_frame_index:int,next_page_number:int,next_commit_frame:bool,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool,frame_name:string}>,discarded_page_numbers:list<int>,retained_frame_names:list<string>,retained_wal_frame_indexes_before_next:list<int>,pending_page_numbers_before_next:list<int>,pending_wal_frame_indexes_after_next:list<int>,pending_page_numbers_after_next:list<int>,current_savepoint_active_after:bool,transaction_active_after:bool,dependencies:list<string>}
     */
    public function rollbackToCurrentAndRecordWalFrame(string $name, int $nextPageNumber, bool $commitFrame = false): array
    {
        if ($nextPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }

        $index = $this->findFrame($name);
        $walPlan = $this->walRollbackToPlan($name);
        $rollbackToFrame = $walPlan['rollback_to_frame'];
        $retainedFrameNames = array_map(
            static fn (array $frame): string => $frame['name'],
            array_slice($this->frames, 0, $index + 1)
        );
        $pendingPagesBeforeNext = $this->pendingPageNumbersBeforeRollbackTarget($index);
        $retainedWalFramesBeforeNext = $this->pendingWalFrameIndexesBeforeRollbackTarget($index);
        $nextFrameIndex = $rollbackToFrame + 1;

        $this->rollbackTo($name);
        $this->recordWalFrameWrite($nextFrameIndex, $nextPageNumber, $commitFrame);

        return [
            'savepoint' => $name,
            'found_index' => $index,
            'rollback_to_frame' => $rollbackToFrame,
            'next_wal_frame_index' => $nextFrameIndex,
            'next_page_number' => $nextPageNumber,
            'next_commit_frame' => $commitFrame,
            'discarded_wal_frames' => $walPlan['discarded_wal_frames'],
            'discarded_page_numbers' => $walPlan['discarded_page_numbers'],
            'retained_frame_names' => $retainedFrameNames,
            'retained_wal_frame_indexes_before_next' => $retainedWalFramesBeforeNext,
            'pending_page_numbers_before_next' => $pendingPagesBeforeNext,
            'pending_wal_frame_indexes_after_next' => $this->pendingWalFrameIndexes(),
            'pending_page_numbers_after_next' => $this->pendingPageNumbers(),
            'current_savepoint_active_after' => $this->hasOpenFrame($name),
            'transaction_active_after' => $this->transactionActive(),
            'dependencies' => [
                'sqlite-savepoint-rollback-to-current-keeps-savepoint',
                'sqlite-pager-current-next-wal-frame64',
            ],
        ];
    }

    /**
     * @return array{savepoint:string,statement:string,found_index:int,rollback_to_frame:int,next_wal_frame_index:int,next_page_number:int,next_commit_frame:bool,discarded_statement_journals:list<string>,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool,frame_name:string}>,discarded_page_numbers:list<int>,retained_frame_names:list<string>,statement_journals_after_rollback:list<array{name:string,savepoint:string,wal_start_frame:int,page_numbers:list<int>,wal_frame_indexes:list<int>}>,statement_journals_after_next:list<array{name:string,savepoint:string,wal_start_frame:int,page_numbers:list<int>,wal_frame_indexes:list<int>}>,pending_page_numbers_after_next:list<int>,pending_wal_frame_indexes_after_next:list<int>,rollback_statement_restored_pages:list<int>,rollback_statement_to_frame:int,current_savepoint_active_after:bool,transaction_active_after:bool,dependencies:list<string>}
     */
    public function rollbackToCurrentAndBeginStatementJournal(
        string $name,
        string $statementName,
        int $nextPageNumber,
        string $beforeImage,
        int $pageSize,
        bool $commitFrame = false,
    ): array {
        if ($statementName === '') {
            throw new \InvalidArgumentException('SQLite statement journal name must not be empty');
        }
        if ($nextPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }
        if ($beforeImage === '') {
            throw new \InvalidArgumentException('SQLite statement journal page images must not be empty');
        }
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite page size must be positive');
        }
        if (strlen($beforeImage) !== $pageSize) {
            throw new \InvalidArgumentException("SQLite statement journal image for page {$nextPageNumber} does not match the page size");
        }

        $index = $this->findFrame($name);
        $discardedStatementJournals = [];
        foreach ($this->statementJournals as $journal) {
            if ($journal['frame_index'] >= $index) {
                $discardedStatementJournals[] = $journal['name'];
            }
        }
        sort($discardedStatementJournals, SORT_STRING);

        $walPlan = $this->walRollbackToPlan($name);
        $rollbackToFrame = $walPlan['rollback_to_frame'];
        $retainedFrameNames = array_map(
            static fn (array $frame): string => $frame['name'],
            array_slice($this->frames, 0, $index + 1)
        );
        $nextFrameIndex = $rollbackToFrame + 1;

        $this->rollbackTo($name);
        $statementJournalsAfterRollback = $this->statementJournalState();
        $this->beginStatementJournal($statementName);
        $this->recordStatementPageImageWrite($statementName, $nextPageNumber, $beforeImage);
        $this->recordStatementWalFrameWrite($statementName, $nextFrameIndex, $nextPageNumber, $commitFrame);
        $statementRollbackPlan = $this->statementRollbackPlan($statementName, $pageSize);

        return [
            'savepoint' => $name,
            'statement' => $statementName,
            'found_index' => $index,
            'rollback_to_frame' => $rollbackToFrame,
            'next_wal_frame_index' => $nextFrameIndex,
            'next_page_number' => $nextPageNumber,
            'next_commit_frame' => $commitFrame,
            'discarded_statement_journals' => $discardedStatementJournals,
            'discarded_wal_frames' => $walPlan['discarded_wal_frames'],
            'discarded_page_numbers' => $walPlan['discarded_page_numbers'],
            'retained_frame_names' => $retainedFrameNames,
            'statement_journals_after_rollback' => $statementJournalsAfterRollback,
            'statement_journals_after_next' => $this->statementJournalState(),
            'pending_page_numbers_after_next' => $this->pendingPageNumbers(),
            'pending_wal_frame_indexes_after_next' => $this->pendingWalFrameIndexes(),
            'rollback_statement_restored_pages' => $statementRollbackPlan['restored_page_numbers'],
            'rollback_statement_to_frame' => $statementRollbackPlan['rollback_to_wal_frame'],
            'current_savepoint_active_after' => $this->hasOpenFrame($name),
            'transaction_active_after' => $this->transactionActive(),
            'dependencies' => [
                'sqlite-savepoint-rollback-to-current-keeps-savepoint',
                'sqlite-statement-journal-current-next66',
                'sqlite-pager-savepoint-subjournal-current-next66',
            ],
        ];
    }

    /**
     * @return array{savepoint:string,next_savepoint:string,found_index:int,rollback_to_frame:int,next_wal_frame_index:int,next_page_number:int,next_commit_frame:bool,discarded_statement_journals:list<string>,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool,frame_name:string}>,discarded_page_numbers:list<int>,retained_frame_names_before_release:list<string>,names_after_rollback:list<string>,names_after_release:list<string>,names_after_next:list<string>,wal_state_after_next:list<array{name:string,transaction:bool,wal_start_frame:int,wal_frame_indexes:list<int>}>,pending_page_numbers_after_next:list<int>,pending_wal_frame_indexes_after_next:list<int>,released_savepoint_closed:bool,next_savepoint_active_after:bool,transaction_active_after:bool,dependencies:list<string>}
     */
    public function rollbackReleaseAndBeginSavepoint(
        string $name,
        string $nextSavepointName,
        int $nextPageNumber,
        string $beforeImage,
        int $pageSize,
        bool $commitFrame = false,
    ): array {
        if ($nextSavepointName === '') {
            throw new \InvalidArgumentException('SQLite next savepoint name must not be empty');
        }
        if ($nextPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }
        if ($beforeImage === '') {
            throw new \InvalidArgumentException('SQLite savepoint page images must not be empty');
        }
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite page size must be positive');
        }
        if (strlen($beforeImage) !== $pageSize) {
            throw new \InvalidArgumentException("SQLite next savepoint image for page {$nextPageNumber} does not match the page size");
        }

        $index = $this->findFrame($name);
        $discardedStatementJournals = [];
        foreach ($this->statementJournals as $journal) {
            if ($journal['frame_index'] >= $index) {
                $discardedStatementJournals[] = $journal['name'];
            }
        }
        sort($discardedStatementJournals, SORT_STRING);

        $walPlan = $this->walRollbackToPlan($name);
        $rollbackToFrame = $walPlan['rollback_to_frame'];
        $retainedFrameNames = array_map(
            static fn (array $frame): string => $frame['name'],
            array_slice($this->frames, 0, $index + 1)
        );
        $nextFrameIndex = $rollbackToFrame + 1;

        $this->rollbackTo($name);
        $namesAfterRollback = $this->names();
        $this->release($name);
        $namesAfterRelease = $this->names();
        $this->savepoint($nextSavepointName);
        $this->recordPageImageWrite($nextPageNumber, $beforeImage);
        $this->recordWalFrameWrite($nextFrameIndex, $nextPageNumber, $commitFrame);

        return [
            'savepoint' => $name,
            'next_savepoint' => $nextSavepointName,
            'found_index' => $index,
            'rollback_to_frame' => $rollbackToFrame,
            'next_wal_frame_index' => $nextFrameIndex,
            'next_page_number' => $nextPageNumber,
            'next_commit_frame' => $commitFrame,
            'discarded_statement_journals' => $discardedStatementJournals,
            'discarded_wal_frames' => $walPlan['discarded_wal_frames'],
            'discarded_page_numbers' => $walPlan['discarded_page_numbers'],
            'retained_frame_names_before_release' => $retainedFrameNames,
            'names_after_rollback' => $namesAfterRollback,
            'names_after_release' => $namesAfterRelease,
            'names_after_next' => $this->names(),
            'wal_state_after_next' => $this->walFrameState(),
            'pending_page_numbers_after_next' => $this->pendingPageNumbers(),
            'pending_wal_frame_indexes_after_next' => $this->pendingWalFrameIndexes(),
            'released_savepoint_closed' => !$this->hasOpenFrame($name),
            'next_savepoint_active_after' => $this->hasOpenFrame($nextSavepointName),
            'transaction_active_after' => $this->transactionActive(),
            'dependencies' => [
                'sqlite-savepoint-rollback-release-current-next68',
                'sqlite-pager-savepoint-next-wal-prefix68',
            ],
        ];
    }

    /**
     * @return array{savepoint:string,next_savepoint:string,found_index:int,rollback_to_frame:int,next_wal_frame_index:int|null,next_page_number:int|null,next_commit_frame:bool,discarded_statement_journals:list<string>,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool,frame_name:string}>,discarded_page_numbers:list<int>,retained_frame_names:list<string>,names_after_rollback:list<string>,names_after_next:list<string>,current_depth_after:int,next_depth_after:int,current_savepoint_active_after:bool,next_savepoint_active_after:bool,pending_page_numbers_after_next:list<int>,pending_wal_frame_indexes_after_next:list<int>,next_frame_wal_start:int|null,dependencies:list<string>}
     */
    public function rollbackToCurrentAndOpenSavepoint(
        string $name,
        string $nextName,
        ?int $nextPageNumber = null,
        ?string $beforeImage = null,
        ?int $pageSize = null,
        bool $commitFrame = false,
    ): array {
        if ($nextName === '') {
            throw new \InvalidArgumentException('SQLite next savepoint name must not be empty');
        }
        if ($nextPageNumber !== null && $nextPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }
        if ($beforeImage !== null && $beforeImage === '') {
            throw new \InvalidArgumentException('SQLite savepoint page images must not be empty');
        }
        if ($beforeImage !== null && $nextPageNumber === null) {
            throw new \InvalidArgumentException('SQLite savepoint page image requires a page number');
        }
        if ($pageSize !== null && $pageSize < 1) {
            throw new \InvalidArgumentException('SQLite page size must be positive');
        }
        if ($beforeImage !== null && $pageSize !== null && strlen($beforeImage) !== $pageSize) {
            throw new \InvalidArgumentException("SQLite savepoint image for page {$nextPageNumber} does not match the page size");
        }
        if ($commitFrame && $nextPageNumber === null) {
            throw new \InvalidArgumentException('SQLite WAL commit marker requires a next page number');
        }

        $index = $this->findFrame($name);
        $walPlan = $this->walRollbackToPlan($name);
        $rollbackToFrame = $walPlan['rollback_to_frame'];
        $retainedFrameNames = array_map(
            static fn (array $frame): string => $frame['name'],
            array_slice($this->frames, 0, $index + 1)
        );
        $discardedStatementJournals = [];
        foreach ($this->statementJournals as $journal) {
            if ($journal['frame_index'] >= $index) {
                $discardedStatementJournals[] = $journal['name'];
            }
        }
        sort($discardedStatementJournals, SORT_STRING);

        $this->rollbackTo($name);
        $namesAfterRollback = $this->names();
        $currentDepthAfter = $this->depth();
        $this->savepoint($nextName);

        $nextFrameIndex = null;
        if ($nextPageNumber !== null) {
            if ($beforeImage !== null) {
                $this->recordPageImageWrite($nextPageNumber, $beforeImage);
            } else {
                $this->recordPageWrite($nextPageNumber);
            }

            $nextFrameIndex = $rollbackToFrame + 1;
            $this->recordWalFrameWrite($nextFrameIndex, $nextPageNumber, $commitFrame);
        }

        $walState = $this->walFrameState();
        $nextFrameState = $walState[array_key_last($walState)] ?? null;

        return [
            'savepoint' => $name,
            'next_savepoint' => $nextName,
            'found_index' => $index,
            'rollback_to_frame' => $rollbackToFrame,
            'next_wal_frame_index' => $nextFrameIndex,
            'next_page_number' => $nextPageNumber,
            'next_commit_frame' => $commitFrame,
            'discarded_statement_journals' => $discardedStatementJournals,
            'discarded_wal_frames' => $walPlan['discarded_wal_frames'],
            'discarded_page_numbers' => $walPlan['discarded_page_numbers'],
            'retained_frame_names' => $retainedFrameNames,
            'names_after_rollback' => $namesAfterRollback,
            'names_after_next' => $this->names(),
            'current_depth_after' => $currentDepthAfter,
            'next_depth_after' => $this->depth(),
            'current_savepoint_active_after' => $this->hasOpenFrame($name),
            'next_savepoint_active_after' => $this->hasOpenFrame($nextName),
            'pending_page_numbers_after_next' => $this->pendingPageNumbers(),
            'pending_wal_frame_indexes_after_next' => $this->pendingWalFrameIndexes(),
            'next_frame_wal_start' => $nextFrameState['wal_start_frame'] ?? null,
            'dependencies' => [
                'sqlite-savepoint-rollback-to-current-keeps-savepoint',
                'sqlite-pager-savepoint-current-next69',
                'sqlite-next-savepoint-after-rollback-to-current',
            ],
        ];
    }

    public function release(string $name): void
    {
        $index = $this->findFrame($name);
        $releasedFrames = array_splice($this->frames, $index);
        $this->clearStatementJournalsFromFrame($index);
        $releasedPages = [];
        foreach ($releasedFrames as $frame) {
            foreach ($frame['pages'] as $pageNumber => $_) {
                $releasedPages[$pageNumber] = true;
            }
        }

        if ($this->frames === []) {
            $this->maxWalFrame = 0;
            $this->statementJournals = [];
            return;
        }

        foreach ($releasedPages as $pageNumber => $_) {
            $this->frames[array_key_last($this->frames)]['pages'][$pageNumber] = true;
        }
        foreach ($releasedFrames as $frame) {
            foreach ($frame['wal_frames'] as $walFrameIndex => $walFrame) {
                $this->frames[array_key_last($this->frames)]['wal_frames'][$walFrameIndex] = $walFrame;
            }
            foreach ($frame['page_images'] as $pageNumber => $pageImage) {
                if (!isset($this->frames[array_key_last($this->frames)]['page_images'][$pageNumber])) {
                    $this->frames[array_key_last($this->frames)]['page_images'][$pageNumber] = $pageImage;
                }
            }
        }
    }

    /**
     * @return array{savepoint:string,found_index:int,released_frame_names:list<string>,merged_page_numbers:list<int>,target_is_transaction:bool,result_depth:int,transaction_active_after:bool}
     */
    public function releaseWithPlan(string $name): array
    {
        $plan = $this->releasePlan($name);
        $this->release($name);

        return $plan;
    }

    /**
     * @return array{savepoint:string,found_index:int,released_frame_names:list<string>,merged_page_numbers:list<int>,target_is_transaction:bool,result_depth:int,transaction_active_after:bool}
     */
    public function releasePlan(string $name): array
    {
        $index = $this->findFrame($name);
        $releasedFrameNames = [];
        $releasedPages = [];
        for ($frameIndex = $index; $frameIndex < count($this->frames); $frameIndex++) {
            $releasedFrameNames[] = $this->frames[$frameIndex]['name'];
            foreach ($this->frames[$frameIndex]['pages'] as $pageNumber => $_) {
                $releasedPages[$pageNumber] = true;
            }
        }

        $pageNumbers = array_keys($releasedPages);
        sort($pageNumbers, SORT_NUMERIC);

        return [
            'savepoint' => $name,
            'found_index' => $index,
            'released_frame_names' => $releasedFrameNames,
            'merged_page_numbers' => $pageNumbers,
            'target_is_transaction' => $this->frames[$index]['transaction'],
            'result_depth' => $index,
            'transaction_active_after' => $index > 0,
        ];
    }

    /**
     * @return list<int>
     */
    public function rollbackToPageNumbers(string $name): array
    {
        $index = $this->findFrame($name);
        $pages = [];
        for ($frameIndex = $index; $frameIndex < count($this->frames); $frameIndex++) {
            foreach ($this->frames[$frameIndex]['pages'] as $pageNumber => $_) {
                $pages[$pageNumber] = true;
            }
        }

        $pageNumbers = array_keys($pages);
        sort($pageNumbers, SORT_NUMERIC);

        return $pageNumbers;
    }

    public function commit(): void
    {
        if ($this->frames === []) {
            throw new \LogicException('SQLite transaction is not active');
        }

        $this->frames = [];
        $this->maxWalFrame = 0;
        $this->statementJournals = [];
    }

    public function rollback(): void
    {
        if ($this->frames === []) {
            throw new \LogicException('SQLite transaction is not active');
        }

        $this->frames = [];
        $this->maxWalFrame = 0;
        $this->statementJournals = [];
    }

    /**
     * @return array{rolled_back_frame_names:list<string>,rollback_page_numbers:list<int>,released_savepoint_count:int,transaction_active_after:bool}
     */
    public function rollbackWithPlan(): array
    {
        $plan = $this->rollbackPlan();
        $this->rollback();

        return $plan;
    }

    /**
     * @return array{rolled_back_frame_names:list<string>,rollback_page_numbers:list<int>,released_savepoint_count:int,transaction_active_after:bool}
     */
    public function rollbackPlan(): array
    {
        if ($this->frames === []) {
            throw new \LogicException('SQLite transaction is not active');
        }

        $frameNames = [];
        $pages = [];
        foreach ($this->frames as $frame) {
            $frameNames[] = $frame['name'];
            foreach ($frame['pages'] as $pageNumber => $_) {
                $pages[$pageNumber] = true;
            }
        }

        $pageNumbers = array_keys($pages);
        sort($pageNumbers, SORT_NUMERIC);

        return [
            'rolled_back_frame_names' => $frameNames,
            'rollback_page_numbers' => $pageNumbers,
            'released_savepoint_count' => max(0, count($this->frames) - 1),
            'transaction_active_after' => false,
        ];
    }

    /**
     * @return array{committed_frame_names:list<string>,committed_page_numbers:list<int>,released_savepoint_count:int,transaction_active_after:bool}
     */
    public function commitWithPlan(): array
    {
        $plan = $this->commitPlan();
        $this->commit();

        return $plan;
    }

    /**
     * @return array{committed_frame_names:list<string>,committed_page_numbers:list<int>,released_savepoint_count:int,transaction_active_after:bool}
     */
    public function commitPlan(): array
    {
        if ($this->frames === []) {
            throw new \LogicException('SQLite transaction is not active');
        }

        $frameNames = [];
        $pages = [];
        foreach ($this->frames as $frame) {
            $frameNames[] = $frame['name'];
            foreach ($frame['pages'] as $pageNumber => $_) {
                $pages[$pageNumber] = true;
            }
        }

        $pageNumbers = array_keys($pages);
        sort($pageNumbers, SORT_NUMERIC);

        return [
            'committed_frame_names' => $frameNames,
            'committed_page_numbers' => $pageNumbers,
            'released_savepoint_count' => max(0, count($this->frames) - 1),
            'transaction_active_after' => false,
        ];
    }

    public function transactionActive(): bool
    {
        return $this->frames !== [];
    }

    public function depth(): int
    {
        return count($this->frames);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (array $frame): string => $frame['name'], $this->frames);
    }

    /**
     * @return list<int>
     */
    public function pendingPageNumbers(): array
    {
        $pages = [];
        foreach ($this->frames as $frame) {
            foreach ($frame['pages'] as $pageNumber => $_) {
                $pages[$pageNumber] = true;
            }
        }

        $pageNumbers = array_keys($pages);
        sort($pageNumbers, SORT_NUMERIC);

        return $pageNumbers;
    }

    /**
     * @return list<int>
     */
    public function pendingWalFrameIndexes(): array
    {
        $frames = [];
        foreach ($this->frames as $frame) {
            foreach ($frame['wal_frames'] as $walFrameIndex => $_) {
                $frames[$walFrameIndex] = true;
            }
        }

        $frameIndexes = array_keys($frames);
        sort($frameIndexes, SORT_NUMERIC);

        return $frameIndexes;
    }

    /**
     * @return array{savepoint:string,found_index:int,page_size:int,restored_page_numbers:list<int>,restore_pages:list<array{page_number:int,database_offset:int,bytes:int,source_frame:string}>,missing_page_numbers:list<int>,transaction_active_after:bool}
     */
    public function rollbackToImagePlan(string $name, int $pageSize): array
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite page size must be positive');
        }

        $index = $this->findFrame($name);
        $restoreImages = $this->rollbackToPageImages($name);
        $restorePages = [];
        foreach ($restoreImages as $pageNumber => $restore) {
            if (strlen($restore['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite savepoint image for page {$pageNumber} does not match the page size");
            }
            $restorePages[] = [
                'page_number' => $pageNumber,
                'database_offset' => ($pageNumber - 1) * $pageSize,
                'bytes' => $pageSize,
                'source_frame' => $restore['frame'],
            ];
        }

        $missing = array_values(array_diff($this->rollbackToPageNumbers($name), array_keys($restoreImages)));
        sort($missing, SORT_NUMERIC);

        return [
            'savepoint' => $name,
            'found_index' => $index,
            'page_size' => $pageSize,
            'restored_page_numbers' => array_keys($restoreImages),
            'restore_pages' => $restorePages,
            'missing_page_numbers' => $missing,
            'transaction_active_after' => true,
        ];
    }

    public function rollbackToDatabaseImage(string $name, string $databaseBytes, int $pageSize): string
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite page size must be positive');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite savepoint rollback requires a database image aligned to the page size');
        }

        $restoreImages = $this->rollbackToPageImages($name);
        $rolledBack = $databaseBytes;
        foreach ($restoreImages as $pageNumber => $restore) {
            if (strlen($restore['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite savepoint image for page {$pageNumber} does not match the page size");
            }
            $offset = ($pageNumber - 1) * $pageSize;
            if ($offset + $pageSize > strlen($rolledBack)) {
                continue;
            }
            $rolledBack = substr_replace($rolledBack, $restore['image'], $offset, $pageSize);
        }

        return $rolledBack;
    }

    /**
     * @param array<int,string> $currentPageImages
     * @return array{savepoint:string,found_index:int,page_size:int,current_source_verified:bool,current_source_page_numbers:list<int>,current_source_prefixes:array<int,string>,rollback_to_frame:int,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool,frame_name:string}>,discarded_page_numbers:list<int>,restored_page_numbers:list<int>,restore_pages:list<array{page_number:int,database_offset:int,bytes:int,source_frame:string}>,missing_page_numbers:list<int>,names_before:list<string>,names_after_rollback:list<string>,names_after_release:list<string>,released_frame_names:list<string>,released_merged_page_numbers:list<int>,pending_page_numbers_after_release:list<int>,pending_wal_frame_indexes_after_release:list<int>,rolled_back_database_bytes:string,transaction_active_after:bool,dependencies:list<string>}
     */
    public function rollbackToCurrentSourceThenRelease(
        string $name,
        string $currentDatabaseBytes,
        array $currentPageImages,
        int $pageSize,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite page size must be positive');
        }
        if (strlen($currentDatabaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite savepoint rollback current source must be aligned to the page size');
        }
        if ($currentPageImages === []) {
            throw new \InvalidArgumentException('SQLite savepoint rollback current source pages must not be empty');
        }

        $sourcePageNumbers = [];
        $currentSourcePrefixes = [];
        foreach ($currentPageImages as $pageNumber => $expectedImage) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite savepoint rollback current source page numbers are one-based');
            }
            if (!is_string($expectedImage) || strlen($expectedImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite savepoint rollback current source image for page {$pageNumber} must match the page size");
            }

            $offset = ($pageNumber - 1) * $pageSize;
            $actualImage = substr($currentDatabaseBytes, $offset, $pageSize);
            if (strlen($actualImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite savepoint rollback current source page {$pageNumber} is outside the database image");
            }
            if ($actualImage !== $expectedImage) {
                throw new \RuntimeException("SQLite savepoint rollback current source page {$pageNumber} is stale");
            }

            $sourcePageNumbers[] = $pageNumber;
            $currentSourcePrefixes[$pageNumber] = rtrim(substr($actualImage, 0, min(48, $pageSize)), "\0");
        }
        sort($sourcePageNumbers, SORT_NUMERIC);
        ksort($currentSourcePrefixes, SORT_NUMERIC);

        $index = $this->findFrame($name);
        $namesBefore = $this->names();
        $walPlan = $this->walRollbackToPlan($name);
        $imagePlan = $this->rollbackToImagePlan($name, $pageSize);
        $rolledBackDatabaseBytes = $this->rollbackToDatabaseImage($name, $currentDatabaseBytes, $pageSize);

        $this->rollbackTo($name);
        $namesAfterRollback = $this->names();
        $releasePlan = $this->releaseWithPlan($name);

        return [
            'savepoint' => $name,
            'found_index' => $index,
            'page_size' => $pageSize,
            'current_source_verified' => true,
            'current_source_page_numbers' => $sourcePageNumbers,
            'current_source_prefixes' => $currentSourcePrefixes,
            'rollback_to_frame' => $walPlan['rollback_to_frame'],
            'discarded_wal_frames' => $walPlan['discarded_wal_frames'],
            'discarded_page_numbers' => $walPlan['discarded_page_numbers'],
            'restored_page_numbers' => $imagePlan['restored_page_numbers'],
            'restore_pages' => $imagePlan['restore_pages'],
            'missing_page_numbers' => $imagePlan['missing_page_numbers'],
            'names_before' => $namesBefore,
            'names_after_rollback' => $namesAfterRollback,
            'names_after_release' => $this->names(),
            'released_frame_names' => $releasePlan['released_frame_names'],
            'released_merged_page_numbers' => $releasePlan['merged_page_numbers'],
            'pending_page_numbers_after_release' => $this->pendingPageNumbers(),
            'pending_wal_frame_indexes_after_release' => $this->pendingWalFrameIndexes(),
            'rolled_back_database_bytes' => $rolledBackDatabaseBytes,
            'transaction_active_after' => $this->transactionActive(),
            'dependencies' => [
                'sqlite-savepoint-nested-rollback-release-current-source-next116',
                'sqlite-savepoint-rollback-to-current-keeps-savepoint',
                'sqlite-savepoint-release-after-rollback',
            ],
        ];
    }

    public function beginStatementJournal(string $name): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite statement journal name must not be empty');
        }
        if ($this->frames === []) {
            throw new \LogicException('SQLite statement journal requires an active transaction');
        }
        if (isset($this->statementJournals[$name])) {
            throw new \LogicException("SQLite statement journal is already active: {$name}");
        }

        $frameIndex = array_key_last($this->frames);
        $this->statementJournals[$name] = [
            'name' => $name,
            'frame_index' => $frameIndex,
            'savepoint_name' => $this->frames[$frameIndex]['name'],
            'wal_start_frame' => $this->maxWalFrame,
            'prior_pages' => $this->frames[$frameIndex]['pages'],
            'prior_page_images' => $this->frames[$frameIndex]['page_images'],
            'page_images' => [],
            'wal_frames' => [],
        ];
    }

    public function recordStatementPageImageWrite(string $statementName, int $pageNumber, string $beforeImage): void
    {
        if (!isset($this->statementJournals[$statementName])) {
            throw new \InvalidArgumentException("SQLite statement journal does not exist: {$statementName}");
        }
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }
        if ($beforeImage === '') {
            throw new \InvalidArgumentException('SQLite statement journal page images must not be empty');
        }

        $this->recordPageWrite($pageNumber);
        if (!isset($this->statementJournals[$statementName]['page_images'][$pageNumber])) {
            $this->statementJournals[$statementName]['page_images'][$pageNumber] = $beforeImage;
        }
    }

    public function recordStatementWalFrameWrite(string $statementName, int $frameIndex, int $pageNumber, bool $commitFrame = false): void
    {
        if (!isset($this->statementJournals[$statementName])) {
            throw new \InvalidArgumentException("SQLite statement journal does not exist: {$statementName}");
        }

        $this->recordWalFrameWrite($frameIndex, $pageNumber, $commitFrame);
        $this->statementJournals[$statementName]['wal_frames'][$frameIndex] = [
            'page_number' => $pageNumber,
            'commit_frame' => $commitFrame,
        ];
    }

    /**
     * @return array{statement:string,savepoint:string,page_size:int,restored_page_numbers:list<int>,restore_pages:list<array{page_number:int,database_offset:int,bytes:int}>,rollback_to_wal_frame:int,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool}>,transaction_active_after:bool,savepoint_active_after:bool,statement_journal_cleared:bool}
     */
    public function statementRollbackPlan(string $statementName, int $pageSize): array
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite page size must be positive');
        }
        $journal = $this->statementJournal($statementName);

        $restorePages = [];
        $restoredPageNumbers = array_keys($journal['page_images']);
        sort($restoredPageNumbers, SORT_NUMERIC);
        foreach ($restoredPageNumbers as $pageNumber) {
            $pageImage = $journal['page_images'][$pageNumber];
            if (strlen($pageImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite statement journal image for page {$pageNumber} does not match the page size");
            }
            $restorePages[] = [
                'page_number' => $pageNumber,
                'database_offset' => ($pageNumber - 1) * $pageSize,
                'bytes' => $pageSize,
            ];
        }

        $discardedWalFrames = [];
        foreach ($journal['wal_frames'] as $frameIndex => $walFrame) {
            $discardedWalFrames[] = [
                'frame_index' => $frameIndex,
                'page_number' => $walFrame['page_number'],
                'commit_frame' => $walFrame['commit_frame'],
            ];
        }
        usort($discardedWalFrames, static fn (array $left, array $right): int => $left['frame_index'] <=> $right['frame_index']);

        return [
            'statement' => $statementName,
            'savepoint' => $journal['savepoint_name'],
            'page_size' => $pageSize,
            'restored_page_numbers' => $restoredPageNumbers,
            'restore_pages' => $restorePages,
            'rollback_to_wal_frame' => $journal['wal_start_frame'],
            'discarded_wal_frames' => $discardedWalFrames,
            'transaction_active_after' => true,
            'savepoint_active_after' => isset($this->frames[$journal['frame_index']]),
            'statement_journal_cleared' => true,
        ];
    }

    public function rollbackStatementDatabaseImage(string $statementName, string $databaseBytes, int $pageSize): string
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite page size must be positive');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite statement rollback requires a database image aligned to the page size');
        }

        $journal = $this->statementJournal($statementName);
        $rolledBack = $databaseBytes;
        foreach ($journal['page_images'] as $pageNumber => $pageImage) {
            if (strlen($pageImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite statement journal image for page {$pageNumber} does not match the page size");
            }
            $offset = ($pageNumber - 1) * $pageSize;
            if ($offset + $pageSize > strlen($rolledBack)) {
                continue;
            }
            $rolledBack = substr_replace($rolledBack, $pageImage, $offset, $pageSize);
        }

        return $rolledBack;
    }

    /**
     * @param array<int,string> $currentPageImages
     * @return array{current_statement:string,next_statement:string,savepoint:string,page_size:int,current_source_verified:bool,current_source_page_numbers:list<int>,current_source_prefixes:array<int,string>,next_source_prefixes:array<int,string>,rollback_to_wal_frame:int,next_wal_frame_index:int,next_page_number:int,next_commit_frame:bool,rollback_restored_page_numbers:list<int>,rollback_discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool}>,statement_journals_after_rollback:list<array{name:string,savepoint:string,wal_start_frame:int,page_numbers:list<int>,wal_frame_indexes:list<int>}>,statement_journals_after_next:list<array{name:string,savepoint:string,wal_start_frame:int,page_numbers:list<int>,wal_frame_indexes:list<int>}>,pending_page_numbers_after_rollback:list<int>,pending_wal_frame_indexes_after_rollback:list<int>,pending_page_numbers_after_next:list<int>,pending_wal_frame_indexes_after_next:list<int>,rolled_back_database_bytes:string,savepoint_active_after:bool,transaction_active_after:bool,dependencies:list<string>}
     */
    public function rollbackStatementCurrentSourceAndBeginStatementJournal(
        string $currentStatementName,
        string $nextStatementName,
        string $currentDatabaseBytes,
        array $currentPageImages,
        int $nextPageNumber,
        string $nextBeforeImage,
        int $pageSize,
        bool $commitFrame = false,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite page size must be positive');
        }
        if (strlen($currentDatabaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite statement rollback current source must be aligned to the page size');
        }
        if ($currentPageImages === []) {
            throw new \InvalidArgumentException('SQLite statement rollback current source pages must not be empty');
        }

        $sourcePageNumbers = [];
        $currentSourcePrefixes = [];
        foreach ($currentPageImages as $pageNumber => $expectedImage) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite statement rollback current source page numbers are one-based');
            }
            if (!is_string($expectedImage) || strlen($expectedImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite statement rollback current source image for page {$pageNumber} must match the page size");
            }

            $offset = ($pageNumber - 1) * $pageSize;
            $actualImage = substr($currentDatabaseBytes, $offset, $pageSize);
            if (strlen($actualImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite statement rollback current source page {$pageNumber} is outside the database image");
            }
            if ($actualImage !== $expectedImage) {
                throw new \RuntimeException("SQLite statement rollback current source page {$pageNumber} is stale");
            }

            $sourcePageNumbers[] = $pageNumber;
            $currentSourcePrefixes[$pageNumber] = rtrim(substr($actualImage, 0, min(48, $pageSize)), "\0");
        }
        sort($sourcePageNumbers, SORT_NUMERIC);
        ksort($currentSourcePrefixes, SORT_NUMERIC);

        $rolledBackDatabaseBytes = $this->rollbackStatementDatabaseImage($currentStatementName, $currentDatabaseBytes, $pageSize);
        $recovery = $this->rollbackStatementAndBeginStatementJournal(
            $currentStatementName,
            $nextStatementName,
            $nextPageNumber,
            $nextBeforeImage,
            $pageSize,
            $commitFrame
        );

        $nextSourcePrefixes = [];
        foreach ($sourcePageNumbers as $pageNumber) {
            $offset = ($pageNumber - 1) * $pageSize;
            $nextSourcePrefixes[$pageNumber] = rtrim(substr($rolledBackDatabaseBytes, $offset, min(48, $pageSize)), "\0");
        }

        return [
            'current_statement' => $currentStatementName,
            'next_statement' => $nextStatementName,
            'savepoint' => $recovery['savepoint'],
            'page_size' => $pageSize,
            'current_source_verified' => true,
            'current_source_page_numbers' => $sourcePageNumbers,
            'current_source_prefixes' => $currentSourcePrefixes,
            'next_source_prefixes' => $nextSourcePrefixes,
            'rollback_to_wal_frame' => $recovery['rollback_to_wal_frame'],
            'next_wal_frame_index' => $recovery['next_wal_frame_index'],
            'next_page_number' => $recovery['next_page_number'],
            'next_commit_frame' => $recovery['next_commit_frame'],
            'rollback_restored_page_numbers' => $recovery['rollback_restored_page_numbers'],
            'rollback_discarded_wal_frames' => $recovery['rollback_discarded_wal_frames'],
            'statement_journals_after_rollback' => $recovery['statement_journals_after_rollback'],
            'statement_journals_after_next' => $recovery['statement_journals_after_next'],
            'pending_page_numbers_after_rollback' => $recovery['pending_page_numbers_after_rollback'],
            'pending_wal_frame_indexes_after_rollback' => $recovery['pending_wal_frame_indexes_after_rollback'],
            'pending_page_numbers_after_next' => $recovery['pending_page_numbers_after_next'],
            'pending_wal_frame_indexes_after_next' => $recovery['pending_wal_frame_indexes_after_next'],
            'rolled_back_database_bytes' => $rolledBackDatabaseBytes,
            'savepoint_active_after' => $recovery['savepoint_active_after'],
            'transaction_active_after' => $recovery['transaction_active_after'],
            'dependencies' => array_values(array_unique(array_merge(
                $recovery['dependencies'],
                [
                    'sqlite-pager-statement-journal-savepoint-current-source',
                    'sqlite-statement-journal-current-source-guard',
                ]
            ))),
        ];
    }

    /**
     * @param array<int,string> $currentPageImages
     * @return array{released_savepoint:string,next_statement:string,page_size:int,current_source_verified:bool,current_source_page_numbers:list<int>,current_source_prefixes:array<int,string>,next_source_prefixes:array<int,string>,release_plan:array{savepoint:string,found_index:int,released_frame_names:list<string>,merged_page_numbers:list<int>,target_is_transaction:bool,result_depth:int,transaction_active_after:bool},discarded_statement_journals:list<string>,statement_journals_before_release:list<array{name:string,savepoint:string,wal_start_frame:int,page_numbers:list<int>,wal_frame_indexes:list<int>}>,statement_journals_after_release:list<array{name:string,savepoint:string,wal_start_frame:int,page_numbers:list<int>,wal_frame_indexes:list<int>}>,statement_journals_after_next:list<array{name:string,savepoint:string,wal_start_frame:int,page_numbers:list<int>,wal_frame_indexes:list<int>}>,names_after_release:list<string>,pending_page_numbers_after_release:list<int>,pending_wal_frame_indexes_after_release:list<int>,pending_page_numbers_after_next:list<int>,pending_wal_frame_indexes_after_next:list<int>,next_wal_start_frame:int,next_wal_frame_index:int,next_page_number:int,next_commit_frame:bool,released_savepoint_active_after:bool,transaction_active_after:bool,dependencies:list<string>}
     */
    public function releaseCurrentSourceAndBeginStatementJournal(
        string $savepointName,
        string $nextStatementName,
        string $currentDatabaseBytes,
        array $currentPageImages,
        int $nextPageNumber,
        string $nextBeforeImage,
        int $pageSize,
        bool $commitFrame = false,
    ): array {
        if ($nextStatementName === '') {
            throw new \InvalidArgumentException('SQLite next statement journal name must not be empty');
        }
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite page size must be positive');
        }
        if (strlen($currentDatabaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite savepoint release current source must be aligned to the page size');
        }
        if ($currentPageImages === []) {
            throw new \InvalidArgumentException('SQLite savepoint release current source pages must not be empty');
        }
        if ($nextPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }
        if ($nextBeforeImage === '') {
            throw new \InvalidArgumentException('SQLite statement journal page images must not be empty');
        }
        if (strlen($nextBeforeImage) !== $pageSize) {
            throw new \InvalidArgumentException("SQLite statement journal image for page {$nextPageNumber} does not match the page size");
        }

        $sourcePageNumbers = [];
        $currentSourcePrefixes = [];
        foreach ($currentPageImages as $pageNumber => $expectedImage) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite savepoint release current source page numbers are one-based');
            }
            if (!is_string($expectedImage) || strlen($expectedImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite savepoint release current source image for page {$pageNumber} must match the page size");
            }

            $offset = ($pageNumber - 1) * $pageSize;
            $actualImage = substr($currentDatabaseBytes, $offset, $pageSize);
            if (strlen($actualImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite savepoint release current source page {$pageNumber} is outside the database image");
            }
            if ($actualImage !== $expectedImage) {
                throw new \RuntimeException("SQLite savepoint release current source page {$pageNumber} is stale");
            }

            $sourcePageNumbers[] = $pageNumber;
            $currentSourcePrefixes[$pageNumber] = rtrim(substr($actualImage, 0, min(48, $pageSize)), "\0");
        }
        sort($sourcePageNumbers, SORT_NUMERIC);
        ksort($currentSourcePrefixes, SORT_NUMERIC);

        $index = $this->findFrame($savepointName);
        $discardedStatementJournals = [];
        foreach ($this->statementJournals as $journal) {
            if ($journal['frame_index'] >= $index) {
                $discardedStatementJournals[] = $journal['name'];
            }
        }
        sort($discardedStatementJournals, SORT_STRING);

        $statementJournalsBeforeRelease = $this->statementJournalState();
        $releasePlan = $this->releasePlan($savepointName);
        $this->release($savepointName);
        $statementJournalsAfterRelease = $this->statementJournalState();
        $pendingPagesAfterRelease = $this->pendingPageNumbers();
        $pendingWalAfterRelease = $this->pendingWalFrameIndexes();
        $nextWalStartFrame = $this->maxWalFrame;
        $nextFrameIndex = $nextWalStartFrame + 1;

        $this->beginStatementJournal($nextStatementName);
        $this->recordStatementPageImageWrite($nextStatementName, $nextPageNumber, $nextBeforeImage);
        $this->recordStatementWalFrameWrite($nextStatementName, $nextFrameIndex, $nextPageNumber, $commitFrame);

        $nextSourcePrefixes = [];
        foreach ($sourcePageNumbers as $pageNumber) {
            $offset = ($pageNumber - 1) * $pageSize;
            $nextSourcePrefixes[$pageNumber] = rtrim(substr($currentDatabaseBytes, $offset, min(48, $pageSize)), "\0");
        }

        return [
            'released_savepoint' => $savepointName,
            'next_statement' => $nextStatementName,
            'page_size' => $pageSize,
            'current_source_verified' => true,
            'current_source_page_numbers' => $sourcePageNumbers,
            'current_source_prefixes' => $currentSourcePrefixes,
            'next_source_prefixes' => $nextSourcePrefixes,
            'release_plan' => $releasePlan,
            'discarded_statement_journals' => $discardedStatementJournals,
            'statement_journals_before_release' => $statementJournalsBeforeRelease,
            'statement_journals_after_release' => $statementJournalsAfterRelease,
            'statement_journals_after_next' => $this->statementJournalState(),
            'names_after_release' => $this->names(),
            'pending_page_numbers_after_release' => $pendingPagesAfterRelease,
            'pending_wal_frame_indexes_after_release' => $pendingWalAfterRelease,
            'pending_page_numbers_after_next' => $this->pendingPageNumbers(),
            'pending_wal_frame_indexes_after_next' => $this->pendingWalFrameIndexes(),
            'next_wal_start_frame' => $nextWalStartFrame,
            'next_wal_frame_index' => $nextFrameIndex,
            'next_page_number' => $nextPageNumber,
            'next_commit_frame' => $commitFrame,
            'released_savepoint_active_after' => $this->hasOpenFrame($savepointName),
            'transaction_active_after' => $this->transactionActive(),
            'dependencies' => [
                'sqlite-pager-statement-journal-savepoint-current-source-next90',
                'sqlite-savepoint-release-merges-current-pager-state',
                'sqlite-statement-journal-next-after-release',
            ],
        ];
    }

    /**
     * @return array{released_savepoint:string,page_size:int,current_source_verified:bool,current_wal_frame_count:int,current_wal_checkpoint_sequence:int,current_wal_salt1:int,current_wal_salt2:int,release_plan:array{savepoint:string,found_index:int,released_frame_names:list<string>,merged_page_numbers:list<int>,target_is_transaction:bool,result_depth:int,transaction_active_after:bool},names_before_release:list<string>,names_after_release:list<string>,pending_page_numbers_after_release:list<int>,pending_wal_frame_indexes_after_release:list<int>,next_wal_start_frame:int,next_wal_frame_index:int,next_page_number:int,next_commit_frame:bool,pending_page_numbers_after_next:list<int>,pending_wal_frame_indexes_after_next:list<int>,released_savepoint_active_after:bool,transaction_active_after:bool,dependencies:list<string>}
     */
    public function releaseCurrentWalSourceAndAppendFrame(
        string $savepointName,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        int $nextPageNumber,
        bool $commitFrame = false
    ): array {
        if ($currentWalBytes === '') {
            throw new \InvalidArgumentException('SQLite savepoint WAL release current source requires WAL bytes');
        }
        if ($currentWal->toBytes() !== $currentWalBytes) {
            throw new \InvalidArgumentException('SQLite savepoint WAL release current source bytes do not match the parsed WAL');
        }
        if ($nextPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }

        $namesBeforeRelease = $this->names();
        $releasePlan = $this->releasePlan($savepointName);
        $this->release($savepointName);
        $pendingPagesAfterRelease = $this->pendingPageNumbers();
        $pendingWalAfterRelease = $this->pendingWalFrameIndexes();
        $nextFrameIndex = $this->maxWalFrame + 1;

        $this->recordWalFrameWrite($nextFrameIndex, $nextPageNumber, $commitFrame);

        return [
            'released_savepoint' => $savepointName,
            'page_size' => $currentWal->header->pageSize,
            'current_source_verified' => true,
            'current_wal_frame_count' => $currentWal->frameCount(),
            'current_wal_checkpoint_sequence' => $currentWal->header->checkpointSequence,
            'current_wal_salt1' => $currentWal->header->salt1,
            'current_wal_salt2' => $currentWal->header->salt2,
            'release_plan' => $releasePlan,
            'names_before_release' => $namesBeforeRelease,
            'names_after_release' => $this->names(),
            'pending_page_numbers_after_release' => $pendingPagesAfterRelease,
            'pending_wal_frame_indexes_after_release' => $pendingWalAfterRelease,
            'next_wal_start_frame' => $nextFrameIndex - 1,
            'next_wal_frame_index' => $nextFrameIndex,
            'next_page_number' => $nextPageNumber,
            'next_commit_frame' => $commitFrame,
            'pending_page_numbers_after_next' => $this->pendingPageNumbers(),
            'pending_wal_frame_indexes_after_next' => $this->pendingWalFrameIndexes(),
            'released_savepoint_active_after' => $this->hasOpenFrame($savepointName),
            'transaction_active_after' => $this->transactionActive(),
            'dependencies' => [
                'sqlite-savepoint-release-current-wal-source-next110',
                'sqlite-wal-release-current-source-next-frame',
                'wordpress-import-release-savepoint-wal-current-source',
            ],
        ];
    }

    /**
     * @return array{statement:string,savepoint:string,page_size:int,restored_page_numbers:list<int>,restore_pages:list<array{page_number:int,database_offset:int,bytes:int}>,rollback_to_wal_frame:int,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool}>,transaction_active_after:bool,savepoint_active_after:bool,statement_journal_cleared:bool}
     */
    public function rollbackStatementOnErrorWithPlan(string $statementName, int $pageSize): array
    {
        $plan = $this->statementRollbackPlan($statementName, $pageSize);
        $journal = $this->statementJournal($statementName);
        foreach ($this->frames as $frameIndex => $frame) {
            foreach ($frame['wal_frames'] as $walFrameIndex => $_) {
                if ($walFrameIndex > $journal['wal_start_frame']) {
                    unset($this->frames[$frameIndex]['wal_frames'][$walFrameIndex]);
                }
            }
        }
        if (isset($this->frames[$journal['frame_index']])) {
            $this->frames[$journal['frame_index']]['pages'] = $journal['prior_pages'];
            $this->frames[$journal['frame_index']]['page_images'] = $journal['prior_page_images'];
        }
        $this->maxWalFrame = $journal['wal_start_frame'];
        unset($this->statementJournals[$statementName]);

        return $plan;
    }

    /**
     * @return array{current_statement:string,next_statement:string,savepoint:string,page_size:int,rollback_to_wal_frame:int,next_wal_frame_index:int,next_page_number:int,next_commit_frame:bool,rollback_restored_page_numbers:list<int>,rollback_discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool}>,statement_journals_after_rollback:list<array{name:string,savepoint:string,wal_start_frame:int,page_numbers:list<int>,wal_frame_indexes:list<int>}>,statement_journals_after_next:list<array{name:string,savepoint:string,wal_start_frame:int,page_numbers:list<int>,wal_frame_indexes:list<int>}>,pending_page_numbers_after_rollback:list<int>,pending_wal_frame_indexes_after_rollback:list<int>,pending_page_numbers_after_next:list<int>,pending_wal_frame_indexes_after_next:list<int>,savepoint_active_after:bool,transaction_active_after:bool,dependencies:list<string>}
     */
    public function rollbackStatementAndBeginStatementJournal(
        string $currentStatementName,
        string $nextStatementName,
        int $nextPageNumber,
        string $beforeImage,
        int $pageSize,
        bool $commitFrame = false,
    ): array {
        if ($nextStatementName === '') {
            throw new \InvalidArgumentException('SQLite next statement journal name must not be empty');
        }
        if ($nextPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }
        if ($beforeImage === '') {
            throw new \InvalidArgumentException('SQLite statement journal page images must not be empty');
        }
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite page size must be positive');
        }
        if (strlen($beforeImage) !== $pageSize) {
            throw new \InvalidArgumentException("SQLite statement journal image for page {$nextPageNumber} does not match the page size");
        }

        $rollbackPlan = $this->rollbackStatementOnErrorWithPlan($currentStatementName, $pageSize);
        $statementJournalsAfterRollback = $this->statementJournalState();
        $pendingPagesAfterRollback = $this->pendingPageNumbers();
        $pendingWalAfterRollback = $this->pendingWalFrameIndexes();
        $nextFrameIndex = $rollbackPlan['rollback_to_wal_frame'] + 1;

        $this->beginStatementJournal($nextStatementName);
        $this->recordStatementPageImageWrite($nextStatementName, $nextPageNumber, $beforeImage);
        $this->recordStatementWalFrameWrite($nextStatementName, $nextFrameIndex, $nextPageNumber, $commitFrame);

        return [
            'current_statement' => $currentStatementName,
            'next_statement' => $nextStatementName,
            'savepoint' => $rollbackPlan['savepoint'],
            'page_size' => $pageSize,
            'rollback_to_wal_frame' => $rollbackPlan['rollback_to_wal_frame'],
            'next_wal_frame_index' => $nextFrameIndex,
            'next_page_number' => $nextPageNumber,
            'next_commit_frame' => $commitFrame,
            'rollback_restored_page_numbers' => $rollbackPlan['restored_page_numbers'],
            'rollback_discarded_wal_frames' => $rollbackPlan['discarded_wal_frames'],
            'statement_journals_after_rollback' => $statementJournalsAfterRollback,
            'statement_journals_after_next' => $this->statementJournalState(),
            'pending_page_numbers_after_rollback' => $pendingPagesAfterRollback,
            'pending_wal_frame_indexes_after_rollback' => $pendingWalAfterRollback,
            'pending_page_numbers_after_next' => $this->pendingPageNumbers(),
            'pending_wal_frame_indexes_after_next' => $this->pendingWalFrameIndexes(),
            'savepoint_active_after' => $rollbackPlan['savepoint_active_after'],
            'transaction_active_after' => $this->transactionActive(),
            'dependencies' => [
                'sqlite-statement-journal-rollback-retry',
                'sqlite-pager-statement-subjournal-next70',
            ],
        ];
    }

    /**
     * @return list<array{name:string,savepoint:string,wal_start_frame:int,page_numbers:list<int>,wal_frame_indexes:list<int>}>
     */
    public function statementJournalState(): array
    {
        $state = [];
        foreach ($this->statementJournals as $journal) {
            $pageNumbers = array_keys($journal['page_images']);
            sort($pageNumbers, SORT_NUMERIC);
            $walFrameIndexes = array_keys($journal['wal_frames']);
            sort($walFrameIndexes, SORT_NUMERIC);
            $state[] = [
                'name' => $journal['name'],
                'savepoint' => $journal['savepoint_name'],
                'wal_start_frame' => $journal['wal_start_frame'],
                'page_numbers' => $pageNumbers,
                'wal_frame_indexes' => $walFrameIndexes,
            ];
        }

        return $state;
    }

    /**
     * @return array{page_size:int,restored_page_numbers:list<int>,restore_pages:list<array{page_number:int,database_offset:int,bytes:int,source_frame:string}>,missing_page_numbers:list<int>,released_savepoint_count:int,transaction_active_after:bool}
     */
    public function rollbackImagePlan(int $pageSize): array
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite page size must be positive');
        }
        if ($this->frames === []) {
            throw new \LogicException('SQLite transaction is not active');
        }

        $restoreImages = $this->rollbackPageImages();
        $restorePages = [];
        foreach ($restoreImages as $pageNumber => $restore) {
            if (strlen($restore['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite transaction rollback image for page {$pageNumber} does not match the page size");
            }
            $restorePages[] = [
                'page_number' => $pageNumber,
                'database_offset' => ($pageNumber - 1) * $pageSize,
                'bytes' => $pageSize,
                'source_frame' => $restore['frame'],
            ];
        }

        $missing = array_values(array_diff($this->pendingPageNumbers(), array_keys($restoreImages)));
        sort($missing, SORT_NUMERIC);

        return [
            'page_size' => $pageSize,
            'restored_page_numbers' => array_keys($restoreImages),
            'restore_pages' => $restorePages,
            'missing_page_numbers' => $missing,
            'released_savepoint_count' => max(0, count($this->frames) - 1),
            'transaction_active_after' => false,
        ];
    }

    public function rollbackDatabaseImage(string $databaseBytes, int $pageSize): string
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite page size must be positive');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite transaction rollback requires a database image aligned to the page size');
        }
        if ($this->frames === []) {
            throw new \LogicException('SQLite transaction is not active');
        }

        $rolledBack = $databaseBytes;
        foreach ($this->rollbackPageImages() as $pageNumber => $restore) {
            if (strlen($restore['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite transaction rollback image for page {$pageNumber} does not match the page size");
            }
            $offset = ($pageNumber - 1) * $pageSize;
            if ($offset + $pageSize > strlen($rolledBack)) {
                continue;
            }
            $rolledBack = substr_replace($rolledBack, $restore['image'], $offset, $pageSize);
        }

        return $rolledBack;
    }

    /**
     * @return array<int,array{image:string,frame:string}>
     */
    public function rollbackToPageImages(string $name): array
    {
        $index = $this->findFrame($name);
        $images = [];
        for ($frameIndex = $index; $frameIndex < count($this->frames); $frameIndex++) {
            foreach ($this->frames[$frameIndex]['page_images'] as $pageNumber => $pageImage) {
                if (!isset($images[$pageNumber])) {
                    $images[$pageNumber] = [
                        'image' => $pageImage,
                        'frame' => $this->frames[$frameIndex]['name'],
                    ];
                }
            }
        }
        ksort($images, SORT_NUMERIC);

        return $images;
    }

    /**
     * @return array<int,array{image:string,frame:string}>
     */
    public function rollbackPageImages(): array
    {
        if ($this->frames === []) {
            throw new \LogicException('SQLite transaction is not active');
        }

        $images = [];
        foreach ($this->frames as $frame) {
            foreach ($frame['page_images'] as $pageNumber => $pageImage) {
                if (!isset($images[$pageNumber])) {
                    $images[$pageNumber] = [
                        'image' => $pageImage,
                        'frame' => $frame['name'],
                    ];
                }
            }
        }
        ksort($images, SORT_NUMERIC);

        return $images;
    }

    /**
     * @return list<array{name:string,transaction:bool,page_numbers:list<int>}>
     */
    public function toArray(): array
    {
        return array_map(static function (array $frame): array {
            $pageNumbers = array_keys($frame['pages']);
            sort($pageNumbers, SORT_NUMERIC);

            return [
                'name' => $frame['name'],
                'transaction' => $frame['transaction'],
                'page_numbers' => $pageNumbers,
            ];
        }, $this->frames);
    }

    /**
     * @return list<array{name:string,transaction:bool,wal_start_frame:int,wal_frame_indexes:list<int>}>
     */
    public function walFrameState(): array
    {
        return array_map(static function (array $frame): array {
            $walFrameIndexes = array_keys($frame['wal_frames']);
            sort($walFrameIndexes, SORT_NUMERIC);

            return [
                'name' => $frame['name'],
                'transaction' => $frame['transaction'],
                'wal_start_frame' => $frame['wal_start_frame'],
                'wal_frame_indexes' => $walFrameIndexes,
            ];
        }, $this->frames);
    }

    private function findFrame(string $name): int
    {
        for ($index = count($this->frames) - 1; $index >= 0; $index--) {
            if (strcasecmp($this->frames[$index]['name'], $name) === 0) {
                return $index;
            }
        }

        throw new \InvalidArgumentException("SQLite savepoint does not exist: {$name}");
    }

    private function hasOpenFrame(string $name): bool
    {
        foreach ($this->frames as $frame) {
            if (strcasecmp($frame['name'], $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{name:string,frame_index:int,savepoint_name:string,wal_start_frame:int,prior_pages:array<int,true>,prior_page_images:array<int,string>,page_images:array<int,string>,wal_frames:array<int,array{page_number:int,commit_frame:bool}>}
     */
    private function statementJournal(string $statementName): array
    {
        if (!isset($this->statementJournals[$statementName])) {
            throw new \InvalidArgumentException("SQLite statement journal does not exist: {$statementName}");
        }

        return $this->statementJournals[$statementName];
    }

    /**
     * @return list<int>
     */
    private function pendingPageNumbersBeforeRollbackTarget(int $targetIndex): array
    {
        $pages = [];
        for ($frameIndex = 0; $frameIndex < $targetIndex; $frameIndex++) {
            foreach ($this->frames[$frameIndex]['pages'] as $pageNumber => $_) {
                $pages[$pageNumber] = true;
            }
        }

        $pageNumbers = array_keys($pages);
        sort($pageNumbers, SORT_NUMERIC);

        return $pageNumbers;
    }

    /**
     * @return list<int>
     */
    private function pendingWalFrameIndexesBeforeRollbackTarget(int $targetIndex): array
    {
        $frames = [];
        for ($frameIndex = 0; $frameIndex < $targetIndex; $frameIndex++) {
            foreach ($this->frames[$frameIndex]['wal_frames'] as $walFrameIndex => $_) {
                $frames[$walFrameIndex] = true;
            }
        }

        $frameIndexes = array_keys($frames);
        sort($frameIndexes, SORT_NUMERIC);

        return $frameIndexes;
    }

    private function clearStatementJournalsFromFrame(int $frameIndex): void
    {
        foreach ($this->statementJournals as $statementName => $journal) {
            if ($journal['frame_index'] >= $frameIndex) {
                unset($this->statementJournals[$statementName]);
            }
        }
    }
}
