<?php

declare(strict_types=1);

use PortLibs\Pandoc\XlsxUpstreamReaderEvidence;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-xlsx-reader-evidence-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary XLSX evidence directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary XLSX evidence directory {$base}");
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

$writeXlsxEvidenceTree = static function (string $root) use ($writeFile): void {
    $writeFile($root, 'test/Tests/Readers/Xlsx.hs', <<<'HS'
module Tests.Readers.Xlsx (tests) where

tests = [ testGroup "basic"
          [ testCompare
            "sheet extraction"
            "xlsx-reader/basic.xlsx"
            "xlsx-reader/basic.native"
          ]
        ]
HS);
    $writeFile($root, 'test/xlsx-reader/basic.xlsx', 'xlsx bytes');
    $writeFile($root, 'test/xlsx-reader/basic.native', '[Para [Str "xlsx"]]');
    foreach ([
        'src/Text/Pandoc/Readers/Xlsx.hs',
        'src/Text/Pandoc/Readers/Xlsx/Parse.hs',
        'src/Text/Pandoc/Readers/Xlsx/Sheets.hs',
        'src/Text/Pandoc/Readers/Xlsx/Cells.hs',
    ] as $path) {
        $writeFile($root, $path, "module Stub where\n");
    }
};

return [
    'reports skipped xlsx reader evidence when upstream root is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $report = (new XlsxUpstreamReaderEvidence($root, 'missing'))->report();
            $text = XlsxUpstreamReaderEvidence::formatTextReport($report);

            $t->same(1, $report['schemaVersion']);
            $t->same(XlsxUpstreamReaderEvidence::TOOL_NAME, $report['tool']);
            $t->same(XlsxUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
            $t->same(0, $report['denominator']['readerTestCompareCount']);
            $t->same(0, $report['denominator']['fixturePairCount']);
            $t->same('not-evaluated-missing-upstream-root', $report['validation']['status']);
            $t->same(false, XlsxUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->contains('Pandoc XLSX reader evidence', $text);
        } finally {
            $removeTree($root);
        }
    },

    'parses upstream xlsx reader test denominator and fixture pairs' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeXlsxEvidenceTree): void {
        $root = $makeTempDir();
        try {
            $writeXlsxEvidenceTree($root);
            $report = (new XlsxUpstreamReaderEvidence($root, '.'))->report();

            $t->same(XlsxUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
            $t->same('valid-upstream-xlsx-reader-denominator', $report['validation']['status']);
            $t->same([], $report['validation']['issues']);
            $t->same(1, $report['denominator']['readerTestCompareCount']);
            $t->same(1, $report['denominator']['fixturePairCount']);
            $t->same('sheet extraction', $report['denominator']['readerCases'][0]['name']);
            $t->same('xlsx-reader/basic.xlsx', $report['denominator']['readerCases'][0]['xlsx']);
            $t->same('xlsx-reader/basic.native', $report['denominator']['readerCases'][0]['native']);
            $t->same([], $report['denominator']['missingReferencedFiles']);
            $t->same([], $report['denominator']['unreferencedFixturePairs']);
            $t->same(5, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, XlsxUpstreamReaderEvidence::hasRequiredReaderTestCount($report, 1));
            $t->same(true, XlsxUpstreamReaderEvidence::hasRequiredFixturePairCount($report, 1));
            $t->same(true, XlsxUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $report['claimBoundaries']['doesNotAssert'], true));
        } finally {
            $removeTree($root);
        }
    },

    'reports invalid xlsx reader evidence for missing and unreferenced fixtures' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile): void {
        $root = $makeTempDir();
        try {
            $writeFile($root, 'test/Tests/Readers/Xlsx.hs', <<<'HS'
tests = [ testCompare "missing native" "xlsx-reader/basic.xlsx" "xlsx-reader/basic.native" ]
HS);
            $writeFile($root, 'test/xlsx-reader/basic.xlsx', 'xlsx bytes');
            $writeFile($root, 'test/xlsx-reader/extra.xlsx', 'extra xlsx bytes');
            $writeFile($root, 'test/xlsx-reader/extra.native', '[Para [Str "extra"]]');
            $writeFile($root, 'test/xlsx-reader/extra2.xlsx', 'extra2 xlsx bytes');
            $writeFile($root, 'test/xlsx-reader/extra2.native', '[Para [Str "extra2"]]');

            $report = (new XlsxUpstreamReaderEvidence($root, '.'))->report();

            $t->same('invalid-upstream-xlsx-reader-denominator', $report['validation']['status']);
            $t->true(in_array('missing-referenced-fixture-files', $report['validation']['issues'], true));
            $t->true(in_array('unreferenced-fixture-pairs', $report['validation']['issues'], true));
            $t->true(in_array('reader-test-count-does-not-match-fixture-pair-count', $report['validation']['issues'], true));
            $t->same(1, count($report['denominator']['missingReferencedFiles']));
            $t->same('test/xlsx-reader/basic.native', $report['denominator']['missingReferencedFiles'][0]['path']);
            $t->same(2, count($report['denominator']['unreferencedFixturePairs']));
            $t->same(false, XlsxUpstreamReaderEvidence::hasNoValidationIssues($report));
        } finally {
            $removeTree($root);
        }
    },

    'cli gates xlsx reader evidence counts and validation issues' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeXlsxEvidenceTree): void {
        $root = $makeTempDir();
        try {
            $writeXlsxEvidenceTree($root);
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-xlsx-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg(dirname($root))
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --json'
                . ' --require-test-count=1'
                . ' --require-fixture-pair-count=1'
                . ' --require-no-validation-issues';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(1, $decoded['denominator']['readerTestCompareCount']);
            $t->same(1, $decoded['denominator']['fixturePairCount']);
            $t->same('valid-upstream-xlsx-reader-denominator', $decoded['validation']['status']);

            $failingCommand = str_replace('--require-test-count=1', '--require-test-count=2', $command) . ' 2>/dev/null';
            $failingOutput = [];
            $failingExitCode = 0;
            exec($failingCommand, $failingOutput, $failingExitCode);

            $t->same(1, $failingExitCode);
        } finally {
            $removeTree($root);
        }
    },
];
