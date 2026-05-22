<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class TrackRenamesStrategy
{
    private const HASH = 1;
    private const MODTIME = 2;
    private const LEAF = 4;

    private function __construct(private readonly int $flags)
    {
    }

    public static function parse(string $strategies): self
    {
        if ($strategies === '') {
            return new self(0);
        }

        $flags = 0;
        foreach (explode(',', $strategies) as $strategy) {
            switch ($strategy) {
                case 'hash':
                    $flags |= self::HASH;
                    break;
                case 'modtime':
                    $flags |= self::MODTIME;
                    break;
                case 'leaf':
                    $flags |= self::LEAF;
                    break;
                case 'size':
                    break;
                default:
                    throw new \InvalidArgumentException('unknown track renames strategy "' . $strategy . '"');
            }
        }

        return new self($flags);
    }

    public function flags(): int
    {
        return $this->flags;
    }

    public function usesHash(): bool
    {
        return ($this->flags & self::HASH) !== 0;
    }

    public function usesModTime(): bool
    {
        return ($this->flags & self::MODTIME) !== 0;
    }

    public function usesLeaf(): bool
    {
        return ($this->flags & self::LEAF) !== 0;
    }
}
