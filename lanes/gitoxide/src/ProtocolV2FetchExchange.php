<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ProtocolV2FetchExchange
{
    private const MAX_PACKET_LINE_LENGTH = 65520;

    /**
     * @param list<RemoteRef> $remoteRefs
     */
    private function __construct(
        private readonly ProtocolCapabilities $capabilities,
        private readonly array $remoteRefs,
        private readonly FetchResponse $fetchResponse,
        private readonly string $capabilityAdvertisementBytes,
        private readonly string $lsRefsAdvertisementBytes,
        private readonly string $fetchResponseBytes,
    ) {
    }

    /**
     * @param null|bool $sidebandAll Pass null to infer Gitoxide's default sideband-all fetch behavior from capabilities.
     * @param null|callable(bool, string):bool $progressHandler Receives sideband error/progress text. Return false to abort.
     */
    public static function fromPacketLines(
        string $bytes,
        ?bool $sidebandAll = null,
        ?string $expectedService = null,
        ?callable $progressHandler = null
    ): self {
        $messages = self::splitMessages($bytes);
        if ($messages === []) {
            throw new \RuntimeException('protocol v2 fetch exchange: expected capability advertisement and fetch response messages');
        }

        $capabilityBytes = $messages[0];
        $next = 1;
        if (str_starts_with(self::firstPayload($capabilityBytes), '# service=')) {
            if (!isset($messages[1])) {
                throw new \RuntimeException('protocol v2 fetch exchange: service announcement missing capability advertisement');
            }
            $capabilityBytes .= $messages[1];
            $next = 2;
        }

        if (!isset($messages[$next])) {
            throw new \RuntimeException('protocol v2 fetch exchange: expected fetch response message');
        }

        $capabilities = ProtocolCapabilities::fromV2PacketLines($capabilityBytes, $expectedService);
        $effectiveSidebandAll = $sidebandAll ?? self::fetchAdvertisesSidebandAll($capabilities);
        if (!isset($messages[$next + 1])) {
            return new self(
                $capabilities,
                [],
                FetchResponse::fromV2PacketLines($messages[$next], $effectiveSidebandAll, $progressHandler),
                $capabilityBytes,
                '',
                $messages[$next],
            );
        }
        if (count($messages) !== $next + 2) {
            throw new \RuntimeException('protocol v2 fetch exchange: unexpected trailing packet-line messages');
        }

        return new self(
            $capabilities,
            LsRefsCommand::parseV2PacketLines($messages[$next]),
            FetchResponse::fromV2PacketLines($messages[$next + 1], $effectiveSidebandAll, $progressHandler),
            $capabilityBytes,
            $messages[$next],
            $messages[$next + 1],
        );
    }

    public function capabilities(): ProtocolCapabilities
    {
        return $this->capabilities;
    }

    /**
     * @return list<RemoteRef>
     */
    public function remoteRefs(): array
    {
        return $this->remoteRefs;
    }

    public function fetchResponse(): FetchResponse
    {
        return $this->fetchResponse;
    }

    public function capabilityAdvertisementBytes(): string
    {
        return $this->capabilityAdvertisementBytes;
    }

    public function lsRefsAdvertisementBytes(): string
    {
        return $this->lsRefsAdvertisementBytes;
    }

    public function fetchResponseBytes(): string
    {
        return $this->fetchResponseBytes;
    }

    /**
     * @return list<string>
     */
    private static function splitMessages(string $bytes): array
    {
        $offset = 0;
        $current = '';
        $messages = [];
        $lengthBytes = strlen($bytes);

        while ($offset < $lengthBytes) {
            if ($current === '' && strspn($bytes, "\r\n", $offset) === $lengthBytes - $offset) {
                break;
            }
            if ($offset + 4 > $lengthBytes) {
                throw new \InvalidArgumentException('protocol v2 fetch exchange: truncated packet line length');
            }

            $header = substr($bytes, $offset, 4);
            if (preg_match('/^[0-9a-fA-F]{4}$/', $header) !== 1) {
                throw new \InvalidArgumentException("protocol v2 fetch exchange: invalid packet line length {$header}");
            }
            $offset += 4;
            $current .= $header;

            $length = hexdec($header);
            if ($length === 0 || $length === 2) {
                $messages[] = $current;
                $current = '';
                continue;
            }
            if ($length === 1) {
                continue;
            }
            if ($length < 4) {
                throw new \InvalidArgumentException("protocol v2 fetch exchange: invalid packet line length {$header}");
            }
            if ($length > self::MAX_PACKET_LINE_LENGTH) {
                throw new \InvalidArgumentException("protocol v2 fetch exchange: packet line exceeds maximum length {$header}");
            }

            $payloadLength = $length - 4;
            if ($offset + $payloadLength > $lengthBytes) {
                throw new \InvalidArgumentException('protocol v2 fetch exchange: truncated packet line payload');
            }

            $current .= substr($bytes, $offset, $payloadLength);
            $offset += $payloadLength;
        }

        if ($current !== '') {
            throw new \RuntimeException('protocol v2 fetch exchange: missing flush packet');
        }

        return $messages;
    }

    private static function firstPayload(string $message): string
    {
        if (strlen($message) < 4) {
            return '';
        }

        $length = hexdec(substr($message, 0, 4));
        if ($length <= 4 || strlen($message) < $length) {
            return '';
        }

        return substr($message, 4, $length - 4);
    }

    private static function fetchAdvertisesSidebandAll(ProtocolCapabilities $capabilities): bool
    {
        return in_array('sideband-all', $capabilities->capability('fetch')?->values() ?? [], true);
    }
}
