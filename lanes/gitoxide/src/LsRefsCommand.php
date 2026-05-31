<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class LsRefsCommand
{
    private const ARGUMENT_PREFIXES = ['symrefs', 'peel', 'ref-prefix ', 'unborn'];

    /**
     * @param list<string> $features
     * @param list<string> $arguments
     */
    private function __construct(
        private readonly ProtocolCapabilities $capabilities,
        private readonly array $features,
        private readonly array $arguments,
        private readonly ?string $agent = null,
    ) {
    }

    /**
     * @param null|list<string> $refPrefixes
     */
    public static function create(?array $refPrefixes, ProtocolCapabilities $capabilities, ?string $agent = null): self
    {
        $features = [];
        if ($agent !== null) {
            $features[] = 'agent';
        }

        $arguments = ['symrefs', 'peel'];
        if (($capabilities->capability('ls-refs')?->supports('unborn')) === true) {
            $arguments[] = 'unborn';
        }
        foreach (self::prefixArguments($refPrefixes ?? []) as $argument) {
            $arguments[] = $argument;
        }

        return new self($capabilities, $features, $arguments, $agent);
    }

    /**
     * @param list<string|RefSpec> $fetchRefspecs
     */
    public static function createFromFetchRefspecs(array $fetchRefspecs, ProtocolCapabilities $capabilities, ?string $agent = null): self
    {
        return self::create(self::refPrefixesFromFetchRefspecs($fetchRefspecs), $capabilities, $agent);
    }

    /**
     * @return list<string>
     */
    public function features(): array
    {
        return $this->features;
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return list<string>
     */
    public function requestFeatureLines(): array
    {
        if ($this->agent === null) {
            return [];
        }

        return ['agent=' . $this->agent];
    }

    public function requestBytes(): string
    {
        $this->validate();

        $bytes = self::packetLine("command=ls-refs\n");
        foreach ($this->requestFeatureLines() as $feature) {
            $bytes .= self::textPacketLine($feature);
        }
        if ($this->arguments !== []) {
            $bytes .= '0001';
            foreach ($this->arguments as $argument) {
                $bytes .= self::textPacketLine($argument);
            }
        }

        return $bytes . '0000';
    }

    public function validate(): void
    {
        foreach ($this->arguments as $argument) {
            $known = false;
            foreach (self::ARGUMENT_PREFIXES as $prefix) {
                if (str_starts_with($argument, $prefix)) {
                    $known = true;
                    break;
                }
            }
            if (!$known) {
                throw new \InvalidArgumentException("ls-refs: argument {$argument} is not known or allowed");
            }
        }

        foreach ($this->requestFeatureLines() as $featureLine) {
            self::assertProtocolTextLine($featureLine, 'ls-refs feature');
        }

        $allowed = $this->capabilities->capability('ls-refs')?->values() ?? [];
        foreach ($this->features as $feature) {
            if ($feature === 'agent' || in_array($feature, $allowed, true)) {
                continue;
            }
            throw new \InvalidArgumentException("ls-refs: capability {$feature} is not supported");
        }
    }

    /**
     * @param list<string> $prefixes
     * @return list<string>
     */
    public static function prefixArguments(array $prefixes): array
    {
        $seen = [];
        $out = [];
        foreach ($prefixes as $prefix) {
            if (isset($seen[$prefix])) {
                continue;
            }
            $seen[$prefix] = true;
            $out[] = 'ref-prefix ' . $prefix;
        }

        return $out;
    }

    /**
     * @param list<string|RefSpec> $fetchRefspecs
     * @return list<string>
     */
    public static function refPrefixesFromFetchRefspecs(array $fetchRefspecs): array
    {
        $seen = [];
        $out = [];
        foreach ($fetchRefspecs as $fetchRefspec) {
            if (is_string($fetchRefspec)) {
                $fetchRefspec = RefSpec::parseFetch($fetchRefspec);
            }
            if (!$fetchRefspec instanceof RefSpec) {
                throw new \InvalidArgumentException('Fetch refspecs must be strings or RefSpec instances');
            }
            if ($fetchRefspec->operation() !== RefSpec::OP_FETCH) {
                throw new \InvalidArgumentException('Only fetch refspecs can be expanded for ls-refs prefixes');
            }

            foreach ($fetchRefspec->expandPrefixes() as $prefix) {
                if (isset($seen[$prefix])) {
                    continue;
                }
                $seen[$prefix] = true;
                $out[] = $prefix;
            }
        }

        return $out;
    }

    /**
     * @return list<RemoteRef>
     */
    public static function parseV2Refs(string $lines): array
    {
        $refs = [];
        foreach (preg_split('/\r?\n/', trim($lines)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }
            $refs[] = self::parseV2RefLine($line);
        }

        return $refs;
    }

    /**
     * @return list<RemoteRef>
     */
    public static function parseV2PacketLines(string $bytes): array
    {
        $offset = 0;
        $lines = '';

        while (true) {
            $packet = self::readPacket($bytes, $offset);
            if ($packet === null) {
                throw new \RuntimeException('ls-refs advertisement: missing flush packet');
            }
            if ($packet['kind'] === 'flush' || $packet['kind'] === 'response-end') {
                return self::parseV2Refs($lines);
            }
            if ($packet['kind'] === 'delimiter') {
                throw new \InvalidArgumentException('ls-refs advertisement: unexpected delimiter packet');
            }
            if (str_starts_with($packet['payload'], 'ERR ')) {
                throw new \RuntimeException('ls-refs advertisement: upload-pack error ' . self::trimLineEnding(substr($packet['payload'], 4)));
            }

            $lines .= $packet['payload'];
            if (!str_ends_with($lines, "\n")) {
                $lines .= "\n";
            }
        }
    }

    public static function parseV2RefLine(string $line): RemoteRef
    {
        $tokens = explode(' ', rtrim($line), 4);
        if (count($tokens) < 2 || $tokens[1] === '') {
            throw new \InvalidArgumentException("Malformed V2 ref line: {$line}");
        }

        [$oid, $name] = $tokens;
        $object = null;
        if ($oid !== 'unborn') {
            self::assertObjectId($oid);
            $object = strtolower($oid);
        }

        $attributes = [];
        if (isset($tokens[2])) {
            $attributes[] = $tokens[2];
        }
        if (isset($tokens[3])) {
            $attributes = array_merge($attributes, explode(' ', $tokens[3]));
        }
        if (count($attributes) > 2) {
            throw new \InvalidArgumentException("Malformed V2 ref line: {$line}");
        }

        $symrefTarget = null;
        $peeled = null;
        foreach ($attributes as $attribute) {
            if (!str_contains($attribute, ':')) {
                throw new \InvalidArgumentException("Malformed V2 ref line: {$line}");
            }
            [$attributeName, $value] = explode(':', $attribute, 2);
            if ($value === '') {
                throw new \InvalidArgumentException("Malformed V2 ref line: {$line}");
            }
            if ($attributeName === 'symref-target') {
                $symrefTarget = $value;
            } elseif ($attributeName === 'peeled') {
                self::assertObjectId($value);
                $peeled = strtolower($value);
            } else {
                throw new \InvalidArgumentException("Unknown V2 ref attribute {$attributeName}");
            }
        }

        if ($symrefTarget !== null) {
            if ($symrefTarget === '(null)') {
                if ($object === null) {
                    throw new \RuntimeException("got 'unborn' while (null) was a symref target");
                }
                return $peeled === null
                    ? RemoteRef::direct($name, $object)
                    : RemoteRef::peeled($name, $object, $peeled);
            }

            if ($object === null) {
                return RemoteRef::unborn($name, $symrefTarget);
            }

            return RemoteRef::symbolic($name, $symrefTarget, $peeled ?? $object, $peeled === null ? null : $object);
        }

        if ($peeled !== null) {
            if ($object === null) {
                throw new \RuntimeException("got 'unborn' as tag target");
            }
            return RemoteRef::peeled($name, $object, $peeled);
        }

        if ($object === null) {
            throw new \RuntimeException("got 'unborn' as object name of direct reference");
        }

        return RemoteRef::direct($name, $object);
    }

    private static function assertObjectId(string $oid): void
    {
        if (preg_match('/^(?:[0-9a-fA-F]{40}|[0-9a-fA-F]{64})$/', $oid) !== 1) {
            throw new \InvalidArgumentException('Protocol ref object id must be a 40-character SHA-1 or 64-character SHA-256 hex string');
        }
    }

    private static function textPacketLine(string $line): string
    {
        self::assertProtocolTextLine($line, 'ls-refs request line');
        if (!str_ends_with($line, "\n")) {
            $line .= "\n";
        }

        return self::packetLine($line);
    }

    private static function packetLine(string $payload): string
    {
        return sprintf('%04x', strlen($payload) + 4) . $payload;
    }

    private static function assertProtocolTextLine(string $line, string $label): void
    {
        if ($line === '') {
            throw new \InvalidArgumentException("{$label} cannot be empty");
        }
        if (str_contains($line, "\0") || str_contains($line, "\r")) {
            throw new \InvalidArgumentException("{$label} contains bytes that cannot be written as a protocol v2 text line");
        }

        $withoutFinalNewline = str_ends_with($line, "\n") ? substr($line, 0, -1) : $line;
        if (str_contains($withoutFinalNewline, "\n")) {
            throw new \InvalidArgumentException("{$label} contains bytes that cannot be written as a protocol v2 text line");
        }
    }

    /**
     * @return null|array{kind:string,payload:string}
     */
    private static function readPacket(string $bytes, int &$offset): ?array
    {
        if ($offset >= strlen($bytes)) {
            return null;
        }
        if ($offset + 4 > strlen($bytes)) {
            throw new \InvalidArgumentException('ls-refs advertisement: truncated packet line length');
        }

        $header = substr($bytes, $offset, 4);
        if (preg_match('/^[0-9a-fA-F]{4}$/', $header) !== 1) {
            throw new \InvalidArgumentException("ls-refs advertisement: invalid packet line length {$header}");
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
            throw new \InvalidArgumentException("ls-refs advertisement: invalid packet line length {$header}");
        }

        $payloadLength = $length - 4;
        if ($offset + $payloadLength > strlen($bytes)) {
            throw new \InvalidArgumentException('ls-refs advertisement: truncated packet line payload');
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
