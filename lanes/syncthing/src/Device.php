<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class Device
{
    public const COMPRESSION_METADATA = 0;
    public const COMPRESSION_NEVER = 1;
    public const COMPRESSION_ALWAYS = 2;

    /**
     * @param list<string> $addresses
     */
    public function __construct(
        public readonly string $idHex,
        public readonly string $name = '',
        public readonly array $addresses = [],
        public readonly int $compression = self::COMPRESSION_METADATA,
        public readonly string $certName = '',
        public readonly int $maxSequence = 0,
        public readonly bool $introducer = false,
        public readonly int $indexId = 0,
        public readonly bool $skipIntroductionRemovals = false,
        public readonly string $encryptionPasswordTokenHex = '',
    ) {
        $this->assertHexBytes($this->idHex, 'device ID');
        $this->assertHexBytes($this->encryptionPasswordTokenHex, 'encryption password token');
        if (!in_array($this->compression, [
            self::COMPRESSION_METADATA,
            self::COMPRESSION_NEVER,
            self::COMPRESSION_ALWAYS,
        ], true)) {
            throw new \InvalidArgumentException('Unknown device compression mode');
        }
        if ($this->maxSequence < 0 || $this->indexId < 0) {
            throw new \InvalidArgumentException('Device sequence fields must not be negative');
        }
        foreach ($this->addresses as $address) {
            if (!is_string($address)) {
                throw new \InvalidArgumentException('Device addresses must be strings');
            }
        }
    }

    private function assertHexBytes(string $hex, string $label): void
    {
        if ($hex !== '' && !preg_match('/^(?:[0-9a-f]{2})+$/', $hex)) {
            throw new \InvalidArgumentException('Expected lowercase hexadecimal bytes for ' . $label);
        }
    }
}

