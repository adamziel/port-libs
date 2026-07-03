<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubMediaBagComparisonHarness
{
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';

    private const DEFAULT_MAX_EXAMPLES = 12;
    private const VERDICT = 'media-bag-comparison-not-full-epub-parity';
    private const CLAIM = 'Compares local PHP EPUB reader image-resource output with upstream Tests.Readers.EPUB media-bag expectations by normalized media path, MIME type, and byte size; no upstream Haskell runner, AST parity, writer parity, or full EPUB feature parity is asserted.';
    private const CURRENT_MEDIA_BAG_SIGNATURE_KIND = 'checked-in-current-epub-media-bag-signature';
    private const CURRENT_MEDIA_BAG_SIGNATURE_ALGORITHM = 'sha256-canonical-json-v1';
    private const CURRENT_MEDIA_BAG_SIGNATURE_SCOPE = 'checked-in-current-upstream-epub-reader-6-case-media-bag-snapshot';
    private const CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURE_SHA256 = '48e9d4d6c7478aa213f3d75fc4cd1a2be58e2617d468d30d9027728d0258ce9d';
    private const RUNNER_TEST_SUITE = 'test:test-pandoc';
    private const RUNNER_BUILD_DIR = '.port-libs/pandoc-runner/cabal-build/epub-targeted-run';
    private const RUNNER_TASTY_GROUP_PATH = ['Readers', 'EPUB', 'EPUB Mediabag'];
    private const RUNNER_TASTY_PATTERN = '$2 == "Readers" && $3 == "EPUB" && $4 == "EPUB Mediabag"';
    private const RUNNER_REQUIRED_TRANSCRIPTS = [
        '.port-libs/pandoc-runner/logs/runner-test-dependencies.txt',
        '.port-libs/pandoc-runner/logs/epub-targeted-list-tests.txt',
        '.port-libs/pandoc-runner/logs/epub-targeted-run.txt',
    ];
    private const RUNNER_REQUIRED_ARTIFACTS = [
        '.port-libs/pandoc-runner/artifacts/epub-targeted-run/result.json',
    ];
    private const CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES = [
        [
            'case' => 'features bag',
            'fixture' => 'epub/img.epub',
            'expectedItemCount' => 4,
            'actualItemCount' => 4,
            'matchesExpected' => true,
            'expectedBag' => [
                ['path' => 'img/check.gif', 'mime' => 'image/gif', 'size' => 1340],
                ['path' => 'img/check.jpg', 'mime' => 'image/jpeg', 'size' => 2661],
                ['path' => 'img/check.png', 'mime' => 'image/png', 'size' => 2815],
                ['path' => 'img/multiscripts_and_greek_alphabet.png', 'mime' => 'image/png', 'size' => 10060],
            ],
            'actualBag' => [
                ['path' => 'img/check.gif', 'mime' => 'image/gif', 'size' => 1340],
                ['path' => 'img/check.jpg', 'mime' => 'image/jpeg', 'size' => 2661],
                ['path' => 'img/check.png', 'mime' => 'image/png', 'size' => 2815],
                ['path' => 'img/multiscripts_and_greek_alphabet.png', 'mime' => 'image/png', 'size' => 10060],
            ],
        ],
        [
            'case' => 'EPUB3 cover bag',
            'fixture' => 'epub/wasteland.epub',
            'expectedItemCount' => 1,
            'actualItemCount' => 1,
            'matchesExpected' => true,
            'expectedBag' => [
                ['path' => 'wasteland-cover.jpg', 'mime' => 'image/jpeg', 'size' => 16586],
            ],
            'actualBag' => [
                ['path' => 'wasteland-cover.jpg', 'mime' => 'image/jpeg', 'size' => 16586],
            ],
        ],
        [
            'case' => 'EPUB3 no cover bag',
            'fixture' => 'epub/img_no_cover.epub',
            'expectedItemCount' => 3,
            'actualItemCount' => 3,
            'matchesExpected' => true,
            'expectedBag' => [
                ['path' => 'img/check.gif', 'mime' => 'image/gif', 'size' => 1340],
                ['path' => 'img/check.jpg', 'mime' => 'image/jpeg', 'size' => 2661],
                ['path' => 'img/check.png', 'mime' => 'image/png', 'size' => 2815],
            ],
            'actualBag' => [
                ['path' => 'img/check.gif', 'mime' => 'image/gif', 'size' => 1340],
                ['path' => 'img/check.jpg', 'mime' => 'image/jpeg', 'size' => 2661],
                ['path' => 'img/check.png', 'mime' => 'image/png', 'size' => 2815],
            ],
        ],
        [
            'case' => 'EPUB2 picture bag',
            'fixture' => 'epub/epub2_picture.epub',
            'expectedItemCount' => 1,
            'actualItemCount' => 1,
            'matchesExpected' => true,
            'expectedBag' => [
                ['path' => 'image/image.jpg', 'mime' => 'image/jpeg', 'size' => 9713],
            ],
            'actualBag' => [
                ['path' => 'image/image.jpg', 'mime' => 'image/jpeg', 'size' => 9713],
            ],
        ],
        [
            'case' => 'EPUB2 cover bag',
            'fixture' => 'epub/epub2_cover.epub',
            'expectedItemCount' => 1,
            'actualItemCount' => 1,
            'matchesExpected' => true,
            'expectedBag' => [
                ['path' => 'image/cover.jpg', 'mime' => 'image/jpeg', 'size' => 9713],
            ],
            'actualBag' => [
                ['path' => 'image/cover.jpg', 'mime' => 'image/jpeg', 'size' => 9713],
            ],
        ],
        [
            'case' => 'EPUB2 no cover bag',
            'fixture' => 'epub/epub2_no_cover.epub',
            'expectedItemCount' => 0,
            'actualItemCount' => 0,
            'matchesExpected' => true,
            'expectedBag' => [],
            'actualBag' => [],
        ],
    ];

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
        $signatures = [];

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
                $signatures[] = $this->mediaBagSignature($caseName, $fixture, $expectedBag, [], false);
                continue;
            }

            ++$epubParsedCount;
            $actualBag = $this->normalizeBag($actualResult['bag']);
            $actualItemCount += count($actualBag);
            $matchesExpected = $actualBag === $expectedBag;
            $signatures[] = $this->mediaBagSignature($caseName, $fixture, $expectedBag, $actualBag, $matchesExpected);
            if ($matchesExpected) {
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

        $currentMediaBagSignature = self::currentMediaBagSignature(
            $signatures,
            $totalCaseCount,
            $comparedCaseCount,
            $epubParsedCount,
            $parseFailureCount,
            $expectedItemCount,
            $actualItemCount,
            $matchCount,
            $mismatchCount
        );

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
            'runnerEvidence' => self::runnerNotRunEvidence(),
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
            'currentMediaBagSignature' => $currentMediaBagSignature,
            'mediaBagSignatures' => $signatures,
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
            $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
            if ($runner !== []) {
                $lines[] = sprintf(
                    'runnerEvidence: status=%s plan=%s executed=%s',
                    (string) ($runner['status'] ?? 'unknown'),
                    (string) ($runner['commandPlanStatus'] ?? 'unknown'),
                    (($runner['executed'] ?? null) === false) ? 'false' : 'unknown'
                );
            }
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
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        if ($runner !== []) {
            $lines[] = sprintf(
                'runnerEvidence: status=%s plan=%s executed=%s',
                (string) ($runner['status'] ?? 'unknown'),
                (string) ($runner['commandPlanStatus'] ?? 'unknown'),
                (($runner['executed'] ?? null) === false) ? 'false' : 'unknown'
            );
        }
        $signature = is_array($report['currentMediaBagSignature'] ?? null) ? $report['currentMediaBagSignature'] : [];
        if ($signature !== []) {
            $signatureValidation = is_array($signature['validation'] ?? null) ? $signature['validation'] : [];
            $lines[] = sprintf(
                'currentMediaBagSignature: status=%s matchesExpected=%s sha256=%s expected=%s',
                (string) ($signatureValidation['status'] ?? 'unknown'),
                (($signature['matchesExpected'] ?? false) === true) ? 'true' : 'false',
                (string) ($signature['sha256'] ?? ''),
                (string) ($signature['expectedSha256'] ?? '')
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
     * @param array<string, mixed> $report
     */
    public static function hasRequiredCurrentMediaBagSignatures(array $report): bool
    {
        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && ($report['mediaBagSignatures'] ?? null) === self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES
            && self::hasRequiredCurrentMediaBagSignature($report);
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredCurrentMediaBagSignature(array $report): bool
    {
        $signature = is_array($report['currentMediaBagSignature'] ?? null) ? $report['currentMediaBagSignature'] : [];
        $validation = is_array($signature['validation'] ?? null) ? $signature['validation'] : [];

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && ($signature['kind'] ?? null) === self::CURRENT_MEDIA_BAG_SIGNATURE_KIND
            && ($signature['algorithm'] ?? null) === self::CURRENT_MEDIA_BAG_SIGNATURE_ALGORITHM
            && ($signature['scope'] ?? null) === self::CURRENT_MEDIA_BAG_SIGNATURE_SCOPE
            && (int) ($signature['caseCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES)
            && (int) ($signature['expectedCaseCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES)
            && (int) ($signature['expectedMediaItemCount'] ?? -1) === self::expectedCurrentMediaBagItemCount()
            && (int) ($signature['actualMediaItemCount'] ?? -1) === self::expectedCurrentMediaBagItemCount()
            && (int) ($signature['mediaBagMatchCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES)
            && (int) ($signature['mediaBagMismatchCount'] ?? -1) === 0
            && ($signature['sha256'] ?? null) === self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURE_SHA256
            && ($signature['expectedSha256'] ?? null) === self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURE_SHA256
            && ($signature['hashMatchesExpected'] ?? null) === true
            && ($signature['matchesExpected'] ?? null) === true
            && ($validation['status'] ?? null) === 'valid-checked-in-current-epub-media-bag-signature'
            && ($validation['issues'] ?? null) === []
            && ($validation['caseSignaturesMatchExpected'] ?? null) === true
            && ($validation['countsMatchExpected'] ?? null) === true;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRunnerNotRunEvidence(array $report): bool
    {
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];

        return ($runner['status'] ?? null) === 'not-run'
            && ($runner['executed'] ?? null) === false
            && array_key_exists('command', $runner)
            && $runner['command'] === null
            && array_key_exists('result', $runner)
            && $runner['result'] === null
            && array_key_exists('resultArtifact', $runner)
            && $runner['resultArtifact'] === null;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRunnerPlanEvidence(array $report): bool
    {
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $binding = is_array($runner['upstreamBinding'] ?? null) ? $runner['upstreamBinding'] : [];
        $target = is_array($runner['target'] ?? null) ? $runner['target'] : [];

        return self::hasRunnerNotRunEvidence($report)
            && ($runner['commandPlanStatus'] ?? null) === 'planned-not-run'
            && ($binding['name'] ?? null) === 'jgm/pandoc'
            && ($binding['expectedCommit'] ?? null) === self::EXPECTED_UPSTREAM_COMMIT
            && ($binding['entryPoint'] ?? null) === 'test/test-pandoc.hs'
            && ($binding['readerTestModule'] ?? null) === 'test/Tests/Readers/EPUB.hs'
            && ($target['testSuite'] ?? null) === self::RUNNER_TEST_SUITE
            && ($target['tastyGroupPath'] ?? null) === self::RUNNER_TASTY_GROUP_PATH
            && ($target['tastyPattern'] ?? null) === self::RUNNER_TASTY_PATTERN
            && ($runner['futureCommands'] ?? null) === self::runnerFutureCommands()
            && ($runner['requiredTranscripts'] ?? null) === self::RUNNER_REQUIRED_TRANSCRIPTS
            && ($runner['requiredArtifacts'] ?? null) === self::RUNNER_REQUIRED_ARTIFACTS;
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
            'runnerEvidence' => self::runnerNotRunEvidence(),
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
            'currentMediaBagSignature' => self::notEvaluatedCurrentMediaBagSignature($reason),
            'mediaBagSignatures' => [],
            'orderedRemainingGaps' => self::orderedRemainingGaps(false, 0, 0, 0, 0),
        ];
    }

    /**
     * @param list<array{path: string, mime: string, size: int}> $expectedBag
     * @param list<array{path: string, mime: string, size: int}> $actualBag
     * @return array{case: string, fixture: string, expectedItemCount: int, actualItemCount: int, matchesExpected: bool, expectedBag: list<array{path: string, mime: string, size: int}>, actualBag: list<array{path: string, mime: string, size: int}>}
     */
    private function mediaBagSignature(string $caseName, string $fixture, array $expectedBag, array $actualBag, bool $matchesExpected): array
    {
        return [
            'case' => $caseName,
            'fixture' => $fixture,
            'expectedItemCount' => count($expectedBag),
            'actualItemCount' => count($actualBag),
            'matchesExpected' => $matchesExpected,
            'expectedBag' => $expectedBag,
            'actualBag' => $actualBag,
        ];
    }

    /**
     * @param list<array<string, mixed>> $signatures
     * @return array<string, mixed>
     */
    private static function currentMediaBagSignature(
        array $signatures,
        int $totalCaseCount,
        int $comparedCaseCount,
        int $epubParsedCount,
        int $parseFailureCount,
        int $expectedItemCount,
        int $actualItemCount,
        int $matchCount,
        int $mismatchCount
    ): array {
        $payload = self::currentMediaBagSignaturePayload(
            $signatures,
            $totalCaseCount,
            $comparedCaseCount,
            $epubParsedCount,
            $parseFailureCount,
            $expectedItemCount,
            $actualItemCount,
            $matchCount,
            $mismatchCount
        );
        $sha256 = hash('sha256', self::canonicalJson($payload));
        $caseSignaturesMatchExpected = $signatures === self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES;
        $countsMatchExpected = $totalCaseCount === count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES)
            && $comparedCaseCount === count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES)
            && $epubParsedCount === count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES)
            && $parseFailureCount === 0
            && $expectedItemCount === self::expectedCurrentMediaBagItemCount()
            && $actualItemCount === self::expectedCurrentMediaBagItemCount()
            && $matchCount === count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES)
            && $mismatchCount === 0;
        $hashMatchesExpected = $sha256 === self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURE_SHA256;
        $issues = [];
        if (!$caseSignaturesMatchExpected) {
            $issues[] = 'media-bag-case-signatures-do-not-match-expected-snapshot';
        }
        if (!$countsMatchExpected) {
            $issues[] = 'media-bag-counters-do-not-match-expected-snapshot';
        }
        if (!$hashMatchesExpected) {
            $issues[] = 'media-bag-signature-sha256-mismatch';
        }

        return [
            'kind' => self::CURRENT_MEDIA_BAG_SIGNATURE_KIND,
            'algorithm' => self::CURRENT_MEDIA_BAG_SIGNATURE_ALGORITHM,
            'scope' => self::CURRENT_MEDIA_BAG_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'caseCount' => $comparedCaseCount,
            'expectedCaseCount' => count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES),
            'expectedMediaItemCount' => $expectedItemCount,
            'actualMediaItemCount' => $actualItemCount,
            'mediaBagMatchCount' => $matchCount,
            'mediaBagMismatchCount' => $mismatchCount,
            'sha256' => $sha256,
            'expectedSha256' => self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURE_SHA256,
            'hashMatchesExpected' => $hashMatchesExpected,
            'matchesExpected' => $issues === [],
            'validation' => [
                'status' => $issues === []
                    ? 'valid-checked-in-current-epub-media-bag-signature'
                    : 'invalid-checked-in-current-epub-media-bag-signature',
                'issues' => $issues,
                'caseSignaturesMatchExpected' => $caseSignaturesMatchExpected,
                'countsMatchExpected' => $countsMatchExpected,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedCurrentMediaBagSignature(string $reason): array
    {
        return [
            'kind' => self::CURRENT_MEDIA_BAG_SIGNATURE_KIND,
            'algorithm' => self::CURRENT_MEDIA_BAG_SIGNATURE_ALGORITHM,
            'scope' => self::CURRENT_MEDIA_BAG_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'caseCount' => 0,
            'expectedCaseCount' => count(self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES),
            'expectedMediaItemCount' => 0,
            'actualMediaItemCount' => 0,
            'mediaBagMatchCount' => 0,
            'mediaBagMismatchCount' => 0,
            'sha256' => null,
            'expectedSha256' => self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURE_SHA256,
            'hashMatchesExpected' => false,
            'matchesExpected' => false,
            'validation' => [
                'status' => 'not-evaluated-source-directory-unavailable',
                'issues' => [$reason],
                'caseSignaturesMatchExpected' => false,
                'countsMatchExpected' => false,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $signatures
     * @return array<string, mixed>
     */
    private static function currentMediaBagSignaturePayload(
        array $signatures,
        int $totalCaseCount,
        int $comparedCaseCount,
        int $epubParsedCount,
        int $parseFailureCount,
        int $expectedItemCount,
        int $actualItemCount,
        int $matchCount,
        int $mismatchCount
    ): array {
        return [
            'schemaVersion' => 1,
            'mediaBagComparison' => [
                'totalCaseCount' => $totalCaseCount,
                'comparedCaseCount' => $comparedCaseCount,
                'epubParsedCount' => $epubParsedCount,
                'parseFailureCount' => $parseFailureCount,
                'expectedMediaItemCount' => $expectedItemCount,
                'actualMediaItemCount' => $actualItemCount,
                'mediaBagMatchCount' => $matchCount,
                'mediaBagMismatchCount' => $mismatchCount,
                'mediaBagSignatures' => $signatures,
            ],
        ];
    }

    private static function expectedCurrentMediaBagItemCount(): int
    {
        $count = 0;
        foreach (self::CHECKED_IN_CURRENT_MEDIA_BAG_SIGNATURES as $signature) {
            $count += (int) ($signature['expectedItemCount'] ?? 0);
        }

        return $count;
    }

    private static function canonicalJson(mixed $value): string
    {
        $json = json_encode(
            self::canonicalValue($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode media-bag signature payload.');
        }

        return $json;
    }

    private static function canonicalValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::canonicalValue($item), $value);
        }

        $normalized = [];
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        foreach ($keys as $key) {
            $normalized[(string) $key] = self::canonicalValue($value[$key]);
        }

        return $normalized;
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

    /**
     * @return array<string, mixed>
     */
    private static function runnerNotRunEvidence(): array
    {
        return [
            'runner' => 'Cabal/Tasty Pandoc EPUB reader media-bag suite',
            'scope' => 'upstream-haskell-runner',
            'status' => 'not-run',
            'executed' => false,
            'command' => null,
            'result' => null,
            'resultArtifact' => null,
            'commandPlanStatus' => 'planned-not-run',
            'upstreamBinding' => [
                'name' => 'jgm/pandoc',
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'entryPoint' => 'test/test-pandoc.hs',
                'readerTestModule' => 'test/Tests/Readers/EPUB.hs',
            ],
            'target' => [
                'testSuite' => self::RUNNER_TEST_SUITE,
                'tastyGroupPath' => self::RUNNER_TASTY_GROUP_PATH,
                'tastyPattern' => self::RUNNER_TASTY_PATTERN,
            ],
            'blockers' => [
                'no committed upstream test:test-pandoc EPUB media-bag runner transcript or result artifact is present',
                'this PHP media-bag comparison gate intentionally does not invoke Cabal/Tasty or hydrate Haskell build dependencies',
                'a future runner claim must be bound to the pinned upstream commit and exact targeted EPUB media-bag Tasty pattern',
            ],
            'futureCommands' => self::runnerFutureCommands(),
            'requiredTranscripts' => self::RUNNER_REQUIRED_TRANSCRIPTS,
            'requiredArtifacts' => self::RUNNER_REQUIRED_ARTIFACTS,
            'reason' => 'This PHP media-bag comparison evidence packet is generated without executing the upstream Haskell runner.',
            'claim' => 'No upstream Haskell runner parity is claimed.',
        ];
    }

    /**
     * @return list<array{purpose: string, program: string, arguments: list<string>}>
     */
    private static function runnerFutureCommands(): array
    {
        return [
            [
                'purpose' => 'prepare runner dependencies in an isolated build directory',
                'program' => 'cabal',
                'arguments' => [
                    'v2-build',
                    '--offline',
                    '--dry-run',
                    '--only-dependencies',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_TEST_SUITE,
                ],
            ],
            [
                'purpose' => 'list targeted EPUB reader media-bag tests',
                'program' => 'cabal',
                'arguments' => [
                    'v2-run',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_TEST_SUITE,
                    '--',
                    '--list-tests',
                    '--pattern',
                    self::RUNNER_TASTY_PATTERN,
                ],
            ],
            [
                'purpose' => 'run targeted EPUB reader media-bag tests',
                'program' => 'cabal',
                'arguments' => [
                    'v2-run',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_TEST_SUITE,
                    '--',
                    '--pattern',
                    self::RUNNER_TASTY_PATTERN,
                ],
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
