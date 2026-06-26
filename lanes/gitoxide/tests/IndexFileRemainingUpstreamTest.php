<?php

declare(strict_types=1);

use PortLibs\Gitoxide\IndexCacheTree;
use PortLibs\Gitoxide\IndexFile;

$upstreamIndexRoot = dirname(__DIR__, 3) . '/.upstream-cache/gitoxide/gix-index';
$fixtureBytes = static function (string $relative) use ($upstreamIndexRoot): string {
    $path = $upstreamIndexRoot . '/' . $relative;
    $bytes = file_get_contents($path);
    if ($bytes === false) {
        throw new RuntimeException("Unable to read upstream index fixture: {$path}");
    }

    return $bytes;
};
$withRecomputedChecksum = static function (string $bytes): string {
    if (strlen($bytes) < 20) {
        throw new RuntimeException('Index fixture is too small to carry a checksum');
    }
    $payload = substr($bytes, 0, -20);
    $checksum = hex2bin(hash('sha1', $payload));
    if ($checksum === false) {
        throw new RuntimeException('Unable to build index checksum fixture');
    }

    return $payload . $checksum;
};
$decodeOutcome = static function (string $bytes): array {
    try {
        return [
            'ok' => true,
            'entries' => IndexFile::entriesFromBytes($bytes),
            'class' => null,
            'message' => null,
        ];
    } catch (Throwable $throwable) {
        return [
            'ok' => false,
            'entries' => null,
            'class' => $throwable::class,
            'message' => $throwable->getMessage(),
        ];
    }
};
$assertDecodeRejected = static function (TestRunner $t, array $outcome, string $messageNeedle): void {
    $t->same(false, $outcome['ok']);
    $t->same(InvalidArgumentException::class, $outcome['class']);
    $t->contains($messageNeedle, (string) $outcome['message']);
};
$assertThrowsMessage = static function (TestRunner $t, string $expectedClass, string $messageNeedle, callable $callback): void {
    try {
        $callback();
    } catch (Throwable $throwable) {
        if (!$throwable instanceof $expectedClass) {
            throw new RuntimeException('Expected ' . $expectedClass . ', got ' . $throwable::class);
        }
        $t->contains($messageNeedle, $throwable->getMessage());

        return;
    }

    throw new RuntimeException('Expected exception ' . $expectedClass . ' was not thrown');
};

