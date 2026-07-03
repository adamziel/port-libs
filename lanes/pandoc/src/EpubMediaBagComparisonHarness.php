<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubMediaBagComparisonHarness
{
    private const DEFAULT_MAX_EXAMPLES = 12;
    private const VERDICT = 'media-bag-comparison-not-full-epub-parity';
    private const CLAIM = 'Compares local PHP EPUB reader image-resource output with upstream Tests.Readers.EPUB media-bag expectations by normalized media path, MIME type, and byte size; no upstream Haskell runner, AST parity, writer parity, or full EPUB feature parity is asserted.';

    /**
     * @param array{limit?: int, maxExamples?: int, readerCases?: list<array<string, mixed>>, fixtureBase?: string} $options
     * @return array<string, mixed>
     */
    public function run(string $upstreamRoot, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));

        if (!is_dir($upstreamRoot)) {
            return $this->skippedReport($upstreamRoot, 'upstream-cache-missing');
        }

        $readerCases = is_array($options['readerCases'] ?? null)
            ? $options['readerCases']
            : $this->readerCasesFromUpstreamRoot($upstreamRoot);
        $fixtureBase = is_string($options['fixtureBase'] ?? null)
            ? rtrim((string) $options['fixtureBase'], DIRECTORY_SEPARATOR)
            : $upstreamRoot . '/test';

        if ($readerCases === []) {
            return $this->skippedReport($upstreamRoot, 'upstream-reader-test-missing-or-empty');
        }

        $totalCaseCount = count($readerCases);
        if ($limit > 0) {
            $readerCases = array_slice($readerCases, 0, $limit);
        }

        $epubParsedCount = 0;
        $expectedItemCount = 0;
        $actualItemCount = 0;
        $matchCount = 0;
        $parseFailures = [];
        $mismatches = [];

        foreach ($readerCases as $case) {
            $fixture = is_string($case['epub'] ?? null) ? $case['epub'] : '';
            $caseName = is_string($case['name'] ?? null) ? $case['name'] : $fixture;
            $expectedBag = is_array($case['expectedBag'] ?? null) ? $this->normalizeBag($case['expectedBag']) : [];
            $expectedItemCount += count($expectedBag);
            $actualResult = $this->readActualBag($fixtureBase . '/' . $fixture);

            if (!$actualResult['ok']) {
                $parseFailures[] = [
                    'case' => $caseName,
                    'fixture' => $fixture,
                    'error' => $actualResult['error'],
                ];
                continue;
            }

            ++$epubParsedCount;
            $actualBag = $this->normalizeBag($actualResult['bag']);
            $actualItemCount += count($actualBag);
            if ($actualBag === $expectedBag) {
                ++$matchCount;
                continue;
            }

            if (count($mismatches) < $maxExamples) {
                $mismatches[] = [
                    'case' => $caseName,
                    'fixture' => $fixture,
                    'firstDifference' => $this->firstDifference($expectedBag, $actualBag) ?? 'unknown-media-bag-difference',
                    'expectedBag' => $expectedBag,
                    'actualBag' => $actualBag,
                ];
            }
        }

        $comparedCaseCount = count($readerCases);
        $mismatchCount = $epubParsedCount - $matchCount;
        $parseFailureCount = count($parseFailures);

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-epub-media-bag',
            'status' => 'completed',
            'skipped' => false,
            'reason' => null,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'epub-upstream-mediabag-comparison',
            'upstreamRoot' => $upstreamRoot,
            'fixtureBase' => $fixtureBase,
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalCaseCount' => $totalCaseCount,
            'comparedCaseCount' => $comparedCaseCount,
            'epubParsedCount' => $epubParsedCount,
            'parseFailureCount' => $parseFailureCount,
            'expectedMediaItemCount' => $expectedItemCount,
            'actualMediaItemCount' => $actualItemCount,
            'mediaBagMatchCount' => $matchCount,
            'mediaBagMismatchCount' => $mismatchCount,
            'mediaBagMatchPercent' => self::percent($matchCount, $comparedCaseCount),
            'mediaBagParityStatus' => self::mediaBagParityStatus($parseFailureCount, $mismatchCount, $comparedCaseCount),
            'parseFailures' => array_slice($parseFailures, 0, $maxExamples),
            'mismatchComparisons' => $mismatches,
            'orderedRemainingGaps' => self::orderedRemainingGaps(
                true,
                $comparedCaseCount,
                $parseFailureCount,
                $matchCount,
                $mismatchCount
            ),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function formatReport(array $report): string
    {
        $lines = [
            'Pandoc EPUB media-bag comparison: ' . (string) ($report['status'] ?? 'unknown'),
            'Verdict: ' . (string) ($report['verdict'] ?? self::VERDICT),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'upstreamRoot=' . (string) ($report['upstreamRoot'] ?? ''),
        ];

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'reason=' . (string) ($report['reason'] ?? 'unknown');
            $lines = self::appendOrderedRemainingGaps($lines, $report);

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $lines[] = sprintf(
            'cases: total=%d compared=%d parsed=%d parseFailures=%d',
            (int) ($report['totalCaseCount'] ?? 0),
            (int) ($report['comparedCaseCount'] ?? 0),
            (int) ($report['epubParsedCount'] ?? 0),
            (int) ($report['parseFailureCount'] ?? 0),
        );
        $lines[] = sprintf(
            'mediaBag: matches=%d (%s) mismatches=%d expectedItems=%d actualItems=%d status=%s',
            (int) ($report['mediaBagMatchCount'] ?? 0),
            self::formatPercent($report['mediaBagMatchPercent'] ?? null),
            (int) ($report['mediaBagMismatchCount'] ?? 0),
            (int) ($report['expectedMediaItemCount'] ?? 0),
            (int) ($report['actualMediaItemCount'] ?? 0),
            (string) ($report['mediaBagParityStatus'] ?? 'unknown'),
        );

        $mismatches = $report['mismatchComparisons'] ?? [];
        if (is_array($mismatches) && $mismatches !== []) {
            $lines[] = 'mismatchExamples:';
            foreach ($mismatches as $mismatch) {
                if (!is_array($mismatch)) {
                    continue;
                }
                $lines[] = '- ' . (string) ($mismatch['fixture'] ?? 'unknown')
                    . ': ' . (string) ($mismatch['firstDifference'] ?? 'unknown');
            }
        }

        $lines = self::appendOrderedRemainingGaps($lines, $report);

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredMediaBagParity(array $report, int $requiredCaseCount): bool
    {
        if ($requiredCaseCount < 0) {
            throw new \InvalidArgumentException('Required EPUB media-bag parity count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['totalCaseCount'] ?? -1) === $requiredCaseCount
            && (int) ($report['comparedCaseCount'] ?? -1) === $requiredCaseCount
            && (int) ($report['epubParsedCount'] ?? -1) === $requiredCaseCount
            && (int) ($report['parseFailureCount'] ?? -1) === 0
            && (int) ($report['mediaBagMatchCount'] ?? -1) === $requiredCaseCount
            && (int) ($report['mediaBagMismatchCount'] ?? -1) === 0
            && ($report['mediaBagParityStatus'] ?? null) === 'media-bag-equality-observed-not-runner-parity';
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredMediaBagItemCount(array $report, int $requiredItemCount): bool
    {
        if ($requiredItemCount < 0) {
            throw new \InvalidArgumentException('Required EPUB media-bag item count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['expectedMediaItemCount'] ?? -1) === $requiredItemCount
            && (int) ($report['actualMediaItemCount'] ?? -1) === $requiredItemCount;
    }

    /**
     * @return array<string, mixed>
     */
    private function skippedReport(string $upstreamRoot, string $reason): array
    {
        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-epub-media-bag',
            'status' => 'skipped',
            'skipped' => true,
            'reason' => $reason,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'epub-upstream-mediabag-comparison',
            'upstreamRoot' => $upstreamRoot,
            'fixtureBase' => null,
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalCaseCount' => 0,
            'comparedCaseCount' => 0,
            'epubParsedCount' => 0,
            'parseFailureCount' => 0,
            'expectedMediaItemCount' => 0,
            'actualMediaItemCount' => 0,
            'mediaBagMatchCount' => 0,
            'mediaBagMismatchCount' => 0,
            'mediaBagMatchPercent' => null,
            'mediaBagParityStatus' => 'not-evaluated-source-directory-unavailable',
            'parseFailures' => [],
            'mismatchComparisons' => [],
            'orderedRemainingGaps' => self::orderedRemainingGaps(false, 0, 0, 0, 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readerCasesFromUpstreamRoot(string $upstreamRoot): array
    {
        $readerTestPath = $upstreamRoot . '/test/Tests/Readers/EPUB.hs';
        if (!is_file($readerTestPath)) {
            return [];
        }

        return EpubUpstreamReaderEvidence::parseReaderCasesFromSource((string) file_get_contents($readerTestPath));
    }

    /**
     * @return array{ok: bool, bag: list<array{path: string, mime: string, size: int}>, error: ?string}
     */
    private function readActualBag(string $path): array
    {
        if (!is_file($path)) {
            return ['ok' => false, 'bag' => [], 'error' => 'missing-epub-fixture'];
        }
        if (!class_exists(\ZipArchive::class)) {
            return ['ok' => false, 'bag' => [], 'error' => 'php-ziparchive-unavailable'];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return ['ok' => false, 'bag' => [], 'error' => 'unable-to-open-epub'];
        }

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta', []);
            if (!is_array($meta)) {
                return ['ok' => false, 'bag' => [], 'error' => 'epub-reader-meta-missing'];
            }

            $rootfile = is_string($meta['epubRootfile'] ?? null) ? $meta['epubRootfile'] : '';
            $rootDirectory = $this->dirname($rootfile);
            $manifestByPath = [];
            foreach (is_array($meta['epubManifestItems'] ?? null) ? $meta['epubManifestItems'] : [] as $item) {
                if (!is_array($item) || !is_string($item['path'] ?? null)) {
                    continue;
                }
                $manifestByPath[(string) $item['path']] = $item;
            }

            $bag = [];
            foreach (is_array($meta['epubMediaBagResources'] ?? null) ? $meta['epubMediaBagResources'] : [] as $resource) {
                if (!is_string($resource) || $resource === '') {
                    continue;
                }
                $manifestItem = is_array($manifestByPath[$resource] ?? null) ? $manifestByPath[$resource] : [];
                $bag[] = [
                    'path' => $this->pathRelativeToRootDirectory($resource, $rootDirectory),
                    'mime' => strtolower((string) ($manifestItem['mediaType'] ?? $this->mimeFromPath($resource))),
                    'size' => $this->zipEntrySize($zip, $resource),
                ];
            }

            return ['ok' => true, 'bag' => $this->normalizeBag($bag), 'error' => null];
        } catch (\Throwable $throwable) {
            return ['ok' => false, 'bag' => [], 'error' => $throwable::class . ': ' . $throwable->getMessage()];
        } finally {
            $zip->close();
        }
    }

    /**
     * @param list<array<string, mixed>> $bag
     * @return list<array{path: string, mime: string, size: int}>
     */
    private function normalizeBag(array $bag): array
    {
        $normalized = [];
        foreach ($bag as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized[] = [
                'path' => (string) ($item['path'] ?? ''),
                'mime' => strtolower((string) ($item['mime'] ?? '')),
                'size' => (int) ($item['size'] ?? 0),
            ];
        }

        usort(
            $normalized,
            static fn (array $left, array $right): int => [$left['path'], $left['mime'], $left['size']] <=> [$right['path'], $right['mime'], $right['size']]
        );

        return $normalized;
    }

    /**
     * @param list<array{path: string, mime: string, size: int}> $expectedBag
     * @param list<array{path: string, mime: string, size: int}> $actualBag
     */
    private function firstDifference(array $expectedBag, array $actualBag): ?string
    {
        if (count($expectedBag) !== count($actualBag)) {
            return 'media item count expected=' . count($expectedBag) . ' actual=' . count($actualBag);
        }

        foreach ($expectedBag as $index => $expected) {
            $actual = $actualBag[$index] ?? null;
            if ($actual === null) {
                return 'missing actual media item at index ' . $index;
            }
            foreach (['path', 'mime', 'size'] as $key) {
                if (($expected[$key] ?? null) !== ($actual[$key] ?? null)) {
                    return 'media item ' . $index . ' ' . $key
                        . ' expected=' . (string) ($expected[$key] ?? '')
                        . ' actual=' . (string) ($actual[$key] ?? '');
                }
            }
        }

        return null;
    }

    private function pathRelativeToRootDirectory(string $path, string $rootDirectory): string
    {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        $root = trim(str_replace('\\', '/', $rootDirectory), '/');
        if ($root !== '' && str_starts_with($normalized, $root . '/')) {
            return substr($normalized, strlen($root) + 1);
        }

        return $normalized;
    }

    private function dirname(string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');
        if ($normalized === '' || !str_contains($normalized, '/')) {
            return '';
        }

        return substr($normalized, 0, (int) strrpos($normalized, '/'));
    }

    private function zipEntrySize(\ZipArchive $zip, string $entryName): int
    {
        $stat = $zip->statName($entryName);
        if (is_array($stat) && isset($stat['size'])) {
            return (int) $stat['size'];
        }

        $bytes = $zip->getFromName($entryName);
        return is_string($bytes) ? strlen($bytes) : 0;
    }

    private function mimeFromPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'gif' => 'image/gif',
            'jpeg', 'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    /**
     * @return array<string, list<string>>
     */
    private static function normalizationPolicy(): array
    {
        return [
            'includes' => [
                'local EpubReader epubMediaBagResources entries derived from emitted image nodes',
                'OPF manifest media-type values',
                'uncompressed ZIP entry byte sizes',
                'Pandoc-style paths relative to the OPF root directory',
            ],
            'excludes' => [
                'Pandoc AST equality',
                'upstream Haskell runner execution',
                'manifest image resources not used by the emitted EPUB image AST',
                'EPUB writer behavior',
            ],
        ];
    }

    private static function mediaBagParityStatus(int $parseFailureCount, int $mismatchCount, int $comparedCaseCount): string
    {
        if ($comparedCaseCount === 0) {
            return 'not-evaluated-no-epub-mediabag-cases';
        }
        if ($parseFailureCount > 0) {
            return 'epub-fixture-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'media-bag-mismatches-observed';
        }

        return 'media-bag-equality-observed-not-runner-parity';
    }

    /**
     * @return list<array{id: string, status: string, summary: string}>
     */
    private static function orderedRemainingGaps(bool $evaluated, int $comparedCount, int $parseFailureCount, int $matchCount, int $mismatchCount): array
    {
        $equalityStatus = 'not-evaluated';
        if ($evaluated) {
            $equalityStatus = $comparedCount > 0 && $parseFailureCount === 0 && $mismatchCount === 0 && $matchCount === $comparedCount
                ? 'covered-by-current-media-bag-evidence'
                : 'open';
        }

        return [
            [
                'id' => 'upstream-epub-mediabag-equality',
                'status' => $equalityStatus,
                'summary' => 'Compare local EPUB reader media-bag path, MIME, and byte-size tuples with Tests.Readers.EPUB expectations.',
            ],
            [
                'id' => 'upstream-epub-reader-runner-results',
                'status' => 'open',
                'summary' => 'Run the upstream Haskell/Tasty EPUB reader tests directly and archive the runner result.',
            ],
            [
                'id' => 'full-epub-reader-feature-coverage',
                'status' => 'open',
                'summary' => 'Broaden beyond the upstream media-bag tests into full EPUB AST, metadata, navigation, CSS, and edge-case coverage.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @param list<string> $lines
     * @return list<string>
     */
    private static function appendOrderedRemainingGaps(array $lines, array $report): array
    {
        $gaps = $report['orderedRemainingGaps'] ?? [];
        if (!is_array($gaps) || $gaps === []) {
            return $lines;
        }

        $lines[] = 'orderedRemainingGaps:';
        foreach (array_values($gaps) as $index => $gap) {
            if (!is_array($gap)) {
                continue;
            }
            $lines[] = sprintf(
                '%d. %s [%s] %s',
                $index + 1,
                (string) ($gap['id'] ?? 'unknown'),
                (string) ($gap['status'] ?? 'unknown'),
                (string) ($gap['summary'] ?? '')
            );
        }

        return $lines;
    }

    private static function percent(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private static function formatPercent(mixed $percent): string
    {
        if (!is_float($percent) && !is_int($percent)) {
            return 'n/a';
        }

        return number_format((float) $percent, 2) . '%';
    }
}
