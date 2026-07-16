<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ReceivePackAdvertisement
{
    private const MAX_PACKET_LINE_LENGTH = 65520;

    /**
     * @param list<RemoteRef> $refs
     * @param list<FetchShallowUpdate> $shallowUpdates
     */
    public function __construct(
        private readonly ProtocolCapabilities $capabilities,
        private readonly array $refs,
        private readonly string $objectFormat = 'sha1',
        private readonly array $shallowUpdates = [],
    ) {
        self::assertObjectFormat($objectFormat);
        foreach ($refs as $ref) {
            if (!$ref instanceof RemoteRef) {
                throw new \InvalidArgumentException('Receive-pack advertisement refs must be RemoteRef instances');
            }
            ReferenceName::assertValid($ref->name);
            if ($ref->object === null) {
                throw new \InvalidArgumentException('Receive-pack advertised refs must point to objects');
            }
            if (preg_match(self::objectIdPattern($objectFormat), $ref->object) !== 1) {
                throw new \InvalidArgumentException("Receive-pack advertised refs must use {$objectFormat} object ids");
            }
        }
        foreach ($shallowUpdates as $update) {
            if (!$update instanceof FetchShallowUpdate) {
                throw new \InvalidArgumentException('Receive-pack advertisement shallow updates must be FetchShallowUpdate instances');
            }
            if ($update->kind !== FetchShallowUpdate::SHALLOW) {
                throw new \InvalidArgumentException('Receive-pack advertisement only supports shallow boundary updates');
            }
            if (preg_match(self::objectIdPattern($objectFormat), $update->object) !== 1) {
                throw new \InvalidArgumentException("Receive-pack shallow updates must use {$objectFormat} object ids");
            }
        }
    }

    public static function fromV1PacketLines(string $bytes): self
    {
        $offset = 0;
        $refs = [];
        $shallowUpdates = [];
        $capabilities = null;
        $objectFormat = 'sha1';
        $symbolicTargets = [];

        while (true) {
            $packet = self::readPacket($bytes, $offset);
            if ($packet === null || $packet['kind'] === 'flush') {
                break;
            }
            if ($packet['kind'] !== 'data') {
                throw new \InvalidArgumentException("receive-pack advertisement: unexpected {$packet['kind']} packet");
            }
            if (str_starts_with($packet['payload'], 'ERR ')) {
                throw new \RuntimeException('receive-pack advertisement: receive-pack error ' . self::trimLineEnding(substr($packet['payload'], 4)));
            }

            $payload = self::trimLineEnding($packet['payload']);
            if ($capabilities === null) {
                $parsed = ProtocolCapabilities::fromV1Bytes($payload);
                $capabilities = $parsed['capabilities'];
                $objectFormat = self::objectFormatFromCapabilities($capabilities);
                $symbolicTargets = self::symbolicTargetsFromCapabilities($capabilities);
                $payload = substr($payload, 0, $parsed['delimiterPosition']);
            } elseif (str_contains($payload, "\0")) {
                throw new \InvalidArgumentException('receive-pack advertisement: capabilities appeared after the first ref');
            }

            if ($payload === '') {
                continue;
            }

            self::parseRefLine($payload, $objectFormat, $symbolicTargets, $refs, $shallowUpdates);
        }

        if ($capabilities === null) {
            throw new \InvalidArgumentException('receive-pack advertisement: capabilities were missing');
        }

        return new self($capabilities, $refs, $objectFormat, $shallowUpdates);
    }

    public function capabilities(): ProtocolCapabilities
    {
        return $this->capabilities;
    }

    public function objectFormat(): string
    {
        return $this->objectFormat;
    }

    /**
     * @return list<RemoteRef>
     */
    public function refs(): array
    {
        return $this->refs;
    }

    /**
     * @return list<FetchShallowUpdate>
     */
    public function shallowUpdates(): array
    {
        return $this->shallowUpdates;
    }

    public function ref(string $name): ?RemoteRef
    {
        foreach ($this->refs as $ref) {
            if ($ref->name === $name) {
                return $ref;
            }
        }

        return null;
    }

    public function objectFor(string $name): ?string
    {
        return $this->ref($name)?->object;
    }

    /**
     * @param array<string, string|null> $symbolicTargets
     * @param list<RemoteRef> $refs
     * @param list<FetchShallowUpdate> $shallowUpdates
     */
    private static function parseRefLine(
        string $line,
        string $objectFormat,
        array &$symbolicTargets,
        array &$refs,
        array &$shallowUpdates,
    ): void
    {
        $line = self::trimLineEnding($line);
        $parts = explode(' ', $line, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('receive-pack advertisement: ref line must contain object id and ref name');
        }
        [$object, $refName] = $parts;
        if ($object === 'shallow') {
            if (preg_match(self::objectIdPattern($objectFormat), $refName) !== 1) {
                throw new \InvalidArgumentException("receive-pack advertisement: shallow object must be a {$objectFormat} object id");
            }
            $shallowUpdates[] = FetchShallowUpdate::shallow($refName);

            return;
        }
        if (preg_match(self::objectIdPattern($objectFormat), $object) !== 1) {
            throw new \InvalidArgumentException("receive-pack advertisement: ref object must be a {$objectFormat} object id");
        }

        if (str_ends_with($refName, '^{}')) {
            $baseRefName = substr($refName, 0, -3);
            if (self::isZeroObject($object) && $baseRefName === 'capabilities') {
                return;
            }

            ReferenceName::assertValid($baseRefName);
            $previous = array_pop($refs);
            if ($previous === null || $previous->kind !== 'direct' || $previous->name !== $baseRefName || $previous->object === null) {
                throw new \InvalidArgumentException('receive-pack advertisement: peeled refs must follow their direct ref');
            }
            $refs[] = RemoteRef::peeled($baseRefName, $previous->object, $object);

            return;
        }

        ReferenceName::assertValid($refName);

        if (array_key_exists($refName, $symbolicTargets)) {
            $target = $symbolicTargets[$refName];
            unset($symbolicTargets[$refName]);
            if ($target !== null) {
                ReferenceName::assertValid($target);
                $refs[] = RemoteRef::symbolic($refName, $target, $object);

                return;
            }
        }

        $refs[] = RemoteRef::direct($refName, $object);
    }

    /**
     * @return array<string, string|null>
     */
    private static function symbolicTargetsFromCapabilities(ProtocolCapabilities $capabilities): array
    {
        $targets = [];
        foreach ($capabilities->symrefs() as $capability) {
            $value = $capability->value;
            if ($value === null || !str_contains($value, ':')) {
                throw new \InvalidArgumentException('receive-pack advertisement: malformed symref capability');
            }
            [$name, $target] = explode(':', $value, 2);
            if ($name === '' || $target === '') {
                throw new \InvalidArgumentException('receive-pack advertisement: malformed symref capability');
            }
            ReferenceName::assertValid($name);
            if ($target !== '(null)') {
                ReferenceName::assertValid($target);
            }

            $targets[$name] = $target === '(null)' ? null : $target;
        }

        return $targets;
    }

    private static function isZeroObject(string $object): bool
    {
        return strspn($object, '0') === strlen($object);
    }

    private static function objectFormatFromCapabilities(ProtocolCapabilities $capabilities): string
    {
        $capability = $capabilities->capability('object-format');
        if ($capability === null) {
            return 'sha1';
        }
        if ($capability->supports('sha1')) {
            return 'sha1';
        }
        if ($capability->supports('sha256')) {
            return 'sha256';
        }

        throw new \InvalidArgumentException('receive-pack advertisement: unsupported object-format capability');
    }

    private static function objectIdPattern(string $objectFormat): string
    {
        self::assertObjectFormat($objectFormat);

        return $objectFormat === 'sha256'
            ? '/^[0-9a-fA-F]{64}$/'
            : '/^[0-9a-fA-F]{40}$/';
    }

    private static function assertObjectFormat(string $objectFormat): void
    {
        if (!in_array($objectFormat, ['sha1', 'sha256'], true)) {
            throw new \InvalidArgumentException("receive-pack advertisement: unsupported object format {$objectFormat}");
        }
    }

    /**
     * @return null|array{kind:string,payload:string}
     */
    private static function readPacket(string $bytes, int &$offset): ?array
    {
        if ($offset === strlen($bytes)) {
            return null;
        }
        if ($offset + 4 > strlen($bytes)) {
            throw new \InvalidArgumentException('receive-pack advertisement: truncated packet line length');
        }

        $header = substr($bytes, $offset, 4);
        if (preg_match('/^[0-9a-fA-F]{4}$/', $header) !== 1) {
            throw new \InvalidArgumentException("receive-pack advertisement: invalid packet line length {$header}");
        }
        $offset += 4;

        $length = hexdec($header);
        if ($length === 0) {
            return ['kind' => 'flush', 'payload' => ''];
        }
        if ($length === 1) {
            return ['kind' => 'delimiter', 'payload' => ''];
        }
        if ($length === 2) {
            return ['kind' => 'response-end', 'payload' => ''];
        }
        if ($length < 4) {
            throw new \InvalidArgumentException("receive-pack advertisement: invalid packet line length {$header}");
        }
        if ($length > self::MAX_PACKET_LINE_LENGTH) {
            throw new \InvalidArgumentException("receive-pack advertisement: packet line exceeds maximum length {$header}");
        }

        $payloadLength = $length - 4;
        if ($offset + $payloadLength > strlen($bytes)) {
            throw new \InvalidArgumentException('receive-pack advertisement: truncated packet line payload');
        }

        $payload = substr($bytes, $offset, $payloadLength);
        $offset += $payloadLength;

        return ['kind' => 'data', 'payload' => $payload];
    }

    private static function trimLineEnding(string $line): string
    {
        return rtrim($line, "\r\n");
    }
}
