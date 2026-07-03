<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DelimitedTextUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-delimited-text-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-delimited-text-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-delimited-text-root';
    public const CHECKED_IN_CURRENT_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/upstream-current-csv-reader';
    public const CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/generated-current-csv-reader';
    public const CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/generated-current-tsv-reader';
    public const EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT = 2;
    public const EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT = 0;
    public const EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT = 2;
    public const EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT = 4;

    private const CHECKED_IN_CURRENT_CSV_FIXTURES = [
        'csv.md' => [
            'role' => 'direct-csv-command-reader-native-output',
            'upstreamPath' => 'test/command/csv.md',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-current-csv-reader/csv.md',
            'sha256' => '42a8bc56612d061388889a10d73b1d34fb870595785ee550ef43c6a065a77ad6',
            'bytes' => 2719,
        ],
        '01.csv' => [
            'role' => 'direct-csv-command-reader-input-fixture',
            'upstreamPath' => 'test/command/01.csv',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-current-csv-reader/01.csv',
            'sha256' => '257c619e19786fddf7685a31a45f6495446a5213083540d09ecba6ce7f1e62cd',
            'bytes' => 47,
        ],
    ];

    private const CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURES = [
        'quoted-multiline.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-multiline',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.csv',
            'sha256' => 'a038fe6edd54cf98e2b3afaf14dd4e5cbdbbdb86ab2b62d9bd60cd783ce3324e',
            'bytes' => 80,
        ],
        'quoted-multiline.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-multiline',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.native',
            'sha256' => 'b0b4ae0c2f04421f042eef43c3a79ab699e771a3873e28b23e85d15091f03d57',
            'bytes' => 1894,
        ],
        'post-delimiter-space.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'post-delimiter-space',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-space.csv',
            'sha256' => '109867931d7a1d37a49d565c175d085415b378800e2acd2d4ec8f1c24935601f',
            'bytes' => 131,
        ],
        'post-delimiter-space.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'post-delimiter-space',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-space.native',
            'sha256' => '766278b6bf6c85a71a50a50df5c8ee776c7e774020897f8f39e34d9841a9c8d1',
            'bytes' => 1684,
        ],
    ];

    private const CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURES = [
        'simple.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'simple',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/simple.tsv',
            'sha256' => 'fcee0aed5a2fde11bbd19f2fc4445357a0d7bbd9c9962df6630fed4b6178ff8e',
            'bytes' => 71,
        ],
        'simple.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'simple',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/simple.native',
            'sha256' => 'f4c930c9d309c4dd6ec1c50eda9e45ff3614566e6c26e4b5254ce3e9c62abb2a',
            'bytes' => 1540,
        ],
        'quote-trailing.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'quote-trailing',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quote-trailing.tsv',
            'sha256' => 'c5694bc5e74a5920c4752369bd967be614f3d7f8fde6395bcd05c9b5f22d85dd',
            'bytes' => 102,
        ],
        'quote-trailing.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'quote-trailing',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quote-trailing.native',
            'sha256' => '51b8ce6dc3164f654f50f7fc1597e2788b04a2b634a32a3f52d51951b68260b6',
            'bytes' => 1975,
        ],
        'unicode-safe.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'unicode-safe',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unicode-safe.tsv',
            'sha256' => 'cd7a0f7e2c4737a1884c0ff3ec73bf6a5990fbdfb6ba1b588b6a6d9202ab3e02',
            'bytes' => 91,
        ],
        'unicode-safe.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'unicode-safe',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unicode-safe.native',
            'sha256' => 'e7d3ea0f37e8d3b0613155eaaf480edf042cd5e22aa4291866ae8a0e627fe990',
            'bytes' => 1370,
        ],
        'ragged-blank-fields.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'ragged-blank-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/ragged-blank-fields.tsv',
            'sha256' => '3eb62cad900b02542011bfcb6ffa891856dbf398aa7e7174785264494258c9d4',
            'bytes' => 76,
        ],
        'ragged-blank-fields.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'ragged-blank-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/ragged-blank-fields.native',
            'sha256' => 'a6f8a232c40e26e421c2640f35ff1f1010f24eb7e42341b9b09dfadfb86a2bee',
            'bytes' => 2159,
        ],
    ];

    private const GENERATED_CSV_NATIVE_SAMPLES = [
        'quoted-multiline' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.native',
        ],
        'post-delimiter-space' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-space.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-space.native',
        ],
    ];

    private const GENERATED_TSV_NATIVE_SAMPLES = [
        'simple' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/simple.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/simple.native',
        ],
        'quote-trailing' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quote-trailing.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quote-trailing.native',
        ],
        'unicode-safe' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unicode-safe.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unicode-safe.native',
        ],
        'ragged-blank-fields' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/ragged-blank-fields.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/ragged-blank-fields.native',
        ],
    ];

    private const SOURCE_FILES = [
        'src/Text/Pandoc/CSV.hs',
        'src/Text/Pandoc/Readers/CSV.hs',
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
        $generatedCsvNativeParityEvidence = self::generatedCsvNativeParityEvidence($this->repoRoot);
        $generatedTsvNativeParityEvidence = self::generatedTsvNativeParityEvidence($this->repoRoot);
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
                'staticCurrentEvidence' => self::checkedInCurrentEvidence($this->repoRoot),
                'generatedCsvNativeParityEvidence' => $generatedCsvNativeParityEvidence,
                'generatedTsvNativeParityEvidence' => $generatedTsvNativeParityEvidence,
                'validation' => [
                    'status' => 'not-evaluated-missing-upstream-root',
                    'issues' => ['missing-upstream-root'],
                ],
                'claim' => self::claim(),
                'claimBoundaries' => self::claimBoundaries(),
            ];
        }

        $upstreamFixtures = $this->upstreamFixtureEvidence($root);
        $sourceInventory = $this->sourceInventory($root);
        $validationIssues = $this->validationIssues($upstreamFixtures, $sourceInventory, $generatedCsvNativeParityEvidence, $generatedTsvNativeParityEvidence);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $this->gitHead($root),
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'fixtureDirectory' => 'test/command',
                'readerSources' => self::SOURCE_FILES,
            ],
            'denominator' => [
                'csvDirectFixtureCount' => count(self::CHECKED_IN_CURRENT_CSV_FIXTURES),
                'tsvDirectFixtureCount' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
                'fixtureScope' => 'direct CSV command reader fixtures in test/command',
                'csvDirectFixtures' => array_values(array_map(
                    static fn (array $fixture): string => (string) $fixture['upstreamPath'],
                    self::CHECKED_IN_CURRENT_CSV_FIXTURES
                )),
                'tsvDirectFixtures' => [],
                'upstreamFixtures' => $upstreamFixtures,
                'parserOptionFixtureCount' => 5,
                'parserOptionFixtures' => [
                    'comma-delimiter-no-header',
                    'space-delimiter-single-quote',
                    'backslash-escaped-quote',
                    'keep-space-after-delimiter',
                    'semicolon-delimiter-multiline-cell',
                ],
            ],
            'sourceInventory' => $sourceInventory,
            'staticCurrentEvidence' => self::checkedInCurrentEvidence($this->repoRoot),
            'generatedCsvNativeParityEvidence' => $generatedCsvNativeParityEvidence,
            'generatedTsvNativeParityEvidence' => $generatedTsvNativeParityEvidence,
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-delimited-text-reader-evidence' : 'invalid-upstream-delimited-text-reader-evidence',
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
        $fixtureDirectory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY);
        $generatedCsvNativeStaticEvidence = self::checkedInGeneratedCsvNativeEvidence($root);
        $generatedTsvNativeStaticEvidence = self::checkedInGeneratedTsvNativeEvidence($root);
        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-current-fixture-directory';
        }

        $fixtures = [];
        foreach (self::CHECKED_IN_CURRENT_CSV_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'upstreamPath' => (string) $snapshot['upstreamPath'],
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-current-csv-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-current-csv-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-current-csv-fixture-byte-count-mismatch';
            }
        }

        if (!self::hasRequiredGeneratedCsvNativeStaticEvidence($generatedCsvNativeStaticEvidence)) {
            $issues[] = 'invalid-checked-in-generated-csv-native-fixture-evidence';
        }

        if (!self::hasRequiredGeneratedTsvNativeStaticEvidence($generatedTsvNativeStaticEvidence)) {
            $issues[] = 'invalid-checked-in-generated-tsv-native-fixture-evidence';
        }

        return [
            'kind' => 'static-checked-in-current-upstream-delimited-text-reader-fixture-evidence',
            'upstream' => [
                'name' => 'jgm/pandoc',
                'commit' => self::EXPECTED_UPSTREAM_COMMIT,
                'fixtureDirectory' => 'test/command',
                'readerSources' => self::SOURCE_FILES,
            ],
            'readerDenominator' => [
                'csvDirectFixtureCount' => count(self::CHECKED_IN_CURRENT_CSV_FIXTURES),
                'tsvDirectFixtureCount' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
                'fixtureScope' => 'direct CSV command reader fixtures in test/command',
                'csvDirectFixtures' => array_values(array_map(
                    static fn (array $fixture): string => (string) $fixture['upstreamPath'],
                    self::CHECKED_IN_CURRENT_CSV_FIXTURES
                )),
                'tsvDirectFixtures' => [],
            ],
            'checkedInFixtureDirectory' => self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'generatedCsvNativeStaticEvidence' => $generatedCsvNativeStaticEvidence,
            'generatedTsvNativeStaticEvidence' => $generatedTsvNativeStaticEvidence,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-delimited-text-reader-evidence' : 'invalid-checked-in-current-delimited-text-reader-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static gate binding Pandoc current CSV command-reader fixtures and generated CSV/TSV native sample fixtures to checked-in SHA-256 and byte-count snapshots.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the checked-in csv.md and 01.csv snapshots match the pinned upstream command fixture hashes',
                    'the upstream command corpus has two CSV direct-reader fixtures tracked by this PHP reader',
                    'the generated CSV-to-native parity fixture pairs are present as local evidence and are not counted as extra upstream CSV direct fixtures',
                    'there is no dedicated upstream TSV command fixture in this pinned corpus',
                    'the generated TSV-to-native parity fixture pairs are present as local evidence and are not counted as upstream TSV direct fixtures',
                ],
                'doesNotAssert' => [
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'that RST csv-table integration is implemented locally',
                    'full CSV/TSV feature parity beyond the direct reader fixtures and local parser-option cases',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkedInGeneratedCsvNativeEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $fixtureDirectory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURE_DIRECTORY);
        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-generated-csv-native-fixture-directory';
        }

        $fixtures = [];
        foreach (self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'sample' => (string) $snapshot['sample'],
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-generated-csv-native-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-generated-csv-native-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-generated-csv-native-fixture-byte-count-mismatch';
            }
        }

        $samples = [];
        foreach (self::GENERATED_CSV_NATIVE_SAMPLES as $name => $sample) {
            $samples[] = [
                'name' => (string) $name,
                'inputPath' => (string) $sample['inputPath'],
                'expectedNativePath' => (string) $sample['expectedNativePath'],
            ];
        }

        return [
            'kind' => 'static-checked-in-generated-csv-native-parity-fixture-evidence',
            'evidenceKind' => 'generated-csv-native-parity-fixtures',
            'checkedInFixtureDirectory' => self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURE_DIRECTORY,
            'sampleCount' => count($samples),
            'samples' => $samples,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'csvDirectFixtureDenominator' => self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-generated-csv-native-parity-evidence' : 'invalid-checked-in-generated-csv-native-parity-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static local CSV-to-native fixture evidence; these fixture pairs are generated evidence and are not additional upstream CSV direct-reader fixtures.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the generated CSV input and expected native output fixture files match checked-in SHA-256 and byte-count snapshots',
                    'the generated CSV native sample count is separate from the upstream CSV direct fixture denominator',
                ],
                'doesNotAssert' => [
                    'that upstream adds dedicated CSV command fixtures for these generated samples',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full CSV/TSV feature parity beyond these generated CSV-to-native samples',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkedInGeneratedTsvNativeEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $fixtureDirectory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURE_DIRECTORY);
        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-generated-tsv-native-fixture-directory';
        }

        $fixtures = [];
        foreach (self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'sample' => (string) $snapshot['sample'],
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-generated-tsv-native-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-generated-tsv-native-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-generated-tsv-native-fixture-byte-count-mismatch';
            }
        }

        $samples = [];
        foreach (self::GENERATED_TSV_NATIVE_SAMPLES as $name => $sample) {
            $samples[] = [
                'name' => (string) $name,
                'inputPath' => (string) $sample['inputPath'],
                'expectedNativePath' => (string) $sample['expectedNativePath'],
            ];
        }

        return [
            'kind' => 'static-checked-in-generated-tsv-native-parity-fixture-evidence',
            'evidenceKind' => 'generated-tsv-native-parity-fixtures',
            'checkedInFixtureDirectory' => self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURE_DIRECTORY,
            'sampleCount' => count($samples),
            'samples' => $samples,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'tsvDirectFixtureDenominator' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-generated-tsv-native-parity-evidence' : 'invalid-checked-in-generated-tsv-native-parity-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static local TSV-to-native fixture evidence; these fixture pairs are generated evidence and are not upstream TSV direct-reader fixtures.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the generated TSV input and expected native output fixture files match checked-in SHA-256 and byte-count snapshots',
                    'the generated TSV native sample count is separate from the upstream TSV direct fixture denominator',
                ],
                'doesNotAssert' => [
                    'that upstream provides dedicated TSV command fixtures',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full CSV/TSV feature parity beyond these generated TSV-to-native samples',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function generatedCsvNativeParityEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $staticEvidence = self::checkedInGeneratedCsvNativeEvidence($root);
        $sampleResults = [];
        $parseFailures = [];
        $mismatches = [];
        $matchCount = 0;
        $comparedCount = 0;

        foreach (self::GENERATED_CSV_NATIVE_SAMPLES as $name => $sample) {
            $inputPath = (string) $sample['inputPath'];
            $expectedNativePath = (string) $sample['expectedNativePath'];
            $absoluteInputPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $inputPath);
            $absoluteExpectedNativePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $expectedNativePath);
            $input = is_file($absoluteInputPath) ? file_get_contents($absoluteInputPath) : false;
            $expectedNative = is_file($absoluteExpectedNativePath) ? file_get_contents($absoluteExpectedNativePath) : false;

            if (!is_string($input) || !is_string($expectedNative)) {
                $failure = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'inputError' => is_string($input) ? null : 'missing-or-unreadable-csv-input-fixture',
                    'expectedNativeError' => is_string($expectedNative) ? null : 'missing-or-unreadable-native-fixture',
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    ...$failure,
                ];
                continue;
            }

            try {
                $document = (new DelimitedTextReader())->readCsv($input, ['sourcePath' => $inputPath]);
                $generatedNative = PandocConverter::write($document, 'native');
            } catch (\Throwable $throwable) {
                $failure = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'inputError' => $throwable::class . ': ' . $throwable->getMessage(),
                    'expectedNativeError' => null,
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    ...$failure,
                ];
                continue;
            }

            ++$comparedCount;
            $expectedTokens = self::nativeTokenStream($expectedNative);
            $generatedTokens = self::nativeTokenStream($generatedNative);
            $matched = $expectedTokens === $generatedTokens;
            if ($matched) {
                ++$matchCount;
            } else {
                $mismatches[] = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'firstDifference' => self::firstStringDifference($expectedTokens, $generatedTokens) ?? 'unknown-native-token-difference',
                ];
            }

            $table = $document->children[0] ?? null;
            $packet = $table instanceof AstNode ? $table->attr('delimitedText', []) : [];
            $sampleResults[] = [
                'name' => (string) $name,
                'status' => $matched ? 'matched' : 'mismatched',
                'inputPath' => $inputPath,
                'expectedNativePath' => $expectedNativePath,
                'reader' => 'csv',
                'expectedNativeSha256' => hash('sha256', $expectedNative),
                'generatedNativeSha256' => hash('sha256', $generatedNative),
                'expectedNativeTokenSha256' => hash('sha256', $expectedTokens),
                'generatedNativeTokenSha256' => hash('sha256', $generatedTokens),
                'rowCount' => is_array($packet) ? ($packet['rowCount'] ?? null) : null,
                'columnCount' => is_array($packet) ? ($packet['columnCount'] ?? null) : null,
                'csvDirectFixtureDenominator' => self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT,
            ];
        }

        $sampleCount = count(self::GENERATED_CSV_NATIVE_SAMPLES);
        $mismatchCount = $comparedCount - $matchCount;
        $staticEvidenceValid = self::hasRequiredGeneratedCsvNativeStaticEvidence($staticEvidence);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'kind' => 'executable-generated-csv-native-parity-evidence',
            'evidenceKind' => 'generated-csv-native-parity',
            'status' => 'completed-generated-csv-native-parity-evidence',
            'claim' => 'Executes the local PHP CSV reader and native writer for checked-in generated CSV samples; these are not extra upstream CSV direct fixtures or upstream runner results.',
            'fixtureDirectory' => self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURE_DIRECTORY,
            'reader' => 'csv',
            'csvDirectFixtureDenominator' => self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT,
            'sampleCount' => $sampleCount,
            'comparedSampleCount' => $comparedCount,
            'parseFailureCount' => count($parseFailures),
            'generatedNativeMatchCount' => $matchCount,
            'generatedNativeMismatchCount' => $mismatchCount,
            'generatedNativeMatchPercent' => self::percent($matchCount, $sampleCount),
            'parityStatus' => self::generatedCsvNativeParityStatus($staticEvidenceValid, count($parseFailures), $mismatchCount, $comparedCount, $sampleCount),
            'staticFixtureEvidence' => $staticEvidence,
            'samples' => $sampleResults,
            'parseFailures' => $parseFailures,
            'mismatches' => $mismatches,
            'claimBoundaries' => [
                'doesAssert' => [
                    'the local CSV reader can read the checked-in generated CSV samples',
                    'the generated native output matches the checked-in expected native fixtures by normalized native token stream',
                    'the upstream CSV direct fixture denominator remains two',
                ],
                'doesNotAssert' => [
                    'that the generated CSV samples are upstream command fixtures',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full CSV/TSV feature parity beyond these generated CSV-to-native samples',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function generatedTsvNativeParityEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $staticEvidence = self::checkedInGeneratedTsvNativeEvidence($root);
        $sampleResults = [];
        $parseFailures = [];
        $mismatches = [];
        $matchCount = 0;
        $comparedCount = 0;

        foreach (self::GENERATED_TSV_NATIVE_SAMPLES as $name => $sample) {
            $inputPath = (string) $sample['inputPath'];
            $expectedNativePath = (string) $sample['expectedNativePath'];
            $absoluteInputPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $inputPath);
            $absoluteExpectedNativePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $expectedNativePath);
            $input = is_file($absoluteInputPath) ? file_get_contents($absoluteInputPath) : false;
            $expectedNative = is_file($absoluteExpectedNativePath) ? file_get_contents($absoluteExpectedNativePath) : false;

            if (!is_string($input) || !is_string($expectedNative)) {
                $failure = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'inputError' => is_string($input) ? null : 'missing-or-unreadable-tsv-input-fixture',
                    'expectedNativeError' => is_string($expectedNative) ? null : 'missing-or-unreadable-native-fixture',
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    ...$failure,
                ];
                continue;
            }

            try {
                $document = (new DelimitedTextReader())->readTsv($input, ['sourcePath' => $inputPath]);
                $generatedNative = PandocConverter::write($document, 'native');
            } catch (\Throwable $throwable) {
                $failure = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'inputError' => $throwable::class . ': ' . $throwable->getMessage(),
                    'expectedNativeError' => null,
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    ...$failure,
                ];
                continue;
            }

            ++$comparedCount;
            $expectedTokens = self::nativeTokenStream($expectedNative);
            $generatedTokens = self::nativeTokenStream($generatedNative);
            $matched = $expectedTokens === $generatedTokens;
            if ($matched) {
                ++$matchCount;
            } else {
                $mismatches[] = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'firstDifference' => self::firstStringDifference($expectedTokens, $generatedTokens) ?? 'unknown-native-token-difference',
                ];
            }

            $table = $document->children[0] ?? null;
            $packet = $table instanceof AstNode ? $table->attr('delimitedText', []) : [];
            $sampleResults[] = [
                'name' => (string) $name,
                'status' => $matched ? 'matched' : 'mismatched',
                'inputPath' => $inputPath,
                'expectedNativePath' => $expectedNativePath,
                'reader' => 'tsv',
                'expectedNativeSha256' => hash('sha256', $expectedNative),
                'generatedNativeSha256' => hash('sha256', $generatedNative),
                'expectedNativeTokenSha256' => hash('sha256', $expectedTokens),
                'generatedNativeTokenSha256' => hash('sha256', $generatedTokens),
                'rowCount' => is_array($packet) ? ($packet['rowCount'] ?? null) : null,
                'columnCount' => is_array($packet) ? ($packet['columnCount'] ?? null) : null,
                'tsvDirectFixtureDenominator' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
            ];
        }

        $sampleCount = count(self::GENERATED_TSV_NATIVE_SAMPLES);
        $mismatchCount = $comparedCount - $matchCount;
        $staticEvidenceValid = self::hasRequiredGeneratedTsvNativeStaticEvidence($staticEvidence);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'kind' => 'executable-generated-tsv-native-parity-evidence',
            'evidenceKind' => 'generated-tsv-native-parity',
            'status' => 'completed-generated-tsv-native-parity-evidence',
            'claim' => 'Executes the local PHP TSV reader and native writer for checked-in generated TSV samples; these are not upstream TSV direct fixtures or upstream runner results.',
            'fixtureDirectory' => self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURE_DIRECTORY,
            'reader' => 'tsv',
            'tsvDirectFixtureDenominator' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
            'sampleCount' => $sampleCount,
            'comparedSampleCount' => $comparedCount,
            'parseFailureCount' => count($parseFailures),
            'generatedNativeMatchCount' => $matchCount,
            'generatedNativeMismatchCount' => $mismatchCount,
            'generatedNativeMatchPercent' => self::percent($matchCount, $sampleCount),
            'parityStatus' => self::generatedTsvNativeParityStatus($staticEvidenceValid, count($parseFailures), $mismatchCount, $comparedCount, $sampleCount),
            'staticFixtureEvidence' => $staticEvidence,
            'samples' => $sampleResults,
            'parseFailures' => $parseFailures,
            'mismatches' => $mismatches,
            'claimBoundaries' => [
                'doesAssert' => [
                    'the local TSV reader can read the checked-in generated TSV samples',
                    'the generated native output matches the checked-in expected native fixtures by normalized native token stream',
                    'the upstream TSV direct fixture denominator remains zero',
                ],
                'doesNotAssert' => [
                    'that the generated TSV samples are upstream command fixtures',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full CSV/TSV feature parity beyond these generated TSV-to-native samples',
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
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
        $upstream = is_array($report['upstream'] ?? null) ? $report['upstream'] : [];
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $staticValidation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];
        $generatedCsvNative = is_array($report['generatedCsvNativeParityEvidence'] ?? null) ? $report['generatedCsvNativeParityEvidence'] : [];
        $generatedTsvNative = is_array($report['generatedTsvNativeParityEvidence'] ?? null) ? $report['generatedTsvNativeParityEvidence'] : [];

        return implode(PHP_EOL, [
            'Pandoc delimited text reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Upstream: ' . (string) ($upstream['commit'] ?? 'unknown')
                . ' expected=' . (string) ($upstream['expectedCommit'] ?? self::EXPECTED_UPSTREAM_COMMIT),
            'CSV direct fixtures: ' . (int) ($denominator['csvDirectFixtureCount'] ?? 0),
            'TSV direct fixtures: ' . (int) ($denominator['tsvDirectFixtureCount'] ?? 0),
            'Static current evidence: ' . (string) ($staticValidation['status'] ?? 'unknown')
                . ' checkedInFixtures=' . (int) ($staticEvidence['checkedInFixtureCount'] ?? 0),
            'Generated CSV native parity: ' . (int) ($generatedCsvNative['generatedNativeMatchCount'] ?? 0)
                . '/' . (int) ($generatedCsvNative['sampleCount'] ?? 0)
                . ' status=' . (string) ($generatedCsvNative['parityStatus'] ?? 'unknown'),
            'Generated TSV native parity: ' . (int) ($generatedTsvNative['generatedNativeMatchCount'] ?? 0)
                . '/' . (int) ($generatedTsvNative['sampleCount'] ?? 0)
                . ' status=' . (string) ($generatedTsvNative['parityStatus'] ?? 'unknown'),
            'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
            'No upstream Haskell/Cabal runner result, RST csv-table integration, or full CSV/TSV feature parity is asserted.',
        ]) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticCurrentEvidence(array $report): bool
    {
        $evidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];
        $denominator = is_array($evidence['readerDenominator'] ?? null) ? $evidence['readerDenominator'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-current-delimited-text-reader-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($denominator['csvDirectFixtureCount'] ?? -1) === self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT
            && (int) ($denominator['tsvDirectFixtureCount'] ?? -1) === self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT
            && self::hasRequiredGeneratedCsvNativeStaticEvidence(
                is_array($evidence['generatedCsvNativeStaticEvidence'] ?? null) ? $evidence['generatedCsvNativeStaticEvidence'] : []
            )
            && self::hasRequiredGeneratedTsvNativeStaticEvidence(
                is_array($evidence['generatedTsvNativeStaticEvidence'] ?? null) ? $evidence['generatedTsvNativeStaticEvidence'] : []
            );
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-delimited-text-reader-evidence'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedCsvNativeStaticEvidence(array $evidence): bool
    {
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-generated-csv-native-parity-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($evidence['sampleCount'] ?? -1) === self::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT
            && (int) ($evidence['checkedInFixtureCount'] ?? -1) === count(self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURES)
            && (int) ($evidence['csvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT;
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedTsvNativeStaticEvidence(array $evidence): bool
    {
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-generated-tsv-native-parity-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($evidence['sampleCount'] ?? -1) === self::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT
            && (int) ($evidence['checkedInFixtureCount'] ?? -1) === count(self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURES)
            && (int) ($evidence['tsvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT;
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedCsvNativeParity(array $evidence, int $requiredSampleCount = self::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT): bool
    {
        if ($requiredSampleCount < 0) {
            throw new \InvalidArgumentException('Required generated CSV native sample count must not be negative');
        }

        $staticEvidence = is_array($evidence['staticFixtureEvidence'] ?? null) ? $evidence['staticFixtureEvidence'] : [];

        return ($evidence['status'] ?? null) === 'completed-generated-csv-native-parity-evidence'
            && (int) ($evidence['csvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT
            && (int) ($evidence['sampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['comparedSampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['parseFailureCount'] ?? -1) === 0
            && (int) ($evidence['generatedNativeMatchCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['generatedNativeMismatchCount'] ?? -1) === 0
            && ($evidence['parityStatus'] ?? null) === 'generated-csv-native-parity-observed-not-upstream-fixture'
            && self::hasRequiredGeneratedCsvNativeStaticEvidence($staticEvidence);
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedTsvNativeParity(array $evidence, int $requiredSampleCount = self::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT): bool
    {
        if ($requiredSampleCount < 0) {
            throw new \InvalidArgumentException('Required generated TSV native sample count must not be negative');
        }

        $staticEvidence = is_array($evidence['staticFixtureEvidence'] ?? null) ? $evidence['staticFixtureEvidence'] : [];

        return ($evidence['status'] ?? null) === 'completed-generated-tsv-native-parity-evidence'
            && (int) ($evidence['tsvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT
            && (int) ($evidence['sampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['comparedSampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['parseFailureCount'] ?? -1) === 0
            && (int) ($evidence['generatedNativeMatchCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['generatedNativeMismatchCount'] ?? -1) === 0
            && ($evidence['parityStatus'] ?? null) === 'generated-tsv-native-parity-observed-not-upstream-fixture'
            && self::hasRequiredGeneratedTsvNativeStaticEvidence($staticEvidence);
    }

    private static function claim(): string
    {
        return 'Tracks the current upstream direct CSV command-reader fixtures, two generated CSV-to-native evidence samples, the absence of dedicated TSV command fixtures, and four generated TSV-to-native evidence samples for the delimited text reader.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the count and file identities of upstream direct CSV command-reader fixtures tracked locally',
                'that the current pinned upstream CSV reader source files are present when an upstream checkout is inspected',
                'that no dedicated TSV command fixture is available in the pinned direct-reader evidence set',
                'static checked-in current csv.md and 01.csv fixture identity when staticCurrentEvidence is valid',
                'two generated CSV-to-native local samples when generatedCsvNativeParityEvidence is valid',
                'four generated TSV-to-native local samples when generatedTsvNativeParityEvidence is valid',
            ],
            'doesNotAssert' => [
                'that upstream Haskell/Cabal/Tasty tests were executed',
                'that local PHP output matches every upstream CSV-adjacent command fixture',
                'that the generated CSV samples are upstream command fixtures',
                'that the generated TSV samples are upstream command fixtures',
                'RST csv-table reader integration',
                'full CSV/TSV feature parity beyond the direct reader fixture evidence',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'csvDirectFixtureCount' => 0,
            'tsvDirectFixtureCount' => 0,
            'fixtureScope' => 'direct CSV command reader fixtures in test/command',
            'csvDirectFixtures' => [],
            'tsvDirectFixtures' => [],
            'upstreamFixtures' => [],
            'parserOptionFixtureCount' => 0,
            'parserOptionFixtures' => [],
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
     * @return list<array{name: string, role: string, path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}>
     */
    private function upstreamFixtureEvidence(string $root): array
    {
        $fixtures = [];
        foreach (self::CHECKED_IN_CURRENT_CSV_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['upstreamPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                ...$file,
            ];
        }

        return $fixtures;
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
     * @param list<array{name: string, role: string, path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}> $upstreamFixtures
     * @param array<string, mixed> $sourceInventory
     * @param array<string, mixed> $generatedCsvNativeParityEvidence
     * @param array<string, mixed> $generatedTsvNativeParityEvidence
     * @return list<string>
     */
    private function validationIssues(array $upstreamFixtures, array $sourceInventory, array $generatedCsvNativeParityEvidence, array $generatedTsvNativeParityEvidence): array
    {
        $issues = [];
        foreach ($upstreamFixtures as $fixture) {
            if (($fixture['present'] ?? false) !== true) {
                $issues[] = 'missing-upstream-csv-command-fixture';
            } elseif (($fixture['sha256'] ?? null) !== ($fixture['expectedSha256'] ?? null)) {
                $issues[] = 'upstream-csv-command-fixture-sha256-mismatch';
            } elseif ((int) ($fixture['bytes'] ?? -1) !== (int) ($fixture['expectedBytes'] ?? -2)) {
                $issues[] = 'upstream-csv-command-fixture-byte-count-mismatch';
            }
        }

        if ((int) ($sourceInventory['missingFileCount'] ?? 0) > 0) {
            $issues[] = 'missing-upstream-delimited-text-reader-source';
        }

        if (!self::hasRequiredGeneratedCsvNativeParity($generatedCsvNativeParityEvidence)) {
            $issues[] = 'generated-csv-native-parity-not-observed';
        }

        if (!self::hasRequiredGeneratedTsvNativeParity($generatedTsvNativeParityEvidence)) {
            $issues[] = 'generated-tsv-native-parity-not-observed';
        }

        return array_values(array_unique($issues));
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

    private static function nativeTokenStream(string $native): string
    {
        $native = (string) preg_replace('/\[\s*\]/', '[]', $native);
        $native = (string) preg_replace('/\(\s*""\s*,\s*\[\]\s*,\s*\[\]\s*\)/', '("",[],[])', $native);

        return (string) preg_replace('/\s+/', ' ', trim($native));
    }

    private static function firstStringDifference(string $expected, string $actual): ?string
    {
        if ($expected === $actual) {
            return null;
        }

        $limit = min(strlen($expected), strlen($actual));
        for ($offset = 0; $offset < $limit; $offset++) {
            if ($expected[$offset] === $actual[$offset]) {
                continue;
            }

            return 'native token byte ' . $offset
                . ' expected=' . bin2hex($expected[$offset])
                . ' actual=' . bin2hex($actual[$offset]);
        }

        return 'native token length expected=' . strlen($expected) . ' actual=' . strlen($actual);
    }

    private static function generatedCsvNativeParityStatus(bool $staticEvidenceValid, int $parseFailureCount, int $mismatchCount, int $comparedCount, int $sampleCount): string
    {
        if (!$staticEvidenceValid) {
            return 'blocked-by-generated-csv-native-fixture-validation';
        }
        if ($parseFailureCount > 0) {
            return 'blocked-by-generated-csv-native-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'generated-csv-native-mismatches-observed';
        }
        if ($sampleCount > 0 && $comparedCount === $sampleCount) {
            return 'generated-csv-native-parity-observed-not-upstream-fixture';
        }

        return 'not-evaluated-no-generated-csv-native-samples';
    }

    private static function generatedTsvNativeParityStatus(bool $staticEvidenceValid, int $parseFailureCount, int $mismatchCount, int $comparedCount, int $sampleCount): string
    {
        if (!$staticEvidenceValid) {
            return 'blocked-by-generated-tsv-native-fixture-validation';
        }
        if ($parseFailureCount > 0) {
            return 'blocked-by-generated-tsv-native-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'generated-tsv-native-mismatches-observed';
        }
        if ($sampleCount > 0 && $comparedCount === $sampleCount) {
            return 'generated-tsv-native-parity-observed-not-upstream-fixture';
        }

        return 'not-evaluated-no-generated-tsv-native-samples';
    }

    private static function percent(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
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