return [
    'upstream file fuzzed entry counts are bounded before allocation' => static function (TestRunner $t) use (
        $fixtureBytes,
        $withRecomputedChecksum,
        $decodeOutcome,
        $assertDecodeRejected,
    ): void {
        $cases = [
            'index/file/fuzzed.rs::impossible_entry_count_is_rejected_before_any_large_allocation' => [
                'tests/fixtures/fuzzed/impossible-entry-count.git-index',
                'Index entry is truncated',
            ],
            'index/file/fuzzed.rs::oversized_entry_count_is_reported_without_allocating_absurd_memory' => [
                'tests/fixtures/fuzzed/oversized-entry-count-out-of-memory.git-index',
                'Index entry path length does not match its terminator',
            ],
            'index/fuzzed.rs::impossible_v4_entry_count_is_rejected_before_reserving' => [
                'fuzz/artifacts/index_file/oom-16fb9c25ef3ba2d2012810726a6b6be0c2181b2b',
                'Index entry is truncated',
            ],
        ];

        foreach ($cases as $upstreamTest => [$relativeFixture, $messageNeedle]) {
            $outcome = $decodeOutcome($withRecomputedChecksum($fixtureBytes($relativeFixture)));
            $assertDecodeRejected($t, $outcome, $messageNeedle);
        }
    },
    'upstream file fuzzed malformed extensions are accepted or rejected without php errors' => static function (TestRunner $t) use (
        $fixtureBytes,
        $withRecomputedChecksum,
        $decodeOutcome,
        $assertDecodeRejected,
    ): void {
        $cases = [
            'index/file/fuzzed.rs::untracked_cache_with_out_of_range_bitmap_bits_is_reported_without_panicking' => [
                'tests/fixtures/fuzzed/untracked-cache-out-of-range-bitmap.git-index',
                true,
                3,
                null,
            ],
            'index/file/fuzzed.rs::tree_extension_with_trailing_bytes_is_reported_without_panicking' => [
                'tests/fixtures/fuzzed/tree-extension-trailing-bytes.git-index',
                false,
                null,
                'Unsupported mandatory index extension',
            ],
            'index/file/fuzzed.rs::fsmonitor_extension_with_out_of_range_ewah_size_is_reported_without_panicking' => [
                'tests/fixtures/fuzzed/fsmonitor-invalid-ewah-size.git-index',
                false,
                null,
                'is truncated',
            ],
            'index/file/fuzzed.rs::untracked_cache_with_impossible_directory_counts_is_rejected_without_allocating_absurd_memory' => [
                'tests/fixtures/fuzzed/untracked-cache-impossible-directory-counts.git-index',
                false,
                null,
                'is truncated',
            ],
            'index/file/fuzzed.rs::untracked_cache_with_truncated_ewah_is_reported_without_panicking' => [
                'tests/fixtures/fuzzed/untracked-cache-truncated-ewah.git-index',
                false,
                null,
                'Index extension header is truncated',
            ],
        ];

        foreach ($cases as $upstreamTest => [$relativeFixture, $shouldDecode, $entryCount, $messageNeedle]) {
            $outcome = $decodeOutcome($withRecomputedChecksum($fixtureBytes($relativeFixture)));
            if ($shouldDecode) {
                $t->same(true, $outcome['ok'], $upstreamTest);
                $t->same($entryCount, count($outcome['entries']), $upstreamTest);
                continue;
            }

            $assertDecodeRejected($t, $outcome, $messageNeedle);
        }
    },
    'upstream file fuzzed tree extension large entry count verifies but can round trip' => static function (TestRunner $t) use (
        $fixtureBytes,
        $withRecomputedChecksum,
        $assertThrowsMessage,
    ): void {
        $bytes = $withRecomputedChecksum($fixtureBytes('tests/fixtures/fuzzed/tree-extension-entry-count-overflow.git-index'));
        $entries = IndexFile::entriesFromBytes($bytes);
        $tree = IndexFile::cacheTreeFromBytes($bytes);
        if (!$tree instanceof IndexCacheTree) {
            throw new RuntimeException('Expected TREE extension fixture to decode');
        }

        $t->same(0, count($entries));
        $t->same('', $tree->name);
        $t->same(547345820, $tree->numEntries);
        $assertThrowsMessage(
            $t,
            RuntimeException::class,
            "TREE entry '' declared 547345820 entries, but the index only contains 0 entries",
            static fn () => $tree->verifyEntryCounts(count($entries)),
        );

        $roundTrip = IndexFile::bytesFor($entries, $tree);
        $roundTripTree = IndexFile::cacheTreeFromBytes($roundTrip);
        $t->same(2, IndexFile::versionFromBytes($roundTrip));
        $t->same($tree->numEntries, $roundTripTree?->numEntries);
        $t->same($tree->bodyBytes(), $roundTripTree?->bodyBytes());
    },
    'upstream file fuzzed malformed entry padding is rejected without php errors' => static function (TestRunner $t) use (
        $fixtureBytes,
        $withRecomputedChecksum,
        $decodeOutcome,
        $assertDecodeRejected,
    ): void {
        $outcome = $decodeOutcome($withRecomputedChecksum($fixtureBytes('tests/fixtures/fuzzed/entry-padding-overflow.git-index')));

        $assertDecodeRejected($t, $outcome, 'Index entry path length does not match its terminator');
    },
    'upstream index fuzzed promoted artifacts decode or reject deterministically' => static function (TestRunner $t) use (
        $fixtureBytes,
        $withRecomputedChecksum,
        $decodeOutcome,
        $assertDecodeRejected,
    ): void {
        $cases = [
            'index/fuzzed.rs::malformed_tree_extension_is_ignored_instead_of_panicking' => [
                'fuzz/artifacts/index_file/crash-92d6786251cf2b3e13cacc9eda01864724aa6b4b',
                false,
                null,
                'is truncated',
            ],
            'index/fuzzed.rs::malformed_fsmonitor_extension_is_ignored_instead_of_panicking' => [
                'fuzz/artifacts/index_file/crash-6fe328e670c3ca54a4dac7a5c0dc1e51501cf1d9',
                true,
                6,
                null,
            ],
            'index/fuzzed.rs::malformed_untracked_cache_extension_is_ignored_instead_of_panicking' => [
                'fuzz/artifacts/index_file/crash-b3dc19d67c36fbc5fc4b4f5729df92911dd3a7d5',
                true,
                3,
                null,
            ],
            'index/fuzzed.rs::impossible_untracked_cache_directory_counts_are_rejected_before_reserving' => [
                'fuzz/artifacts/index_file/oom-08fa49e4b1e2e3267dbd9adb27a1926003e39418',
                false,
                null,
                'Index extension header is truncated',
            ],
            'index/fuzzed.rs::malformed_entry_padding_is_rejected_instead_of_panicking' => [
                'fuzz/artifacts/index_file/crash-f5b1c2a323c8b3d9275ce5223cae534d4af0f8e0',
                false,
                null,
                'Index v2 entry cannot contain extended flags',
            ],
            'index/fuzzed.rs::malformed_untracked_cache_bitmap_is_rejected_instead_of_panicking' => [
                'fuzz/artifacts/index_file/crash-41a7b73c58e644d4a605336b387172f6c9101c5b',
                false,
                null,
                'is truncated',
            ],
            'index/fuzzed.rs::malformed_entry_padding_with_untracked_cache_is_rejected_instead_of_panicking' => [
                'fuzz/artifacts/index_file/crash-8ee7f3c7bc2d72e3d1fcff7b67b493a8403297e2',
                false,
                null,
                'Index v2 entry cannot contain extended flags',
            ],
        ];

        foreach ($cases as $upstreamTest => [$relativeFixture, $shouldDecode, $entryCount, $messageNeedle]) {
            $outcome = $decodeOutcome($withRecomputedChecksum($fixtureBytes($relativeFixture)));
            if ($shouldDecode) {
                $t->same(true, $outcome['ok'], $upstreamTest);
                $t->same($entryCount, count($outcome['entries']), $upstreamTest);
                continue;
            }

            $assertDecodeRejected($t, $outcome, $messageNeedle);
        }
    },
    'upstream file fuzzed unpromoted artifacts do not produce php errors while parsing' => static function (TestRunner $t) use (
        $fixtureBytes,
        $withRecomputedChecksum,
        $decodeOutcome,
    ): void {
        $artifacts = [
            'crash-183d7e59664e77ac486de5ef39a3d223d6235e83',
            'oom-240461da86da2f14cc4554c7a77726285a0ac9be',
            'oom-71f5c01e4874bfe4ab5e8d40107fcdabafb6287f',
            'crash-f8884670b4ff8bba25d7278aff725beb1dec4aa4',
        ];

        foreach ($artifacts as $artifact) {
            $outcome = $decodeOutcome($withRecomputedChecksum($fixtureBytes('fuzz/artifacts/index_file/' . $artifact)));
            if ($outcome['ok']) {
                $t->same(true, is_array($outcome['entries']), $artifact);
                continue;
            }

            $t->same(InvalidArgumentException::class, $outcome['class'], $artifact);
        }
    },
];
