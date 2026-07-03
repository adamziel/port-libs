<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-epub-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-epub-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-epub-root';

    private const CURRENT_READER_STATIC_SIGNATURE_KIND = 'checked-in-current-epub-reader-static-signature';
    private const CURRENT_READER_STATIC_SIGNATURE_ALGORITHM = 'sha256-canonical-json-v1';
    private const CURRENT_READER_STATIC_SIGNATURE_SCOPE = 'checked-in-current-upstream-epub-reader-static-6-case-media-expectation-and-fixture-identity-snapshot';
    private const CHECKED_IN_CURRENT_STATIC_SIGNATURE_SHA256 = 'c3551818c84a8100f79266b00b653f14baa5d4ee4ae1d22db36eb8c19844e22c';
    private const CHECKED_IN_CURRENT_STATIC_MEDIA_BAG_TEST_COUNT = 6;
    private const CHECKED_IN_CURRENT_STATIC_FIXTURE_REFERENCE_COUNT = 6;
    private const CHECKED_IN_CURRENT_STATIC_EXPECTED_MEDIA_ITEM_COUNT = 10;
    private const REFERENCED_FIXTURE_IDENTITY_KIND = 'checked-in-current-epub-reader-referenced-fixture-identity';
    private const REFERENCED_FIXTURE_IDENTITY_SCOPE = 'checked-in-current-upstream-epub-reader-6-referenced-epub-fixture-snapshot';
    private const REFERENCED_FIXTURE_IDENTITY_HASH_ALGORITHM = 'sha256';
    private const CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES = [
        'epub/epub2_cover.epub' => [
            'sha256' => '4af73a135aa632cbf0c00b2889a5fc1d39a59a77fa294fdeff5ede72ff6ffed1',
            'bytes' => 11794,
        ],
        'epub/epub2_no_cover.epub' => [
            'sha256' => '8369dbe5cf315f1fe00f9dd1bf7c500cc663d7648edbf0d7b6a9b4d785fedf4e',
            'bytes' => 3584,
        ],
        'epub/epub2_picture.epub' => [
            'sha256' => '6049dde9e1d0ebcd175a8c5b937984f349af996e293310eafbce09e4c7384495',
            'bytes' => 11742,
        ],
        'epub/img.epub' => [
            'sha256' => 'f2c25e0e0612b7ac33a8d6a1c9719a86e7d2a0290472fc7d8b5068de781a822f',
            'bytes' => 20478,
        ],
        'epub/img_no_cover.epub' => [
            'sha256' => '3063f5e9b9610df1ddcc682ce49c293bcf681f1958700a5b6c3eda344383cf2a',
            'bytes' => 10602,
        ],
        'epub/wasteland.epub' => [
            'sha256' => '151ec5dbca33e39a4e3f6894e92fa5a101290bdeaaa792e0700595971456a278',
            'bytes' => 25840,
        ],
    ];
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

    private readonly string $repoRoot;
    private readonly string $upstreamRoot;
    private readonly ?string $fixtureBase;

    public function __construct(string $repoRoot, string $upstreamRoot = self::DEFAULT_RELATIVE_UPSTREAM_ROOT, ?string $fixtureBase = null)
    {
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('Repository root must not be empty');
        }
        if ($upstreamRoot === '') {
            throw new \InvalidArgumentException('Upstream root must not be empty');
        }
        if ($fixtureBase === '') {
            throw new \InvalidArgumentException('Fixture base must not be empty');
        }

        $this->repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $this->upstreamRoot = $upstreamRoot;
        $this->fixtureBase = $fixtureBase;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $root = $this->absoluteUpstreamRoot();
        if (!is_dir($root)) {
            return [
                'schemaVersion' => 1,
                'tool' => self::TOOL_NAME,
                'status' => self::STATUS_SKIPPED_MISSING_SOURCE,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'root' => $this->displayPath($root),
                    'commit' => null,
                    'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                ],
                'denominator' => $this->emptyDenominator(),
                'sourceInventory' => $this->emptySourceInventory(),
                'referencedFixtureIdentity' => self::notEvaluatedReferencedFixtureIdentity('missing-upstream-root'),
                'currentReaderStaticSignature' => self::notEvaluatedCurrentReaderStaticSignature('missing-upstream-root'),
                'runnerEvidence' => self::runnerNotRunEvidence(),
                'validation' => [
                    'status' => 'not-evaluated-missing-upstream-root',
                    'issues' => ['missing-upstream-root'],
                ],
                'claim' => self::claim(),
                'claimBoundaries' => self::claimBoundaries(),
            ];
        }

        $readerTestPath = $root . '/test/Tests/Readers/EPUB.hs';
        $fixtureRoot = $this->absoluteFixtureBase() ?? $root;
        $readerCases = is_file($readerTestPath)
            ? self::parseReaderCasesFromSource((string) file_get_contents($readerTestPath))
            : [];
        $validationIssues = $this->validationIssues($root, $fixtureRoot, $readerCases);
        $denominator = [
            'mediaBagTestCount' => count($readerCases),
            'fixtureReferenceCount' => count($this->fixtureReferences($readerCases)),
            'expectedMediaItemCount' => $this->expectedMediaItemCount($readerCases),
            'readerCases' => $readerCases,
            'referencedFixtures' => $this->fixtureReferences($readerCases),
            'missingReferencedFiles' => $this->missingReferencedFiles($root, $fixtureRoot, $readerCases),
            'unreferencedEpubFixtures' => $this->unreferencedEpubFixtures($root, $fixtureRoot, $readerCases),
        ];
        $referencedFixtureIdentity = $this->referencedFixtureIdentity($root, $fixtureRoot, $readerCases);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $this->gitHead($root),
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerTestModule' => 'test/Tests/Readers/EPUB.hs',
                'fixtureDirectory' => 'test/epub',
                'resolvedFixtureBase' => $this->displayPath($fixtureRoot),
                'resolvedFixtureDirectory' => $this->fixtureDirectoryDisplayPath(),
                'readerSource' => 'src/Text/Pandoc/Readers/EPUB.hs',
                'readerSourceRequired' => !$this->hasExplicitFixtureBase(),
            ],
            'denominator' => $denominator,
            'sourceInventory' => $this->sourceInventory($root),
            'referencedFixtureIdentity' => $referencedFixtureIdentity,
            'currentReaderStaticSignature' => self::currentReaderStaticSignature($denominator, $validationIssues, $referencedFixtureIdentity),
            'runnerEvidence' => self::runnerNotRunEvidence(),
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-epub-reader-mediabag-denominator' : 'invalid-upstream-epub-reader-mediabag-denominator',
                'issues' => $validationIssues,
            ],
            'claim' => self::claim(),
            'claimBoundaries' => self::claimBoundaries(),
        ];
    }

    /**
     * @return list<array{name: string, epub: string, bagName: string, expectedBag: list<array{path: string, mime: string, size: int}>, expectedBagItemCount: int, expectedBagMissing: bool}>
     */
    public static function parseReaderCasesFromSource(string $source): array
    {
        $bagDefinitions = self::parseBagDefinitions($source);
        $cases = [];
        $pattern = '/\btestCase\s+"([^"]+)"\s*\(\s*testMediaBag\s+"([^"]+\.epub)"\s+([A-Za-z_][A-Za-z0-9_\']*)\s*\)/s';
        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $bagName = (string) $match[3];
            $expectedBag = $bagDefinitions[$bagName] ?? [];
            $cases[] = [
                'name' => (string) $match[1],
                'epub' => (string) $match[2],
                'bagName' => $bagName,
                'expectedBag' => $expectedBag,
                'expectedBagItemCount' => count($expectedBag),
                'expectedBagMissing' => !array_key_exists($bagName, $bagDefinitions),
            ];
        }

        return $cases;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
        $upstream = is_array($report['upstream'] ?? null) ? $report['upstream'] : [];
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $signature = is_array($report['currentReaderStaticSignature'] ?? null) ? $report['currentReaderStaticSignature'] : [];
        $signatureValidation = is_array($signature['validation'] ?? null) ? $signature['validation'] : [];
        $identity = is_array($report['referencedFixtureIdentity'] ?? null) ? $report['referencedFixtureIdentity'] : [];
        $identityValidation = is_array($identity['validation'] ?? null) ? $identity['validation'] : [];

        return implode(PHP_EOL, [
            'Pandoc EPUB reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Upstream: ' . (string) ($upstream['commit'] ?? 'unknown')
                . ' expected=' . (string) ($upstream['expectedCommit'] ?? self::EXPECTED_UPSTREAM_COMMIT),
            'Media bag tests: ' . (int) ($denominator['mediaBagTestCount'] ?? 0),
            'Referenced EPUB fixtures: ' . (int) ($denominator['fixtureReferenceCount'] ?? 0),
            'Expected media items: ' . (int) ($denominator['expectedMediaItemCount'] ?? 0),
            'Referenced fixture identity: ' . (string) ($identityValidation['status'] ?? 'unknown'),
            'Static current signature: ' . (string) ($signatureValidation['status'] ?? 'unknown'),
            'Runner status: ' . (string) ($runner['status'] ?? 'unknown'),
            'Runner plan: ' . (string) ($runner['commandPlanStatus'] ?? 'unknown'),
            'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
            'No upstream Haskell/Cabal runner result, EPUB writer parity, or full EPUB feature parity is asserted.',
        ]) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredMediaBagTestCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['mediaBagTestCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredFixtureReferenceCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['fixtureReferenceCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredExpectedMediaItemCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['expectedMediaItemCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-epub-reader-mediabag-denominator'
            && ($validation['issues'] ?? null) === [];
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
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticCurrentSignature(array $report): bool
    {
        $signature = is_array($report['currentReaderStaticSignature'] ?? null) ? $report['currentReaderStaticSignature'] : [];
        $validation = is_array($signature['validation'] ?? null) ? $signature['validation'] : [];

        return ($report['status'] ?? null) === self::STATUS_COMPLETED
            && self::hasNoValidationIssues($report)
            && self::hasRunnerNotRunEvidence($report)
            && self::hasRequiredReferencedFixtureIdentity($report)
            && (int) ($signature['mediaBagTestCount'] ?? -1) === self::CHECKED_IN_CURRENT_STATIC_MEDIA_BAG_TEST_COUNT
            && (int) ($signature['fixtureReferenceCount'] ?? -1) === self::CHECKED_IN_CURRENT_STATIC_FIXTURE_REFERENCE_COUNT
            && (int) ($signature['expectedMediaItemCount'] ?? -1) === self::CHECKED_IN_CURRENT_STATIC_EXPECTED_MEDIA_ITEM_COUNT
            && ($signature['kind'] ?? null) === self::CURRENT_READER_STATIC_SIGNATURE_KIND
            && ($signature['algorithm'] ?? null) === self::CURRENT_READER_STATIC_SIGNATURE_ALGORITHM
            && ($signature['scope'] ?? null) === self::CURRENT_READER_STATIC_SIGNATURE_SCOPE
            && ($signature['sha256'] ?? null) === self::CHECKED_IN_CURRENT_STATIC_SIGNATURE_SHA256
            && ($signature['expectedSha256'] ?? null) === self::CHECKED_IN_CURRENT_STATIC_SIGNATURE_SHA256
            && ($signature['hashMatchesExpected'] ?? null) === true
            && ($signature['matchesExpected'] ?? null) === true
            && ($validation['status'] ?? null) === 'valid-checked-in-current-epub-reader-static-signature'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredReferencedFixtureIdentity(array $report): bool
    {
        $identity = is_array($report['referencedFixtureIdentity'] ?? null) ? $report['referencedFixtureIdentity'] : [];
        $validation = is_array($identity['validation'] ?? null) ? $identity['validation'] : [];

        return ($report['status'] ?? null) === self::STATUS_COMPLETED
            && self::hasNoValidationIssues($report)
            && ($identity['kind'] ?? null) === self::REFERENCED_FIXTURE_IDENTITY_KIND
            && ($identity['scope'] ?? null) === self::REFERENCED_FIXTURE_IDENTITY_SCOPE
            && ($identity['hashAlgorithm'] ?? null) === self::REFERENCED_FIXTURE_IDENTITY_HASH_ALGORITHM
            && (int) ($identity['expectedFileCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES)
            && (int) ($identity['observedFileCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES)
            && (int) ($identity['presentFileCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES)
            && (int) ($identity['missingFileCount'] ?? -1) === 0
            && ($identity['expectedPaths'] ?? null) === array_keys(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES)
            && ($identity['observedPaths'] ?? null) === array_keys(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES)
            && ($identity['matchesExpected'] ?? null) === true
            && ($validation['status'] ?? null) === 'valid-checked-in-current-epub-reader-referenced-fixture-identity'
            && ($validation['issues'] ?? null) === [];
    }

    private static function claim(): string
    {
        return 'Parses the pinned upstream Tests.Readers.EPUB module to establish the current EPUB reader media-bag test denominator and expected media directory tuples.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the count and fixture paths of upstream EPUB media-bag tests in Tests.Readers.EPUB',
                'the expected media-bag path, MIME type, and byte-size tuples embedded in the upstream test module',
                'that every referenced EPUB fixture exists in the upstream checkout or explicit checked-in fixture base',
                'that the upstream EPUB reader source file is present when validating a full upstream checkout without an explicit checked-in fixture base',
                'the checked-in current referenced EPUB fixture SHA-256 and byte identity snapshot when explicitly gated',
                'the checked-in current static EPUB reader denominator and referenced fixture identity signature when explicitly gated',
                'that upstream Haskell runner evidence is explicitly not-run',
                'the future upstream runner command plan targets test:test-pandoc Readers/EPUB/EPUB Mediabag at the pinned upstream commit without execution',
            ],
            'doesNotAssert' => [
                'that upstream Haskell/Cabal/Tasty tests were executed',
                'full upstream Tests.Readers.EPUB runner parity',
                'that local PHP output matches upstream media-bag output',
                'EPUB writer parity',
                'full EPUB feature parity beyond the upstream reader media-bag tests',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerNotRunEvidence(): array
    {
        return [
            'runner' => 'Cabal/Tasty Pandoc EPUB reader suite',
            'scope' => 'upstream-haskell-runner',
            'status' => 'not-run',
            'executed' => false,
            'command' => null,
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
                'no committed upstream test:test-pandoc EPUB runner transcript or result artifact is present',
                'this PHP evidence gate intentionally does not invoke Cabal/Tasty or hydrate Haskell build dependencies',
                'a future runner claim must be bound to the pinned upstream commit and exact targeted EPUB Tasty pattern',
            ],
            'futureCommands' => self::runnerFutureCommands(),
            'requiredTranscripts' => self::RUNNER_REQUIRED_TRANSCRIPTS,
            'requiredArtifacts' => self::RUNNER_REQUIRED_ARTIFACTS,
            'reason' => 'This PHP evidence packet is generated without executing the upstream Haskell runner.',
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

    /**
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'mediaBagTestCount' => 0,
            'fixtureReferenceCount' => 0,
            'expectedMediaItemCount' => 0,
            'readerCases' => [],
            'referencedFixtures' => [],
            'missingReferencedFiles' => [],
            'unreferencedEpubFixtures' => [],
        ];
    }

    /**
     * @param array<string, mixed> $denominator
     * @param list<string> $validationIssues
     * @param array<string, mixed> $referencedFixtureIdentity
     * @return array<string, mixed>
     */
    private static function currentReaderStaticSignature(array $denominator, array $validationIssues, array $referencedFixtureIdentity): array
    {
        $payload = self::currentReaderStaticSignaturePayload($denominator, $referencedFixtureIdentity);
        $sha256 = hash('sha256', self::canonicalJson($payload));
        $countsMatchExpected = (int) ($denominator['mediaBagTestCount'] ?? -1) === self::CHECKED_IN_CURRENT_STATIC_MEDIA_BAG_TEST_COUNT
            && (int) ($denominator['fixtureReferenceCount'] ?? -1) === self::CHECKED_IN_CURRENT_STATIC_FIXTURE_REFERENCE_COUNT
            && (int) ($denominator['expectedMediaItemCount'] ?? -1) === self::CHECKED_IN_CURRENT_STATIC_EXPECTED_MEDIA_ITEM_COUNT;
        $denominatorValidationMatchesExpected = $validationIssues === [];
        $identityValidation = is_array($referencedFixtureIdentity['validation'] ?? null)
            ? $referencedFixtureIdentity['validation']
            : [];
        $referencedFixtureIdentityMatchesExpected = ($referencedFixtureIdentity['matchesExpected'] ?? null) === true
            && ($identityValidation['status'] ?? null) === 'valid-checked-in-current-epub-reader-referenced-fixture-identity'
            && ($identityValidation['issues'] ?? null) === [];
        $hashMatchesExpected = $sha256 === self::CHECKED_IN_CURRENT_STATIC_SIGNATURE_SHA256;
        $issues = [];
        if (!$countsMatchExpected) {
            $issues[] = 'reader-static-denominator-counts-do-not-match-expected-snapshot';
        }
        if (!$denominatorValidationMatchesExpected) {
            $issues[] = 'reader-static-denominator-validation-issues';
        }
        if (!$referencedFixtureIdentityMatchesExpected) {
            $issues[] = 'reader-static-referenced-fixture-identity-does-not-match-expected-snapshot';
        }
        if (!$hashMatchesExpected) {
            $issues[] = 'reader-static-signature-sha256-mismatch';
        }

        return [
            'kind' => self::CURRENT_READER_STATIC_SIGNATURE_KIND,
            'algorithm' => self::CURRENT_READER_STATIC_SIGNATURE_ALGORITHM,
            'scope' => self::CURRENT_READER_STATIC_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'payloadIncludes' => [
                'Tests.Readers.EPUB media-bag test case names',
                'testMediaBag EPUB fixture references',
                'expected media-bag path, MIME type, and byte-size tuples',
                'missing referenced fixture file list',
                'referenced EPUB fixture SHA-256 and byte identities',
            ],
            'mediaBagTestCount' => (int) ($denominator['mediaBagTestCount'] ?? 0),
            'expectedMediaBagTestCount' => self::CHECKED_IN_CURRENT_STATIC_MEDIA_BAG_TEST_COUNT,
            'fixtureReferenceCount' => (int) ($denominator['fixtureReferenceCount'] ?? 0),
            'expectedFixtureReferenceCount' => self::CHECKED_IN_CURRENT_STATIC_FIXTURE_REFERENCE_COUNT,
            'expectedMediaItemCount' => (int) ($denominator['expectedMediaItemCount'] ?? 0),
            'expectedExpectedMediaItemCount' => self::CHECKED_IN_CURRENT_STATIC_EXPECTED_MEDIA_ITEM_COUNT,
            'sha256' => $sha256,
            'expectedSha256' => self::CHECKED_IN_CURRENT_STATIC_SIGNATURE_SHA256,
            'hashMatchesExpected' => $hashMatchesExpected,
            'matchesExpected' => $issues === [],
            'validation' => [
                'status' => $issues === []
                    ? 'valid-checked-in-current-epub-reader-static-signature'
                    : 'invalid-checked-in-current-epub-reader-static-signature',
                'issues' => $issues,
                'countsMatchExpected' => $countsMatchExpected,
                'denominatorValidationMatchesExpected' => $denominatorValidationMatchesExpected,
                'referencedFixtureIdentityMatchesExpected' => $referencedFixtureIdentityMatchesExpected,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedCurrentReaderStaticSignature(string $reason): array
    {
        return [
            'kind' => self::CURRENT_READER_STATIC_SIGNATURE_KIND,
            'algorithm' => self::CURRENT_READER_STATIC_SIGNATURE_ALGORITHM,
            'scope' => self::CURRENT_READER_STATIC_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'payloadIncludes' => [
                'Tests.Readers.EPUB media-bag test case names',
                'testMediaBag EPUB fixture references',
                'expected media-bag path, MIME type, and byte-size tuples',
                'missing referenced fixture file list',
                'referenced EPUB fixture SHA-256 and byte identities',
            ],
            'mediaBagTestCount' => 0,
            'expectedMediaBagTestCount' => self::CHECKED_IN_CURRENT_STATIC_MEDIA_BAG_TEST_COUNT,
            'fixtureReferenceCount' => 0,
            'expectedFixtureReferenceCount' => self::CHECKED_IN_CURRENT_STATIC_FIXTURE_REFERENCE_COUNT,
            'expectedMediaItemCount' => 0,
            'expectedExpectedMediaItemCount' => self::CHECKED_IN_CURRENT_STATIC_EXPECTED_MEDIA_ITEM_COUNT,
            'sha256' => null,
            'expectedSha256' => self::CHECKED_IN_CURRENT_STATIC_SIGNATURE_SHA256,
            'hashMatchesExpected' => false,
            'matchesExpected' => false,
            'validation' => [
                'status' => 'not-evaluated-source-directory-unavailable',
                'issues' => [$reason],
                'countsMatchExpected' => false,
                'denominatorValidationMatchesExpected' => false,
                'referencedFixtureIdentityMatchesExpected' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedReferencedFixtureIdentity(string $reason): array
    {
        return [
            'kind' => self::REFERENCED_FIXTURE_IDENTITY_KIND,
            'scope' => self::REFERENCED_FIXTURE_IDENTITY_SCOPE,
            'snapshotSchemaVersion' => 1,
            'hashAlgorithm' => self::REFERENCED_FIXTURE_IDENTITY_HASH_ALGORITHM,
            'expectedFileCount' => count(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES),
            'observedFileCount' => 0,
            'presentFileCount' => 0,
            'missingFileCount' => 0,
            'expectedPaths' => array_keys(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES),
            'observedPaths' => [],
            'missingExpectedPaths' => array_keys(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES),
            'unexpectedObservedPaths' => [],
            'totalBytes' => 0,
            'expectedTotalBytes' => array_sum(array_column(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES, 'bytes')),
            'files' => [],
            'matchesExpected' => false,
            'validation' => [
                'status' => 'not-evaluated-source-directory-unavailable',
                'issues' => [$reason],
                'pathsMatchExpected' => false,
                'contentsMatchExpected' => false,
                'allReferencedFilesPresent' => false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $denominator
     * @param array<string, mixed> $referencedFixtureIdentity
     * @return array<string, mixed>
     */
    private static function currentReaderStaticSignaturePayload(array $denominator, array $referencedFixtureIdentity): array
    {
        $readerCases = is_array($denominator['readerCases'] ?? null) ? $denominator['readerCases'] : [];

        return [
            'kind' => self::CURRENT_READER_STATIC_SIGNATURE_KIND,
            'snapshotSchemaVersion' => 1,
            'expectedUpstreamCommit' => self::EXPECTED_UPSTREAM_COMMIT,
            'readerTestModule' => 'test/Tests/Readers/EPUB.hs',
            'fixtureDirectory' => 'test/epub',
            'counts' => [
                'mediaBagTestCount' => (int) ($denominator['mediaBagTestCount'] ?? 0),
                'fixtureReferenceCount' => (int) ($denominator['fixtureReferenceCount'] ?? 0),
                'expectedMediaItemCount' => (int) ($denominator['expectedMediaItemCount'] ?? 0),
            ],
            'readerCases' => self::readerCaseSignatureSnapshot($readerCases),
            'referencedFixtures' => self::stringList($denominator['referencedFixtures'] ?? []),
            'missingReferencedFiles' => self::pathList($denominator['missingReferencedFiles'] ?? []),
            'referencedFixtureIdentity' => self::referencedFixtureIdentitySignatureSnapshot($referencedFixtureIdentity),
        ];
    }

    /**
     * @param array<string, mixed> $identity
     * @return array<string, mixed>
     */
    private static function referencedFixtureIdentitySignatureSnapshot(array $identity): array
    {
        $files = is_array($identity['files'] ?? null) ? $identity['files'] : [];

        return [
            'kind' => self::REFERENCED_FIXTURE_IDENTITY_KIND,
            'scope' => self::REFERENCED_FIXTURE_IDENTITY_SCOPE,
            'expectedFileCount' => (int) ($identity['expectedFileCount'] ?? 0),
            'observedFileCount' => (int) ($identity['observedFileCount'] ?? 0),
            'files' => self::referencedFixtureIdentityFileSignatureSnapshot($files),
        ];
    }

    /**
     * @param list<array<string, mixed>> $files
     * @return list<array{path: string, sha256: ?string, bytes: ?int}>
     */
    private static function referencedFixtureIdentityFileSignatureSnapshot(array $files): array
    {
        $snapshot = [];
        foreach ($files as $file) {
            $snapshot[] = [
                'path' => (string) ($file['path'] ?? ''),
                'sha256' => is_string($file['sha256'] ?? null) ? $file['sha256'] : null,
                'bytes' => is_int($file['bytes'] ?? null) ? $file['bytes'] : null,
            ];
        }

        usort(
            $snapshot,
            static fn (array $left, array $right): int => $left['path'] <=> $right['path']
        );

        return $snapshot;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<array<string, mixed>>
     */
    private static function readerCaseSignatureSnapshot(array $readerCases): array
    {
        $snapshot = [];
        foreach ($readerCases as $case) {
            $expectedBag = is_array($case['expectedBag'] ?? null) ? $case['expectedBag'] : [];
            $snapshot[] = [
                'name' => (string) ($case['name'] ?? ''),
                'epub' => (string) ($case['epub'] ?? ''),
                'bagName' => (string) ($case['bagName'] ?? ''),
                'expectedBagItemCount' => (int) ($case['expectedBagItemCount'] ?? 0),
                'expectedBagMissing' => ($case['expectedBagMissing'] ?? null) === true,
                'expectedBag' => self::expectedBagSignatureSnapshot($expectedBag),
            ];
        }

        return $snapshot;
    }

    /**
     * @param list<array<string, mixed>> $expectedBag
     * @return list<array{path: string, mime: string, size: int}>
     */
    private static function expectedBagSignatureSnapshot(array $expectedBag): array
    {
        $snapshot = [];
        foreach ($expectedBag as $item) {
            $snapshot[] = [
                'path' => (string) ($item['path'] ?? ''),
                'mime' => strtolower((string) ($item['mime'] ?? '')),
                'size' => (int) ($item['size'] ?? 0),
            ];
        }

        usort(
            $snapshot,
            static fn (array $left, array $right): int => [$left['path'], $left['mime'], $left['size']] <=> [$right['path'], $right['mime'], $right['size']]
        );

        return $snapshot;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    /**
     * @return list<string>
     */
    private static function pathList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $paths = [];
        foreach ($value as $item) {
            if (is_array($item) && is_string($item['path'] ?? null)) {
                $paths[] = $item['path'];
            }
        }

        return $paths;
    }

    private static function canonicalJson(mixed $value): string
    {
        $json = json_encode(
            self::canonicalValue($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode EPUB reader static signature payload.');
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
     * @return array<string, mixed>
     */
    private function emptySourceInventory(): array
    {
        return [
            'files' => [],
            'presentFileCount' => 0,
            'missingFileCount' => 0,
            'presentLineCount' => 0,
        ];
    }

    /**
     * @return array<string, list<array{path: string, mime: string, size: int}>>
     */
    private static function parseBagDefinitions(string $source): array
    {
        $definitions = [];
        if (preg_match_all('/^([A-Za-z_][A-Za-z0-9_\']*)\s*=\s*(\[[^\]]*\])/m', $source, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $definitions[(string) $match[1]] = self::parseBagTuples((string) $match[2]);
        }

        return $definitions;
    }

    /**
     * @return list<array{path: string, mime: string, size: int}>
     */
    private static function parseBagTuples(string $source): array
    {
        $items = [];
        if (preg_match_all('/\("([^"]+)"\s*,\s*"([^"]+)"\s*,\s*(\d+)\)/', $source, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $items[] = [
                'path' => (string) $match[1],
                'mime' => strtolower((string) $match[2]),
                'size' => (int) $match[3],
            ];
        }

        usort(
            $items,
            static fn (array $left, array $right): int => [$left['path'], $left['mime'], $left['size']] <=> [$right['path'], $right['mime'], $right['size']]
        );

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<string>
     */
    private function fixtureReferences(array $readerCases): array
    {
        $seen = [];
        $references = [];
        foreach ($readerCases as $case) {
            $fixture = is_string($case['epub'] ?? null) ? $case['epub'] : '';
            if ($fixture === '' || isset($seen[$fixture])) {
                continue;
            }
            $seen[$fixture] = true;
            $references[] = $fixture;
        }

        return $references;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     */
    private function expectedMediaItemCount(array $readerCases): int
    {
        $count = 0;
        foreach ($readerCases as $case) {
            $expectedBag = $case['expectedBag'] ?? [];
            if (is_array($expectedBag)) {
                $count += count($expectedBag);
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<array{path: string}>
     */
    private function missingReferencedFiles(string $root, string $fixtureRoot, array $readerCases): array
    {
        $missing = [];
        foreach ($this->fixtureReferences($readerCases) as $fixture) {
            $path = $this->fixturePath($root, $fixtureRoot, $fixture);
            if (!is_file($path)) {
                $missing[] = ['path' => $this->fixtureDisplayPath($fixture)];
            }
        }

        return $missing;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return array<string, mixed>
     */
    private function referencedFixtureIdentity(string $root, string $fixtureRoot, array $readerCases): array
    {
        $references = $this->fixtureReferences($readerCases);
        sort($references, SORT_STRING);

        $files = [];
        foreach ($references as $fixture) {
            $path = $this->fixturePath($root, $fixtureRoot, $fixture);
            $present = is_file($path);
            $sha256 = $present ? hash_file('sha256', $path) : null;
            $sha256 = is_string($sha256) ? $sha256 : null;
            $bytes = $present ? filesize($path) : null;
            $bytes = is_int($bytes) ? $bytes : null;
            $expected = self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES[$fixture] ?? null;
            $expectedSha256 = is_array($expected) ? (string) $expected['sha256'] : null;
            $expectedBytes = is_array($expected) ? (int) $expected['bytes'] : null;
            $matchesExpected = $present
                && $sha256 === $expectedSha256
                && $bytes === $expectedBytes;

            $files[] = [
                'path' => $fixture,
                'displayPath' => $this->fixtureDisplayPath($fixture),
                'present' => $present,
                'sha256' => $sha256,
                'bytes' => $bytes,
                'expectedSha256' => $expectedSha256,
                'expectedBytes' => $expectedBytes,
                'matchesExpected' => $matchesExpected,
            ];
        }

        $expectedPaths = array_keys(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES);
        $observedPaths = array_map(static fn (array $file): string => (string) $file['path'], $files);
        sort($observedPaths, SORT_STRING);
        $missingExpectedPaths = array_values(array_diff($expectedPaths, $observedPaths));
        $unexpectedObservedPaths = array_values(array_diff($observedPaths, $expectedPaths));
        $presentFileCount = count(array_filter($files, static fn (array $file): bool => $file['present'] === true));
        $missingFileCount = count($files) - $presentFileCount;
        $contentsMatchExpected = $files !== []
            && count(array_filter($files, static fn (array $file): bool => $file['matchesExpected'] !== true)) === 0;
        $pathsMatchExpected = $observedPaths === $expectedPaths;
        $allReferencedFilesPresent = $missingFileCount === 0;
        $issues = [];
        if (!$pathsMatchExpected) {
            $issues[] = 'referenced-fixture-paths-do-not-match-expected-snapshot';
        }
        if (!$allReferencedFilesPresent) {
            $issues[] = 'missing-referenced-fixture-identity-files';
        }
        if (!$contentsMatchExpected) {
            $issues[] = 'referenced-fixture-identity-content-does-not-match-expected-snapshot';
        }

        return [
            'kind' => self::REFERENCED_FIXTURE_IDENTITY_KIND,
            'scope' => self::REFERENCED_FIXTURE_IDENTITY_SCOPE,
            'snapshotSchemaVersion' => 1,
            'hashAlgorithm' => self::REFERENCED_FIXTURE_IDENTITY_HASH_ALGORITHM,
            'expectedFileCount' => count(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES),
            'observedFileCount' => count($files),
            'presentFileCount' => $presentFileCount,
            'missingFileCount' => $missingFileCount,
            'expectedPaths' => $expectedPaths,
            'observedPaths' => $observedPaths,
            'missingExpectedPaths' => $missingExpectedPaths,
            'unexpectedObservedPaths' => $unexpectedObservedPaths,
            'totalBytes' => array_sum(array_map(
                static fn (array $file): int => is_int($file['bytes'] ?? null) ? $file['bytes'] : 0,
                $files
            )),
            'expectedTotalBytes' => array_sum(array_column(self::CHECKED_IN_CURRENT_REFERENCED_FIXTURE_IDENTITIES, 'bytes')),
            'files' => $files,
            'matchesExpected' => $issues === [],
            'validation' => [
                'status' => $issues === []
                    ? 'valid-checked-in-current-epub-reader-referenced-fixture-identity'
                    : 'invalid-checked-in-current-epub-reader-referenced-fixture-identity',
                'issues' => $issues,
                'pathsMatchExpected' => $pathsMatchExpected,
                'contentsMatchExpected' => $contentsMatchExpected,
                'allReferencedFilesPresent' => $allReferencedFilesPresent,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<string>
     */
    private function unreferencedEpubFixtures(string $root, string $fixtureRoot, array $readerCases): array
    {
        $fixtureDirectory = $this->fixtureDirectory($root, $fixtureRoot);
        if (!is_dir($fixtureDirectory)) {
            return [];
        }

        $referenced = array_fill_keys($this->fixtureReferences($readerCases), true);
        $unreferenced = [];
        foreach (glob($fixtureDirectory . '/*.epub') ?: [] as $path) {
            $relative = 'epub/' . basename($path);
            if (!isset($referenced[$relative])) {
                $unreferenced[] = $relative;
            }
        }
        sort($unreferenced, SORT_STRING);

        return $unreferenced;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<string>
     */
    private function validationIssues(string $root, string $fixtureRoot, array $readerCases): array
    {
        $issues = [];
        if (!is_file($root . '/test/Tests/Readers/EPUB.hs')) {
            $issues[] = 'missing-reader-test-module';
        }
        if (!$this->hasExplicitFixtureBase() && !is_file($root . '/src/Text/Pandoc/Readers/EPUB.hs')) {
            $issues[] = 'missing-reader-source';
        }
        if ($readerCases === []) {
            $issues[] = 'no-epub-mediabag-tests';
        }

        foreach ($readerCases as $case) {
            if (($case['expectedBagMissing'] ?? false) === true) {
                $issues[] = 'missing-expected-mediabag-definition';
                break;
            }
        }

        if ($this->missingReferencedFiles($root, $fixtureRoot, $readerCases) !== []) {
            $issues[] = 'missing-referenced-fixture-files';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceInventory(string $root): array
    {
        $files = [];
        foreach ([
            'test/Tests/Readers/EPUB.hs',
            'src/Text/Pandoc/Readers/EPUB.hs',
        ] as $relativePath) {
            $path = $root . '/' . $relativePath;
            $present = is_file($path);
            $files[] = [
                'path' => $relativePath,
                'present' => $present,
                'lineCount' => $present ? $this->lineCount($path) : 0,
            ];
        }

        return [
            'files' => $files,
            'readerSourceRequired' => !$this->hasExplicitFixtureBase(),
            'presentFileCount' => count(array_filter($files, static fn (array $file): bool => $file['present'] === true)),
            'missingFileCount' => count(array_filter($files, static fn (array $file): bool => $file['present'] === false)),
            'presentLineCount' => array_sum(array_map(static fn (array $file): int => (int) $file['lineCount'], $files)),
        ];
    }

    private function lineCount(string $path): int
    {
        $contents = file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            return 0;
        }

        return substr_count($contents, "\n") + (str_ends_with($contents, "\n") ? 0 : 1);
    }

    private function absoluteUpstreamRoot(): string
    {
        if (str_starts_with($this->upstreamRoot, DIRECTORY_SEPARATOR)) {
            return rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . trim($this->upstreamRoot, DIRECTORY_SEPARATOR);
    }

    private function absoluteFixtureBase(): ?string
    {
        if (!$this->hasExplicitFixtureBase()) {
            return null;
        }

        $fixtureBase = (string) $this->fixtureBase;
        if (str_starts_with($fixtureBase, DIRECTORY_SEPARATOR)) {
            return rtrim($fixtureBase, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . trim($fixtureBase, DIRECTORY_SEPARATOR);
    }

    private function hasExplicitFixtureBase(): bool
    {
        return is_string($this->fixtureBase) && $this->fixtureBase !== '';
    }

    private function fixturePath(string $root, string $fixtureRoot, string $fixture): string
    {
        return $this->hasExplicitFixtureBase()
            ? $fixtureRoot . '/' . $fixture
            : $root . '/test/' . $fixture;
    }

    private function fixtureDirectory(string $root, string $fixtureRoot): string
    {
        return $this->hasExplicitFixtureBase()
            ? $fixtureRoot . '/epub'
            : $root . '/test/epub';
    }

    private function fixtureDisplayPath(string $fixture): string
    {
        return $this->hasExplicitFixtureBase() ? $fixture : 'test/' . $fixture;
    }

    private function fixtureDirectoryDisplayPath(): string
    {
        return $this->hasExplicitFixtureBase() ? 'epub' : 'test/epub';
    }

    private function displayPath(string $path): string
    {
        if (str_starts_with($path, $this->repoRoot . DIRECTORY_SEPARATOR)) {
            return substr($path, strlen($this->repoRoot) + 1);
        }

        return $path;
    }

    private function gitHead(string $root): ?string
    {
        $command = 'git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || $output === []) {
            return null;
        }

        return trim((string) $output[0]) ?: null;
    }
}
