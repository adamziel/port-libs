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

    public function release(string $name): void
    {
        $index = $this->findFrame($name);
        $releasedFrames = array_splice($this->frames, $index);
        $releasedPages = [];
        foreach ($releasedFrames as $frame) {
            foreach ($frame['pages'] as $pageNumber => $_) {
                $releasedPages[$pageNumber] = true;
            }
        }

        if ($this->frames === []) {
            $this->maxWalFrame = 0;
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
    }

    public function rollback(): void
    {
        if ($this->frames === []) {
            throw new \LogicException('SQLite transaction is not active');
        }

        $this->frames = [];
        $this->maxWalFrame = 0;
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
            if ($this->frames[$index]['name'] === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException("SQLite savepoint does not exist: {$name}");
    }
}
