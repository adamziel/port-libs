<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitConfigValue
{
    private const INT64_MAX = '9223372036854775807';
    private const INT64_MIN_ABS = '9223372036854775808';

    private const COLOR_NAMES = [
        'normal' => 'normal',
        '-1' => 'normal',
        'default' => 'default',
        'black' => 'black',
        'red' => 'red',
        'green' => 'green',
        'yellow' => 'yellow',
        'blue' => 'blue',
        'magenta' => 'magenta',
        'cyan' => 'cyan',
        'white' => 'white',
    ];

    private const COLOR_BRIGHT_NAMES = [
        'black' => 'brightblack',
        'red' => 'brightred',
        'green' => 'brightgreen',
        'yellow' => 'brightyellow',
        'blue' => 'brightblue',
        'magenta' => 'brightmagenta',
        'cyan' => 'brightcyan',
        'white' => 'brightwhite',
    ];

    private const COLOR_ATTRIBUTES = [
        'reset' => ['canonical' => 'reset', 'order' => 8],
        'bold' => ['canonical' => 'bold', 'order' => 1],
        'dim' => ['canonical' => 'dim', 'order' => 2],
        'italic' => ['canonical' => 'italic', 'order' => 3],
        'ul' => ['canonical' => 'ul', 'order' => 4],
        'blink' => ['canonical' => 'blink', 'order' => 5],
        'reverse' => ['canonical' => 'reverse', 'order' => 6],
        'strike' => ['canonical' => 'strike', 'order' => 7],
    ];

    private const COLOR_INVERTED_ATTRIBUTES = [
        'bold' => ['canonical' => 'nobold', 'order' => 22],
        'dim' => ['canonical' => 'nodim', 'order' => 21],
        'italic' => ['canonical' => 'noitalic', 'order' => 23],
        'ul' => ['canonical' => 'noul', 'order' => 24],
        'blink' => ['canonical' => 'noblink', 'order' => 25],
        'reverse' => ['canonical' => 'noreverse', 'order' => 26],
        'strike' => ['canonical' => 'nostrike', 'order' => 27],
    ];

    private function __construct()
    {
    }

    public static function parseBoolean(string $value): bool
    {
        $lower = strtolower($value);
        if ($lower === 'yes' || $lower === 'on' || $lower === 'true') {
            return true;
        }

        if ($lower === 'no' || $lower === 'off' || $lower === 'false' || $value === '') {
            return false;
        }

        $integer = self::parseInt64($value);
        if ($integer !== null) {
            return $integer !== 0;
        }

        throw new \InvalidArgumentException("Invalid Git boolean value: {$value}");
    }

    /**
     * @return array{value:int,suffix:?string}
     */
    public static function parseInteger(string $value): array
    {
        self::assertUtf8($value, 'integer');

        $integer = self::parseInt64($value);
        if ($integer !== null) {
            return ['value' => $integer, 'suffix' => null];
        }

        if (strlen($value) <= 1) {
            throw new \InvalidArgumentException("Invalid Git integer value: {$value}");
        }

        $number = substr($value, 0, -1);
        $suffix = substr($value, -1);
        $integer = self::parseInt64($number);
        $normalizedSuffix = match ($suffix) {
            'k', 'K' => 'k',
            'm', 'M' => 'm',
            'g', 'G' => 'g',
            default => null,
        };

        if ($integer === null || $normalizedSuffix === null) {
            throw new \InvalidArgumentException("Invalid Git integer value: {$value}");
        }

        return ['value' => $integer, 'suffix' => $normalizedSuffix];
    }

    /**
     * @param array{value:int,suffix:?string} $integer
     */
    public static function integerToDecimal(array $integer): ?int
    {
        $value = $integer['value'];
        $multiplier = match ($integer['suffix']) {
            null => 1,
            'k' => 1024,
            'm' => 1024 * 1024,
            'g' => 1024 * 1024 * 1024,
            default => throw new \InvalidArgumentException('Invalid Git integer suffix'),
        };

        if ($multiplier === 1) {
            return $value;
        }

        if ($value > intdiv(PHP_INT_MAX, $multiplier)) {
            return null;
        }
        if ($value < intdiv(PHP_INT_MIN, $multiplier)) {
            return null;
        }

        return $value * $multiplier;
    }

    public static function parseIntegerDecimal(string $value): ?int
    {
        return self::integerToDecimal(self::parseInteger($value));
    }

    public static function parseColorName(string $value): string
    {
        $name = self::colorName($value);
        if ($name === null) {
            throw new \InvalidArgumentException("Invalid Git color name: {$value}");
        }

        return $name;
    }

    public static function parseColorAttribute(string $value): string
    {
        $attribute = self::colorAttribute($value);
        if ($attribute === null) {
            throw new \InvalidArgumentException("Invalid Git color attribute: {$value}");
        }

        return $attribute['canonical'];
    }

    public static function normalizeColor(string $value): string
    {
        self::assertUtf8($value, 'color');

        $tokens = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($tokens === false) {
            throw new \InvalidArgumentException("Invalid Git color value: {$value}");
        }

        $foreground = null;
        $background = null;
        $attributes = [];
        foreach ($tokens as $token) {
            $name = self::colorName($token);
            if ($name !== null) {
                if ($foreground === null) {
                    $foreground = $name;
                    continue;
                }
                if ($background === null) {
                    $background = $name;
                    continue;
                }

                throw new \InvalidArgumentException("Invalid Git color value: {$value}");
            }

            $attribute = self::colorAttribute($token);
            if ($attribute === null) {
                throw new \InvalidArgumentException("Invalid Git color value: {$value}");
            }

            $attributes[$attribute['canonical']] = $attribute;
        }

        $parts = [];
        if ($foreground !== null) {
            $parts[] = $foreground;
        }
        if ($background !== null) {
            $parts[] = $background;
        }

        uasort(
            $attributes,
            static fn (array $left, array $right): int => $left['order'] <=> $right['order'],
        );
        foreach ($attributes as $attribute) {
            $parts[] = $attribute['canonical'];
        }

        return implode(' ', $parts);
    }

    private static function assertUtf8(string $value, string $kind): void
    {
        if (@preg_match('//u', $value) !== 1) {
            throw new \InvalidArgumentException("Invalid UTF-8 in Git {$kind} value");
        }
    }

    private static function parseInt64(string $value): ?int
    {
        if (preg_match('/^[+-]?[0-9]+$/D', $value) !== 1) {
            return null;
        }

        $negative = str_starts_with($value, '-');
        $digits = $value;
        if ($value[0] === '-' || $value[0] === '+') {
            $digits = substr($value, 1);
        }

        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }

        $limit = $negative ? self::INT64_MIN_ABS : self::INT64_MAX;
        if (self::exceedsDecimalMagnitude($digits, $limit)) {
            return null;
        }

        if ($negative && $digits === self::INT64_MIN_ABS) {
            return PHP_INT_MIN;
        }

        $integer = (int) $digits;

        return $negative ? -$integer : $integer;
    }

    private static function exceedsDecimalMagnitude(string $digits, string $limit): bool
    {
        $digitLength = strlen($digits);
        $limitLength = strlen($limit);
        if ($digitLength !== $limitLength) {
            return $digitLength > $limitLength;
        }

        return strcmp($digits, $limit) > 0;
    }

    private static function colorName(string $value): ?string
    {
        $bright = false;
        $name = $value;
        if (str_starts_with($value, 'bright')) {
            $bright = true;
            $name = substr($value, strlen('bright'));
        }

        if ($bright && ($name === 'normal' || $name === 'default')) {
            return null;
        }

        if ($bright && isset(self::COLOR_BRIGHT_NAMES[$name])) {
            return self::COLOR_BRIGHT_NAMES[$name];
        }

        if (!$bright && isset(self::COLOR_NAMES[$name])) {
            return self::COLOR_NAMES[$name];
        }

        if (preg_match('/^\+?[0-9]+$/D', $name) === 1) {
            $number = self::parseInt64($name);
            if ($number !== null && $number >= 0 && $number <= 255) {
                return (string) $number;
            }
        }

        if (str_starts_with($name, '#')) {
            $hex = substr($name, 1);
            if (strlen($hex) === 6 && ctype_xdigit($hex)) {
                return '#' . strtolower($hex);
            }
        }

        return null;
    }

    /**
     * @return ?array{canonical:string,order:int}
     */
    private static function colorAttribute(string $value): ?array
    {
        $inverted = false;
        $name = $value;
        if (str_starts_with($value, 'no-')) {
            $inverted = true;
            $name = substr($value, 3);
        } elseif (str_starts_with($value, 'no')) {
            $inverted = true;
            $name = substr($value, 2);
        }

        if ($inverted) {
            return self::COLOR_INVERTED_ATTRIBUTES[$name] ?? null;
        }

        return self::COLOR_ATTRIBUTES[$name] ?? null;
    }
}
