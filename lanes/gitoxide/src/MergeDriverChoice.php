<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class MergeDriverChoice
{
    public const BUILTIN = 'builtin';
    public const EXTERNAL = 'external';

    public function __construct(
        public readonly string $kind,
        public readonly string $name,
        public readonly ?string $builtin,
        public readonly ?ExternalMergeDriver $driver,
        public readonly ?string $resolveBinaryWith = null,
    ) {
    }

    public static function builtin(string $driver, ?string $resolveBinaryWith = null): self
    {
        return new self(self::BUILTIN, $driver, $driver, null, $resolveBinaryWith);
    }

    public static function external(ExternalMergeDriver $driver, ?string $resolveBinaryWith = null): self
    {
        return new self(self::EXTERNAL, $driver->name, null, $driver, $resolveBinaryWith);
    }
}
