<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class Blake2s
{
    private const BLOCK_BYTES = 64;
    private const OUT_BYTES = 32;
    private const MASK_32 = 0xffffffff;

    /**
     * @var list<int>
     */
    private const IV = [
        0x6A09E667, 0xBB67AE85, 0x3C6EF372, 0xA54FF53A,
        0x510E527F, 0x9B05688C, 0x1F83D9AB, 0x5BE0CD19,
    ];

    /**
     * @var list<list<int>>
     */
    private const SIGMA = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
        [14, 10, 4, 8, 9, 15, 13, 6, 1, 12, 0, 2, 11, 7, 5, 3],
        [11, 8, 12, 0, 5, 2, 15, 13, 10, 14, 3, 6, 7, 1, 9, 4],
        [7, 9, 3, 1, 13, 12, 11, 14, 2, 6, 5, 10, 4, 0, 15, 8],
        [9, 0, 5, 7, 2, 4, 10, 15, 14, 1, 11, 12, 6, 8, 3, 13],
        [2, 12, 6, 10, 0, 11, 8, 3, 4, 13, 7, 5, 15, 14, 1, 9],
        [12, 5, 1, 15, 14, 13, 4, 10, 0, 7, 6, 3, 9, 2, 8, 11],
        [13, 11, 7, 14, 12, 1, 3, 9, 5, 0, 15, 4, 8, 6, 2, 10],
        [6, 15, 14, 9, 11, 3, 0, 8, 12, 2, 13, 7, 1, 4, 10, 5],
        [10, 2, 8, 4, 7, 6, 1, 5, 15, 11, 9, 14, 3, 12, 13, 0],
    ];

    public static function hash(string $input): string
    {
        $h = self::IV;
        $h[0] ^= 0x01010000 ^ self::OUT_BYTES;

        $length = strlen($input);
        $offset = 0;
        $counter = 0;

        while ($length - $offset > self::BLOCK_BYTES) {
            $counter += self::BLOCK_BYTES;
            self::compress($h, substr($input, $offset, self::BLOCK_BYTES), $counter, false);
            $offset += self::BLOCK_BYTES;
        }

        $lastBlock = substr($input, $offset);
        $counter += strlen($lastBlock);
        self::compress($h, str_pad($lastBlock, self::BLOCK_BYTES, "\0"), $counter, true);

        $output = '';
        foreach ($h as $word) {
            $output .= pack('V', $word);
        }

        return substr($output, 0, self::OUT_BYTES);
    }

    public static function hashHex(string $input): string
    {
        return bin2hex(self::hash($input));
    }

    /**
     * @param list<int> $h
     */
    private static function compress(array &$h, string $block, int $counter, bool $last): void
    {
        /** @var list<int> $m */
        $m = array_values(unpack('V16', $block));
        $v = array_merge($h, self::IV);

        $v[12] ^= $counter & self::MASK_32;
        $v[13] ^= ($counter >> 32) & self::MASK_32;
        if ($last) {
            $v[14] ^= self::MASK_32;
        }

        for ($round = 0; $round < 10; $round++) {
            $s = self::SIGMA[$round];
            self::g($v, 0, 4, 8, 12, $m[$s[0]], $m[$s[1]]);
            self::g($v, 1, 5, 9, 13, $m[$s[2]], $m[$s[3]]);
            self::g($v, 2, 6, 10, 14, $m[$s[4]], $m[$s[5]]);
            self::g($v, 3, 7, 11, 15, $m[$s[6]], $m[$s[7]]);
            self::g($v, 0, 5, 10, 15, $m[$s[8]], $m[$s[9]]);
            self::g($v, 1, 6, 11, 12, $m[$s[10]], $m[$s[11]]);
            self::g($v, 2, 7, 8, 13, $m[$s[12]], $m[$s[13]]);
            self::g($v, 3, 4, 9, 14, $m[$s[14]], $m[$s[15]]);
        }

        for ($i = 0; $i < 8; $i++) {
            $h[$i] = ($h[$i] ^ $v[$i] ^ $v[$i + 8]) & self::MASK_32;
        }
    }

    /**
     * @param list<int> $v
     */
    private static function g(array &$v, int $a, int $b, int $c, int $d, int $x, int $y): void
    {
        $v[$a] = self::add32($v[$a], $v[$b], $x);
        $v[$d] = self::rotr32($v[$d] ^ $v[$a], 16);
        $v[$c] = self::add32($v[$c], $v[$d]);
        $v[$b] = self::rotr32($v[$b] ^ $v[$c], 12);
        $v[$a] = self::add32($v[$a], $v[$b], $y);
        $v[$d] = self::rotr32($v[$d] ^ $v[$a], 8);
        $v[$c] = self::add32($v[$c], $v[$d]);
        $v[$b] = self::rotr32($v[$b] ^ $v[$c], 7);
    }

    private static function add32(int ...$values): int
    {
        $sum = 0;
        foreach ($values as $value) {
            $sum = ($sum + $value) & self::MASK_32;
        }

        return $sum;
    }

    private static function rotr32(int $word, int $bits): int
    {
        $word &= self::MASK_32;

        return (($word >> $bits) | (($word << (32 - $bits)) & self::MASK_32)) & self::MASK_32;
    }
}
