<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PptxUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-pptx-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-pptx-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-pptx-root';
    public const CHECKED_IN_CURRENT_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/upstream-current-pptx-reader';
    public const EXPECTED_STATIC_READER_TEST_COMPARE_COUNT = 1;
    public const EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT = 9;

    private const STATIC_CURRENT_READER_CASES = [
        [
            'name' => 'text extraction',
            'pptx' => 'pptx-reader/basic.pptx',
            'native' => 'pptx-reader/basic.native',
            'pairKey' => 'pptx-reader/basic.pptx|pptx-reader/basic.native',
        ],
    ];
    private const CHECKED_IN_CURRENT_FIXTURE_SNAPSHOT = [
        'basic' => [
            'name' => 'text extraction',
            'pptx' => 'pptx-reader/basic.pptx',
            'native' => 'pptx-reader/basic.native',
            'pairKey' => 'pptx-reader/basic.pptx|pptx-reader/basic.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/basic.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/basic.native',
            'pptxSha256' => 'e48fd9c2f8369d1792197e301d5fea676bf6e51097a24af7d85831a6f96dc2dc',
            'nativeSha256' => '42804b9b1954094a4b0ff0be20084e2e6d9bc0a84272f34f7f219f82505da6b4',
            'pptxBytes' => 111674,
            'nativeBytes' => 3966,
        ],
        'minimal' => [
            'name' => 'generated minimal text extraction parity',
            'pptx' => 'pptx-reader/minimal.pptx',
            'native' => 'pptx-reader/minimal.native',
            'pairKey' => 'pptx-reader/minimal.pptx|pptx-reader/minimal.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/minimal.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/minimal.native',
            'pptxSha256' => 'f4852d7b0455ae99a8ef2b3d419cb2aa9ab2f8b5c4167e3770a38483ab36f202',
            'nativeSha256' => '6ec8b821c9a28c12ca65c771d7dcb6df0ec7f9f91b139e318d4cdbbd4fde4c76',
            'pptxBytes' => 1502,
            'nativeBytes' => 119,
        ],
        'break-tab-field' => [
            'name' => 'generated break, tab, and field text boundary parity',
            'pptx' => 'pptx-reader/break-tab-field.pptx',
            'native' => 'pptx-reader/break-tab-field.native',
            'pairKey' => 'pptx-reader/break-tab-field.pptx|pptx-reader/break-tab-field.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/break-tab-field.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/break-tab-field.native',
            'pptxSha256' => 'eab556ea99844fb5f815f977d46d5a1923d59f71682c7cceae5e23b5937f113c',
            'nativeSha256' => 'e619a9e7b375700d5fd8c2c74cd9bb5c424098d39b972212a86f58764affadf4',
            'pptxBytes' => 1435,
            'nativeBytes' => 113,
        ],
        'bullets' => [
            'name' => 'generated minimal bullet list parity',
            'pptx' => 'pptx-reader/bullets.pptx',
            'native' => 'pptx-reader/bullets.native',
            'pairKey' => 'pptx-reader/bullets.pptx|pptx-reader/bullets.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/bullets.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/bullets.native',
            'pptxSha256' => '912915e6c9a56eda1e2cb657b23cd007cd0c49da8d8d96a199e9cb8c1e310760',
            'nativeSha256' => 'f53f49de194917ae945eaaff66720120bf8a0df95c6075b31a08ea41f633507c',
            'pptxBytes' => 1543,
            'nativeBytes' => 157,
        ],
        'embedded-image' => [
            'name' => 'generated embedded image native parity',
            'pptx' => 'pptx-reader/embedded-image.pptx',
            'native' => 'pptx-reader/embedded-image.native',
            'pairKey' => 'pptx-reader/embedded-image.pptx|pptx-reader/embedded-image.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/embedded-image.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/embedded-image.native',
            'pptxSha256' => 'de45bd6af2dcf74e29dd7d961e5459c3a5d2b420992b1bbf280b10ee6df7256a',
            'nativeSha256' => '1aea7cedcb9155ee19a55db0d2825b1427dab1f51bbb460d140cd637e2bec266',
            'pptxBytes' => 2363,
            'nativeBytes' => 195,
        ],
        'hyperlink-text' => [
            'name' => 'generated text hyperlink invisibility parity',
            'pptx' => 'pptx-reader/hyperlink-text.pptx',
            'native' => 'pptx-reader/hyperlink-text.native',
            'pairKey' => 'pptx-reader/hyperlink-text.pptx|pptx-reader/hyperlink-text.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/hyperlink-text.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/hyperlink-text.native',
            'pptxSha256' => '22180e777f4a145bd3aff34f6fd5c2a846ce5567d758a78565b5dfc6addca6e3',
            'nativeSha256' => 'f4334af63e88a238caf0dcb2a4bf37fa1745d54bb2d703ec287fb3cc0474bcd7',
            'pptxBytes' => 2004,
            'nativeBytes' => 100,
        ],
        'two-slides' => [
            'name' => 'generated two-slide ordering parity',
            'pptx' => 'pptx-reader/two-slides.pptx',
            'native' => 'pptx-reader/two-slides.native',
            'pairKey' => 'pptx-reader/two-slides.pptx|pptx-reader/two-slides.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/two-slides.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/two-slides.native',
            'pptxSha256' => '58e37ebe22ba5f7e5b9f7c3fe886ae5ff085876371178e63cc115a8f6d4e052c',
            'nativeSha256' => '269e2c8b638af9834b52a0ff23c795578f9b21404e27c60d846cf81b3520596a',
            'pptxBytes' => 1897,
            'nativeBytes' => 177,
        ],
        'speaker-notes' => [
            'name' => 'generated speaker notes visibility parity',
            'pptx' => 'pptx-reader/speaker-notes.pptx',
            'native' => 'pptx-reader/speaker-notes.native',
            'pairKey' => 'pptx-reader/speaker-notes.pptx|pptx-reader/speaker-notes.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/speaker-notes.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/speaker-notes.native',
            'pptxSha256' => '52d0a82f3a84c594a9be816307c90b918cb914802bd3622a4cf9e2c06f40ddc5',
            'nativeSha256' => '24f10e8e2632d64f9afb7a3aac8b0e48570d8ef61d76f6f0a51f841d104142f1',
            'pptxBytes' => 2511,
            'nativeBytes' => 95,
        ],
        'numbered-list' => [
            'name' => 'generated auto-numbered paragraph boundary parity',
            'pptx' => 'pptx-reader/numbered-list.pptx',
            'native' => 'pptx-reader/numbered-list.native',
            'pairKey' => 'pptx-reader/numbered-list.pptx|pptx-reader/numbered-list.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/numbered-list.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/numbered-list.native',
            'pptxSha256' => 'ba1162b8a31aba2b9cc01b1d346a070d66a0f8666afa44e0ace72bfdd76f1d4b',
            'nativeSha256' => 'be9e2f1c3a9f5815ea6cc86debe2ff081a4666931dd2e48c32245cd3de40cd9f',
            'pptxBytes' => 1520,
            'nativeBytes' => 118,
        ],
    ];

    private readonly string $repoRoot;
    private readonly string $upstreamRoot;

    public function __construct(string $repoRoot, string $upstreamRoot = self::DEFAULT_RELATIVE_UPSTREAM_ROOT)
    {
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('Repository root must not be empty');
        }
        if ($upstreamRoot === '') {
            throw new \InvalidArgumentException('Upstream root must not be empty');
        }

        $this->repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $this->upstreamRoot = $upstreamRoot;
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
                'staticCurrentEvidence' => $this->staticCurrentEvidence(),
                'runnerEvidence' => self::runnerNotRunEvidence(),
                'validation' => [
                    'status' => 'not-evaluated-missing-upstream-root',
                    'issues' => ['missing-upstream-root'],
                ],
                'claim' => self::claim(),
                'claimBoundaries' => self::claimBoundaries(),
            ];
        }

        $readerTestPath = $root . '/test/Tests/Readers/Pptx.hs';
        $fixtureDirectory = $root . '/test/pptx-reader';
        $readerCases = is_file($readerTestPath)
            ? $this->parseReaderCases((string) file_get_contents($readerTestPath))
            : [];
        $fixturePairs = $this->fixturePairs($fixtureDirectory);
        $unpairedFixtures = $this->unpairedFixtureFiles($fixtureDirectory);
        $validationIssues = $this->validationIssues($root, $readerCases, $fixturePairs, $unpairedFixtures);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $this->gitHead($root),
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerTestModule' => 'test/Tests/Readers/Pptx.hs',
                'fixtureDirectory' => 'test/pptx-reader',
            ],
            'denominator' => [
                'readerTestCompareCount' => count($readerCases),
                'fixturePairCount' => count($fixturePairs),
                'referencedPairCount' => count($readerCases),
                'readerCases' => $readerCases,
                'fixturePairs' => array_values($fixturePairs),
                'unpairedPptxFixtureCount' => count($unpairedFixtures['pptx']),
                'unpairedNativeFixtureCount' => count($unpairedFixtures['native']),
                'unpairedPptxFixtures' => $unpairedFixtures['pptx'],
                'unpairedNativeFixtures' => $unpairedFixtures['native'],
                'missingReferencedFiles' => $this->missingReferencedFiles($root, $readerCases),
                'unreferencedFixturePairs' => $this->unreferencedFixturePairs($readerCases, $fixturePairs),
            ],
            'sourceInventory' => $this->sourceInventory($root),
            'staticCurrentEvidence' => $this->staticCurrentEvidence(),
            'runnerEvidence' => self::runnerNotRunEvidence(),
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-pptx-reader-denominator' : 'invalid-upstream-pptx-reader-denominator',
                'issues' => $validationIssues,
            ],
            'claim' => self::claim(),
            'claimBoundaries' => self::claimBoundaries(),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
        $upstream = is_array($report['upstream'] ?? null) ? $report['upstream'] : [];
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $staticValidation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];
        $staticDenominator = is_array($staticEvidence['readerDenominator'] ?? null) ? $staticEvidence['readerDenominator'] : [];
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];

        return implode(PHP_EOL, [
            'Pandoc PPTX reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Upstream: ' . (string) ($upstream['commit'] ?? 'unknown')
                . ' expected=' . (string) ($upstream['expectedCommit'] ?? self::EXPECTED_UPSTREAM_COMMIT),
            'Reader test comparisons: ' . (int) ($denominator['readerTestCompareCount'] ?? 0),
            'Fixture pairs: ' . (int) ($denominator['fixturePairCount'] ?? 0)
                . ' unpairedPptx=' . (int) ($denominator['unpairedPptxFixtureCount'] ?? 0)
                . ' unpairedNative=' . (int) ($denominator['unpairedNativeFixtureCount'] ?? 0),
            'Static current evidence: ' . (string) ($staticValidation['status'] ?? 'unknown')
                . ' comparisons=' . (int) ($staticDenominator['expectedCompareCount'] ?? 0)
                . ' checkedInPairs=' . (int) ($staticEvidence['checkedInFixturePairCount'] ?? 0)
                . ' checkedInUnpairedPptx=' . (int) ($staticEvidence['checkedInUnpairedPptxFixtureCount'] ?? 0)
                . ' checkedInUnpairedNative=' . (int) ($staticEvidence['checkedInUnpairedNativeFixtureCount'] ?? 0),
            'Runner status: ' . (string) ($runner['status'] ?? 'unknown'),
            'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
            'No upstream Haskell/Cabal runner result or full PowerPoint feature parity is asserted.',
        ]) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredReaderTestCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['readerTestCompareCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredFixturePairCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['fixturePairCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-pptx-reader-denominator'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticCurrentEvidence(array $report): bool
    {
        $evidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];
        $denominator = is_array($evidence['readerDenominator'] ?? null) ? $evidence['readerDenominator'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-current-pptx-reader-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($denominator['expectedCompareCount'] ?? -1) === self::EXPECTED_STATIC_READER_TEST_COMPARE_COUNT
            && (int) ($evidence['checkedInFixturePairCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT;
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

    private static function claim(): string
    {
        return 'Parses the pinned upstream Tests.Readers.Pptx test module and test/pptx-reader fixture directory to establish the current PPTX reader golden-test denominator.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the count and file paths of upstream PPTX reader golden comparisons in Tests.Readers.Pptx',
                'that every referenced PPTX/native fixture file exists in the pinned sparse upstream checkout',
                'that root-level test/pptx-reader PPTX/native fixture pairs and unpaired files are accounted for',
                'static checked-in current upstream basic.pptx/basic.native plus generated minimal.pptx/minimal.native, break-tab-field.pptx/break-tab-field.native, bullets.pptx/bullets.native, embedded-image.pptx/embedded-image.native, hyperlink-text.pptx/hyperlink-text.native, two-slides.pptx/two-slides.native, speaker-notes.pptx/speaker-notes.native, and numbered-list.pptx/numbered-list.native fixture identities when staticCurrentEvidence is valid',
                'that upstream Haskell runner evidence is explicitly not-run',
            ],
            'doesNotAssert' => [
                'that upstream Haskell/Cabal/Tasty tests were executed',
                'full upstream Tests.Readers.Pptx runner parity',
                'that local PHP output matches upstream native output',
                'PPTX writer parity',
                'full PowerPoint feature parity beyond Pandoc reader behavior',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerNotRunEvidence(): array
    {
        return [
            'runner' => 'Cabal/Tasty Pandoc PPTX reader suite',
            'scope' => 'upstream-haskell-runner',
            'status' => 'not-run',
            'executed' => false,
            'command' => null,
            'resultArtifact' => null,
            'blockers' => [
                'no committed upstream test:test-pandoc PPTX runner transcript or result artifact is present',
                'this PHP evidence gate intentionally does not invoke Cabal/Tasty or hydrate Haskell build dependencies',
                'a future runner claim must be bound to the pinned upstream commit and exact targeted PPTX Tasty pattern',
            ],
            'futureCommands' => [
                [
                    'purpose' => 'prepare runner dependencies in an isolated build directory',
                    'program' => 'cabal',
                    'arguments' => [
                        'v2-build',
                        '--offline',
                        '--dry-run',
                        '--only-dependencies',
                        '--project-dir=.',
                        '--builddir=.port-libs/pandoc-runner/cabal-build/pptx-targeted-run',
                        'test:test-pandoc',
                    ],
                ],
                [
                    'purpose' => 'list targeted PPTX reader tests',
                    'program' => 'cabal',
                    'arguments' => [
                        'v2-run',
                        '--offline',
                        '--project-dir=.',
                        '--builddir=.port-libs/pandoc-runner/cabal-build/pptx-targeted-run',
                        'test:test-pandoc',
                        '--',
                        '--list-tests',
                        '--pattern',
                        '$2 == "Readers" && $3 == "Pptx"',
                    ],
                ],
                [
                    'purpose' => 'run targeted PPTX reader tests',
                    'program' => 'cabal',
                    'arguments' => [
                        'v2-run',
                        '--offline',
                        '--project-dir=.',
                        '--builddir=.port-libs/pandoc-runner/cabal-build/pptx-targeted-run',
                        'test:test-pandoc',
                        '--',
                        '--pattern',
                        '$2 == "Readers" && $3 == "Pptx"',
                    ],
                ],
            ],
            'requiredArtifacts' => [
                '.port-libs/pandoc-runner/logs/runner-test-dependencies.txt',
                '.port-libs/pandoc-runner/logs/pptx-targeted-list-tests.txt',
                '.port-libs/pandoc-runner/logs/pptx-targeted-run.txt',
                '.port-libs/pandoc-runner/artifacts/pptx-targeted-run/result.json',
            ],
            'claim' => 'No upstream Haskell runner parity is claimed.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staticCurrentEvidence(): array
    {
        $fixtureDirectory = $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY);
        $checkedInFixturePairs = array_values($this->fixturePairs($fixtureDirectory));
        $checkedInUnpairedFixtures = $this->unpairedFixtureFiles($fixtureDirectory);
        $checkedInPairKeys = [];
        foreach ($checkedInFixturePairs as $pair) {
            $checkedInPairKeys[(string) $pair['pairKey']] = true;
        }

        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-current-fixture-directory';
        }

        if (count(self::STATIC_CURRENT_READER_CASES) !== self::EXPECTED_STATIC_READER_TEST_COMPARE_COUNT) {
            $issues[] = 'static-reader-test-count-does-not-match-expected-snapshot';
        }

        if (count($checkedInFixturePairs) !== self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT) {
            $issues[] = 'checked-in-fixture-pair-count-does-not-match-static-snapshot';
        }
        if ($checkedInUnpairedFixtures['pptx'] !== []) {
            $issues[] = 'checked-in-current-unpaired-pptx-fixtures';
        }
        if ($checkedInUnpairedFixtures['native'] !== []) {
            $issues[] = 'checked-in-current-unpaired-native-fixtures';
        }

        $snapshotPairs = [];
        foreach (self::CHECKED_IN_CURRENT_FIXTURE_SNAPSHOT as $stem => $snapshot) {
            $pptx = $this->snapshotFileEvidence(
                (string) $snapshot['pptxPath'],
                (string) $snapshot['pptxSha256'],
                (int) $snapshot['pptxBytes']
            );
            $native = $this->snapshotFileEvidence(
                (string) $snapshot['nativePath'],
                (string) $snapshot['nativeSha256'],
                (int) $snapshot['nativeBytes']
            );
            $pairKey = (string) $snapshot['pairKey'];
            $snapshotPairs[] = [
                'stem' => (string) $stem,
                'name' => (string) ($snapshot['name'] ?? self::STATIC_CURRENT_READER_CASES[0]['name']),
                'pptx' => (string) $snapshot['pptx'],
                'native' => (string) $snapshot['native'],
                'pairKey' => $pairKey,
                'checkedInPptx' => $pptx,
                'checkedInNative' => $native,
            ];

            if (($pptx['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-current-pptx-fixture';
            } elseif (($pptx['sha256'] ?? null) !== $snapshot['pptxSha256']) {
                $issues[] = 'checked-in-current-pptx-sha256-mismatch';
            } elseif ((int) ($pptx['bytes'] ?? -1) !== (int) $snapshot['pptxBytes']) {
                $issues[] = 'checked-in-current-pptx-byte-count-mismatch';
            }

            if (($native['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-current-native-fixture';
            } elseif (($native['sha256'] ?? null) !== $snapshot['nativeSha256']) {
                $issues[] = 'checked-in-current-native-sha256-mismatch';
            } elseif ((int) ($native['bytes'] ?? -1) !== (int) $snapshot['nativeBytes']) {
                $issues[] = 'checked-in-current-native-byte-count-mismatch';
            }

            if (!isset($checkedInPairKeys[$pairKey])) {
                $issues[] = 'checked-in-current-fixture-pair-key-mismatch';
            }
        }

        return [
            'kind' => 'static-checked-in-current-pptx-reader-fixture-evidence',
            'upstream' => [
                'name' => 'jgm/pandoc',
                'commit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerTestModule' => 'test/Tests/Readers/Pptx.hs',
                'fixtureDirectory' => 'test/pptx-reader',
            ],
            'readerDenominator' => [
                'expectedCompareCount' => count(self::STATIC_CURRENT_READER_CASES),
                'expectedReaderCases' => self::STATIC_CURRENT_READER_CASES,
            ],
            'checkedInFixtureDirectory' => self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY,
            'checkedInFixturePairCount' => count($checkedInFixturePairs),
            'checkedInFixturePairs' => $snapshotPairs,
            'checkedInUnpairedPptxFixtureCount' => count($checkedInUnpairedFixtures['pptx']),
            'checkedInUnpairedNativeFixtureCount' => count($checkedInUnpairedFixtures['native']),
            'checkedInUnpairedPptxFixtures' => $checkedInUnpairedFixtures['pptx'],
            'checkedInUnpairedNativeFixtures' => $checkedInUnpairedFixtures['native'],
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-pptx-reader-evidence' : 'invalid-checked-in-current-pptx-reader-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static gate binding the pinned Tests.Readers.Pptx one-case denominator to the checked-in current upstream basic.pptx/basic.native fixture pair, plus eight generated PPTX/native pairs used only for local normalized-AST parity.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'Tests.Readers.Pptx at the pinned upstream commit has one golden comparison for pptx-reader/basic.pptx and pptx-reader/basic.native',
                    'the checked-in current PPTX fixture directory contains nine same-stem PPTX/native pairs and no unpaired PPTX/native files',
                    'the checked-in basic.pptx/basic.native, minimal.pptx/minimal.native, break-tab-field.pptx/break-tab-field.native, bullets.pptx/bullets.native, embedded-image.pptx/embedded-image.native, hyperlink-text.pptx/hyperlink-text.native, two-slides.pptx/two-slides.native, speaker-notes.pptx/speaker-notes.native, and numbered-list.pptx/numbered-list.native files match the expected SHA-256 hashes and byte counts for this snapshot',
                ],
                'doesNotAssert' => [
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'that a fresh upstream checkout was inspected during this PHP gate',
                    'that minimal.pptx/minimal.native is an upstream Tests.Readers.Pptx fixture',
                    'that break-tab-field.pptx/break-tab-field.native is an upstream Tests.Readers.Pptx fixture',
                    'that bullets.pptx/bullets.native is an upstream Tests.Readers.Pptx fixture',
                    'that embedded-image.pptx/embedded-image.native is an upstream Tests.Readers.Pptx fixture',
                    'that hyperlink-text.pptx/hyperlink-text.native is an upstream Tests.Readers.Pptx fixture',
                    'that two-slides.pptx/two-slides.native is an upstream Tests.Readers.Pptx fixture',
                    'that speaker-notes.pptx/speaker-notes.native is an upstream Tests.Readers.Pptx fixture',
                    'that numbered-list.pptx/numbered-list.native is an upstream Tests.Readers.Pptx fixture',
                    'broader PPTX fixture corpus coverage beyond basic.pptx/basic.native, minimal.pptx/minimal.native, break-tab-field.pptx/break-tab-field.native, bullets.pptx/bullets.native, embedded-image.pptx/embedded-image.native, hyperlink-text.pptx/hyperlink-text.native, two-slides.pptx/two-slides.native, speaker-notes.pptx/speaker-notes.native, and numbered-list.pptx/numbered-list.native',
                    'full PowerPoint feature parity',
                ],
            ],
        ];
    }

    /**
     * @return array{path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}
     */
    private function snapshotFileEvidence(string $relativePath, string $expectedSha256, int $expectedBytes): array
    {
        $path = $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : false;
        $bytes = $present ? filesize($path) : false;

        return [
            'path' => $relativePath,
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'expectedSha256' => $expectedSha256,
            'bytes' => is_int($bytes) ? $bytes : null,
            'expectedBytes' => $expectedBytes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'readerTestCompareCount' => 0,
            'fixturePairCount' => 0,
            'referencedPairCount' => 0,
            'readerCases' => [],
            'fixturePairs' => [],
            'unpairedPptxFixtureCount' => 0,
            'unpairedNativeFixtureCount' => 0,
            'unpairedPptxFixtures' => [],
            'unpairedNativeFixtures' => [],
            'missingReferencedFiles' => [],
            'unreferencedFixturePairs' => [],
        ];
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
     * @return list<array{name: string, pptx: string, native: string, pairKey: string}>
     */
    private function parseReaderCases(string $source): array
    {
        $cases = [];
        $pattern = '/\btestCompare(?:WithOpts\s+\S+)?\s+"([^"]+)"\s+"([^"]+\.pptx)"\s+"([^"]+\.native)"/s';
        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $pptx = (string) $match[2];
                $native = (string) $match[3];
                $cases[] = [
                    'name' => (string) $match[1],
                    'pptx' => $pptx,
                    'native' => $native,
                    'pairKey' => self::pairKey($pptx, $native),
                ];
            }
        }

        return $cases;
    }

    /**
     * @return array<string, array{pptx: string, native: string, pairKey: string}>
     */
    private function fixturePairs(string $fixtureDirectory): array
    {
        if (!is_dir($fixtureDirectory)) {
            return [];
        }

        $pptxByStem = $this->fixtureFilesByStem($fixtureDirectory, 'pptx');
        $nativeByStem = $this->fixtureFilesByStem($fixtureDirectory, 'native');

        $pairs = [];
        foreach (array_intersect(array_keys($pptxByStem), array_keys($nativeByStem)) as $stem) {
            $pptx = $pptxByStem[$stem];
            $native = $nativeByStem[$stem];
            $pairs[$stem] = [
                'pptx' => $pptx,
                'native' => $native,
                'pairKey' => self::pairKey($pptx, $native),
            ];
        }
        ksort($pairs, SORT_STRING);

        return $pairs;
    }

    /**
     * @return array<string, string>
     */
    private function fixtureFilesByStem(string $fixtureDirectory, string $extension): array
    {
        if (!is_dir($fixtureDirectory)) {
            return [];
        }

        $files = [];
        foreach (glob($fixtureDirectory . '/*.' . $extension) ?: [] as $path) {
            $stem = basename($path, '.' . $extension);
            $files[$stem] = 'pptx-reader/' . basename($path);
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return array{pptx:list<string>, native:list<string>}
     */
    private function unpairedFixtureFiles(string $fixtureDirectory): array
    {
        $pptxByStem = $this->fixtureFilesByStem($fixtureDirectory, 'pptx');
        $nativeByStem = $this->fixtureFilesByStem($fixtureDirectory, 'native');

        $unpairedPptx = array_values(array_diff_key($pptxByStem, $nativeByStem));
        $unpairedNative = array_values(array_diff_key($nativeByStem, $pptxByStem));
        sort($unpairedPptx, SORT_STRING);
        sort($unpairedNative, SORT_STRING);

        return [
            'pptx' => $unpairedPptx,
            'native' => $unpairedNative,
        ];
    }

    /**
     * @param list<array{name: string, pptx: string, native: string, pairKey: string}> $readerCases
     * @return list<array{case: string, path: string}>
     */
    private function missingReferencedFiles(string $root, array $readerCases): array
    {
        $missing = [];
        foreach ($readerCases as $case) {
            foreach (['pptx', 'native'] as $kind) {
                $relative = 'test/' . $case[$kind];
                if (!is_file($root . '/' . $relative)) {
                    $missing[] = [
                        'case' => $case['name'],
                        'path' => $relative,
                    ];
                }
            }
        }

        return $missing;
    }

    /**
     * @param list<array{name: string, pptx: string, native: string, pairKey: string}> $readerCases
     * @param array<string, array{pptx: string, native: string, pairKey: string}> $fixturePairs
     * @return list<array{pptx: string, native: string, pairKey: string}>
     */
    private function unreferencedFixturePairs(array $readerCases, array $fixturePairs): array
    {
        $referenced = [];
        foreach ($readerCases as $case) {
            $referenced[$case['pairKey']] = true;
        }

        $unreferenced = [];
        foreach ($fixturePairs as $pair) {
            if (!isset($referenced[$pair['pairKey']])) {
                $unreferenced[] = $pair;
            }
        }

        return $unreferenced;
    }

    /**
     * @param list<array{name: string, pptx: string, native: string, pairKey: string}> $readerCases
     * @param array<string, array{pptx: string, native: string, pairKey: string}> $fixturePairs
     * @param array{pptx:list<string>, native:list<string>} $unpairedFixtures
     * @return list<string>
     */
    private function validationIssues(string $root, array $readerCases, array $fixturePairs, array $unpairedFixtures): array
    {
        $issues = [];
        if (!is_file($root . '/test/Tests/Readers/Pptx.hs')) {
            $issues[] = 'missing-reader-test-module';
        }
        if (!is_dir($root . '/test/pptx-reader')) {
            $issues[] = 'missing-pptx-reader-fixture-directory';
        }
        if ($readerCases === []) {
            $issues[] = 'no-reader-test-comparisons-found';
        }
        if ($fixturePairs === []) {
            $issues[] = 'no-pptx-native-fixture-pairs-found';
        }
        if ($this->missingReferencedFiles($root, $readerCases) !== []) {
            $issues[] = 'missing-referenced-fixture-files';
        }
        if ($this->unreferencedFixturePairs($readerCases, $fixturePairs) !== []) {
            $issues[] = 'unreferenced-fixture-pairs';
        }
        if ($unpairedFixtures['pptx'] !== []) {
            $issues[] = 'unpaired-pptx-fixtures';
        }
        if ($unpairedFixtures['native'] !== []) {
            $issues[] = 'unpaired-native-fixtures';
        }
        if (count($readerCases) !== count($fixturePairs)) {
            $issues[] = 'reader-test-count-does-not-match-fixture-pair-count';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceInventory(string $root): array
    {
        $relativeFiles = [
            'test/Tests/Readers/Pptx.hs',
            'src/Text/Pandoc/Readers/Pptx.hs',
            'src/Text/Pandoc/Readers/Pptx/Parse.hs',
            'src/Text/Pandoc/Readers/Pptx/Shapes.hs',
            'src/Text/Pandoc/Readers/Pptx/Slides.hs',
            'src/Text/Pandoc/Readers/Pptx/SmartArt.hs',
        ];
        $files = [];
        $presentCount = 0;
        $lineCount = 0;
        foreach ($relativeFiles as $relative) {
            $path = $root . '/' . $relative;
            $present = is_file($path);
            $lines = $present ? count(file($path) ?: []) : 0;
            $files[] = [
                'path' => $relative,
                'present' => $present,
                'lines' => $lines,
            ];
            if ($present) {
                ++$presentCount;
                $lineCount += $lines;
            }
        }

        return [
            'files' => $files,
            'presentFileCount' => $presentCount,
            'missingFileCount' => count($relativeFiles) - $presentCount,
            'presentLineCount' => $lineCount,
        ];
    }

    private function absoluteUpstreamRoot(): string
    {
        if (str_starts_with($this->upstreamRoot, DIRECTORY_SEPARATOR)) {
            return rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
    }

    private function displayPath(string $path): string
    {
        $prefix = $this->repoRoot . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }

        return $path;
    }

    private function gitHead(string $root): ?string
    {
        if (!is_dir($root . '/.git')) {
            return null;
        }

        $output = [];
        $exitCode = 0;
        exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0 || !is_string($output[0] ?? null)) {
            return null;
        }

        return trim($output[0]);
    }

    private static function pairKey(string $pptx, string $native): string
    {
        return $pptx . '|' . $native;
    }
}
