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
