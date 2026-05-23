<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class BlockPullResult
{
    /**
     * @param list<BlockRequestPlan> $attempts
     * @param list<string> $errors
     */
    public function __construct(
        public readonly Block $block,
        public readonly string $data = '',
        public readonly ?BlockRequestPlan $plan = null,
        public readonly ?string $error = null,
        public readonly array $attempts = [],
        public readonly array $errors = [],
        public readonly bool $zeroBlock = false,
    ) {
        foreach ($this->attempts as $attempt) {
            if (!$attempt instanceof BlockRequestPlan) {
                throw new \InvalidArgumentException('Expected only BlockRequestPlan attempts');
            }
        }
        foreach ($this->errors as $error) {
            if (!is_string($error) || $error === '') {
                throw new \InvalidArgumentException('Pull attempt errors must be non-empty strings');
            }
        }
        if ($this->error !== null && $this->error === '') {
            throw new \InvalidArgumentException('Pull error must be non-empty when present');
        }
    }

    public function successful(): bool
    {
        return $this->error === null;
    }

    /**
     * @return list<string>
     */
    public function attemptedDeviceIds(): array
    {
        return array_map(
            static fn (BlockRequestPlan $attempt): string => $attempt->deviceId,
            $this->attempts,
        );
    }

    /**
     * @return array{successful:bool, device:?string, error:?string, dataBytes:int, attempts:list<string>, zeroBlock:bool}
     */
    public function toArray(): array
    {
        return [
            'successful' => $this->successful(),
            'device' => $this->plan?->deviceId,
            'error' => $this->error,
            'dataBytes' => strlen($this->data),
            'attempts' => $this->attemptedDeviceIds(),
            'zeroBlock' => $this->zeroBlock,
        ];
    }
}
