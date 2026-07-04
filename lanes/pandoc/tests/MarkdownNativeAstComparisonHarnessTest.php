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
            'upstream-markdown-ascii-identifiers',
            'upstream-markdown-fenced-div',
            'upstream-markdown-header-attributes',
            'upstream-markdown-line-blocks',
            'upstream-markdown-lhs-inverse-bird-html',
            'upstream-markdown-raw-html-invalid-tag',
            'upstream-markdown-pipe-table-escaped-cell',
            'upstream-markdown-raw-html-nesting',
            'upstream-markdown-z-lists-without-preceding-blankline-profile',
            'upstream-markdown-z-simple-table-profile',
            'upstream-markdown-zzz-intraword-underscore-profile',
            'upstream-markdown-zzzz-angle-brackets-escapable-profile',
            'upstream-markdown-zzzzz-wikilinks-title-after-pipe-profile',
            'upstream-markdown-zzzzzz-ignore-line-breaks-profile',
            'upstream-markdown-zzzzzzz-auto-identifiers-disabled-profile',
            'upstream-markdown-zzzzzzzz-blank-before-header-disabled-profile',
            'upstream-markdown-zzzzzzzzz-citation-digit-key',
            'upstream-markdown-zzzzzzzzzz-bare-uri-bracket-encoding',
        ];
        foreach ($paired as $basename) {
            $t->true(is_file($root . '/' . $basename . '.md'), "{$basename}.md must be checked in");
            $t->true(is_file($root . '/' . $basename . '.native'), "{$basename}.native must be checked in");
        }

        $harness = new MarkdownNativeAstComparisonHarness();
        $report = $harness->run($root);
        $text = $harness->formatReport($report);

        $t->same('completed', $report['status']);
        $t->same(76, $report['markdownFixtureCount']);
        $t->same(76, $report['nativeFixtureCount']);
        $t->same(76, $report['pairedFixtureCount']);
        $t->same(0, $report['unpairedMarkdownFixtureCount']);
        $t->same(0, $report['unpairedNativeFixtureCount']);
        $t->same(76, $report['totalPairCount']);
        $t->same(76, $report['comparedPairCount']);
        $t->same(76, $report['markdownParsedCount']);
        $t->same(76, $report['nativeParsedCount']);
        $t->same(76, $report['bothParsedCount']);
        $t->same(0, $report['parseFailureCount']);
        $t->same(76, $report['normalizedAstMatchCount']);
        $t->same(0, $report['normalizedAstMismatchCount']);
        $t->same('normalized-ast-equality-observed-not-runner-parity', $report['astParityStatus']);
        $t->same(true, MarkdownNativeAstComparisonHarness::hasRequiredMappedParity($report, 76));
        $t->same(false, MarkdownNativeAstComparisonHarness::hasRequiredMappedParity($report, 77));
        $t->same(['format' => 'markdown+ascii_identifiers'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-ascii-identifiers.md'] ?? null);
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
        $t->same(['format' => 'markdown+hard_line_breaks'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-hard-line-break-profile.md'] ?? null);
        $t->same(['format' => 'markdown+lists_without_preceding_blankline'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-lists-without-preceding-blankline-profile.md'] ?? null);
        $t->same(['format' => 'markdown_phpextra'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-phpextra-profile.md'] ?? null);
        $t->same(['format' => 'markdown+simple_tables'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-simple-table-profile.md'] ?? null);
        $t->same(['format' => 'markdown+short_subsuperscripts'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-short-subsuperscript-profile.md'] ?? null);
        $t->same(['format' => 'markdown-shortcut_reference_links+spaced_reference_links'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-spaced-reference-link-profile.md'] ?? null);
        $t->same(['format' => 'markdown+tex_math_double_backslash'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-tex-math-double-backslash-profile.md'] ?? null);
        $t->same(['format' => 'markdown+east_asian_line_breaks'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zz-east-asian-line-break-profile.md'] ?? null);
        $t->same(['format' => 'markdown+tex_math_single_backslash'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zz-tex-math-single-backslash-profile.md'] ?? null);
        $t->same(['format' => 'markdown-intraword_underscores'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzz-intraword-underscore-profile.md'] ?? null);
        $t->same(['format' => 'markdown-all_symbols_escapable+angle_brackets_escapable'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzz-angle-brackets-escapable-profile.md'] ?? null);
        $t->same(['format' => 'markdown+wikilinks_title_after_pipe'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzz-wikilinks-title-after-pipe-profile.md'] ?? null);
        $t->same(['format' => 'markdown+ignore_line_breaks'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzz-ignore-line-breaks-profile.md'] ?? null);
        $t->same(['format' => 'markdown-auto_identifiers'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzz-auto-identifiers-disabled-profile.md'] ?? null);
        $t->same(['format' => 'markdown-blank_before_header'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzz-blank-before-header-disabled-profile.md'] ?? null);
        $t->same(['format' => 'markdown+autolink_bare_uris'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzz-bare-uri-bracket-encoding.md'] ?? null);
        $t->same('covered-by-current-normalized-ast-evidence', $report['orderedRemainingGaps'][0]['status']);
        $t->same('checked-in-markdown-fixtures-without-native-pairs', $report['orderedRemainingGaps'][1]['id']);
        $t->same('covered-by-current-normalized-ast-evidence', $report['orderedRemainingGaps'][1]['status']);
        $t->same('Markdown fixtures=76; native fixtures=76; same-basename pairs=76; Markdown fixtures without native pairs=0', $report['orderedRemainingGaps'][1]['currentEvidence']);
        $t->same('The current checked-in gate covers 76 paired fixture(s) out of 76 selected Markdown fixture(s).', $report['orderedRemainingGaps'][3]['currentEvidence']);
        $t->contains('fixtureInventory: markdown=76 native=76 paired=76 unpairedMarkdown=0 unpairedNative=0', $text);
        $t->contains('pairs: total=76 compared=76 parsedBoth=76 parseFailures=0', $text);
        $t->contains('normalizedAst: matches=76 (100.00%) mismatches=0', $text);

        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-markdown-native-ast.php')
            . ' --markdown-dir=' . escapeshellarg($root)
            . ' --json'
            . ' summary'
            . ' --require-mapped-parity=76';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(76, $decoded['markdownFixtureCount']);
        $t->same(0, $decoded['unpairedMarkdownFixtureCount']);
        $t->same(76, $decoded['normalizedAstMatchCount']);
        $t->same(0, $decoded['normalizedAstMismatchCount']);
    },
];
