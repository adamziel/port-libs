<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitDate
{
    public function __construct(
        public readonly int $seconds,
        public readonly int $offset,
    ) {
    }

    public static function parse(string $input): self
    {
        $time = self::parseCompactIso8601($input) ?? self::parseFlexibleIso8601($input);
        if ($time === null) {
            throw new \InvalidArgumentException("Unsupported git date format: {$input}");
        }

        return $time;
    }

    public function format(string $format): string
    {
        return match ($format) {
            GitDateFormat::SHORT => $this->formatFixed('Y-m-d'),
            GitDateFormat::UNIX => (string) $this->seconds,
            GitDateFormat::RAW => $this->formatRaw(),
            GitDateFormat::ISO8601 => $this->formatFixed('Y-m-d H:i:s') . ' ' . $this->formatOffset(false),
            GitDateFormat::ISO8601_STRICT => $this->formatFixed('Y-m-d\TH:i:s') . $this->formatOffset(true),
            GitDateFormat::RFC2822 => $this->formatFixed('D, d M Y H:i:s') . ' ' . $this->formatOffset(false),
            GitDateFormat::GIT_RFC2822 => $this->formatFixed('D, j M Y H:i:s') . ' ' . $this->formatOffset(false),
            GitDateFormat::GITOXIDE => $this->formatFixed('D M d Y H:i:s') . ' ' . $this->formatOffset(false),
            GitDateFormat::DEFAULT => $this->formatFixed('D M j H:i:s Y') . ' ' . $this->formatOffset(false),
            default => throw new \InvalidArgumentException("Unsupported git date format kind: {$format}"),
        };
    }

    public function formatOrUnix(string $format): string
    {
        try {
            return $this->format($format);
        } catch (\InvalidArgumentException) {
            return (string) $this->seconds;
        }
    }

    private function formatRaw(): string
    {
        $absolute = abs($this->offset);
        $hours = intdiv($absolute, 3600);
        if ($hours > 99) {
            throw new \InvalidArgumentException('Cannot represent offsets larger than +-9900');
        }

        $minutes = intdiv($absolute - ($hours * 3600), 60);

        return $this->seconds . ' ' . ($this->offset < 0 ? '-' : '+') . sprintf('%02d%02d', $hours, $minutes);
    }

    private function formatFixed(string $phpFormat): string
    {
        $this->assertFixedOffsetRepresentable();

        return gmdate($phpFormat, $this->seconds + $this->offset);
    }

    private function formatOffset(bool $withColon): string
    {
        $this->assertFixedOffsetRepresentable();

        $absolute = abs($this->offset);
        $hours = intdiv($absolute, 3600);
        $minutes = intdiv($absolute - ($hours * 3600), 60);
        $separator = $withColon ? ':' : '';

        return ($this->offset < 0 ? '-' : '+') . sprintf('%02d%s%02d', $hours, $separator, $minutes);
    }

    private function assertFixedOffsetRepresentable(): void
    {
        $absolute = abs($this->offset);
        if ($absolute > (23 * 3600) + (59 * 60) || ($absolute % 60) !== 0) {
            throw new \InvalidArgumentException('Cannot represent offset as a fixed timezone');
        }
    }

    private static function parseCompactIso8601(string $input): ?self
    {
        $input = trim($input);
        $tPosition = strpos($input, 'T');
        if ($tPosition !== 8) {
            return null;
        }

        $datePart = substr($input, 0, 8);
        if (!self::isAscii($datePart) || preg_match('/^\d{8}$/', $datePart) !== 1) {
            return null;
        }

        $year = (int) substr($datePart, 0, 4);
        $month = (int) substr($datePart, 4, 2);
        $day = (int) substr($datePart, 6, 2);

        [$timePart, $offsetPart] = self::splitTimeAndOffset(substr($input, 9));
        $dotPosition = strpos($timePart, '.');
        if ($dotPosition !== false) {
            $timePart = substr($timePart, 0, $dotPosition);
        }

        $time = self::parseTimeComponent($timePart);
        $offset = self::parseFlexibleOffset($offsetPart);
        if ($time === null || $offset === null) {
            return null;
        }

        return self::fromLocal($year, $month, $day, $time['hour'], $time['minute'], $time['second'], $offset);
    }

    private static function parseFlexibleIso8601(string $input): ?self
    {
        $input = trim($input);
        if (strlen($input) < 11) {
            return null;
        }

        $datePart = substr($input, 0, 10);
        if (
            !self::isAscii($datePart)
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePart) !== 1
        ) {
            return null;
        }

        $separator = $input[10];
        if ($separator !== 'T' && $separator !== ' ') {
            return null;
        }

        $year = (int) substr($datePart, 0, 4);
        $month = (int) substr($datePart, 5, 2);
        $day = (int) substr($datePart, 8, 2);

        [$timePart, $offsetPart] = self::splitTimeAndOffset(substr($input, 11));
        $dotPosition = strpos($timePart, '.');
        if ($dotPosition !== false) {
            $timePart = substr($timePart, 0, $dotPosition);
        }

        $time = self::parseTimeComponent($timePart);
        $offset = self::parseFlexibleOffset($offsetPart);
        if ($time === null || $offset === null) {
            return null;
        }

        return self::fromLocal($year, $month, $day, $time['hour'], $time['minute'], $time['second'], $offset);
    }

    /**
     * @return array{hour: int, minute: int, second: int}|null
     */
    private static function parseTimeComponent(string $time): ?array
    {
        $time = trim($time);
        if (!self::isAscii($time)) {
            return null;
        }

        if (str_contains($time, ':')) {
            $parts = explode(':', $time);
            $hour = self::parseUnsignedInteger($parts[0] ?? '');
            $minute = array_key_exists(1, $parts) ? self::parseUnsignedInteger($parts[1]) : 0;
            $second = array_key_exists(2, $parts) ? self::parseUnsignedInteger($parts[2]) : 0;
        } else {
            [$hourText, $minuteText, $secondText] = match (strlen($time)) {
                2 => [$time, '0', '0'],
                4 => [substr($time, 0, 2), substr($time, 2, 2), '0'],
                6 => [substr($time, 0, 2), substr($time, 2, 2), substr($time, 4, 2)],
                default => [null, null, null],
            };
            if ($hourText === null) {
                return null;
            }

            $hour = self::parseUnsignedInteger($hourText);
            $minute = self::parseUnsignedInteger($minuteText);
            $second = self::parseUnsignedInteger($secondText);
        }

        if ($hour === null || $minute === null || $second === null) {
            return null;
        }

        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return ['hour' => $hour, 'minute' => $minute, 'second' => $second];
    }

    private static function parseFlexibleOffset(string $offset): ?int
    {
        $offset = trim($offset);
        if ($offset === '' || $offset === 'Z') {
            return 0;
        }

        if (!self::isAscii($offset)) {
            return null;
        }

        $signText = $offset[0] ?? '';
        if ($signText !== '+' && $signText !== '-') {
            return null;
        }

        $rest = str_replace(':', '', substr($offset, 1));
        if (strlen($rest) === 2) {
            $hoursText = $rest;
            $minutesText = '0';
        } elseif (strlen($rest) === 4) {
            $hoursText = substr($rest, 0, 2);
            $minutesText = substr($rest, 2, 2);
        } else {
            return null;
        }

        $hours = self::parseUnsignedInteger($hoursText);
        $minutes = self::parseUnsignedInteger($minutesText);
        if ($hours === null || $minutes === null || $hours > 23 || $minutes > 59) {
            return null;
        }

        $seconds = ($hours * 3600) + ($minutes * 60);

        return $signText === '-' ? -$seconds : $seconds;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitTimeAndOffset(string $input): array
    {
        $input = trim($input);
        if (str_ends_with($input, 'Z')) {
            return [substr($input, 0, -1), 'Z'];
        }

        $offsetStart = null;
        for ($index = strlen($input) - 1; $index >= 0; $index--) {
            $char = $input[$index];
            if (($char === '+' || $char === '-') && $index >= 5) {
                $after = substr($input, $index + 1);
                if ($after !== '' && ctype_digit($after[0])) {
                    $offsetStart = $index;
                    break;
                }
            }
        }

        $spacePosition = strrpos($input, ' ');
        if ($spacePosition !== false && $spacePosition > 5) {
            $potentialOffset = trim(substr($input, $spacePosition + 1));
            if (
                str_starts_with($potentialOffset, '+')
                || str_starts_with($potentialOffset, '-')
                || $potentialOffset === 'Z'
            ) {
                return [substr($input, 0, $spacePosition), $potentialOffset];
            }
        }

        if ($offsetStart !== null) {
            return [substr($input, 0, $offsetStart), substr($input, $offsetStart)];
        }

        return [$input, ''];
    }

    private static function fromLocal(
        int $year,
        int $month,
        int $day,
        int $hour,
        int $minute,
        int $second,
        int $offset,
    ): ?self {
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        $localAsUtc = gmmktime($hour, $minute, $second, $month, $day, $year);
        if ($localAsUtc === false) {
            return null;
        }

        return new self($localAsUtc - $offset, $offset);
    }

    private static function parseUnsignedInteger(string $value): ?int
    {
        return preg_match('/^\d+$/', $value) === 1 ? (int) $value : null;
    }

    private static function isAscii(string $value): bool
    {
        return preg_match('/^[\x00-\x7F]*$/', $value) === 1;
    }
}
