<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PushResponse
{
    private const MAX_PACKET_LINE_LENGTH = 65520;

    /**
     * @param list<PushRefStatus> $refStatuses
     * @param list<string> $progressMessages
     * @param list<string> $errorMessages
     */
    public function __construct(
        private readonly string $unpackStatus,
        private readonly array $refStatuses,
        private readonly array $progressMessages = [],
        private readonly array $errorMessages = [],
    ) {
    }

    public static function fromReportStatusPacketLines(string $bytes, string $objectFormat = 'any'): self
    {
        return self::parseReportStatus($bytes, [], [], $objectFormat, null);
    }

    /**
     * @param list<string> $expectedRefNames
     */
    public static function fromReportStatusPacketLinesForExpectedRefNames(string $bytes, array $expectedRefNames, string $objectFormat = 'any'): self
    {
        return self::parseReportStatus($bytes, [], [], $objectFormat, self::expectedRefLookup($expectedRefNames));
    }

    public static function fromSidebandPacketLines(string $bytes, string $objectFormat = 'any'): self
    {
        return self::parseSidebandPacketLines($bytes, $objectFormat, null);
    }

    /**
     * @param list<string> $expectedRefNames
     */
    public static function fromSidebandPacketLinesForExpectedRefNames(string $bytes, array $expectedRefNames, string $objectFormat = 'any'): self
    {
        return self::parseSidebandPacketLines($bytes, $objectFormat, self::expectedRefLookup($expectedRefNames));
    }

    /**
     * @param array<string, true>|null $expectedRefLookup
     */
    private static function parseSidebandPacketLines(string $bytes, string $objectFormat, ?array $expectedRefLookup): self
    {
        $offset = 0;
        $statusBytes = '';
        $progressMessages = [];
        $errorMessages = [];
        $sawFlush = false;

        while (true) {
            $packet = self::readPacket($bytes, $offset);
            if ($packet === null) {
                break;
            }
            if ($packet['kind'] === 'flush' || $packet['kind'] === 'delimiter' || $packet['kind'] === 'response-end') {
                $sawFlush = true;
                break;
            }
            if ($packet['kind'] !== 'data') {
                throw new \InvalidArgumentException("push response: unexpected {$packet['kind']} packet in sideband stream");
            }
            if (str_starts_with($packet['payload'], 'ERR ')) {
                throw new \RuntimeException('push response: receive-pack error ' . self::trimLineEnding(substr($packet['payload'], 4)));
            }
            if ($packet['payload'] === '') {
                throw new \InvalidArgumentException('push response: sideband packet was empty');
            }

            $band = ord($packet['payload'][0]);
            $data = substr($packet['payload'], 1);
            if ($band === 1) {
                $statusBytes .= $data;
            } elseif ($band === 2) {
                if ($data !== '') {
                    $progressMessages[] = self::trimOneTrailingNewline($data);
                }
            } elseif ($band === 3) {
                if ($data !== '') {
                    $errorMessages[] = self::trimOneTrailingNewline($data);
                }
            } else {
                throw new \InvalidArgumentException("push response: invalid sideband {$band}");
            }
        }

        if (!$sawFlush) {
            throw new \InvalidArgumentException('push response: missing sideband flush packet');
        }
        if ($errorMessages !== []) {
            throw new \RuntimeException('push response: sideband error ' . implode("\n", $errorMessages));
        }

        return self::parseReportStatus($statusBytes, $progressMessages, $errorMessages, $objectFormat, $expectedRefLookup);
    }

    public function unpackStatus(): string
    {
        return $this->unpackStatus;
    }

    public function unpackOk(): bool
    {
        return $this->unpackStatus === 'ok';
    }

    public function unpackError(): ?string
    {
        return $this->unpackOk() ? null : $this->unpackStatus;
    }

    /**
     * @return list<PushRefStatus>
     */
    public function refStatuses(): array
    {
        return $this->refStatuses;
    }

    /**
     * @return list<PushRefStatus>
     */
    public function rejectedRefs(): array
    {
        return array_values(array_filter(
            $this->refStatuses,
            static fn (PushRefStatus $status): bool => $status->isRejected()
        ));
    }

    /**
     * @return list<string>
     */
    public function progressMessages(): array
    {
        return $this->progressMessages;
    }

    /**
     * @return list<string>
     */
    public function errorMessages(): array
    {
        return $this->errorMessages;
    }

    public function isSuccessful(): bool
    {
        return $this->unpackOk()
            && $this->refStatuses !== []
            && $this->rejectedRefs() === []
            && $this->errorMessages === [];
    }

    /**
     * @param list<string> $expectedRefNames
     */
    public function forExpectedRefNames(array $expectedRefNames): self
    {
        $expected = [];
        $ordered = [];
        foreach ($expectedRefNames as $refName) {
            ReferenceName::assertValid($refName);
            if (isset($expected[$refName])) {
                continue;
            }
            $expected[$refName] = true;
            $ordered[] = $refName;
        }

        $matched = [];
        foreach ($this->refStatuses as $status) {
            if (!isset($expected[$status->refName])) {
                if ($status->hasReportOption()) {
                    throw new \InvalidArgumentException("push response: report-status-v2 option followed unrequested ref {$status->refName}");
                }

                continue;
            }
            $matched[$status->refName][] = $status;
        }

        $filtered = [];
        foreach ($ordered as $refName) {
            if (isset($matched[$refName])) {
                array_push($filtered, ...self::expectedRefStatuses($matched[$refName]));
                continue;
            }

            $filtered[] = PushRefStatus::rejected($refName, 'remote failed to report status');
        }

        return new self($this->unpackStatus, $filtered, $this->progressMessages, $this->errorMessages);
    }

    /**
     * @param list<PushUpdate> $expectedUpdates
     */
    public function forExpectedUpdates(array $expectedUpdates): self
    {
        $expectedRefNames = [];
        $updatesByRef = [];
        foreach ($expectedUpdates as $update) {
            if (!$update instanceof PushUpdate) {
                throw new \InvalidArgumentException('push response: expected updates must be PushUpdate instances');
            }
            if (isset($updatesByRef[$update->refName])) {
                continue;
            }

            $updatesByRef[$update->refName] = $update;
            $expectedRefNames[] = $update->refName;
        }

        $filtered = $this->forExpectedRefNames($expectedRefNames);
        $statuses = [];
        foreach ($filtered->refStatuses as $status) {
            $update = $updatesByRef[$status->refName] ?? null;
            $statuses[] = $update !== null && $status->hasReportOption()
                ? $status->withObjectFallbacks($update->oldObject, $update->newObject)
                : $status;
        }

        return new self($filtered->unpackStatus, $statuses, $filtered->progressMessages, $filtered->errorMessages);
    }

    /**
     * @param non-empty-list<PushRefStatus> $statuses
     * @return non-empty-list<PushRefStatus>
     */
    private static function expectedRefStatuses(array $statuses): array
    {
        $last = $statuses[count($statuses) - 1];
        $reports = [];
        foreach ($statuses as $status) {
            if ($status->isOk() && $status->hasReportOption()) {
                $reports[] = $status;
            }
        }

        if (!$last->isOk()) {
            return $reports !== []
                ? array_map(
                    static fn (PushRefStatus $status): PushRefStatus => $status->asRejected($last->message ?? 'failed'),
                    $reports
                )
                : [$last];
        }

        return $reports !== [] ? $reports : [$last];
    }

    /**
     * @param list<string> $progressMessages
     * @param list<string> $errorMessages
     * @param array<string, true>|null $expectedRefLookup
     */
    private static function parseReportStatus(string $bytes, array $progressMessages, array $errorMessages, string $objectFormat, ?array $expectedRefLookup): self
    {
        $offset = 0;
        $unpackStatus = null;
        $refStatuses = [];
        $sawFlush = false;
        $optionAllowed = false;

        while (true) {
            $packet = self::readPacket($bytes, $offset);
            if ($packet === null) {
                break;
            }
            if ($packet['kind'] === 'flush' || $packet['kind'] === 'delimiter' || $packet['kind'] === 'response-end') {
                $sawFlush = true;
                break;
            }
            if ($packet['kind'] !== 'data') {
                throw new \InvalidArgumentException("push response: unexpected {$packet['kind']} packet in report-status stream");
            }
            if (str_starts_with($packet['payload'], 'ERR ')) {
                throw new \RuntimeException('push response: receive-pack error ' . self::trimLineEnding(substr($packet['payload'], 4)));
            }

            $line = self::trimLineEnding($packet['payload']);
            if ($unpackStatus === null) {
                if (!str_starts_with($line, 'unpack ')) {
                    throw new \InvalidArgumentException('push response: first report-status line must be unpack status');
                }
                $unpackStatus = substr($line, strlen('unpack '));
                continue;
            }

            self::parseStatusLine($line, $refStatuses, $objectFormat, $expectedRefLookup, $optionAllowed);
        }

        if (!$sawFlush) {
            throw new \InvalidArgumentException('push response: missing report-status flush packet');
        }
        if ($unpackStatus === null) {
            throw new \InvalidArgumentException('push response: missing unpack status');
        }

        return new self($unpackStatus, $refStatuses, $progressMessages, $errorMessages);
    }

    /**
     * @param list<PushRefStatus> $refStatuses
     * @param array<string, true>|null $expectedRefLookup
     */
    private static function parseStatusLine(string $line, array &$refStatuses, string $objectFormat, ?array $expectedRefLookup, bool &$optionAllowed): void
    {
        if (str_starts_with($line, 'ok ')) {
            [$refName, $message] = self::splitRefStatusPayload(substr($line, 3));
            $optionAllowed = false;
            if ($expectedRefLookup !== null && !isset($expectedRefLookup[$refName])) {
                return;
            }
            $refStatuses[] = PushRefStatus::ok($refName, $message);
            $optionAllowed = true;

            return;
        }

        if (str_starts_with($line, 'ng ')) {
            [$refName, $message] = self::splitRefStatusPayload(substr($line, 3));
            $optionAllowed = false;
            if ($expectedRefLookup !== null && !isset($expectedRefLookup[$refName])) {
                return;
            }
            $refStatuses[] = PushRefStatus::rejected($refName, $message ?? 'failed');

            return;
        }

        if (str_starts_with($line, 'option ')) {
            if (!$optionAllowed || $refStatuses === []) {
                throw new \InvalidArgumentException('push response: report-status-v2 option appeared before a ref status');
            }

            $option = substr($line, strlen('option '));
            $name = $option;
            $value = null;
            if (str_contains($option, ' ')) {
                [$name, $value] = explode(' ', $option, 2);
            }
            $last = count($refStatuses) - 1;
            $refStatuses[$last] = $refStatuses[$last]->withOption($name, $value, $objectFormat);

            return;
        }

        throw new \InvalidArgumentException("push response: unknown report-status line {$line}");
    }

    /**
     * @param list<string> $expectedRefNames
     * @return array<string, true>
     */
    private static function expectedRefLookup(array $expectedRefNames): array
    {
        $lookup = [];
        foreach ($expectedRefNames as $refName) {
            if (!is_string($refName)) {
                throw new \InvalidArgumentException('push response: expected ref names must be strings');
            }
            ReferenceName::assertValid($refName);
            $lookup[$refName] = true;
        }

        return $lookup;
    }

    /**
     * @return array{string, ?string}
     */
    private static function splitRefStatusPayload(string $payload): array
    {
        if (str_contains($payload, ' ')) {
            [$refName, $message] = explode(' ', $payload, 2);

            return [$refName, $message];
        }

        return [$payload, null];
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
            throw new \InvalidArgumentException('push response: truncated packet line length');
        }

        $header = substr($bytes, $offset, 4);
        if (preg_match('/^[0-9a-fA-F]{4}$/', $header) !== 1) {
            throw new \InvalidArgumentException("push response: invalid packet line length {$header}");
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
            throw new \InvalidArgumentException("push response: invalid packet line length {$header}");
        }
        if ($length === 4) {
            throw new \InvalidArgumentException('push response: invalid empty packet line');
        }
        if ($length > self::MAX_PACKET_LINE_LENGTH) {
            throw new \InvalidArgumentException("push response: packet line exceeds maximum length {$header}");
        }

        $payloadLength = $length - 4;
        if ($offset + $payloadLength > strlen($bytes)) {
            throw new \InvalidArgumentException('push response: truncated packet line payload');
        }

        $payload = substr($bytes, $offset, $payloadLength);
        $offset += $payloadLength;

        return ['kind' => 'data', 'payload' => $payload];
    }

    private static function trimLineEnding(string $line): string
    {
        return self::trimOneTrailingNewline($line);
    }

    private static function trimOneTrailingNewline(string $data): string
    {
        return str_ends_with($data, "\n") ? substr($data, 0, -1) : $data;
    }
}
