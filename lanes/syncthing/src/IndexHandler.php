<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class IndexHandler
{
    private bool $paused = false;

    public function __construct(
        private readonly string $folder,
        private int $localPrevSequence = 0,
        private int $sentPrevSequence = 0,
        private readonly bool $folderIsReceiveEncrypted = false,
        private mixed $runner = null,
    ) {
        if ($folder === '') {
            throw new \InvalidArgumentException('Folder ID must not be empty');
        }
        if ($this->localPrevSequence < 0 || $this->sentPrevSequence < 0) {
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

    public function localPrevSequence(): int
    {
        return $this->localPrevSequence;
    }

    public function sentPrevSequence(): int
    {
        return $this->sentPrevSequence;
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
