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
        $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredSelectedFixtureCount($report, 48));
        $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredNativeMappedParity($report, 41));
        $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
        $t->same(false, HtmlUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->contains('Pandoc HTML reader evidence', $text);
        $t->contains('Static current evidence: valid-checked-in-current-html-reader-evidence checkedInFixtures=48', $text);
        $t->contains('Native AST mapped parity: 41/41', $text);
        $t->contains('Native AST fixture inventory: html=48 native=41 paired=41 unpairedHtml=7 unpairedNative=0', $text);
    },

    'reports checked-in current html fixture static evidence' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $evidence = HtmlUpstreamReaderEvidence::checkedInCurrentEvidence($repoRoot);

        $t->same('static-checked-in-current-upstream-html-reader-fixture-evidence', $evidence['kind']);
        $t->same(HtmlUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $evidence['upstream']['commit']);
        $t->same(48, $evidence['readerDenominator']['selectedFixtureCount']);
        $t->same('selected checked-in upstream-derived HTML reader fixtures', $evidence['readerDenominator']['fixtureScope']);
        $t->same(41, $evidence['readerDenominator']['nativeMappedPairCount']);
        $t->same(48, $evidence['checkedInFixtureCount']);
        $t->same('upstream-html-anchor-image-attrs.html', $evidence['checkedInFixtures'][0]['name']);
        $t->same('27073f93fc90c5a85361723faad6fa6e1e44a891b344680476c41f9a4df3be74', $evidence['checkedInFixtures'][0]['checkedInFile']['sha256']);
        $t->same(363, $evidence['checkedInFixtures'][0]['checkedInFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][0]['localTestReferenceCount'] >= 1);
        $t->same('upstream-native-html-row-header-table.html', $evidence['checkedInFixtures'][47]['name']);
        $t->same('5f59ee99b16a90f6da337f94dd75c239cefb4ff7073c21e516077773892a332d', $evidence['checkedInFixtures'][47]['checkedInFile']['sha256']);
        $t->same(288, $evidence['checkedInFixtures'][47]['checkedInFile']['bytes']);
        $t->same('valid-checked-in-current-html-reader-evidence', $evidence['validation']['status']);
        $t->same([], $evidence['validation']['issues']);
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
            $t->same(48, $report['denominator']['selectedFixtureCount']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredNativeMappedParity($report, 41));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
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
            $t->same(48, $report['denominator']['selectedFixtureCount']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredNativeMappedParity($report, 41));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(true, HtmlUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->true(in_array('full upstream Tests.Readers.HTML runner parity', $report['claimBoundaries']['doesNotAssert'], true));
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
            . ' --require-selected-fixture-count=48'
            . ' --require-static-current-evidence'
            . ' --require-native-mapped-parity=41'
            . ' --require-runner-not-run';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(HtmlUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $decoded['status']);
        $t->same(48, $decoded['staticCurrentEvidence']['readerDenominator']['selectedFixtureCount']);
        $t->same('valid-checked-in-current-html-reader-evidence', $decoded['staticCurrentEvidence']['validation']['status']);
        $t->same(41, $decoded['nativeAstEvidence']['normalizedAstMatchCount']);
        $t->same('not-run', $decoded['runnerEvidence']['status']);

        $failingCommand = str_replace('--require-selected-fixture-count=48', '--require-selected-fixture-count=49', $command) . ' 2>/dev/null';
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
