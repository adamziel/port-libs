<?php

declare(strict_types=1);

use PortLibs\Quadrable\Blake2s;
use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\QuadbStore;
use PortLibs\Quadrable\SparseTree;

return [
    'integer keys round-trip across upstream boundary examples' => static function (TestRunner $t): void {
        foreach ([0, 1, 2, 10, 63, 64, 65, 1024, (1 << 20) - 5, Key::MAX_INTEGER] as $number) {
            $t->same($number, Key::fromInteger($number)->toInteger(), 'round-trip failed for ' . $number);
        }
    },
    'integer key overflow fails before native php arithmetic can corrupt the key' => static function (TestRunner $t): void {
        foreach ([Key::MAX_INTEGER + 1, Key::MAX_INTEGER + 2] as $number) {
            try {
                Key::fromInteger($number);
            } catch (InvalidArgumentException $exception) {
                $t->contains('int range exceeded', $exception->getMessage());
                continue;
            }

            throw new RuntimeException('Expected integer key overflow rejection for ' . $number);
        }

        $overRange = Key::null();
        foreach ([0, 1, 2, 3, 4] as $bit) {
            $overRange->setBit($bit, 1);
        }

        $t->throws(RuntimeException::class, static fn () => $overRange->toInteger());
    },
    'integer key zero encodes as the null key' => static function (TestRunner $t): void {
        $t->same(str_repeat('0', 64), Key::fromInteger(0)->hex());
    },
    'integer keys support upstream truncated hash suffixes for wordpress meta rows' => static function (TestRunner $t): void {
        $base = Key::fromInteger(42);
        $suffix23 = substr(Blake2s::hash('wp_postmeta:_thumbnail_id'), -23);
        $key = Key::fromIntegerAndHash(42, $suffix23);

        $t->same(substr($base->bytes(), 0, 9) . $suffix23, $key->bytes());
        $t->same($key->hex(), Key::fromIntegerAndHash(42, $suffix23)->hex());

        $suffix31 = str_repeat("\x7a", 31);
        $wide = Key::fromIntegerAndHash(42, $suffix31);
        $t->same(substr($base->bytes(), 0, 1) . $suffix31, $wide->bytes());

        $t->throws(InvalidArgumentException::class, static fn () => Key::fromIntegerAndHash(42, str_repeat('a', 22)));
        $t->throws(InvalidArgumentException::class, static fn () => Key::fromIntegerAndHash(42, str_repeat('a', 32)));

        $metaKey = static fn (int $postId, string $metaKey): Key => Key::fromIntegerAndHash(
            $postId,
            substr(Blake2s::hash($metaKey), -23)
        );

        $tree = new SparseTree();
        $tree->change()
            ->putKey($metaKey(42, '_thumbnail_id'), 'wp_postmeta:42:_thumbnail_id=7')
            ->putKey($metaKey(42, '_edit_lock'), 'wp_postmeta:42:_edit_lock=1716400000')
            ->putKey($metaKey(42, '_wp_page_template'), 'wp_postmeta:42:_wp_page_template=templates/full-width.html')
            ->putKey($metaKey(43, '_thumbnail_id'), 'wp_postmeta:43:_thumbnail_id=8')
            ->apply();

        $prefix42 = substr(Key::fromInteger(42)->bytes(), 0, 9);
        $values = [];
        for ($iterator = $tree->iterate(Key::fromIntegerAndHash(42, str_repeat("\0", 23))); !$iterator->atEnd(); $iterator->next()) {
            $entry = $iterator->get();
            $t->true($entry !== null);
            if (substr($entry->key()->bytes(), 0, 9) !== $prefix42) {
                break;
            }

            $values[] = $entry->value();
        }

        sort($values, SORT_STRING);
        $t->same([
            'wp_postmeta:42:_edit_lock=1716400000',
            'wp_postmeta:42:_thumbnail_id=7',
            'wp_postmeta:42:_wp_page_template=templates/full-width.html',
        ], $values);
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
    'mineHash prefix search maps upstream quadb bit predicate deterministically' => static function (TestRunner $t): void {
        $result = Key::mineHashPrefix('101010', 1, 200);

        $t->same([
            'input' => '146',
            'hashHex' => 'aba72397aa8d459aaf3190fd24625ca5cf09fe3127aa1fb40325eb13c57f1c89',
            'attempts' => 146,
        ], $result);
        $t->true(Key::hashMatchesBitPrefix('146', '101010'));
        $t->same(false, Key::hashMatchesBitPrefix('145', '101010'));
        $t->same("146 -> aba72397aa8d459aaf3190fd24625ca5cf09fe3127aa1fb40325eb13c57f1c89\n", QuadbStore::mineHashText('101010', 1, 200));

        $t->throws(InvalidArgumentException::class, static fn () => Key::mineHashPrefix('10x'));
        $t->throws(InvalidArgumentException::class, static fn () => Key::mineHashPrefix(str_repeat('0', 257)));
        $t->throws(RuntimeException::class, static fn () => Key::mineHashPrefix('11111111', 1, 1));
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
