<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxWriterGoldenManifest
{
    public const DEFAULT_RELATIVE_DOCX_DIR = DocxParityCorpusAudit::DEFAULT_RELATIVE_DOCX_DIR;
    public const PINNED_UPSTREAM_GOLDEN_PACKAGE_COUNT = 38;
    public const STATUS_REPORTED = 'reported_writer_golden_package_inventory';
    public const STATUS_SKIPPED_MISSING_GOLDEN_DIRECTORY = 'skipped_missing_writer_golden_directory';
    public const STATUS_SKIPPED_UNREADABLE_GOLDEN_DIRECTORY = 'skipped_unreadable_writer_golden_directory';
    public const EVIDENCE_KIND = 'writer-golden-package-inventory-only';
    public const EVIDENCE_KIND_GENERATED_COMPARISON = 'writer-golden-package-generated-stable-comparison';
    public const EXPECTED_WRITER_CLASS = 'PortLibs\\Pandoc\\DocxWriter';
    public const EXPECTED_WRITER_RELATIVE_PATH = 'lanes/pandoc/src/DocxWriter.php';
    public const OPEN_REASON = 'writer-unsupported-no-DocxWriter-implementation-and-docx-output-registry-unsupported';
    public const COMPARISON_NOT_RECORDED_REASON = 'docx-writer-comparison-still-not-recorded';
    public const GENERATED_DIRECTORY_NOT_CONFIGURED_REASON = 'generated-docx-directory-not-configured';
    public const GENERATED_DIRECTORY_MISSING_REASON = 'generated-docx-directory-missing';
    public const GENERATED_DIRECTORY_UNREADABLE_REASON = 'generated-docx-directory-unreadable';
    public const GENERATED_COMPARISON_MISMATCH_REASON = 'generated-docx-package-stable-semantic-mismatches-or-coverage-gaps';
    public const GENERATED_COMPARISON_MATCH_REASON = 'all-generated-docx-packages-match-upstream-golden-stable-semantics';
    public const CLAIM = 'Inventories upstream golden DOCX packages and local writer support status; when generated DOCX packages are supplied, compares them to upstream writer golden packages by stable package semantics. No writer parity is asserted without generated comparisons.';

    private readonly string $repoRoot;
    private readonly string $docxDirectory;
    private readonly ?string $generatedDocxDirectory;

    public function __construct(
        string $repoRoot,
        string $docxDirectory = self::DEFAULT_RELATIVE_DOCX_DIR,
        private readonly int $sampleLimit = 8,
        ?string $generatedDocxDirectory = null
    ) {
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('Repository root must not be empty');
        }
        if ($docxDirectory === '') {
            throw new \InvalidArgumentException('DOCX directory must not be empty');
        }
        if ($generatedDocxDirectory === '') {
            throw new \InvalidArgumentException('Generated DOCX directory must not be empty when provided');
        }
        if ($sampleLimit < 0) {
            throw new \InvalidArgumentException('Sample limit must not be negative');
        }

        $this->repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $this->docxDirectory = $docxDirectory;
        $this->generatedDocxDirectory = $generatedDocxDirectory;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $goldenDir = $this->absoluteGoldenDirectory();
        $writer = $this->localWriterEvidence();

        if (!is_dir($goldenDir)) {
            return $this->skipReport(
                self::STATUS_SKIPPED_MISSING_GOLDEN_DIRECTORY,
                "Upstream DOCX writer golden directory does not exist: {$goldenDir}",
                $writer
            );
        }
        if (!is_readable($goldenDir)) {
            return $this->skipReport(
                self::STATUS_SKIPPED_UNREADABLE_GOLDEN_DIRECTORY,
                "Upstream DOCX writer golden directory is not readable: {$goldenDir}",
                $writer
            );
        }

        $packageRows = $this->packageRows($goldenDir);
        $packageCount = count($packageRows);
        $readablePackages = count(array_filter(
            $packageRows,
            static fn (array $row): bool => ($row['status'] ?? null) === 'readable'
        ));
        $partCount = array_sum(array_map(
            static fn (array $row): int => (int) ($row['partCount'] ?? 0),
            $packageRows
        ));
        $readablePartCount = array_sum(array_map(
            static fn (array $row): int => (int) ($row['readablePartCount'] ?? 0),
            $packageRows
        ));

        $packageComparison = $this->packageComparisonEvidence($writer, $packageRows);

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-docx-writer-golden-audit',
            'status' => self::STATUS_REPORTED,
            'skipped' => false,
            'claim' => self::CLAIM,
            'evidenceKind' => (($packageComparison['run'] ?? false) === true)
                ? self::EVIDENCE_KIND_GENERATED_COMPARISON
                : self::EVIDENCE_KIND,
            'repoRoot' => $this->repoRoot,
            'upstreamDocxDirectory' => $this->absoluteDocxDirectory(),
            'upstreamDocxDirectoryDisplay' => $this->displayPath($this->absoluteDocxDirectory()),
            'goldenDirectory' => $goldenDir,
            'goldenDirectoryDisplay' => $this->displayPath($goldenDir),
            'goldenDirectoryPresent' => true,
            'expectedUpstreamWriterSourceReferences' => self::expectedUpstreamWriterSourceReferences(),
            'localWriter' => $writer,
            'packageComparison' => $packageComparison,
            'goldenPackageCount' => $packageCount,
            'readableGoldenPackageCount' => $readablePackages,
            'unreadableGoldenPackageCount' => $packageCount - $readablePackages,
            'packagePartCount' => $partCount,
            'readablePackagePartCount' => $readablePartCount,
            'packageRows' => $packageRows,
            'packageSamples' => array_slice($packageRows, 0, $this->sampleLimit),
            'notes' => [
                'Each part hash is the SHA-256 of the uncompressed package part bytes; raw package bytes are not emitted.',
                'These upstream golden DOCX files are expected writer outputs, not generated during this audit.',
                'Writer parity remains open until generated local DOCX packages match upstream package parts, relationships, content types, and document XML semantics.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $lines = [
            'Pandoc DOCX writer golden package manifest',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'Golden directory: ' . (string) ($report['goldenDirectoryDisplay'] ?? $report['goldenDirectory'] ?? ''),
        ];

        $writer = $report['localWriter'] ?? [];
        if (is_array($writer)) {
            $lines[] = 'DOCX writer implementation: '
                . (string) ($writer['status'] ?? 'unknown')
                . '; classExists='
                . self::boolText($writer['classExists'] ?? false)
                . '; fileExists='
                . self::boolText($writer['fileExists'] ?? false)
                . '; registryStatus='
                . (string) ($writer['registryStatus'] ?? 'unknown');
        }

        $comparison = $report['packageComparison'] ?? [];
        if (is_array($comparison)) {
            $expected = (int) ($comparison['expectedGoldenPackageCount'] ?? $report['goldenPackageCount'] ?? 0);
            $compared = (int) ($comparison['comparedPackageCount'] ?? 0);
            $lines[] = 'Generated package comparison: '
                . ((($comparison['run'] ?? false) === true) ? 'run' : 'not run')
                . '; compared='
                . $compared
                . '/'
                . $expected
                . '; matched='
                . (int) ($comparison['matchedPackageCount'] ?? 0)
                . '; mismatched='
                . (int) ($comparison['mismatchedPackageCount'] ?? 0)
                . '; missing='
                . (int) ($comparison['missingGeneratedPackageCount'] ?? 0)
                . '; unexpected='
                . (int) ($comparison['unexpectedGeneratedPackageCount'] ?? 0)
                . '; reason='
                . (string) ($comparison['reason'] ?? 'unknown');
        }

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'Result: skipped';
            $lines[] = 'Reason: ' . (string) ($report['reason'] ?? 'golden directory unavailable');
            $lines[] = 'No DOCX writer parity is asserted.';

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $lines[] = 'Golden packages: '
            . (int) ($report['goldenPackageCount'] ?? 0)
            . '; readable packages: '
            . (int) ($report['readableGoldenPackageCount'] ?? 0)
            . '; package parts: '
            . (int) ($report['packagePartCount'] ?? 0)
            . '; readable parts: '
            . (int) ($report['readablePackagePartCount'] ?? 0);

        $samples = $report['packageSamples'] ?? [];
        if (is_array($samples) && $samples !== []) {
            $lines[] = 'Golden package samples:';
            foreach ($samples as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $partNames = $row['partNames'] ?? [];
                $partSummary = is_array($partNames) && $partNames !== []
                    ? implode(', ', array_slice(array_map('strval', $partNames), 0, 6))
                    : 'none';
                $lines[] = '- '
                    . (string) ($row['fileName'] ?? 'unknown.docx')
                    . ': parts='
                    . (int) ($row['partCount'] ?? 0)
                    . '; packageSha256='
                    . (string) ($row['packageSha256'] ?? '')
                    . '; firstParts='
                    . $partSummary;
            }
        }

        $lines[] = (($comparison['run'] ?? false) === true)
            ? 'Generated DOCX packages were compared by stable package semantics; full writer parity is still limited to this generated package evidence.'
            : 'No generated DOCX package parity is asserted.';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @return list<array<string, string>>
     */
    public static function expectedUpstreamWriterSourceReferences(): array
    {
        return [
            [
                'kind' => 'writer-implementation',
                'path' => 'src/Text/Pandoc/Writers/Docx.hs',
                'role' => 'upstream DOCX writer implementation to port or compare against',
            ],
            [
                'kind' => 'writer-tests',
                'path' => 'test/Tests/Writers/Docx.hs',
                'role' => 'upstream DOCX writer golden-test definitions',
            ],
            [
                'kind' => 'writer-golden-packages',
                'path' => 'test/docx/golden/*.docx',
                'role' => 'expected upstream DOCX writer package outputs',
            ],
            [
                'kind' => 'reference-docx',
                'path' => 'data/default.docx',
                'role' => 'upstream reference DOCX template used by writer behavior',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $writer
     * @return array<string, mixed>
     */
    private function skipReport(string $status, string $reason, array $writer): array
    {
        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-docx-writer-golden-audit',
            'status' => $status,
            'skipped' => true,
            'claim' => self::CLAIM,
            'evidenceKind' => self::EVIDENCE_KIND,
            'repoRoot' => $this->repoRoot,
            'upstreamDocxDirectory' => $this->absoluteDocxDirectory(),
            'upstreamDocxDirectoryDisplay' => $this->displayPath($this->absoluteDocxDirectory()),
            'goldenDirectory' => $this->absoluteGoldenDirectory(),
            'goldenDirectoryDisplay' => $this->displayPath($this->absoluteGoldenDirectory()),
            'goldenDirectoryPresent' => false,
            'reason' => $reason,
            'expectedUpstreamWriterSourceReferences' => self::expectedUpstreamWriterSourceReferences(),
            'localWriter' => $writer,
            'packageComparison' => $this->packageComparisonEvidence($writer, [], self::PINNED_UPSTREAM_GOLDEN_PACKAGE_COUNT),
            'goldenPackageCount' => 0,
            'readableGoldenPackageCount' => 0,
            'unreadableGoldenPackageCount' => 0,
            'packagePartCount' => 0,
            'readablePackagePartCount' => 0,
            'packageRows' => [],
            'packageSamples' => [],
            'notes' => [
                'The local upstream DOCX corpus cache is optional for CI.',
                'No writer golden package inventory or parity result is inferred when the golden directory is absent.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function localWriterEvidence(): array
    {
        $writerPath = $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::EXPECTED_WRITER_RELATIVE_PATH);
        $writerFileExists = is_file($writerPath);
        $writerClassExists = class_exists(self::EXPECTED_WRITER_CLASS);
        $registry = PandocFormatRegistry::phpOutputSupport()['docx'] ?? [
            'status' => 'unknown',
            'implementation' => '',
            'notes' => 'DOCX output format is missing from the registry.',
        ];
        $registryStatus = (string) ($registry['status'] ?? 'unknown');
        $unsupported = !$writerFileExists && !$writerClassExists && $registryStatus === 'unsupported';

        return [
            'status' => $unsupported ? 'unsupported' : 'implementation-present-or-registered',
            'expectedClass' => self::EXPECTED_WRITER_CLASS,
            'classExists' => $writerClassExists,
            'expectedPath' => self::EXPECTED_WRITER_RELATIVE_PATH,
            'absolutePath' => $writerPath,
            'fileExists' => $writerFileExists,
            'registryFormat' => 'docx',
            'registryStatus' => $registryStatus,
            'registryImplementation' => (string) ($registry['implementation'] ?? ''),
            'registryNotes' => (string) ($registry['notes'] ?? ''),
            'unsupportedReason' => $unsupported ? self::OPEN_REASON : self::COMPARISON_NOT_RECORDED_REASON,
        ];
    }

    /**
     * @param array<string, mixed> $writer
     * @return array<string, mixed>
     */
    private function packageComparisonEvidence(array $writer, array $goldenPackageRows, ?int $expectedGoldenPackageCount = null): array
    {
        $expectedGoldenPackageCount ??= count($goldenPackageRows);
        $generatedDir = $this->absoluteGeneratedDirectory();
        $generatedDirConfigured = $generatedDir !== null;
        $base = [
            'run' => false,
            'status' => 'not-run',
            'generatedDirectoryConfigured' => $generatedDirConfigured,
            'generatedDirectory' => $generatedDir,
            'generatedDirectoryDisplay' => $generatedDir === null ? null : $this->displayPath($generatedDir),
            'generatedDirectoryPresent' => $generatedDir !== null && is_dir($generatedDir),
            'stableComparisonContract' => self::stableComparisonContract(),
            'expectedGoldenPackageCount' => $expectedGoldenPackageCount,
            'generatedPackageCount' => 0,
            'comparedPackageCount' => 0,
            'matchedPackageCount' => 0,
            'mismatchedPackageCount' => 0,
            'missingGeneratedPackageCount' => $expectedGoldenPackageCount,
            'unexpectedGeneratedPackageCount' => 0,
            'unreadableComparisonPackageCount' => 0,
            'comparisonCoveragePercent' => self::percent(0, $expectedGoldenPackageCount),
            'stableMatchPercent' => self::percent(0, $expectedGoldenPackageCount),
            'allStableSemanticsMatch' => false,
            'reason' => (string) ($writer['unsupportedReason'] ?? self::OPEN_REASON),
            'requiredBeforeParityClaim' => 'Run the native PHP DocxWriter against upstream writer golden cases and compare package parts, relationships, content types, and document XML semantics.',
            'comparisonRows' => [],
            'comparisonSamples' => [],
        ];

        if ($generatedDir === null) {
            $reason = (($writer['status'] ?? null) === 'unsupported')
                ? (string) ($writer['unsupportedReason'] ?? self::OPEN_REASON)
                : self::GENERATED_DIRECTORY_NOT_CONFIGURED_REASON;

            return array_replace($base, [
                'status' => 'not-run-generated-directory-not-configured',
                'reason' => $reason,
            ]);
        }

        if (!is_dir($generatedDir)) {
            return array_replace($base, [
                'status' => 'not-run-generated-directory-missing',
                'reason' => self::GENERATED_DIRECTORY_MISSING_REASON,
            ]);
        }

        if (!is_readable($generatedDir)) {
            return array_replace($base, [
                'status' => 'not-run-generated-directory-unreadable',
                'reason' => self::GENERATED_DIRECTORY_UNREADABLE_REASON,
            ]);
        }

        $generatedRows = $this->packageRows($generatedDir);
        $comparisonRows = $this->comparisonRows($goldenPackageRows, $generatedRows);
        $generatedPackageCount = count($generatedRows);
        $comparedCount = count(array_filter(
            $comparisonRows,
            static fn (array $row): bool => ($row['compared'] ?? false) === true
        ));
        $matchedCount = count(array_filter(
            $comparisonRows,
            static fn (array $row): bool => ($row['status'] ?? null) === 'stable-match'
        ));
        $mismatchedCount = count(array_filter(
            $comparisonRows,
            static fn (array $row): bool => ($row['status'] ?? null) === 'stable-mismatch'
        ));
        $missingCount = count(array_filter(
            $comparisonRows,
            static fn (array $row): bool => ($row['status'] ?? null) === 'missing-generated'
        ));
        $unexpectedCount = count(array_filter(
            $comparisonRows,
            static fn (array $row): bool => ($row['status'] ?? null) === 'unexpected-generated'
        ));
        $unreadableCount = count(array_filter(
            $comparisonRows,
            static fn (array $row): bool => ($row['status'] ?? null) === 'unreadable-package'
        ));
        $allMatch = $expectedGoldenPackageCount > 0
            && $matchedCount === $expectedGoldenPackageCount
            && $mismatchedCount === 0
            && $missingCount === 0
            && $unexpectedCount === 0
            && $unreadableCount === 0;

        return array_replace($base, [
            'run' => true,
            'status' => $allMatch ? 'matched-stable-package-semantics' : 'mismatched-stable-package-semantics',
            'generatedDirectoryPresent' => true,
            'generatedPackageCount' => $generatedPackageCount,
            'comparedPackageCount' => $comparedCount,
            'matchedPackageCount' => $matchedCount,
            'mismatchedPackageCount' => $mismatchedCount,
            'missingGeneratedPackageCount' => $missingCount,
            'unexpectedGeneratedPackageCount' => $unexpectedCount,
            'unreadableComparisonPackageCount' => $unreadableCount,
            'comparisonCoveragePercent' => self::percent($comparedCount, $expectedGoldenPackageCount),
            'stableMatchPercent' => self::percent($matchedCount, $expectedGoldenPackageCount),
            'allStableSemanticsMatch' => $allMatch,
            'reason' => $allMatch ? self::GENERATED_COMPARISON_MATCH_REASON : self::GENERATED_COMPARISON_MISMATCH_REASON,
            'comparisonRows' => $comparisonRows,
            'comparisonSamples' => array_slice($comparisonRows, 0, $this->sampleLimit),
            'generatedPackageSamples' => array_slice($generatedRows, 0, $this->sampleLimit),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function stableComparisonContract(): array
    {
        return [
            'compares' => [
                'DOCX package file names against upstream test/docx/golden/*.docx names',
                'non-directory OPC package part-name set',
                'OPC [Content_Types].xml Default and Override records sorted by semantic key',
                'all OPC .rels relationship records sorted by source part, relationship id, type, target mode, and resolved target',
                'XML document-part semantics using namespace/local-name element and attribute records with formatting-only whitespace ignored',
                'binary part uncompressed byte size and SHA-256 payload digests',
            ],
            'ignores' => [
                'raw ZIP package byte equality',
                'ZIP central-directory order, local-entry order, compression method, comments, and timestamps',
                'XML attribute order, namespace prefix spelling, indentation, and formatting-only whitespace',
                'content-types child order and relationship child order',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $goldenRows
     * @param list<array<string, mixed>> $generatedRows
     * @return list<array<string, mixed>>
     */
    private function comparisonRows(array $goldenRows, array $generatedRows): array
    {
        $generatedByName = [];
        foreach ($generatedRows as $row) {
            $generatedByName[(string) ($row['fileName'] ?? '')] = $row;
        }

        $goldenNames = [];
        $rows = [];
        foreach ($goldenRows as $goldenRow) {
            $fileName = (string) ($goldenRow['fileName'] ?? '');
            $goldenNames[$fileName] = true;
            $generatedRow = $generatedByName[$fileName] ?? null;
            if (!is_array($generatedRow)) {
                $rows[] = [
                    'fileName' => $fileName,
                    'status' => 'missing-generated',
                    'compared' => false,
                    'goldenPackageSha256' => $goldenRow['packageSha256'] ?? null,
                    'generatedPackageSha256' => null,
                    'goldenStablePackageSha256' => $goldenRow['stableSemantics']['packageStableSemanticsSha256'] ?? null,
                    'generatedStablePackageSha256' => null,
                    'mismatchKinds' => ['missing-generated-package'],
                ];
                continue;
            }

            if (($goldenRow['status'] ?? null) !== 'readable' || ($generatedRow['status'] ?? null) !== 'readable') {
                $rows[] = [
                    'fileName' => $fileName,
                    'status' => 'unreadable-package',
                    'compared' => false,
                    'goldenStatus' => (string) ($goldenRow['status'] ?? 'unknown'),
                    'generatedStatus' => (string) ($generatedRow['status'] ?? 'unknown'),
                    'goldenMessage' => $goldenRow['message'] ?? null,
                    'generatedMessage' => $generatedRow['message'] ?? null,
                    'mismatchKinds' => ['unreadable-package'],
                ];
                continue;
            }

            $goldenSemantics = is_array($goldenRow['stableSemantics'] ?? null) ? $goldenRow['stableSemantics'] : [];
            $generatedSemantics = is_array($generatedRow['stableSemantics'] ?? null) ? $generatedRow['stableSemantics'] : [];
            $mismatchKinds = self::stableSemanticMismatchKinds($goldenSemantics, $generatedSemantics);
            $rows[] = [
                'fileName' => $fileName,
                'status' => $mismatchKinds === [] ? 'stable-match' : 'stable-mismatch',
                'compared' => true,
                'goldenPackageBytes' => $goldenRow['packageBytes'] ?? null,
                'generatedPackageBytes' => $generatedRow['packageBytes'] ?? null,
                'goldenPackageSha256' => $goldenRow['packageSha256'] ?? null,
                'generatedPackageSha256' => $generatedRow['packageSha256'] ?? null,
                'goldenStablePackageSha256' => $goldenSemantics['packageStableSemanticsSha256'] ?? null,
                'generatedStablePackageSha256' => $generatedSemantics['packageStableSemanticsSha256'] ?? null,
                'mismatchKinds' => $mismatchKinds,
            ];
        }

        foreach ($generatedRows as $generatedRow) {
            $fileName = (string) ($generatedRow['fileName'] ?? '');
            if (isset($goldenNames[$fileName])) {
                continue;
            }

            $rows[] = [
                'fileName' => $fileName,
                'status' => 'unexpected-generated',
                'compared' => false,
                'goldenPackageSha256' => null,
                'generatedPackageSha256' => $generatedRow['packageSha256'] ?? null,
                'goldenStablePackageSha256' => null,
                'generatedStablePackageSha256' => $generatedRow['stableSemantics']['packageStableSemanticsSha256'] ?? null,
                'mismatchKinds' => ['unexpected-generated-package'],
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $golden
     * @param array<string, mixed> $generated
     * @return list<string>
     */
    private static function stableSemanticMismatchKinds(array $golden, array $generated): array
    {
        $checks = [
            'partNameSetSha256' => 'part-name-set',
            'contentTypesSha256' => 'content-types',
            'relationshipsSha256' => 'relationships',
            'xmlPartSemanticsSha256' => 'xml-part-semantics',
            'binaryPartPayloadSha256' => 'binary-part-payloads',
            'packageStableSemanticsSha256' => 'package-stable-semantics',
        ];

        $mismatches = [];
        foreach ($checks as $key => $kind) {
            if (($golden[$key] ?? null) !== ($generated[$key] ?? null)) {
                $mismatches[] = $kind;
            }
        }

        if (count($mismatches) > 1 && in_array('package-stable-semantics', $mismatches, true)) {
            $mismatches = array_values(array_filter(
                $mismatches,
                static fn (string $kind): bool => $kind !== 'package-stable-semantics'
            ));
        }

        return $mismatches;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packageRows(string $goldenDir): array
    {
        $paths = [];
        foreach (new \DirectoryIterator($goldenDir) as $entry) {
            if (!$entry->isDot() && $entry->isFile() && strtolower($entry->getExtension()) === 'docx') {
                $paths[] = $entry->getPathname();
            }
        }
        sort($paths, SORT_STRING);

        $rows = [];
        foreach ($paths as $path) {
            $rows[] = $this->packageRow($path);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function packageRow(string $path): array
    {
        $fileName = basename($path);
        $row = [
            'fileName' => $fileName,
            'file' => $this->displayPath($path),
            'expectedUpstreamGoldenReference' => 'test/docx/golden/' . $fileName,
            'status' => 'unreadable',
            'packageBytes' => 0,
            'packageSha256' => null,
            'partCount' => 0,
            'readablePartCount' => 0,
            'partNames' => [],
            'partRows' => [],
        ];

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new \RuntimeException("Unable to read writer golden DOCX package: {$path}");
            }

            $package = ZipPackage::fromString($bytes);
            $partRows = $this->partRows($package);
            $partNames = array_map(
                static fn (array $partRow): string => (string) $partRow['name'],
                $partRows
            );

            return array_replace($row, [
                'status' => 'readable',
                'packageBytes' => strlen($bytes),
                'packageSha256' => hash('sha256', $bytes),
                'stableSemantics' => $this->stablePackageSemantics($package),
                'partCount' => count($partRows),
                'readablePartCount' => count(array_filter(
                    $partRows,
                    static fn (array $partRow): bool => ($partRow['status'] ?? null) === 'readable'
                )),
                'partNames' => $partNames,
                'partRows' => $partRows,
            ]);
        } catch (\Throwable $throwable) {
            return array_replace($row, [
                'status' => 'unreadable',
                'errorClass' => $throwable::class,
                'message' => self::oneLine($throwable->getMessage()),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function partRows(ZipPackage $package): array
    {
        $entries = $package->entries();
        usort(
            $entries,
            static fn (ZipPackageEntry $left, ZipPackageEntry $right): int => strcmp($left->name, $right->name)
        );

        $rows = [];
        foreach ($entries as $entry) {
            $row = [
                'name' => $entry->name,
                'status' => 'unreadable',
                'isDirectory' => $entry->isDirectory(),
                'compressionMethod' => $entry->compressionMethod,
                'compressedBytes' => $entry->compressedSize,
                'declaredUncompressedBytes' => $entry->uncompressedSize,
                'crc32' => $entry->crc32Hex(),
                'sha256' => null,
                'uncompressedBytes' => 0,
            ];

            try {
                $data = $entry->isDirectory() ? '' : $package->read($entry->name);
                $rows[] = array_replace($row, [
                    'status' => 'readable',
                    'sha256' => hash('sha256', $data),
                    'uncompressedBytes' => strlen($data),
                ]);
            } catch (\Throwable $throwable) {
                $rows[] = array_replace($row, [
                    'status' => 'unreadable',
                    'errorClass' => $throwable::class,
                    'message' => self::oneLine($throwable->getMessage()),
                ]);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function stablePackageSemantics(ZipPackage $package): array
    {
        $entries = $package->entries();
        usort(
            $entries,
            static fn (ZipPackageEntry $left, ZipPackageEntry $right): int => strcmp($left->name, $right->name)
        );

        $partNames = [];
        $xmlPartRows = [];
        $binaryPartRows = [];
        foreach ($entries as $entry) {
            if ($entry->isDirectory()) {
                continue;
            }

            $partNames[] = $entry->name;
            $data = $package->read($entry->name);
            $partRow = self::stablePartSemanticRow($entry->name, $data);
            if (($partRow['semanticKind'] ?? null) === 'xml') {
                $xmlPartRows[] = self::xmlPartComparisonRow($partRow);
            } elseif (($partRow['semanticKind'] ?? null) === 'binary') {
                $binaryPartRows[] = $partRow;
            }
        }

        $contentTypes = self::contentTypesSemantics($package);
        $relationships = self::relationshipsSemantics($package);
        $signature = [
            'partNames' => $partNames,
            'contentTypes' => $contentTypes,
            'relationships' => $relationships,
            'xmlParts' => $xmlPartRows,
            'binaryParts' => $binaryPartRows,
        ];

        return [
            'schemaVersion' => 1,
            'partCount' => count($partNames),
            'partNames' => $partNames,
            'partNameSetSha256' => self::stableHash($partNames),
            'contentTypeStatus' => $contentTypes['status'],
            'contentTypeRecordCount' => $contentTypes['recordCount'],
            'contentTypesSha256' => self::stableHash($contentTypes),
            'relationshipPartCount' => $relationships['relationshipPartCount'],
            'relationshipRecordCount' => $relationships['relationshipRecordCount'],
            'relationshipsSha256' => self::stableHash($relationships),
            'xmlPartCount' => count($xmlPartRows),
            'xmlPartSemanticsSha256' => self::stableHash($xmlPartRows),
            'binaryPartCount' => count($binaryPartRows),
            'binaryPartPayloadSha256' => self::stableHash($binaryPartRows),
            'packageStableSemanticsSha256' => self::stableHash($signature),
            'contentTypes' => $contentTypes,
            'relationships' => $relationships,
            'xmlPartRows' => $xmlPartRows,
            'binaryPartRows' => $binaryPartRows,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function xmlPartComparisonRow(array $row): array
    {
        $comparison = [
            'name' => (string) ($row['name'] ?? ''),
            'semanticKind' => 'xml',
            'semanticStatus' => (string) ($row['semanticStatus'] ?? 'unknown'),
            'semanticSha256' => (string) ($row['semanticSha256'] ?? ''),
        ];

        if (isset($row['message'])) {
            $comparison['message'] = (string) $row['message'];
        }

        return $comparison;
    }

    /**
     * @return array<string, mixed>
     */
    private static function stablePartSemanticRow(string $name, string $data): array
    {
        $kind = self::stablePartKind($name);
        $row = [
            'name' => $name,
            'semanticKind' => $kind,
            'uncompressedBytes' => strlen($data),
            'rawSha256' => hash('sha256', $data),
        ];

        if ($kind !== 'xml') {
            return $row;
        }

        try {
            $xmlSemantics = self::xmlSemantics($data, $name);

            return array_replace($row, [
                'semanticStatus' => 'parsed-xml',
                'semanticSha256' => self::stableHash($xmlSemantics),
            ]);
        } catch (\Throwable $throwable) {
            return array_replace($row, [
                'semanticStatus' => 'raw-xml-parse-failed',
                'semanticSha256' => hash('sha256', $data),
                'message' => self::oneLine($throwable->getMessage()),
            ]);
        }
    }

    private static function stablePartKind(string $name): string
    {
        if ($name === '[Content_Types].xml') {
            return 'content-types';
        }
        if (OpcRelationships::isRelationshipPartName($name)) {
            return 'relationships';
        }

        $lower = strtolower($name);
        if (str_ends_with($lower, '.xml') || str_ends_with($lower, '.vml')) {
            return 'xml';
        }

        return 'binary';
    }

    /**
     * @return array<string, mixed>
     */
    private static function contentTypesSemantics(ZipPackage $package): array
    {
        if (!in_array('[Content_Types].xml', $package->names(), true)) {
            return [
                'status' => 'missing',
                'recordCount' => 0,
                'defaultCount' => 0,
                'overrideCount' => 0,
                'records' => [],
            ];
        }

        $xml = $package->read('[Content_Types].xml');
        try {
            $contentTypes = OpcContentTypes::fromXml($xml);
            $records = [];
            $defaults = $contentTypes->defaults();
            ksort($defaults, SORT_STRING);
            foreach ($defaults as $extension => $contentType) {
                $records[] = [
                    'kind' => 'default',
                    'extension' => strtolower((string) $extension),
                    'contentType' => (string) $contentType,
                ];
            }

            $overrides = $contentTypes->overrides();
            ksort($overrides, SORT_STRING);
            foreach ($overrides as $partName => $contentType) {
                $records[] = [
                    'kind' => 'override',
                    'partName' => OpcPackagePath::canonicalPartName($partName),
                    'contentType' => (string) $contentType,
                ];
            }

            return [
                'status' => 'readable',
                'recordCount' => count($records),
                'defaultCount' => count($defaults),
                'overrideCount' => count($overrides),
                'records' => $records,
            ];
        } catch (\Throwable $throwable) {
            return [
                'status' => 'unreadable',
                'recordCount' => 0,
                'defaultCount' => 0,
                'overrideCount' => 0,
                'rawSha256' => hash('sha256', $xml),
                'errorClass' => $throwable::class,
                'message' => self::oneLine($throwable->getMessage()),
                'records' => [],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function relationshipsSemantics(ZipPackage $package): array
    {
        $entries = $package->entries();
        usort(
            $entries,
            static fn (ZipPackageEntry $left, ZipPackageEntry $right): int => strcmp($left->name, $right->name)
        );

        $partRows = [];
        $records = [];
        foreach ($entries as $entry) {
            if ($entry->isDirectory() || !OpcRelationships::isRelationshipPartName($entry->name)) {
                continue;
            }

            $xml = $package->read($entry->name);
            try {
                $sourcePartName = OpcRelationships::sourcePartNameForRelationshipPart($entry->name);
                $relationships = OpcRelationships::fromXml($xml, $sourcePartName);
                $partRows[] = [
                    'relationshipPartName' => OpcPackagePath::canonicalPartName($entry->name),
                    'sourcePartName' => $sourcePartName,
                    'status' => 'readable',
                    'relationshipCount' => count($relationships->all()),
                ];

                foreach ($relationships->all() as $relationship) {
                    try {
                        $resolvedTarget = $relationships->resolveTarget($relationship);
                        $targetStatus = 'resolved';
                    } catch (\Throwable $throwable) {
                        $resolvedTarget = $relationship->target;
                        $targetStatus = 'unresolved:' . self::oneLine($throwable->getMessage());
                    }

                    $records[] = [
                        'relationshipPartName' => OpcPackagePath::canonicalPartName($entry->name),
                        'sourcePartName' => $sourcePartName,
                        'relationshipId' => $relationship->id,
                        'relationshipType' => $relationship->type,
                        'targetMode' => $relationship->targetMode,
                        'resolvedTarget' => $resolvedTarget,
                        'targetStatus' => $targetStatus,
                    ];
                }
            } catch (\Throwable $throwable) {
                $partRows[] = [
                    'relationshipPartName' => $entry->name,
                    'sourcePartName' => null,
                    'status' => 'unreadable',
                    'rawSha256' => hash('sha256', $xml),
                    'errorClass' => $throwable::class,
                    'message' => self::oneLine($throwable->getMessage()),
                    'relationshipCount' => 0,
                ];
            }
        }

        usort(
            $records,
            static fn (array $left, array $right): int => strcmp(
                implode("\0", [
                    (string) ($left['relationshipPartName'] ?? ''),
                    (string) ($left['sourcePartName'] ?? ''),
                    (string) ($left['relationshipId'] ?? ''),
                    (string) ($left['relationshipType'] ?? ''),
                    (string) ($left['targetMode'] ?? ''),
                    (string) ($left['resolvedTarget'] ?? ''),
                ]),
                implode("\0", [
                    (string) ($right['relationshipPartName'] ?? ''),
                    (string) ($right['sourcePartName'] ?? ''),
                    (string) ($right['relationshipId'] ?? ''),
                    (string) ($right['relationshipType'] ?? ''),
                    (string) ($right['targetMode'] ?? ''),
                    (string) ($right['resolvedTarget'] ?? ''),
                ])
            )
        );

        return [
            'relationshipPartCount' => count($partRows),
            'relationshipRecordCount' => count($records),
            'relationshipParts' => $partRows,
            'records' => $records,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function xmlSemantics(string $xml, string $label): array
    {
        $dom = self::loadXmlDocument($xml, $label);
        if (!$dom->documentElement instanceof \DOMElement) {
            throw new \RuntimeException("XML part has no document element: {$label}");
        }

        return self::xmlNodeSemantics($dom->documentElement);
    }

    private static function loadXmlDocument(string $xml, string $label): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = true;
            $dom->resolveExternals = false;
            $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
            if ($loaded !== true) {
                throw new \RuntimeException("Unable to parse XML part {$label}: " . self::libxmlErrorSummary());
            }

            return $dom;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function xmlNodeSemantics(\DOMNode $node): array
    {
        if ($node instanceof \DOMElement) {
            $attributes = [];
            foreach ($node->attributes as $attribute) {
                if (!$attribute instanceof \DOMAttr || $attribute->namespaceURI === 'http://www.w3.org/2000/xmlns/') {
                    continue;
                }

                $attributes[] = [
                    'namespace' => (string) ($attribute->namespaceURI ?? ''),
                    'name' => (string) ($attribute->localName ?: $attribute->name),
                    'value' => $attribute->value,
                ];
            }
            usort(
                $attributes,
                static fn (array $left, array $right): int => strcmp(
                    ($left['namespace'] ?? '') . "\0" . ($left['name'] ?? '') . "\0" . ($left['value'] ?? ''),
                    ($right['namespace'] ?? '') . "\0" . ($right['name'] ?? '') . "\0" . ($right['value'] ?? '')
                )
            );

            $children = [];
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMText && self::isFormattingWhitespace($child)) {
                    continue;
                }
                if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                    continue;
                }

                $children[] = self::xmlNodeSemantics($child);
            }

            return [
                'kind' => 'element',
                'namespace' => (string) ($node->namespaceURI ?? ''),
                'name' => (string) ($node->localName ?: $node->nodeName),
                'attributes' => $attributes,
                'children' => $children,
            ];
        }

        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            return [
                'kind' => 'text',
                'value' => $node->nodeValue ?? '',
            ];
        }

        return [
            'kind' => $node->nodeType,
            'name' => $node->nodeName,
            'value' => $node->nodeValue,
        ];
    }

    private static function isFormattingWhitespace(\DOMText $text): bool
    {
        if (trim($text->data) !== '') {
            return false;
        }

        $parent = $text->parentNode;
        if (!$parent instanceof \DOMElement) {
            return false;
        }

        foreach ($parent->childNodes as $sibling) {
            if ($sibling instanceof \DOMElement) {
                return true;
            }
        }

        return false;
    }

    private static function libxmlErrorSummary(): string
    {
        $errors = libxml_get_errors();
        if ($errors === []) {
            return 'unknown XML parse error';
        }

        $error = $errors[0];

        return self::oneLine(trim($error->message) . ' at line ' . $error->line . ', column ' . $error->column);
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', self::stableJson($value));
    }

    private static function stableJson(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
    }

    private static function percent(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private function absoluteDocxDirectory(): string
    {
        if (str_starts_with($this->docxDirectory, DIRECTORY_SEPARATOR)) {
            return rtrim($this->docxDirectory, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->docxDirectory);
    }

    private function absoluteGoldenDirectory(): string
    {
        return $this->absoluteDocxDirectory() . DIRECTORY_SEPARATOR . 'golden';
    }

    private function absoluteGeneratedDirectory(): ?string
    {
        if ($this->generatedDocxDirectory === null) {
            return null;
        }

        if (str_starts_with($this->generatedDocxDirectory, DIRECTORY_SEPARATOR)) {
            return rtrim($this->generatedDocxDirectory, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->generatedDocxDirectory);
    }

    private function displayPath(string $path): string
    {
        $root = $this->repoRoot . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $root)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
        }

        return $path;
    }

    private static function boolText(mixed $value): string
    {
        return $value === true ? 'yes' : 'no';
    }

    private static function oneLine(string $message): string
    {
        $message = preg_replace('/\s+/', ' ', trim($message));
        if (!is_string($message)) {
            return '';
        }

        return strlen($message) > 240 ? substr($message, 0, 237) . '...' : $message;
    }
}
