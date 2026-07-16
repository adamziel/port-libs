<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class PullFinisherResult
{
    /**
     * @param array{folder:string, item:string, error:?string, type:string, action:string}|null $itemFinishedEvent
     */
    public function __construct(
        public readonly bool $handled,
        public readonly PullFinalizationResult $finalization,
        public readonly ?array $itemFinishedEvent = null,
        public readonly ?string $pullError = null,
    ) {
        if ($this->pullError === '') {
            throw new \InvalidArgumentException('Pull finisher error must be null or non-empty');
        }
    }
}
