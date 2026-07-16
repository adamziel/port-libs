<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ExternalMergeDriverCommand
{
    /**
     * @param list<string> $temporaryPaths
     */
    public function __construct(
        public readonly string $command,
        public readonly string $ancestorPath,
        public readonly string $currentPath,
        public readonly string $otherPath,
        private readonly array $temporaryPaths,
    ) {
    }

    /**
     * @param callable(self): int $runner
     */
    public function run(callable $runner): BlobMergeResult
    {
        $exitCode = $runner($this);

        return $this->readResultFromExitCode($exitCode);
    }

    public function readResultFromExitCode(int $exitCode): BlobMergeResult
    {
        if ($exitCode !== 0) {
            throw new \RuntimeException("External merge driver failed with non-zero exit status {$exitCode}: {$this->command}");
        }

        $contents = file_get_contents($this->currentPath);
        if ($contents === false) {
            throw new \RuntimeException('IO failed when dealing with merge-driver output');
        }

        return new BlobMergeResult($contents, BlobMergeResult::RESOLUTION_COMPLETE, 0);
    }

    public function cleanup(): void
    {
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function __destruct()
    {
        $this->cleanup();
    }

}
