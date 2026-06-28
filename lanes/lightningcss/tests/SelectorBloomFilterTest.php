<?php

declare(strict_types=1);

use PortLibs\LightningCSS\SelectorBloomFilter;

return [
    'selector bloom filter maps upstream create and insert behavior' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d selectors/bloom.rs::create_and_insert_some_stuff lines 284-344.
        $filter = new SelectorBloomFilter();
        $hash = static fn (int $value): int => SelectorBloomFilter::hashString((string) $value);

        $t->same(0, SelectorBloomFilter::ARRAY_SIZE % 8, 'upstream BloomStorageBool array-size multiple');
        $t->true($filter->isZeroed(), 'upstream BloomFilter::new starts zeroed');

        for ($i = 0; $i < 1000; $i++) {
            $filter->insertHash($hash($i));
        }

        $missedInserted = 0;
        for ($i = 0; $i < 1000; $i++) {
            if (!$filter->mightContainHash($hash($i))) {
                $missedInserted++;
            }
        }
        $t->same(0, $missedInserted, 'upstream BloomFilter inserted hashes are retained');

        $falsePositives = 0;
        for ($i = 1001; $i < 2000; $i++) {
            if ($filter->mightContainHash($hash($i))) {
                $falsePositives++;
            }
        }
        $t->true($falsePositives < 190, 'upstream BloomFilter false-positive rate remains below 19%');

        for ($i = 0; $i < 100; $i++) {
            $filter->removeHash($hash($i));
        }

        $missedSurvivors = 0;
        for ($i = 100; $i < 1000; $i++) {
            if (!$filter->mightContainHash($hash($i))) {
                $missedSurvivors++;
            }
        }
        $t->same(0, $missedSurvivors, 'upstream BloomFilter removal keeps other inserted hashes');

        $removedStillPossible = 0;
        for ($i = 0; $i < 100; $i++) {
            if ($filter->mightContainHash($hash($i))) {
                $removedStillPossible++;
            }
        }
        $t->true($removedStillPossible < 20, 'upstream BloomFilter removed-hash false positives remain below 20%');

        $filter->clear();

        $postClearPossible = 0;
        for ($i = 0; $i < 2000; $i++) {
            if ($filter->mightContainHash($hash($i))) {
                $postClearPossible++;
            }
        }
        $t->same(0, $postClearPossible, 'upstream BloomFilter::clear removes all hash candidates');
        $t->true($filter->isZeroed(), 'upstream BloomFilter::clear zeroes storage');
    },
];
