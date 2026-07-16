<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class Mt19937
{
    private const N = 624;
    private const M = 397;
    private const MATRIX_A = 0x9908b0df;
    private const UPPER_MASK = 0x80000000;
    private const LOWER_MASK = 0x7fffffff;
    private const UINT32_MASK = 0xffffffff;

    /**
     * @var list<int>
     */
    private array $state = [];
    private int $index = self::N;

    public function __construct(int $seed = 5489)
    {
        $this->seed($seed);
    }

    public function seed(int $seed): void
    {
        $this->state = [self::uint32($seed)];
        for ($i = 1; $i < self::N; $i++) {
            $previous = $this->state[$i - 1];
            $this->state[$i] = self::uint32(1812433253 * ($previous ^ ($previous >> 30)) + $i);
        }

        $this->index = self::N;
    }

    public function nextUint32(): int
    {
        if ($this->index >= self::N) {
            $this->twist();
        }

        $y = $this->state[$this->index++];
        $y ^= $y >> 11;
        $y ^= ($y << 7) & 0x9d2c5680;
        $y ^= ($y << 15) & 0xefc60000;
        $y ^= $y >> 18;

        return self::uint32($y);
    }

    public function nextModulo(int $modulus): int
    {
        if ($modulus <= 0) {
            throw new \InvalidArgumentException('modulus must be positive');
        }

        return $this->nextUint32() % $modulus;
    }

    private function twist(): void
    {
        for ($i = 0; $i < self::N; $i++) {
            $y = ($this->state[$i] & self::UPPER_MASK) | ($this->state[($i + 1) % self::N] & self::LOWER_MASK);
            $next = $this->state[($i + self::M) % self::N] ^ ($y >> 1);
            if (($y & 1) !== 0) {
                $next ^= self::MATRIX_A;
            }

            $this->state[$i] = self::uint32($next);
        }

        $this->index = 0;
    }

    private static function uint32(int $value): int
    {
        return $value & self::UINT32_MASK;
    }
}
