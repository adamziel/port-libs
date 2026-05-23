<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class BuiltinDriver
{
    public const TEXT = 'text';
    public const BINARY = 'binary';
    public const UNION = 'union';

    public const ATTRIBUTE_SET = 'set';
    public const ATTRIBUTE_UNSET = 'unset';
    public const ATTRIBUTE_VALUE = 'value';
    public const ATTRIBUTE_UNSPECIFIED = 'unspecified';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::TEXT, self::BINARY, self::UNION];
    }

    public static function byName(string $name): ?string
    {
        return in_array($name, self::all(), true) ? $name : null;
    }

    public static function asString(string $driver): string
    {
        if (self::byName($driver) === null) {
            throw new \InvalidArgumentException("Unknown built-in merge driver: {$driver}");
        }

        return $driver;
    }

    public static function fromMergeAttribute(string $state, ?string $value = null, ?string $defaultDriver = null): string
    {
        return match ($state) {
            self::ATTRIBUTE_SET => self::TEXT,
            self::ATTRIBUTE_UNSET => self::BINARY,
            self::ATTRIBUTE_VALUE => self::byName((string) $value) ?? self::TEXT,
            self::ATTRIBUTE_UNSPECIFIED => ($defaultDriver === null ? null : self::byName($defaultDriver)) ?? self::TEXT,
            default => throw new \InvalidArgumentException("Unknown merge attribute state: {$state}"),
        };
    }

    public static function markerSizeFromAttribute(?string $value, int $fallback = 7): int
    {
        if ($value === null || !preg_match('/^[0-9]+$/', $value)) {
            return $fallback;
        }

        $markerSize = (int) $value;

        return $markerSize >= 1 && $markerSize <= 255 ? $markerSize : $fallback;
    }

    public static function merge(
        string $driver,
        string $base,
        string $ours,
        string $theirs,
        string $textStyle = BlobMerge::STYLE_MERGE,
        ?string $baseLabel = 'base',
        ?string $oursLabel = 'ours',
        ?string $theirsLabel = 'theirs',
        int $markerSize = 7,
        ?string $binaryResolveWith = null,
    ): BlobMergeResult {
        return match (self::asString($driver)) {
            self::TEXT => BlobMerge::mergeText($base, $ours, $theirs, $textStyle, $baseLabel, $oursLabel, $theirsLabel, $markerSize),
            self::UNION => BlobMerge::mergeText($base, $ours, $theirs, BlobMerge::STYLE_UNION, $baseLabel, $oursLabel, $theirsLabel, $markerSize),
            self::BINARY => BlobMerge::mergeBinary($base, $ours, $theirs, $binaryResolveWith),
        };
    }
}
