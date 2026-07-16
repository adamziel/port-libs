<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class SyncCodec
{
    /**
     * @param list<SyncRequest> $requests
     */
    public static function encodeRequests(array $requests): string
    {
        $output = '';

        foreach ($requests as $request) {
            if (!$request instanceof SyncRequest) {
                throw new \InvalidArgumentException('encodeRequests expects SyncRequest instances');
            }

            $output .= self::encodeKeyHash($request->pathHex());
            $output .= chr($request->startDepth);
            $output .= chr($request->depthLimit);
            $output .= chr($request->expandLeaves ? 1 : 0);
        }

        return $output;
    }

    /**
     * @return list<SyncRequest>
     */
    public static function decodeRequests(string $encoded): array
    {
        $offset = 0;
        $requests = [];

        while ($offset < strlen($encoded)) {
            $requests[] = new SyncRequest(
                Key::fromHex(self::decodeKeyHash($encoded, $offset)),
                self::readByte($encoded, $offset),
                self::readByte($encoded, $offset),
                (self::readByte($encoded, $offset) & 1) === 1
            );
        }

        return $requests;
    }

    /**
     * @param list<Proof> $responses
     */
    public static function encodeResponses(array $responses, int $encodingType = Proof::ENCODING_HASHED_KEYS): string
    {
        $output = '';

        foreach ($responses as $response) {
            if (!$response instanceof Proof) {
                throw new \InvalidArgumentException('encodeResponses expects Proof instances');
            }

            $proof = $response->encode($encodingType);
            $output .= self::encodeVarInt(strlen($proof));
            $output .= $proof;
        }

        return $output;
    }

    /**
     * @return list<Proof>
     */
    public static function decodeResponses(string $encoded): array
    {
        $offset = 0;
        $responses = [];

        while ($offset < strlen($encoded)) {
            $proofSize = self::decodeVarInt($encoded, $offset);
            $responses[] = Proof::decode(self::readBytes($encoded, $offset, $proofSize));
        }

        return $responses;
    }

    private static function encodeKeyHash(string $keyHashHex): string
    {
        self::assertHash($keyHashHex);
        $keyHash = (string) hex2bin($keyHashHex);

        $trailingZeros = 0;
        for ($i = 31; $i >= 0; $i--) {
            if ($keyHash[$i] !== "\0") {
                break;
            }
            $trailingZeros++;
        }

        return chr($trailingZeros) . substr($keyHash, 0, 32 - $trailingZeros);
    }

    private static function decodeKeyHash(string $encoded, int &$offset): string
    {
        $trailingZeros = self::readByte($encoded, $offset);
        if ($trailingZeros > 32) {
            throw new \RuntimeException('invalid trailing-zero key hash count');
        }

        return bin2hex(self::readBytes($encoded, $offset, 32 - $trailingZeros) . str_repeat("\0", $trailingZeros));
    }

    private static function encodeVarInt(int $number): string
    {
        if ($number < 0) {
            throw new \InvalidArgumentException('varint cannot encode negative numbers');
        }
        if ($number === 0) {
            return "\0";
        }

        $bytes = [];
        while ($number > 0) {
            array_unshift($bytes, $number & 0x7f);
            $number >>= 7;
        }

        $last = count($bytes) - 1;
        foreach ($bytes as $index => $byte) {
            if ($index !== $last) {
                $byte |= 0x80;
            }
            $bytes[$index] = chr($byte);
        }

        return implode('', $bytes);
    }

    private static function decodeVarInt(string $encoded, int &$offset): int
    {
        $result = 0;
        while (true) {
            $byte = self::readByte($encoded, $offset);
            $result = ($result << 7) | ($byte & 0b0111_1111);
            if (($byte & 0b1000_0000) === 0) {
                return $result;
            }
        }
    }

    private static function readByte(string $encoded, int &$offset): int
    {
        if ($offset >= strlen($encoded)) {
            throw new \RuntimeException('sync transport ends prematurely');
        }

        return ord($encoded[$offset++]);
    }

    private static function readBytes(string $encoded, int &$offset, int $length): string
    {
        if ($length < 0 || strlen($encoded) - $offset < $length) {
            throw new \RuntimeException('sync transport ends prematurely');
        }

        $bytes = substr($encoded, $offset, $length);
        $offset += $length;

        return $bytes;
    }

    private static function assertHash(string $hashHex): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $hashHex)) {
            throw new \InvalidArgumentException('Expected lowercase 32-byte hash hex');
        }
    }
}
