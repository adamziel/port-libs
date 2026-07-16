<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class HashSet
{
    /**
     * @var array<string, true>
     */
    private array $types = [];

    public function __construct(string ...$types)
    {
        $this->add(...$types);
    }

    public static function supported(): self
    {
        return new self(...HashType::supported());
    }

    public function add(string ...$types): self
    {
        foreach ($types as $type) {
            $normalized = HashType::fromString($type);
            if ($normalized !== HashType::NONE) {
                $this->types[$normalized] = true;
            }
        }

        return $this;
    }

    public function contains(string $type): bool
    {
        return isset($this->types[HashType::fromString($type)]);
    }

    public function overlap(self $other): self
    {
        $overlap = new self();
        foreach ($this->toArray() as $type) {
            if ($other->contains($type)) {
                $overlap->add($type);
            }
        }

        return $overlap;
    }

    public function subsetOf(self $other): bool
    {
        foreach ($this->toArray() as $type) {
            if (!$other->contains($type)) {
                return false;
            }
        }

        return true;
    }

    public function getOne(): string
    {
        foreach (HashType::supported() as $type) {
            if (isset($this->types[$type])) {
                return $type;
            }
        }

        return HashType::NONE;
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        $types = [];
        foreach (HashType::supported() as $type) {
            if (isset($this->types[$type])) {
                $types[] = $type;
            }
        }

        return $types;
    }

    public function count(): int
    {
        return count($this->types);
    }

    public function __toString(): string
    {
        return '[' . implode(', ', $this->toArray()) . ']';
    }
}
