<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class PullScanScheduleResult
{
    /**
     * @param list<string> $paths
     * @param list<array{path:string, type:string, sources:list<string>}> $items
     */
    public function __construct(
        public readonly bool $scheduled,
        public readonly array $paths = [],
        public readonly array $items = [],
        public readonly ?string $error = null,
        public readonly bool $alreadyClosed = false,
    ) {
        foreach ($this->paths as $path) {
            if (!is_string($path) || $path === '') {
                throw new \InvalidArgumentException('Scan paths must be non-empty strings');
            }
        }
        foreach ($this->items as $item) {
            if (
                !isset($item['path'], $item['type'], $item['sources'])
                || !is_string($item['path'])
                || !is_string($item['type'])
                || !is_array($item['sources'])
            ) {
                throw new \InvalidArgumentException('Scan items must include path, type, and sources');
            }
        }
        if ($this->error === '') {
            throw new \InvalidArgumentException('Scan scheduling error must be null or non-empty');
        }
    }

    /**
     * @return array{scheduled:bool, paths:list<string>, items:list<array{path:string, type:string, sources:list<string>}>, error:?string, alreadyClosed:bool}
     */
    public function toArray(): array
    {
        return [
            'scheduled' => $this->scheduled,
            'paths' => $this->paths,
            'items' => $this->items,
            'error' => $this->error,
            'alreadyClosed' => $this->alreadyClosed,
        ];
    }
}
