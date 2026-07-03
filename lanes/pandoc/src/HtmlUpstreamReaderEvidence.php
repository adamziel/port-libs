<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class HtmlUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-html-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-html-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-html-root';
    public const CHECKED_IN_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures';
    public const EXPECTED_SELECTED_FIXTURE_COUNT = 48;
    public const EXPECTED_NATIVE_MAPPED_PAIR_COUNT = 41;

    private const SOURCE_FILES = [
        'test/Tests/Readers/HTML.hs',
        'src/Text/Pandoc/Readers/HTML.hs',
    ];

    private const CHECKED_IN_HTML_FIXTURES = [
        'upstream-html-anchor-image-attrs.html' => [
            'sha256' => '27073f93fc90c5a85361723faad6fa6e1e44a891b344680476c41f9a4df3be74',
            'bytes' => 363,
        ],
        'upstream-html-base-media.html' => [
            'sha256' => '2769a543b2e88aba05d2d95bd22f4dd53e9c7b81c270d7dadd4038e2428e1c8a',
            'bytes' => 461,
        ],
        'upstream-html-bdo-direction.html' => [
            'sha256' => '8a49cc0d2a5be4a343c0bea3717f7003e713b68898d44a03ea65ba4267acb02e',
            'bytes' => 239,
        ],
        'upstream-html-checkbox-list.html' => [
            'sha256' => 'f10574b3a847f995ed86e7c0876948cac0a452a06b7b0aece497a12412de5e45',
            'bytes' => 599,
        ],
        'upstream-html-cite-wbr-raw-inline.html' => [
            'sha256' => 'ce0511c08b23c0e77b8487471c6d276804e3a525932732f543a7447a4ccd7ec9',
            'bytes' => 292,
        ],
        'upstream-html-doc-noteref-footnotes.html' => [
            'sha256' => 'c6842790e7750ca06998c0f7e0e91c17f44c59e7698cc1f05c1fa2172f266f2f',
            'bytes' => 521,
        ],
        'upstream-html-doc-noteref-table-placement.html' => [
            'sha256' => '9fe0e5278a6698d05f306aa3b45af53828d9eedc5c5f554b1dfaf67801f5ebeb',
            'bytes' => 1588,
        ],
        'upstream-html-figure-caption.html' => [
            'sha256' => '4ce8a7d28f575b096ca2d26c0510526a81d269b09921fe8d7637fc2dcd727a03',
            'bytes' => 542,
        ],
        'upstream-html-generic-raw-inline.html' => [
            'sha256' => '1f1367a1693e92216965e77c30d190be4662b8c756696f59eb7b44d32265d52e',
            'bytes' => 271,
        ],
        'upstream-html-header-native-divs.html' => [
            'sha256' => '3c3d08b67b3506cd2d43a7490237c240f7eb33fa26b0ac02aab612f72feb10dd',
            'bytes' => 358,
        ],
        'upstream-html-iframe-local-resource.html' => [
            'sha256' => '86f1137cd0a68b10b18c96eb5144ba938bf7ed23d7fcd582a2275049fe214a7c',
            'bytes' => 312,
        ],
        'upstream-html-inline-code-aliases.html' => [
            'sha256' => '885749949b1f212a85f081b8a094836517a87a1ce82978e1cc4b33cd900f7719',
            'bytes' => 242,
        ],
        'upstream-html-lang-metadata.html' => [
            'sha256' => 'bf1f7665c81c1c438333e8c7b68a0ddc754a8fdb43515082c541d9589c95b218',
            'bytes' => 184,
        ],
        'upstream-html-line-block.html' => [
            'sha256' => 'a4d154266a877da11a47d3102be71faf469d468f0ae2c3cabbc44c8e6af38324',
            'bytes' => 286,
        ],
        'upstream-html-list-item-id.html' => [
            'sha256' => '7f94cd5a7639e1193a309cb0fb04453be05d9e545050bf2b1e18727392bed540',
            'bytes' => 367,
        ],
        'upstream-html-main-native-divs.html' => [
            'sha256' => '092476d2be22cecef9c1a2a59bc1643ac6b25a6e783238f249438801565f75b2',
            'bytes' => 435,
        ],
        'upstream-html-math-renderer-spans.html' => [
            'sha256' => 'e49465a7bbda8503f0d979c015a79eb14e9da6591252f6ce85eeff79a737151d',
            'bytes' => 564,
        ],
        'upstream-html-mathml-annotation.html' => [
            'sha256' => '61554b0518b7631ec28580295e06bf25e52048581ef704755d1f757e27fa2536',
            'bytes' => 673,
        ],
        'upstream-html-orphan-list-blocks.html' => [
            'sha256' => '14d455dbd0284dcab58715fe4c5e0ed2042a28e2e75ed2815773cf4b1d5b0762',
            'bytes' => 393,
        ],
        'upstream-html-pre-code-attributes.html' => [
            'sha256' => '78967902404465f84e40de1811d31d02b59df8b61d51804e59e5af90076390f7',
            'bytes' => 446,
        ],
        'upstream-html-pre-code-br.html' => [
            'sha256' => '904f669d02c0ceb4a9fd17f49141892dcab2b686ecc2f95a4ad1c450ee9bde30',
            'bytes' => 410,
        ],
        'upstream-html-raw-disabled-skip.html' => [
            'sha256' => 'd9a2445ca3adbbfa7e71d4c6b274f3a1415dc0297b5dd83eba28b692e1a93a3a',
            'bytes' => 686,
        ],
        'upstream-html-script-raw-block.html' => [
            'sha256' => 'fb4460c0338874e1df4835c1babbc6aec72ede2ad1230ba047354b10b93f47f3',
            'bytes' => 395,
        ],
        'upstream-html-section-aside-native-divs.html' => [
            'sha256' => '4b603995bd1fd40d53728011117159ca7dfc2a6fa5181cf04eb102d4c7a81b9a',
            'bytes' => 489,
        ],
        'upstream-html-small-inline.html' => [
            'sha256' => 'e2396299f217b7b9586fe658a469d9c1a758e7253843396d3b45809fc1e02ecb',
            'bytes' => 252,
        ],
        'upstream-html-smallcaps-class.html' => [
            'sha256' => '7caf7c91f9bbdd53e398c5a24ead2c8dea7bc64b54ec2071f86b3b9f5d0e6698',
            'bytes' => 373,
        ],
        'upstream-html-span-strikeout.html' => [
            'sha256' => '747abe3e4ae1dd7eceb84374cf2d011eeea200ec462a707f15c9a7d452c24023',
            'bytes' => 292,
        ],
        'upstream-html-spanlike-inline.html' => [
            'sha256' => 'e9db8292f853608429f5bcd454539aaee94bd5f3cc591e81dde11d15853050df',
            'bytes' => 383,
        ],
        'upstream-html-standalone-applet-inline.html' => [
            'sha256' => 'e6e7304349b25f9e2ac9abfa4fd6ba0b8d213eab5a2d63c2e71363ccb9d572fe',
            'bytes' => 136,
        ],
        'upstream-html-standalone-audio-inline.html' => [
            'sha256' => 'd0446e5747e434d546a0c2a15fae1f90abae0770abecdd3f3f75c54f13c0885c',
            'bytes' => 294,
        ],
        'upstream-html-standalone-button-inline.html' => [
            'sha256' => '458261a563155df934db322517be91c53288c7a6be6f33ea7bc0abeb263ea3f9',
            'bytes' => 130,
        ],
        'upstream-html-standalone-del-inline.html' => [
            'sha256' => 'bacde20bd18fd52bb4cb30a46d1eb1c13c0edfab93e14682a78344b044064792',
            'bytes' => 102,
        ],
        'upstream-html-standalone-inline-flow.html' => [
            'sha256' => 'e61703febac1355b893a2fd82043eaeacccbe10346f6b0b31d1eeda7224d09f1',
            'bytes' => 315,
        ],
        'upstream-html-standalone-ins-inline.html' => [
            'sha256' => '51dbb0ca79fffb7ff896d8efc4c5eb43dcfb40801cfca2de0ed73d3775f2d54e',
            'bytes' => 133,
        ],
        'upstream-html-standalone-linebreak.html' => [
            'sha256' => 'd3d98dce3cf6ef4831c5f9e7ade3ef30a9e7cfdba22d95d377cccb56f853b9a8',
            'bytes' => 60,
        ],
        'upstream-html-standalone-map-inline.html' => [
            'sha256' => 'e47c57f7acb1c64cef2f3ab277b1fec0e85ceee066f7730c2d07bba6ad5fd7ce',
            'bytes' => 155,
        ],
        'upstream-html-standalone-noscript-inline.html' => [
            'sha256' => '3e5b4514606df09a247defe704af4f77e2acadaa3a0386967901a087317f3a85',
            'bytes' => 136,
        ],
        'upstream-html-standalone-object-embed-inline.html' => [
            'sha256' => '812c92b498e7277341af326de065e42dc2418127d8cab4319e37efde1a695155',
            'bytes' => 236,
        ],
        'upstream-html-standalone-progress-inline.html' => [
            'sha256' => '58d700a3804ad3173490f753cc6de701132297494df5965111a413d1e9e7d170',
            'bytes' => 61,
        ],
        'upstream-html-standalone-svg-inline.html' => [
            'sha256' => '494458bac5f13caf80af60ae9d5b819f526a2abe6ea86a85eaa9d19b9f5ff850',
            'bytes' => 186,
        ],
        'upstream-html-standalone-video-inline.html' => [
            'sha256' => 'a6137290335aff99a2ce6ba523e84a56c423f82a404ff0551667bd8f31deae6f',
            'bytes' => 292,
        ],
        'upstream-html-standalone-void-inline.html' => [
            'sha256' => '2262d0478848397f25460afd7c8434f9eb1f8a853960dd981e773feb4bf0f370',
            'bytes' => 497,
        ],
        'upstream-html-style-raw-block.html' => [
            'sha256' => 'd1bab4d1f54578b76172cfad1840328c0bd2b21d19b12033e6cef7b01b265f07',
            'bytes' => 334,
        ],
        'upstream-html-style-script-inline.html' => [
            'sha256' => 'd94c7acd5813fe1d91d2b4d1cdfab9e10f6a324931c34ad51e24e2e4e1c06655',
            'bytes' => 338,
        ],
        'upstream-html-svg-disabled-raw-html.html' => [
            'sha256' => '549e6825f21db28ea13f593c7a82ace600ca1b5278463fc1fcc8c8009ba717c1',
            'bytes' => 313,
        ],
        'upstream-html-svg-raw-html.html' => [
            'sha256' => '0877b690a62009527229615f57306aee142ab77a93dec345be4691a0a9ab36b6',
            'bytes' => 305,
        ],
        'upstream-html-textarea-raw-block.html' => [
            'sha256' => '9bce92712152c77d67a9739b2e6157ee2d046f31ad30e473e007337bc6f37674',
            'bytes' => 324,
        ],
        'upstream-native-html-row-header-table.html' => [
            'sha256' => '5f59ee99b16a90f6da337f94dd75c239cefb4ff7073c21e516077773892a332d',
            'bytes' => 288,
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
        $staticEvidence = self::checkedInCurrentEvidence($this->repoRoot);
        $nativeAstEvidence = $this->nativeAstEvidence();
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
                'staticCurrentEvidence' => $staticEvidence,
                'nativeAstEvidence' => $nativeAstEvidence,
                'runnerEvidence' => self::runnerNotRunEvidence(),
                'validation' => [
                    'status' => 'not-evaluated-missing-upstream-root',
                    'issues' => ['missing-upstream-root'],
                ],
                'claim' => self::claim(),
                'claimBoundaries' => self::claimBoundaries(),
            ];
        }

        $sourceInventory = $this->sourceInventory($root);
        $upstreamCommit = $this->gitHead($root);
        $validationIssues = $this->validationIssues($sourceInventory, $staticEvidence, $nativeAstEvidence, $upstreamCommit);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $upstreamCommit,
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerSources' => self::SOURCE_FILES,
            ],
            'denominator' => self::selectedFixtureDenominator(),
            'sourceInventory' => $sourceInventory,
            'staticCurrentEvidence' => $staticEvidence,
            'nativeAstEvidence' => $nativeAstEvidence,
            'runnerEvidence' => self::runnerNotRunEvidence(),
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-html-reader-evidence' : 'invalid-upstream-html-reader-evidence',
                'issues' => $validationIssues,
            ],
            'claim' => self::claim(),
            'claimBoundaries' => self::claimBoundaries(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkedInCurrentEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $fixtures = [];
        $issues = [];
        $categoryCounts = [];

        foreach (self::CHECKED_IN_HTML_FIXTURES as $name => $snapshot) {
            $category = self::categoryForFixture($name);
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            $file = self::snapshotFileEvidence(
                $root,
                self::CHECKED_IN_FIXTURE_DIRECTORY . '/' . $name,
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $testReferences = self::localTestReferences($root, $name);
            $fixtures[] = [
                'name' => $name,
                'category' => $category,
                'checkedInFile' => $file,
                'localTestReferenceCount' => count($testReferences),
                'localTestReferences' => $testReferences,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-html-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-html-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-html-fixture-byte-count-mismatch';
            }
            if ($testReferences === []) {
                $issues[] = 'missing-html-fixture-local-test-reference';
            }
        }
        ksort($categoryCounts, SORT_STRING);

        return [
            'kind' => 'static-checked-in-current-upstream-html-reader-fixture-evidence',
            'upstream' => [
                'name' => 'jgm/pandoc',
                'commit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerSources' => self::SOURCE_FILES,
            ],
            'readerDenominator' => self::selectedFixtureDenominator(),
            'checkedInFixtureDirectory' => self::CHECKED_IN_FIXTURE_DIRECTORY,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInCategoryCounts' => $categoryCounts,
            'checkedInFixtures' => $fixtures,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-html-reader-evidence' : 'invalid-checked-in-current-html-reader-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static gate binding the checked-in upstream-derived HTML reader fixture corpus to SHA-256 and byte-count snapshots.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the checked-in HTML reader fixture corpus has 48 pinned fixture snapshots',
                    'each pinned fixture has at least one local test reference',
                    'the existing HTML/native AST comparator still observes 41 same-basename native-pair matches when included in the report',
                ],
                'doesNotAssert' => [
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'that the checked-in fixture corpus is the full upstream Tests.Readers.HTML suite',
                    'full HTML5 tree-construction, browser DOM repair, metadata, raw HTML, media, table, or inline semantic parity',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $staticDenominator = is_array($staticEvidence['readerDenominator'] ?? null) ? $staticEvidence['readerDenominator'] : [];
        $staticValidation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];
        $native = is_array($report['nativeAstEvidence'] ?? null) ? $report['nativeAstEvidence'] : [];
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $selectedFixtureCount = (int) (
            ($denominator['selectedFixtureCount'] ?? 0) !== 0
                ? $denominator['selectedFixtureCount']
                : ($staticDenominator['selectedFixtureCount'] ?? 0)
        );

        return implode(PHP_EOL, [
            'Pandoc HTML reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Selected checked-in fixtures: ' . $selectedFixtureCount,
            'Static current evidence: ' . (string) ($staticValidation['status'] ?? 'unknown')
                . ' checkedInFixtures=' . (int) ($staticEvidence['checkedInFixtureCount'] ?? 0),
            'Native AST mapped parity: ' . (int) ($native['normalizedAstMatchCount'] ?? 0)
                . '/' . (int) ($native['comparedPairCount'] ?? 0)
                . ' status=' . (string) ($native['astParityStatus'] ?? 'unknown'),
            'Native AST fixture inventory: html=' . (int) ($native['htmlFixtureCount'] ?? 0)
                . ' native=' . (int) ($native['nativeFixtureCount'] ?? 0)
                . ' paired=' . (int) ($native['pairedFixtureCount'] ?? $native['totalPairCount'] ?? 0)
                . ' unpairedHtml=' . (int) ($native['unpairedHtmlFixtureCount'] ?? 0)
                . ' unpairedNative=' . (int) ($native['unpairedNativeFixtureCount'] ?? 0),
            'Runner status: ' . (string) ($runner['status'] ?? 'unknown'),
            'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
            'No upstream Haskell/Cabal runner result or full HTML reader parity is asserted.',
        ]) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredSelectedFixtureCount(array $report, int $requiredCount): bool
    {
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $denominator = is_array($staticEvidence['readerDenominator'] ?? null) ? $staticEvidence['readerDenominator'] : [];

        return (int) ($denominator['selectedFixtureCount'] ?? -1) === $requiredCount
            && (int) ($staticEvidence['checkedInFixtureCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticCurrentEvidence(array $report): bool
    {
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $validation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-current-html-reader-evidence'
            && ($validation['issues'] ?? null) === []
            && self::hasRequiredSelectedFixtureCount($report, self::EXPECTED_SELECTED_FIXTURE_COUNT);
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredNativeMappedParity(array $report, int $requiredPairCount): bool
    {
        $native = is_array($report['nativeAstEvidence'] ?? null) ? $report['nativeAstEvidence'] : [];

        return HtmlNativeAstComparisonHarness::hasRequiredMappedParity($native, $requiredPairCount);
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
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-html-reader-evidence'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function selectedFixtureDenominator(): array
    {
        $fixtures = [];
        $categories = [];
        foreach (array_keys(self::CHECKED_IN_HTML_FIXTURES) as $name) {
            $category = self::categoryForFixture($name);
            $fixtures[] = [
                'name' => $name,
                'category' => $category,
                'sourceKind' => 'selected-upstream-html-reader-fixture',
            ];
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }
        ksort($categories, SORT_STRING);

        return [
            'selectedFixtureCount' => count($fixtures),
            'fixtureScope' => 'selected checked-in upstream-derived HTML reader fixtures',
            'selectedFixtures' => $fixtures,
            'categoryCounts' => $categories,
            'nativeMappedPairCount' => self::EXPECTED_NATIVE_MAPPED_PAIR_COUNT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerNotRunEvidence(): array
    {
        return [
            'runner' => 'Cabal/Tasty Pandoc HTML reader suite',
            'status' => 'not-run',
            'executed' => false,
            'command' => null,
            'resultArtifact' => null,
            'reason' => 'This native PHP evidence packet is generated without executing the upstream Haskell runner.',
            'claim' => 'No upstream Haskell runner parity is claimed.',
        ];
    }

    private static function claim(): string
    {
        return 'Tracks selected checked-in upstream-derived HTML reader fixtures, local test references, and the existing same-basename HTML/native AST comparison as bounded evidence for HTML reader progress.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the identity and count of 48 selected checked-in upstream-derived HTML fixtures',
                'that each selected fixture is referenced by at least one local focused test',
                'that the existing native AST gate observes 41 checked-in same-basename HTML/native matches',
                'that upstream Haskell runner evidence is explicitly not-run',
            ],
            'doesNotAssert' => [
                'full upstream Tests.Readers.HTML runner parity',
                'complete HTML5 tree-construction or browser DOM repair parity',
                'complete metadata, raw HTML, media, table, or inline semantics parity',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'selectedFixtureCount' => 0,
            'fixtureScope' => 'selected checked-in upstream-derived HTML reader fixtures',
            'selectedFixtures' => [],
            'categoryCounts' => [],
            'nativeMappedPairCount' => 0,
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
     * @return array{files: list<array{path: string, present: bool, bytes: ?int, lineCount: ?int}>, presentFileCount: int, missingFileCount: int, presentLineCount: int}
     */
    private function sourceInventory(string $root): array
    {
        $files = [];
        foreach (self::SOURCE_FILES as $path) {
            $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $present = is_file($absolute);
            $contents = $present ? file_get_contents($absolute) : false;
            $bytes = $present ? filesize($absolute) : false;
            $files[] = [
                'path' => $path,
                'present' => $present,
                'bytes' => is_int($bytes) ? $bytes : null,
                'lineCount' => is_string($contents) ? substr_count($contents, "\n") + ($contents === '' || str_ends_with($contents, "\n") ? 0 : 1) : null,
            ];
        }

        $present = array_values(array_filter($files, static fn (array $file): bool => ($file['present'] ?? false) === true));

        return [
            'files' => $files,
            'presentFileCount' => count($present),
            'missingFileCount' => count($files) - count($present),
            'presentLineCount' => array_sum(array_map(static fn (array $file): int => (int) ($file['lineCount'] ?? 0), $present)),
        ];
    }

    /**
     * @param array<string, mixed> $sourceInventory
     * @param array<string, mixed> $staticEvidence
     * @param array<string, mixed> $nativeAstEvidence
     * @return list<string>
     */
    private function validationIssues(array $sourceInventory, array $staticEvidence, array $nativeAstEvidence, ?string $upstreamCommit): array
    {
        $issues = [];
        $staticValidation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];
        if ($upstreamCommit !== self::EXPECTED_UPSTREAM_COMMIT) {
            $issues[] = 'upstream-html-reader-commit-mismatch';
        }
        if (($staticValidation['status'] ?? null) !== 'valid-checked-in-current-html-reader-evidence') {
            $issues[] = 'invalid-checked-in-current-html-reader-evidence';
        }
        if ((int) ($sourceInventory['missingFileCount'] ?? 0) > 0) {
            $issues[] = 'missing-upstream-html-reader-source';
        }
        if (!HtmlNativeAstComparisonHarness::hasRequiredMappedParity($nativeAstEvidence, self::EXPECTED_NATIVE_MAPPED_PAIR_COUNT)) {
            $issues[] = 'html-native-ast-mapped-parity-not-observed';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array<string, mixed>
     */
    private function nativeAstEvidence(): array
    {
        return (new HtmlNativeAstComparisonHarness())->run($this->repoRoot . '/lanes/pandoc/fixtures');
    }

    /**
     * @return array{path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}
     */
    private static function snapshotFileEvidence(string $root, string $relativePath, string $expectedSha256, int $expectedBytes): array
    {
        $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
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
     * @return list<string>
     */
    private static function localTestReferences(string $root, string $fixtureName): array
    {
        $testRoot = rtrim($root, DIRECTORY_SEPARATOR) . '/lanes/pandoc/tests';
        if (!is_dir($testRoot)) {
            return [];
        }

        $references = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($testRoot, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            if ($file->getFilename() === 'HtmlUpstreamReaderEvidenceTest.php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if (!is_string($contents) || !str_contains($contents, $fixtureName)) {
                continue;
            }
            $references[] = substr($file->getPathname(), strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1);
        }
        sort($references, SORT_STRING);

        return $references;
    }

    private static function categoryForFixture(string $name): string
    {
        if ($name === 'upstream-native-html-row-header-table.html') {
            return 'native-html-table-row-header';
        }
        if (str_contains($name, 'doc-noteref')) {
            return 'doc-noteref-footnote-table';
        }
        if (str_contains($name, 'raw') || str_contains($name, 'svg') || str_contains($name, 'script') || str_contains($name, 'style') || str_contains($name, 'textarea')) {
            return 'raw-html-boundary';
        }
        if (str_contains($name, 'math')) {
            return 'math-html';
        }
        if (str_contains($name, 'standalone')) {
            return 'standalone-inline-html';
        }
        if (str_contains($name, 'table') || str_contains($name, 'list') || str_contains($name, 'figure')) {
            return 'block-structure';
        }

        return 'inline-and-metadata';
    }

    private function absoluteUpstreamRoot(): string
    {
        if (str_starts_with($this->upstreamRoot, DIRECTORY_SEPARATOR)) {
            return rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->upstreamRoot);
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
        $head = $root . '/.git/HEAD';
        if (!is_file($head)) {
            return null;
        }

        $contents = trim((string) file_get_contents($head));
        if (str_starts_with($contents, 'ref: ')) {
            $refPath = $root . '/.git/' . substr($contents, 5);

            return is_file($refPath) ? trim((string) file_get_contents($refPath)) : null;
        }

        return preg_match('/^[0-9a-f]{40}$/', $contents) === 1 ? $contents : null;
    }
}
