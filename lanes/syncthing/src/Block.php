<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class Block
{
    /**
     * Syncthing's hard-coded SHA-256 values for full blocks of zeroes.
     *
     * @var array<int, string>
     */
    private const ZERO_HASH_BY_SIZE = [
        128 << 10 => 'fa43239bcee7b97ca62f007cc68487560a39e19f74f3dde7486db3f98df8e471',
        256 << 10 => '8a39d2abd3999ab73c34db2476849cddf303ce389b35826850f9a700589b4a90',
        512 << 10 => '07854d2fef297a06ba81685e660c332de36d5d18d546927d30daad6d7fda1541',
        1 << 20 => '30e14955ebf1352266dc2ff8067e68104607e750abb9d3b36582b8af909fcb58',
        2 << 20 => '5647f05ec18958947d32874eeb788fa396a05d0bab7c1b71f112ceb7e9b31eee',
        4 << 20 => 'bb9f8df61474d25e71fa00722318cd387396ca1736605e1248821cc0de3d3af8',
        8 << 20 => '2daeb1f36095b44b318410b3f4e8b5d989dcc7bb023d1426c492dab0a3053e74',
        16 << 20 => '080acf35a507ac9849cfcba47dc2ad83e01b75663a516279c8b9d243b719643e',
    ];

    public function __construct(
        public readonly int $offset,
        public readonly int $size,
        public readonly string $hashHex,
    ) {
    }

    public function isAllZeroes(): bool
    {
        $hashHex = self::ZERO_HASH_BY_SIZE[$this->size] ?? null;

        return $hashHex !== null && hash_equals($hashHex, strtolower($this->hashHex));
    }
}
