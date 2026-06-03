<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcRelationship
{
    public const TARGET_MODE_INTERNAL = 'Internal';
    public const TARGET_MODE_EXTERNAL = 'External';

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $target,
        public readonly string $targetMode = self::TARGET_MODE_INTERNAL,
    ) {
        if ($id === '' || $type === '' || $target === '') {
            throw new \InvalidArgumentException('OPC relationship Id, Type, and Target must be non-empty');
        }

        if ($targetMode !== self::TARGET_MODE_INTERNAL && $targetMode !== self::TARGET_MODE_EXTERNAL) {
            throw new \InvalidArgumentException('Unsupported OPC relationship TargetMode: ' . $targetMode);
        }
    }

    public function isExternal(): bool
    {
        return $this->targetMode === self::TARGET_MODE_EXTERNAL;
    }
}
