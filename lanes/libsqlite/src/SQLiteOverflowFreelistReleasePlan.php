<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteOverflowFreelistReleasePlan
{
    /**
     * @param list<array{source:string,pages:list<int>,count:int}> $sources
     * @param list<int> $releasedOverflowPages
     */
    public function __construct(
        public readonly array $sources,
        public readonly array $releasedOverflowPages,
        public readonly SQLiteFreelistFreePlan $freePlan,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        array $deleteResults,
        bool $secureDelete = false,
    ): self {
        if ($deleteResults === []) {
            throw new \InvalidArgumentException('SQLite overflow freelist release requires at least one delete result');
        }

        $sources = [];
        $releasedPages = [];
        $seenPages = [];
        foreach ($deleteResults as $index => $deleteResult) {
            if (!is_array($deleteResult)) {
                throw new \InvalidArgumentException('SQLite overflow freelist release delete results must be arrays');
            }

            $pages = $deleteResult['obsolete_overflow_page_numbers'] ?? null;
            if (!is_array($pages)) {
                throw new \InvalidArgumentException('SQLite overflow freelist release delete result is missing obsolete overflow pages');
            }

            $source = $deleteResult['source'] ?? self::inferSource($deleteResult, $index);
            if (!is_string($source) || $source === '') {
                throw new \InvalidArgumentException('SQLite overflow freelist release source labels must be non-empty strings');
            }

            $sourcePages = [];
            foreach (array_values($pages) as $pageNumber) {
                if (!is_int($pageNumber)) {
                    throw new \InvalidArgumentException('SQLite overflow freelist release page numbers must be integers');
                }
                if (isset($seenPages[$pageNumber])) {
                    throw new \InvalidArgumentException("SQLite overflow freelist release page {$pageNumber} appears more than once");
                }

                $seenPages[$pageNumber] = true;
                $sourcePages[] = $pageNumber;
                $releasedPages[] = $pageNumber;
            }

            $sources[] = [
                'source' => $source,
                'pages' => $sourcePages,
                'count' => count($sourcePages),
            ];
        }

        if ($releasedPages === []) {
            throw new \InvalidArgumentException('SQLite overflow freelist release has no obsolete overflow pages');
        }

        return new self(
            $sources,
            $releasedPages,
            $database->planPageFreeList($releasedPages, $secureDelete),
        );
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int,rowids?:list<int>,record_values?:list<list<mixed>>}> $chains
     */
    public static function fromOverflowChains(
        SQLiteDatabase $database,
        array $chains,
        bool $secureDelete = false,
    ): self {
        if ($chains === []) {
            throw new \InvalidArgumentException('SQLite overflow freelist release requires at least one overflow chain');
        }

        $deleteResults = [];
        foreach (array_values($chains) as $index => $chain) {
            if (!is_array($chain)) {
                throw new \InvalidArgumentException('SQLite overflow freelist release chains must be arrays');
            }

            $firstPage = $chain['first_page'] ?? null;
            if (!is_int($firstPage)) {
                throw new \InvalidArgumentException('SQLite overflow freelist release chain is missing a first overflow page');
            }

            $overflowPayloadBytes = $chain['overflow_payload_bytes'] ?? null;
            if (!is_int($overflowPayloadBytes)) {
                throw new \InvalidArgumentException('SQLite overflow freelist release chain is missing an overflow payload byte count');
            }

            $source = $chain['source'] ?? "overflow-chain-{$index}";
            if (!is_string($source) || $source === '') {
                throw new \InvalidArgumentException('SQLite overflow freelist release source labels must be non-empty strings');
            }

            $deleteResult = [
                'source' => $source,
                'obsolete_overflow_page_numbers' => SQLiteOverflowPage::pageNumbersFromDatabase(
                    $database,
                    $firstPage,
                    $overflowPayloadBytes,
                ),
            ];
            if (array_key_exists('rowids', $chain)) {
                $deleteResult['rowids'] = $chain['rowids'];
            }
            if (array_key_exists('record_values', $chain)) {
                $deleteResult['record_values'] = $chain['record_values'];
            }

            $deleteResults[] = $deleteResult;
        }

        return self::fromDeleteResults($database, $deleteResults, $secureDelete);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sources' => $this->sources,
            'released_overflow_pages' => $this->releasedOverflowPages,
            'released_overflow_page_count' => count($this->releasedOverflowPages),
            'free_plan' => $this->freePlan->toArray(),
        ];
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    private static function inferSource(array $deleteResult, int $index): string
    {
        if (array_key_exists('rowids', $deleteResult) || array_key_exists('rowid', $deleteResult)) {
            return "table-delete-{$index}";
        }
        if (array_key_exists('record_values', $deleteResult)) {
            return "index-delete-{$index}";
        }

        return "delete-{$index}";
    }
}
