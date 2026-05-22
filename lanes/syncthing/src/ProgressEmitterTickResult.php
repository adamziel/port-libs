<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class ProgressEmitterTickResult
{
    /**
     * @param array<string, array<string, PullerProgress>> $event
     * @param list<ProgressUpdateBatch> $batches
     * @param list<ProgressUpdateBatch> $sent
     * @param list<ProgressSendFailure> $failures
     */
    public function __construct(
        public readonly bool $changed,
        public readonly array $event,
        public readonly array $batches,
        public readonly array $sent,
        public readonly array $failures,
        public readonly ?int $nextIntervalSeconds,
    ) {
        foreach ($this->batches as $batch) {
            if (!$batch instanceof ProgressUpdateBatch) {
                throw new \InvalidArgumentException('Expected only ProgressUpdateBatch instances');
            }
        }
        foreach ($this->sent as $batch) {
            if (!$batch instanceof ProgressUpdateBatch) {
                throw new \InvalidArgumentException('Expected only ProgressUpdateBatch instances');
            }
        }
        foreach ($this->failures as $failure) {
            if (!$failure instanceof ProgressSendFailure) {
                throw new \InvalidArgumentException('Expected only ProgressSendFailure instances');
            }
        }
    }

    public function sentCount(): int
    {
        return count($this->sent);
    }

    public function failed(): bool
    {
        return $this->failures !== [];
    }
}
