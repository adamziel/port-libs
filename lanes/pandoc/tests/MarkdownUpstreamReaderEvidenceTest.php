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
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredSelectedFixtureCount($report, 26));
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
        $t->same(false, MarkdownUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->contains('Pandoc Markdown reader evidence', $text);
        $t->contains('Selected checked-in fixtures: 26', $text);
        $t->contains('Static current evidence: valid-checked-in-current-markdown-reader-evidence checkedInFixtures=26', $text);
    },

    'reports checked-in current markdown fixture static evidence' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $evidence = MarkdownUpstreamReaderEvidence::checkedInCurrentEvidence($repoRoot);

        $t->same('static-checked-in-current-upstream-markdown-reader-fixture-evidence', $evidence['kind']);
        $t->same(MarkdownUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $evidence['upstream']['commit']);
        $t->same(26, $evidence['readerDenominator']['selectedFixtureCount']);
        $t->same('selected checked-in upstream-derived Markdown reader fixtures', $evidence['readerDenominator']['fixtureScope']);
        $t->same(['selected-upstream-markdown-reader-case', 'upstream-command-fixture'], $evidence['readerDenominator']['sourceKinds']);
        $t->same(26, $evidence['checkedInFixtureCount']);
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
        $t->same('upstream-markdown-definition-list-blank-second.md', $evidence['checkedInFixtures'][12]['name']);
        $t->same('41f9f906efbc3be2cd34fdf1ff854dc4e94ac257e3a467a0f466cf387a9142d2', $evidence['checkedInFixtures'][12]['checkedInFile']['sha256']);
        $t->same(39, $evidence['checkedInFixtures'][12]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php', $evidence['checkedInFixtures'][12]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php', $evidence['checkedInFixtures'][12]['localTestReferences'], true));
        $t->same('upstream-markdown-github-wikilinks.md', $evidence['checkedInFixtures'][13]['name']);
        $t->same('6f0ec576210ab97db42c4e7facdde2a34edfdb34eabc7198fc19367729892b3f', $evidence['checkedInFixtures'][13]['checkedInFile']['sha256']);
        $t->same(151, $evidence['checkedInFixtures'][13]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderGithubWikiLinkFixtureCompletionTest.php', $evidence['checkedInFixtures'][13]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderGithubWikiLinkFixtureCompletionTest.php', $evidence['checkedInFixtures'][13]['localTestReferences'], true));
        $t->same('upstream-markdown-inline-code-list-markers.md', $evidence['checkedInFixtures'][14]['name']);
        $t->same('66bbbbf31d775879a2df0a391f044b95241598d42a48a583148224664c50fcef', $evidence['checkedInFixtures'][14]['checkedInFile']['sha256']);
        $t->same(132, $evidence['checkedInFixtures'][14]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderInlineCodeListMarkerCompletionTest.php', $evidence['checkedInFixtures'][14]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderInlineCodeListMarkerCompletionTest.php', $evidence['checkedInFixtures'][14]['localTestReferences'], true));
        $t->same('upstream-markdown-backslash-escaped-links.md', $evidence['checkedInFixtures'][15]['name']);
        $t->same('6ad9984a92f484095874487a5fd713e18489f8216829e1e6f7c9137beb2e5216', $evidence['checkedInFixtures'][15]['checkedInFile']['sha256']);
        $t->same(110, $evidence['checkedInFixtures'][15]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBackslashEscapedLinkFixtureCompletionTest.php', $evidence['checkedInFixtures'][15]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBackslashEscapedLinkFixtureCompletionTest.php', $evidence['checkedInFixtures'][15]['localTestReferences'], true));
        $t->same('upstream-markdown-definition-list-nested-list.md', $evidence['checkedInFixtures'][16]['name']);
        $t->same('fec7e3095c4cd2c98514e86f9dd6ab35106ee9fc9fffdfdb80116bc60bd7f8e7', $evidence['checkedInFixtures'][16]['checkedInFile']['sha256']);
        $t->same(14, $evidence['checkedInFixtures'][16]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php', $evidence['checkedInFixtures'][16]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php', $evidence['checkedInFixtures'][16]['localTestReferences'], true));
        $t->same('upstream-markdown-definition-list-html-div.md', $evidence['checkedInFixtures'][17]['name']);
        $t->same('8addb5b1c8253a8c5d4019e5d86c16ce335e8bd7409cd3ea54bfddd42dd2c4af', $evidence['checkedInFixtures'][17]['checkedInFile']['sha256']);
        $t->same(26, $evidence['checkedInFixtures'][17]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php', $evidence['checkedInFixtures'][17]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php', $evidence['checkedInFixtures'][17]['localTestReferences'], true));
        $t->same('upstream-markdown-link-label-boundaries.md', $evidence['checkedInFixtures'][18]['name']);
        $t->same('261c0f00e439b4cdf7cdac0748ec16fa55760d79eb5feeef29baf0e373b36e86', $evidence['checkedInFixtures'][18]['checkedInFile']['sha256']);
        $t->same(76, $evidence['checkedInFixtures'][18]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderLinkLabelBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][18]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderLinkLabelBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][18]['localTestReferences'], true));
        $t->same('upstream-markdown-unbalanced-brackets.md', $evidence['checkedInFixtures'][19]['name']);
        $t->same('ad87edba0a8dc59fc7fb9f90885cd2203e1e4fae65e6a2feaa20cdb059d3ce5c', $evidence['checkedInFixtures'][19]['checkedInFile']['sha256']);
        $t->same(15, $evidence['checkedInFixtures'][19]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderUnbalancedBracketFixtureCompletionTest.php', $evidence['checkedInFixtures'][19]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderUnbalancedBracketFixtureCompletionTest.php', $evidence['checkedInFixtures'][19]['localTestReferences'], true));
        $t->same('upstream-markdown-link-title-entities.md', $evidence['checkedInFixtures'][20]['name']);
        $t->same('c9dc6a34cf99ca078667ee8f6c860a3fd0fc533cc6bef09982871ff7c52dc186', $evidence['checkedInFixtures'][20]['checkedInFile']['sha256']);
        $t->same(41, $evidence['checkedInFixtures'][20]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderLinkTitleEntityFixtureTest.php', $evidence['checkedInFixtures'][20]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderLinkTitleEntityFixtureTest.php', $evidence['checkedInFixtures'][20]['localTestReferences'], true));
        $t->same('upstream-markdown-inline-code-attribute.md', $evidence['checkedInFixtures'][21]['name']);
        $t->same('c66ef2aad2940a3c5dd08b69f66f2fb3418dd8e13403904ff625c9a9ed721033', $evidence['checkedInFixtures'][21]['checkedInFile']['sha256']);
        $t->same(40, $evidence['checkedInFixtures'][21]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderInlineCodeAttributeFixtureTest.php', $evidence['checkedInFixtures'][21]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderInlineCodeAttributeFixtureTest.php', $evidence['checkedInFixtures'][21]['localTestReferences'], true));
        $t->same('upstream-markdown-inline-code-attribute-space.md', $evidence['checkedInFixtures'][22]['name']);
        $t->same('9ebec08cb14463ce3612095b9e3be58869b95cd111cd4a1ab43fd462894aef58', $evidence['checkedInFixtures'][22]['checkedInFile']['sha256']);
        $t->same(30, $evidence['checkedInFixtures'][22]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderInlineCodeAttributeSpaceFixtureTest.php', $evidence['checkedInFixtures'][22]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderInlineCodeAttributeSpaceFixtureTest.php', $evidence['checkedInFixtures'][22]['localTestReferences'], true));
        $t->same('upstream-markdown-character-references.md', $evidence['checkedInFixtures'][23]['name']);
        $t->same('21c98a8e50f0dc8b4ee6fe323df335c944aca0b5c452c2db0809b47e2fd6aa6d', $evidence['checkedInFixtures'][23]['checkedInFile']['sha256']);
        $t->same(14, $evidence['checkedInFixtures'][23]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderCharacterReferenceFixtureTest.php', $evidence['checkedInFixtures'][23]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderCharacterReferenceFixtureTest.php', $evidence['checkedInFixtures'][23]['localTestReferences'], true));
        $t->same('upstream-markdown-strikeout.md', $evidence['checkedInFixtures'][24]['name']);
        $t->same('30c0bc85dc189577486880cb6f9a135d77b420b091bf6396f31d4a1f985cddcb', $evidence['checkedInFixtures'][24]['checkedInFile']['sha256']);
        $t->same(25, $evidence['checkedInFixtures'][24]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderStrikeoutFixtureTest.php', $evidence['checkedInFixtures'][24]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderStrikeoutFixtureTest.php', $evidence['checkedInFixtures'][24]['localTestReferences'], true));
        $t->same('upstream-markdown-emoji-symbols.md', $evidence['checkedInFixtures'][25]['name']);
        $t->same('90eb7db9d0f39fb87c9caa96f9f02d6facd390d39e3e82f59724883e2cc1c32b', $evidence['checkedInFixtures'][25]['checkedInFile']['sha256']);
        $t->same(17, $evidence['checkedInFixtures'][25]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderEmojiFixtureTest.php', $evidence['checkedInFixtures'][25]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderEmojiFixtureTest.php', $evidence['checkedInFixtures'][25]['localTestReferences'], true));
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
            $t->same(26, $report['denominator']['selectedFixtureCount']);
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
            . ' --require-selected-fixture-count=26'
            . ' --require-static-current-evidence'
            . ' --require-runner-not-run';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(MarkdownUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $decoded['status']);
        $t->same(26, $decoded['staticCurrentEvidence']['readerDenominator']['selectedFixtureCount']);
        $t->same('valid-checked-in-current-markdown-reader-evidence', $decoded['staticCurrentEvidence']['validation']['status']);
        $t->same('not-run', $decoded['runnerEvidence']['status']);
        $t->true(in_array('complete Markdown dialect parity across every Pandoc extension profile', $decoded['claimBoundaries']['doesNotAssert'], true));

        $failingCommand = str_replace('--require-selected-fixture-count=26', '--require-selected-fixture-count=27', $command) . ' 2>/dev/null';
        $failingOutput = [];
        $failingExitCode = 0;
        exec($failingCommand, $failingOutput, $failingExitCode);

        $t->same(1, $failingExitCode);
    },
];
