<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitNegotiate;

return [
    'gix-negotiate window_size initial_value_without_previous_window_size' => static function (TestRunner $t): void {
        $t->same(16, GitNegotiate::windowSize(false, null));
        $t->same(16, GitNegotiate::windowSize(true, null));
    },
    'gix-negotiate window_size transport_is_stateless' => static function (TestRunner $t): void {
        $windowSize = GitNegotiate::windowSize(true, null);

        foreach ([32, 64, 128, 256, 512, 1024, 2048, 4096, 8192, 16384, 18022, 19824] as $expected) {
            $windowSize = GitNegotiate::windowSize(true, $windowSize);
            $t->same($expected, $windowSize);
        }
    },
    'gix-negotiate window_size transport_is_not_stateless' => static function (TestRunner $t): void {
        $windowSize = GitNegotiate::windowSize(false, null);

        foreach ([32, 64, 96] as $expected) {
            $windowSize = GitNegotiate::windowSize(false, $windowSize);
            $t->same($expected, $windowSize);
        }

        $windowSize = 4;
        foreach ([8, 16, 32, 64, 96] as $expected) {
            $windowSize = GitNegotiate::windowSize(false, $windowSize);
            $t->same($expected, $windowSize);
        }
    },
    'gix-negotiate size_of_entry' => static function (TestRunner $t): void {
        $actual = GitNegotiate::estimatedCommitEntrySize();
        $sha1 = 56;
        $sha256Extra = 16;
        $expected = $sha1 + $sha256Extra;

        $t->true(
            GitNegotiate::commitEntrySizeOk($actual, $expected),
            "we may keep a lot of these, so let's not let them grow unnoticed: {$actual} <~ {$expected}"
        );
    },
];
