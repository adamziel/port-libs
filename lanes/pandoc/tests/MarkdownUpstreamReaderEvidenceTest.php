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
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredSelectedFixtureCount($report, 41));
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
        $t->same(false, MarkdownUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->contains('Pandoc Markdown reader evidence', $text);
        $t->contains('Selected checked-in fixtures: 41', $text);
        $t->contains('Static current evidence: valid-checked-in-current-markdown-reader-evidence checkedInFixtures=41', $text);
    },

    'reports checked-in current markdown fixture static evidence' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $evidence = MarkdownUpstreamReaderEvidence::checkedInCurrentEvidence($repoRoot);

        $t->same('static-checked-in-current-upstream-markdown-reader-fixture-evidence', $evidence['kind']);
        $t->same(MarkdownUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $evidence['upstream']['commit']);
        $t->same(41, $evidence['readerDenominator']['selectedFixtureCount']);
        $t->same('selected checked-in upstream-derived Markdown reader fixtures', $evidence['readerDenominator']['fixtureScope']);
        $t->same(['selected-upstream-markdown-reader-case', 'upstream-command-fixture'], $evidence['readerDenominator']['sourceKinds']);
        $t->same(41, $evidence['checkedInFixtureCount']);
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
        $t->same('upstream-markdown-superscript-subscript.md', $evidence['checkedInFixtures'][26]['name']);
        $t->same('bf1a4d320f780ab971cfa70deff5beaf835d738f3f5aab9f7c33def0c2e24efe', $evidence['checkedInFixtures'][26]['checkedInFile']['sha256']);
        $t->same(199, $evidence['checkedInFixtures'][26]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderScriptFixtureTest.php', $evidence['checkedInFixtures'][26]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderScriptFixtureTest.php', $evidence['checkedInFixtures'][26]['localTestReferences'], true));
        $t->same('upstream-markdown-smart-punctuation.md', $evidence['checkedInFixtures'][27]['name']);
        $t->same('4bb6eabecef549ae4b8e3f29c7a7f956d7d1e2a24f157cb42e7c10a97fbb0fb3', $evidence['checkedInFixtures'][27]['checkedInFile']['sha256']);
        $t->same(135, $evidence['checkedInFixtures'][27]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderSmartPunctuationFixtureCompletionTest.php', $evidence['checkedInFixtures'][27]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderSmartPunctuationFixtureCompletionTest.php', $evidence['checkedInFixtures'][27]['localTestReferences'], true));
        $t->same('upstream-markdown-pipe-table-escaped-cell.md', $evidence['checkedInFixtures'][28]['name']);
        $t->same('11abcb5c5ff0e2815bc3abcfabbc5fcd38e19fb2be4ac32104b3e4c554d13516', $evidence['checkedInFixtures'][28]['checkedInFile']['sha256']);
        $t->same(76, $evidence['checkedInFixtures'][28]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderPipeTableFixtureCompletionTest.php', $evidence['checkedInFixtures'][28]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderPipeTableFixtureCompletionTest.php', $evidence['checkedInFixtures'][28]['localTestReferences'], true));
        $t->same('upstream-markdown-fenced-div.md', $evidence['checkedInFixtures'][29]['name']);
        $t->same('55f2e8b78f0447326a3cd1574f8622a91d1f3fa8e595b83204eb70adb4e089c2', $evidence['checkedInFixtures'][29]['checkedInFile']['sha256']);
        $t->same(106, $evidence['checkedInFixtures'][29]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFencedDivFixtureCompletionTest.php', $evidence['checkedInFixtures'][29]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFencedDivFixtureCompletionTest.php', $evidence['checkedInFixtures'][29]['localTestReferences'], true));
        $t->same('upstream-markdown-header-attributes.md', $evidence['checkedInFixtures'][30]['name']);
        $t->same('69a74cf0b29a2821c37d2c8b08791c168691de424cf70bb9b8e5802ea4fa0520', $evidence['checkedInFixtures'][30]['checkedInFile']['sha256']);
        $t->same(80, $evidence['checkedInFixtures'][30]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderHeaderAttributeFixtureTest.php', $evidence['checkedInFixtures'][30]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderHeaderAttributeFixtureTest.php', $evidence['checkedInFixtures'][30]['localTestReferences'], true));
        $t->same('upstream-markdown-numbered-examples.md', $evidence['checkedInFixtures'][31]['name']);
        $t->same('8b249e3e2d1a4c4995eb28150c84bb4c77166d9dfb97de0b448e90388e7e8fc9', $evidence['checkedInFixtures'][31]['checkedInFile']['sha256']);
        $t->same(39, $evidence['checkedInFixtures'][31]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderNumberedExampleFixtureCompletionTest.php', $evidence['checkedInFixtures'][31]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderNumberedExampleFixtureCompletionTest.php', $evidence['checkedInFixtures'][31]['localTestReferences'], true));
        $t->same('upstream-markdown-mark.md', $evidence['checkedInFixtures'][32]['name']);
        $t->same('b8c09f9af30b7896e4721d9eada0dfb9cf06c29989eff78cb6ae3e8221a2b4f7', $evidence['checkedInFixtures'][32]['checkedInFile']['sha256']);
        $t->same(36, $evidence['checkedInFixtures'][32]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMarkFixtureCompletionTest.php', $evidence['checkedInFixtures'][32]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMarkFixtureCompletionTest.php', $evidence['checkedInFixtures'][32]['localTestReferences'], true));
        $t->same('upstream-markdown-bracketed-spans.md', $evidence['checkedInFixtures'][33]['name']);
        $t->same('04316f1a9913cf1614ae0fcbf3e493a07456fbe5a7d80f9558dda665bf347456', $evidence['checkedInFixtures'][33]['checkedInFile']['sha256']);
        $t->same(147, $evidence['checkedInFixtures'][33]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBracketedSpanFixtureCompletionTest.php', $evidence['checkedInFixtures'][33]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBracketedSpanFixtureCompletionTest.php', $evidence['checkedInFixtures'][33]['localTestReferences'], true));
        $t->same('upstream-markdown-fenced-code-attributes.md', $evidence['checkedInFixtures'][34]['name']);
        $t->same('6f09b188dded819552fc8a2297abafbfd0d158d0d238bcaf325a93ec766d8b30', $evidence['checkedInFixtures'][34]['checkedInFile']['sha256']);
        $t->same(78, $evidence['checkedInFixtures'][34]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFencedCodeAttributeFixtureCompletionTest.php', $evidence['checkedInFixtures'][34]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFencedCodeAttributeFixtureCompletionTest.php', $evidence['checkedInFixtures'][34]['localTestReferences'], true));
        $t->same('upstream-markdown-mmd-short-scripts.md', $evidence['checkedInFixtures'][35]['name']);
        $t->same('51ad1c2f928c09fe555f260e30579499bdea5d1b34941f1760b3c07a787d03d4', $evidence['checkedInFixtures'][35]['checkedInFile']['sha256']);
        $t->same(100, $evidence['checkedInFixtures'][35]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMmdShortScriptFixtureCompletionTest.php', $evidence['checkedInFixtures'][35]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMmdShortScriptFixtureCompletionTest.php', $evidence['checkedInFixtures'][35]['localTestReferences'], true));
        $t->same('upstream-markdown-numeric-character-references.md', $evidence['checkedInFixtures'][36]['name']);
        $t->same('73238d01c18d759c2af0440d38c928593d2511e5d07090487639a50d056df12c', $evidence['checkedInFixtures'][36]['checkedInFile']['sha256']);
        $t->same(18, $evidence['checkedInFixtures'][36]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderNumericCharacterReferenceFixtureCompletionTest.php', $evidence['checkedInFixtures'][36]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderNumericCharacterReferenceFixtureCompletionTest.php', $evidence['checkedInFixtures'][36]['localTestReferences'], true));
        $t->same('upstream-markdown-footnote-definitions.md', $evidence['checkedInFixtures'][37]['name']);
        $t->same('4e11531363fafbdd59e3c1cd99f37e0162340827819b667ecea1b859f5ca5bd4', $evidence['checkedInFixtures'][37]['checkedInFile']['sha256']);
        $t->same(21, $evidence['checkedInFixtures'][37]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFootnoteDefinitionFixtureCompletionTest.php', $evidence['checkedInFixtures'][37]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFootnoteDefinitionFixtureCompletionTest.php', $evidence['checkedInFixtures'][37]['localTestReferences'], true));
        $t->same('upstream-markdown-escaped-line-break.md', $evidence['checkedInFixtures'][38]['name']);
        $t->same('227f9cf35e3cdba7f00821c2a4c1e3dc7914cf5e74ae2c59a9e49aae616d2303', $evidence['checkedInFixtures'][38]['checkedInFile']['sha256']);
        $t->same(12, $evidence['checkedInFixtures'][38]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderEscapedLineBreakFixtureCompletionTest.php', $evidence['checkedInFixtures'][38]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderEscapedLineBreakFixtureCompletionTest.php', $evidence['checkedInFixtures'][38]['localTestReferences'], true));
        $t->same('upstream-markdown-implicit-header-references.md', $evidence['checkedInFixtures'][39]['name']);
        $t->same('0a7eaf250ae086961351c6c684f26aa3b63caf4abe743fa29f2325c6d653f904', $evidence['checkedInFixtures'][39]['checkedInFile']['sha256']);
        $t->same(46, $evidence['checkedInFixtures'][39]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderImplicitHeaderReferenceFixtureCompletionTest.php', $evidence['checkedInFixtures'][39]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderImplicitHeaderReferenceFixtureCompletionTest.php', $evidence['checkedInFixtures'][39]['localTestReferences'], true));
        $t->same('upstream-markdown-emph-strong-boundaries.md', $evidence['checkedInFixtures'][40]['name']);
        $t->same('dacb0085f517373fa21e84028a7433a2315f90fca6eda8500d393a5783b06bf9', $evidence['checkedInFixtures'][40]['checkedInFile']['sha256']);
        $t->same(84, $evidence['checkedInFixtures'][40]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderEmphStrongBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][40]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderEmphStrongBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][40]['localTestReferences'], true));
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
            $t->same(41, $report['denominator']['selectedFixtureCount']);
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
            . ' --require-selected-fixture-count=41'
            . ' --require-static-current-evidence'
            . ' --require-runner-not-run';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(MarkdownUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $decoded['status']);
        $t->same(41, $decoded['staticCurrentEvidence']['readerDenominator']['selectedFixtureCount']);
        $t->same('valid-checked-in-current-markdown-reader-evidence', $decoded['staticCurrentEvidence']['validation']['status']);
        $t->same('not-run', $decoded['runnerEvidence']['status']);
        $t->true(in_array('complete Markdown dialect parity across every Pandoc extension profile', $decoded['claimBoundaries']['doesNotAssert'], true));

        $failingCommand = str_replace('--require-selected-fixture-count=41', '--require-selected-fixture-count=40', $command) . ' 2>/dev/null';
        $failingOutput = [];
        $failingExitCode = 0;
        exec($failingCommand, $failingOutput, $failingExitCode);

        $t->same(1, $failingExitCode);
    },
];
