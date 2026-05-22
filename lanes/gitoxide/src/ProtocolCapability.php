<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ProtocolCapability
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $value = null,
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('Protocol capability name cannot be empty');
        }
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        if ($this->value === null || $this->value === '') {
            return [];
        }

        return preg_split('/ +/', $this->value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public function supports(string $value): bool
    {
        return in_array($value, $this->values(), true);
    }
}
