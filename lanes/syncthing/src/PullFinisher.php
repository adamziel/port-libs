<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class PullFinisher
{
    /**
     * @var list<array{folder:string, item:string, error:?string, type:string, action:string}>
     */
    private array $itemFinishedEvents = [];

    /**
     * @var list<array{path:string, error:string}>
     */
    private array $pullErrors = [];

    /**
     * @var array{total:int, reused:int, pulled:int, copyOrigin:int, copyElsewhere:int}
     */
    private array $blockStats = [
        'total' => 0,
        'reused' => 0,
        'pulled' => 0,
        'copyOrigin' => 0,
        'copyElsewhere' => 0,
    ];

    /**
     * @param callable(array{folder:string, item:string, error:?string, type:string, action:string}): void|null $itemFinished
     */
    public function __construct(
        private readonly PullJobQueue $queue,
        private readonly ?ProgressEmitter $progressEmitter = null,
        private readonly ?PullDbUpdater $dbUpdater = null,
        private readonly string $folderId = '',
        private readonly bool $receiveEncryptedFolder = false,
        private readonly mixed $itemFinished = null,
        private readonly ?FolderErrorTracker $folderErrors = null,
        private readonly ?PullScanner $pullScanner = null,
    ) {
        if ($this->itemFinished !== null && !is_callable($this->itemFinished)) {
            throw new \InvalidArgumentException('ItemFinished callback must be callable or null');
        }
    }

    public function finish(
        PullTemporaryFile $state,
        int $reused = 0,
        int $copyTotal = 0,
        int $pullTotal = 0,
        int $copyOrigin = 0,
    ): PullFinisherResult {
        $this->assertCounters($reused, $copyTotal, $pullTotal, $copyOrigin);

        $finalization = $state->finalize();
        if (!$finalization->closed) {
            return new PullFinisherResult(false, $finalization);
        }

        $file = $state->file;
        $this->queue->done($file->name);
        $this->pullScanner?->queueFinalization($finalization);

        $error = $finalization->error;
        if ($error === null && $finalization->finalized && $finalization->dbUpdateType !== '' && $this->dbUpdater !== null) {
            $dbError = $this->dbUpdater->append($file, $finalization->dbUpdateType);
            if ($dbError instanceof \Throwable) {
                $error = $dbError->getMessage();
            }
        }

        $pullError = null;
        if ($error !== null) {
            $pullError = 'finishing: ' . $error;
            $this->pullErrors[] = [
                'path' => $file->name,
                'error' => $pullError,
            ];
            $this->folderErrors?->newPullError($file->name, $pullError);
        } elseif ($finalization->finalized) {
            $this->addBlockStats($file, $reused, $copyTotal, $pullTotal, $copyOrigin);
        }

        if (!$this->receiveEncryptedFolder && $this->progressEmitter !== null) {
            $this->progressEmitter->deregister($this->folderId, $file->name);
        }

        $event = [
            'folder' => $this->folderId,
            'item' => $file->name,
            'error' => $error,
            'type' => 'file',
            'action' => 'update',
        ];
        $this->itemFinishedEvents[] = $event;
        if ($this->itemFinished !== null) {
            ($this->itemFinished)($event);
        }

        return new PullFinisherResult(true, $finalization, $event, $pullError);
    }

    /**
     * @return list<array{folder:string, item:string, error:?string, type:string, action:string}>
     */
    public function itemFinishedEvents(): array
    {
        return $this->itemFinishedEvents;
    }

    /**
     * @return list<array{path:string, error:string}>
     */
    public function pullErrors(): array
    {
        return $this->pullErrors;
    }

    /**
     * @return array{total:int, reused:int, pulled:int, copyOrigin:int, copyElsewhere:int}
     */
    public function blockStats(): array
    {
        return $this->blockStats;
    }

    private function assertCounters(int $reused, int $copyTotal, int $pullTotal, int $copyOrigin): void
    {
        foreach ([$reused, $copyTotal, $pullTotal, $copyOrigin] as $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException('Pull finisher counters must not be negative');
            }
        }
        if ($copyOrigin > $copyTotal) {
            throw new \InvalidArgumentException('Origin copy count cannot exceed total copy count');
        }
    }

    private function addBlockStats(FileInfo $file, int $reused, int $copyTotal, int $pullTotal, int $copyOrigin): void
    {
        $minBlocksPerBlock = max(1, intdiv($file->blockSize(), BlockList::MIN_BLOCK_SIZE));

        $this->blockStats['total'] += ($reused + $copyTotal + $pullTotal) * $minBlocksPerBlock;
        $this->blockStats['reused'] += $reused * $minBlocksPerBlock;
        $this->blockStats['pulled'] += $pullTotal * $minBlocksPerBlock;
        $this->blockStats['copyOrigin'] += $copyOrigin * $minBlocksPerBlock;
        $this->blockStats['copyElsewhere'] += ($copyTotal - $copyOrigin) * $minBlocksPerBlock;
    }
}
