<?php

declare(strict_types=1);

use PortLibs\Pandoc\ManUpstreamReaderEvidence;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-man-reader-evidence-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary man evidence directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary man evidence directory {$base}");
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

$writeManEvidenceTree = static function (string $root) use ($writeFile): void {
    $writeFile($root, 'test/Tests/Readers/Man.hs', <<<'HS'
tests :: [TestTree]
tests = [
  testGroup "Macros" [
      "Bold" =:
      ".B foo"
      =?> para (strong "foo")
    , "comment  with \\\"" =:
      "Foo \\\" bar\n" =?> para (text "Foo")
    ]
  ]
HS);
    $writeFile($root, 'src/Text/Pandoc/Readers/Man.hs', "module Text.Pandoc.Readers.Man where\n");
};

return [
    'reports skipped man reader evidence when upstream root is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $report = (new ManUpstreamReaderEvidence($root, 'missing'))->report();
            $text = ManUpstreamReaderEvidence::formatTextReport($report);

            $t->same(1, $report['schemaVersion']);
            $t->same(ManUpstreamReaderEvidence::TOOL_NAME, $report['tool']);
            $t->same(ManUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
            $t->same(0, $report['denominator']['readerUnitCaseCount']);
            $t->same('not-evaluated-missing-upstream-root', $report['validation']['status']);
            $t->same(false, ManUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->contains('Pandoc man reader evidence', $text);
        } finally {
            $removeTree($root);
        }
    },

    'parses upstream man reader unit denominator' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeManEvidenceTree): void {
        $root = $makeTempDir();
        try {
            $writeManEvidenceTree($root);
            $report = (new ManUpstreamReaderEvidence($root, '.'))->report();

            $t->same(ManUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
            $t->same('valid-upstream-man-reader-denominator', $report['validation']['status']);
            $t->same([], $report['validation']['issues']);
            $t->same(2, $report['denominator']['readerUnitCaseCount']);
            $t->same('Bold', $report['denominator']['readerCases'][0]['name']);
            $t->contains('comment', $report['denominator']['readerCases'][1]['name']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, ManUpstreamReaderEvidence::hasRequiredReaderUnitCaseCount($report, 2));
            $t->same(true, ManUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $report['claimBoundaries']['doesNotAssert'], true));
        } finally {
            $removeTree($root);
        }
    },

    'reports invalid man reader evidence when source files are missing' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile): void {
        $root = $makeTempDir();
        try {
            $writeFile($root, 'test/Tests/Readers/Man.hs', 'tests = []');

            $report = (new ManUpstreamReaderEvidence($root, '.'))->report();

            $t->same('invalid-upstream-man-reader-denominator', $report['validation']['status']);
            $t->true(in_array('missing-reader-source', $report['validation']['issues'], true));
            $t->true(in_array('no-man-reader-unit-cases', $report['validation']['issues'], true));
            $t->same(false, ManUpstreamReaderEvidence::hasNoValidationIssues($report));
        } finally {
            $removeTree($root);
        }
    },

    'cli gates man reader evidence counts and validation issues' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeManEvidenceTree): void {
        $root = $makeTempDir();
        try {
            $writeManEvidenceTree($root);
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-man-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg(dirname($root))
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --json'
                . ' --require-test-count=2'
                . ' --require-no-validation-issues';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(2, $decoded['denominator']['readerUnitCaseCount']);
            $t->same('valid-upstream-man-reader-denominator', $decoded['validation']['status']);

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
