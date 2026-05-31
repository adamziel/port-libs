<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class FetchResponse
{
    private const MAX_PACKET_LINE_LENGTH = 65520;

    /**
     * @param list<FetchAcknowledgement> $acknowledgements
     * @param list<FetchShallowUpdate> $shallowUpdates
     * @param list<FetchWantedRef> $wantedRefs
     * @param list<string> $progressMessages
     * @param list<string> $errorMessages
     */
    public function __construct(
        private readonly array $acknowledgements,
        private readonly array $shallowUpdates,
        private readonly array $wantedRefs,
        private readonly bool $hasPack,
        private readonly string $packData = '',
        private readonly array $progressMessages = [],
        private readonly array $errorMessages = [],
    ) {
    }

    public static function fromV2PacketLines(string $bytes, bool $sidebandAll = false): self
    {
        $offset = 0;
        $acknowledgements = [];
        $shallowUpdates = [];
        $wantedRefs = [];
        $progressMessages = [];
        $errorMessages = [];

        while (true) {
            $packet = self::readV2Packet($bytes, $offset, $sidebandAll, $progressMessages, $errorMessages);
            if ($packet === null) {
                throw new \RuntimeException('fetch response: could not read message headline');
            }
            if ($packet['kind'] === 'flush' || $packet['kind'] === 'response-end') {
                return new self($acknowledgements, $shallowUpdates, $wantedRefs, false, '', $progressMessages, $errorMessages);
            }
            if ($packet['kind'] === 'delimiter') {
                continue;
            }
            if (self::isUploadPackErrorPacket($packet['payload'])) {
                self::throwUploadPackError($packet['payload']);
            }

            $header = rtrim($packet['payload'], "\r\n");
            if ($header === 'acknowledgments') {
                if (self::parseV2Section($bytes, $offset, $acknowledgements, FetchAcknowledgement::fromLine(...), $sidebandAll, $progressMessages, $errorMessages)) {
                    return new self($acknowledgements, $shallowUpdates, $wantedRefs, false, '', $progressMessages, $errorMessages);
                }
                continue;
            }
            if ($header === 'shallow-info') {
                if (self::parseV2Section($bytes, $offset, $shallowUpdates, FetchShallowUpdate::fromLine(...), $sidebandAll, $progressMessages, $errorMessages)) {
                    return new self($acknowledgements, $shallowUpdates, $wantedRefs, false, '', $progressMessages, $errorMessages);
                }
                continue;
            }
            if ($header === 'wanted-refs') {
                if (self::parseV2Section($bytes, $offset, $wantedRefs, FetchWantedRef::fromLine(...), $sidebandAll, $progressMessages, $errorMessages)) {
                    return new self($acknowledgements, $shallowUpdates, $wantedRefs, false, '', $progressMessages, $errorMessages);
                }
                continue;
            }
            if ($header === 'packfile') {
                $sidebands = self::decodeSidebandPacketLines($bytes, $offset, $sidebandAll, $progressMessages, $errorMessages);

                return new self(
                    $acknowledgements,
                    $shallowUpdates,
                    $wantedRefs,
                    true,
                    $sidebands['packData'],
                    $progressMessages,
                    $errorMessages
                );
            }

            throw new \InvalidArgumentException("fetch response: unknown or unsupported section header {$header}");
        }
    }

    /**
     * @param list<string> $features
     */
    public static function checkRequiredFeatures(string $protocolVersion, array $features): void
    {
        if ($protocolVersion === FetchCommand::PROTOCOL_V2) {
            return;
        }
        if ($protocolVersion !== FetchCommand::PROTOCOL_V1) {
            throw new \InvalidArgumentException("fetch response: unsupported protocol version {$protocolVersion}");
        }
        if (!in_array('multi_ack_detailed', $features, true)) {
            throw new \LogicException('fetch response: missing required server capability multi_ack_detailed');
        }
        if (!in_array('side-band', $features, true) && !in_array('side-band-64k', $features, true)) {
            throw new \LogicException('fetch response: missing required server capability side-band OR side-band-64k');
        }
    }

    /**
     * @param null|list<FetchShallowUpdate> $updates
     */
    public function appendV1ShallowUpdates(?array $updates): self
    {
        return new self(
            $this->acknowledgements,
            array_merge($this->shallowUpdates, $updates ?? []),
            $this->wantedRefs,
            $this->hasPack,
            $this->packData,
            $this->progressMessages,
            $this->errorMessages
        );
    }

    /**
     * @return list<FetchAcknowledgement>
     */
    public function acknowledgements(): array
    {
        return $this->acknowledgements;
    }

    /**
     * @return list<FetchShallowUpdate>
     */
    public function shallowUpdates(): array
    {
        return $this->shallowUpdates;
    }

    /**
     * @return list<FetchWantedRef>
     */
    public function wantedRefs(): array
    {
        return $this->wantedRefs;
    }

    public function hasPack(): bool
    {
        return $this->hasPack;
    }

    public function packData(): string
    {
        return $this->packData;
    }

    /**
     * @return list<string>
     */
    public function progressMessages(): array
    {
        return $this->progressMessages;
    }

    /**
     * @return list<RemoteProgress>
     */
    public function remoteProgress(): array
    {
        $progress = [];
        foreach ($this->progressMessages as $message) {
            $parsed = RemoteProgress::fromText($message);
            if ($parsed !== null) {
                $progress[] = $parsed;
            }
        }

        return $progress;
    }

    /**
     * @return list<string>
     */
    public function errorMessages(): array
    {
        return $this->errorMessages;
    }

    /**
     * @param list<mixed> $out
     * @param callable(string):mixed $parse
     */
    private static function parseV2Section(
        string $bytes,
        int &$offset,
        array &$out,
        callable $parse,
        bool $sidebandAll,
        array &$progressMessages,
        array &$errorMessages
    ): bool {
        while (true) {
            $packet = self::readV2Packet($bytes, $offset, $sidebandAll, $progressMessages, $errorMessages);
            if ($packet === null || $packet['kind'] === 'flush' || $packet['kind'] === 'response-end') {
                return true;
            }
            if ($packet['kind'] === 'delimiter') {
                return false;
            }
            if (self::isUploadPackErrorPacket($packet['payload'])) {
                self::throwUploadPackError($packet['payload']);
            }
            $out[] = $parse($packet['payload']);
        }
    }

    /**
     * @return array{packData:string,progressMessages:list<string>,errorMessages:list<string>}
     */
    private static function decodeSidebandPacketLines(
        string $bytes,
        int &$offset,
        bool $sidebandAll,
        array &$progressMessages,
        array &$errorMessages
    ): array {
        $packData = '';

        while (true) {
            $packet = self::readV2Packet($bytes, $offset, $sidebandAll, $progressMessages, $errorMessages);
            if ($packet === null || $packet['kind'] === 'flush' || $packet['kind'] === 'response-end') {
                break;
            }
            if ($packet['kind'] === 'delimiter') {
                break;
            }
            if (!$sidebandAll && self::isUploadPackErrorPacket($packet['payload'])) {
                self::throwUploadPackError($packet['payload']);
            }
            if ($sidebandAll) {
                $packData .= $packet['payload'];
                continue;
            }

            $sideband = self::decodeSidebandPayload($packet['payload']);
            if ($sideband['band'] === 1) {
                $packData .= $sideband['data'];
            } elseif ($sideband['band'] === 2) {
                $progressMessages[] = self::trimOneTrailingNewline($sideband['data']);
            } elseif ($sideband['data'] !== '') {
                $errorMessages[] = self::trimOneTrailingNewline($sideband['data']);
            }
        }

        return [
            'packData' => $packData,
            'progressMessages' => $progressMessages,
            'errorMessages' => $errorMessages,
        ];
    }

    /**
     * @param list<string> $progressMessages
     * @param list<string> $errorMessages
     * @return null|array{kind:string,payload:string}
     */
    private static function readV2Packet(
        string $bytes,
        int &$offset,
        bool $sidebandAll,
        array &$progressMessages,
        array &$errorMessages
    ): ?array {
        while (true) {
            $packet = self::readPacket($bytes, $offset);
            if (!$sidebandAll || $packet === null || $packet['kind'] !== 'data') {
                return $packet;
            }
            if (self::isUploadPackErrorPacket($packet['payload'])) {
                self::throwUploadPackError($packet['payload']);
            }

            $sideband = self::decodeSidebandPayload($packet['payload']);
            if ($sideband['band'] === 1) {
                if ($sideband['data'] === '') {
                    continue;
                }

                return ['kind' => 'data', 'payload' => $sideband['data']];
            }

            if ($sideband['band'] === 2) {
                $progressMessages[] = self::trimOneTrailingNewline($sideband['data']);
                continue;
            }

            if ($sideband['data'] !== '') {
                $errorMessages[] = self::trimOneTrailingNewline($sideband['data']);
            }
        }
    }

    /**
     * @return array{band:int,data:string}
     */
    private static function decodeSidebandPayload(string $payload): array
    {
        if ($payload === '') {
            throw new \InvalidArgumentException('fetch response: sideband packet was empty');
        }

        $band = ord($payload[0]);
        if ($band < 1 || $band > 3) {
            throw new \InvalidArgumentException("fetch response: invalid sideband {$band}");
        }

        return ['band' => $band, 'data' => substr($payload, 1)];
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
            throw new \InvalidArgumentException('fetch response: truncated packet line length');
        }

        $header = substr($bytes, $offset, 4);
        if (preg_match('/^[0-9a-fA-F]{4}$/', $header) !== 1) {
            throw new \InvalidArgumentException("fetch response: invalid packet line length {$header}");
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
        if ($length === 4) {
            throw new \InvalidArgumentException('fetch response: empty packet line');
        }
        if ($length < 4) {
            throw new \InvalidArgumentException("fetch response: invalid packet line length {$header}");
        }
        if ($length > self::MAX_PACKET_LINE_LENGTH) {
            throw new \InvalidArgumentException("fetch response: packet line exceeds maximum length {$header}");
        }

        $payloadLength = $length - 4;
        if ($offset + $payloadLength > strlen($bytes)) {
            throw new \InvalidArgumentException('fetch response: truncated packet line payload');
        }

        $payload = substr($bytes, $offset, $payloadLength);
        $offset += $payloadLength;

        return ['kind' => 'data', 'payload' => $payload];
    }

    private static function isUploadPackErrorPacket(string $payload): bool
    {
        return str_starts_with($payload, 'ERR ');
    }

    private static function throwUploadPackError(string $payload): never
    {
        throw new \RuntimeException('fetch response: upload-pack error ' . substr($payload, 4));
    }

    private static function trimOneTrailingNewline(string $data): string
    {
        return str_ends_with($data, "\n") ? substr($data, 0, -1) : $data;
    }
}
