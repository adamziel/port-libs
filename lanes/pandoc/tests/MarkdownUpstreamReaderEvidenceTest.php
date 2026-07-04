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

$writeRunnerTranscripts = static function (string $root, array $paths) use ($writeFile): array {
    $records = [];
    foreach (array_values($paths) as $index => $path) {
        $contents = 'markdown runner transcript ' . (string) ($index + 1) . "\n" . $path . "\n";
        $writeFile($root, $path, $contents);
        $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $records[] = [
            'path' => $path,
            'sha256' => hash_file('sha256', $absolutePath),
            'bytes' => filesize($absolutePath),
        ];
    }

    return $records;
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
        $t->same('valid-checked-in-current-markdown-native-expectation-evidence', $report['staticCurrentEvidence']['nativeExpectationEvidence']['validation']['status']);
        $t->same(59, $report['staticCurrentEvidence']['nativeExpectationEvidence']['presentFixtureCount']);
        $t->same(MarkdownUpstreamReaderEvidence::EXPECTED_NATIVE_EXPECTATION_MANIFEST_SHA256, $report['staticCurrentEvidence']['nativeExpectationEvidence']['manifestSha256']);
        $t->same(59, $report['nativeAstEvidence']['totalPairCount']);
        $t->same(59, $report['nativeAstEvidence']['normalizedAstMatchCount']);
        $t->same(0, $report['nativeAstEvidence']['unpairedMarkdownFixtureCount']);
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredSelectedFixtureCount($report, 59));
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredNativeMappedParity($report, 59));
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
        $t->same(false, MarkdownUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->same('planned-not-run', $report['runnerEvidence']['commandPlanStatus']);
        $t->same(['Readers', 'Markdown'], $report['runnerEvidence']['target']['tastyGroupPath']);
        $t->same('$2 == "Readers" && $3 == "Markdown"', $report['runnerEvidence']['target']['tastyPattern']);
        $t->true(in_array('.port-libs/pandoc-runner/logs/markdown-targeted-run.txt', $report['runnerEvidence']['requiredTranscripts'], true));
        $t->true(in_array('.port-libs/pandoc-runner/artifacts/markdown-targeted-run/result.json', $report['runnerEvidence']['requiredArtifacts'], true));
        $t->contains('Pandoc Markdown reader evidence', $text);
        $t->contains('Selected checked-in fixtures: 59', $text);
        $t->contains('Static current evidence: valid-checked-in-current-markdown-reader-evidence checkedInFixtures=59 nativeExpectations=59 nativeManifest=valid-checked-in-current-markdown-native-expectation-evidence', $text);
        $t->contains('Native AST mapped parity: 59/59 status=normalized-ast-equality-observed-not-runner-parity', $text);
        $t->contains('Runner plan: planned-not-run', $text);
    },

    'reports checked-in current markdown fixture static evidence' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $evidence = MarkdownUpstreamReaderEvidence::checkedInCurrentEvidence($repoRoot);

        $t->same('static-checked-in-current-upstream-markdown-reader-fixture-evidence', $evidence['kind']);
        $t->same(MarkdownUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $evidence['upstream']['commit']);
        $t->same(59, $evidence['readerDenominator']['selectedFixtureCount']);
        $t->same('selected checked-in upstream-derived Markdown reader fixtures', $evidence['readerDenominator']['fixtureScope']);
        $t->same(['selected-upstream-markdown-reader-case', 'upstream-command-fixture'], $evidence['readerDenominator']['sourceKinds']);
        $t->same(59, $evidence['checkedInFixtureCount']);
        $t->same('static-checked-in-current-markdown-native-expectation-evidence', $evidence['nativeExpectationEvidence']['kind']);
        $t->same(59, $evidence['nativeExpectationEvidence']['expectedFixtureCount']);
        $t->same(59, $evidence['nativeExpectationEvidence']['fixtureCount']);
        $t->same(59, $evidence['nativeExpectationEvidence']['presentFixtureCount']);
        $t->same(MarkdownUpstreamReaderEvidence::EXPECTED_NATIVE_EXPECTATION_MANIFEST_SHA256, $evidence['nativeExpectationEvidence']['expectedManifestSha256']);
        $t->same(MarkdownUpstreamReaderEvidence::EXPECTED_NATIVE_EXPECTATION_MANIFEST_SHA256, $evidence['nativeExpectationEvidence']['manifestSha256']);
        $t->same('valid-checked-in-current-markdown-native-expectation-evidence', $evidence['nativeExpectationEvidence']['validation']['status']);
        $t->same([], $evidence['nativeExpectationEvidence']['validation']['issues']);
        $t->same('upstream-command-details-summary.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][0]['name']);
        $t->same('b4eb75fd704ba79251373224e5dfaf6f5ebb7333af8da4eb7dff9143c570dea5', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][0]['sha256']);
        $t->same(480, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][0]['bytes']);
        $t->same('upstream-markdown-ascii-identifiers.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][7]['name']);
        $t->same('b524cd92748fd10b2d98431e6bd009b446c3fbd885adb77fa325594b20eb6951', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][7]['sha256']);
        $t->same(1048, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][7]['bytes']);
        $t->same('upstream-markdown-autolink-attributes.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][8]['name']);
        $t->same('cd7de98dd3d21abdc5c0b501f7f0c92eda63f4f102449d58c8d0036ff9886726', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][8]['sha256']);
        $t->same(379, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][8]['bytes']);
        $t->same('upstream-markdown-definition-list-nested-list.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][16]['name']);
        $t->same('c22963e813336f323a81771346bf208a06d7cb1b0863aa6ec7073b80fed459e7', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][16]['sha256']);
        $t->same(110, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][16]['bytes']);
        $t->same('upstream-markdown-definition-list-tight-bodies.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][17]['name']);
        $t->same('79070528c9fff595351668c7a84bdba031bbbfbfe94ca511a58923559172c36b', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][17]['sha256']);
        $t->same(303, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][17]['bytes']);
        $t->same('upstream-markdown-inline-math.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][34]['name']);
        $t->same('436699144f2e7a6c951532b7ee1f859a92e32ea954d3694f46f82d1701bc8a3b', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][34]['sha256']);
        $t->same(169, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][34]['bytes']);
        $t->same('upstream-markdown-inline-note-citations.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][35]['name']);
        $t->same('04ec5abd9d97dad4c60a9502fcda48ab6d596d22d8773742dfaa787593513ad0', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][35]['sha256']);
        $t->same(1115, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][35]['bytes']);
        $t->same('upstream-markdown-lhs-inverse-bird-html.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][36]['name']);
        $t->same('ded30bb9ec48394e6dd42c95ab504456b4f85ff37c1f1a68076bf603260c25d8', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][36]['sha256']);
        $t->same(129, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][36]['bytes']);
        $t->same('upstream-markdown-raw-html-nesting.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][47]['name']);
        $t->same('d16a4635f6eae97cbd79896344371abc70993130b2493e232e8778f950e5e8a6', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][47]['sha256']);
        $t->same(96, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][47]['bytes']);
        $t->same('upstream-markdown-strict-compact-heading.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][50]['name']);
        $t->same('dca4b0a1370fb86f4ea2ab8f19d1254313558ae57954679d1b3ca1fbaa2c8044', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][50]['sha256']);
        $t->same(43, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][50]['bytes']);
        $t->same('upstream-markdown-task-list.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][53]['name']);
        $t->same('80ead08088210467f4ce4ab35800a2be9e47b76a5c63fb92c3692ce3b49728df', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][53]['sha256']);
        $t->same(846, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][53]['bytes']);
        $t->same('upstream-markdown-unbalanced-brackets.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][54]['name']);
        $t->same('9179740322c07d3717a32cb023a504dafaf164a6f39e2b1cc2fd1f3d11b7e5d8', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][54]['sha256']);
        $t->same(34, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][54]['bytes']);
        $t->same('upstream-markdown-yaml-metadata.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][55]['name']);
        $t->same('7ef515f951844ff049c5f6a4c98f42094843aa311061f59eb20d2be6bca6d7b2', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][55]['sha256']);
        $t->same(75, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][55]['bytes']);
        $t->same('upstream-markdown-z-commonmark-x-grid-table-default.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][56]['name']);
        $t->same('566596459e8d3e3ab0ae5842fecc54764cad72c34c81a104ca9df16451e5143d', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][56]['sha256']);
        $t->same(389, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][56]['bytes']);
        $t->same('upstream-markdown-z-fancy-list-markers.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][57]['name']);
        $t->same('d355209d6e62f20b6c85859cac1100dae074c28c12fb7257e5760395a205fd4e', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][57]['sha256']);
        $t->same(374, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][57]['bytes']);
        $t->same('upstream-markdown-z-phpextra-profile.native', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][58]['name']);
        $t->same('be3c3413188c1a207f164bf91440fe7905e791190969231477e6f0aa9e062af4', $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][58]['sha256']);
        $t->same(404, $evidence['nativeExpectationEvidence']['checkedInNativeFixtures'][58]['bytes']);
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
        $t->same('upstream-markdown-raw-latex-bare-begin.md', $evidence['checkedInFixtures'][41]['name']);
        $t->same('f4aa0601ed6885d2a2bd06e9502564322ba0ee4e687501995fa00c1481f98d8a', $evidence['checkedInFixtures'][41]['checkedInFile']['sha256']);
        $t->same(7, $evidence['checkedInFixtures'][41]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawLatexBareBeginFixtureCompletionTest.php', $evidence['checkedInFixtures'][41]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawLatexBareBeginFixtureCompletionTest.php', $evidence['checkedInFixtures'][41]['localTestReferences'], true));
        $t->same('upstream-markdown-figure-latex-placement.md', $evidence['checkedInFixtures'][42]['name']);
        $t->same('3840aacf3395bbee84846e39c378749f32b386d09dc3bfd02348c524577dcb56', $evidence['checkedInFixtures'][42]['checkedInFile']['sha256']);
        $t->same(59, $evidence['checkedInFixtures'][42]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFigureLatexPlacementFixtureCompletionTest.php', $evidence['checkedInFixtures'][42]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFigureLatexPlacementFixtureCompletionTest.php', $evidence['checkedInFixtures'][42]['localTestReferences'], true));
        $t->same('upstream-markdown-raw-email-address.md', $evidence['checkedInFixtures'][43]['name']);
        $t->same('d75b7a8bd91fde01fc8e7aea25c1f124c0a70af523af13330b641757655788ec', $evidence['checkedInFixtures'][43]['checkedInFile']['sha256']);
        $t->same(10, $evidence['checkedInFixtures'][43]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawEmailAddressFixtureCompletionTest.php', $evidence['checkedInFixtures'][43]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawEmailAddressFixtureCompletionTest.php', $evidence['checkedInFixtures'][43]['localTestReferences'], true));
        $t->same('upstream-markdown-footnote-continuation-boundaries.md', $evidence['checkedInFixtures'][44]['name']);
        $t->same('bcd100bebcaa3c2d7e1e51df1a3e72cebbbc93760b6d903039ee149d0153640f', $evidence['checkedInFixtures'][44]['checkedInFile']['sha256']);
        $t->same(78, $evidence['checkedInFixtures'][44]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFootnoteContinuationBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][44]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFootnoteContinuationBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][44]['localTestReferences'], true));
        $t->same('upstream-markdown-heading-boundaries.md', $evidence['checkedInFixtures'][45]['name']);
        $t->same('6497aa032094a74bfdb6cc714e4f922dfc3a29c14b1196bb84337f201caf1f52', $evidence['checkedInFixtures'][45]['checkedInFile']['sha256']);
        $t->same(44, $evidence['checkedInFixtures'][45]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderHeadingBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][45]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderHeadingBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][45]['localTestReferences'], true));
        $t->same('upstream-markdown-raw-html-invalid-comment.md', $evidence['checkedInFixtures'][46]['name']);
        $t->same('d2e5f74952fd26fd316d646bc360d421b533233e05620ba08afe784f4c17cafa', $evidence['checkedInFixtures'][46]['checkedInFile']['sha256']);
        $t->same(23, $evidence['checkedInFixtures'][46]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawHtmlInvalidCommentFixtureCompletionTest.php', $evidence['checkedInFixtures'][46]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawHtmlInvalidCommentFixtureCompletionTest.php', $evidence['checkedInFixtures'][46]['localTestReferences'], true));
        $t->same('upstream-markdown-raw-html-nesting.md', $evidence['checkedInFixtures'][47]['name']);
        $t->same('0e02bd68029985d4aa7eb46ecd54335afa80af300626cf32fbe23756dc764f7b', $evidence['checkedInFixtures'][47]['checkedInFile']['sha256']);
        $t->same(16, $evidence['checkedInFixtures'][47]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawHtmlNestingFixtureCompletionTest.php', $evidence['checkedInFixtures'][47]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawHtmlNestingFixtureCompletionTest.php', $evidence['checkedInFixtures'][47]['localTestReferences'], true));
        $t->same('upstream-markdown-yaml-metadata.md', $evidence['checkedInFixtures'][48]['name']);
        $t->same('5f69d57ef44116f63721edde6e0f164d3388f692e0fb9359bdc8ea35261e3376', $evidence['checkedInFixtures'][48]['checkedInFile']['sha256']);
        $t->same(142, $evidence['checkedInFixtures'][48]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderYamlMetadataFixtureCompletionTest.php', $evidence['checkedInFixtures'][48]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderYamlMetadataFixtureCompletionTest.php', $evidence['checkedInFixtures'][48]['localTestReferences'], true));
        $t->same('upstream-markdown-definition-list-tight-bodies.md', $evidence['checkedInFixtures'][49]['name']);
        $t->same('58eb007d7f3dac48da8c992622e5a29defd68c7a84cdabb29b81ac4e218df924', $evidence['checkedInFixtures'][49]['checkedInFile']['sha256']);
        $t->same(62, $evidence['checkedInFixtures'][49]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php', $evidence['checkedInFixtures'][49]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php', $evidence['checkedInFixtures'][49]['localTestReferences'], true));
        $t->same('upstream-markdown-lhs-inverse-bird-html.md', $evidence['checkedInFixtures'][50]['name']);
        $t->same('f08f6db28a623c0f60dbe069e68567e38f7ecbf71367f01eebfa52c2d6735ce0', $evidence['checkedInFixtures'][50]['checkedInFile']['sha256']);
        $t->same(16, $evidence['checkedInFixtures'][50]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderLiterateHaskellFixtureCompletionTest.php', $evidence['checkedInFixtures'][50]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderLiterateHaskellFixtureCompletionTest.php', $evidence['checkedInFixtures'][50]['localTestReferences'], true));
        $t->same('upstream-markdown-alerts.md', $evidence['checkedInFixtures'][51]['name']);
        $t->same('d4f826212c99ace92b25f414db142d565fa7b737ff6c0cbabb4010d5cf1f7b29', $evidence['checkedInFixtures'][51]['checkedInFile']['sha256']);
        $t->same(107, $evidence['checkedInFixtures'][51]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAlertProfileCompletionTest.php', $evidence['checkedInFixtures'][51]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAlertProfileCompletionTest.php', $evidence['checkedInFixtures'][51]['localTestReferences'], true));
        $t->same('upstream-markdown-strict-compact-heading.md', $evidence['checkedInFixtures'][52]['name']);
        $t->same('7631fb35c6f86b29590e5a339c7c14abc67cb65adc8557efffef6f69485eb0b4', $evidence['checkedInFixtures'][52]['checkedInFile']['sha256']);
        $t->same(4, $evidence['checkedInFixtures'][52]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderStrictCompactHeadingFixtureCompletionTest.php', $evidence['checkedInFixtures'][52]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderStrictCompactHeadingFixtureCompletionTest.php', $evidence['checkedInFixtures'][52]['localTestReferences'], true));
        $t->same('upstream-markdown-z-commonmark-x-grid-table-default.md', $evidence['checkedInFixtures'][53]['name']);
        $t->same('412a732f5c23e980d34d1be6b014030f06ff723439ba06740804fe1d52a946a1', $evidence['checkedInFixtures'][53]['checkedInFile']['sha256']);
        $t->same(50, $evidence['checkedInFixtures'][53]['checkedInFile']['bytes']);
        $t->same('commonmark_x grid_tables disabled by default, pipe_tables still enabled', $evidence['checkedInFixtures'][53]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderTableProfileSurgeTest.php', $evidence['checkedInFixtures'][53]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderTableProfileSurgeTest.php', $evidence['checkedInFixtures'][53]['localTestReferences'], true));
        $t->same('upstream-markdown-autolink-attributes.md', $evidence['checkedInFixtures'][54]['name']);
        $t->same('1e53b4ffdeab43731a3909f53ffd8d4f44d2560d5355b7e490ef2f19376c2052', $evidence['checkedInFixtures'][54]['checkedInFile']['sha256']);
        $t->same(64, $evidence['checkedInFixtures'][54]['checkedInFile']['bytes']);
        $t->same('markdown angle autolink raw_attribute boundary', $evidence['checkedInFixtures'][54]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAutolinkAttributeFixtureCompletionTest.php', $evidence['checkedInFixtures'][54]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAutolinkAttributeFixtureCompletionTest.php', $evidence['checkedInFixtures'][54]['localTestReferences'], true));
        $t->same('upstream-markdown-z-fancy-list-markers.md', $evidence['checkedInFixtures'][55]['name']);
        $t->same('c3d8db151d6eed0603f2e3774c031b405a5ca8b64d69574f3feb590df1e62d21', $evidence['checkedInFixtures'][55]['checkedInFile']['sha256']);
        $t->same(71, $evidence['checkedInFixtures'][55]['checkedInFile']['bytes']);
        $t->same('markdown+fancy_lists upper-alpha, upper-roman, and parenthesized decimal ordered markers', $evidence['checkedInFixtures'][55]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFancyListFixtureCompletionTest.php', $evidence['checkedInFixtures'][55]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFancyListFixtureCompletionTest.php', $evidence['checkedInFixtures'][55]['localTestReferences'], true));
        $t->same('upstream-markdown-inline-math.md', $evidence['checkedInFixtures'][56]['name']);
        $t->same('364f852f91e3d11943ffa83ae6cd717f3b9ae38a2c61100fe135e95d4bf9180a', $evidence['checkedInFixtures'][56]['checkedInFile']['sha256']);
        $t->same(36, $evidence['checkedInFixtures'][56]['checkedInFile']['bytes']);
        $t->same('markdown tex_math_dollars inline math', $evidence['checkedInFixtures'][56]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderInlineMathFixtureCompletionTest.php', $evidence['checkedInFixtures'][56]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderInlineMathFixtureCompletionTest.php', $evidence['checkedInFixtures'][56]['localTestReferences'], true));
        $t->same('upstream-markdown-ascii-identifiers.md', $evidence['checkedInFixtures'][57]['name']);
        $t->same('37abcb0679639cce00173e8737b95b0c76da7a3f2b6bc3790bccbd2790abf232', $evidence['checkedInFixtures'][57]['checkedInFile']['sha256']);
        $t->same(156, $evidence['checkedInFixtures'][57]['checkedInFile']['bytes']);
        $t->same('markdown+ascii_identifiers', $evidence['checkedInFixtures'][57]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAsciiIdentifierProfileCompletionTest.php', $evidence['checkedInFixtures'][57]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][57]['localTestReferences'], true));
        $t->same('upstream-markdown-z-phpextra-profile.md', $evidence['checkedInFixtures'][58]['name']);
        $t->same('83e7b30e00869c6ef685979df5fa075d3e4bb2bc988d0e615ea584b6374f5347', $evidence['checkedInFixtures'][58]['checkedInFile']['sha256']);
        $t->same(120, $evidence['checkedInFixtures'][58]['checkedInFile']['bytes']);
        $t->same('markdown_phpextra header_attributes/link_attributes/definition_lists/footnotes defaults', $evidence['checkedInFixtures'][58]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderPhpExtraProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][58]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][58]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderPhpExtraProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][58]['localTestReferences'], true));
        $t->same('valid-checked-in-current-markdown-reader-evidence', $evidence['validation']['status']);
        $t->same([], $evidence['validation']['issues']);
        $t->true(in_array('each selected fixture has at least one local PHP test reference', $evidence['claimBoundaries']['doesAssert'], true));
        $t->true(in_array('the fifty-nine selected checked-in Markdown native expectation snapshots match the expected deterministic manifest hash', $evidence['claimBoundaries']['doesAssert'], true));
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
            $t->same(59, $report['denominator']['selectedFixtureCount']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
            $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredNativeMappedParity($report, 59));
            $t->same(true, MarkdownUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(true, MarkdownUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->same(true, MarkdownUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->true(in_array('the future upstream runner command plan targets test:test-pandoc Readers/Markdown at the pinned upstream commit without execution', $report['claimBoundaries']['doesAssert'], true));
            $t->true(in_array('full upstream Tests.Readers.Markdown runner parity', $report['claimBoundaries']['doesNotAssert'], true));
            $t->true(in_array('native AST parity for selected Markdown fixtures without same-basename .native expectations', $report['claimBoundaries']['doesNotAssert'], true));
        } finally {
            $removeTree($root);
        }
    },

    'validates supplied markdown reader upstream runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeMarkdownEvidenceTree, $writeRunnerTranscripts): void {
        $root = $makeTempDir();
        try {
            $writeMarkdownEvidenceTree($root);
            $baseReport = (new MarkdownUpstreamReaderEvidence($root, $root))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $testNames = array_map(
                static fn (array $fixture): string => $fixture['name'],
                $baseReport['denominator']['selectedFixtures']
            );
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc Markdown reader suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => MarkdownUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT,
                ],
                'target' => $runnerPlan['target'],
                'command' => $runnerPlan['futureCommands'][2],
                'exitCode' => 0,
                'testCount' => count($testNames),
                'passedCount' => count($testNames),
                'failedCount' => 0,
                'skippedCount' => 0,
                'testNames' => $testNames,
                'transcriptPaths' => $runnerPlan['requiredTranscripts'],
                'transcripts' => $transcripts,
            ];
            $validPayload = $payload;
            $writeFile($root, 'result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $artifactPath = $root . '/result.json';
            $report = (new MarkdownUpstreamReaderEvidence($root, $root, $artifactPath))->report();
            $text = MarkdownUpstreamReaderEvidence::formatTextReport($report);

            $t->same('completed', $report['runnerEvidence']['status']);
            $t->same(true, $report['runnerEvidence']['executed']);
            $t->same('runner-result-artifact-validated', $report['runnerEvidence']['commandPlanStatus']);
            $t->same('valid-upstream-markdown-reader-runner-result-artifact', $report['runnerEvidence']['validation']['status']);
            $t->same([], $report['runnerEvidence']['validation']['issues']);
            $t->same('upstream-markdown-reader-runner-result-artifact', $report['runnerEvidence']['resultArtifact']['kind']);
            $t->same(true, $report['runnerEvidence']['resultArtifact']['present']);
            $t->same(hash_file('sha256', $artifactPath), $report['runnerEvidence']['resultArtifact']['sha256']);
            $t->same(filesize($artifactPath), $report['runnerEvidence']['resultArtifact']['bytes']);
            $t->same(MarkdownUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $report['runnerEvidence']['upstreamBinding']['observedCommit']);
            $t->same($runnerPlan['target'], $report['runnerEvidence']['target']);
            $t->same($runnerPlan['futureCommands'][2], $report['runnerEvidence']['command']);
            $t->same($testNames, $report['runnerEvidence']['observed']['testNames']);
            $t->same($runnerPlan['requiredTranscripts'], $report['runnerEvidence']['observed']['transcriptPaths']);
            $t->same($transcripts, $report['runnerEvidence']['observed']['transcripts']);
            $t->same($transcripts, $report['runnerEvidence']['expected']['transcripts']);
            $t->same('upstream-markdown-reader-runner-transcript', $report['runnerEvidence']['transcripts'][0]['kind']);
            $t->same(true, $report['runnerEvidence']['transcripts'][0]['present']);
            $t->same(true, MarkdownUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($report));
            $t->same(false, MarkdownUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(false, MarkdownUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->contains('Runner status: completed', $text);
            $t->contains('Runner plan: runner-result-artifact-validated', $text);
            $t->contains('Runner result artifact: valid-upstream-markdown-reader-runner-result-artifact', $text);
            $t->contains('Supplied upstream Haskell/Cabal runner result artifact is validated', $text);

            $payload = $validPayload;
            $payload['failedCount'] = 1;
            $payload['exitCode'] = 1;
            $writeFile($root, 'bad-result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badReport = (new MarkdownUpstreamReaderEvidence($root, $root, $root . '/bad-result.json'))->report();

            $t->same('invalid', $badReport['runnerEvidence']['status']);
            $t->same('invalid-upstream-markdown-reader-runner-result-artifact', $badReport['runnerEvidence']['validation']['status']);
            $t->true(in_array('runner-result-exit-code-nonzero', $badReport['runnerEvidence']['validation']['issues'], true));
            $t->true(in_array('runner-result-counts-mismatch', $badReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, MarkdownUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($badReport));

            $badTranscriptPayload = $validPayload;
            $badTranscriptPayload['transcripts'][0]['bytes'] = 0;
            $writeFile($root, 'bad-transcript-result.json', json_encode($badTranscriptPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badTranscriptReport = (new MarkdownUpstreamReaderEvidence($root, $root, $root . '/bad-transcript-result.json'))->report();

            $t->same('invalid', $badTranscriptReport['runnerEvidence']['status']);
            $t->true(in_array('runner-result-transcript-bytes-mismatch', $badTranscriptReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, MarkdownUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($badTranscriptReport));
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
            . ' --require-selected-fixture-count=59'
            . ' --require-static-current-evidence'
            . ' --require-native-mapped-parity=59'
            . ' --require-runner-not-run'
            . ' --require-runner-plan';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(MarkdownUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $decoded['status']);
        $t->same(59, $decoded['staticCurrentEvidence']['readerDenominator']['selectedFixtureCount']);
        $t->same('valid-checked-in-current-markdown-reader-evidence', $decoded['staticCurrentEvidence']['validation']['status']);
        $t->same('valid-checked-in-current-markdown-native-expectation-evidence', $decoded['staticCurrentEvidence']['nativeExpectationEvidence']['validation']['status']);
        $t->same(MarkdownUpstreamReaderEvidence::EXPECTED_NATIVE_EXPECTATION_MANIFEST_SHA256, $decoded['staticCurrentEvidence']['nativeExpectationEvidence']['manifestSha256']);
        $t->same(59, $decoded['nativeAstEvidence']['normalizedAstMatchCount']);
        $t->same(0, $decoded['nativeAstEvidence']['normalizedAstMismatchCount']);
        $t->same('not-run', $decoded['runnerEvidence']['status']);
        $t->same('planned-not-run', $decoded['runnerEvidence']['commandPlanStatus']);
        $t->same('test:test-pandoc', $decoded['runnerEvidence']['target']['testSuite']);
        $t->same(['Readers', 'Markdown'], $decoded['runnerEvidence']['target']['tastyGroupPath']);
        $t->true(in_array('complete Markdown dialect parity across every Pandoc extension profile', $decoded['claimBoundaries']['doesNotAssert'], true));

        $failingCommand = str_replace('--require-selected-fixture-count=59', '--require-selected-fixture-count=60', $command) . ' 2>/dev/null';
        $failingOutput = [];
        $failingExitCode = 0;
        exec($failingCommand, $failingOutput, $failingExitCode);

        $t->same(1, $failingExitCode);

        $failingNativeCommand = str_replace('--require-native-mapped-parity=59', '--require-native-mapped-parity=60', $command) . ' 2>/dev/null';
        $failingNativeOutput = [];
        $failingNativeExitCode = 0;
        exec($failingNativeCommand, $failingNativeOutput, $failingNativeExitCode);

        $t->same(1, $failingNativeExitCode);
    },

    'cli gates supplied markdown reader upstream runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeMarkdownEvidenceTree, $writeRunnerTranscripts): void {
        $root = $makeTempDir();
        try {
            $writeMarkdownEvidenceTree($root);
            $baseReport = (new MarkdownUpstreamReaderEvidence($root, $root))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $testNames = array_map(
                static fn (array $fixture): string => $fixture['name'],
                $baseReport['denominator']['selectedFixtures']
            );
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc Markdown reader suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => MarkdownUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT,
                ],
                'target' => $runnerPlan['target'],
                'command' => $runnerPlan['futureCommands'][2],
                'exitCode' => 0,
                'testCount' => count($testNames),
                'passedCount' => count($testNames),
                'failedCount' => 0,
                'skippedCount' => 0,
                'testNames' => $testNames,
                'transcriptPaths' => $runnerPlan['requiredTranscripts'],
                'transcripts' => $transcripts,
            ];
            $validPayload = $payload;
            $writeFile($root, 'result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-markdown-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg($root)
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --runner-result-artifact=' . escapeshellarg($root . '/result.json')
                . ' --json'
                . ' --require-runner-result-artifact';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same('completed', $decoded['runnerEvidence']['status']);
            $t->same('valid-upstream-markdown-reader-runner-result-artifact', $decoded['runnerEvidence']['validation']['status']);
            $t->same(true, MarkdownUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($decoded));

            $payload = $validPayload;
            $payload['target']['tastyPattern'] = '$2 == "Readers" && $3 == "HTML"';
            $writeFile($root, 'bad-result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $failingCommand = str_replace('result.json', 'bad-result.json', $command) . ' 2>/dev/null';
            $failingOutput = [];
            $failingExitCode = 0;
            exec($failingCommand, $failingOutput, $failingExitCode);

            $t->same(1, $failingExitCode);
        } finally {
            $removeTree($root);
        }
    },

    'workflow gates current markdown fixture denominator' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $workflow = file_get_contents($repoRoot . '/.github/workflows/pandoc-markdown.yml');
        if ($workflow === false) {
            throw new RuntimeException('Unable to read pandoc-markdown workflow');
        }

        $t->contains('--require-selected-fixture-count=48', $workflow);
        $t->contains('--require-native-mapped-parity=48', $workflow);
        $t->contains('--require-mapped-parity=48', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownUpstreamReaderEvidenceTest.php', $workflow);
        $t->contains('/test/Tests/Readers/Markdown.hs', $workflow);
        $t->contains('/src/Text/Pandoc/Readers/Markdown.hs', $workflow);
    },
];
