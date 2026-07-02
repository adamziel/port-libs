<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubUpstreamReaderEvidence;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-epub-reader-evidence-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary EPUB evidence directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary EPUB evidence directory {$base}");
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

$writeEpubEvidenceTree = static function (string $root) use ($writeFile): void {
    $writeFile($root, 'test/Tests/Readers/EPUB.hs', <<<'HS'
module Tests.Readers.EPUB (tests) where

featuresBag :: [(String, String, Int)]
featuresBag = [("img/check.gif","image/gif",1340)
              ,("img/check.png","image/png",2815)
              ]

emptyBag :: [(String, String, Int)]
emptyBag = []

tests :: [TestTree]
tests =
  [ testGroup "EPUB Mediabag"
    [ testCase "features bag"
      (testMediaBag "epub/img.epub" featuresBag),
      testCase "empty bag"
      (testMediaBag "epub/empty.epub" emptyBag)
    ]
  ]
HS);
    $writeFile($root, 'test/epub/img.epub', 'epub bytes');
    $writeFile($root, 'test/epub/empty.epub', 'empty epub bytes');
    $writeFile($root, 'src/Text/Pandoc/Readers/EPUB.hs', "module Text.Pandoc.Readers.EPUB where\n");
};

return [
    'reports skipped epub reader evidence when upstream root is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $report = (new EpubUpstreamReaderEvidence($root, 'missing'))->report();
            $text = EpubUpstreamReaderEvidence::formatTextReport($report);

            $t->same(1, $report['schemaVersion']);
            $t->same(EpubUpstreamReaderEvidence::TOOL_NAME, $report['tool']);
            $t->same(EpubUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
            $t->same(0, $report['denominator']['mediaBagTestCount']);
            $t->same(0, $report['denominator']['fixtureReferenceCount']);
            $t->same('not-evaluated-missing-upstream-root', $report['validation']['status']);
            $t->same(false, EpubUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->contains('Pandoc EPUB reader evidence', $text);
        } finally {
            $removeTree($root);
        }
    },

    'parses upstream epub reader mediabag denominator and expected tuples' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeEpubEvidenceTree): void {
        $root = $makeTempDir();
        try {
            $writeEpubEvidenceTree($root);
            $report = (new EpubUpstreamReaderEvidence($root, '.'))->report();

            $t->same(EpubUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
            $t->same('valid-upstream-epub-reader-mediabag-denominator', $report['validation']['status']);
            $t->same([], $report['validation']['issues']);
            $t->same(2, $report['denominator']['mediaBagTestCount']);
            $t->same(2, $report['denominator']['fixtureReferenceCount']);
            $t->same(2, $report['denominator']['expectedMediaItemCount']);
            $t->same('features bag', $report['denominator']['readerCases'][0]['name']);
            $t->same('epub/img.epub', $report['denominator']['readerCases'][0]['epub']);
            $t->same('featuresBag', $report['denominator']['readerCases'][0]['bagName']);
            $t->same('img/check.gif', $report['denominator']['readerCases'][0]['expectedBag'][0]['path']);
            $t->same('image/gif', $report['denominator']['readerCases'][0]['expectedBag'][0]['mime']);
            $t->same(1340, $report['denominator']['readerCases'][0]['expectedBag'][0]['size']);
            $t->same([], $report['denominator']['missingReferencedFiles']);
            $t->same([], $report['denominator']['unreferencedEpubFixtures']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredMediaBagTestCount($report, 2));
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredFixtureReferenceCount($report, 2));
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredExpectedMediaItemCount($report, 2));
            $t->same(true, EpubUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $report['claimBoundaries']['doesNotAssert'], true));
        } finally {
            $removeTree($root);
        }
    },

    'reports invalid epub reader evidence for missing fixture and bag definition' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile): void {
        $root = $makeTempDir();
        try {
            $writeFile($root, 'test/Tests/Readers/EPUB.hs', <<<'HS'
tests = [ testCase "missing bag" (testMediaBag "epub/missing.epub" missingBag) ]
HS);
            $writeFile($root, 'src/Text/Pandoc/Readers/EPUB.hs', "module Stub where\n");

            $report = (new EpubUpstreamReaderEvidence($root, '.'))->report();

            $t->same('invalid-upstream-epub-reader-mediabag-denominator', $report['validation']['status']);
            $t->true(in_array('missing-expected-mediabag-definition', $report['validation']['issues'], true));
            $t->true(in_array('missing-referenced-fixture-files', $report['validation']['issues'], true));
            $t->same(1, count($report['denominator']['missingReferencedFiles']));
            $t->same('test/epub/missing.epub', $report['denominator']['missingReferencedFiles'][0]['path']);
            $t->same(false, EpubUpstreamReaderEvidence::hasNoValidationIssues($report));
        } finally {
            $removeTree($root);
        }
    },

    'cli gates epub reader evidence counts and validation issues' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeEpubEvidenceTree): void {
        $root = $makeTempDir();
        try {
            $writeEpubEvidenceTree($root);
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg(dirname($root))
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --json'
                . ' --require-test-count=2'
                . ' --require-fixture-reference-count=2'
                . ' --require-expected-media-item-count=2'
                . ' --require-no-validation-issues';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(2, $decoded['denominator']['mediaBagTestCount']);
            $t->same(2, $decoded['denominator']['fixtureReferenceCount']);
            $t->same(2, $decoded['denominator']['expectedMediaItemCount']);
            $t->same('valid-upstream-epub-reader-mediabag-denominator', $decoded['validation']['status']);

            $failingCommand = str_replace('--require-test-count=2', '--require-test-count=3', $command) . ' 2>/dev/null';
            $failingOutput = [];
            $failingExitCode = 0;
            exec($failingCommand, $failingOutput, $failingExitCode);

            $t->same(1, $failingExitCode);
        } finally {
            $removeTree($root);
        }
    },
];
