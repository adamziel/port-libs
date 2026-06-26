<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitBitmap;
use PortLibs\Gitoxide\GitHashtable;

$setBits = static function (GitBitmap $bitmap): ?array {
    $actual = [];
    $result = $bitmap->forEachSetBit(static function (int $idx) use (&$actual): bool {
        $actual[] = $idx;
        return true;
    });

    return $result === null ? null : $actual;
};

$replaceUInt64 = static function (string $bytes, int $offset, int $hi, int $lo): string {
    return substr($bytes, 0, $offset) . pack('N2', $hi, $lo) . substr($bytes, $offset + 8);
};

$assertEmptyRest = static function (TestRunner $t, string $rest): void {
    $t->same('', $rest, 'fixture should be fully consumed');
};

return [
    'gix-bitmap ewah runaway_run_length_is_rejected' => static function (TestRunner $t) use ($assertEmptyRest): void {
        $fixture = dirname(__DIR__, 3) . '/.upstream-cache/gitoxide/gix-bitmap/fuzz/artifacts/ewah/slow-unit-ac817962d1a6c123d4d1f73860f5b779423ed171';
        [$bitmap, $rest] = GitBitmap::decode((string) file_get_contents($fixture));

        $assertEmptyRest($t, $rest);
        $t->same(null, $bitmap->forEachSetBit(static fn (int $idx): bool => true));
    },
    'gix-bitmap ewah non_zero_padding_bits_in_last_literal_word_are_rejected' => static function (TestRunner $t) use ($assertEmptyRest, $replaceUInt64): void {
        $data = GitBitmap::fromBits([false])->writeTo();
        $literalWordOffset = 4 + 4 + 8;
        $literal = unpack('Nhi/Nlo', substr($data, $literalWordOffset, 8));
        if ($literal === false) {
            throw new RuntimeException('Unable to read literal word');
        }
        $data = $replaceUInt64($data, $literalWordOffset, $literal['hi'] | (1 << 1), $literal['lo']);
        [$bitmap, $rest] = GitBitmap::decode($data);

        $assertEmptyRest($t, $rest);
        $t->same(null, $bitmap->forEachSetBit(static fn (int $idx): bool => true));
    },
    'gix-bitmap ewah literal_only_bitmaps_preserve_all_set_bits' => static function (TestRunner $t) use ($assertEmptyRest, $setBits): void {
        $fixtures = [
            [],
            [false],
            [true],
            [true, false, true, false, true],
            array_map(static fn (int $idx): bool => $idx % 3 === 0, range(0, 63)),
            array_map(static fn (int $idx): bool => $idx === 0 || $idx === 63 || $idx === 64, range(0, 64)),
            array_map(static fn (int $idx): bool => $idx === 1 || $idx === 64 || $idx === 129, range(0, 129)),
        ];

        foreach ($fixtures as $bits) {
            $bitmap = GitBitmap::fromBits($bits);
            $expected = [];
            foreach ($bits as $idx => $bit) {
                if ($bit) {
                    $expected[] = $idx;
                }
            }

            [$decoded, $rest] = GitBitmap::decode($bitmap->writeTo());
            $assertEmptyRest($t, $rest);
            $t->same(count($bits), $decoded->numBits(), 'fixture should preserve the declared bit length');
            $t->same($expected, $setBits($decoded), 'iteration should report exactly the set bits from the source bitmap');
        }
    },
    'gix-bitmap ewah zero_padding_bits_in_last_literal_word_are_accepted' => static function (TestRunner $t) use ($setBits): void {
        $bitmap = GitBitmap::fromBits([true, false, true]);

        $t->same([0, 2], $setBits($bitmap), 'iteration should ignore zero padding bits');
    },
    'gix-hashtable hasher write_uses_the_first_8_bytes_verbatim_assuming_a_secure_hash_as_input' => static function (TestRunner $t): void {
        $hasher = GitHashtable::hasher();
        $hasher->write(GitHashtable::uint64ToNativeEndianBytes(0x0a0a9f2a7b7e0367));

        $t->same(0x0a0a9f2a7b7e0367, $hasher->finish());
    },
    'gix-hashtable hasher non_write_methods_panic' => static function (TestRunner $t): void {
        $hasher = GitHashtable::hasher();

        $t->throws(LogicException::class, static fn () => $hasher->writeUsize(4));
    },
];
