<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class RemoteProgress
{
    private const MAX_PERCENTAGE = 4294967295;

    private function __construct(
        public readonly string $raw,
        public readonly string $action,
        public readonly ?int $percent,
        public readonly ?int $step,
        public readonly ?int $max,
    ) {
    }

    public static function fromText(string $text): ?self
    {
        $actionEnd = strpos($text, ':');
        if ($actionEnd === false || $actionEnd === 0) {
            return null;
        }

        $offset = $actionEnd;
        $percent = self::nextOptionalPercentage($text, $offset);
        $step = self::nextOptionalNumber($text, $offset);
        $max = self::nextOptionalNumber($text, $offset);

        if ($percent === null && $step === null && $max === null) {
            return null;
        }

        return new self(
            $text,
            substr($text, 0, $actionEnd),
            $percent,
            $step,
            $max,
        );
    }

    private static function nextOptionalPercentage(string $text, int &$offset): ?int
    {
        $before = $offset;
        self::skipUntilDigitOrEnd($text, $offset);
        $number = self::parseNumber($text, $offset);
        if ($number === null) {
            return null;
        }

        if (($text[$offset] ?? null) === '%') {
            ++$offset;

            if ($number > self::MAX_PERCENTAGE) {
                return null;
            }

            return $number;
        }

        $offset = $before;

        return null;
    }

    private static function nextOptionalNumber(string $text, int &$offset): ?int
    {
        self::skipUntilDigitOrEnd($text, $offset);

        return self::parseNumber($text, $offset);
    }

    private static function skipUntilDigitOrEnd(string $text, int &$offset): void
    {
        $length = strlen($text);
        while ($offset < $length && !self::isAsciiDigit($text[$offset])) {
            ++$offset;
        }
    }

    private static function parseNumber(string $text, int &$offset): ?int
    {
        $length = strlen($text);
        $start = $offset;
        while ($offset < $length && self::isAsciiDigit($text[$offset])) {
            ++$offset;
        }

        if ($start === $offset) {
            return null;
        }

        $digits = substr($text, $start, $offset - $start);
        $normalized = ltrim($digits, '0');
        if ($normalized === '') {
            return 0;
        }

        $max = (string) PHP_INT_MAX;
        if (strlen($normalized) > strlen($max)) {
            return null;
        }
        if (strlen($normalized) === strlen($max) && strcmp($normalized, $max) > 0) {
            return null;
        }

        return (int) $normalized;
    }

    private static function isAsciiDigit(string $char): bool
    {
        return $char >= '0' && $char <= '9';
    }
}
