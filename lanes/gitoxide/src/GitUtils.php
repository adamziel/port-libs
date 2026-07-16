<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitUtils
{
    private const BACKOFF_MAX_MS = 1000;

    /**
     * Per-mille jitter factors matching gix-utils' 750..=1250 range, kept deterministic for tests.
     *
     * @var list<int>
     */
    private const DETERMINISTIC_JITTER = [750, 1000, 1250, 875, 1125, 950, 1050, 800, 1200, 925, 1075];

    private function __construct()
    {
    }

    /**
     * @return list<int> Milliseconds yielded by gix_utils::backoff::Quadratic.
     */
    public static function quadraticBackoff(int $count, bool $randomized = false): array
    {
        if ($count < 0) {
            throw new \InvalidArgumentException('Backoff count must be non-negative');
        }

        $values = [];
        $sequence = self::quadraticSequence($randomized);

        for ($i = 0; $i < $count; $i++) {
            $values[] = $sequence->current();
            $sequence->next();
        }

        return $values;
    }

    /**
     * @return list<int> Milliseconds yielded until the accumulated wait exceeds the requested time.
     */
    public static function quadraticBackoffUntilNoRemaining(int $timeMs, bool $randomized = false): array
    {
        if ($timeMs < 0) {
            throw new \InvalidArgumentException('Backoff time must be non-negative');
        }

        $values = [];
        $elapsed = 0;

        foreach (self::quadraticSequence($randomized) as $durationMs) {
            $values[] = $durationMs;
            $elapsed += $durationMs;

            if ($elapsed > $timeMs) {
                break;
            }
        }

        return $values;
    }

    public static function toUnsigned(string $bytes, string $type = 'usize'): int
    {
        return self::toUnsignedWithRadix($bytes, 10, $type);
    }

    public static function toUnsignedWithRadix(string $bytes, int $radix, string $type = 'usize'): int
    {
        self::validateRadix($radix);
        [, $max] = self::integerBounds($type);

        if ($bytes === '') {
            throw new GitUtilsIntegerParseException('empty');
        }

        $result = 0;
        $length = strlen($bytes);

        for ($i = 0; $i < $length; $i++) {
            $digit = self::digitValue($bytes[$i], $radix);
            if ($digit === null) {
                throw new GitUtilsIntegerParseException('invalid_digit');
            }

            if ($result > intdiv($max - $digit, $radix)) {
                throw new GitUtilsIntegerParseException('overflow');
            }

            $result = ($result * $radix) + $digit;
        }

        return $result;
    }

    public static function toSigned(string $bytes, string $type = 'i64'): int
    {
        return self::toSignedWithRadix($bytes, 10, $type);
    }

    public static function toSignedWithRadix(string $bytes, int $radix, string $type = 'i64'): int
    {
        self::validateRadix($radix);

        if ($bytes === '') {
            throw new GitUtilsIntegerParseException('empty');
        }

        $first = $bytes[0];
        if ($first === '+') {
            return self::toUnsignedWithRadix(substr($bytes, 1), $radix, $type);
        }

        if ($first !== '-') {
            return self::toUnsignedWithRadix($bytes, $radix, $type);
        }

        $digits = substr($bytes, 1);
        if ($digits === '') {
            throw new GitUtilsIntegerParseException('empty');
        }

        [$min] = self::integerBounds($type);
        $result = 0;
        $length = strlen($digits);

        for ($i = 0; $i < $length; $i++) {
            $digit = self::digitValue($digits[$i], $radix);
            if ($digit === null) {
                throw new GitUtilsIntegerParseException('invalid_digit');
            }

            if ($result < intdiv($min + $digit, $radix)) {
                throw new GitUtilsIntegerParseException('underflow');
            }

            $result = ($result * $radix) - $digit;
            if ($result < $min) {
                throw new GitUtilsIntegerParseException('underflow');
            }
        }

        return $result;
    }

    public static function buffers(): GitUtilsBuffers
    {
        return new GitUtilsBuffers();
    }

    /**
     * @return array{value: string, copied: bool}
     */
    public static function decompose(string $value): array
    {
        $normalized = self::normalizeUnicode($value, 'd');

        return ['value' => $normalized, 'copied' => $normalized !== $value];
    }

    /**
     * @return array{value: string, copied: bool}
     */
    public static function precompose(string $value): array
    {
        $normalized = self::normalizeUnicode($value, 'c');

        return ['value' => $normalized, 'copied' => $normalized !== $value];
    }

    /**
     * @return \Generator<int, int>
     */
    private static function quadraticSequence(bool $randomized): \Generator
    {
        $multiplier = 1;
        $exponent = 1;
        $index = 0;

        while (true) {
            yield $randomized ? self::deterministicJitter($multiplier, $index) : $multiplier;

            $multiplier += (2 * $exponent) + 1;
            if ($multiplier > self::BACKOFF_MAX_MS) {
                $multiplier = self::BACKOFF_MAX_MS;
            } else {
                $exponent++;
            }
            $index++;
        }
    }

    private static function deterministicJitter(int $backoffMs, int $index): int
    {
        $factor = self::DETERMINISTIC_JITTER[$index % count(self::DETERMINISTIC_JITTER)];
        $newValue = intdiv($factor * $backoffMs, 1000);

        return $newValue === 0 ? $backoffMs : $newValue;
    }

    private static function validateRadix(int $radix): void
    {
        if ($radix < 2 || $radix > 36) {
            throw new \InvalidArgumentException("radix must lie in the range 2..=36, found {$radix}");
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function integerBounds(string $type): array
    {
        return match (strtolower($type)) {
            'u8' => [0, 255],
            'u64', 'usize' => [0, PHP_INT_MAX],
            'i32' => [-2147483648, 2147483647],
            'i64', 'isize' => [-PHP_INT_MAX - 1, PHP_INT_MAX],
            default => throw new \InvalidArgumentException("Unsupported integer type {$type}"),
        };
    }

    private static function digitValue(string $char, int $radix): ?int
    {
        $byte = ord($char);

        if ($byte >= 48 && $byte <= 57) {
            $value = $byte - 48;
        } elseif ($byte >= 65 && $byte <= 90) {
            $value = $byte - 55;
        } elseif ($byte >= 97 && $byte <= 122) {
            $value = $byte - 87;
        } else {
            return null;
        }

        return $value < $radix ? $value : null;
    }

    private static function normalizeUnicode(string $value, string $form): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalizerForm = $form === 'd' ? \Normalizer::FORM_D : \Normalizer::FORM_C;
            $normalized = \Normalizer::normalize($value, $normalizerForm);
            if (is_string($normalized)) {
                return $normalized;
            }

            throw new \InvalidArgumentException('Unable to normalize unicode string');
        }

        return match ($form) {
            'd' => str_replace("\xC3\xA4", "a\xCC\x88", $value),
            'c' => str_replace("a\xCC\x88", "\xC3\xA4", $value),
            default => $value,
        };
    }
}

