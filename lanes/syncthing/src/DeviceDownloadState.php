<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class DeviceDownloadState
{
    /**
     * @var array<string, array<string, array{version:VersionVector, blockIndexes:list<int>, blockSize:int}>>
     */
    private array $folders = [];

    /**
     * @param list<FileDownloadProgressUpdate> $updates
     */
    public function update(string $folder, array $updates): void
    {
        $files = $this->folders[$folder] ?? [];

        foreach ($updates as $update) {
            if (!$update instanceof FileDownloadProgressUpdate) {
                throw new \InvalidArgumentException('Expected only FileDownloadProgressUpdate instances');
            }

            $local = $files[$update->name] ?? null;
            if ($update->isForget()) {
                if ($local !== null && $local['version']->equal($update->version)) {
                    unset($files[$update->name]);
                }
                continue;
            }

            if (!$update->isAppend()) {
                continue;
            }

            if ($local === null || !$local['version']->equal($update->version)) {
                $files[$update->name] = [
                    'version' => $update->version,
                    'blockIndexes' => $update->blockIndexes,
                    'blockSize' => $update->blockSize,
                ];
                continue;
            }

            $local['blockIndexes'] = array_merge($local['blockIndexes'], $update->blockIndexes);
            $files[$update->name] = $local;
        }

        $this->folders[$folder] = $files;
    }

    public function has(string $folder, string $file, VersionVector $version, int $index): bool
    {
        if ($index < 0) {
            return false;
        }

        $local = $this->folders[$folder][$file] ?? null;
        if ($local === null || !$local['version']->equal($version)) {
            return false;
        }

        return in_array($index, $local['blockIndexes'], true);
    }

    /**
     * @return array<string, int>
     */
    public function getBlockCounts(string $folder): array
    {
        $counts = [];
        foreach ($this->folders[$folder] ?? [] as $name => $state) {
            $counts[$name] = count($state['blockIndexes']);
        }

        ksort($counts, SORT_STRING);

        return $counts;
    }

    public function bytesDownloaded(string $folder): int
    {
        $bytes = 0;
        foreach ($this->folders[$folder] ?? [] as $state) {
            $blockSize = $state['blockSize'] !== 0 ? $state['blockSize'] : BlockList::MIN_BLOCK_SIZE;
            $bytes += count($state['blockIndexes']) * $blockSize;
        }

        return $bytes;
    }
}
