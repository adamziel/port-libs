<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitHash
{
    public const SHA1 = 'sha1';
    public const SHA256 = 'sha256';

    private const MIN_PREFIX_HEX_LENGTH = 4;
    private const HASH_BYTES = [
        self::SHA1 => 20,
        self::SHA256 => 32,
    ];
    private const EMPTY_BLOB_IDS = [
        self::SHA1 => 'e69de29bb2d1d6434b8b29ae775ad8c2e48c5391',
        self::SHA256 => '473a0f4c3be8a93681a267e3b1e9a7dcda1185436fe141f7749120a303721813',
    ];
    private const EMPTY_TREE_IDS = [
        self::SHA1 => '4b825dc642cb6eb9a060e54bf8d69288fbee4904',
        self::SHA256 => '6ef19b41225c5369f1c104d45d8d85efa9b057b53b14b4b9b939dd74decc5321',
    ];

    private function __construct()
    {
    }

    public static function parseKind(string $input): string
    {
        return match ($input) {
            'sha1', 'SHA1', 'SHA-1' => self::SHA1,
            'sha256', 'SHA256', 'SHA-256' => self::SHA256,
            default => throw new \InvalidArgumentException("Unknown hash kind: {$input}"),
        };
    }

    public static function displayKind(string $kind): string
    {
        return self::assertKind($kind);
    }

    public static function kindLengthInBytes(string $kind): int
    {
        return self::HASH_BYTES[self::assertKind($kind)];
    }

    public static function kindLengthInHex(string $kind): int
    {
        return self::kindLengthInBytes($kind) * 2;
    }

    public static function kindFromHexLength(int $hexLength): ?string
    {
        if ($hexLength < 0) {
            return null;
        }
        if ($hexLength <= self::kindLengthInHex(self::SHA1)) {
            return self::SHA1;
        }
        if ($hexLength <= self::kindLengthInHex(self::SHA256)) {
            return self::SHA256;
        }

        return null;
    }

    public static function shortestKind(): string
    {
        return self::SHA1;
    }

    public static function longestKind(): string
    {
        return self::SHA256;
    }

    /**
     * @return list<string>
     */
    public static function allKinds(): array
    {
        return [self::SHA1, self::SHA256];
    }

    public static function nullId(string $kind): string
    {
        return str_repeat('0', self::kindLengthInHex($kind));
    }

    public static function emptyBlobId(string $kind): string
    {
        return self::EMPTY_BLOB_IDS[self::assertKind($kind)];
    }

    public static function emptyTreeId(string $kind): string
    {
        return self::EMPTY_TREE_IDS[self::assertKind($kind)];
    }

    public static function objectIdFromHex(string $hex): string
    {
        $length = strlen($hex);
        if ($length !== self::kindLengthInHex(self::SHA1) && $length !== self::kindLengthInHex(self::SHA256)) {
            throw new \InvalidArgumentException("A hash sized {$length} hexadecimal characters is invalid");
        }
        if (preg_match('/\A[0-9a-fA-F]+\z/', $hex) !== 1) {
            throw new \InvalidArgumentException('Invalid character encountered');
        }

        return strtolower($hex);
    }

    public static function objectIdFromBytes(string $bytes): string
    {
        $length = strlen($bytes);
        if ($length !== self::kindLengthInBytes(self::SHA1) && $length !== self::kindLengthInBytes(self::SHA256)) {
            throw new \InvalidArgumentException("A hash sized " . ($length * 2) . " hexadecimal characters is invalid");
        }

        return bin2hex($bytes);
    }

    public static function objectIdKind(string $hex): string
    {
        $hex = self::objectIdFromHex($hex);

        return self::kindFromExactHexLength(strlen($hex));
    }

    /**
     * @return array{oid: string, kind: string, hexLength: int}
     */
    public static function prefixNew(string $objectIdHex, int $hexLength): array
    {
        $objectIdHex = self::objectIdFromHex($objectIdHex);
        $kind = self::kindFromExactHexLength(strlen($objectIdHex));
        $kindHexLength = self::kindLengthInHex($kind);
        if ($hexLength > $kindHexLength) {
            throw new \InvalidArgumentException("An object of kind {$kind} cannot be larger than {$kindHexLength} in hex, but {$hexLength} was requested");
        }
        if ($hexLength < self::MIN_PREFIX_HEX_LENGTH) {
            throw new \InvalidArgumentException("The minimum hex length of a short object id is " . self::MIN_PREFIX_HEX_LENGTH . ", got {$hexLength}");
        }

        return self::prefixFromCanonicalHex(substr($objectIdHex, 0, $hexLength), $kind, $hexLength);
    }

    /**
     * @return array{oid: string, kind: string, hexLength: int}
     */
    public static function prefixFromHex(string $hex): array
    {
        $hexLength = strlen($hex);
        if ($hexLength < self::MIN_PREFIX_HEX_LENGTH) {
            throw new \InvalidArgumentException("The minimum hex length of a short object id is " . self::MIN_PREFIX_HEX_LENGTH . ", got {$hexLength}");
        }

        return self::prefixFromHexNonEmpty($hex);
    }

    /**
     * @return array{oid: string, kind: string, hexLength: int}
     */
    public static function prefixFromHexNonEmpty(string $hex): array
    {
        $hexLength = strlen($hex);
        $longest = self::kindLengthInHex(self::longestKind());
        if ($hexLength > $longest) {
            throw new \InvalidArgumentException("An id cannot be larger than {$longest} chars in hex, but {$hexLength} was requested");
        }
        if ($hexLength === 0) {
            throw new \InvalidArgumentException("The minimum hex length of a short object id is " . self::MIN_PREFIX_HEX_LENGTH . ", got 0");
        }
        if (preg_match('/\A[0-9a-fA-F]+\z/', $hex) !== 1) {
            throw new \InvalidArgumentException('Invalid hex character');
        }

        $kind = self::kindFromHexLength($hexLength);
        if ($kind === null) {
            throw new \InvalidArgumentException("An id cannot be larger than {$longest} chars in hex, but {$hexLength} was requested");
        }

        return self::prefixFromCanonicalHex(strtolower($hex), $kind, $hexLength);
    }

    /**
     * @param array{oid: string, kind: string, hexLength: int} $prefix
     */
    public static function prefixAsObjectId(array $prefix): string
    {
        return $prefix['oid'];
    }

    /**
     * @param array{oid: string, kind: string, hexLength: int} $prefix
     */
    public static function prefixHexLength(array $prefix): int
    {
        return $prefix['hexLength'];
    }

    /**
     * @param array{oid: string, kind: string, hexLength: int} $prefix
     */
    public static function prefixToHex(array $prefix): string
    {
        return substr($prefix['oid'], 0, $prefix['hexLength']);
    }

    /**
     * @param array{oid: string, kind: string, hexLength: int} $prefix
     */
    public static function prefixCompareObjectId(array $prefix, string $objectIdHex): int
    {
        $objectIdHex = self::objectIdFromHex($objectIdHex);

        return self::compareHex(self::prefixToHex($prefix), substr($objectIdHex, 0, $prefix['hexLength']));
    }

    /**
     * @param array{oid: string, kind: string, hexLength: int} $left
     * @param array{oid: string, kind: string, hexLength: int} $right
     */
    public static function prefixCompare(array $left, array $right): int
    {
        $leftKindLength = self::kindLengthInHex($left['kind']);
        $rightKindLength = self::kindLengthInHex($right['kind']);
        if ($leftKindLength !== $rightKindLength) {
            return $leftKindLength <=> $rightKindLength;
        }

        $oidComparison = self::compareHex($left['oid'], $right['oid']);
        if ($oidComparison !== 0) {
            return $oidComparison;
        }

        return $left['hexLength'] <=> $right['hexLength'];
    }

    /**
     * @return array{oid: string, kind: string, hexLength: int}
     */
    private static function prefixFromCanonicalHex(string $prefixHex, string $kind, int $hexLength): array
    {
        $kind = self::assertKind($kind);
        $oid = substr($prefixHex . str_repeat('0', self::kindLengthInHex($kind)), 0, self::kindLengthInHex($kind));

        return [
            'oid' => $oid,
            'kind' => $kind,
            'hexLength' => $hexLength,
        ];
    }

    private static function assertKind(string $kind): string
    {
        if (!isset(self::HASH_BYTES[$kind])) {
            throw new \InvalidArgumentException("Unsupported hash kind: {$kind}");
        }

        return $kind;
    }

    private static function kindFromExactHexLength(int $hexLength): string
    {
        return match ($hexLength) {
            40 => self::SHA1,
            64 => self::SHA256,
            default => throw new \InvalidArgumentException("A hash sized {$hexLength} hexadecimal characters is invalid"),
        };
    }

    private static function compareHex(string $left, string $right): int
    {
        $comparison = strcmp($left, $right);
        if ($comparison < 0) {
            return -1;
        }
        if ($comparison > 0) {
            return 1;
        }

        return 0;
    }
}
