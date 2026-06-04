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

        self::assertRelationshipId($id);

        if ($targetMode !== self::TARGET_MODE_INTERNAL && $targetMode !== self::TARGET_MODE_EXTERNAL) {
            throw new \InvalidArgumentException('Unsupported OPC relationship TargetMode: ' . $targetMode);
        }
    }

    public function isExternal(): bool
    {
        return $this->targetMode === self::TARGET_MODE_EXTERNAL;
    }

    private static function assertRelationshipId(string $id): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/D', $id) !== 1) {
            throw new \InvalidArgumentException('OPC relationship Id must be an XML NCName-style identifier');
        }
    }
}
