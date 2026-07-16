<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class FetchAcknowledgement
{
    public const COMMON = 'common';
    public const READY = 'ready';
    public const NAK = 'nak';

    private function __construct(
        public readonly string $kind,
        public readonly ?string $object = null,
    ) {
    }

    public static function common(string $objectId): self
    {
        return new self(self::COMMON, self::normalizeObjectId($objectId));
    }

    public static function ready(): self
    {
        return new self(self::READY);
    }

    public static function nak(): self
    {
        return new self(self::NAK);
    }

    public static function fromLine(string $line): self
    {
        $line = ProtocolLine::trimEnd($line);
        if ($line === 'ready') {
            return self::ready();
        }
        if ($line === 'NAK') {
            return self::nak();
        }

        $tokens = explode(' ', $line, 3);
        if (($tokens[0] ?? null) !== 'ACK' || !isset($tokens[1])) {
            throw new \InvalidArgumentException("fetch response: unknown line prefix in {$line}");
        }

        $object = self::normalizeObjectId($tokens[1]);
        if (!isset($tokens[2])) {
            return self::common($object);
        }

        return match ($tokens[2]) {
            'common' => self::common($object),
            'ready' => self::ready(),
            default => throw new \InvalidArgumentException("fetch response: unknown line prefix in {$line}"),
        };
    }

    public function id(): ?string
    {
        return $this->kind === self::COMMON ? $this->object : null;
    }

    private static function normalizeObjectId(string $objectId): string
    {
        if (preg_match('/^(?:[0-9a-fA-F]{40}|[0-9a-fA-F]{64})$/', $objectId) !== 1) {
            throw new \InvalidArgumentException("fetch response: unknown line prefix in ACK {$objectId}");
        }

        return strtolower($objectId);
    }
}
