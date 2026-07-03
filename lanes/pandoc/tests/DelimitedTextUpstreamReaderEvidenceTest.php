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
        $t->same('valid-checked-in-current-delimited-text-reader-evidence', $evidence['validation']['status']);
        $t->same([], $evidence['validation']['issues']);
        $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $evidence['claimBoundaries']['doesNotAssert'], true));
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
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        } finally {
            $removeTree($root);
        }
    },
];
