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
            'upstream-command-11253-latex-macros',
            'upstream-command-11253-latex-macros-disabled',
            'upstream-command-11542-definition-code-block',
            'upstream-command-7080-mmd-reference-image-attributes',
            'upstream-command-11589-attributed-superscript',
            'upstream-command-gfm-adjacent-emoji',
            'upstream-markdown-gfm-math-fence-profile',
            'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-github-yaml-profile',
            'upstream-markdown-alerts',
            'upstream-markdown-ascii-identifiers',
            'upstream-markdown-fenced-div',
            'upstream-markdown-header-attributes',
            'upstream-markdown-line-blocks',
            'upstream-markdown-lhs-inverse-bird-html',
            'upstream-markdown-ordered-task-list',
            'upstream-markdown-raw-html-invalid-tag',
            'upstream-markdown-pipe-table-escaped-cell',
            'upstream-markdown-raw-html-nesting',
            'upstream-markdown-reader-more-grid-table-spans',
            'upstream-markdown-z-lists-without-preceding-blankline-profile',
            'upstream-markdown-z-old-dashes-profile',
            'upstream-markdown-definition-list-laziness',
            'upstream-markdown-implicit-header-reference-atx',
            'upstream-markdown-implicit-header-reference-setext',
            'upstream-markdown-z-simple-table-profile',
            'upstream-markdown-zzz-intraword-underscore-profile',
            'upstream-markdown-zzzz-angle-brackets-escapable-profile',
            'upstream-markdown-zzzzz-wikilinks-title-after-pipe-profile',
            'upstream-markdown-zzzzzz-ignore-line-breaks-profile',
            'upstream-markdown-zzzzzzz-auto-identifiers-disabled-profile',
            'upstream-markdown-zzzzzzzz-blank-before-header-disabled-profile',
            'upstream-markdown-zzzzzzzzz-citation-digit-key',
            'upstream-markdown-zzzzzzzzzz-bare-uri-bracket-encoding',
            'upstream-markdown-zzzzzzzzzzz-mmd-title-block-profile',
            'upstream-markdown-zzzzzzzzzzzz-gfm-auto-identifiers-profile',
            'upstream-markdown-zzzzzzzzzzzzz-citation-link-boundaries',
            'upstream-markdown-zzzzzzzzzzzzzz-footnote-fenced-div-boundary',
            'upstream-markdown-zzzzzzzzzzzzzzz-raw-html-list-boundary',
            'upstream-markdown-zzzzzzzzzzzzzzzz-gfm-nested-list-continuation',
            'upstream-markdown-zzzzzzzzzzzzzzzzz-blank-before-blockquote-disabled-profile',
            'upstream-markdown-zzzzzzzzzzzzzzzzzz-blank-before-header-blockquote-profile',
            'upstream-markdown-zzzzzzzzzzzzzzzzzzz-mark-extension-profile',
            'upstream-markdown-zzzzzzzzzzzzzzzzzzzz-angle-autolink-unicode-dash-boundary',
            'upstream-markdown-zzzzzzzzzzzzzzzzzzzzz-partial-autolink-boundary',
            'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzz-gfm-raw-html-split-angle-boundary',
            'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-gfm-definition-list-profile',
            'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzz-bare-uri-scheme-boundaries',
            'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-bare-uri-query-boundaries',
            'upstream-markdown-bare-uri-query-hyphen-boundaries',
            'upstream-markdown-bare-uri-port-path-boundaries',
            'upstream-markdown-bare-uri-raw-html-anchor',
            'upstream-markdown-bare-uri-git-file-schemes',
            'upstream-markdown-bare-uri-path-punctuation',
            'upstream-markdown-bare-uri-unicode-path',
            'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzz-tex-math-dollar-display-boundary',
            'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzz-raw-html-inline-commonmark-profile',
            'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzz-atx-heading-space-disabled-profile',
            'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzz-startnum-disabled-profile',
            'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-in-html-blocks-profile',
            'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-attribute-profile',
            'upstream-markdown-destination-bare-space',
            'upstream-markdown-smart-french-apostrophe',
            'upstream-markdown-table-attributes-disabled-profile',
            'upstream-markdown-citation-link-following',
            'upstream-markdown-reader-more-raw-tex-environments',
            'upstream-markdown-reader-more-code-spans',
            'upstream-markdown-citation-simple-baseline',
            'upstream-markdown-reference-multiline-title',
            'upstream-markdown-z-fancy-list-parenthesized-profile',
        ];
        foreach ($paired as $basename) {
            $t->true(is_file($root . '/' . $basename . '.md'), "{$basename}.md must be checked in");
            $t->true(is_file($root . '/' . $basename . '.native'), "{$basename}.native must be checked in");
        }
        $t->true(
            is_file($root . '/upstream-command-11589-attributed-superscript.md'),
            'upstream-command-11589-attributed-superscript.md must be checked in'
        );
        $t->true(
            is_file($root . '/upstream-command-11589-attributed-superscript.native'),
            'upstream-command-11589-attributed-superscript.native must be checked in'
        );
        $t->true(
            is_file($root . '/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzz-tex-math-dollar-display-boundary.md'),
            'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzz-tex-math-dollar-display-boundary.md must be checked in'
        );
        $t->true(
            is_file($root . '/upstream-markdown-reader-more-raw-tex-environments.md'),
            'upstream-markdown-reader-more-raw-tex-environments.md must be checked in'
        );
        $t->true(
            is_file($root . '/upstream-markdown-reader-more-raw-tex-environments.native'),
            'upstream-markdown-reader-more-raw-tex-environments.native must be checked in'
        );
        $t->true(
            is_file($root . '/upstream-markdown-reader-more-code-spans.md'),
            'upstream-markdown-reader-more-code-spans.md must be checked in'
        );
        $t->true(
            is_file($root . '/upstream-markdown-reader-more-code-spans.native'),
            'upstream-markdown-reader-more-code-spans.native must be checked in'
        );

        $harness = new MarkdownNativeAstComparisonHarness();
        $report = $harness->run($root);
        $text = $harness->formatReport($report);

        $t->same('completed', $report['status']);
        $t->same(136, $report['markdownFixtureCount']);
        $t->same(136, $report['nativeFixtureCount']);
        $t->same(136, $report['pairedFixtureCount']);
        $t->same(0, $report['unpairedMarkdownFixtureCount']);
        $t->same(0, $report['unpairedNativeFixtureCount']);
        $t->same(136, $report['totalPairCount']);
        $t->same(136, $report['comparedPairCount']);
        $t->same(136, $report['markdownParsedCount']);
        $t->same(136, $report['nativeParsedCount']);
        $t->same(136, $report['bothParsedCount']);
        $t->same(0, $report['parseFailureCount']);
        $t->same(136, $report['normalizedAstMatchCount']);
        $t->same(0, $report['normalizedAstMismatchCount']);
        $t->same('normalized-ast-equality-observed-not-runner-parity', $report['astParityStatus']);
        $t->same(true, MarkdownNativeAstComparisonHarness::hasRequiredMappedParity($report, 136));
        $t->same(false, MarkdownNativeAstComparisonHarness::hasRequiredMappedParity($report, 137));
        $t->same(['format' => 'markdown-latex_macros'], $report['markdownReaderFixtureOptionOverrides']['upstream-command-11253-latex-macros-disabled.md'] ?? null);
        $t->same(['format' => 'markdown_mmd'], $report['markdownReaderFixtureOptionOverrides']['upstream-command-7080-mmd-reference-image-attributes.md'] ?? null);
        $t->same(['format' => 'gfm'], $report['markdownReaderFixtureOptionOverrides']['upstream-command-gfm-adjacent-emoji.md'] ?? null);
        $t->same(['format' => 'markdown+ascii_identifiers'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-ascii-identifiers.md'] ?? null);
        $t->same(['format' => 'gfm'], $report['markdownReaderFixtureOptionOverrides']['upstream-command-gfm-details-list.md'] ?? null);
        $t->same(['format' => 'gfm'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-alerts.md'] ?? null);
        $t->same(['format' => 'markdown+grid_tables'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-reader-more-grid-table-spans.md'] ?? null);
        $t->same(['format' => 'commonmark_x'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-commonmark-x-grid-table-default.md'] ?? null);
        $t->same(['format' => 'markdown+definition_lists'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-definition-list-laziness.md'] ?? null);
        $t->same(['format' => 'markdown+emoji'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-emoji-symbols.md'] ?? null);
        $t->same(['format' => 'markdown_github+wikilinks_title_before_pipe'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-github-wikilinks.md'] ?? null);
        $t->same(['format' => 'gfm'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-gfm-math-fence-profile.md'] ?? null);
        $t->same(['format' => 'markdown+line_blocks'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-line-blocks.md'] ?? null);
        $t->same(['format' => 'markdown+lhs'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-lhs-inverse-bird-html.md'] ?? null);
        $t->same(['format' => 'markdown-citations'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-raw-email-address.md'] ?? null);
        $t->same(['format' => 'markdown_strict'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-strict-compact-heading.md'] ?? null);
        $t->same(['format' => 'markdown-table_attributes'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-table-attributes-disabled-profile.md'] ?? null);
        $t->same(['format' => 'markdown+fancy_lists'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-fancy-list-markers.md'] ?? null);
        $t->same(['format' => 'markdown+fancy_lists'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-fancy-list-parenthesized-profile.md'] ?? null);
        $t->same(['format' => 'markdown+hard_line_breaks'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-hard-line-break-profile.md'] ?? null);
        $t->same(['format' => 'markdown+lists_without_preceding_blankline'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-lists-without-preceding-blankline-profile.md'] ?? null);
        $t->same(['format' => 'markdown+old_dashes+smart'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-z-old-dashes-profile.md'] ?? null);
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
        $t->same(['format' => 'markdown_mmd'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzz-mmd-title-block-profile.md'] ?? null);
        $t->same(['format' => 'markdown+task_lists'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-ordered-task-list.md'] ?? null);
        $t->same(['format' => 'commonmark+gfm_auto_identifiers+ascii_identifiers'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzz-gfm-auto-identifiers-profile.md'] ?? null);
        $t->same(['format' => 'markdown+footnotes+fenced_divs+native_divs'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzz-footnote-fenced-div-boundary.md'] ?? null);
        $t->same(['format' => 'gfm'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzzzz-gfm-nested-list-continuation.md'] ?? null);
        $t->same(['format' => 'markdown-blank_before_blockquote'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzzzzz-blank-before-blockquote-disabled-profile.md'] ?? null);
        $t->same(['format' => 'markdown-blank_before_header'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzzzzzz-blank-before-header-blockquote-profile.md'] ?? null);
        $t->same(['format' => 'markdown+mark'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzzzzzzz-mark-extension-profile.md'] ?? null);
        $t->same(['format' => 'gfm'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzzzzzzzzzz-gfm-raw-html-split-angle-boundary.md'] ?? null);
        $t->same(['format' => 'gfm+definition_lists'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-gfm-definition-list-profile.md'] ?? null);
        $t->same(['format' => 'markdown+autolink_bare_uris'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzz-bare-uri-scheme-boundaries.md'] ?? null);
        $t->same(['format' => 'markdown+autolink_bare_uris'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-bare-uri-query-boundaries.md'] ?? null);
        $t->same(['format' => 'markdown+autolink_bare_uris'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-bare-uri-port-path-boundaries.md'] ?? null);
        $t->same(['format' => 'markdown+autolink_bare_uris+raw_html'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-bare-uri-raw-html-anchor.md'] ?? null);
        $t->same(['format' => 'markdown+autolink_bare_uris'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-bare-uri-git-file-schemes.md'] ?? null);
        $t->same(['format' => 'markdown+autolink_bare_uris+raw_html'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-bare-uri-path-punctuation.md'] ?? null);
        $t->same(['format' => 'markdown+autolink_bare_uris'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-bare-uri-unicode-path.md'] ?? null);
        $t->same(['format' => 'markdown+citations'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-citation-simple-baseline.md'] ?? null);
        $t->same(['format' => 'markdown+smart'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-smart-french-apostrophe.md'] ?? null);
        $t->same(['format' => 'markdown+smart'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-smart-inline-note-double-quotes.md'] ?? null);
        $t->same(['format' => 'commonmark+raw_attribute'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzz-raw-html-inline-commonmark-profile.md'] ?? null);
        $t->same(['format' => 'markdown-space_in_atx_header'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzz-atx-heading-space-disabled-profile.md'] ?? null);
        $t->same(['format' => 'markdown-startnum+fancy_lists'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzz-startnum-disabled-profile.md'] ?? null);
        $t->same(['format' => 'markdown+markdown_in_html_blocks'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-in-html-blocks-profile.md'] ?? null);
        $t->same(['format' => 'markdown_phpextra'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-attribute-profile.md'] ?? null);
        $t->same(['format' => 'markdown_github'], $report['markdownReaderFixtureOptionOverrides']['upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-github-yaml-profile.md'] ?? null);
        $t->same('covered-by-current-normalized-ast-evidence', $report['orderedRemainingGaps'][0]['status']);
        $t->same('checked-in-markdown-fixtures-without-native-pairs', $report['orderedRemainingGaps'][1]['id']);
        $t->same('covered-by-current-normalized-ast-evidence', $report['orderedRemainingGaps'][1]['status']);
        $t->same('Markdown fixtures=136; native fixtures=136; same-basename pairs=136; Markdown fixtures without native pairs=0', $report['orderedRemainingGaps'][1]['currentEvidence']);
        $t->same('The current checked-in gate covers 136 paired fixture(s) out of 136 selected Markdown fixture(s).', $report['orderedRemainingGaps'][3]['currentEvidence']);
        $t->contains('fixtureInventory: markdown=136 native=136 paired=136 unpairedMarkdown=0 unpairedNative=0', $text);
        $t->contains('pairs: total=136 compared=136 parsedBoth=136 parseFailures=0', $text);
        $t->contains('normalizedAst: matches=136 (100.00%) mismatches=0', $text);

        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-markdown-native-ast.php')
            . ' --markdown-dir=' . escapeshellarg($root)
            . ' --json'
            . ' summary'
            . ' --require-mapped-parity=136';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(136, $decoded['markdownFixtureCount']);
        $t->same(0, $decoded['unpairedMarkdownFixtureCount']);
        $t->same(136, $decoded['normalizedAstMatchCount']);
        $t->same(0, $decoded['normalizedAstMismatchCount']);
    },
];
