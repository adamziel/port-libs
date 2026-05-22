<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ProtocolCapabilities
{
    /**
     * @param list<ProtocolCapability> $capabilities
     */
    private function __construct(
        private readonly array $capabilities,
    ) {
    }

    /**
     * @return array{capabilities:self,delimiterPosition:int}
     */
    public static function fromV1Bytes(string $bytes): array
    {
        $delimiter = strpos($bytes, "\0");
        if ($delimiter === false) {
            throw new \InvalidArgumentException('Capabilities were missing entirely as there was no 0 byte');
        }
        if ($delimiter + 1 === strlen($bytes)) {
            throw new \InvalidArgumentException('There was not a single capability behind the delimiter');
        }

        return [
            'capabilities' => self::fromTokens(preg_split('/ +/', substr($bytes, $delimiter + 1), -1, PREG_SPLIT_NO_EMPTY) ?: []),
            'delimiterPosition' => $delimiter,
        ];
    }

    public static function fromV2Lines(string $lines): self
    {
        $lines = preg_split('/\r?\n/', trim($lines)) ?: [];
        $versionLine = array_shift($lines);
        if ($versionLine === null || $versionLine === '') {
            throw new \InvalidArgumentException('A version line was expected, but none was retrieved');
        }
        if (!str_starts_with($versionLine, 'version ')) {
            throw new \InvalidArgumentException("Expected 'version X', got {$versionLine}");
        }
        if ($versionLine !== 'version 2') {
            throw new \InvalidArgumentException("Got unsupported version {$versionLine}, expected version 2");
        }

        return self::fromTokens(array_values(array_filter($lines, static fn (string $line): bool => $line !== '')));
    }

    /**
     * @return list<ProtocolCapability>
     */
    public function all(): array
    {
        return $this->capabilities;
    }

    public function contains(string $name): bool
    {
        return $this->capability($name) !== null;
    }

    public function capability(string $name): ?ProtocolCapability
    {
        foreach ($this->capabilities as $capability) {
            if ($capability->name === $name) {
                return $capability;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (ProtocolCapability $capability): string => $capability->name, $this->capabilities);
    }

    /**
     * @return list<ProtocolCapability>
     */
    public function symrefs(): array
    {
        return array_values(array_filter(
            $this->capabilities,
            static fn (ProtocolCapability $capability): bool => $capability->name === 'symref'
        ));
    }

    /**
     * @param list<string> $tokens
     */
    private static function fromTokens(array $tokens): self
    {
        $capabilities = [];
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            [$name, $value] = array_pad(explode('=', $token, 2), 2, null);
            $capabilities[] = new ProtocolCapability($name, $value);
        }

        if ($capabilities === []) {
            throw new \InvalidArgumentException('There was not a single capability behind the delimiter');
        }

        return new self($capabilities);
    }
}
