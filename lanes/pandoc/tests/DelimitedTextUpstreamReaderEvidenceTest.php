<?php

declare(strict_types=1);

use PortLibs\Pandoc\DelimitedTextUpstreamReaderEvidence;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-delimited-text-evidence-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary delimited text evidence directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary delimited text evidence directory {$base}");
    }

    return $base;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
};

$writeFile = static function (string $root, string $relativePath, string $contents): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create fixture directory {$directory}");
    }
    file_put_contents($path, $contents);
};

$writeDelimitedTextEvidenceTree = static function (string $upstreamRoot, string $repoRoot) use ($writeFile): void {
    foreach ([
        'csv.md',
        '01.csv',
    ] as $name) {
        $writeFile(
            $upstreamRoot,
            'test/command/' . $name,
            (string) file_get_contents($repoRoot . '/lanes/pandoc/fixtures/upstream-current-csv-reader/' . $name)
        );
    }
    $writeFile($upstreamRoot, 'src/Text/Pandoc/CSV.hs', "module Text.Pandoc.CSV where\n");
    $writeFile($upstreamRoot, 'src/Text/Pandoc/Readers/CSV.hs', "module Text.Pandoc.Readers.CSV where\n");
};

return [
    'reports skipped delimited text reader evidence when upstream root is absent' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $report = (new DelimitedTextUpstreamReaderEvidence($repoRoot, 'missing-upstream-root-for-static-gate'))->report();
        $text = DelimitedTextUpstreamReaderEvidence::formatTextReport($report);

        $t->same(1, $report['schemaVersion']);
        $t->same(DelimitedTextUpstreamReaderEvidence::TOOL_NAME, $report['tool']);
        $t->same(DelimitedTextUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
        $t->same('not-evaluated-missing-upstream-root', $report['validation']['status']);
        $t->same(['missing-upstream-root'], $report['validation']['issues']);
        $t->same('valid-checked-in-current-delimited-text-reader-evidence', $report['staticCurrentEvidence']['validation']['status']);
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        $t->same(false, DelimitedTextUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->contains('Pandoc delimited text reader evidence', $text);
        $t->contains('Static current evidence: valid-checked-in-current-delimited-text-reader-evidence checkedInFixtures=2', $text);
        $t->contains('Generated TSV native parity: 1/1 status=generated-tsv-native-parity-observed-not-upstream-fixture', $text);
    },
    'reports checked-in current csv command fixture static evidence' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $evidence = DelimitedTextUpstreamReaderEvidence::checkedInCurrentEvidence($repoRoot);

        $t->same('static-checked-in-current-upstream-delimited-text-reader-fixture-evidence', $evidence['kind']);
        $t->same('4f5226df4faa0d66dd2c089465b13886360ab3c2', $evidence['upstream']['commit']);
        $t->same(2, $evidence['readerDenominator']['csvDirectFixtureCount']);
        $t->same(0, $evidence['readerDenominator']['tsvDirectFixtureCount']);
        $t->same([
            'test/command/csv.md',
            'test/command/01.csv',
        ], $evidence['readerDenominator']['csvDirectFixtures']);
        $t->same(2, $evidence['checkedInFixtureCount']);
        $t->same('csv.md', $evidence['checkedInFixtures'][0]['name']);
        $t->same('42a8bc56612d061388889a10d73b1d34fb870595785ee550ef43c6a065a77ad6', $evidence['checkedInFixtures'][0]['checkedInFile']['sha256']);
        $t->same(2719, $evidence['checkedInFixtures'][0]['checkedInFile']['bytes']);
        $t->same('01.csv', $evidence['checkedInFixtures'][1]['name']);
        $t->same('257c619e19786fddf7685a31a45f6495446a5213083540d09ecba6ce7f1e62cd', $evidence['checkedInFixtures'][1]['checkedInFile']['sha256']);
        $t->same(47, $evidence['checkedInFixtures'][1]['checkedInFile']['bytes']);
        $t->same('static-checked-in-generated-tsv-native-parity-fixture-evidence', $evidence['generatedTsvNativeStaticEvidence']['kind']);
        $t->same(1, $evidence['generatedTsvNativeStaticEvidence']['sampleCount']);
        $t->same(2, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtureCount']);
        $t->same(0, $evidence['generatedTsvNativeStaticEvidence']['tsvDirectFixtureDenominator']);
        $t->same('simple.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][0]['name']);
        $t->same('fcee0aed5a2fde11bbd19f2fc4445357a0d7bbd9c9962df6630fed4b6178ff8e', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][0]['checkedInFile']['sha256']);
        $t->same(71, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][0]['checkedInFile']['bytes']);
        $t->same('simple.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][1]['name']);
        $t->same('f4c930c9d309c4dd6ec1c50eda9e45ff3614566e6c26e4b5254ce3e9c62abb2a', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][1]['checkedInFile']['sha256']);
        $t->same(1540, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][1]['checkedInFile']['bytes']);
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedTsvNativeStaticEvidence($evidence['generatedTsvNativeStaticEvidence']));
        $t->same('valid-checked-in-current-delimited-text-reader-evidence', $evidence['validation']['status']);
        $t->same([], $evidence['validation']['issues']);
        $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $evidence['claimBoundaries']['doesNotAssert'], true));
    },
    'executes generated tsv native parity evidence' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $evidence = DelimitedTextUpstreamReaderEvidence::generatedTsvNativeParityEvidence($repoRoot);

        $t->same('executable-generated-tsv-native-parity-evidence', $evidence['kind']);
        $t->same('generated-tsv-native-parity', $evidence['evidenceKind']);
        $t->same('tsv', $evidence['reader']);
        $t->same(0, $evidence['tsvDirectFixtureDenominator']);
        $t->same(1, $evidence['sampleCount']);
        $t->same(1, $evidence['comparedSampleCount']);
        $t->same(0, $evidence['parseFailureCount']);
        $t->same(1, $evidence['generatedNativeMatchCount']);
        $t->same(0, $evidence['generatedNativeMismatchCount']);
        $t->same(100.0, $evidence['generatedNativeMatchPercent']);
        $t->same('generated-tsv-native-parity-observed-not-upstream-fixture', $evidence['parityStatus']);
        $t->same('matched', $evidence['samples'][0]['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/simple.tsv', $evidence['samples'][0]['inputPath']);
        $t->same(3, $evidence['samples'][0]['columnCount']);
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedTsvNativeParity($evidence));
        $t->true(in_array('that the generated TSV sample is an upstream command fixture', $evidence['claimBoundaries']['doesNotAssert'], true));
    },
    'validates hydrated upstream delimited text reader fixture evidence' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeDelimitedTextEvidenceTree): void {
        $repoRoot = dirname(__DIR__, 3);
        $root = $makeTempDir();
        try {
            $writeDelimitedTextEvidenceTree($root, $repoRoot);
            $report = (new DelimitedTextUpstreamReaderEvidence($repoRoot, $root))->report();

            $t->same(DelimitedTextUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
            $t->same('valid-upstream-delimited-text-reader-evidence', $report['validation']['status']);
            $t->same([], $report['validation']['issues']);
            $t->same(2, $report['denominator']['csvDirectFixtureCount']);
            $t->same(0, $report['denominator']['tsvDirectFixtureCount']);
            $t->same('test/command/csv.md', $report['denominator']['upstreamFixtures'][0]['path']);
            $t->same('42a8bc56612d061388889a10d73b1d34fb870595785ee550ef43c6a065a77ad6', $report['denominator']['upstreamFixtures'][0]['sha256']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(1, $report['generatedTsvNativeParityEvidence']['generatedNativeMatchCount']);
            $t->same('generated-tsv-native-parity-observed-not-upstream-fixture', $report['generatedTsvNativeParityEvidence']['parityStatus']);
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        } finally {
            $removeTree($root);
        }
    },
    'cli reports generated tsv native parity without changing tsv denominator' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($repoRoot . '/tools/pandoc-delimited-text-reader-evidence.php')
            . ' --json'
            . ' --require-honest-denominators'
            . ' --require-generated-tsv-native-parity'
            . ' --require-runner-not-run'
            . ' --require-no-validation-issues';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(0, $decoded['tsv']['denominator']);
        $t->same(0, $decoded['tsv']['tsvDirectFixtureDenominator']);
        $t->same(1, $decoded['tsv']['generatedNativeParitySampleCount']);
        $t->same(1, $decoded['generatedTsvNativeParity']['generatedNativeMatchCount']);
        $t->same('generated-tsv-native-parity-observed-not-upstream-fixture', $decoded['generatedTsvNativeParity']['parityStatus']);
        $t->same([], $decoded['validationIssues']);
    },
];
