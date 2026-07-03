<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubNativeAstPackageComparisonHarness
{
    private const DEFAULT_MAX_EXAMPLES = 12;
    private const VERDICT = 'epub-native-ast-package-comparison-not-full-epub-parity';
    private const CLAIM = 'Compares local PHP EPUB package parsing and reader output with a supplied checked-in current EPUB fixture directory and same-basename .native goldens. Package parsing/reader acceptance, fixture identity, and native AST equality are reported separately; no upstream Haskell runner, writer parity, or full EPUB feature parity is asserted.';

    /** @var array<string, true> */
    private const IGNORED_ATTRS = [
        'epubRootfile' => true,
        'epubManifestItemCount' => true,
        'epubManifestItems' => true,
        'epubSpineItems' => true,
        'epubSpineItemRefs' => true,
        'epubReadableResources' => true,
        'epubReferencedResources' => true,
        'epubImageResources' => true,
        'epubMediaBagResources' => true,
        'epubTocResources' => true,
        'epubTocEntryCount' => true,
        'epubTocEntries' => true,
        'epubLandmarkEntryCount' => true,
        'epubLandmarkEntries' => true,
        'sourceFormat' => true,
    ];

    /**
     * @var array<string, array{sha256: string, bytes: int}>
     */
    private const CHECKED_IN_CURRENT_FIXTURE_IDENTITIES = [
        'epub2_cover.epub' => [
            'sha256' => '4af73a135aa632cbf0c00b2889a5fc1d39a59a77fa294fdeff5ede72ff6ffed1',
            'bytes' => 11794,
        ],
        'epub2_cover.native' => [
            'sha256' => '4107c44d7711b63dac21745139f9cfb6dd99288b38ecf0d43e07b5ecd2493618',
            'bytes' => 1314,
        ],
        'epub2_no_cover.epub' => [
            'sha256' => '8369dbe5cf315f1fe00f9dd1bf7c500cc663d7648edbf0d7b6a9b4d785fedf4e',
            'bytes' => 3584,
        ],
        'epub2_no_cover.native' => [
            'sha256' => '48808c2e009669341a887a3c23adf033744aa652b0f69c319f0058396b59c6b8',
            'bytes' => 1242,
        ],
        'epub2_picture.epub' => [
            'sha256' => '6049dde9e1d0ebcd175a8c5b937984f349af996e293310eafbce09e4c7384495',
            'bytes' => 11742,
        ],
        'epub2_picture.native' => [
            'sha256' => 'fa1cc897a5172b6f66411f2b61156a86669654e0338d137f543e069d4f73fb39',
            'bytes' => 1314,
        ],
        'features.epub' => [
            'sha256' => '6bf9a102249d58b32f14b39dfbc966bdecadff68a3fb707cb3ca62334734358a',
            'bytes' => 8970,
        ],
        'features.native' => [
            'sha256' => 'c384a314081ecc860bb0f8a9ffb5273976ed56341e4f16e05dd448126e85c41f',
            'bytes' => 48453,
        ],
        'formatting.epub' => [
            'sha256' => '491fc57ec384449a23c4f2abdcfe91be9ab2a07f50f466fb8d80775b89bf3965',
            'bytes' => 14022,
        ],
        'formatting.native' => [
            'sha256' => '9041b6aa23827579a4db45074bd9b26077337defc26ec62ab3b57f676f4eeb21',
            'bytes' => 172999,
        ],
        'img.epub' => [
            'sha256' => 'f2c25e0e0612b7ac33a8d6a1c9719a86e7d2a0290472fc7d8b5068de781a822f',
            'bytes' => 20478,
        ],
        'img.native' => [
            'sha256' => '817c691f8fab94b1ed9092b9cc23a2299771af8df99c8b0a8dded51ce63baf91',
            'bytes' => 6762,
        ],
        'img_no_cover.epub' => [
            'sha256' => '3063f5e9b9610df1ddcc682ce49c293bcf681f1958700a5b6c3eda344383cf2a',
            'bytes' => 10602,
        ],
        'img_no_cover.native' => [
            'sha256' => '0e0152ba08256f6926bb9e9bba1892b673aa994ddbc8ab369d36f0abeab0b2b2',
            'bytes' => 6630,
        ],
        'wasteland.epub' => [
            'sha256' => '151ec5dbca33e39a4e3f6894e92fa5a101290bdeaaa792e0700595971456a278',
            'bytes' => 25840,
        ],
        'wasteland.native' => [
            'sha256' => '0a268af28518f063604659adb2ff27b123c771f8312b60fb40445bb2c551bbac',
            'bytes' => 150477,
        ],
    ];

    /**
     * @var array<string, mixed>
     */
    private const CHECKED_IN_CURRENT_PACKAGE_FEATURE_COVERAGE = [
        'fixtureCount' => 8,
        'metadataLanguageCounts' => [
            'de-DE' => 3,
            'en' => 4,
            'en-US' => 1,
        ],
        'navigationTypeCounts' => [
            'nav' => 5,
            'ncx' => 3,
        ],
        'navigationSectionTypes' => [
            'landmarks',
            'toc',
        ],
        'fixturesWithGuideReferences' => [
            'epub2_cover',
            'epub2_no_cover',
            'epub2_picture',
        ],
        'fixturesWithPackageLinks' => [
            'wasteland',
        ],
        'fixturesWithCoverImagePart' => [
            'epub2_cover',
            'epub2_picture',
            'img',
            'wasteland',
        ],
        'fixturesWithImages' => [
            'epub2_cover',
            'epub2_picture',
            'formatting',
            'img',
            'img_no_cover',
            'wasteland',
        ],
        'fixturesWithStylesheets' => [
            'epub2_cover',
            'epub2_no_cover',
            'epub2_picture',
            'features',
            'formatting',
            'img',
            'img_no_cover',
            'wasteland',
        ],
        'fixturesWithLandmarks' => [
            'features',
            'formatting',
            'img',
            'img_no_cover',
            'wasteland',
        ],
        'totals' => [
            'manifestItems' => 51,
            'readingOrderItems' => 22,
            'xhtmlAssets' => 23,
            'imageAssets' => 11,
            'stylesheetAssets' => 13,
            'navigationEntries' => 90,
            'landmarkEntries' => 7,
            'packageLinks' => 3,
            'guideReferences' => 3,
        ],
    ];

    /**
     * @param array{limit?: int, maxExamples?: int} $options
     * @return array<string, mixed>
     */
    public function run(string $epubDirectory, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));

        if (!is_dir($epubDirectory)) {
            return $this->skippedReport($epubDirectory, 'upstream-cache-missing');
        }

        $fixtureIdentity = $this->fixtureIdentity($epubDirectory);
        $epubFiles = $this->filesByBasename($epubDirectory, 'epub');
        $nativeFiles = $this->filesByBasename($epubDirectory, 'native');
        $epubNames = array_keys($epubFiles);
        sort($epubNames, SORT_STRING);
        $pairNames = array_values(array_intersect($epubNames, array_keys($nativeFiles)));
        sort($pairNames, SORT_STRING);

        $totalEpubCount = count($epubNames);
        $totalPairCount = count($pairNames);
        if ($limit > 0) {
            $epubNames = array_slice($epubNames, 0, $limit);
            $pairNames = array_values(array_intersect($pairNames, $epubNames));
        }

        $packageParsedCount = 0;
        $readerParsedCount = 0;
        $packageParseFailures = [];
        $readerParseFailures = [];
        $packageSummaries = [];
        $packageFeatureSummaries = [];
        $readerDocuments = [];
        $categoryCounts = [];

        foreach ($epubNames as $epubName) {
            $path = $epubFiles[$epubName];
            $packageResult = $this->readPackage($path);
            if ($packageResult['ok']) {
                ++$packageParsedCount;
                /** @var EpubPackage $package */
                $package = $packageResult['package'];
                $summary = $this->packageSummary($epubName, $package);
                $packageFeatureSummaries[] = $summary;
                if (count($packageSummaries) < $maxExamples) {
                    $packageSummaries[] = $summary;
                }
            } else {
                $packageParseFailures[] = [
                    'fixture' => $epubName,
                    'error' => $packageResult['error'],
                ];
                $this->addCategory($categoryCounts, 'package-parse-failure', $epubName, $maxExamples);
            }

            $readerResult = $this->readEpub($path);
            if ($readerResult['ok']) {
                ++$readerParsedCount;
                $readerDocuments[$epubName] = $readerResult['document'];
            } else {
                $readerParseFailures[] = [
                    'fixture' => $epubName,
                    'error' => $readerResult['error'],
                ];
                $this->addCategory($categoryCounts, 'reader-parse-failure', $epubName, $maxExamples);
            }
        }

        $epubPairParsedCount = 0;
        $nativeParsedCount = 0;
        $bothParsedCount = 0;
        $normalizedAstMatchCount = 0;
        $nativeParseFailures = [];
        $astParseFailures = [];
        $mismatches = [];

        foreach ($pairNames as $pairName) {
            $epubDocument = $readerDocuments[$pairName] ?? null;
            if ($epubDocument instanceof AstNode) {
                ++$epubPairParsedCount;
            }

            $nativeResult = $this->readNative($nativeFiles[$pairName]);
            if ($nativeResult['ok']) {
                ++$nativeParsedCount;
            } else {
                $nativeParseFailures[] = [
                    'fixture' => $pairName,
                    'error' => $nativeResult['error'],
                ];
                $this->addCategory($categoryCounts, 'native-parse-failure', $pairName, $maxExamples);
            }

            if (!$epubDocument instanceof AstNode || !$nativeResult['ok']) {
                $astParseFailures[] = [
                    'fixture' => $pairName,
                    'epubError' => $epubDocument instanceof AstNode ? null : 'EPUB reader did not parse fixture',
                    'nativeError' => $nativeResult['error'],
                ];
                continue;
            }

            /** @var AstNode $nativeDocument */
            $nativeDocument = $nativeResult['document'];
            ++$bothParsedCount;

            $epubAst = $this->normalizedNode($epubDocument);
            $nativeAst = $this->normalizedNode($nativeDocument);
            if ($epubAst === $nativeAst) {
                ++$normalizedAstMatchCount;
                continue;
            }

            $difference = $this->firstDifference($epubAst, $nativeAst) ?? 'unknown-normalized-ast-difference';
            $categories = $this->mismatchCategories($difference);
            foreach ($categories as $category) {
                $this->addCategory($categoryCounts, $category, $pairName, $maxExamples);
            }
            if (count($mismatches) < $maxExamples) {
                $mismatches[] = [
                    'fixture' => $pairName,
                    'firstDifference' => $difference,
                    'categories' => $categories,
                    'epubTopTypes' => $this->topTypeSequence($epubDocument),
                    'nativeTopTypes' => $this->topTypeSequence($nativeDocument),
                ];
            }
        }

        ksort($categoryCounts);
        $comparedEpubCount = count($epubNames);
        $comparedPairCount = count($pairNames);
        $packageParseFailureCount = count($packageParseFailures);
        $readerParseFailureCount = count($readerParseFailures);
        $astParseFailureCount = count($astParseFailures);
        $normalizedAstMismatchCount = $bothParsedCount - $normalizedAstMatchCount;

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-epub-native-ast-package',
            'status' => 'completed',
            'skipped' => false,
            'reason' => null,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'epub-native-ast-package-comparison',
            'upstreamEpubDirectory' => $epubDirectory,
            'fixtureIdentity' => $fixtureIdentity,
            'packageFeatureCoverage' => self::packageFeatureCoverage($packageFeatureSummaries),
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalEpubCount' => $totalEpubCount,
            'comparedEpubCount' => $comparedEpubCount,
            'packageParsedCount' => $packageParsedCount,
            'readerParsedCount' => $readerParsedCount,
            'packageParseFailureCount' => $packageParseFailureCount,
            'readerParseFailureCount' => $readerParseFailureCount,
            'packageAcceptanceStatus' => self::packageAcceptanceStatus($comparedEpubCount, $packageParseFailureCount, $readerParseFailureCount),
            'totalPairCount' => $totalPairCount,
            'comparedPairCount' => $comparedPairCount,
            'epubPairParsedCount' => $epubPairParsedCount,
            'nativeParsedCount' => $nativeParsedCount,
            'bothParsedCount' => $bothParsedCount,
            'astParseFailureCount' => $astParseFailureCount,
            'nativeParseFailureCount' => count($nativeParseFailures),
            'normalizedAstMatchCount' => $normalizedAstMatchCount,
            'normalizedAstMismatchCount' => $normalizedAstMismatchCount,
            'normalizedAstMatchPercent' => self::percent($normalizedAstMatchCount, $comparedPairCount),
            'astParityStatus' => self::astParityStatus($comparedPairCount, $astParseFailureCount, $normalizedAstMismatchCount),
            'packageSummaries' => $packageSummaries,
            'packageParseFailures' => array_slice($packageParseFailures, 0, $maxExamples),
            'readerParseFailures' => array_slice($readerParseFailures, 0, $maxExamples),
            'nativeParseFailures' => array_slice($nativeParseFailures, 0, $maxExamples),
            'astParseFailures' => array_slice($astParseFailures, 0, $maxExamples),
            'mismatchComparisons' => $mismatches,
            'mismatchCategories' => array_values($categoryCounts),
            'orderedRemainingGaps' => self::orderedRemainingGaps(
                true,
                $comparedEpubCount,
                $packageParseFailureCount,
                $readerParseFailureCount,
                $comparedPairCount,
                $astParseFailureCount,
                $normalizedAstMatchCount,
                $normalizedAstMismatchCount
            ),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function formatReport(array $report): string
    {
        $lines = [
            'Pandoc EPUB native/package comparison: ' . (string) ($report['status'] ?? 'unknown'),
            'Verdict: ' . (string) ($report['verdict'] ?? self::VERDICT),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'upstreamEpubDirectory=' . (string) ($report['upstreamEpubDirectory'] ?? ''),
        ];

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'reason=' . (string) ($report['reason'] ?? 'unknown');
            $lines = self::appendOrderedRemainingGaps($lines, $report);

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $lines[] = sprintf(
            'packages: total=%d compared=%d packageParsed=%d readerParsed=%d packageFailures=%d readerFailures=%d status=%s',
            (int) ($report['totalEpubCount'] ?? 0),
            (int) ($report['comparedEpubCount'] ?? 0),
            (int) ($report['packageParsedCount'] ?? 0),
            (int) ($report['readerParsedCount'] ?? 0),
            (int) ($report['packageParseFailureCount'] ?? 0),
            (int) ($report['readerParseFailureCount'] ?? 0),
            (string) ($report['packageAcceptanceStatus'] ?? 'unknown')
        );
        $lines[] = sprintf(
            'nativePairs: total=%d compared=%d parsedBoth=%d parseFailures=%d nativeFailures=%d',
            (int) ($report['totalPairCount'] ?? 0),
            (int) ($report['comparedPairCount'] ?? 0),
            (int) ($report['bothParsedCount'] ?? 0),
            (int) ($report['astParseFailureCount'] ?? 0),
            (int) ($report['nativeParseFailureCount'] ?? 0)
        );
        $lines[] = sprintf(
            'normalizedAst: matches=%d (%s) mismatches=%d status=%s',
            (int) ($report['normalizedAstMatchCount'] ?? 0),
            self::formatPercent($report['normalizedAstMatchPercent'] ?? null),
            (int) ($report['normalizedAstMismatchCount'] ?? 0),
            (string) ($report['astParityStatus'] ?? 'unknown')
        );
        $fixtureIdentity = is_array($report['fixtureIdentity'] ?? null) ? $report['fixtureIdentity'] : [];
        $fixtureValidation = is_array($fixtureIdentity['validation'] ?? null) ? $fixtureIdentity['validation'] : [];
        if ($fixtureIdentity !== []) {
            $lines[] = sprintf(
                'fixtureIdentity: status=%s expected=%d observed=%d',
                (string) ($fixtureValidation['status'] ?? 'unknown'),
                (int) ($fixtureIdentity['expectedFileCount'] ?? 0),
                (int) ($fixtureIdentity['observedFileCount'] ?? 0)
            );
        }
        $featureCoverage = is_array($report['packageFeatureCoverage'] ?? null) ? $report['packageFeatureCoverage'] : [];
        if ($featureCoverage !== []) {
            $navigationTypeCounts = is_array($featureCoverage['navigationTypeCounts'] ?? null)
                ? $featureCoverage['navigationTypeCounts']
                : [];
            $totals = is_array($featureCoverage['totals'] ?? null) ? $featureCoverage['totals'] : [];
            $coverFixtures = is_array($featureCoverage['fixturesWithCoverImagePart'] ?? null)
                ? $featureCoverage['fixturesWithCoverImagePart']
                : [];
            $landmarkFixtures = is_array($featureCoverage['fixturesWithLandmarks'] ?? null)
                ? $featureCoverage['fixturesWithLandmarks']
                : [];
            $lines[] = sprintf(
                'packageFeatureCoverage: fixtures=%d nav=%d ncx=%d covers=%d landmarks=%d manifestItems=%d readingOrderItems=%d imageAssets=%d stylesheetAssets=%d',
                (int) ($featureCoverage['fixtureCount'] ?? 0),
                (int) ($navigationTypeCounts['nav'] ?? 0),
                (int) ($navigationTypeCounts['ncx'] ?? 0),
                count($coverFixtures),
                count($landmarkFixtures),
                (int) ($totals['manifestItems'] ?? 0),
                (int) ($totals['readingOrderItems'] ?? 0),
                (int) ($totals['imageAssets'] ?? 0),
                (int) ($totals['stylesheetAssets'] ?? 0)
            );
        }

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

        $categories = $report['mismatchCategories'] ?? [];
        if (is_array($categories) && $categories !== []) {
            $lines[] = 'mismatchCategories:';
            foreach ($categories as $category) {
                if (!is_array($category)) {
                    continue;
                }
                $examples = $category['examples'] ?? [];
                $exampleText = is_array($examples) && $examples !== []
                    ? ' examples=' . implode(',', array_map('strval', $examples))
                    : '';
                $lines[] = sprintf(
                    '- %s count=%d%s',
                    (string) ($category['category'] ?? 'unknown'),
                    (int) ($category['count'] ?? 0),
                    $exampleText
                );
            }
        }

        $lines = self::appendOrderedRemainingGaps($lines, $report);

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredPackageParity(array $report, int $requiredEpubCount): bool
    {
        if ($requiredEpubCount < 0) {
            throw new \InvalidArgumentException('Required EPUB package count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['totalEpubCount'] ?? -1) === $requiredEpubCount
            && (int) ($report['comparedEpubCount'] ?? -1) === $requiredEpubCount
            && (int) ($report['packageParsedCount'] ?? -1) === $requiredEpubCount
            && (int) ($report['readerParsedCount'] ?? -1) === $requiredEpubCount
            && (int) ($report['packageParseFailureCount'] ?? -1) === 0
            && (int) ($report['readerParseFailureCount'] ?? -1) === 0
            && ($report['packageAcceptanceStatus'] ?? null) === 'package-and-reader-acceptance-observed-not-full-epub-parity';
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredNativeReadiness(array $report, int $requiredPairCount): bool
    {
        if ($requiredPairCount < 0) {
            throw new \InvalidArgumentException('Required EPUB native pair count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['totalPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['comparedPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['epubPairParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['nativeParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['bothParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['astParseFailureCount'] ?? -1) === 0
            && (int) ($report['nativeParseFailureCount'] ?? -1) === 0;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredMappedParity(array $report, int $requiredPairCount): bool
    {
        if ($requiredPairCount < 0) {
            throw new \InvalidArgumentException('Required EPUB mapped parity count must not be negative');
        }

        return self::hasRequiredNativeReadiness($report, $requiredPairCount)
            && (int) ($report['normalizedAstMatchCount'] ?? -1) === $requiredPairCount
            && (int) ($report['normalizedAstMismatchCount'] ?? -1) === 0
            && ($report['astParityStatus'] ?? null) === 'normalized-ast-equality-observed-not-runner-parity';
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredFixtureIdentity(array $report): bool
    {
        $identity = is_array($report['fixtureIdentity'] ?? null) ? $report['fixtureIdentity'] : [];
        $validation = is_array($identity['validation'] ?? null) ? $identity['validation'] : [];

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($identity['expectedFileCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)
            && (int) ($identity['observedFileCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)
            && ($validation['status'] ?? null) === 'valid-checked-in-current-epub-fixture-identity'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredCurrentPackageFeatureCoverage(array $report): bool
    {
        $coverage = is_array($report['packageFeatureCoverage'] ?? null) ? $report['packageFeatureCoverage'] : [];
        if (($report['skipped'] ?? false) !== false || ($report['status'] ?? null) !== 'completed') {
            return false;
        }

        foreach (self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_COVERAGE as $key => $expected) {
            if (($coverage[$key] ?? null) !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function skippedReport(string $epubDirectory, string $reason): array
    {
        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-epub-native-ast-package',
            'status' => 'skipped',
            'skipped' => true,
            'reason' => $reason,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'epub-native-ast-package-comparison',
            'upstreamEpubDirectory' => $epubDirectory,
            'fixtureIdentity' => self::notEvaluatedFixtureIdentity(),
            'packageFeatureCoverage' => self::emptyPackageFeatureCoverage(),
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalEpubCount' => 0,
            'comparedEpubCount' => 0,
            'packageParsedCount' => 0,
            'readerParsedCount' => 0,
            'packageParseFailureCount' => 0,
            'readerParseFailureCount' => 0,
            'packageAcceptanceStatus' => 'not-evaluated-source-directory-unavailable',
            'totalPairCount' => 0,
            'comparedPairCount' => 0,
            'epubPairParsedCount' => 0,
            'nativeParsedCount' => 0,
            'bothParsedCount' => 0,
            'astParseFailureCount' => 0,
            'nativeParseFailureCount' => 0,
            'normalizedAstMatchCount' => 0,
            'normalizedAstMismatchCount' => 0,
            'normalizedAstMatchPercent' => null,
            'astParityStatus' => 'not-evaluated-source-directory-unavailable',
            'packageSummaries' => [],
            'packageParseFailures' => [],
            'readerParseFailures' => [],
            'nativeParseFailures' => [],
            'astParseFailures' => [],
            'mismatchComparisons' => [],
            'mismatchCategories' => [],
            'orderedRemainingGaps' => self::orderedRemainingGaps(false, 0, 0, 0, 0, 0, 0, 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizationPolicy(): array
    {
        return [
            'compares' => [
                'node type',
                'non-provenance node attributes',
                'child order and child count',
                'visible inline and block AST shape after adjacent text nodes are coalesced',
            ],
            'excludes' => [
                'document metadata and local EPUB package provenance attrs',
                'derived text attrs on plain, paragraph, heading, table_cell, and term nodes',
                'reader-specific adjacent Str/Space text-node segmentation',
            ],
            'doesNotAssert' => [
                'upstream Haskell/Cabal runner execution',
                'EPUB writer parity',
                'byte-level EPUB package equality',
                'full EPUB feature parity beyond audited package and native fixtures',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyPackageFeatureCoverage(): array
    {
        return [
            'kind' => 'epub-package-feature-coverage',
            'fixtureCount' => 0,
            'metadataLanguageCounts' => [],
            'navigationTypeCounts' => [],
            'navigationSectionTypes' => [],
            'fixturesWithGuideReferences' => [],
            'fixturesWithPackageLinks' => [],
            'fixturesWithCoverImagePart' => [],
            'fixturesWithImages' => [],
            'fixturesWithStylesheets' => [],
            'fixturesWithLandmarks' => [],
            'totals' => [
                'manifestItems' => 0,
                'readingOrderItems' => 0,
                'xhtmlAssets' => 0,
                'imageAssets' => 0,
                'stylesheetAssets' => 0,
                'navigationEntries' => 0,
                'landmarkEntries' => 0,
                'packageLinks' => 0,
                'guideReferences' => 0,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function filesByBasename(string $directory, string $extension): array
    {
        $files = [];
        foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.' . $extension) ?: [] as $path) {
            $files[basename($path, '.' . $extension)] = $path;
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function fixtureIdentity(string $directory): array
    {
        $observedFiles = [];
        foreach (['epub', 'native'] as $extension) {
            foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.' . $extension) ?: [] as $path) {
                $observedFiles[basename($path)] = $path;
            }
        }
        ksort($observedFiles, SORT_STRING);

        $files = [];
        $missingFiles = [];
        $changedFiles = [];
        foreach (self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES as $relativePath => $expected) {
            $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
            $present = is_file($path);
            $actualSha256 = $present ? hash_file('sha256', $path) : false;
            $size = $present ? filesize($path) : false;
            $actualBytes = is_int($size) ? $size : null;
            $sha256 = is_string($actualSha256) ? $actualSha256 : null;
            $matches = $present
                && $sha256 === $expected['sha256']
                && $actualBytes === $expected['bytes'];

            if (!$present) {
                $missingFiles[] = $relativePath;
            } elseif (!$matches) {
                $changedFiles[] = $relativePath;
            }

            $files[] = [
                'path' => $relativePath,
                'present' => $present,
                'sha256' => $sha256,
                'expectedSha256' => $expected['sha256'],
                'bytes' => $actualBytes,
                'expectedBytes' => $expected['bytes'],
                'matchesExpected' => $matches,
            ];
        }

        $unexpectedFiles = array_values(array_diff(array_keys($observedFiles), array_keys(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)));
        sort($unexpectedFiles, SORT_STRING);

        $issues = [];
        if (count($observedFiles) !== count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)) {
            $issues[] = 'fixture-file-count-does-not-match-expected-snapshot';
        }
        if ($missingFiles !== []) {
            $issues[] = 'missing-expected-fixture-files';
        }
        if ($unexpectedFiles !== []) {
            $issues[] = 'unexpected-fixture-files';
        }
        if ($changedFiles !== []) {
            $issues[] = 'fixture-hash-or-byte-count-mismatch';
        }

        return [
            'kind' => 'static-checked-in-current-epub-fixture-identity',
            'expectedFileCount' => count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
            'observedFileCount' => count($observedFiles),
            'expectedFiles' => array_keys(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
            'observedFiles' => array_keys($observedFiles),
            'files' => $files,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-epub-fixture-identity' : 'invalid-checked-in-current-epub-fixture-identity',
                'issues' => $issues,
                'missingFiles' => $missingFiles,
                'unexpectedFiles' => $unexpectedFiles,
                'changedFiles' => $changedFiles,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedFixtureIdentity(): array
    {
        return [
            'kind' => 'static-checked-in-current-epub-fixture-identity',
            'expectedFileCount' => count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
            'observedFileCount' => 0,
            'expectedFiles' => array_keys(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
            'observedFiles' => [],
            'files' => [],
            'validation' => [
                'status' => 'not-evaluated-source-directory-unavailable',
                'issues' => ['source-directory-unavailable'],
                'missingFiles' => [],
                'unexpectedFiles' => [],
                'changedFiles' => [],
            ],
        ];
    }

    /**
     * @return array{ok: bool, package: ?EpubPackage, error: ?string}
     */
    private function readPackage(string $path): array
    {
        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new \RuntimeException("Unable to read EPUB fixture '{$path}'.");
            }

            return ['ok' => true, 'package' => EpubPackage::fromString($bytes), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'package' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function packageSummary(string $fixture, EpubPackage $package): array
    {
        $assets = $package->assetSummary();
        $metadata = $package->metadata();
        $navigation = $package->navigation();
        $navigationSections = $package->navigationSections();
        $navigationSectionTypes = [];
        $landmarkEntryCount = 0;
        $pageListEntryCount = 0;
        $auxiliaryNavigationEntryCount = 0;

        foreach ($navigationSections as $section) {
            $types = is_array($section['types'] ?? null) ? $section['types'] : [];
            $entries = is_array($section['entries'] ?? null) ? $section['entries'] : [];
            foreach ($types as $type) {
                if (!is_string($type) || $type === '') {
                    continue;
                }
                $navigationSectionTypes[$type] = true;
                if ($type === 'landmarks') {
                    $landmarkEntryCount += count($entries);
                } elseif ($type === 'page-list') {
                    $pageListEntryCount += count($entries);
                } elseif ($type !== 'toc') {
                    $auxiliaryNavigationEntryCount += count($entries);
                }
            }
        }
        $navigationSectionTypes = array_keys($navigationSectionTypes);
        sort($navigationSectionTypes, SORT_STRING);

        return [
            'fixture' => $fixture,
            'opfPart' => $package->opfPartName(),
            'metadataTitle' => is_string($metadata['title'] ?? null) ? $metadata['title'] : '',
            'metadataLanguage' => is_string($metadata['language'] ?? null) ? $metadata['language'] : '',
            'metadataCreatorCount' => is_array($metadata['creators'] ?? null) ? count($metadata['creators']) : 0,
            'packageLinkCount' => count($package->packageLinks()),
            'guideReferenceCount' => count($package->guideReferences()),
            'manifestItemCount' => count($package->manifestItems()),
            'readingOrderCount' => count($package->readingOrder()),
            'xhtmlAssetCount' => count($assets['xhtmlParts']),
            'imageAssetCount' => count($assets['imageParts']),
            'stylesheetAssetCount' => count($assets['stylesheetParts']),
            'navigationType' => is_array($navigation) ? (string) ($navigation['type'] ?? '') : null,
            'navigationEntryCount' => is_array($navigation) && is_array($navigation['entries'] ?? null) ? count($navigation['entries']) : 0,
            'navigationSectionCount' => count($navigationSections),
            'navigationSectionTypes' => $navigationSectionTypes,
            'landmarkEntryCount' => $landmarkEntryCount,
            'pageListEntryCount' => $pageListEntryCount,
            'auxiliaryNavigationEntryCount' => $auxiliaryNavigationEntryCount,
            'coverImagePart' => $assets['coverImagePart'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $summaries
     * @return array<string, mixed>
     */
    private static function packageFeatureCoverage(array $summaries): array
    {
        $coverage = self::emptyPackageFeatureCoverage();
        $coverage['fixtureCount'] = count($summaries);
        $metadataLanguageCounts = [];
        $navigationTypeCounts = [];
        $navigationSectionTypes = [];

        foreach ($summaries as $summary) {
            $fixture = is_string($summary['fixture'] ?? null) ? $summary['fixture'] : '';
            $language = is_string($summary['metadataLanguage'] ?? null) ? $summary['metadataLanguage'] : '';
            if ($language !== '') {
                $metadataLanguageCounts[$language] = (int) ($metadataLanguageCounts[$language] ?? 0) + 1;
            }

            $navigationType = is_string($summary['navigationType'] ?? null) ? $summary['navigationType'] : '';
            if ($navigationType !== '') {
                $navigationTypeCounts[$navigationType] = (int) ($navigationTypeCounts[$navigationType] ?? 0) + 1;
            }

            foreach (is_array($summary['navigationSectionTypes'] ?? null) ? $summary['navigationSectionTypes'] : [] as $type) {
                if (is_string($type) && $type !== '') {
                    $navigationSectionTypes[$type] = true;
                }
            }

            if ($fixture !== '' && (int) ($summary['guideReferenceCount'] ?? 0) > 0) {
                $coverage['fixturesWithGuideReferences'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['packageLinkCount'] ?? 0) > 0) {
                $coverage['fixturesWithPackageLinks'][] = $fixture;
            }
            if ($fixture !== '' && is_string($summary['coverImagePart'] ?? null) && (string) $summary['coverImagePart'] !== '') {
                $coverage['fixturesWithCoverImagePart'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['imageAssetCount'] ?? 0) > 0) {
                $coverage['fixturesWithImages'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['stylesheetAssetCount'] ?? 0) > 0) {
                $coverage['fixturesWithStylesheets'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['landmarkEntryCount'] ?? 0) > 0) {
                $coverage['fixturesWithLandmarks'][] = $fixture;
            }

            $coverage['totals']['manifestItems'] += (int) ($summary['manifestItemCount'] ?? 0);
            $coverage['totals']['readingOrderItems'] += (int) ($summary['readingOrderCount'] ?? 0);
            $coverage['totals']['xhtmlAssets'] += (int) ($summary['xhtmlAssetCount'] ?? 0);
            $coverage['totals']['imageAssets'] += (int) ($summary['imageAssetCount'] ?? 0);
            $coverage['totals']['stylesheetAssets'] += (int) ($summary['stylesheetAssetCount'] ?? 0);
            $coverage['totals']['navigationEntries'] += (int) ($summary['navigationEntryCount'] ?? 0);
            $coverage['totals']['landmarkEntries'] += (int) ($summary['landmarkEntryCount'] ?? 0);
            $coverage['totals']['packageLinks'] += (int) ($summary['packageLinkCount'] ?? 0);
            $coverage['totals']['guideReferences'] += (int) ($summary['guideReferenceCount'] ?? 0);
        }

        ksort($metadataLanguageCounts, SORT_STRING);
        ksort($navigationTypeCounts, SORT_STRING);
        $coverage['metadataLanguageCounts'] = $metadataLanguageCounts;
        $coverage['navigationTypeCounts'] = $navigationTypeCounts;
        $coverage['navigationSectionTypes'] = array_keys($navigationSectionTypes);
        sort($coverage['navigationSectionTypes'], SORT_STRING);

        return $coverage;
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readEpub(string $path): array
    {
        try {
            return ['ok' => true, 'document' => (new EpubReader())->readEpubFile($path), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readNative(string $path): array
    {
        try {
            $native = file_get_contents($path);
            if (!is_string($native)) {
                throw new \RuntimeException("Unable to read native fixture '{$path}'.");
            }

            return ['ok' => true, 'document' => (new NativeReader())->read($native), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    public function normalizedDocument(AstNode $document): array
    {
        return $this->normalizedNode($document);
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    private function normalizedNode(AstNode $node): array
    {
        $attrs = [];
        foreach ($node->attrs as $key => $value) {
            $key = (string) $key;
            if ($node->type === 'document' && $key === 'meta') {
                continue;
            }
            if (self::isIgnoredAttrKey($key)) {
                continue;
            }
            if ($key === 'text' && in_array($node->type, ['plain', 'paragraph', 'heading', 'table_cell', 'term'], true)) {
                continue;
            }
            if ($key === 'attributes' && $value === []) {
                continue;
            }

            $normalizedValue = $this->normalizedValue($value);
            if (in_array($key, ['attributes', 'htmlAttributes'], true) && $normalizedValue === []) {
                continue;
            }
            if ($normalizedValue === [] || $normalizedValue === null || $normalizedValue === '') {
                continue;
            }
            $attrs[$key] = $normalizedValue;
        }
        ksort($attrs, SORT_STRING);

        return [
            'type' => $node->type,
            'attrs' => $attrs,
            'children' => $this->normalizedChildren($node->children),
        ];
    }

    /**
     * @param list<AstNode> $children
     * @return list<array<string, mixed>>
     */
    private function normalizedChildren(array $children): array
    {
        $normalized = [];
        foreach ($children as $child) {
            $node = $this->normalizedNode($child);
            if ($this->isEmptyTableFootNode($node)) {
                continue;
            }
            $this->appendNormalizedChild($normalized, $node);
        }

        return $this->trimBoundaryWhitespaceText($normalized);
    }

    /**
     * @param list<array<string, mixed>> $normalized
     * @param array<string, mixed> $node
     */
    private function appendNormalizedChild(array &$normalized, array $node): void
    {
        $lastIndex = count($normalized) - 1;
        if ($lastIndex >= 0 && $this->isPlainTextNode($normalized[$lastIndex]) && $this->isPlainTextNode($node)) {
            $normalized[$lastIndex]['attrs']['text'] .= $node['attrs']['text'];
            return;
        }

        $normalized[] = $node;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>
     */
    private function trimBoundaryWhitespaceText(array $nodes): array
    {
        while ($nodes !== [] && $this->isWhitespaceOnlyPlainTextNode($nodes[0])) {
            array_shift($nodes);
        }

        while ($nodes !== [] && $this->isWhitespaceOnlyPlainTextNode($nodes[count($nodes) - 1])) {
            array_pop($nodes);
        }

        return array_values($nodes);
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isPlainTextNode(array $node): bool
    {
        $attrs = $node['attrs'] ?? null;

        return ($node['type'] ?? null) === 'text'
            && is_array($attrs)
            && array_keys($attrs) === ['text']
            && is_string($attrs['text'])
            && ($node['children'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isWhitespaceOnlyPlainTextNode(array $node): bool
    {
        if (!$this->isPlainTextNode($node)) {
            return false;
        }

        return trim((string) $node['attrs']['text']) === '';
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isEmptyTableFootNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'table_foot'
            && ($node['attrs'] ?? null) === []
            && ($node['children'] ?? null) === [];
    }

    private function normalizedValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace("\t", ' ', $value);
        }
        if (is_float($value)) {
            return round($value, 12);
        }
        if ($value instanceof AstNode) {
            return $this->normalizedNode($value);
        }
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            if ($this->isAstNodeList($value)) {
                return $this->normalizedChildren($value);
            }

            return array_map(fn (mixed $item): mixed => $this->normalizedValue($item), $value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (self::isIgnoredAttrKey((string) $key)) {
                continue;
            }
            $normalized[(string) $key] = $this->normalizedValue($item);
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param list<mixed> $value
     */
    private function isAstNodeList(array $value): bool
    {
        foreach ($value as $item) {
            if (!$item instanceof AstNode) {
                return false;
            }
        }

        return $value !== [];
    }

    private static function isIgnoredAttrKey(string $key): bool
    {
        return isset(self::IGNORED_ATTRS[$key])
            || str_starts_with($key, 'epub')
            || self::isNativeProvenanceAttrKey($key);
    }

    private static function isNativeProvenanceAttrKey(string $key): bool
    {
        return str_starts_with($key, 'native')
            || str_ends_with($key, 'Native')
            || str_ends_with($key, 'Natives')
            || str_ends_with($key, 'Constructor')
            || str_ends_with($key, 'Constructors')
            || in_array($key, ['constructor', 'pandocApiVersion'], true);
    }

    private function firstDifference(mixed $epub, mixed $native, string $path = 'root'): ?string
    {
        if (gettype($epub) !== gettype($native)) {
            return "{$path} type " . gettype($epub) . ' vs ' . gettype($native);
        }
        if (!is_array($epub)) {
            return $epub === $native ? null : "{$path} value " . self::shortJson($epub) . ' vs ' . self::shortJson($native);
        }

        $epubKeys = array_keys($epub);
        $nativeKeys = array_keys($native);
        if ($epubKeys !== $nativeKeys) {
            return "{$path} keys " . self::shortJson($epubKeys) . ' vs ' . self::shortJson($nativeKeys);
        }

        foreach ($epubKeys as $key) {
            $difference = $this->firstDifference($epub[$key], $native[$key], $path . '.' . $key);
            if ($difference !== null) {
                return $difference;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function mismatchCategories(string $difference): array
    {
        $lower = strtolower($difference);
        $categories = [];
        if (str_contains($lower, '.children keys')) {
            $categories[] = 'child-count-or-inline-granularity';
        }
        if (str_contains($lower, '.attrs keys') || str_contains($lower, '.attrs.')) {
            $categories[] = 'attribute-shape';
        }
        if (str_contains($lower, 'raw_html') || str_contains($lower, 'div') || str_contains($lower, 'span')) {
            $categories[] = 'xhtml-structure-shape';
        }
        if (str_contains($lower, 'image') || str_contains($lower, 'url') || str_contains($lower, 'alt')) {
            $categories[] = 'image-shape';
        }
        if (str_contains($lower, 'table') || str_contains($lower, 'row') || str_contains($lower, 'cell')) {
            $categories[] = 'table-shape';
        }
        if (str_contains($lower, '.type')) {
            $categories[] = 'node-type';
        }
        if (str_contains($lower, ' value ')) {
            $categories[] = 'scalar-value';
        }
        if ($categories === []) {
            $categories[] = 'uncategorized-normalized-ast-drift';
        }

        return array_values(array_unique($categories));
    }

    /**
     * @return list<string>
     */
    private function topTypeSequence(AstNode $document): array
    {
        return array_map(static fn (AstNode $child): string => $child->type, $document->children);
    }

    /**
     * @param array<string, array{category: string, count: int, examples: list<string>}> $categoryCounts
     */
    private function addCategory(array &$categoryCounts, string $category, string $fixture, int $maxExamples): void
    {
        if (!isset($categoryCounts[$category])) {
            $categoryCounts[$category] = ['category' => $category, 'count' => 0, 'examples' => []];
        }

        ++$categoryCounts[$category]['count'];
        if (count($categoryCounts[$category]['examples']) < $maxExamples) {
            $categoryCounts[$category]['examples'][] = $fixture;
        }
    }

    private static function packageAcceptanceStatus(int $comparedEpubCount, int $packageParseFailureCount, int $readerParseFailureCount): string
    {
        if ($comparedEpubCount === 0) {
            return 'not-evaluated-no-epub-files';
        }
        if ($packageParseFailureCount > 0 || $readerParseFailureCount > 0) {
            return 'blocked-by-package-or-reader-parse-failures';
        }

        return 'package-and-reader-acceptance-observed-not-full-epub-parity';
    }

    private static function astParityStatus(int $comparedPairCount, int $parseFailureCount, int $mismatchCount): string
    {
        if ($comparedPairCount === 0) {
            return 'not-evaluated-no-native-pairs';
        }
        if ($parseFailureCount > 0) {
            return 'blocked-by-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'normalized-ast-mismatches-observed';
        }

        return 'normalized-ast-equality-observed-not-runner-parity';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function orderedRemainingGaps(
        bool $directoryPresent,
        int $comparedEpubCount,
        int $packageParseFailureCount,
        int $readerParseFailureCount,
        int $comparedPairCount,
        int $astParseFailureCount,
        int $astMatchCount,
        int $astMismatchCount
    ): array {
        if (!$directoryPresent) {
            $packageEvidence = 'EPUB directory absent; package comparison did not run';
            $astEvidence = 'EPUB directory absent; native AST comparison did not run';
        } else {
            $packageEvidence = "compared epubs={$comparedEpubCount}; package failures={$packageParseFailureCount}; reader failures={$readerParseFailureCount}";
            $astEvidence = "native pairs={$comparedPairCount}; parse failures={$astParseFailureCount}; normalized matches={$astMatchCount}; normalized mismatches={$astMismatchCount}";
        }

        $packageCovered = $directoryPresent
            && $comparedEpubCount > 0
            && $packageParseFailureCount === 0
            && $readerParseFailureCount === 0;
        $astCovered = $directoryPresent
            && $comparedPairCount > 0
            && $astParseFailureCount === 0
            && $astMismatchCount === 0
            && $astMatchCount === $comparedPairCount;

        return [
            [
                'rank' => 1,
                'id' => 'upstream-epub-package-and-reader-acceptance',
                'status' => !$directoryPresent ? 'not-evaluated' : ($packageCovered ? 'covered-by-current-package-evidence' : 'open'),
                'currentEvidence' => $packageEvidence,
                'evidenceRequired' => 'Parse every upstream EPUB package with both the package preflight reader and local EPUB reader, keeping package and reader failures at zero.',
            ],
            [
                'rank' => 2,
                'id' => 'upstream-epub-native-ast-equality',
                'status' => !$directoryPresent ? 'not-evaluated' : ($astCovered ? 'covered-by-current-normalized-ast-evidence' : 'open'),
                'currentEvidence' => $astEvidence,
                'evidenceRequired' => 'Compare local PHP EPUB reader output against same-basename upstream .native goldens, keeping parse failures and normalized AST mismatches at zero.',
            ],
            [
                'rank' => 3,
                'id' => 'upstream-haskell-epub-reader-runner-results',
                'status' => 'open',
                'currentEvidence' => 'This harness inventories upstream EPUB packages and native goldens, but it does not run the upstream Haskell/Tasty process itself.',
                'evidenceRequired' => 'Record reproducible upstream Tests.Readers.EPUB runner results when a Haskell runner is available.',
            ],
        ];
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $report
     * @return list<string>
     */
    private static function appendOrderedRemainingGaps(array $lines, array $report): array
    {
        $gaps = $report['orderedRemainingGaps'] ?? [];
        if (!is_array($gaps) || $gaps === []) {
            return $lines;
        }

        $lines[] = 'orderedRemainingGaps:';
        foreach ($gaps as $gap) {
            if (!is_array($gap)) {
                continue;
            }
            $lines[] = sprintf(
                '%d. %s [%s]: %s',
                (int) ($gap['rank'] ?? 0),
                (string) ($gap['id'] ?? 'unknown'),
                (string) ($gap['status'] ?? 'unknown'),
                (string) ($gap['currentEvidence'] ?? '')
            );
        }

        return $lines;
    }

    private static function percent(int $count, int $total): ?float
    {
        return $total === 0 ? null : round(($count / $total) * 100, 2);
    }

    private static function formatPercent(mixed $percent): string
    {
        return is_int($percent) || is_float($percent) ? number_format((float) $percent, 2) . '%' : 'n/a';
    }

    private static function shortJson(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return gettype($value);
        }

        return strlen($json) > 240 ? substr($json, 0, 237) . '...' : $json;
    }
}
