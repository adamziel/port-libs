<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class SparseCheckoutOptions
{
    public const MODE_INCLUDE_DIRECTORIES_STORE_INCLUDED_ENTRIES_AND_EXCLUDED_DIRS = 'include-directories-store-included-entries-and-excluded-dirs';
    public const MODE_INCLUDE_DIRECTORIES_STORE_ALL_ENTRIES_SKIP_UNMATCHED = 'include-directories-store-all-entries-skip-unmatched';
    public const MODE_INCLUDE_BY_IGNORE_PATTERN_STORE_ALL_ENTRIES_SKIP_UNMATCHED = 'include-by-ignore-pattern-store-all-entries-skip-unmatched';
    public const MODE_DISABLED = 'disabled';

    public function __construct(
        public readonly bool $sparseCheckout = false,
        public readonly bool $directoryPatternsOnly = false,
        public readonly bool $writeSparseIndex = false,
    ) {
    }

    public function sparseMode(): string
    {
        if (!$this->sparseCheckout) {
            return self::MODE_DISABLED;
        }

        if (!$this->directoryPatternsOnly) {
            return self::MODE_INCLUDE_BY_IGNORE_PATTERN_STORE_ALL_ENTRIES_SKIP_UNMATCHED;
        }

        if ($this->writeSparseIndex) {
            return self::MODE_INCLUDE_DIRECTORIES_STORE_INCLUDED_ENTRIES_AND_EXCLUDED_DIRS;
        }

        return self::MODE_INCLUDE_DIRECTORIES_STORE_ALL_ENTRIES_SKIP_UNMATCHED;
    }
}
