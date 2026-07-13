<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitBitmap
{
    private const WORD_BITS = 64;
    private const LOW_WORD_BITS = 32;
    private const UINT32_MASK = 0xffffffff;

    /**
     * @param list<array{hi:int,lo:int}> $words
     */
    private function __construct(
        private readonly int $numBits,
        private readonly array $words,
        private readonly int $rlwOffset,
    ) {
    }

    /**
     * @param list<bool> $bits
     */
    public static function fromBits(array $bits): self
    {
        $numBits = count($bits);
        if ($numBits > self::UINT32_MASK) {
            throw new \InvalidArgumentException('Bitmap bit length exceeds u32::MAX');
        }

        $literalWords = [];
        foreach ($bits as $idx => $bit) {
            if ($bit !== true) {
                continue;
            }

            $wordIndex = intdiv($idx, self::WORD_BITS);
            $bitIndex = $idx % self::WORD_BITS;
            $literalWords[$wordIndex] ??= ['hi' => 0, 'lo' => 0];
            if ($bitIndex < self::LOW_WORD_BITS) {
                $literalWords[$wordIndex]['lo'] |= 1 << $bitIndex;
            } else {
                $literalWords[$wordIndex]['hi'] |= 1 << ($bitIndex - self::LOW_WORD_BITS);
            }
        }

        $literalCount = intdiv($numBits + self::WORD_BITS - 1, self::WORD_BITS);
        $words = [[
            'hi' => $literalCount << 1,
            'lo' => 0,
        ]];
        for ($idx = 0; $idx < $literalCount; $idx++) {
            $words[] = $literalWords[$idx] ?? ['hi' => 0, 'lo' => 0];
        }

        return new self($numBits, $words, 0);
    }

    /**
     * @return array{0:self,1:string}
     */
    public static function decode(string $bytes): array
    {
        $offset = 0;
        $numBits = self::readUInt32($bytes, $offset, 'eof reading amount of bits');
        $wordCount = self::readUInt32($bytes, $offset, 'eof reading chunk length');
        if ($wordCount > intdiv(PHP_INT_MAX, 8)) {
            throw new \InvalidArgumentException('EWAH word count is too large');
        }

        $requiredWordBytes = $wordCount * 8;
        if (strlen($bytes) - $offset < $requiredWordBytes) {
            throw new \InvalidArgumentException('eof while reading bit data');
        }

        $words = [];
        for ($idx = 0; $idx < $wordCount; $idx++) {
            $parts = unpack('Nhi/Nlo', substr($bytes, $offset, 8));
            if ($parts === false) {
                throw new \InvalidArgumentException('eof while reading bit data');
            }
            $words[] = [
                'hi' => $parts['hi'],
                'lo' => $parts['lo'],
            ];
            $offset += 8;
        }

        $rlwOffset = self::readUInt32($bytes, $offset, 'eof while reading run length width');

        return [new self($numBits, $words, $rlwOffset), substr($bytes, $offset)];
    }

    public function writeTo(): string
    {
        $wordCount = count($this->words);
        if ($wordCount > self::UINT32_MASK) {
            throw new \RuntimeException('bit word count exceeds u32::MAX');
        }
        if ($this->rlwOffset > self::UINT32_MASK) {
            throw new \RuntimeException('run length word offset exceeds u32::MAX');
        }

        $bytes = pack('N2', $this->numBits, $wordCount);
        foreach ($this->words as $word) {
            $bytes .= pack('N2', $word['hi'], $word['lo']);
        }

        return $bytes . pack('N', $this->rlwOffset);
    }

    public function numBits(): int
    {
        return $this->numBits;
    }

    /**
     * Calls `$callback($index)` for each set bit. Returns `null` when the EWAH
     * stream is structurally impossible, matching upstream's `None`.
     *
     * @param callable(int):mixed $callback
     */
    public function forEachSetBit(callable $callback): ?bool
    {
        $index = 0;
        $cursor = 0;
        $wordCount = count($this->words);

        while ($cursor < $wordCount) {
            $word = $this->words[$cursor];
            $cursor++;

            $runningLength = self::runningLength($word) * self::WORD_BITS;
            $end = $index + $runningLength;
            if ($end < $index || $end > $this->numBits) {
                return null;
            }

            if (self::runBitIsSet($word)) {
                while ($index < $end) {
                    if ($callback($index) === null) {
                        return null;
                    }
                    $index++;
                }
            } else {
                $index = $end;
            }

            $literalWords = self::literalWordCount($word);
            for ($literalIdx = 0; $literalIdx < $literalWords; $literalIdx++) {
                if ($cursor >= $wordCount) {
                    return null;
                }
                $literal = $this->words[$cursor];
                $cursor++;

                $remaining = $this->numBits - $index;
                if ($remaining <= 0) {
                    return null;
                }

                $bitsInWord = min(self::WORD_BITS, $remaining);
                if ($bitsInWord < self::WORD_BITS && self::hasBitsAtOrAbove($literal, $bitsInWord)) {
                    return null;
                }

                for ($bitIndex = 0; $bitIndex < $bitsInWord; $bitIndex++) {
                    if (self::wordBitIsSet($literal, $bitIndex) && $callback($index) === null) {
                        return null;
                    }
                    $index++;
                }
            }
        }

        return true;
    }

    /**
     * @param array{hi:int,lo:int} $word
     */
    private static function runBitIsSet(array $word): bool
    {
        return ($word['lo'] & 1) === 1;
    }

    /**
     * @param array{hi:int,lo:int} $word
     */
    private static function runningLength(array $word): int
    {
        return ($word['lo'] >> 1) | (($word['hi'] & 1) << 31);
    }

    /**
     * @param array{hi:int,lo:int} $word
     */
    private static function literalWordCount(array $word): int
    {
        return $word['hi'] >> 1;
    }

    /**
     * @param array{hi:int,lo:int} $word
     */
    private static function wordBitIsSet(array $word, int $bitIndex): bool
    {
        if ($bitIndex < self::LOW_WORD_BITS) {
            return ($word['lo'] & (1 << $bitIndex)) !== 0;
        }

        return ($word['hi'] & (1 << ($bitIndex - self::LOW_WORD_BITS))) !== 0;
    }

    /**
     * @param array{hi:int,lo:int} $word
     */
    private static function hasBitsAtOrAbove(array $word, int $bitIndex): bool
    {
        if ($bitIndex <= 0) {
            return $word['hi'] !== 0 || $word['lo'] !== 0;
        }
        if ($bitIndex < self::LOW_WORD_BITS) {
            $allowedLo = (1 << $bitIndex) - 1;
            $invalidLo = $word['lo'] & (self::UINT32_MASK ^ $allowedLo);

            return $word['hi'] !== 0 || $invalidLo !== 0;
        }
        if ($bitIndex === self::LOW_WORD_BITS) {
            return $word['hi'] !== 0;
        }

        $allowedHi = (1 << ($bitIndex - self::LOW_WORD_BITS)) - 1;
        $invalidHi = $word['hi'] & (self::UINT32_MASK ^ $allowedHi);

        return $invalidHi !== 0;
    }

    private static function readUInt32(string $bytes, int &$offset, string $error): int
    {
        if (strlen($bytes) - $offset < 4) {
            throw new \InvalidArgumentException($error);
        }

        $parts = unpack('Nvalue', substr($bytes, $offset, 4));
        if ($parts === false) {
            throw new \InvalidArgumentException($error);
        }
        $offset += 4;

        return $parts['value'];
    }
}
