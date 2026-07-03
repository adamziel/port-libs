<?php

declare(strict_types=1);

use PortLibs\Pandoc\PptxUpstreamReaderEvidence;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-pptx-reader-evidence-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary PPTX evidence directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary PPTX evidence directory {$base}");
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

$writePptxEvidenceTree = static function (string $root) use ($writeFile): void {
    $writeFile($root, 'test/Tests/Readers/Pptx.hs', <<<'HS'
module Tests.Readers.Pptx (tests) where

tests = [ testGroup "basic"
          [ testCompare
            "text extraction"
            "pptx-reader/basic.pptx"
            "pptx-reader/basic.native"
          ]
        ]
HS);
    $writeFile($root, 'test/pptx-reader/basic.pptx', 'pptx bytes');
    $writeFile($root, 'test/pptx-reader/basic.native', '[Para [Str "pptx"]]');
    foreach ([
        'src/Text/Pandoc/Readers/Pptx.hs',
        'src/Text/Pandoc/Readers/Pptx/Parse.hs',
        'src/Text/Pandoc/Readers/Pptx/Shapes.hs',
        'src/Text/Pandoc/Readers/Pptx/Slides.hs',
        'src/Text/Pandoc/Readers/Pptx/SmartArt.hs',
    ] as $path) {
        $writeFile($root, $path, "module Stub where\n");
    }
};

