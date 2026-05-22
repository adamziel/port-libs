<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderIndexState
{
    /**
     * @var array<string, array<string, FileInfo>>
     */
    private array $filesByDevice = [];

    public function __construct(private readonly string $localDeviceId = 'local')
    {
        $this->assertDeviceId($localDeviceId);
    }

    /**
     * @param list<FileInfo> $files
     */
    public function update(string $deviceId, array $files, bool $reset = false): void
    {
        $this->assertDeviceId($deviceId);
        if ($reset) {
            $this->filesByDevice[$deviceId] = [];
        }

        foreach ($files as $file) {
            if (!$file instanceof FileInfo) {
                throw new \InvalidArgumentException('Expected only FileInfo instances');
            }
            if ($file->name === '') {
                throw new \InvalidArgumentException('Indexed files must have a name');
            }

            $this->filesByDevice[$deviceId][$file->name] = $file;
        }
    }

    public function dropAllFiles(string $deviceId): void
    {
        $this->assertDeviceId($deviceId);
        unset($this->filesByDevice[$deviceId]);
    }

    /**
     * @param list<string> $names
     */
    public function dropFilesNamed(string $deviceId, array $names): void
    {
        $this->assertDeviceId($deviceId);
        foreach ($names as $name) {
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('Dropped file names must be non-empty strings');
            }
            unset($this->filesByDevice[$deviceId][$name]);
        }
        if (($this->filesByDevice[$deviceId] ?? []) === []) {
            unset($this->filesByDevice[$deviceId]);
        }
    }

    public function deviceFile(string $deviceId, string $name): ?FileInfo
    {
        $this->assertDeviceId($deviceId);
        if ($name === '') {
            throw new \InvalidArgumentException('File name must not be empty');
        }

        return $this->filesByDevice[$deviceId][$name] ?? null;
    }

    public function globalFile(string $name): ?FileInfo
    {
        if ($name === '') {
            throw new \InvalidArgumentException('File name must not be empty');
        }

        $entries = $this->entriesForName($name);
        if ($entries === []) {
            return null;
        }
        usort($entries, $this->compareEntries(...));

        foreach ($entries as $entry) {
            if (!$entry['file']->isInvalid()) {
                return $entry['file'];
            }
        }

        return $entries[0]['file'];
    }

    /**
     * @return list<FileInfo>
     */
    public function globalFiles(): array
    {
        $files = [];
        foreach ($this->allNames() as $name) {
            $global = $this->globalFile($name);
            if ($global !== null) {
                $files[] = $global;
            }
        }

        return $files;
    }

    /**
     * @return list<FileInfo>
     */
    public function neededFiles(string $deviceId, int $limit = 0, int $offset = 0): array
    {
        $this->assertDeviceId($deviceId);
        if ($limit < 0 || $offset < 0) {
            throw new \InvalidArgumentException('Need pagination values must not be negative');
        }

        $files = [];
        foreach ($this->globalFiles() as $global) {
            if ($deviceId === $this->localDeviceId) {
                if ($this->localNeedsGlobal($global)) {
                    $files[] = $global;
                }
                continue;
            }

            if ($this->remoteNeedsGlobal($deviceId, $global)) {
                $files[] = $global;
            }
        }

        usort($files, static fn (FileInfo $left, FileInfo $right): int => strcmp($left->name, $right->name));

        if ($offset > 0 || $limit > 0) {
            return array_slice($files, $offset, $limit > 0 ? $limit : null);
        }

        return $files;
    }

    public function countGlobal(): FolderCounts
    {
        return $this->countFiles(array_values(array_filter(
            $this->globalFiles(),
            static fn (FileInfo $file): bool => !$file->isInvalid(),
        )));
    }

    public function countNeed(string $deviceId): FolderCounts
    {
        return $this->countFiles($this->neededFiles($deviceId));
    }

    /**
     * @return list<string>
     */
    private function allNames(): array
    {
        $names = [];
        foreach ($this->filesByDevice as $files) {
            foreach (array_keys($files) as $name) {
                $names[$name] = true;
            }
        }
        $names = array_keys($names);
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @return list<array{device:string, file:FileInfo}>
     */
    private function entriesForName(string $name): array
    {
        $entries = [];
        foreach ($this->filesByDevice as $device => $files) {
            if (isset($files[$name])) {
                $entries[] = [
                    'device' => $device,
                    'file' => $files[$name],
                ];
            }
        }

        return $entries;
    }

    private function localNeedsGlobal(FileInfo $global): bool
    {
        if ($global->isInvalid()) {
            return false;
        }

        $entries = $this->entriesForName($global->name);
        usort($entries, $this->compareEntries(...));

        $globalIndex = null;
        $localIndex = null;
        foreach ($entries as $index => $entry) {
            if ($globalIndex === null && !$entry['file']->isInvalid()) {
                $globalIndex = $index;
            }
            if ($entry['device'] === $this->localDeviceId) {
                $localIndex = $index;
            }
        }
        $globalIndex ??= 0;

        $local = $this->filesByDevice[$this->localDeviceId][$global->name] ?? null;
        $hasLocal = ($localIndex !== null && $localIndex <= $globalIndex)
            || ($local !== null && $local->version->equal($global->version))
            || ($local === null && $global->isDeleted());

        return !$hasLocal;
    }

    private function remoteNeedsGlobal(string $deviceId, FileInfo $global): bool
    {
        if ($global->isInvalid()) {
            return false;
        }

        $remote = $this->filesByDevice[$deviceId][$global->name] ?? null;
        if (!$global->isDeleted()) {
            return $remote === null || !$remote->version->equal($global->version);
        }

        return $remote !== null && !$remote->isDeleted() && !$remote->isInvalid();
    }

    /**
     * @param list<FileInfo> $files
     */
    private function countFiles(array $files): FolderCounts
    {
        $bytes = 0;
        $fileCount = 0;
        $directories = 0;
        $symlinks = 0;
        $deleted = 0;

        foreach ($files as $file) {
            if ($file->isDeleted()) {
                $deleted++;
                continue;
            }

            $bytes += $file->size;
            if ($file->isDirectory()) {
                $directories++;
            } elseif ($file->isSymlink()) {
                $symlinks++;
            } else {
                $fileCount++;
            }
        }

        return new FolderCounts(
            bytes: $bytes,
            files: $fileCount,
            directories: $directories,
            symlinks: $symlinks,
            deleted: $deleted,
        );
    }

    /**
     * @param array{device:string, file:FileInfo} $left
     * @param array{device:string, file:FileInfo} $right
     */
    private function compareEntries(array $left, array $right): int
    {
        $leftFile = $left['file'];
        $rightFile = $right['file'];

        $versionComparison = $leftFile->version->compare($rightFile->version);
        return match ($versionComparison) {
            VersionVector::ORDER_EQUAL => $this->compareEqualVersionEntries($left, $right),
            VersionVector::ORDER_GREATER => -1,
            VersionVector::ORDER_LESSER => 1,
            VersionVector::ORDER_CONCURRENT_GREATER,
            VersionVector::ORDER_CONCURRENT_LESSER => $this->compareConcurrentEntries($left, $right, $versionComparison),
            default => 0,
        };
    }

    /**
     * @param array{device:string, file:FileInfo} $left
     * @param array{device:string, file:FileInfo} $right
     */
    private function compareEqualVersionEntries(array $left, array $right): int
    {
        $invalid = $this->compareInvalidity($left['file'], $right['file']);
        if ($invalid !== 0) {
            return $invalid;
        }

        return $this->deviceRank($left['device']) <=> $this->deviceRank($right['device'])
            ?: strcmp($left['device'], $right['device']);
    }

    /**
     * @param array{device:string, file:FileInfo} $left
     * @param array{device:string, file:FileInfo} $right
     */
    private function compareConcurrentEntries(array $left, array $right, string $versionComparison): int
    {
        $invalid = $this->compareInvalidity($left['file'], $right['file']);
        if ($invalid !== 0) {
            return $invalid;
        }

        $modified = [$left['file']->modifiedS, $left['file']->modifiedNs] <=> [$right['file']->modifiedS, $right['file']->modifiedNs];
        if ($modified !== 0) {
            return -$modified;
        }

        return $versionComparison === VersionVector::ORDER_CONCURRENT_GREATER ? -1 : 1;
    }

    private function compareInvalidity(FileInfo $left, FileInfo $right): int
    {
        if ($left->isInvalid() === $right->isInvalid()) {
            return 0;
        }

        return $left->isInvalid() ? 1 : -1;
    }

    private function deviceRank(string $deviceId): int
    {
        return $deviceId === $this->localDeviceId ? 0 : 1;
    }

    private function assertDeviceId(string $deviceId): void
    {
        if ($deviceId === '') {
            throw new \InvalidArgumentException('Device ID must not be empty');
        }
    }
}
