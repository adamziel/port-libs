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

$writeRunnerTranscripts = static function (string $root, array $paths, array $testNames = []) use ($writeFile): array {
    $records = [];
    foreach (array_values($paths) as $index => $path) {
        $contents = 'markdown runner transcript ' . (string) ($index + 1) . "\n" . $path . "\n";
        if (str_ends_with($path, '-targeted-list-tests.txt')) {
            $contents .= implode("\n", $testNames) . "\n";
        }
        if (str_ends_with($path, '-targeted-run.txt')) {
            $contents .= implode("\n", array_map(static fn (string $name): string => $name . ': OK', $testNames)) . "\n";
        }
        $contents .= "exitCode: 0\n";
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
        $t->same(124, $report['staticCurrentEvidence']['nativeExpectationEvidence']['presentFixtureCount']);
        $t->same(MarkdownUpstreamReaderEvidence::EXPECTED_NATIVE_EXPECTATION_MANIFEST_SHA256, $report['staticCurrentEvidence']['nativeExpectationEvidence']['manifestSha256']);
        $t->same(124, $report['nativeAstEvidence']['totalPairCount']);
        $t->same(124, $report['nativeAstEvidence']['normalizedAstMatchCount']);
        $t->same(0, $report['nativeAstEvidence']['unpairedMarkdownFixtureCount']);
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredSelectedFixtureCount($report, 124));
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredNativeMappedParity($report, 124));
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
        $t->same(true, MarkdownUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
        $t->same(false, MarkdownUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->same('planned-not-run', $report['runnerEvidence']['commandPlanStatus']);
        $t->same(['Readers', 'Markdown'], $report['runnerEvidence']['target']['tastyGroupPath']);
        $t->same('$2 == "Readers" && $3 == "Markdown"', $report['runnerEvidence']['target']['tastyPattern']);
        $t->true(in_array('.port-libs/pandoc-runner/logs/markdown-targeted-run.txt', $report['runnerEvidence']['requiredTranscripts'], true));
        $t->true(in_array('.port-libs/pandoc-runner/artifacts/markdown-targeted-run/result.json', $report['runnerEvidence']['requiredArtifacts'], true));
        $t->contains('Pandoc Markdown reader evidence', $text);
        $t->contains('Selected checked-in fixtures: 124', $text);
        $t->contains('Static current evidence: valid-checked-in-current-markdown-reader-evidence checkedInFixtures=124 nativeExpectations=124 nativeManifest=valid-checked-in-current-markdown-native-expectation-evidence', $text);
        $t->contains('Native AST mapped parity: 124/124 status=normalized-ast-equality-observed-not-runner-parity', $text);
        $t->contains('Runner plan: planned-not-run', $text);
    },

    'reports checked-in current markdown fixture static evidence' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $evidence = MarkdownUpstreamReaderEvidence::checkedInCurrentEvidence($repoRoot);

        $t->same('static-checked-in-current-upstream-markdown-reader-fixture-evidence', $evidence['kind']);
        $t->same(MarkdownUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $evidence['upstream']['commit']);
        $t->same(124, $evidence['readerDenominator']['selectedFixtureCount']);
        $t->same('selected checked-in upstream-derived Markdown reader fixtures', $evidence['readerDenominator']['fixtureScope']);
        $t->same(['selected-upstream-markdown-reader-case', 'upstream-command-fixture'], $evidence['readerDenominator']['sourceKinds']);
        $t->same(124, $evidence['checkedInFixtureCount']);
        $t->same('static-checked-in-current-markdown-native-expectation-evidence', $evidence['nativeExpectationEvidence']['kind']);
        $t->same(124, $evidence['nativeExpectationEvidence']['expectedFixtureCount']);
        $t->same(124, $evidence['nativeExpectationEvidence']['fixtureCount']);
        $t->same(124, $evidence['nativeExpectationEvidence']['presentFixtureCount']);
        $t->same(MarkdownUpstreamReaderEvidence::EXPECTED_NATIVE_EXPECTATION_MANIFEST_SHA256, $evidence['nativeExpectationEvidence']['expectedManifestSha256']);
        $t->same(MarkdownUpstreamReaderEvidence::EXPECTED_NATIVE_EXPECTATION_MANIFEST_SHA256, $evidence['nativeExpectationEvidence']['manifestSha256']);
        $t->same('valid-checked-in-current-markdown-native-expectation-evidence', $evidence['nativeExpectationEvidence']['validation']['status']);
        $t->same([], $evidence['nativeExpectationEvidence']['validation']['issues']);
        $nativeFixturesByName = [];
        foreach ($evidence['nativeExpectationEvidence']['checkedInNativeFixtures'] as $nativeFixture) {
            $nativeFixturesByName[$nativeFixture['name']] = $nativeFixture;
        }
        $t->same(
            'b655637d31601bc6d91a01948f12f017f20591d081df4212723c2ff070270eee',
            $nativeFixturesByName['upstream-markdown-inline-code-list-marker-generated-boundaries.native']['sha256'] ?? null
        );
        $t->same(
            4081,
            $nativeFixturesByName['upstream-markdown-inline-code-list-marker-generated-boundaries.native']['bytes'] ?? null
        );
        $t->same(
            '9306fdd26a6dc941c3a7d376662ceb61ea5e368bef4923f307a64e7e45226512',
            $nativeFixturesByName['upstream-markdown-smart-french-apostrophe.native']['sha256'] ?? null
        );
        $t->same(
            363,
            $nativeFixturesByName['upstream-markdown-smart-french-apostrophe.native']['bytes'] ?? null
        );
        $t->same(
            '83077cf3814e38ab905c60595a720c6d63a7aa7309e8f9d83c3f6c5bfe8c2893',
            $nativeFixturesByName['upstream-command-7080-mmd-reference-image-attributes.native']['sha256'] ?? null
        );
        $t->same(
            164,
            $nativeFixturesByName['upstream-command-7080-mmd-reference-image-attributes.native']['bytes'] ?? null
        );
        $t->same(
            '34b46cf8f763cc2f46122f4a8e594888be5c8166a9d415432297fa35b43aa9cb',
            $nativeFixturesByName['upstream-markdown-citation-link-following.native']['sha256'] ?? null
        );
        $t->same(
            2264,
            $nativeFixturesByName['upstream-markdown-citation-link-following.native']['bytes'] ?? null
        );
        $t->same(
            '3c7cce38d9e94bd2f4b3527c489ad199fe614f323b21e6a83eb61bf27da30293',
            $nativeFixturesByName['upstream-markdown-reader-more-raw-tex-environments.native']['sha256'] ?? null
        );
        $t->same(
            565,
            $nativeFixturesByName['upstream-markdown-reader-more-raw-tex-environments.native']['bytes'] ?? null
        );
        $t->same(
            '70c3167c36fad88a64a45caa85ecc56552ecfc2dc560dfccbf18269b26bda28a',
            $nativeFixturesByName['upstream-markdown-reader-more-code-spans.native']['sha256'] ?? null
        );
        $t->same(
            265,
            $nativeFixturesByName['upstream-markdown-reader-more-code-spans.native']['bytes'] ?? null
        );
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
        $inlineCodeListGeneratedFixture = null;
        $markdownInHtmlBlocksFixture = null;
        $markdownAttributeFixture = null;
        $smartFrenchApostropheFixture = null;
        $mmdReferenceImageAttributeFixture = null;
        $rawTexEnvironmentsFixture = null;
        $codeSpanFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-inline-code-list-marker-generated-boundaries.md') {
                $inlineCodeListGeneratedFixture = $checkedInFixture;
            }
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-in-html-blocks-profile.md') {
                $markdownInHtmlBlocksFixture = $checkedInFixture;
            }
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-attribute-profile.md') {
                $markdownAttributeFixture = $checkedInFixture;
            }
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-smart-french-apostrophe.md') {
                $smartFrenchApostropheFixture = $checkedInFixture;
            }
            if (($checkedInFixture['name'] ?? null) === 'upstream-command-7080-mmd-reference-image-attributes.md') {
                $mmdReferenceImageAttributeFixture = $checkedInFixture;
            }
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-reader-more-raw-tex-environments.md') {
                $rawTexEnvironmentsFixture = $checkedInFixture;
            }
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-reader-more-code-spans.md') {
                $codeSpanFixture = $checkedInFixture;
            }
        }
        $t->true(is_array($inlineCodeListGeneratedFixture));
        $inlineCodeListGeneratedFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('8556aa8110eadfdde51dbf8e8e2afb293acf254118b27ef3b099453d0ee2f74f', $inlineCodeListGeneratedFixture['checkedInFile']['sha256']);
        $t->same(681, $inlineCodeListGeneratedFixture['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderInlineCodeListMarkerCompletionTest.php', $inlineCodeListGeneratedFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderInlineCodeListMarkerCompletionTest.php', $inlineCodeListGeneratedFixture['localTestReferences'], true));
        $t->true(is_array($markdownInHtmlBlocksFixture));
        $markdownInHtmlBlocksFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('eea6a5f1a4b9fef9e314a6c18a1edee0bbc2cfc66b01186d61414bc7248d938d', $markdownInHtmlBlocksFixture['checkedInFile']['sha256']);
        $t->same(108, $markdownInHtmlBlocksFixture['checkedInFile']['bytes']);
        $t->same('markdown+markdown_in_html_blocks container block with nested Markdown content', $markdownInHtmlBlocksFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMarkdownInHtmlBlocksFixtureCompletionTest.php', $markdownInHtmlBlocksFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMarkdownInHtmlBlocksFixtureCompletionTest.php', $markdownInHtmlBlocksFixture['localTestReferences'], true));
        $t->true(is_array($markdownAttributeFixture));
        $markdownAttributeFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('dfe0b021428a827a66dcb79aab83ba4ecdfbdebc6aebdd5f28aab915911057b9', $markdownAttributeFixture['checkedInFile']['sha256']);
        $t->same(121, $markdownAttributeFixture['checkedInFile']['bytes']);
        $t->same('markdown_phpextra markdown_attribute container block with nested Markdown content', $markdownAttributeFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMarkdownInHtmlBlocksFixtureCompletionTest.php', $markdownAttributeFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMarkdownInHtmlBlocksFixtureCompletionTest.php', $markdownAttributeFixture['localTestReferences'], true));
        $t->true(is_array($smartFrenchApostropheFixture));
        $smartFrenchApostropheFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('f1a577d502c60ea42b20146a305234d7dbeface9f317244dd448e4561f3af3f5', $smartFrenchApostropheFixture['checkedInFile']['sha256']);
        $t->same(76, $smartFrenchApostropheFixture['checkedInFile']['bytes']);
        $t->same('markdown+smart French apostrophe and guillemet boundary', $smartFrenchApostropheFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderSmartPunctuationFixtureCompletionTest.php', $smartFrenchApostropheFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $smartFrenchApostropheFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderSmartPunctuationFixtureCompletionTest.php', $smartFrenchApostropheFixture['localTestReferences'], true));
        $t->true(is_array($mmdReferenceImageAttributeFixture));
        $mmdReferenceImageAttributeFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-command-7080-mmd-reference-image-attributes.md', $mmdReferenceImageAttributeFixture['name']);
        $t->same('c9b0bb4fd75042f55ff3287c0519c9df48a6017a116d42403a7a99840a761ca6', $mmdReferenceImageAttributeFixture['checkedInFile']['sha256']);
        $t->same(56, $mmdReferenceImageAttributeFixture['checkedInFile']['bytes']);
        $t->same('markdown_mmd reference image attributes', $mmdReferenceImageAttributeFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMmdReferenceImageAttributeFixtureCompletionTest.php', $mmdReferenceImageAttributeFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $mmdReferenceImageAttributeFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMmdReferenceImageAttributeFixtureCompletionTest.php', $mmdReferenceImageAttributeFixture['localTestReferences'], true));
        $t->true(is_array($rawTexEnvironmentsFixture));
        $rawTexEnvironmentsFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-reader-more-raw-tex-environments.md', $rawTexEnvironmentsFixture['name']);
        $t->same('ae8e7928a750fece03f479ade0e1d6d7998fd804c186b9d0dcf93e503adbac86', $rawTexEnvironmentsFixture['checkedInFile']['sha256']);
        $t->same(328, $rawTexEnvironmentsFixture['checkedInFile']['bytes']);
        $t->same('markdown raw_tex raw ConTeXt/LaTeX environments', $rawTexEnvironmentsFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $rawTexEnvironmentsFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $rawTexEnvironmentsFixture['localTestReferences'], true));
        $t->true(is_array($codeSpanFixture));
        $codeSpanFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-reader-more-code-spans.md', $codeSpanFixture['name']);
        $t->same('8dc499f98165df54885e4909cc5808645174597006f8c1d2d95b6f6e16850809', $codeSpanFixture['checkedInFile']['sha256']);
        $t->same(65, $codeSpanFixture['checkedInFile']['bytes']);
        $t->same('markdown code span multiline whitespace and delimiter boundary', $codeSpanFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderCodeSpanFixtureCompletionTest.php', $codeSpanFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $codeSpanFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderCodeSpanFixtureCompletionTest.php', $codeSpanFixture['localTestReferences'], true));
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
        $t->same('upstream-markdown-smart-inline-note-quotes.md', $evidence['checkedInFixtures'][28]['name']);
        $t->same('7ac90d3c784c265f70c9cb1593a06afe196acaec58e62660144c8657213164e2', $evidence['checkedInFixtures'][28]['checkedInFile']['sha256']);
        $t->same(14, $evidence['checkedInFixtures'][28]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderSmartPunctuationFixtureCompletionTest.php', $evidence['checkedInFixtures'][28]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderSmartPunctuationFixtureCompletionTest.php', $evidence['checkedInFixtures'][28]['localTestReferences'], true));
        $t->same('upstream-markdown-pipe-table-escaped-cell.md', $evidence['checkedInFixtures'][29]['name']);
        $t->same('11abcb5c5ff0e2815bc3abcfabbc5fcd38e19fb2be4ac32104b3e4c554d13516', $evidence['checkedInFixtures'][29]['checkedInFile']['sha256']);
        $t->same(76, $evidence['checkedInFixtures'][29]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderPipeTableFixtureCompletionTest.php', $evidence['checkedInFixtures'][29]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderPipeTableFixtureCompletionTest.php', $evidence['checkedInFixtures'][29]['localTestReferences'], true));
        $t->same('upstream-markdown-fenced-div.md', $evidence['checkedInFixtures'][30]['name']);
        $t->same('55f2e8b78f0447326a3cd1574f8622a91d1f3fa8e595b83204eb70adb4e089c2', $evidence['checkedInFixtures'][30]['checkedInFile']['sha256']);
        $t->same(106, $evidence['checkedInFixtures'][30]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFencedDivFixtureCompletionTest.php', $evidence['checkedInFixtures'][30]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFencedDivFixtureCompletionTest.php', $evidence['checkedInFixtures'][30]['localTestReferences'], true));
        $t->same('upstream-markdown-header-attributes.md', $evidence['checkedInFixtures'][31]['name']);
        $t->same('69a74cf0b29a2821c37d2c8b08791c168691de424cf70bb9b8e5802ea4fa0520', $evidence['checkedInFixtures'][31]['checkedInFile']['sha256']);
        $t->same(80, $evidence['checkedInFixtures'][31]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderHeaderAttributeFixtureTest.php', $evidence['checkedInFixtures'][31]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderHeaderAttributeFixtureTest.php', $evidence['checkedInFixtures'][31]['localTestReferences'], true));
        $t->same('upstream-markdown-numbered-examples.md', $evidence['checkedInFixtures'][32]['name']);
        $t->same('8b249e3e2d1a4c4995eb28150c84bb4c77166d9dfb97de0b448e90388e7e8fc9', $evidence['checkedInFixtures'][32]['checkedInFile']['sha256']);
        $t->same(39, $evidence['checkedInFixtures'][32]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderNumberedExampleFixtureCompletionTest.php', $evidence['checkedInFixtures'][32]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderNumberedExampleFixtureCompletionTest.php', $evidence['checkedInFixtures'][32]['localTestReferences'], true));
        $t->same('upstream-markdown-mark.md', $evidence['checkedInFixtures'][33]['name']);
        $t->same('b8c09f9af30b7896e4721d9eada0dfb9cf06c29989eff78cb6ae3e8221a2b4f7', $evidence['checkedInFixtures'][33]['checkedInFile']['sha256']);
        $t->same(36, $evidence['checkedInFixtures'][33]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMarkFixtureCompletionTest.php', $evidence['checkedInFixtures'][33]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMarkFixtureCompletionTest.php', $evidence['checkedInFixtures'][33]['localTestReferences'], true));
        $t->same('upstream-markdown-bracketed-spans.md', $evidence['checkedInFixtures'][34]['name']);
        $t->same('04316f1a9913cf1614ae0fcbf3e493a07456fbe5a7d80f9558dda665bf347456', $evidence['checkedInFixtures'][34]['checkedInFile']['sha256']);
        $t->same(147, $evidence['checkedInFixtures'][34]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBracketedSpanFixtureCompletionTest.php', $evidence['checkedInFixtures'][34]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBracketedSpanFixtureCompletionTest.php', $evidence['checkedInFixtures'][34]['localTestReferences'], true));
        $t->same('upstream-markdown-fenced-code-attributes.md', $evidence['checkedInFixtures'][35]['name']);
        $t->same('6f09b188dded819552fc8a2297abafbfd0d158d0d238bcaf325a93ec766d8b30', $evidence['checkedInFixtures'][35]['checkedInFile']['sha256']);
        $t->same(78, $evidence['checkedInFixtures'][35]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFencedCodeAttributeFixtureCompletionTest.php', $evidence['checkedInFixtures'][35]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFencedCodeAttributeFixtureCompletionTest.php', $evidence['checkedInFixtures'][35]['localTestReferences'], true));
        $t->same('upstream-markdown-mmd-short-scripts.md', $evidence['checkedInFixtures'][36]['name']);
        $t->same('51ad1c2f928c09fe555f260e30579499bdea5d1b34941f1760b3c07a787d03d4', $evidence['checkedInFixtures'][36]['checkedInFile']['sha256']);
        $t->same(100, $evidence['checkedInFixtures'][36]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMmdShortScriptFixtureCompletionTest.php', $evidence['checkedInFixtures'][36]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMmdShortScriptFixtureCompletionTest.php', $evidence['checkedInFixtures'][36]['localTestReferences'], true));
        $t->same('upstream-markdown-numeric-character-references.md', $evidence['checkedInFixtures'][37]['name']);
        $t->same('73238d01c18d759c2af0440d38c928593d2511e5d07090487639a50d056df12c', $evidence['checkedInFixtures'][37]['checkedInFile']['sha256']);
        $t->same(18, $evidence['checkedInFixtures'][37]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderNumericCharacterReferenceFixtureCompletionTest.php', $evidence['checkedInFixtures'][37]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderNumericCharacterReferenceFixtureCompletionTest.php', $evidence['checkedInFixtures'][37]['localTestReferences'], true));
        $t->same('upstream-markdown-footnote-definitions.md', $evidence['checkedInFixtures'][38]['name']);
        $t->same('4e11531363fafbdd59e3c1cd99f37e0162340827819b667ecea1b859f5ca5bd4', $evidence['checkedInFixtures'][38]['checkedInFile']['sha256']);
        $t->same(21, $evidence['checkedInFixtures'][38]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFootnoteDefinitionFixtureCompletionTest.php', $evidence['checkedInFixtures'][38]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFootnoteDefinitionFixtureCompletionTest.php', $evidence['checkedInFixtures'][38]['localTestReferences'], true));
        $t->same('upstream-markdown-escaped-line-break.md', $evidence['checkedInFixtures'][39]['name']);
        $t->same('227f9cf35e3cdba7f00821c2a4c1e3dc7914cf5e74ae2c59a9e49aae616d2303', $evidence['checkedInFixtures'][39]['checkedInFile']['sha256']);
        $t->same(12, $evidence['checkedInFixtures'][39]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderEscapedLineBreakFixtureCompletionTest.php', $evidence['checkedInFixtures'][39]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderEscapedLineBreakFixtureCompletionTest.php', $evidence['checkedInFixtures'][39]['localTestReferences'], true));
        $t->same('upstream-markdown-implicit-header-references.md', $evidence['checkedInFixtures'][40]['name']);
        $t->same('0a7eaf250ae086961351c6c684f26aa3b63caf4abe743fa29f2325c6d653f904', $evidence['checkedInFixtures'][40]['checkedInFile']['sha256']);
        $t->same(46, $evidence['checkedInFixtures'][40]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderImplicitHeaderReferenceFixtureCompletionTest.php', $evidence['checkedInFixtures'][40]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderImplicitHeaderReferenceFixtureCompletionTest.php', $evidence['checkedInFixtures'][40]['localTestReferences'], true));
        $t->same('upstream-markdown-emph-strong-boundaries.md', $evidence['checkedInFixtures'][41]['name']);
        $t->same('dacb0085f517373fa21e84028a7433a2315f90fca6eda8500d393a5783b06bf9', $evidence['checkedInFixtures'][41]['checkedInFile']['sha256']);
        $t->same(84, $evidence['checkedInFixtures'][41]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderEmphStrongBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][41]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderEmphStrongBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][41]['localTestReferences'], true));
        $t->same('upstream-markdown-raw-latex-bare-begin.md', $evidence['checkedInFixtures'][42]['name']);
        $t->same('f4aa0601ed6885d2a2bd06e9502564322ba0ee4e687501995fa00c1481f98d8a', $evidence['checkedInFixtures'][42]['checkedInFile']['sha256']);
        $t->same(7, $evidence['checkedInFixtures'][42]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawLatexBareBeginFixtureCompletionTest.php', $evidence['checkedInFixtures'][42]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawLatexBareBeginFixtureCompletionTest.php', $evidence['checkedInFixtures'][42]['localTestReferences'], true));
        $t->same('upstream-markdown-figure-latex-placement.md', $evidence['checkedInFixtures'][43]['name']);
        $t->same('3840aacf3395bbee84846e39c378749f32b386d09dc3bfd02348c524577dcb56', $evidence['checkedInFixtures'][43]['checkedInFile']['sha256']);
        $t->same(59, $evidence['checkedInFixtures'][43]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFigureLatexPlacementFixtureCompletionTest.php', $evidence['checkedInFixtures'][43]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFigureLatexPlacementFixtureCompletionTest.php', $evidence['checkedInFixtures'][43]['localTestReferences'], true));
        $t->same('upstream-markdown-raw-email-address.md', $evidence['checkedInFixtures'][44]['name']);
        $t->same('d75b7a8bd91fde01fc8e7aea25c1f124c0a70af523af13330b641757655788ec', $evidence['checkedInFixtures'][44]['checkedInFile']['sha256']);
        $t->same(10, $evidence['checkedInFixtures'][44]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawEmailAddressFixtureCompletionTest.php', $evidence['checkedInFixtures'][44]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawEmailAddressFixtureCompletionTest.php', $evidence['checkedInFixtures'][44]['localTestReferences'], true));
        $t->same('upstream-markdown-footnote-continuation-boundaries.md', $evidence['checkedInFixtures'][45]['name']);
        $t->same('bcd100bebcaa3c2d7e1e51df1a3e72cebbbc93760b6d903039ee149d0153640f', $evidence['checkedInFixtures'][45]['checkedInFile']['sha256']);
        $t->same(78, $evidence['checkedInFixtures'][45]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFootnoteContinuationBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][45]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFootnoteContinuationBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][45]['localTestReferences'], true));
        $t->same('upstream-markdown-heading-boundaries.md', $evidence['checkedInFixtures'][46]['name']);
        $t->same('6497aa032094a74bfdb6cc714e4f922dfc3a29c14b1196bb84337f201caf1f52', $evidence['checkedInFixtures'][46]['checkedInFile']['sha256']);
        $t->same(44, $evidence['checkedInFixtures'][46]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderHeadingBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][46]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderHeadingBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][46]['localTestReferences'], true));
        $t->same('upstream-markdown-raw-html-invalid-comment.md', $evidence['checkedInFixtures'][47]['name']);
        $t->same('d2e5f74952fd26fd316d646bc360d421b533233e05620ba08afe784f4c17cafa', $evidence['checkedInFixtures'][47]['checkedInFile']['sha256']);
        $t->same(23, $evidence['checkedInFixtures'][47]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawHtmlInvalidCommentFixtureCompletionTest.php', $evidence['checkedInFixtures'][47]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawHtmlInvalidCommentFixtureCompletionTest.php', $evidence['checkedInFixtures'][47]['localTestReferences'], true));
        $t->same('upstream-markdown-raw-html-invalid-tag.md', $evidence['checkedInFixtures'][48]['name']);
        $t->same('c981cea993a23dc23358c1d17fdda03abc7ed9b95f0fdd721beb0629dcba891a', $evidence['checkedInFixtures'][48]['checkedInFile']['sha256']);
        $t->same(15, $evidence['checkedInFixtures'][48]['checkedInFile']['bytes']);
        $t->same('markdown raw_html invalid tag literal boundary', $evidence['checkedInFixtures'][48]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawHtmlInvalidTagBoundaryCompletionTest.php', $evidence['checkedInFixtures'][48]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawHtmlInvalidTagBoundaryCompletionTest.php', $evidence['checkedInFixtures'][48]['localTestReferences'], true));

        $legacyCheckedInFixtures = $evidence['checkedInFixtures'];
        array_splice($legacyCheckedInFixtures, 47, 1);
        $evidence['checkedInFixtures'] = $legacyCheckedInFixtures;
        $t->same('upstream-markdown-raw-html-nesting.md', $evidence['checkedInFixtures'][48]['name']);
        $t->same('0e02bd68029985d4aa7eb46ecd54335afa80af300626cf32fbe23756dc764f7b', $evidence['checkedInFixtures'][48]['checkedInFile']['sha256']);
        $t->same(16, $evidence['checkedInFixtures'][48]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawHtmlNestingFixtureCompletionTest.php', $evidence['checkedInFixtures'][48]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawHtmlNestingFixtureCompletionTest.php', $evidence['checkedInFixtures'][48]['localTestReferences'], true));
        $t->same('upstream-markdown-yaml-metadata.md', $evidence['checkedInFixtures'][49]['name']);
        $t->same('5f69d57ef44116f63721edde6e0f164d3388f692e0fb9359bdc8ea35261e3376', $evidence['checkedInFixtures'][49]['checkedInFile']['sha256']);
        $t->same(142, $evidence['checkedInFixtures'][49]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderYamlMetadataFixtureCompletionTest.php', $evidence['checkedInFixtures'][49]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderYamlMetadataFixtureCompletionTest.php', $evidence['checkedInFixtures'][49]['localTestReferences'], true));
        $t->same('upstream-markdown-definition-list-tight-bodies.md', $evidence['checkedInFixtures'][50]['name']);
        $t->same('58eb007d7f3dac48da8c992622e5a29defd68c7a84cdabb29b81ac4e218df924', $evidence['checkedInFixtures'][50]['checkedInFile']['sha256']);
        $t->same(62, $evidence['checkedInFixtures'][50]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php', $evidence['checkedInFixtures'][50]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php', $evidence['checkedInFixtures'][50]['localTestReferences'], true));
        $t->same('upstream-markdown-lhs-inverse-bird-html.md', $evidence['checkedInFixtures'][51]['name']);
        $t->same('f08f6db28a623c0f60dbe069e68567e38f7ecbf71367f01eebfa52c2d6735ce0', $evidence['checkedInFixtures'][51]['checkedInFile']['sha256']);
        $t->same(16, $evidence['checkedInFixtures'][51]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderLiterateHaskellFixtureCompletionTest.php', $evidence['checkedInFixtures'][51]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderLiterateHaskellFixtureCompletionTest.php', $evidence['checkedInFixtures'][51]['localTestReferences'], true));
        $t->same('upstream-markdown-alerts.md', $evidence['checkedInFixtures'][52]['name']);
        $t->same('d4f826212c99ace92b25f414db142d565fa7b737ff6c0cbabb4010d5cf1f7b29', $evidence['checkedInFixtures'][52]['checkedInFile']['sha256']);
        $t->same(107, $evidence['checkedInFixtures'][52]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAlertProfileCompletionTest.php', $evidence['checkedInFixtures'][52]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAlertProfileCompletionTest.php', $evidence['checkedInFixtures'][52]['localTestReferences'], true));
        $t->same('upstream-markdown-strict-compact-heading.md', $evidence['checkedInFixtures'][53]['name']);
        $t->same('7631fb35c6f86b29590e5a339c7c14abc67cb65adc8557efffef6f69485eb0b4', $evidence['checkedInFixtures'][53]['checkedInFile']['sha256']);
        $t->same(4, $evidence['checkedInFixtures'][53]['checkedInFile']['bytes']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderStrictCompactHeadingFixtureCompletionTest.php', $evidence['checkedInFixtures'][53]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderStrictCompactHeadingFixtureCompletionTest.php', $evidence['checkedInFixtures'][53]['localTestReferences'], true));
        $t->same('upstream-markdown-z-commonmark-x-grid-table-default.md', $evidence['checkedInFixtures'][54]['name']);
        $t->same('412a732f5c23e980d34d1be6b014030f06ff723439ba06740804fe1d52a946a1', $evidence['checkedInFixtures'][54]['checkedInFile']['sha256']);
        $t->same(50, $evidence['checkedInFixtures'][54]['checkedInFile']['bytes']);
        $t->same('commonmark_x grid_tables disabled by default, pipe_tables still enabled', $evidence['checkedInFixtures'][54]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderTableProfileSurgeTest.php', $evidence['checkedInFixtures'][54]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderTableProfileSurgeTest.php', $evidence['checkedInFixtures'][54]['localTestReferences'], true));
        $t->same('upstream-markdown-autolink-attributes.md', $evidence['checkedInFixtures'][55]['name']);
        $t->same('1e53b4ffdeab43731a3909f53ffd8d4f44d2560d5355b7e490ef2f19376c2052', $evidence['checkedInFixtures'][55]['checkedInFile']['sha256']);
        $t->same(64, $evidence['checkedInFixtures'][55]['checkedInFile']['bytes']);
        $t->same('markdown angle autolink raw_attribute boundary', $evidence['checkedInFixtures'][55]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAutolinkAttributeFixtureCompletionTest.php', $evidence['checkedInFixtures'][55]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAutolinkAttributeFixtureCompletionTest.php', $evidence['checkedInFixtures'][55]['localTestReferences'], true));
        $t->same('upstream-markdown-z-fancy-list-markers.md', $evidence['checkedInFixtures'][56]['name']);
        $t->same('c3d8db151d6eed0603f2e3774c031b405a5ca8b64d69574f3feb590df1e62d21', $evidence['checkedInFixtures'][56]['checkedInFile']['sha256']);
        $t->same(71, $evidence['checkedInFixtures'][56]['checkedInFile']['bytes']);
        $t->same('markdown+fancy_lists upper-alpha, upper-roman, and parenthesized decimal ordered markers', $evidence['checkedInFixtures'][56]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFancyListFixtureCompletionTest.php', $evidence['checkedInFixtures'][56]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFancyListFixtureCompletionTest.php', $evidence['checkedInFixtures'][56]['localTestReferences'], true));
        $t->same('upstream-markdown-z-hard-line-break-profile.md', $evidence['checkedInFixtures'][57]['name']);
        $t->same('4fdbc441ea7b546100e086ac1e4fc5ae6749b7314311c99db05be450eca12996', $evidence['checkedInFixtures'][57]['checkedInFile']['sha256']);
        $t->same(17, $evidence['checkedInFixtures'][57]['checkedInFile']['bytes']);
        $t->same('markdown+hard_line_breaks physical paragraph newlines as LineBreak constructors', $evidence['checkedInFixtures'][57]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderHardLineBreakProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][57]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderHardLineBreakProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][57]['localTestReferences'], true));
        $t->same('upstream-markdown-inline-math.md', $evidence['checkedInFixtures'][58]['name']);
        $t->same('364f852f91e3d11943ffa83ae6cd717f3b9ae38a2c61100fe135e95d4bf9180a', $evidence['checkedInFixtures'][58]['checkedInFile']['sha256']);
        $t->same(36, $evidence['checkedInFixtures'][58]['checkedInFile']['bytes']);
        $t->same('markdown tex_math_dollars inline math', $evidence['checkedInFixtures'][58]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderInlineMathFixtureCompletionTest.php', $evidence['checkedInFixtures'][58]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderInlineMathFixtureCompletionTest.php', $evidence['checkedInFixtures'][58]['localTestReferences'], true));
        $t->same('upstream-markdown-ascii-identifiers.md', $evidence['checkedInFixtures'][59]['name']);
        $t->same('37abcb0679639cce00173e8737b95b0c76da7a3f2b6bc3790bccbd2790abf232', $evidence['checkedInFixtures'][59]['checkedInFile']['sha256']);
        $t->same(156, $evidence['checkedInFixtures'][59]['checkedInFile']['bytes']);
        $t->same('markdown+ascii_identifiers', $evidence['checkedInFixtures'][59]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAsciiIdentifierProfileCompletionTest.php', $evidence['checkedInFixtures'][59]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][59]['localTestReferences'], true));
        $t->same('upstream-markdown-z-phpextra-profile.md', $evidence['checkedInFixtures'][60]['name']);
        $t->same('83e7b30e00869c6ef685979df5fa075d3e4bb2bc988d0e615ea584b6374f5347', $evidence['checkedInFixtures'][60]['checkedInFile']['sha256']);
        $t->same(120, $evidence['checkedInFixtures'][60]['checkedInFile']['bytes']);
        $t->same('markdown_phpextra header_attributes/link_attributes/definition_lists/footnotes defaults', $evidence['checkedInFixtures'][60]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderPhpExtraProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][60]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][60]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderPhpExtraProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][60]['localTestReferences'], true));
        $t->same('upstream-markdown-z-simple-table-profile.md', $evidence['checkedInFixtures'][61]['name']);
        $t->same('dead898102ace731504514d4a7babbc466ed7ed3e402ba0cba752a4c610689d9', $evidence['checkedInFixtures'][61]['checkedInFile']['sha256']);
        $t->same(39, $evidence['checkedInFixtures'][61]['checkedInFile']['bytes']);
        $t->same('markdown+simple_tables header/body table constructor profile', $evidence['checkedInFixtures'][61]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderSimpleTableProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][61]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][61]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderSimpleTableProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][61]['localTestReferences'], true));
        $t->same('upstream-markdown-z-short-subsuperscript-profile.md', $evidence['checkedInFixtures'][62]['name']);
        $t->same('749be67c41a587eefa87c24129d84369e3fa3a10d9cc299fb709e51d3818e33a', $evidence['checkedInFixtures'][62]['checkedInFile']['sha256']);
        $t->same(29, $evidence['checkedInFixtures'][62]['checkedInFile']['bytes']);
        $t->same('markdown+short_subsuperscripts short subscript/superscript profile', $evidence['checkedInFixtures'][62]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderShortSubsuperscriptProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][62]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][62]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderShortSubsuperscriptProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][62]['localTestReferences'], true));
        $t->same('upstream-markdown-z-spaced-reference-link-profile.md', $evidence['checkedInFixtures'][63]['name']);
        $t->same('1a8864629a497067a58f7e12bdb6a56005779f92f8219d705e9ef488e6bd9bab', $evidence['checkedInFixtures'][63]['checkedInFile']['sha256']);
        $t->same(127, $evidence['checkedInFixtures'][63]['checkedInFile']['bytes']);
        $t->same('markdown-shortcut_reference_links+spaced_reference_links spaced reference labels with shortcut links disabled', $evidence['checkedInFixtures'][63]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderSpacedReferenceLinkProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][63]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][63]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderSpacedReferenceLinkProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][63]['localTestReferences'], true));
        $t->same('upstream-markdown-z-tex-math-double-backslash-profile.md', $evidence['checkedInFixtures'][64]['name']);
        $t->same('9b0d34f3b6a66f40771940859a979cc06da95e3fc7deb439907edac34a2a484b', $evidence['checkedInFixtures'][64]['checkedInFile']['sha256']);
        $t->same(40, $evidence['checkedInFixtures'][64]['checkedInFile']['bytes']);
        $t->same('markdown+tex_math_double_backslash inline/display math delimiters', $evidence['checkedInFixtures'][64]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDoubleBackslashMathProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][64]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderDoubleBackslashMathProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][64]['localTestReferences'], true));
        $t->same('upstream-markdown-zz-east-asian-line-break-profile.md', $evidence['checkedInFixtures'][65]['name']);
        $t->same('bfa1c21a376998bbdf364121bcaa093db875c9e415c0d40908a38182922f2871', $evidence['checkedInFixtures'][65]['checkedInFile']['sha256']);
        $t->same(20, $evidence['checkedInFixtures'][65]['checkedInFile']['bytes']);
        $t->same('markdown+east_asian_line_breaks joins East Asian soft line boundaries only', $evidence['checkedInFixtures'][65]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderEastAsianLineBreakProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][65]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][65]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderEastAsianLineBreakProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][65]['localTestReferences'], true));
        $t->same('upstream-markdown-zz-tex-math-single-backslash-profile.md', $evidence['checkedInFixtures'][66]['name']);
        $t->same('f02128f194b2d78319520340497fe7bc3f195a7f030bd59838091275769f918f', $evidence['checkedInFixtures'][66]['checkedInFile']['sha256']);
        $t->same(36, $evidence['checkedInFixtures'][66]['checkedInFile']['bytes']);
        $t->same('markdown+tex_math_single_backslash inline/display math delimiters', $evidence['checkedInFixtures'][66]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderSingleBackslashMathProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][66]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][66]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderSingleBackslashMathProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][66]['localTestReferences'], true));
        $t->same('upstream-markdown-zzz-intraword-underscore-profile.md', $evidence['checkedInFixtures'][67]['name']);
        $t->same('23bc30dbf0fb9813f0cf0addfd364c788ce80f56203bf1ca2f69f7f5752fec8d', $evidence['checkedInFixtures'][67]['checkedInFile']['sha256']);
        $t->same(30, $evidence['checkedInFixtures'][67]['checkedInFile']['bytes']);
        $t->same('markdown-intraword_underscores intraword emphasis/strong profile', $evidence['checkedInFixtures'][67]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderIntrawordUnderscoreProfileCompletionTest.php', $evidence['checkedInFixtures'][67]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][67]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderIntrawordUnderscoreProfileCompletionTest.php', $evidence['checkedInFixtures'][67]['localTestReferences'], true));
        $t->same('upstream-markdown-z-lists-without-preceding-blankline-profile.md', $evidence['checkedInFixtures'][68]['name']);
        $t->same('4de2a3ad30fc94cb41a4d00e9f004d07fde543dacd892f5c4559812afe118521', $evidence['checkedInFixtures'][68]['checkedInFile']['sha256']);
        $t->same(47, $evidence['checkedInFixtures'][68]['checkedInFile']['bytes']);
        $t->same('markdown+lists_without_preceding_blankline paragraph-interrupting bullet and non-one ordered list markers', $evidence['checkedInFixtures'][68]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderListsWithoutPrecedingBlanklineProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][68]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][68]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderListsWithoutPrecedingBlanklineProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][68]['localTestReferences'], true));
        $t->same('upstream-markdown-zzzz-angle-brackets-escapable-profile.md', $evidence['checkedInFixtures'][69]['name']);
        $t->same('1693f0d6344c29946c29918167e5197d7631207e72cb1000f16b14d355904b6b', $evidence['checkedInFixtures'][69]['checkedInFile']['sha256']);
        $t->same(12, $evidence['checkedInFixtures'][69]['checkedInFile']['bytes']);
        $t->same('markdown-all_symbols_escapable+angle_brackets_escapable angle-only escape profile', $evidence['checkedInFixtures'][69]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAngleBracketEscapableProfileCompletionTest.php', $evidence['checkedInFixtures'][69]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][69]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAngleBracketEscapableProfileCompletionTest.php', $evidence['checkedInFixtures'][69]['localTestReferences'], true));
        $t->same('upstream-markdown-zzzzz-wikilinks-title-after-pipe-profile.md', $evidence['checkedInFixtures'][70]['name']);
        $t->same('57fd7294b0f6f88a4190b23b68771dc5a111d3b9bcc7aa6d000be987cce9fa52', $evidence['checkedInFixtures'][70]['checkedInFile']['sha256']);
        $t->same(45, $evidence['checkedInFixtures'][70]['checkedInFile']['bytes']);
        $t->same('markdown+wikilinks_title_after_pipe target-before-pipe/title-after-pipe wikilink profile', $evidence['checkedInFixtures'][70]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderWikiLinkDirectionCompletionTest.php', $evidence['checkedInFixtures'][70]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][70]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderWikiLinkDirectionCompletionTest.php', $evidence['checkedInFixtures'][70]['localTestReferences'], true));
        $t->same('upstream-markdown-zzzzzz-ignore-line-breaks-profile.md', $evidence['checkedInFixtures'][71]['name']);
        $t->same('de6e28f373e2f47e1e603f8e9bf80abb7fa9d13fdfe210f9125bd4f052e4ebdc', $evidence['checkedInFixtures'][71]['checkedInFile']['sha256']);
        $t->same(30, $evidence['checkedInFixtures'][71]['checkedInFile']['bytes']);
        $t->same('markdown+ignore_line_breaks ordinary physical newlines ignored while explicit hard-break markers survive', $evidence['checkedInFixtures'][71]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderLineBreakProfileSurgeTest.php', $evidence['checkedInFixtures'][71]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][71]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderLineBreakProfileSurgeTest.php', $evidence['checkedInFixtures'][71]['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][71]['localTestReferences'], true));
        $t->same('upstream-markdown-zzzzzzz-auto-identifiers-disabled-profile.md', $evidence['checkedInFixtures'][72]['name']);
        $t->same('d5699a975a6717d34a5ec0d06d4b852a3201be0726d90ac01b10e552754ed926', $evidence['checkedInFixtures'][72]['checkedInFile']['sha256']);
        $t->same(19, $evidence['checkedInFixtures'][72]['checkedInFile']['bytes']);
        $t->same('markdown-auto_identifiers generated heading IDs disabled while heading text remains parsed', $evidence['checkedInFixtures'][72]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAutoIdentifierProfileCompletionTest.php', $evidence['checkedInFixtures'][72]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][72]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][72]['localTestReferences'], true));
        $t->same('upstream-markdown-zzzzzzzz-blank-before-header-disabled-profile.md', $evidence['checkedInFixtures'][73]['name']);
        $t->same('153bfcf8342792293c62211ee56b1af9f717e80dd74df85e634f4db18877a580', $evidence['checkedInFixtures'][73]['checkedInFile']['sha256']);
        $t->same(40, $evidence['checkedInFixtures'][73]['checkedInFile']['bytes']);
        $t->same('markdown-blank_before_header paragraph-interrupting ATX heading with implicit header reference', $evidence['checkedInFixtures'][73]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBlankBeforeBlockBoundaryProfileTest.php', $evidence['checkedInFixtures'][73]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][73]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBlankBeforeBlockBoundaryProfileTest.php', $evidence['checkedInFixtures'][73]['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][73]['localTestReferences'], true));
        $t->same('upstream-markdown-zzzzzzzzz-citation-digit-key.md', $evidence['checkedInFixtures'][74]['name']);
        $t->same('ed4794c48dd0f70f0bb11a32c8b2148259ddc3366c5fafa72db9ed97c02b74a8', $evidence['checkedInFixtures'][74]['checkedInFile']['sha256']);
        $t->same(15, $evidence['checkedInFixtures'][74]['checkedInFile']['bytes']);
        $t->same('markdown citations digit-leading key', $evidence['checkedInFixtures'][74]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderCitationDigitKeyFixtureCompletionTest.php', $evidence['checkedInFixtures'][74]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderCitationDigitKeyFixtureCompletionTest.php', $evidence['checkedInFixtures'][74]['localTestReferences'], true));
        $t->same('upstream-markdown-zzzzzzzzzz-bare-uri-bracket-encoding.md', $evidence['checkedInFixtures'][75]['name']);
        $t->same('bf3c3167f8df54bff120fe833bd61bbe0db2ed036a9f4e6befdbe178aaaf9702', $evidence['checkedInFixtures'][75]['checkedInFile']['sha256']);
        $t->same(113, $evidence['checkedInFixtures'][75]['checkedInFile']['bytes']);
        $t->same('markdown+autolink_bare_uris square and curly bracket target percent-encoding', $evidence['checkedInFixtures'][75]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBareUriBracketEncodingFixtureCompletionTest.php', $evidence['checkedInFixtures'][75]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][75]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBareUriBracketEncodingFixtureCompletionTest.php', $evidence['checkedInFixtures'][75]['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][75]['localTestReferences'], true));
        $t->same('upstream-markdown-zzzzzzzzzzz-mmd-title-block-profile.md', $evidence['checkedInFixtures'][76]['name']);
        $t->same('ae9b323e06416495765aa0a6f06a3b51c0892744cf842fe6bcdc4287bff41f5a', $evidence['checkedInFixtures'][76]['checkedInFile']['sha256']);
        $t->same(70, $evidence['checkedInFixtures'][76]['checkedInFile']['bytes']);
        $t->same('markdown_mmd mmd_title_block metadata removed from body', $evidence['checkedInFixtures'][76]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMmdTitleBlockProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][76]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][76]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMmdTitleBlockProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][76]['localTestReferences'], true));
        $t->same('upstream-markdown-zzzzzzzzzzzz-gfm-auto-identifiers-profile.md', $evidence['checkedInFixtures'][77]['name']);
        $t->same('24fe9261edac6c433bd1add99f394033bda6ad3a8721798e28032ebf00187be8', $evidence['checkedInFixtures'][77]['checkedInFile']['sha256']);
        $t->same(91, $evidence['checkedInFixtures'][77]['checkedInFile']['bytes']);
        $t->same('commonmark+gfm_auto_identifiers+ascii_identifiers punctuation stripping, ASCII folding, dash fallback, and duplicate suffixing', $evidence['checkedInFixtures'][77]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderGfmAutoIdentifierProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][77]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][77]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderGfmAutoIdentifierProfileFixtureCompletionTest.php', $evidence['checkedInFixtures'][77]['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][77]['localTestReferences'], true));
        $t->same('upstream-markdown-zzzzzzzzzzzzz-citation-link-boundaries.md', $evidence['checkedInFixtures'][78]['name']);
        $t->same('61c35e0cc522237de7afc0ceb3eedf50716dbab3b654c3bfef996196527a469c', $evidence['checkedInFixtures'][78]['checkedInFile']['sha256']);
        $t->same(178, $evidence['checkedInFixtures'][78]['checkedInFile']['bytes']);
        $t->same('markdown citations with following footnote, inline link, reference link, shortcut reference link, implicit header reference, and suffix boundary', $evidence['checkedInFixtures'][78]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderCitationLinkBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][78]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][78]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderCitationLinkBoundaryFixtureCompletionTest.php', $evidence['checkedInFixtures'][78]['localTestReferences'], true));
        $t->same('upstream-markdown-zzzzzzzzzzzzzz-footnote-fenced-div-boundary.md', $evidence['checkedInFixtures'][79]['name']);
        $t->same('1899054fb7e7d232092d3a4b96b83d804f0a4c66ded8c370431f04ce46394775', $evidence['checkedInFixtures'][79]['checkedInFile']['sha256']);
        $t->same(153, $evidence['checkedInFixtures'][79]['checkedInFile']['bytes']);
        $t->same('markdown footnotes plus fenced_divs/native_divs same-line literal and indented Div boundary', $evidence['checkedInFixtures'][79]['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFootnoteFencedDivBoundaryCompletionTest.php', $evidence['checkedInFixtures'][79]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $evidence['checkedInFixtures'][79]['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderFootnoteFencedDivBoundaryCompletionTest.php', $evidence['checkedInFixtures'][79]['localTestReferences'], true));
        $rawHtmlListFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzz-raw-html-list-boundary.md') {
                $rawHtmlListFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($rawHtmlListFixture));
        $rawHtmlListFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('cbfbf9fca325dc0888cf2ed788e87f9b498d8cc76ce4c9b07ed325b6007fc5a2', $rawHtmlListFixture['checkedInFile']['sha256']);
        $t->same(131, $rawHtmlListFixture['checkedInFile']['bytes']);
        $t->same('markdown raw_html/native_divs list continuation boundary', $rawHtmlListFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawHtmlListBoundaryFixtureCompletionTest.php', $rawHtmlListFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $rawHtmlListFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawHtmlListBoundaryFixtureCompletionTest.php', $rawHtmlListFixture['localTestReferences'], true));
        $gfmNestedListFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzz-gfm-nested-list-continuation.md') {
                $gfmNestedListFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($gfmNestedListFixture));
        $gfmNestedListFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-zzzzzzzzzzzzzzzz-gfm-nested-list-continuation.md', $gfmNestedListFixture['name']);
        $t->same('ba07e4cc5bd3d93801b0146b1f6244fab8658024aebdb0024b1cab825d48b8ed', $gfmNestedListFixture['checkedInFile']['sha256']);
        $t->same(20, $gfmNestedListFixture['checkedInFile']['bytes']);
        $t->same('gfm nested list continuation under prior bullet item', $gfmNestedListFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderGfmNestedListContinuationFixtureCompletionTest.php', $gfmNestedListFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $gfmNestedListFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderGfmNestedListContinuationFixtureCompletionTest.php', $gfmNestedListFixture['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $gfmNestedListFixture['localTestReferences'], true));
        $blankBeforeBlockQuoteFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzzz-blank-before-blockquote-disabled-profile.md') {
                $blankBeforeBlockQuoteFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($blankBeforeBlockQuoteFixture));
        $blankBeforeBlockQuoteFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-zzzzzzzzzzzzzzzzz-blank-before-blockquote-disabled-profile.md', $blankBeforeBlockQuoteFixture['name']);
        $t->same('8a4291b15fe0e64bebd8dbdd3654a358d661f899f1dfcba7be6e60d34c0f4f76', $blankBeforeBlockQuoteFixture['checkedInFile']['sha256']);
        $t->same(51, $blankBeforeBlockQuoteFixture['checkedInFile']['bytes']);
        $t->same('markdown-blank_before_blockquote top-level and nested paragraph-interrupting block quote profile behavior', $blankBeforeBlockQuoteFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBlankBeforeBlockBoundaryProfileTest.php', $blankBeforeBlockQuoteFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $blankBeforeBlockQuoteFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBlankBeforeBlockBoundaryProfileTest.php', $blankBeforeBlockQuoteFixture['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $blankBeforeBlockQuoteFixture['localTestReferences'], true));
        $blankBeforeHeaderBlockQuoteFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzzzz-blank-before-header-blockquote-profile.md') {
                $blankBeforeHeaderBlockQuoteFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($blankBeforeHeaderBlockQuoteFixture));
        $blankBeforeHeaderBlockQuoteFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-zzzzzzzzzzzzzzzzzz-blank-before-header-blockquote-profile.md', $blankBeforeHeaderBlockQuoteFixture['name']);
        $t->same('5424e779dd141e369b8a3618ba776adc9965ca590ae67126ec8454c89184ea04', $blankBeforeHeaderBlockQuoteFixture['checkedInFile']['sha256']);
        $t->same(44, $blankBeforeHeaderBlockQuoteFixture['checkedInFile']['bytes']);
        $t->same('markdown-blank_before_header blockquote-contained paragraph-interrupting heading and implicit header reference', $blankBeforeHeaderBlockQuoteFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBlankBeforeBlockBoundaryProfileTest.php', $blankBeforeHeaderBlockQuoteFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $blankBeforeHeaderBlockQuoteFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBlankBeforeBlockBoundaryProfileTest.php', $blankBeforeHeaderBlockQuoteFixture['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $blankBeforeHeaderBlockQuoteFixture['localTestReferences'], true));
        $markExtensionFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzzzzz-mark-extension-profile.md') {
                $markExtensionFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($markExtensionFixture));
        $markExtensionFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-zzzzzzzzzzzzzzzzzzz-mark-extension-profile.md', $markExtensionFixture['name']);
        $t->same('b8c09f9af30b7896e4721d9eada0dfb9cf06c29989eff78cb6ae3e8221a2b4f7', $markExtensionFixture['checkedInFile']['sha256']);
        $t->same(36, $markExtensionFixture['checkedInFile']['bytes']);
        $t->same('markdown+mark highlighted inline Span with nested strong content', $markExtensionFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMarkExtensionProfileCompletionTest.php', $markExtensionFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $markExtensionFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderMarkExtensionProfileCompletionTest.php', $markExtensionFixture['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $markExtensionFixture['localTestReferences'], true));
        $unicodeDashAutolinkFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzzzzzz-angle-autolink-unicode-dash-boundary.md') {
                $unicodeDashAutolinkFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($unicodeDashAutolinkFixture));
        $unicodeDashAutolinkFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-zzzzzzzzzzzzzzzzzzzz-angle-autolink-unicode-dash-boundary.md', $unicodeDashAutolinkFixture['name']);
        $t->same('6e30b22ae36c55d1fa851d76124bbe27f9439f2e541919e2e711a71c8d4c3071', $unicodeDashAutolinkFixture['checkedInFile']['sha256']);
        $t->same(20, $unicodeDashAutolinkFixture['checkedInFile']['bytes']);
        $t->same('markdown angle autolink followed by Unicode dash boundary', $unicodeDashAutolinkFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAngleAutolinkResidualTest.php', $unicodeDashAutolinkFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $unicodeDashAutolinkFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAngleAutolinkResidualTest.php', $unicodeDashAutolinkFixture['localTestReferences'], true));
        $partialAutolinkFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzzzzzzz-partial-autolink-boundary.md') {
                $partialAutolinkFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($partialAutolinkFixture));
        $partialAutolinkFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-zzzzzzzzzzzzzzzzzzzzz-partial-autolink-boundary.md', $partialAutolinkFixture['name']);
        $t->same('781de064172e9944bb03b648b31f917e91d8bd51be8d128e62cdd313c1fc6409', $partialAutolinkFixture['checkedInFile']['sha256']);
        $t->same(51, $partialAutolinkFixture['checkedInFile']['bytes']);
        $t->same('markdown angle autolink partial www URL literal boundary', $partialAutolinkFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAngleAutolinkResidualTest.php', $partialAutolinkFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $partialAutolinkFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAngleAutolinkResidualTest.php', $partialAutolinkFixture['localTestReferences'], true));
        $gfmRawHtmlSplitAngleFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzz-gfm-raw-html-split-angle-boundary.md') {
                $gfmRawHtmlSplitAngleFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($gfmRawHtmlSplitAngleFixture));
        $gfmRawHtmlSplitAngleFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-zzzzzzzzzzzzzzzzzzzzzz-gfm-raw-html-split-angle-boundary.md', $gfmRawHtmlSplitAngleFixture['name']);
        $t->same('0aff578e385aeec2e57c71ec2f38ce81370718f6d22351ba0bdee3db7e7d3b7c', $gfmRawHtmlSplitAngleFixture['checkedInFile']['sha256']);
        $t->same(6, $gfmRawHtmlSplitAngleFixture['checkedInFile']['bytes']);
        $t->same('gfm raw HTML split angle literal paragraph boundary', $gfmRawHtmlSplitAngleFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderGfmRawHtmlSplitAngleBoundaryFixtureCompletionTest.php', $gfmRawHtmlSplitAngleFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $gfmRawHtmlSplitAngleFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderGfmRawHtmlSplitAngleBoundaryFixtureCompletionTest.php', $gfmRawHtmlSplitAngleFixture['localTestReferences'], true));
        $bareUriSchemeFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzz-bare-uri-scheme-boundaries.md') {
                $bareUriSchemeFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($bareUriSchemeFixture));
        $bareUriSchemeFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzz-bare-uri-scheme-boundaries.md', $bareUriSchemeFixture['name']);
        $t->same('eab76a84dbaa0c8c93b841c15a8b7cf4e29285b1b206311b9e3fa0ac04d94a0b', $bareUriSchemeFixture['checkedInFile']['sha256']);
        $t->same(101, $bareUriSchemeFixture['checkedInFile']['bytes']);
        $t->same('markdown+autolink_bare_uris uppercase/doi/mailto scheme and trailing punctuation boundaries', $bareUriSchemeFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBareUriSchemeBoundaryFixtureCompletionTest.php', $bareUriSchemeFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $bareUriSchemeFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBareUriSchemeBoundaryFixtureCompletionTest.php', $bareUriSchemeFixture['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $bareUriSchemeFixture['localTestReferences'], true));
        $bareUriQueryFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-bare-uri-query-boundaries.md') {
                $bareUriQueryFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($bareUriQueryFixture));
        $bareUriQueryFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-bare-uri-query-boundaries.md', $bareUriQueryFixture['name']);
        $t->same('2d251db0ec3147f60db5580984f4c7dc5a18eedc8f03f6e8814f4e7c2d4e3e57', $bareUriQueryFixture['checkedInFile']['sha256']);
        $t->same(183, $bareUriQueryFixture['checkedInFile']['bytes']);
        $t->same('markdown+autolink_bare_uris query-string, parenthesized path, and fragment boundaries', $bareUriQueryFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBareUriQueryBoundaryFixtureCompletionTest.php', $bareUriQueryFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $bareUriQueryFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBareUriQueryBoundaryFixtureCompletionTest.php', $bareUriQueryFixture['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $bareUriQueryFixture['localTestReferences'], true));
        $bareUriQueryHyphenFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-bare-uri-query-hyphen-boundaries.md') {
                $bareUriQueryHyphenFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($bareUriQueryHyphenFixture));
        $bareUriQueryHyphenFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-bare-uri-query-hyphen-boundaries.md', $bareUriQueryHyphenFixture['name']);
        $t->same('161499a458db1013329ddd59fe20e37cafa71947c55beb3a7adffc99a7eb7309', $bareUriQueryHyphenFixture['checkedInFile']['sha256']);
        $t->same(69, $bareUriQueryHyphenFixture['checkedInFile']['bytes']);
        $t->same('markdown+autolink_bare_uris query values ending and beginning with hyphen', $bareUriQueryHyphenFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBareUriQueryBoundaryFixtureCompletionTest.php', $bareUriQueryHyphenFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $bareUriQueryHyphenFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBareUriQueryBoundaryFixtureCompletionTest.php', $bareUriQueryHyphenFixture['localTestReferences'], true));
        $bareUriPathPunctuationFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-bare-uri-path-punctuation.md') {
                $bareUriPathPunctuationFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($bareUriPathPunctuationFixture));
        $bareUriPathPunctuationFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-bare-uri-path-punctuation.md', $bareUriPathPunctuationFixture['name']);
        $t->same('d48891d00c0184a1eec42834d4a928035dd3480b8aafe11a54790071c6e8a20d', $bareUriPathPunctuationFixture['checkedInFile']['sha256']);
        $t->same(190, $bareUriPathPunctuationFixture['checkedInFile']['bytes']);
        $t->same('markdown+autolink_bare_uris+raw_html bare URI path punctuation boundaries', $bareUriPathPunctuationFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBareUriPathPunctuationFixtureCompletionTest.php', $bareUriPathPunctuationFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $bareUriPathPunctuationFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderBareUriPathPunctuationFixtureCompletionTest.php', $bareUriPathPunctuationFixture['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $bareUriPathPunctuationFixture['localTestReferences'], true));
        $texMathDollarDisplayFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzz-tex-math-dollar-display-boundary.md') {
                $texMathDollarDisplayFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($texMathDollarDisplayFixture));
        $texMathDollarDisplayFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzz-tex-math-dollar-display-boundary.md', $texMathDollarDisplayFixture['name']);
        $t->same('4cd6d7a97debf74bcd4580ee94fb067a8d7eea429d74562d5ab1a3204837f1b5', $texMathDollarDisplayFixture['checkedInFile']['sha256']);
        $t->same(45, $texMathDollarDisplayFixture['checkedInFile']['bytes']);
        $t->same('markdown tex_math_dollars standalone display math', $texMathDollarDisplayFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderInlineMathFixtureCompletionTest.php', $texMathDollarDisplayFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $texMathDollarDisplayFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderInlineMathFixtureCompletionTest.php', $texMathDollarDisplayFixture['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $texMathDollarDisplayFixture['localTestReferences'], true));
        $rawHtmlInlineCommonmarkFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzz-raw-html-inline-commonmark-profile.md') {
                $rawHtmlInlineCommonmarkFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($rawHtmlInlineCommonmarkFixture));
        $rawHtmlInlineCommonmarkFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzz-raw-html-inline-commonmark-profile.md', $rawHtmlInlineCommonmarkFixture['name']);
        $t->same('4f5b81222a650acf3c9cc2367c3b0638e122e53c7647275528ae3d5aed370a2a', $rawHtmlInlineCommonmarkFixture['checkedInFile']['sha256']);
        $t->same(40, $rawHtmlInlineCommonmarkFixture['checkedInFile']['bytes']);
        $t->same('commonmark+raw_attribute raw HTML inline constructor', $rawHtmlInlineCommonmarkFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawCodeProfileOverrideSurgeTest.php', $rawHtmlInlineCommonmarkFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $rawHtmlInlineCommonmarkFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderRawCodeProfileOverrideSurgeTest.php', $rawHtmlInlineCommonmarkFixture['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $rawHtmlInlineCommonmarkFixture['localTestReferences'], true));
        $atxHeadingSpaceFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzz-atx-heading-space-disabled-profile.md') {
                $atxHeadingSpaceFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($atxHeadingSpaceFixture));
        $atxHeadingSpaceFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzz-atx-heading-space-disabled-profile.md', $atxHeadingSpaceFixture['name']);
        $t->same('413d41252fef624ef193fa4175f527d27f0eac5f7cd23c216d4d1afac2901b11', $atxHeadingSpaceFixture['checkedInFile']['sha256']);
        $t->same(26, $atxHeadingSpaceFixture['checkedInFile']['bytes']);
        $t->same('markdown-space_in_atx_header compact attributed ATX heading', $atxHeadingSpaceFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAtxHeadingSpaceProfileSurgeTest.php', $atxHeadingSpaceFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $atxHeadingSpaceFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAtxHeadingSpaceProfileSurgeTest.php', $atxHeadingSpaceFixture['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $atxHeadingSpaceFixture['localTestReferences'], true));
        $startnumFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzz-startnum-disabled-profile.md') {
                $startnumFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($startnumFixture));
        $startnumFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzz-startnum-disabled-profile.md', $startnumFixture['name']);
        $t->same('0393af1be681455314c76c66916a7d262ff1bbfddc323cba8bdf04001fc2a6a0', $startnumFixture['checkedInFile']['sha256']);
        $t->same(36, $startnumFixture['checkedInFile']['bytes']);
        $t->same('markdown-startnum+fancy_lists ordered-list start-number suppression', $startnumFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderStartnumProfileFixtureCompletionTest.php', $startnumFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $startnumFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderStartnumProfileFixtureCompletionTest.php', $startnumFixture['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $startnumFixture['localTestReferences'], true));

        $attributedSuperscriptFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-command-11589-attributed-superscript.md') {
                $attributedSuperscriptFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($attributedSuperscriptFixture));
        $attributedSuperscriptFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-command-11589-attributed-superscript.md', $attributedSuperscriptFixture['name']);
        $t->same('7e6fca282949f7571d555d7c58cc85d7434874a7b7774807cec0f03cb8623df4', $attributedSuperscriptFixture['checkedInFile']['sha256']);
        $t->same(44, $attributedSuperscriptFixture['checkedInFile']['bytes']);
        $t->same('markdown superscript/bracketed_spans/inline attributes', $attributedSuperscriptFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAttributedSuperscriptFixtureCompletionTest.php', $attributedSuperscriptFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $attributedSuperscriptFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderAttributedSuperscriptFixtureCompletionTest.php', $attributedSuperscriptFixture['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $attributedSuperscriptFixture['localTestReferences'], true));

        $gridTableSpanFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-reader-more-grid-table-spans.md') {
                $gridTableSpanFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($gridTableSpanFixture));
        $gridTableSpanFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-reader-more-grid-table-spans.md', $gridTableSpanFixture['name']);
        $t->same('d83277252dd4c4da6a66b08dc096b30d47afbf50823e9abf3db0e0f313aae727', $gridTableSpanFixture['checkedInFile']['sha256']);
        $t->same(880, $gridTableSpanFixture['checkedInFile']['bytes']);
        $t->same('markdown+grid_tables row-span, column-span, and complex-header profile', $gridTableSpanFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderGridTableSpanFixtureTest.php', $gridTableSpanFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $gridTableSpanFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderGridTableSpanFixtureTest.php', $gridTableSpanFixture['localTestReferences'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $gridTableSpanFixture['localTestReferences'], true));

        $citationLinkFollowingFixture = null;
        foreach ($evidence['checkedInFixtures'] as $checkedInFixture) {
            if (($checkedInFixture['name'] ?? null) === 'upstream-markdown-citation-link-following.md') {
                $citationLinkFollowingFixture = $checkedInFixture;
                break;
            }
        }
        $t->true(is_array($citationLinkFollowingFixture));
        $citationLinkFollowingFixture ??= ['checkedInFile' => [], 'coverageTests' => [], 'localTestReferences' => []];
        $t->same('upstream-markdown-citation-link-following.md', $citationLinkFollowingFixture['name']);
        $t->same('59149a056667d4bc32cd387324e59104f8ef85541130db1d9f86b4514b770e98', $citationLinkFollowingFixture['checkedInFile']['sha256']);
        $t->same(154, $citationLinkFollowingFixture['checkedInFile']['bytes']);
        $t->same('markdown citations followed by footnote, inline link, reference link, implicit header link, and suffix fallback', $citationLinkFollowingFixture['formatProfile']);
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderCitationLinkFollowingFixtureCompletionTest.php', $citationLinkFollowingFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $citationLinkFollowingFixture['coverageTests'], true));
        $t->true(in_array('lanes/pandoc/tests/MarkdownReaderCitationLinkFollowingFixtureCompletionTest.php', $citationLinkFollowingFixture['localTestReferences'], true));
        $t->same('valid-checked-in-current-markdown-reader-evidence', $evidence['validation']['status']);
        $t->same([], $evidence['validation']['issues']);
        $t->true(in_array('each selected fixture has at least one local PHP test reference', $evidence['claimBoundaries']['doesAssert'], true));
        $t->true(in_array('the selected checked-in Markdown native expectation snapshots match the expected deterministic manifest hash', $evidence['claimBoundaries']['doesAssert'], true));
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
            $t->same(124, $report['denominator']['selectedFixtureCount']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
            $t->same(true, MarkdownUpstreamReaderEvidence::hasRequiredNativeMappedParity($report, 124));
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
            . ' --require-selected-fixture-count=124'
            . ' --require-static-current-evidence'
            . ' --require-native-mapped-parity=124'
            . ' --require-runner-not-run'
            . ' --require-runner-plan';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(MarkdownUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $decoded['status']);
        $t->same(124, $decoded['staticCurrentEvidence']['readerDenominator']['selectedFixtureCount']);
        $t->same('valid-checked-in-current-markdown-reader-evidence', $decoded['staticCurrentEvidence']['validation']['status']);
        $t->same('valid-checked-in-current-markdown-native-expectation-evidence', $decoded['staticCurrentEvidence']['nativeExpectationEvidence']['validation']['status']);
        $t->same(MarkdownUpstreamReaderEvidence::EXPECTED_NATIVE_EXPECTATION_MANIFEST_SHA256, $decoded['staticCurrentEvidence']['nativeExpectationEvidence']['manifestSha256']);
        $t->same(124, $decoded['nativeAstEvidence']['normalizedAstMatchCount']);
        $t->same(0, $decoded['nativeAstEvidence']['normalizedAstMismatchCount']);
        $t->same('not-run', $decoded['runnerEvidence']['status']);
        $t->same('planned-not-run', $decoded['runnerEvidence']['commandPlanStatus']);
        $t->same('test:test-pandoc', $decoded['runnerEvidence']['target']['testSuite']);
        $t->same(['Readers', 'Markdown'], $decoded['runnerEvidence']['target']['tastyGroupPath']);
        $t->true(in_array('complete Markdown dialect parity across every Pandoc extension profile', $decoded['claimBoundaries']['doesNotAssert'], true));

        $failingCommand = str_replace('--require-selected-fixture-count=124', '--require-selected-fixture-count=125', $command) . ' 2>/dev/null';
        $failingOutput = [];
        $failingExitCode = 0;
        exec($failingCommand, $failingOutput, $failingExitCode);

        $t->same(1, $failingExitCode);

        $failingNativeCommand = str_replace('--require-native-mapped-parity=124', '--require-native-mapped-parity=125', $command) . ' 2>/dev/null';
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

    'generic reader runner artifact tool writes and validates markdown result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeMarkdownEvidenceTree, $writeRunnerTranscripts): void {
        $root = $makeTempDir();
        try {
            $writeMarkdownEvidenceTree($root);
            $baseReport = (new MarkdownUpstreamReaderEvidence($root, $root))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $testNames = array_map(
                static fn (array $fixture): string => $fixture['name'],
                $baseReport['denominator']['selectedFixtures']
            );
            $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts'], $testNames);

            $artifactPath = '.port-libs/pandoc-runner/artifacts/markdown-targeted-run/result.json';
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-reader-runner-artifact.php')
                . ' --repo-root=' . escapeshellarg($root)
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --format=markdown'
                . ' --write-result-artifact=' . escapeshellarg($artifactPath)
                . ' --result-started-at-utc=2026-07-05T00:00:00Z'
                . ' --result-finished-at-utc=2026-07-05T00:00:01Z'
                . ' --require-valid-result-artifact'
                . ' --json';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
            $writtenArtifact = $root . '/' . $artifactPath;
            $payload = json_decode((string) file_get_contents($writtenArtifact), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same('pandoc-reader-runner-artifact', $decoded['tool']);
            $t->same('markdown', $decoded['format']);
            $t->same('runner-result-artifact-valid', $decoded['status']);
            $t->same('valid-upstream-markdown-reader-runner-result-artifact', $decoded['validation']['status']);
            $t->same(true, $decoded['resultArtifact']['written']);
            $t->same($artifactPath, $decoded['resultArtifact']['path']);
            $t->same(true, $decoded['resultArtifact']['payload']['runnerExecuted']);
            $t->same(124, $decoded['resultArtifact']['payload']['testCount']);
            $t->same(124, $decoded['resultArtifact']['payload']['passedCount']);
            $t->same(0, $decoded['resultArtifact']['payload']['failedCount']);
            $t->same(124, count($decoded['expectedTestNames']));
            $t->same(2, $payload['schemaVersion']);
            $t->same('Cabal/Tasty Pandoc Markdown reader suite', $payload['runner']);
            $t->same(true, $payload['runnerExecuted']);
            $t->same(124, $payload['testCount']);
            $t->same(124, $payload['passedCount']);
            $t->same(0, $payload['failedCount']);
            $t->same('valid-targeted-runner-transcripts', $payload['transcriptEvidence']['status']);
            $t->same($runnerPlan['futureCommands'][2], $payload['command']);
            $t->same($runnerPlan['requiredTranscripts'], $payload['transcriptPaths']);
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
        $runnerWorkflow = file_get_contents($repoRoot . '/.github/workflows/pandoc-reader-runners.yml');
        if ($runnerWorkflow === false) {
            throw new RuntimeException('Unable to read pandoc-reader-runners workflow');
        }

        $t->contains('--require-selected-fixture-count=124', $workflow);
        $t->contains('--require-native-mapped-parity=124', $workflow);
        $t->contains('--require-mapped-parity=124', $workflow);
        $t->contains('--require-selected-fixture-count=124', $runnerWorkflow);
        $t->contains('--require-native-mapped-parity=124', $runnerWorkflow);
        $t->contains('lanes/pandoc/tests/MarkdownReaderAtxHeadingSpaceProfileSurgeTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownReaderCodeSpanFixtureCompletionTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownReaderInlineMathFixtureCompletionTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownReaderBareUriGitFileSchemesFixtureCompletionTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownReaderBareUriPathPunctuationFixtureCompletionTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownReaderBareUriRawHtmlAnchorFixtureCompletionTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownReaderBareUriQueryBoundaryFixtureCompletionTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownReaderBareUriSchemeBoundaryFixtureCompletionTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownReaderDestinationValidationCompletionTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownReaderGfmRawHtmlSplitAngleBoundaryFixtureCompletionTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownReaderGridTableSpanFixtureTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownReaderImplicitHeaderReferenceFixtureCompletionTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownReaderRawCodeProfileOverrideSurgeTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownReaderStartnumProfileFixtureCompletionTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/MarkdownUpstreamReaderEvidenceTest.php', $workflow);
        $t->contains('/test/Tests/Readers/Markdown.hs', $workflow);
        $t->contains('/src/Text/Pandoc/Readers/Markdown.hs', $workflow);
    },
];
