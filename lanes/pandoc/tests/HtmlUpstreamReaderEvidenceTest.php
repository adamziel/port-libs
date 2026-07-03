<?php

declare(strict_types=1);

use PortLibs\Pandoc\HtmlUpstreamReaderEvidence;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-html-evidence-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary HTML evidence directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary HTML evidence directory {$base}");
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

$writeHtmlEvidenceTree = static function (string $upstreamRoot) use ($writeFile): void {
    $writeFile($upstreamRoot, 'test/Tests/Readers/HTML.hs', "module Tests.Readers.HTML where\n");
    $writeFile($upstreamRoot, 'src/Text/Pandoc/Readers/HTML.hs', "module Text.Pandoc.Readers.HTML where\n");
};

$writeGitHead = static function (string $upstreamRoot, string $commit): void {
    $gitDirectory = $upstreamRoot . DIRECTORY_SEPARATOR . '.git';
    if (!is_dir($gitDirectory) && !mkdir($gitDirectory, 0777, true) && !is_dir($gitDirectory)) {
        throw new RuntimeException("Unable to create git directory {$gitDirectory}");
    }

    file_put_contents($gitDirectory . DIRECTORY_SEPARATOR . 'HEAD', $commit . "\n");
};

return [
    'reports skipped html reader evidence when upstream root is absent' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $report = (new HtmlUpstreamReaderEvidence($repoRoot, 'missing-upstream-root-for-static-html-gate'))->report();
        $text = HtmlUpstreamReaderEvidence::formatTextReport($report);

        $t->same(1, $report['schemaVersion']);
        $t->same(HtmlUpstreamReaderEvidence::TOOL_NAME, $report['tool']);
        $t->same(HtmlUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
        $t->same('not-evaluated-missing-upstream-root', $report['validation']['status']);
        $t->same(['missing-upstream-root'], $report['validation']['issues']);
        $t->same('valid-checked-in-current-html-reader-evidence', $report['staticCurrentEvidence']['validation']['status']);
        $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredSelectedFixtureCount($report, 60));
        $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredNativeMappedParity($report, 60));
        $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
        $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
        $t->same(false, HtmlUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->same('planned-not-run', $report['runnerEvidence']['commandPlanStatus']);
        $t->same('test:test-pandoc', $report['runnerEvidence']['target']['testSuite']);
        $t->same(['Readers', 'HTML'], $report['runnerEvidence']['target']['tastyGroupPath']);
        $t->same('$2 == "Readers" && $3 == "HTML"', $report['runnerEvidence']['target']['tastyPattern']);
        $t->same('$2 == "Readers" && $3 == "HTML"', $report['runnerEvidence']['futureCommands'][1]['arguments'][8]);
        $t->same('$2 == "Readers" && $3 == "HTML"', $report['runnerEvidence']['futureCommands'][2]['arguments'][7]);
        $t->true(in_array('.port-libs/pandoc-runner/logs/html-targeted-run.txt', $report['runnerEvidence']['requiredTranscripts'], true));
        $t->true(in_array('.port-libs/pandoc-runner/artifacts/html-targeted-run/result.json', $report['runnerEvidence']['requiredArtifacts'], true));
        $t->contains('Pandoc HTML reader evidence', $text);
        $t->contains('Static current evidence: valid-checked-in-current-html-reader-evidence checkedInFixtures=60 nativePairs=60', $text);
        $t->contains('Native AST mapped parity: 60/60', $text);
        $t->contains('Native AST fixture inventory: html=60 native=60 paired=60 unpairedHtml=0 unpairedNative=0', $text);
        $t->contains('Runner plan: planned-not-run', $text);
    },

    'reports checked-in current html fixture static evidence' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $evidence = HtmlUpstreamReaderEvidence::checkedInCurrentEvidence($repoRoot);

        $t->same('static-checked-in-current-upstream-html-reader-fixture-evidence', $evidence['kind']);
        $t->same(HtmlUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $evidence['upstream']['commit']);
        $t->same(60, $evidence['readerDenominator']['selectedFixtureCount']);
        $t->same('selected checked-in upstream-derived and generated-current HTML reader fixtures', $evidence['readerDenominator']['fixtureScope']);
        $t->same(60, $evidence['readerDenominator']['nativeMappedPairCount']);
        $t->same(60, $evidence['checkedInFixtureCount']);
        $t->same(60, $evidence['checkedInNativePairCount']);
        $t->same('upstream-html-anchor-image-attrs.html', $evidence['checkedInFixtures'][0]['name']);
        $t->same('27073f93fc90c5a85361723faad6fa6e1e44a891b344680476c41f9a4df3be74', $evidence['checkedInFixtures'][0]['checkedInFile']['sha256']);
        $t->same(363, $evidence['checkedInFixtures'][0]['checkedInFile']['bytes']);
        $t->same('lanes/pandoc/fixtures/upstream-html-anchor-image-attrs.native', $evidence['checkedInFixtures'][0]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][0]['checkedInNativePairFile']['present']);
        $t->same('7436ab45de8ec3a0738919f71e675412964bdf2dcf6aa60e56fc1fe4d5fffc6a', $evidence['checkedInFixtures'][0]['checkedInNativePairFile']['sha256']);
        $t->same(533, $evidence['checkedInFixtures'][0]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][0]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-base-absolute-image.html', $evidence['checkedInFixtures'][1]['name']);
        $t->same('f1ddb1f06c2b15d5667621c3c16b173d9afef19a7d5146bc017db44eba454e95', $evidence['checkedInFixtures'][1]['checkedInFile']['sha256']);
        $t->same(239, $evidence['checkedInFixtures'][1]['checkedInFile']['bytes']);
        $t->same('lanes/pandoc/fixtures/upstream-html-base-absolute-image.native', $evidence['checkedInFixtures'][1]['checkedInNativePairFile']['path']);
        $t->same('d4b2b819e8f822057a0a5f864d1113bb086ed55c1545726809e4b0243a68855f', $evidence['checkedInFixtures'][1]['checkedInNativePairFile']['sha256']);
        $t->same(139, $evidence['checkedInFixtures'][1]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][1]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-blockquote.html', $evidence['checkedInFixtures'][4]['name']);
        $t->same('7c1e8ba1dcde81e031bed35a0d75fad7dba0bf13ddbeef6188d38ae5cae82678', $evidence['checkedInFixtures'][4]['checkedInFile']['sha256']);
        $t->same(193, $evidence['checkedInFixtures'][4]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][4]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][4]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-definition-list.html', $evidence['checkedInFixtures'][7]['name']);
        $t->same('b90033d358361a2fbb664565e8a16ba7b7b474b54ce6c694ce7866bbcf805fcf', $evidence['checkedInFixtures'][7]['checkedInFile']['sha256']);
        $t->same(248, $evidence['checkedInFixtures'][7]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][7]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][7]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-details-summary-raw-block.html', $evidence['checkedInFixtures'][8]['name']);
        $t->same('f711f5a1e931605d4c6a6f17ec3ee863c12bdcacfbbf6291fa352305ebf049ca', $evidence['checkedInFixtures'][8]['checkedInFile']['sha256']);
        $t->same(268, $evidence['checkedInFixtures'][8]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][8]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][8]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-inline-quote-cite-base.html', $evidence['checkedInFixtures'][16]['name']);
        $t->same('58ab514d8472421c2abb19720e4debd695d5eb6f4f8dfea431081129eadad0b3', $evidence['checkedInFixtures'][16]['checkedInFile']['sha256']);
        $t->same(307, $evidence['checkedInFixtures'][16]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][16]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][16]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-sup-sub-inline.html', $evidence['checkedInFixtures'][47]['name']);
        $t->same('dbe1779879889257c5c569412522975181231391267bade40b84280943bdf2a0', $evidence['checkedInFixtures'][47]['checkedInFile']['sha256']);
        $t->same(173, $evidence['checkedInFixtures'][47]['checkedInFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][47]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-table-foot.html', $evidence['checkedInFixtures'][52]['name']);
        $t->same('250bdb2dfa7b027925bf2b2b858555085c27116f6c3326339a7fc6275326b159', $evidence['checkedInFixtures'][52]['checkedInFile']['sha256']);
        $t->same(382, $evidence['checkedInFixtures'][52]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][52]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][52]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-thematic-break.html', $evidence['checkedInFixtures'][54]['name']);
        $t->same('beb96f5bb964a06db042cb6e9b8b52f187c0701216e5b27a3a381d9d4cf536ea', $evidence['checkedInFixtures'][54]['checkedInFile']['sha256']);
        $t->same(205, $evidence['checkedInFixtures'][54]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][54]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][54]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-ruby-annotation.html', $evidence['checkedInFixtures'][55]['name']);
        $t->same('a0a8c53ca2264dcded30176307d7094145ccb337bfcabfae87a5d880074a0d60', $evidence['checkedInFixtures'][55]['checkedInFile']['sha256']);
        $t->same(203, $evidence['checkedInFixtures'][55]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][55]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][55]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-address-block.html', $evidence['checkedInFixtures'][56]['name']);
        $t->same('8108f7532e80e46c24c47d11ea212837f7cc3123d5dfbafa03af9187a0b50fcf', $evidence['checkedInFixtures'][56]['checkedInFile']['sha256']);
        $t->same(284, $evidence['checkedInFixtures'][56]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][56]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][56]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-xml-lang-metadata.html', $evidence['checkedInFixtures'][57]['name']);
        $t->same('ee6034835ca62d6e63472a2aa6a27c506f4c015369f8114409246357c2333596', $evidence['checkedInFixtures'][57]['checkedInFile']['sha256']);
        $t->same(95, $evidence['checkedInFixtures'][57]['checkedInFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][57]['localTestReferenceCount'] >= 1);
        $t->same('upstream-native-html-row-header-table.html', $evidence['checkedInFixtures'][58]['name']);
        $t->same('5f59ee99b16a90f6da337f94dd75c239cefb4ff7073c21e516077773892a332d', $evidence['checkedInFixtures'][58]['checkedInFile']['sha256']);
        $t->same(288, $evidence['checkedInFixtures'][58]['checkedInFile']['bytes']);
        $t->same('upstream-html-multi-tbody-row-header-table.html', $evidence['checkedInFixtures'][59]['name']);
        $t->same('2b89dfbad53b5f34a1d4f9ffc48d3450d0641ac762c6324a265f7061ab889b22', $evidence['checkedInFixtures'][59]['checkedInFile']['sha256']);
        $t->same(391, $evidence['checkedInFixtures'][59]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][59]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-multi-tbody-row-header-table.native', $evidence['checkedInFixtures'][59]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][59]['checkedInNativePairFile']['present']);
        $t->same('bbfb47f816f76c3dc4560673a0bcb8a2cf7bc7aa11c7c81a3d634212beaa6807', $evidence['checkedInFixtures'][59]['checkedInNativePairFile']['sha256']);
        $t->same(3940, $evidence['checkedInFixtures'][59]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][59]['localTestReferenceCount'] >= 1);
        $t->same('valid-checked-in-current-html-reader-evidence', $evidence['validation']['status']);
        $t->same([], $evidence['validation']['issues']);
        $t->true(in_array('each pinned HTML fixture has a same-basename checked-in native expectation file', $evidence['claimBoundaries']['doesAssert'], true));
        $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $evidence['claimBoundaries']['doesNotAssert'], true));
    },

    'rejects hydrated upstream html reader source evidence without pinned git head' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeHtmlEvidenceTree): void {
        $repoRoot = dirname(__DIR__, 3);
        $root = $makeTempDir();
        try {
            $writeHtmlEvidenceTree($root);
            $report = (new HtmlUpstreamReaderEvidence($repoRoot, $root))->report();

            $t->same(HtmlUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
            $t->same(null, $report['upstream']['commit']);
            $t->same('invalid-upstream-html-reader-evidence', $report['validation']['status']);
            $t->same(['upstream-html-reader-commit-mismatch'], $report['validation']['issues']);
            $t->same(60, $report['denominator']['selectedFixtureCount']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredNativeMappedParity($report, 60));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->same(false, HtmlUpstreamReaderEvidence::hasNoValidationIssues($report));
        } finally {
            $removeTree($root);
        }
    },

    'validates hydrated upstream html reader source evidence at expected commit' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeHtmlEvidenceTree, $writeGitHead): void {
        $repoRoot = dirname(__DIR__, 3);
        $root = $makeTempDir();
        try {
            $writeHtmlEvidenceTree($root);
            $writeGitHead($root, HtmlUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT);
            $report = (new HtmlUpstreamReaderEvidence($repoRoot, $root))->report();

            $t->same(HtmlUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
            $t->same(HtmlUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $report['upstream']['commit']);
            $t->same('valid-upstream-html-reader-evidence', $report['validation']['status']);
            $t->same([], $report['validation']['issues']);
            $t->same(60, $report['denominator']['selectedFixtureCount']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredNativeMappedParity($report, 60));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->same(true, HtmlUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->true(in_array('full upstream Tests.Readers.HTML runner parity', $report['claimBoundaries']['doesNotAssert'], true));
            $t->true(in_array('the future upstream runner command plan targets test:test-pandoc Readers/HTML at the pinned upstream commit without execution', $report['claimBoundaries']['doesAssert'], true));
        } finally {
            $removeTree($root);
        }
    },

    'cli gates checked-in current html fixture evidence without hydrated upstream cache' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($repoRoot . '/tools/pandoc-html-reader-evidence.php')
            . ' --repo-root=' . escapeshellarg($repoRoot)
            . ' --upstream-root=missing-upstream-root-for-static-html-gate'
            . ' --json'
            . ' --require-selected-fixture-count=60'
            . ' --require-static-current-evidence'
            . ' --require-native-mapped-parity=60'
            . ' --require-runner-not-run'
            . ' --require-runner-plan';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(HtmlUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $decoded['status']);
        $t->same(60, $decoded['staticCurrentEvidence']['readerDenominator']['selectedFixtureCount']);
        $t->same('valid-checked-in-current-html-reader-evidence', $decoded['staticCurrentEvidence']['validation']['status']);
        $t->same(60, $decoded['staticCurrentEvidence']['checkedInNativePairCount']);
        $t->same(60, $decoded['nativeAstEvidence']['normalizedAstMatchCount']);
        $t->same('not-run', $decoded['runnerEvidence']['status']);
        $t->same('planned-not-run', $decoded['runnerEvidence']['commandPlanStatus']);
        $t->same('$2 == "Readers" && $3 == "HTML"', $decoded['runnerEvidence']['target']['tastyPattern']);

        $failingCommand = str_replace('--require-selected-fixture-count=60', '--require-selected-fixture-count=61', $command) . ' 2>/dev/null';
        $failingOutput = [];
        $failingExitCode = 0;
        exec($failingCommand, $failingOutput, $failingExitCode);

        $t->same(1, $failingExitCode);
    },

    'cli rejects hydrated html source evidence without expected upstream commit' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeHtmlEvidenceTree): void {
        $repoRoot = dirname(__DIR__, 3);
        $root = $makeTempDir();
        try {
            $writeHtmlEvidenceTree($root);
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($repoRoot . '/tools/pandoc-html-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg($repoRoot)
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --json'
                . ' --require-no-validation-issues'
                . ' 2>/dev/null';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(1, $exitCode);
            $t->same('invalid-upstream-html-reader-evidence', $decoded['validation']['status']);
            $t->same(['upstream-html-reader-commit-mismatch'], $decoded['validation']['issues']);
        } finally {
            $removeTree($root);
        }
    },
];
