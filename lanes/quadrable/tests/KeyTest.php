<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;

return [
    'integer keys round-trip across upstream boundary examples' => static function (TestRunner $t): void {
        foreach ([0, 1, 2, 10, 63, 64, 65, 1024, (1 << 20) - 5, PHP_INT_MAX - 2] as $number) {
            $t->same($number, Key::fromInteger($number)->toInteger(), 'round-trip failed for ' . $number);
        }
    },
    'integer key zero encodes as the null key' => static function (TestRunner $t): void {
        $t->same(str_repeat('0', 64), Key::fromInteger(0)->hex());
    },
    'key bit access follows most significant bit order' => static function (TestRunner $t): void {
        $key = Key::null();
        $key->setBit(0, 1);
        $key->setBit(9, 1);
        $t->same('8040000000000000000000000000000000000000000000000000000000000000', $key->hex());
        $t->same(1, $key->getBit(0));
        $t->same(1, $key->getBit(9));
        $t->same(0, $key->getBit(10));
    },
    'keepPrefixBits zeroes the suffix in place' => static function (TestRunner $t): void {
        $key = Key::max();
        $key->keepPrefixBits(10);
        $t->same('ffc0000000000000000000000000000000000000000000000000000000000000', $key->hex());
    },
    'non integer-format keys are rejected by toInteger' => static function (TestRunner $t): void {
        $key = Key::fromHex(str_repeat('00', 16) . '01' . str_repeat('00', 15));
        $t->throws(RuntimeException::class, static fn () => $key->toInteger());
    },
];