return [
    'reports skipped pptx reader evidence when upstream root is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $report = (new PptxUpstreamReaderEvidence($root, 'missing'))->report();
            $text = PptxUpstreamReaderEvidence::formatTextReport($report);

            $t->same(1, $report['schemaVersion']);
            $t->same(PptxUpstreamReaderEvidence::TOOL_NAME, $report['tool']);
            $t->same(PptxUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
            $t->same(0, $report['denominator']['readerTestCompareCount']);
            $t->same(0, $report['denominator']['fixturePairCount']);
            $t->same('not-evaluated-missing-upstream-root', $report['validation']['status']);
            $t->same(false, PptxUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->same(true, PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same('not-run', $report['runnerEvidence']['status']);
            $t->same(false, $report['runnerEvidence']['executed']);
            $t->same(null, $report['runnerEvidence']['command']);
            $t->same(null, $report['runnerEvidence']['resultArtifact']);
            $t->contains('Pandoc PPTX reader evidence', $text);
            $t->contains('Runner status: not-run', $text);
        } finally {
            $removeTree($root);
        }
    },
    'parses upstream pptx reader test denominator and fixture pairs' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePptxEvidenceTree): void {
        $root = $makeTempDir();
        try {
            $writePptxEvidenceTree($root);
            $report = (new PptxUpstreamReaderEvidence($root, '.'))->report();

            $t->same(PptxUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
            $t->same('valid-upstream-pptx-reader-denominator', $report['validation']['status']);
            $t->same([], $report['validation']['issues']);
            $t->same(1, $report['denominator']['readerTestCompareCount']);
            $t->same(1, $report['denominator']['fixturePairCount']);
            $t->same('text extraction', $report['denominator']['readerCases'][0]['name']);
            $t->same('pptx-reader/basic.pptx', $report['denominator']['readerCases'][0]['pptx']);
            $t->same('pptx-reader/basic.native', $report['denominator']['readerCases'][0]['native']);
            $t->same(0, $report['denominator']['unpairedPptxFixtureCount']);
            $t->same(0, $report['denominator']['unpairedNativeFixtureCount']);
            $t->same([], $report['denominator']['unpairedPptxFixtures']);
            $t->same([], $report['denominator']['unpairedNativeFixtures']);
            $t->same([], $report['denominator']['missingReferencedFiles']);
            $t->same([], $report['denominator']['unreferencedFixturePairs']);
            $t->same(6, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, PptxUpstreamReaderEvidence::hasRequiredReaderTestCount($report, 1));
            $t->same(true, PptxUpstreamReaderEvidence::hasRequiredFixturePairCount($report, 1));
            $t->same(true, PptxUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->same(true, PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same('upstream-haskell-runner', $report['runnerEvidence']['scope']);
            $t->same('$2 == "Readers" && $3 == "Pptx"', $report['runnerEvidence']['futureCommands'][1]['arguments'][8]);
            $t->true(in_array('.port-libs/pandoc-runner/logs/pptx-targeted-run.txt', $report['runnerEvidence']['requiredArtifacts'], true));
            $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $report['claimBoundaries']['doesNotAssert'], true));
            $t->true(in_array('that upstream Haskell runner evidence is explicitly not-run', $report['claimBoundaries']['doesAssert'], true));
        } finally {
            $removeTree($root);
        }
    },
    'reports checked-in current upstream pptx static evidence gate' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $report = (new PptxUpstreamReaderEvidence($repoRoot, 'missing-upstream-root-for-static-gate'))->report();
        $text = PptxUpstreamReaderEvidence::formatTextReport($report);
        $static = $report['staticCurrentEvidence'];
        $pair = $static['checkedInFixturePairs'][0];

        $t->same(PptxUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
        $t->same('valid-checked-in-current-pptx-reader-evidence', $static['validation']['status']);
        $t->same([], $static['validation']['issues']);
        $t->same(1, $static['readerDenominator']['expectedCompareCount']);
        $t->same('text extraction', $static['readerDenominator']['expectedReaderCases'][0]['name']);
        $t->same('pptx-reader/basic.pptx', $static['readerDenominator']['expectedReaderCases'][0]['pptx']);
        $t->same('pptx-reader/basic.native', $static['readerDenominator']['expectedReaderCases'][0]['native']);
        $t->same(1, $static['checkedInFixturePairCount']);
        $t->same(0, $static['checkedInUnpairedPptxFixtureCount']);
        $t->same(0, $static['checkedInUnpairedNativeFixtureCount']);
        $t->same([], $static['checkedInUnpairedPptxFixtures']);
        $t->same([], $static['checkedInUnpairedNativeFixtures']);
        $t->same('basic', $pair['stem']);
        $t->same('pptx-reader/basic.pptx|pptx-reader/basic.native', $pair['pairKey']);
        $t->same('e48fd9c2f8369d1792197e301d5fea676bf6e51097a24af7d85831a6f96dc2dc', $pair['checkedInPptx']['sha256']);
        $t->same('42804b9b1954094a4b0ff0be20084e2e6d9bc0a84272f34f7f219f82505da6b4', $pair['checkedInNative']['sha256']);
        $t->same(111674, $pair['checkedInPptx']['bytes']);
        $t->same(3966, $pair['checkedInNative']['bytes']);
        $t->same(false, PptxUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        $t->same(true, PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
        $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $static['claimBoundaries']['doesNotAssert'], true));
        $t->contains('Static current evidence: valid-checked-in-current-pptx-reader-evidence comparisons=1 checkedInPairs=1', $text);
        $t->contains('Runner status: not-run', $text);
    },
    'reports invalid pptx reader evidence for missing and unreferenced fixtures' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile): void {
        $root = $makeTempDir();
        try {
            $writeFile($root, 'test/Tests/Readers/Pptx.hs', <<<'HS'
tests = [ testCompare "missing native" "pptx-reader/basic.pptx" "pptx-reader/basic.native" ]
HS);
            $writeFile($root, 'test/pptx-reader/basic.pptx', 'pptx bytes');
            $writeFile($root, 'test/pptx-reader/extra.pptx', 'extra pptx bytes');
            $writeFile($root, 'test/pptx-reader/extra.native', '[Para [Str "extra"]]');
            $writeFile($root, 'test/pptx-reader/extra2.pptx', 'extra2 pptx bytes');
            $writeFile($root, 'test/pptx-reader/extra2.native', '[Para [Str "extra2"]]');
            $writeFile($root, 'test/pptx-reader/orphan.native', '[Para [Str "orphan"]]');

            $report = (new PptxUpstreamReaderEvidence($root, '.'))->report();

            $t->same('invalid-upstream-pptx-reader-denominator', $report['validation']['status']);
            $t->true(in_array('missing-referenced-fixture-files', $report['validation']['issues'], true));
            $t->true(in_array('unreferenced-fixture-pairs', $report['validation']['issues'], true));
            $t->true(in_array('unpaired-pptx-fixtures', $report['validation']['issues'], true));
            $t->true(in_array('unpaired-native-fixtures', $report['validation']['issues'], true));
            $t->true(in_array('reader-test-count-does-not-match-fixture-pair-count', $report['validation']['issues'], true));
            $t->same(1, $report['denominator']['unpairedPptxFixtureCount']);
            $t->same(1, $report['denominator']['unpairedNativeFixtureCount']);
            $t->same(['pptx-reader/basic.pptx'], $report['denominator']['unpairedPptxFixtures']);
            $t->same(['pptx-reader/orphan.native'], $report['denominator']['unpairedNativeFixtures']);
            $t->same(1, count($report['denominator']['missingReferencedFiles']));
            $t->same('test/pptx-reader/basic.native', $report['denominator']['missingReferencedFiles'][0]['path']);
            $t->same(2, count($report['denominator']['unreferencedFixturePairs']));
            $t->same(false, PptxUpstreamReaderEvidence::hasNoValidationIssues($report));
        } finally {
            $removeTree($root);
        }
    },
    'cli gates checked-in current pptx static evidence without upstream runner claim' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $repoRoot = dirname(__DIR__, 3);
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($repoRoot . '/tools/pandoc-pptx-reader-evidence.php')
            . ' --repo-root=' . escapeshellarg($repoRoot)
            . ' --upstream-root=' . escapeshellarg('missing-upstream-root-for-static-gate')
            . ' --json'
            . ' --require-static-current-evidence'
            . ' --require-runner-not-run';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(PptxUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $decoded['status']);
        $t->same('not-evaluated-missing-upstream-root', $decoded['validation']['status']);
        $t->same('valid-checked-in-current-pptx-reader-evidence', $decoded['staticCurrentEvidence']['validation']['status']);
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($decoded));
        $t->same(true, PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($decoded));
        $t->same('not-run', $decoded['runnerEvidence']['status']);
        $t->same(false, PptxUpstreamReaderEvidence::hasNoValidationIssues($decoded));

        $missingRoot = $makeTempDir();
        try {
            $failingCommand = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($repoRoot . '/tools/pandoc-pptx-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg($missingRoot)
                . ' --upstream-root=' . escapeshellarg('missing-upstream-root-for-static-gate')
                . ' --json'
                . ' --require-static-current-evidence'
                . ' 2>/dev/null';
            $failingOutput = [];
            $failingExitCode = 0;
            exec($failingCommand, $failingOutput, $failingExitCode);

            $t->same(1, $failingExitCode);
        } finally {
            $removeTree($missingRoot);
        }
    },
    'cli gates pptx reader evidence counts and validation issues' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePptxEvidenceTree): void {
        $root = $makeTempDir();
        try {
            $writePptxEvidenceTree($root);
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-pptx-reader-evidence.php')
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
            $t->same('valid-upstream-pptx-reader-denominator', $decoded['validation']['status']);

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