final class GitUtilsIntegerParseException extends \InvalidArgumentException
{
    public function __construct(private readonly string $kind)
    {
        parent::__construct(match ($kind) {
            'empty' => 'cannot parse integer without digits',
            'invalid_digit' => 'invalid digit found in slice',
            'overflow' => 'number too large to fit in target type',
            'underflow' => 'number too small to fit in target type',
            default => 'integer parse error',
        });
    }

    public function kind(): string
    {
        return $this->kind;
    }
}

final class GitUtilsBuffers
{
    private string $src = '';
    private string $dest = '';

    public function clear(): void
    {
        $this->src = '';
        $this->dest = '';
    }

    public function swap(): void
    {
        [$this->src, $this->dest] = [$this->dest, $this->src];
    }

    public function useForeignSrc(string $src): GitUtilsBuffersWithForeignSource
    {
        $this->clear();

        return new GitUtilsBuffersWithForeignSource($this, $src);
    }

    /**
     * @return array{src: string, dest: string}
     */
    public function srcAndDest(): array
    {
        return ['src' => $this->src, 'dest' => $this->dest];
    }

    public function source(): string
    {
        return $this->src;
    }

    public function destination(): string
    {
        return $this->dest;
    }

    public function appendDestination(string $bytes): void
    {
        $this->dest .= $bytes;
    }

    public function clearDestination(): void
    {
        $this->dest = '';
    }
}

final class GitUtilsBuffersWithForeignSource
{
    private ?string $readOnlySource;

    public function __construct(private readonly GitUtilsBuffers $buffers, string $readOnlySource)
    {
        $this->readOnlySource = $readOnlySource;
    }

    public function readOnlySource(): ?string
    {
        return $this->readOnlySource;
    }

    public function sourceBuffer(): string
    {
        return $this->buffers->source();
    }

    /**
     * @return array{src: string, dest: string}
     */
    public function srcAndDest(): array
    {
        return [
            'src' => $this->readOnlySource ?? $this->buffers->source(),
            'dest' => $this->buffers->destination(),
        ];
    }

    public function appendDestination(string $bytes): void
    {
        $this->buffers->appendDestination($bytes);
    }

    public function swap(): void
    {
        $this->readOnlySource = null;
        $this->buffers->swap();
        $this->buffers->clearDestination();
    }
}
