<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class PullDbUpdater
{
    public const DB_UPDATE_HANDLE_DIR = 'dbUpdateHandleDir';
    public const DB_UPDATE_DELETE_DIR = 'dbUpdateDeleteDir';
    public const DB_UPDATE_HANDLE_FILE = 'dbUpdateHandleFile';
    public const DB_UPDATE_DELETE_FILE = 'dbUpdateDeleteFile';
    public const DB_UPDATE_SHORTCUT_FILE = 'dbUpdateShortcutFile';
    public const DB_UPDATE_HANDLE_SYMLINK = 'dbUpdateHandleSymlink';
    public const DB_UPDATE_INVALIDATE = 'dbUpdateHandleInvalidate';

    /**
     * @var array<string, true>
     */
    private array $changedDirs = [];

    /**
     * @var list<list<FileInfo>>
     */
    private array $updateBatches = [];

    /**
     * @var list<string>
     */
    private array $fsyncedDirectories = [];

    /**
     * @var array<string, string>
     */
    private array $fsyncErrors = [];

    /**
     * @var list<array{name:string, deleted:bool}>
     */
    private array $receivedFiles = [];

    private int $changed = 0;

    private bool $foundReceivedFile = false;

    private ?FileInfo $lastReceivedFile = null;

    private FileInfoBatch $batch;

    /**
     * @param callable(list<FileInfo>): (\Throwable|null)|null $updateLocalsFromPulling
     * @param callable(string): (\Throwable|null)|null $syncDirectory
     * @param callable(string, bool): void|null $receivedFile
     */
    public function __construct(
        private readonly bool $disableFsync = false,
        private readonly mixed $updateLocalsFromPulling = null,
        private readonly mixed $syncDirectory = null,
        private readonly mixed $receivedFile = null,
    ) {
        foreach ([
            'updateLocalsFromPulling' => $this->updateLocalsFromPulling,
            'syncDirectory' => $this->syncDirectory,
            'receivedFile' => $this->receivedFile,
        ] as $name => $callback) {
            if ($callback !== null && !is_callable($callback)) {
                throw new \InvalidArgumentException($name . ' must be callable or null');
            }
        }

        $this->batch = FileInfoBatch::withFlushFunction($this->flushBatch(...));
    }

    public function append(FileInfo $file, string $jobType): ?\Throwable
    {
        $this->assertJobType($jobType);
        $this->trackChangedDirectory($file, $jobType);

        if (!$file->isInvalid() && $this->isReceivedFileCandidate($jobType)) {
            $this->foundReceivedFile = true;
            $this->lastReceivedFile = $file;
        }

        $this->batch->append($file->withSequence(0));
        $this->changed++;

        return $this->batch->flushIfFull();
    }

    public function tick(): ?\Throwable
    {
        return $this->batch->flush();
    }

    public function close(): int
    {
        $this->batch->flush();

        return $this->changed;
    }

    public function changedCount(): int
    {
        return $this->changed;
    }

    /**
     * @return list<list<FileInfo>>
     */
    public function updateBatches(): array
    {
        return $this->updateBatches;
    }

    /**
     * @return list<string>
     */
    public function fsyncedDirectories(): array
    {
        return $this->fsyncedDirectories;
    }

    /**
     * @return array<string, string>
     */
    public function fsyncErrors(): array
    {
        return $this->fsyncErrors;
    }

    /**
     * @return list<array{name:string, deleted:bool}>
     */
    public function receivedFiles(): array
    {
        return $this->receivedFiles;
    }

    /**
     * @param list<FileInfo> $files
     */
    private function flushBatch(array $files): ?\Throwable
    {
        foreach (array_keys($this->changedDirs) as $dir) {
            unset($this->changedDirs[$dir]);
            if ($this->disableFsync) {
                continue;
            }

            $this->fsyncedDirectories[] = $dir;
            if ($this->syncDirectory === null) {
                continue;
            }

            try {
                $result = ($this->syncDirectory)($dir);
            } catch (\Throwable $throwable) {
                $this->fsyncErrors[$dir] = $throwable->getMessage();
                continue;
            }

            if ($result instanceof \Throwable) {
                $this->fsyncErrors[$dir] = $result->getMessage();
            }
        }

        $this->updateBatches[] = $files;
        if ($this->updateLocalsFromPulling !== null) {
            try {
                $result = ($this->updateLocalsFromPulling)($files);
            } catch (\Throwable $throwable) {
                return $throwable;
            }
            if ($result instanceof \Throwable) {
                return $result;
            }
            if ($result !== null) {
                return new \UnexpectedValueException('updateLocalsFromPulling must return null or Throwable');
            }
        }

        if ($this->foundReceivedFile && $this->lastReceivedFile !== null) {
            $event = [
                'name' => $this->lastReceivedFile->name,
                'deleted' => $this->lastReceivedFile->isDeleted(),
            ];
            $this->receivedFiles[] = $event;
            if ($this->receivedFile !== null) {
                ($this->receivedFile)($event['name'], $event['deleted']);
            }
            $this->foundReceivedFile = false;
            $this->lastReceivedFile = null;
        }

        return null;
    }

    private function trackChangedDirectory(FileInfo $file, string $jobType): void
    {
        switch ($jobType) {
            case self::DB_UPDATE_HANDLE_FILE:
            case self::DB_UPDATE_SHORTCUT_FILE:
                $this->changedDirs[$this->directoryName($file->name)] = true;
                break;
            case self::DB_UPDATE_HANDLE_DIR:
                $this->changedDirs[$file->name] = true;
                break;
            default:
                break;
        }
    }

    private function directoryName(string $name): string
    {
        $slash = strrpos($name, '/');

        return $slash === false ? '.' : substr($name, 0, $slash);
    }

    private function isReceivedFileCandidate(string $jobType): bool
    {
        return $jobType === self::DB_UPDATE_HANDLE_FILE
            || $jobType === self::DB_UPDATE_DELETE_FILE;
    }

    private function assertJobType(string $jobType): void
    {
        if (!in_array($jobType, [
            self::DB_UPDATE_HANDLE_DIR,
            self::DB_UPDATE_DELETE_DIR,
            self::DB_UPDATE_HANDLE_FILE,
            self::DB_UPDATE_DELETE_FILE,
            self::DB_UPDATE_SHORTCUT_FILE,
            self::DB_UPDATE_HANDLE_SYMLINK,
            self::DB_UPDATE_INVALIDATE,
        ], true)) {
            throw new \InvalidArgumentException('Unknown Syncthing db update type');
        }
    }
}
