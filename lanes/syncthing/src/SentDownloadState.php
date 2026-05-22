<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class SentDownloadState
{
    /**
     * @var array<string, array<string, array{version:VersionVector, blockIndexes:list<int>, updated:int, created:int, blockSize:int}>>
     */
    private array $folders = [];

    /**
     * @param list<ActiveDownload> $downloads
     *
     * @return list<FileDownloadProgressUpdate>
     */
    public function update(string $folder, array $downloads, int $minBlocks = 0): array
    {
        if ($minBlocks < 0) {
            throw new \InvalidArgumentException('Minimum block threshold must not be negative');
        }

        $files = $this->folders[$folder] ?? [];
        $seen = [];
        $updates = [];

        foreach ($downloads as $download) {
            if (!$download instanceof ActiveDownload) {
                throw new \InvalidArgumentException('Expected only ActiveDownload instances');
            }
            if (!$download->eligibleForTemporaryIndex($folder, $minBlocks)) {
                continue;
            }

            $name = $download->file->name;
            $seen[$name] = true;
            $available = $download->availableBlockIndexes;
            $version = $download->file->version;
            $blockSize = $download->file->blockSize();
            $local = $files[$name] ?? null;

            if ($local === null) {
                if ($available !== []) {
                    $files[$name] = [
                        'version' => $version,
                        'blockIndexes' => $available,
                        'updated' => $download->availableUpdated,
                        'created' => $download->created,
                        'blockSize' => $blockSize,
                    ];
                    $updates[] = $this->appendUpdate($name, $version, $available, $blockSize);
                }
                continue;
            }

            if ($local['updated'] === $download->availableUpdated && $local['version']->equal($version)) {
                continue;
            }

            if (!$local['version']->equal($version) || $local['created'] !== $download->created) {
                $updates[] = $this->forgetUpdate($name, $local['version']);
                $updates[] = $this->appendUpdate($name, $version, $available, $blockSize);
                $files[$name] = [
                    'version' => $version,
                    'blockIndexes' => $available,
                    'updated' => $download->availableUpdated,
                    'created' => $download->created,
                    'blockSize' => $blockSize,
                ];
                continue;
            }

            $newBlocks = array_slice($available, count($local['blockIndexes']));
            $local['blockIndexes'] = array_merge($local['blockIndexes'], $newBlocks);
            $local['updated'] = $download->availableUpdated;
            $files[$name] = $local;

            if ($newBlocks !== []) {
                $updates[] = $this->appendUpdate($name, $local['version'], $newBlocks, $blockSize);
            }
        }

        foreach ($files as $name => $state) {
            if (!isset($seen[$name])) {
                $updates[] = $this->forgetUpdate($name, $state['version']);
                unset($files[$name]);
            }
        }

        $this->folders[$folder] = $files;

        return $updates;
    }

    /**
     * @return list<FileDownloadProgressUpdate>
     */
    public function cleanup(string $folder): array
    {
        $files = $this->folders[$folder] ?? null;
        if ($files === null) {
            return [];
        }

        $updates = [];
        foreach ($files as $name => $state) {
            $updates[] = $this->forgetUpdate($name, $state['version']);
        }
        unset($this->folders[$folder]);

        return $updates;
    }

    /**
     * @return list<string>
     */
    public function folders(): array
    {
        $folders = array_keys($this->folders);
        sort($folders, SORT_STRING);

        return $folders;
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

    /**
     * @param list<int> $blockIndexes
     */
    private function appendUpdate(string $name, VersionVector $version, array $blockIndexes, int $blockSize): FileDownloadProgressUpdate
    {
        return new FileDownloadProgressUpdate(
            updateType: FileDownloadProgressUpdate::TYPE_APPEND,
            name: $name,
            version: $version,
            blockIndexes: $blockIndexes,
            blockSize: $blockSize,
        );
    }

    private function forgetUpdate(string $name, VersionVector $version): FileDownloadProgressUpdate
    {
        return new FileDownloadProgressUpdate(
            updateType: FileDownloadProgressUpdate::TYPE_FORGET,
            name: $name,
            version: $version,
        );
    }
}
