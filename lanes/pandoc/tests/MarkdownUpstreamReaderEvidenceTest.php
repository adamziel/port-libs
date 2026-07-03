<?php

declare(strict_types=1);

use PortLibs\Pandoc\MarkdownUpstreamReaderEvidence;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-markdown-evidence-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary Markdown evidence directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary Markdown evidence directory {$base}");
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

$writeMarkdownEvidenceTree = static function (string $upstreamRoot) use ($writeFile): void {
    $writeFile($upstreamRoot, 'test/Tests/Readers/Markdown.hs', "module Tests.Readers.Markdown where\n");
    $writeFile($upstreamRoot, 'src/Text/Pandoc/Readers/Markdown.hs', "module Text.Pandoc.Readers.Markdown where\n");
};

return [
    'reports skipped markdown reader evidence when upstream root is absent' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $report = (new MarkdownUpstreamReaderEvidence($repoRoot, 'missing-upstream-root-for-static-markdown-gate'))->report();
        $text = MarkdownUpstreamReaderEvidence::formatTextReport($report);

        $t->same(1, $report['schemaVersion']);
        $t->same(MarkdownUpstreamReaderEvidence::TOOL_NAME, $report['tool']);
        $t->same(MarkdownUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
        $t->same('not-evaluated-missing-upstream-root', $report['validation']['status']);
        $t->same(['missing-upstream-root'], $report['validation']['issues']);
        $t->same('valid-checked-in-current-markdown-reader-evidence', $report['staticCurrentEvidence']['validation']['status']);
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredSelectedFixtureCount($report, 13));
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
        $t->same(false, MarkdownUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->contains('Pandoc Markdown reader evidence', $text);
        $t->contains('Selected checked-in fixtures: 13', $text);
        $t->contains('Static current evidence: valid-checked-in-current-markdown-reader-evidence checkedInFixtures=13', $text);
    },

    'reports checked-in current markdown fixture static evidence' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $evidence = MarkdownUpstreamReaderEvidence::checkedInCurrentEvidence($repoRoot);

        $t->same('static-checked-in-current-upstream-markdown-reader-fixture-evidence', $evidence['kind']);
        $t->same(MarkdownUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $evidence['upstream']['commit']);
        $t->same(13, $evidence['readerDenominator']['selectedFixtureCount']);
        $t->same('selected checked-in upstream-derived Markdown reader fixtures', $evidence['readerDenominator']['fixtureScope']);
        $t->same(['selected-upstream-markdown-reader-case', 'upstream-command-fixture'], $evidence['readerDenominator']['sourceKinds']);
        $t->same(13, $evidence['checkedInFixtureCount']);
        $t->same('upstream-command-parse-raw.md', $evidence['checkedInFixtures'][0]['name']);
        $t->same('command-parse-raw-reader-fixture', $evidence['checkedInFixtures'][0]['role']);
        $t->same('e3b50f56f86883e3e323cf97d52cd07a3c3797fb7d5f89bbb422392e8008f72b', $evidence['checkedInFixtures'][0]['checkedInFile']['sha256']);
        $t->same(379, $evidence['checkedInFixtures'][0]['checkedInFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][0]['localTestReferenceCount'] >= 1);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderParseRawFixtureCompletionTest.php', $evidence['checkedInFixtures'][0]['localTestReferences'], true));
        $t->same('upstream-command-details-summary.md', $evidence['checkedInFixtures'][2]['name']);
        $t->same('bd279e57d0cad59c8c7b9651f58fee3e763cb822af97ec34323144ea4fa0955c', $evidence['checkedInFixtures'][2]['checkedInFile']['sha256']);
        $t->same(188, $evidence['checkedInFixtures'][2]['checkedInFile']['bytes']);
        $t->same('upstream-markdown-citation-span-boundary.md', $evidence['checkedInFixtures'][6]['name']);
        $t->same('4a9c744c4eef5597fcd1c178fd756b18ee78e70e57230689c564d2f695bef6d1', $evidence['checkedInFixtures'][6]['checkedInFile']['sha256']);
        $t->same(84, $evidence['checkedInFixtures'][6]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderCitationSpanBoundaryCompletionTest.php', $evidence['checkedInFixtures'][6]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderCitationSpanBoundaryCompletionTest.php', $evidence['checkedInFixtures'][6]['localTestReferences'], true));
        $t->same('upstream-markdown-line-blocks.md', $evidence['checkedInFixtures'][7]['name']);
        $t->same('7a175df8a9934d4e50567ba25b1736df404f704740dc8671ea455e8910d4681c', $evidence['checkedInFixtures'][7]['checkedInFile']['sha256']);
        $t->same(38, $evidence['checkedInFixtures'][7]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderLineBlockProfileSurgeTest.php', $evidence['checkedInFixtures'][7]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderLineBlockProfileSurgeTest.php', $evidence['checkedInFixtures'][7]['localTestReferences'], true));
        $t->same('upstream-markdown-task-list.md', $evidence['checkedInFixtures'][8]['name']);
        $t->same('2631c0b4e1bbaa22fe4e13f8da163f37feb68c6a0c4fb4d8185402b43407611d', $evidence['checkedInFixtures'][8]['checkedInFile']['sha256']);
        $t->same(108, $evidence['checkedInFixtures'][8]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderTaskListProfileSurgeTest.php', $evidence['checkedInFixtures'][8]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderTaskListProfileSurgeTest.php', $evidence['checkedInFixtures'][8]['localTestReferences'], true));
        $t->same('upstream-command-empty-paragraphs.md', $evidence['checkedInFixtures'][9]['name']);
        $t->same('3cec1e6ab0a690ebe90035bd1a71453c755b6ff198283b8651c8161c20983314', $evidence['checkedInFixtures'][9]['checkedInFile']['sha256']);
        $t->same(1800, $evidence['checkedInFixtures'][9]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderTest.php', $evidence['checkedInFixtures'][9]['localTestReferences'], true));
        $t->same('upstream-markdown-definition-lists.md', $evidence['checkedInFixtures'][10]['name']);
        $t->same('233fac188307a5ed3eeaa321c45322e221637c4deaf8c52626af41161c2aaec0', $evidence['checkedInFixtures'][10]['checkedInFile']['sha256']);
        $t->same(38, $evidence['checkedInFixtures'][10]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php', $evidence['checkedInFixtures'][10]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php', $evidence['checkedInFixtures'][10]['localTestReferences'], true));
        $t->same('upstream-markdown-definition-list-blank-first.md', $evidence['checkedInFixtures'][11]['name']);
        $t->same('2df49ee09f7e0538c7f2c3fff6ccea40a7a5d7caeeca954aa67a87f785be2970', $evidence['checkedInFixtures'][11]['checkedInFile']['sha256']);
        $t->same(40, $evidence['checkedInFixtures'][11]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php', $evidence['checkedInFixtures'][11]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php', $evidence['checkedInFixtures'][11]['localTestReferences'], true));
        $t->same('upstream-markdown-github-wikilinks.md', $evidence['checkedInFixtures'][12]['name']);
        $t->same('6f0ec576210ab97db42c4e7facdde2a34edfdb34eabc7198fc19367729892b3f', $evidence['checkedInFixtures'][12]['checkedInFile']['sha256']);
        $t->same(151, $evidence['checkedInFixtures'][12]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderGithubWikiLinkFixtureCompletionTest.php', $evidence['checkedInFixtures'][12]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderGithubWikiLinkFixtureCompletionTest.php', $evidence['checkedInFixtures'][12]['localTestReferences'], true));
        $t->same('valid-checked-in-current-markdown-reader-evidence', $evidence['validation']['status']);
        $t->same([], $evidence['validation']['issues']);
        $t->true(in_array('each selected fixture has at least one local PHP test reference', $evidence['claimBoundaries']['doesAssert'], true));
        $t->true(in_array('full Markdown dialect parity across every extension combination', $evidence['claimBoundaries']['doesNotAssert'], true));
    },

    'validates hydrated upstream markdown reader source evidence' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeMarkdownEvidenceTree): void {
        $repoRoot = dirname(__DIR__, 3);
        $root = $makeTempDir();
        try {
            $writeMarkdownEvidenceTree($root);
            $report = (new MarkdownUpstreamReaderEvidence($repoRoot, $root))->report();

            $t->same(MarkdownUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
            $t->same('valid-upstream-markdown-reader-evidence', $report['validation']['status']);
            $t->same([], $report['validation']['issues']);
            $t->same(13, $report['denominator']['selectedFixtureCount']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
            $t->same(true, MarkdownUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(true, MarkdownUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->true(in_array('full upstream Tests.Readers.Markdown runner parity', $report['claimBoundaries']['doesNotAssert'], true));
        } finally {
            $removeTree($root);
        }
    },

    'cli gates checked-in current markdown fixture evidence through checked-in fixtures mode' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($repoRoot . '/tools/pandoc-markdown-reader-evidence.php')
            . ' --repo-root=' . escapeshellarg($repoRoot)
            . ' --checked-in-fixtures'
            . ' --json'
            . ' --require-selected-fixture-count=13'
            . ' --require-static-current-evidence'
            . ' --require-runner-not-run';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(MarkdownUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $decoded['status']);
        $t->same(13, $decoded['staticCurrentEvidence']['readerDenominator']['selectedFixtureCount']);
        $t->same('valid-checked-in-current-markdown-reader-evidence', $decoded['staticCurrentEvidence']['validation']['status']);
        $t->same('not-run', $decoded['runnerEvidence']['status']);
        $t->true(in_array('complete Markdown dialect parity across every Pandoc extension profile', $decoded['claimBoundaries']['doesNotAssert'], true));

        $failingCommand = str_replace('--require-selected-fixture-count=13', '--require-selected-fixture-count=14', $command) . ' 2>/dev/null';
        $failingOutput = [];
        $failingExitCode = 0;
        exec($failingCommand, $failingOutput, $failingExitCode);

        $t->same(1, $failingExitCode);
    },
];
