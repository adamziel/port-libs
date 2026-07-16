<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

use InvalidArgumentException;

final class DeviceId
{
    public const LENGTH = 32;
    public const SHORT_STRING_LENGTH = 7;

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const LUHN_CHUNK_LENGTH = 13;
    private const LUHN_CHUNKS = 4;

    private string $bytes;

    private function __construct(string $bytes)
    {
        if (strlen($bytes) !== self::LENGTH) {
            throw new InvalidArgumentException('Device ID must be exactly 32 bytes');
        }

        $this->bytes = $bytes;
    }

    public static function fromRawCertificateBytes(string $rawCertificate): self
    {
        return new self(hash('sha256', $rawCertificate, true));
    }

    public static function fromBytes(string $bytes): self
    {
        return new self($bytes);
    }

    public static function fromString(string $value): self
    {
        $id = trim($value, '=');
        $id = strtoupper($id);
        $id = str_replace(['0', '1', '8'], ['O', 'I', 'B'], $id);
        $id = str_replace(['-', ' '], '', $id);

        if ($id === '') {
            return self::empty();
        }

        if (strlen($id) === 56) {
            $id = self::unluhnify($id);
        } elseif (strlen($id) !== 52) {
            throw new InvalidArgumentException('device ID invalid: incorrect length');
        }

        return new self(self::base32Decode($id));
    }

    public static function empty(): self
    {
        return new self(str_repeat("\0", self::LENGTH));
    }

    public static function local(): self
    {
        return new self(str_repeat("\xff", self::LENGTH));
    }

    public static function global(): self
    {
        return new self(str_repeat("\xf8", self::LENGTH));
    }

    public static function luhn32CheckDigit(string $value): string
    {
        $factor = 1;
        $sum = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $codepoint = self::codepoint32($value[$i]);
            if ($codepoint === null) {
                throw new InvalidArgumentException("digit '{$value[$i]}' not valid in alphabet");
            }

            $addend = $factor * $codepoint;
            $factor = $factor === 2 ? 1 : 2;
            $sum += intdiv($addend, 32) + ($addend % 32);
        }

        return self::BASE32_ALPHABET[(32 - ($sum % 32)) % 32];
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    public function hex(): string
    {
        return bin2hex($this->bytes);
    }

    public function isEmpty(): bool
    {
        return $this->bytes === str_repeat("\0", self::LENGTH);
    }

    public function toString(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        return self::chunkify(self::luhnify(self::base32Encode($this->bytes)));
    }

    public function shortString(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        return substr(self::base32Encode(substr($this->bytes, 0, 8)), 0, self::SHORT_STRING_LENGTH);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->bytes, $other->bytes);
    }

    public function compare(self $other): int
    {
        $comparison = strcmp($this->bytes, $other->bytes);

        return $comparison < 0 ? -1 : ($comparison > 0 ? 1 : 0);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private static function luhnify(string $value): string
    {
        if (strlen($value) !== self::LUHN_CHUNK_LENGTH * self::LUHN_CHUNKS) {
            throw new InvalidArgumentException('unsupported string length');
        }

        $out = '';
        for ($i = 0; $i < self::LUHN_CHUNKS; $i++) {
            $chunk = substr($value, $i * self::LUHN_CHUNK_LENGTH, self::LUHN_CHUNK_LENGTH);
            $out .= $chunk . self::luhn32CheckDigit($chunk);
        }

        return $out;
    }

    private static function unluhnify(string $value): string
    {
        if (strlen($value) !== self::LUHN_CHUNKS * (self::LUHN_CHUNK_LENGTH + 1)) {
            throw new InvalidArgumentException('unsupported string length');
        }

        $out = '';
        for ($i = 0; $i < self::LUHN_CHUNKS; $i++) {
            $start = $i * (self::LUHN_CHUNK_LENGTH + 1);
            $chunk = substr($value, $start, self::LUHN_CHUNK_LENGTH);
            $check = $value[$start + self::LUHN_CHUNK_LENGTH];
            $expected = self::luhn32CheckDigit($chunk);
            if ($check !== $expected) {
                throw new InvalidArgumentException('device ID check digit incorrect');
            }
            $out .= $chunk;
        }

        return $out;
    }

    private static function chunkify(string $value): string
    {
        return implode('-', str_split($value, 7));
    }

    private static function codepoint32(string $char): ?int
    {
        $ord = ord($char);
        if ($ord >= ord('A') && $ord <= ord('Z')) {
            return $ord - ord('A');
        }
        if ($ord >= ord('2') && $ord <= ord('7')) {
            return 26 + $ord - ord('2');
        }

        return null;
    }

    private static function base32Encode(string $bytes): string
    {
        $buffer = 0;
        $bits = 0;
        $out = '';
        $length = strlen($bytes);

        for ($i = 0; $i < $length; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $out .= self::BASE32_ALPHABET[($buffer >> $bits) & 0x1f];
                $buffer &= $bits === 0 ? 0 : (1 << $bits) - 1;
            }
        }

        if ($bits > 0) {
            $out .= self::BASE32_ALPHABET[($buffer << (5 - $bits)) & 0x1f];
        }

        return $out;
    }

    private static function base32Decode(string $value): string
    {
        $buffer = 0;
        $bits = 0;
        $out = '';
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $codepoint = self::codepoint32($value[$i]);
            if ($codepoint === null) {
                throw new InvalidArgumentException('illegal base32 data');
            }

            $buffer = ($buffer << 5) | $codepoint;
            $bits += 5;

            while ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buffer >> $bits) & 0xff);
                $buffer &= $bits === 0 ? 0 : (1 << $bits) - 1;
            }
        }

        if ($bits > 0 && $buffer !== 0) {
            throw new InvalidArgumentException('illegal base32 padding data');
        }
        if (strlen($out) !== self::LENGTH) {
            throw new InvalidArgumentException('incorrect decoded device ID length');
        }

        return $out;
    }
}
