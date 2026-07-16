<?php

declare(strict_types=1);

use PortLibs\Syncthing\VersionVector;

return [
    'maps upstream version update ordering semantics' => static function (TestRunner $t): void {
        $vector = new VersionVector();
        $t->true($vector->isEmpty());

        $vector = $vector->update(42, 5);
        $t->same([42 => 5], $vector->toArray());

        $vector = $vector->update(36, 6);
        $t->same([36 => 6, 42 => 5], $vector->toArray());

        $vector = $vector->update(37, 7);
        $t->same([36 => 6, 37 => 7, 42 => 5], $vector->toArray());

        $vector = $vector->update(37, 1);
        $t->same([36 => 6, 37 => 8, 42 => 5], $vector->toArray());

        $vector = $vector->update(37, 100);
        $t->same([36 => 6, 37 => 100, 42 => 5], $vector->toArray());

        $vector = $vector->update(37, 50);
        $t->same([36 => 6, 37 => 101, 42 => 5], $vector->toArray());
    },
    'merges counters using upstream max-by-device rules' => static function (TestRunner $t): void {
        $empty = new VersionVector();
        $t->same([], $empty->merge($empty)->toArray());

        $left = VersionVector::fromCounters([22 => 1]);
        $right = VersionVector::fromCounters([42 => 1]);
        $t->same([22 => 1, 42 => 1], $left->merge($right)->toArray());
        $t->same([22 => 1, 42 => 1], $right->merge($left)->toArray());

        $base = VersionVector::fromCounters([22 => 1, 42 => 1]);
        $t->same([22 => 1, 23 => 2, 42 => 1], $base->merge(VersionVector::fromCounters([23 => 2]))->toArray());

        $a = VersionVector::fromCounters([10 => 11, 20 => 22, 30 => 33]);
        $b = VersionVector::fromCounters([5 => 55, 10 => 12, 15 => 155, 20 => 21, 25 => 255, 35 => 355]);
        $t->same([5 => 55, 10 => 12, 15 => 155, 20 => 22, 25 => 255, 30 => 33, 35 => 355], $a->merge($b)->toArray());
    },
    'compares version vectors including concurrent orderings' => static function (TestRunner $t): void {
        $empty = new VersionVector();
        $t->same(VersionVector::ORDER_EQUAL, $empty->compare(new VersionVector()));
        $t->same(VersionVector::ORDER_EQUAL, VersionVector::fromCounters([42 => 0])->compare($empty));
        $t->same(VersionVector::ORDER_EQUAL, VersionVector::fromCounters([42 => 0])->compare(VersionVector::fromCounters([43 => 0])));

        $t->same(VersionVector::ORDER_GREATER, VersionVector::fromCounters([42 => 2])->compare(VersionVector::fromCounters([42 => 1])));
        $t->same(VersionVector::ORDER_LESSER, VersionVector::fromCounters([42 => 1])->compare(VersionVector::fromCounters([42 => 2])));
        $t->same(VersionVector::ORDER_GREATER, VersionVector::fromCounters([42 => 2, 43 => 1])->compare(VersionVector::fromCounters([42 => 1])));
        $t->same(VersionVector::ORDER_LESSER, VersionVector::fromCounters([42 => 1])->compare(VersionVector::fromCounters([42 => 2, 43 => 1])));

        $t->same(VersionVector::ORDER_CONCURRENT_GREATER, VersionVector::fromCounters([42 => 2])->compare(VersionVector::fromCounters([43 => 1])));
        $t->same(VersionVector::ORDER_CONCURRENT_LESSER, VersionVector::fromCounters([43 => 1])->compare(VersionVector::fromCounters([42 => 2])));
        $t->same(VersionVector::ORDER_CONCURRENT_GREATER, VersionVector::fromCounters([22 => 23, 42 => 1])->compare(VersionVector::fromCounters([22 => 22, 42 => 2])));
        $t->same(VersionVector::ORDER_CONCURRENT_LESSER, VersionVector::fromCounters([22 => 21, 42 => 2])->compare(VersionVector::fromCounters([22 => 22, 42 => 1])));
        $t->same(VersionVector::ORDER_CONCURRENT_LESSER, VersionVector::fromCounters([22 => 21, 42 => 2, 43 => 1])->compare(VersionVector::fromCounters([20 => 1, 22 => 22, 42 => 1])));
    },
    'detects concurrent wordpress edits before merge' => static function (TestRunner $t): void {
        $base = new VersionVector();
        $mediaEdit = $base->update(101, 1700000000);
        $captionEdit = $base->update(202, 1700000000);

        $t->true($mediaEdit->concurrent($captionEdit));
        $t->same(VersionVector::ORDER_CONCURRENT_GREATER, $mediaEdit->compare($captionEdit));
        $t->same('101:1700000000', $mediaEdit->humanString());
        $t->same('0000000000000065:1700000000', (string) $mediaEdit);

        $merged = $mediaEdit->merge($captionEdit);
        $t->same([101 => 1700000000, 202 => 1700000000], $merged->toArray());
        $t->same(VersionVector::ORDER_GREATER, $merged->compare($mediaEdit));
        $t->same(VersionVector::ORDER_GREATER, $mediaEdit->update(101, 1700000001)->compare($mediaEdit));
        $t->same([101 => 1700000000], $merged->dropOthers(101)->toArray());
        $t->same(0, $merged->counter(404));
    },
    'rejects invalid vector counters' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => VersionVector::fromCounters([-1 => 1]));
        $t->throws(InvalidArgumentException::class, static fn () => VersionVector::fromCounters([1 => -1]));
        $t->throws(InvalidArgumentException::class, static fn () => VersionVector::fromCounters([['bad' => 1]]));
    },
];
