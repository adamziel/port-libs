<?php

declare(strict_types=1);

use PortLibs\Pandoc\MarkdownNativeAstComparisonHarness;

$fixtureRoot = static fn (): string => dirname(__DIR__) . '/fixtures';

return [
    'skips markdown native ast comparison when source directory is absent' => static function (TestRunner $t): void {
        $missing = sys_get_temp_dir() . '/missing-markdown-native-' . bin2hex(random_bytes(4));
        $report = (new MarkdownNativeAstComparisonHarness())->run($missing);
        $text = (new MarkdownNativeAstComparisonHarness())->formatReport($report);

        $t->same('skipped', $report['status']);
        $t->same(true, $report['skipped']);
        $t->same('markdown-native-fixture-directory-missing', $report['reason']);
        $t->same(0, $report['comparedPairCount']);
        $t->same('not-evaluated-source-directory-unavailable', $report['astParityStatus']);
        $t->contains('Pandoc Markdown/native AST comparison: skipped', $text);
    },

    'checked-in markdown fixtures match native ast shape for paired seed corpus' => static function (TestRunner $t) use ($fixtureRoot): void {
        $root = $fixtureRoot();
        $paired = [
            'upstream-markdown-alerts',
            'upstream-markdown-fenced-div',
            'upstream-markdown-header-attributes',
            'upstream-markdown-line-blocks',
            'upstream-markdown-lhs-inverse-bird-html',
            'upstream-markdown-pipe-table-escaped-cell',
            'upstream-markdown-raw-html-nesting',
        ];
        foreach ($paired as $basename) {
            $t->true(is_file($root . '/' . $basename . '.md'), "{$basename}.md must be checked in");
            $t->true(is_file($root . '/' . $basename . '.native'), "{$basename}.native must be checked in");
        }

        $harness = new MarkdownNativeAstComparisonHarness();
        $report = $harness->run($root);
        $text = $harness->formatReport($report);

        $t->same('completed', $report['status']);
        $t->same(56, $report['markdownFixtureCount']);
        $t->same(56, $report['nativeFixtureCount']);
        $t->same(56, $report['pairedFixtureCount']);
        $t->same(0, $report['unpairedMarkdownFixtureCount']);
        $t->same(0, $report['unpairedNativeFixtureCount']);
        $t->same(56, $report['totalPairCount']);
        $t->same(56, $report['comparedPairCount']);
        $t->same(56, $report['markdownParsedCount']);
        $t->same(56, $report['nativeParsedCount']);
        $t->same(56, $report['bothParsedCount']);
        $t->same(0, $report['parseFailureCount']);
        $t->same(56, $report['normalizedAstMatchCount']);
        $t->same(0, $report['normalizedAstMismatchCount']);
        $t->same('normalized-ast-equality-observed-not-runner-parity', $report['astParityStatus']);
        $t->same(true, MarkdownNativeAstComparisonHarness::hasRequiredMappedParity($report, 56));
        $t->same(false, MarkdownNativeAstComparisonHarness::hasRequiredMappedParity($report, 57));
        $t->same(['format' => 'gfm'], $report['markdownReaderFixtureOptionOverrides']['upstream-command-gfm-details-list.md'] ?? null);
        $t->same(['format' => 'gfm'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-alerts.md'] ?? null);
        $t->same(['format' => 'commonmark_x'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-commonmark-x-grid-table-default.md'] ?? null);
        $t->same(['format' => 'markdown+emoji'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-emoji-symbols.md'] ?? null);
        $t->same(['format' => 'gfm+wikilinks_title_before_pipe'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-github-wikilinks.md'] ?? null);
        $t->same(['format' => 'markdown+line_blocks'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-line-blocks.md'] ?? null);
        $t->same(['format' => 'markdown+lhs'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-lhs-inverse-bird-html.md'] ?? null);
        $t->same(['format' => 'markdown-citations'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-raw-email-address.md'] ?? null);
        $t->same(['format' => 'markdown_strict'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-strict-compact-heading.md'] ?? null);
        $t->same(['format' => 'markdown+fancy_lists'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-fancy-list-markers.md'] ?? null);
        $t->same('covered-by-current-normalized-ast-evidence', $report['orderedRemainingGaps'][0]['status']);
        $t->same('checked-in-markdown-fixtures-without-native-pairs', $report['orderedRemainingGaps'][1]['id']);
        $t->same('covered-by-current-normalized-ast-evidence', $report['orderedRemainingGaps'][1]['status']);
        $t->same('Markdown fixtures=56; native fixtures=56; same-basename pairs=56; Markdown fixtures without native pairs=0', $report['orderedRemainingGaps'][1]['currentEvidence']);
        $t->same('The current checked-in gate covers 56 paired fixture(s) out of 56 selected Markdown fixture(s).', $report['orderedRemainingGaps'][3]['currentEvidence']);
        $t->contains('fixtureInventory: markdown=56 native=56 paired=56 unpairedMarkdown=0 unpairedNative=0', $text);
        $t->contains('pairs: total=56 compared=56 parsedBoth=56 parseFailures=0', $text);
        $t->contains('normalizedAst: matches=56 (100.00%) mismatches=0', $text);

        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-markdown-native-ast.php')
            . ' --markdown-dir=' . escapeshellarg($root)
            . ' --json'
            . ' summary'
            . ' --require-mapped-parity=56';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(56, $decoded['markdownFixtureCount']);
        $t->same(0, $decoded['unpairedMarkdownFixtureCount']);
        $t->same(56, $decoded['normalizedAstMatchCount']);
        $t->same(0, $decoded['normalizedAstMismatchCount']);
    },
];
