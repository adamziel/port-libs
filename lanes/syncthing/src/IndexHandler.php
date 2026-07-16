<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class IndexHandler
{
    private bool $paused = false;

    /**
     * @var array<string, FileInfo>
     */
    private array $remoteFiles = [];

    /**
     * @var list<array{description:string, extra:array<string, string>}>
     */
    private array $sequenceAnomalies = [];

    /**
     * @var list<array{type:string, data:array<string, mixed>}>
     */
    private array $receiveEvents = [];

    public function __construct(
        private readonly string $folder,
        private int $localPrevSequence = 0,
        private int $sentPrevSequence = 0,
        private readonly bool $folderIsReceiveEncrypted = false,
        private mixed $runner = null,
        private int $remoteSequence = 0,
    ) {
        if ($folder === '') {
            throw new \InvalidArgumentException('Folder ID must not be empty');
        }
        if ($this->localPrevSequence < 0 || $this->sentPrevSequence < 0 || $this->remoteSequence < 0) {
            throw new \InvalidArgumentException('Index handler sequence numbers must not be negative');
        }
    }

    /**
     * @param iterable<FileInfo> $localFilesBySequence
     * @param callable(Index|IndexUpdate): (\Throwable|null) $emit
     */
    public function sendIndexTo(iterable $localFilesBySequence, callable $emit): ?\Throwable
    {
        $initial = $this->localPrevSequence === 0;
        $previousWasDelete = false;
        $previousSequence = null;
        $emit = \Closure::fromCallable($emit);

        $batch = FileInfoBatch::withFlushFunction(function (array $files) use (&$initial, $emit): ?\Throwable {
            if ($files === []) {
                throw new \LogicException('bug: flush called with empty batch');
            }

            $lastSequence = $files[count($files) - 1]->sequence;
            $message = $initial
                ? new Index($this->folder, $files, lastSequence: $lastSequence)
                : new IndexUpdate($this->folder, $files, lastSequence: $lastSequence, prevSequence: $this->sentPrevSequence);

            $initial = false;
            $result = $emit($message);
            if ($result instanceof \Throwable) {
                return $result;
            }
            if ($result !== null) {
                throw new \UnexpectedValueException('IndexHandler emit callback must return null or Throwable');
            }

            $this->sentPrevSequence = $lastSequence;

            return null;
        });

        foreach ($localFilesBySequence as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException('Expected only FileInfo instances');
            }

            if ($batch->full() && (!$file->isDeleted() || $previousWasDelete)) {
                break;
            }

            if ($file->sequence < $this->localPrevSequence + 1) {
                throw new \RuntimeException('database returned sequence lower than requested');
            }
            if ($previousSequence !== null && $file->sequence <= $previousSequence) {
                throw new \RuntimeException('database returned non-increasing sequence');
            }

            $previousSequence = $file->sequence;
            $this->localPrevSequence = $file->sequence;

            if ($this->folderIsReceiveEncrypted && $file->isReceiveOnlyChanged()) {
                continue;
            }

            $prepared = self::prepareFileInfoForIndex($file);
            $previousWasDelete = $prepared->isDeleted();
            $batch->append($prepared);
        }

        return $batch->flush();
    }

    public function folder(): string
    {
        return $this->folder;
    }

    public function folderIsReceiveEncrypted(): bool
    {
        return $this->folderIsReceiveEncrypted;
    }

    public function pause(): void
    {
        $this->paused = true;
        $this->runner = null;
    }

    public function resume(mixed $runner = null): void
    {
        $this->paused = false;
        $this->runner = $runner;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function runner(): mixed
    {
        return $this->runner;
    }

    /**
     * @param iterable<FileInfo> $localFilesBySequence
     *
     * @return list<Index|IndexUpdate>
     */
    public function buildIndexMessages(iterable $localFilesBySequence): array
    {
        $messages = [];
        $error = $this->sendIndexTo(
            $localFilesBySequence,
            static function (Index|IndexUpdate $message) use (&$messages): ?\Throwable {
                $messages[] = $message;

                return null;
            },
        );
        if ($error !== null) {
            throw $error;
        }

        return $messages;
    }

    /**
     * @param iterable<FileInfo> $localFilesBySequence
     *
     * @return list<string>
     */
    public function buildIndexFrames(
        iterable $localFilesBySequence,
        int $compressionMode = Device::COMPRESSION_NEVER,
        string $directorySeparator = DIRECTORY_SEPARATOR,
    ): array {
        $frames = [];
        $error = $this->sendIndexTo(
            $localFilesBySequence,
            static function (Index|IndexUpdate $message) use (&$frames, $compressionMode, $directorySeparator): ?\Throwable {
                if ($message instanceof Index) {
                    $frames[] = BepWire::encodeIndexMessage($message->normalizedForWire($directorySeparator), $compressionMode);
                } else {
                    $frames[] = BepWire::encodeIndexUpdateMessage($message->normalizedForWire($directorySeparator), $compressionMode);
                }

                return null;
            },
        );
        if ($error !== null) {
            throw $error;
        }

        return $frames;
    }

    public static function prepareFileInfoForIndex(FileInfo $file, int $encryptionTrailerSize = 0): FileInfo
    {
        if ($encryptionTrailerSize < 0) {
            throw new \InvalidArgumentException('Encrypted file trailer size must not be negative');
        }
        if ($encryptionTrailerSize > $file->size) {
            throw new \LengthException('Encrypted file trailer size exceeds FileInfo size');
        }

        if (!$file->isReceiveOnlyChanged() && $encryptionTrailerSize === 0) {
            return $file;
        }

        return self::copyFileInfo(
            $file,
            version: $file->isReceiveOnlyChanged() ? new VersionVector() : $file->version,
            size: $file->size - $encryptionTrailerSize,
        );
    }

    /**
     * @param list<FileInfo> $files
     *
     * @return list<FileDownloadProgressUpdate>
     */
    public static function forgetUpdatesForReceivedIndex(array $files): array
    {
        $updates = [];
        foreach ($files as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException('Expected only FileInfo instances');
            }
            if ($file->isSymlink() || $file->isDirectory() || $file->isDeleted()) {
                continue;
            }

            $updates[] = new FileDownloadProgressUpdate(
                updateType: FileDownloadProgressUpdate::TYPE_FORGET,
                name: $file->name,
                version: $file->version,
            );
        }

        return $updates;
    }

    /**
     * @param list<FileInfo> $files
     */
    public function receiveIndex(
        array $files,
        bool $update,
        string $operation,
        int $prevSequence = 0,
        int $lastSequence = 0,
        ?DeviceDownloadState $downloads = null,
        string $remoteDeviceIdHex = '',
        ?callable $eventLogger = null,
    ): IndexReceiveResult {
        if ($operation === '') {
            throw new \InvalidArgumentException('Index receive operation must not be empty');
        }
        if ($prevSequence < 0 || $lastSequence < 0) {
            throw new \InvalidArgumentException('Index receive sequence numbers must not be negative');
        }
        foreach ($files as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException('Expected only FileInfo instances');
            }
        }
        if ($this->paused) {
            throw new \RuntimeException($this->folder . ': folder is paused');
        }

        $anomalyStart = count($this->sequenceAnomalies);

        try {
            $forgetUpdates = self::forgetUpdatesForReceivedIndex($files);
            $downloads?->update($this->folder, $forgetUpdates);

            if (!$update) {
                $this->remoteFiles = [];
            }

            if ($prevSequence > 0 && $prevSequence !== $this->remoteSequence) {
                $this->logSequenceAnomaly('index update with unexpected sequence', [
                    'prevSeq' => $prevSequence,
                    'lastSeq' => $lastSequence,
                    'batch' => count($files),
                    'expectedPrev' => $this->remoteSequence,
                ]);
            }

            $previousSequence = null;
            foreach ($files as $index => $file) {
                if ($file->sequence < $prevSequence) {
                    $this->logSequenceAnomaly('file with sequence before prevSequence', [
                        'prevSeq' => $prevSequence,
                        'lastSeq' => $lastSequence,
                        'batch' => count($files),
                        'seenSeq' => $file->sequence,
                        'atIndex' => $index,
                    ]);
                }
                if ($lastSequence > 0 && $file->sequence > $lastSequence) {
                    $this->logSequenceAnomaly('file with sequence after lastSequence', [
                        'prevSeq' => $prevSequence,
                        'lastSeq' => $lastSequence,
                        'batch' => count($files),
                        'seenSeq' => $file->sequence,
                        'atIndex' => $index,
                    ]);
                }
                if ($previousSequence !== null && $file->sequence <= $previousSequence) {
                    $this->logSequenceAnomaly('index update with non-increasing sequence', [
                        'prevSeq' => $prevSequence,
                        'lastSeq' => $lastSequence,
                        'batch' => count($files),
                        'seenSeq' => $file->sequence,
                        'atIndex' => $index,
                        'precedingSeq' => $previousSequence,
                    ]);
                }
                if ($previousSequence !== null && $file->sequence === $previousSequence) {
                    throw new \RuntimeException('duplicate remote sequence number ' . $previousSequence);
                }

                $previousSequence = $file->sequence;
            }

            if ($lastSequence > 0 && $files !== [] && $lastSequence !== $files[count($files) - 1]->sequence) {
                $this->logSequenceAnomaly('index update with unexpected last sequence', [
                    'prevSeq' => $prevSequence,
                    'lastSeq' => $lastSequence,
                    'batch' => count($files),
                    'seenSeq' => $files[count($files) - 1]->sequence,
                ]);
            }

            foreach ($files as $file) {
                $this->remoteFiles[$file->name] = $file;
            }
            if ($files !== []) {
                $this->remoteSequence = $files[count($files) - 1]->sequence;
            }

            if ($lastSequence > 0 && $files !== [] && $this->remoteSequence !== $lastSequence) {
                $this->logSequenceAnomaly('unexpected sequence after update', [
                    'prevSeq' => $prevSequence,
                    'lastSeq' => $lastSequence,
                    'batch' => count($files),
                    'seenSeq' => $files[count($files) - 1]->sequence,
                    'returnedSeq' => $this->remoteSequence,
                ]);
            }

            $event = [
                'type' => 'RemoteIndexUpdated',
                'data' => [
                    'device' => $remoteDeviceIdHex,
                    'folder' => $this->folder,
                    'items' => count($files),
                    'sequence' => $this->remoteSequence,
                    'version' => $this->remoteSequence,
                ],
            ];
            $this->receiveEvents[] = $event;
            if ($eventLogger !== null) {
                $eventLogger($event['type'], $event['data']);
            }

            return new IndexReceiveResult(
                folder: $this->folder,
                remoteDeviceIdHex: $remoteDeviceIdHex,
                update: $update,
                operation: $operation,
                prevSequence: $prevSequence,
                lastSequence: $lastSequence,
                sequence: $this->remoteSequence,
                items: count($files),
                forgetUpdates: $forgetUpdates,
                anomalies: array_slice($this->sequenceAnomalies, $anomalyStart),
                event: $event,
            );
        } finally {
            $this->schedulePull();
        }
    }

    public function localPrevSequence(): int
    {
        return $this->localPrevSequence;
    }

    public function sentPrevSequence(): int
    {
        return $this->sentPrevSequence;
    }

    public function remoteSequence(): int
    {
        return $this->remoteSequence;
    }

    public function remoteFile(string $name): ?FileInfo
    {
        return $this->remoteFiles[$name] ?? null;
    }

    /**
     * @return array<string, FileInfo>
     */
    public function remoteFiles(): array
    {
        ksort($this->remoteFiles, SORT_STRING);

        return $this->remoteFiles;
    }

    /**
     * @return list<array{description:string, extra:array<string, string>}>
     */
    public function sequenceAnomalies(): array
    {
        return $this->sequenceAnomalies;
    }

    /**
     * @return list<array{type:string, data:array<string, mixed>}>
     */
    public function receiveEvents(): array
    {
        return $this->receiveEvents;
    }

    /**
     * @param array<string, int> $extra
     */
    private function logSequenceAnomaly(string $description, array $extra): void
    {
        $strings = [];
        foreach ($extra as $key => $value) {
            $strings[$key] = (string) $value;
        }

        $this->sequenceAnomalies[] = [
            'description' => $description,
            'extra' => $strings,
        ];
    }

    private function schedulePull(): void
    {
        if ($this->runner === null) {
            return;
        }
        if (is_callable($this->runner)) {
            ($this->runner)();

            return;
        }
        if (is_object($this->runner) && method_exists($this->runner, 'schedulePull')) {
            $this->runner->schedulePull();

            return;
        }
        if (is_object($this->runner) && method_exists($this->runner, 'SchedulePull')) {
            $this->runner->SchedulePull();
        }
    }

    private static function copyFileInfo(
        FileInfo $file,
        ?VersionVector $version = null,
        ?int $size = null,
    ): FileInfo {
        return new FileInfo(
            name: $file->name,
            modifiedS: $file->modifiedS,
            modifiedNs: $file->modifiedNs,
            version: $version ?? $file->version,
            deleted: $file->deleted,
            localFlags: $file->localFlags,
            size: $size ?? $file->size,
            blocksHash: $file->blocksHash,
            previousBlocksHash: $file->previousBlocksHash,
            type: $file->type,
            permissions: $file->permissions,
            noPermissions: $file->noPermissions,
            rawBlockSize: $file->rawBlockSize,
            sequence: $file->sequence,
            symlinkTarget: $file->symlinkTarget,
            blocks: $file->blocks,
            unixOwnerName: $file->unixOwnerName,
            unixGroupName: $file->unixGroupName,
            unixUid: $file->unixUid,
            unixGid: $file->unixGid,
            modifiedBy: $file->modifiedBy,
            encryptedPayload: $file->encryptedPayload,
        );
    }
}
