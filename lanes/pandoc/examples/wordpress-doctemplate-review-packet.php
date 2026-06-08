<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\DocTemplate;
use PortLibs\Pandoc\UnicodeText;

$templatePath = 'review-packets/review';
$resources = [
    'review-packets/review.html' => <<<'HTML'
<article class="wp-import-review">
${ components/review-header() }
${ components/admin-note() }
${ components/résumé() }
${ components/warning-list() }
${ review-body() }
</article>
HTML,
    'review-packets/components/review-header.html' => <<<'HTML'
<header>
<h1>$title/uppercase$</h1>
<p class="summary">$~$$warnings/length$
warnings	queued
for $title$$~$</p>
<p class="next-warning">${ warnings/rest/first:components/next-warning()/uppercase }</p>
<p class="ticket">Ticket: ${ review-id/left 8 "[" "]" }</p>
<p class="authors">$for(authors/pairs)$$it.value.name$$sep$, $endfor$</p>
<p class="review-sources">${ reviewSources/rest/uppercase[ / ] }</p>
<p class="brace-separated-sources">${ reviewSources/uppercase[} ] }</p>
<p class="chomped-review-sources">${ reviewSourcesWithNewlines/chomp/uppercase[ / ] }</p>
<p class="review-meta">$for(reviewMeta/pairs)$$it.key$=$it.value$$sep$; $endfor$</p>
<p class="style-metadata">$style:font-name$ / ${ style:family:components/style-token()[, ] }</p>
<p class="digit-metadata">$reviewNumbers.2026-batch.status$ / $reviewNumbers.1st-pass$ / ${ assets.360-view:components/asset-digit-row()[, ] }</p>
<p class="derived-missing-count">${ missingWarnings/length:components/missing-count() }</p>
<pre class="plain-text-summary">$wrappedPlainSummary$</pre>
<p class="labeled-note">$^$Note: $summaryNote$</p>
<p class="dedented-note">$^$$dedentedNote$
</p>
<p>$^$$reviewStatus$
   Owner: $reviewOwner$</p>
<p class="comment-spacing">Before preserved comment whitespace</p>
  $-- indented reviewer comments preserve their line ending upstream
<p class="comment-spacing">After preserved comment whitespace</p>
<p class="audit-flag" data-suppressed="$suppressed$">Suppressed: <$suppressed$></p>
${ components/crlf-note() }
${ components/trailing-note() }
</header>
HTML,
    'review-packets/components/admin-note.html' => <<<'HTML'
$if(adminNote)$<aside class="admin-note">$adminNote$</aside>$endif$
HTML,
    'review-packets/components/résumé.html' => <<<'HTML'
<p class="source-summary" data-état="$révision.état$">$révision.titre$ $^$$révision.note$</p>
HTML,
    'review-packets/components/next-warning.html' => '$warnings.source$/$it.source$: $warnings.message$',
    'review-packets/components/missing-count.html' => 'missing=<$missingWarnings$>; it=$it$',
    'review-packets/components/style-token.html' => '$style:family.style:name$=$it.style:font-name$',
    'review-packets/components/asset-digit-row.html' => '$assets.360-view.name$:$it.1st-pass$',
    'review-packets/components/crlf-note.html' => '<p class="crlf-note">CRLF partial final line ending stripped</p>' . "\r\n",
    'review-packets/components/trailing-note.html' => '<p class="partial-spacing">Partial spacing survives reviewer packet boundaries</p>' . "\n\n",
    'review-packets/components/warning-list.html' => <<<'HTML'
$if(warnings)$
<ul class="warnings">
$for(warnings)$${ components/warning-row() }
$endfor$</ul>
$endif$
HTML,
    'review-packets/components/warning-row.html' => <<<'HTML'
<li data-index="$it.index$" data-source="$it.source$" data-review-title="$title$">$~$<span class="marker">$it.index/alpha/uppercase$.</span> <span class="source">${ it.source/uppercase/left 8 "{" "}" }</span> <span class="priority">$it.priority/roman/uppercase/right 4$</span>
$it.message$$~$</li>
HTML,
    'wp-data/templates/review-body.html' => <<<'HTML'
<section class="wp-import-body">
  $^$$body$
</section>
HTML,
];

$context = [
    'title' => 'Batch 42 Review',
    'authors' => [
        ['name' => 'Migration bot'],
        ['name' => 'Content editor'],
    ],
    'reviewSources' => ['media', 'links', 'layout'],
    'reviewSourcesWithNewlines' => ["media\n", "links\n\n", "layout\r\n"],
    'reviewMeta' => [
        'zeta' => 'queued-last',
        'alpha' => 'queued-first',
        'review-id' => 'PR-42',
    ],
    'reviewNumbers' => [
        '2026-batch' => ['status' => 'queued'],
        '1st-pass' => 'metadata',
    ],
    'assets' => [
        '360-view' => [
            ['name' => 'cover-spin', '1st-pass' => 'ok'],
            ['name' => 'layout-spin', '1st-pass' => 'review'],
        ],
    ],
    'style:font-name' => 'Atkinson Hyperlegible',
    'style:family' => [
        [
            'style:name' => 'Heading_20_1',
            'style:font-name' => 'Alegreya',
        ],
        [
            'style:name' => 'BodyText',
            'style:font-name' => 'Atkinson Hyperlegible',
        ],
    ],
    'summaryNote' => "Review imported title blocks\nConfirm reviewer packet spacing",
    'dedentedNote' => 'Dedented review packet close',
    'reviewStatus' => 'Ready for import',
    'reviewOwner' => "Migration desk\nReview desk",
    'suppressed' => false,
    'révision' => [
        'état' => 'prêt',
        'titre' => 'Résumé de migration',
        'note' => "Première ligne\nDeuxième ligne",
    ],
    'warnings' => [
        ['index' => 1, 'title' => 'Media warning', 'source' => 'media', 'priority' => 1, 'message' => 'Check &amp; confirm alt text'],
        ['index' => 2, 'title' => 'Link warning', 'source' => 'links', 'priority' => 4, 'message' => 'Verify edit links before publish'],
        ['index' => 3, 'title' => 'Multilingual warning', 'source' => '魚', 'priority' => 9, 'message' => 'Confirm multilingual source label spacing'],
    ],
    'body' => implode("\n", [
        '<!-- wp:paragraph --><p>Imported body is already escaped.</p><!-- /wp:paragraph -->',
        '<!-- wp:paragraph --><p>Second reviewer packet line stays nested.</p><!-- /wp:paragraph -->',
    ]) . "\n",
];

for ($index = 4; $index <= 27; $index++) {
    $context['warnings'][] = [
        'index' => $index,
        'title' => 'Generated warning ' . $index,
        'source' => 'batch-' . $index,
        'priority' => 1,
        'message' => 'Generated reviewer checkpoint ' . $index,
    ];
}

$renderer = new DocTemplate();
$context['wrappedPlainSummary'] = $renderer->renderWrapped(
    '$~$Review queue includes media links layout and multilingual source packet follow-ups.$~$',
    [],
    48,
);

$output = $renderer->renderResource($templatePath, $resources, $context, 'wp-data', 'html');

if (in_array('--self-test', $argv, true)) {
    $sourceSummaryPrefix = '<p class="source-summary" data-état="prêt">Résumé de migration ';
    $labeledNotePrefix = '<p class="labeled-note">';
    foreach ([
        '<h1>BATCH 42 REVIEW</h1>',
        '<p class="summary">27 warnings queued for Batch 42 Review</p>',
        '<p class="next-warning">LINKS/LINKS: VERIFY EDIT LINKS BEFORE PUBLISH</p>',
        '<p class="ticket">Ticket: [        ]</p>',
        '<p class="authors">Migration bot, Content editor</p>',
        '<p class="review-sources">LINKS / LAYOUT</p>',
        '<p class="brace-separated-sources">MEDIA} LINKS} LAYOUT</p>',
        '<p class="chomped-review-sources">MEDIA / LINKS / LAYOUT</p>',
        '<p class="review-meta">alpha=queued-first; review-id=PR-42; zeta=queued-last</p>',
        '<p class="style-metadata">Atkinson Hyperlegible / Heading_20_1=Alegreya, BodyText=Atkinson Hyperlegible</p>',
        '<p class="digit-metadata">queued / metadata / cover-spin:ok, layout-spin:review</p>',
        '<p class="derived-missing-count">missing=<>; it=0</p>',
        "<pre class=\"plain-text-summary\">Review queue includes media links layout and\nmultilingual source packet follow-ups.</pre>",
        $labeledNotePrefix . 'Note: Review imported title blocks' . "\n"
            . str_repeat(' ', UnicodeText::displayWidth($labeledNotePrefix))
            . 'Confirm reviewer packet spacing</p>',
        "<p class=\"dedented-note\">Dedented review packet close\n</p>",
        "<p>Ready for import\n   Owner: Migration desk\n   Review desk</p>",
        "<p class=\"comment-spacing\">Before preserved comment whitespace</p>\n  \n<p class=\"comment-spacing\">After preserved comment whitespace</p>",
        '<p class="audit-flag" data-suppressed="">Suppressed: <></p>',
        "<p class=\"crlf-note\">CRLF partial final line ending stripped</p>\n<p class=\"partial-spacing\">Partial spacing survives reviewer packet boundaries</p>",
        "<p class=\"partial-spacing\">Partial spacing survives reviewer packet boundaries</p>\n\n</header>",
        $sourceSummaryPrefix . 'Première ligne' . "\n"
            . str_repeat(' ', UnicodeText::displayWidth($sourceSummaryPrefix))
            . 'Deuxième ligne</p>',
        '<li data-index="1" data-source="media" data-review-title="Batch 42 Review"><span class="marker">A.</span> <span class="source">{MEDIA   }</span> <span class="priority">   I</span> Check &amp; confirm alt text</li>',
        '<li data-index="2" data-source="links" data-review-title="Batch 42 Review"><span class="marker">B.</span> <span class="source">{LINKS   }</span> <span class="priority">  IV</span> Verify edit links before publish</li>',
        '<li data-index="3" data-source="魚" data-review-title="Batch 42 Review"><span class="marker">C.</span> <span class="source">{魚      }</span> <span class="priority">  IX</span> Confirm multilingual source label spacing</li>',
        '<li data-index="26" data-source="batch-26" data-review-title="Batch 42 Review"><span class="marker">Z.</span> <span class="source">{BATCH-26}</span> <span class="priority">   I</span> Generated reviewer checkpoint 26</li>',
        '<li data-index="27" data-source="batch-27" data-review-title="Batch 42 Review"><span class="marker">AA.</span> <span class="source">{BATCH-27}</span> <span class="priority">   I</span> Generated reviewer checkpoint 27</li>',
        '  <!-- wp:paragraph --><p>Imported body is already escaped.</p><!-- /wp:paragraph -->',
        '  <!-- wp:paragraph --><p>Second reviewer packet line stays nested.</p><!-- /wp:paragraph -->',
    ] as $needle) {
        if (!str_contains($output, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate output: {$needle}\n");
            exit(1);
        }
    }

    $trimmedDelimiterPacket = (new DocTemplate())->render(
        "<div class=\"delimiter-trim\">\n"
            . '$if(reviewStatus)$' . "   \t\n"
            . '$reviewStatus$' . "   \t\n"
            . '$endif$' . "   \t\n"
            . '</div>',
        ['reviewStatus' => 'Ready for import'],
    );
    if ($trimmedDelimiterPacket !== "<div class=\"delimiter-trim\">\nReady for import\n</div>") {
        fwrite(STDERR, "Unexpected doctemplate delimiter whitespace output\n");
        exit(1);
    }

    $crOnlyNestingPacket = (new DocTemplate())->render(
        "<section class=\"legacy-cr-review\">\r  " . '$body$' . "\r</section>",
        [
            'body' => "<!-- wp:paragraph --><p>Legacy CR body.</p><!-- /wp:paragraph -->\r"
                . '<!-- wp:paragraph --><p>Nested CR line.</p><!-- /wp:paragraph -->',
        ],
    );
    if ($crOnlyNestingPacket !== "<section class=\"legacy-cr-review\">\r  <!-- wp:paragraph --><p>Legacy CR body.</p><!-- /wp:paragraph -->\r  <!-- wp:paragraph --><p>Nested CR line.</p><!-- /wp:paragraph -->\r</section>") {
        fwrite(STDERR, "Unexpected doctemplate CR-only automatic nesting output\n");
        exit(1);
    }

    if (str_contains($output, "\n\n</section>")) {
        fwrite(STDERR, "Unexpected blank line before doctemplate review body close\n");
        exit(1);
    }

    if (str_contains($output, "</p>\n\n<ul class=\"warnings\">") || str_contains($output, "<ul class=\"warnings\">\n\n<li")) {
        fwrite(STDERR, "Unexpected blank line from multiline doctemplate warning-list controls\n");
        exit(1);
    }

    if (str_contains($output, "</header>\n\n<p class=\"source-summary\"")) {
        fwrite(STDERR, "Unexpected blank line from empty standalone doctemplate partial\n");
        exit(1);
    }

    if (str_contains($output, "\r")) {
        fwrite(STDERR, "Unexpected CR byte from included doctemplate partial final line ending\n");
        exit(1);
    }

    $extensionFallback = (new DocTemplate())->renderResource('packets/review', [
        'packets/review.html' => '<p>$title$</p>',
    ], [
        'title' => 'Extension fallback',
    ], null, 'html');
    if ($extensionFallback !== '<p>Extension fallback</p>') {
        fwrite(STDERR, "Missing expected doctemplate extension fallback\n");
        exit(1);
    }

    $extensionQualifiedFallback = (new DocTemplate())->renderResource('packets/review', [
        'packets/review.html' => '<p class="extension-qualified">$body$</p>',
    ], [
        'body' => 'Extension-qualified format fallback',
    ], null, 'html+smart-native_divs');
    if ($extensionQualifiedFallback !== '<p class="extension-qualified">Extension-qualified format fallback</p>') {
        fwrite(STDERR, "Missing expected doctemplate extension-qualified format fallback\n");
        exit(1);
    }

    $extensionQualifiedDefault = (new DocTemplate())->renderResource('templates/default', [], [
        'body' => 'Extension-qualified Markdown default fallback',
    ], null, 'markdown_strict+emoji-hard_line_breaks');
    if ($extensionQualifiedDefault !== "Extension-qualified Markdown default fallback\n") {
        fwrite(STDERR, "Missing expected doctemplate extension-qualified default fallback\n");
        exit(1);
    }

    $html4DefaultAlias = (new DocTemplate())->renderResource('templates/default', [], [
        'pandoc-version' => '3.7.0',
        'pagetitle' => 'HTML4 Alias Review',
        'body' => '<p>HTML4 alias review body.</p>',
    ], null, 'html4+smart');
    foreach ([
        '<!DOCTYPE html>',
        '<title>HTML4 Alias Review</title>',
        '<p>HTML4 alias review body.</p>',
    ] as $needle) {
        if (!str_contains($html4DefaultAlias, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate html4 default alias fallback: {$needle}\n");
            exit(1);
        }
    }

    $nestedDefaultPartialFallback = (new DocTemplate())->renderResource('packets/review.html', [
        'packets/review.html' => <<<'HTML'
<article class="nested-default-partial">
<style>
${ components/styles.html() }
</style>
<section>${ fragments/default.plain() }</section>
</article>
HTML,
    ], [
        'document-css' => true,
        'mainfont' => 'Atkinson Hyperlegible',
        'csl-css' => true,
        'csl-entry-spacing' => '0.75em',
        'body' => 'Nested default partial body',
    ], null, 'html');
    foreach ([
        '/* Default styles provided by pandoc.',
        'font-family: Atkinson Hyperlegible;',
        '/* CSS for citations */',
        'margin-bottom: 0.75em;',
        '<section>Nested default partial body</section>',
    ] as $needle) {
        if (!str_contains($nestedDefaultPartialFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate nested default partial fallback: {$needle}\n");
            exit(1);
        }
    }

    $defaultFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'lang' => 'en',
        'dir' => 'ltr',
        'pandoc-version' => '3.7.0',
        'title-prefix' => 'WordPress Import',
        'pagetitle' => 'Default Template Packet',
        'title' => 'Default Template Review',
        'subtitle' => 'Native metadata handoff',
        'author' => ['Migration bot', 'Content editor'],
        'author-meta' => ['Migration bot', 'Content editor'],
        'date' => '2026-06-05',
        'date-meta' => '2026-06-05',
        'keywords' => ['migration', 'wordpress', 'review'],
        'description-meta' => 'Default template review packet',
        'css' => ['review.css'],
        'math' => '<script type="math/tex">queued</script>',
        'abstract-title' => 'Abstract',
        'abstract' => '<p>Default template metadata survives.</p>',
        'toc' => true,
        'idprefix' => 'wp-review-',
        'toc-title' => 'Contents',
        'table-of-contents' => '<ul><li>Default body</li></ul>',
        'body' => '<!-- wp:paragraph --><p>Default body.</p><!-- /wp:paragraph -->',
        'document-css' => true,
        'mainfont' => 'Atkinson Hyperlegible',
        'fontcolor' => '#202124',
        'backgroundcolor' => '#ffffff',
        'linkcolor' => '#135e96',
        'table-caption-below' => true,
        'displaymath-css' => true,
        'highlighting-css' => '.sourceCode .kw { color: #005cc5; }',
        'csl-css' => true,
        'csl-entry-spacing' => '0.5em',
    ], null, 'html');
    foreach ([
        '<!DOCTYPE html>',
        '<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en" dir="ltr">',
        '<meta charset="utf-8" />',
        '<meta name="generator" content="pandoc 3.7.0" />',
        '<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />',
        '<meta name="author" content="Migration bot" />',
        '<meta name="author" content="Content editor" />',
        '<meta name="dcterms.date" content="2026-06-05" />',
        '<meta name="keywords" content="migration, wordpress, review" />',
        '<meta name="description" content="Default template review packet" />',
        '<title>WordPress Import &ndash; Default Template Packet</title>',
        '<link rel="stylesheet" href="review.css" />',
        '<script type="math/tex">queued</script>',
        '/* Default styles provided by pandoc.',
        'font-family: Atkinson Hyperlegible;',
        'color: #202124;',
        'background-color: #ffffff;',
        'color: #135e96;',
        'caption-side: bottom;',
        '.display.math{display: block; text-align: center; margin: 0.5rem auto;}',
        '/* CSS for syntax highlighting */',
        '.sourceCode .kw { color: #005cc5; }',
        '/* CSS for citations */',
        'margin-bottom: 0.5em;',
        '<h1 class="title">Default Template Review</h1>',
        '<p class="subtitle">Native metadata handoff</p>',
        '<p class="author">Migration bot</p>',
        '<p class="author">Content editor</p>',
        '<p class="date">2026-06-05</p>',
        '<div class="abstract-title">Abstract</div>',
        '<p>Default template metadata survives.</p>',
        '<nav id="wp-review-TOC" role="doc-toc">',
        '<h2 id="wp-review-toc-title">Contents</h2>',
        '<ul><li>Default body</li></ul>',
        '<!-- wp:paragraph --><p>Default body.</p><!-- /wp:paragraph -->',
    ] as $needle) {
        if (!str_contains($defaultFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate default template fallback: {$needle}\n");
            exit(1);
        }
    }

    $markdownFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'titleblock' => "# Markdown Review Packet\n\nNative default handoff",
        'header-includes' => ['<!-- wp:html --><aside>Header audit</aside><!-- /wp:html -->'],
        'include-before' => ['<!-- wp:paragraph --><p>Before Markdown body.</p><!-- /wp:paragraph -->'],
        'toc' => true,
        'table-of-contents' => '- [Body](#body)',
        'body' => "## Body\n\n<!-- wp:paragraph --><p>Markdown body.</p><!-- /wp:paragraph -->",
        'include-after' => ['<!-- wp:paragraph --><p>After Markdown body.</p><!-- /wp:paragraph -->'],
    ], null, 'gfm');
    foreach ([
        "# Markdown Review Packet\n\nNative default handoff",
        "<!-- wp:html --><aside>Header audit</aside><!-- /wp:html -->\n\n<!-- wp:paragraph --><p>Before Markdown body.</p><!-- /wp:paragraph -->",
        "- [Body](#body)\n\n## Body",
        "<!-- wp:paragraph --><p>Markdown body.</p><!-- /wp:paragraph -->\n\n<!-- wp:paragraph --><p>After Markdown body.</p><!-- /wp:paragraph -->",
    ] as $needle) {
        if (!str_contains($markdownFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate markdown default fallback: {$needle}\n");
            exit(1);
        }
    }

    $commonmarkFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'body' => 'CommonMark review body',
    ], null, 'commonmark_x');
    if ($commonmarkFallback !== "CommonMark review body\n") {
        fwrite(STDERR, "Missing expected doctemplate commonmark_x default fallback\n");
        exit(1);
    }

    $asciidocFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'titleblock' => true,
        'title' => 'AsciiDoc Review Packet',
        'author' => ['Migration bot', 'Content editor'],
        'date' => '2026-06-07',
        'keywords' => ['migration', 'wordpress', 'review'],
        'lang' => 'en-US',
        'toc' => true,
        'math' => true,
        'abstract' => 'Native AsciiDoc default handoff.',
        'header-includes' => [':wp-review: enabled'],
        'include-before' => ['[NOTE]' . "\n" . '====' . "\n" . 'Review imported blocks before publishing.' . "\n" . '===='],
        'body' => "== Imported Body\n\nConverted AsciiDoc packet.",
        'include-after' => ['[appendix]' . "\n" . '== Handoff'],
    ], null, 'asciidoctor');
    foreach ([
        '= AsciiDoc Review Packet',
        'Migration bot; Content editor',
        '2026-06-07',
        ':keywords: migration, wordpress, review',
        ':lang: en-US',
        ':toc:',
        ':stem: latexmath',
        '[abstract]',
        '== Abstract',
        'Native AsciiDoc default handoff.',
        ':wp-review: enabled',
        "[NOTE]\n====\nReview imported blocks before publishing.\n====",
        "== Imported Body\n\nConverted AsciiDoc packet.",
        "[appendix]\n== Handoff",
    ] as $needle) {
        if (!str_contains($asciidocFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate asciidoc default fallback: {$needle}\n");
            exit(1);
        }
    }

    $legacyAsciiDocFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'body' => 'Legacy AsciiDoc body',
    ], null, 'asciidoc_legacy');
    if ($legacyAsciiDocFallback !== "Legacy AsciiDoc body\n") {
        fwrite(STDERR, "Missing expected doctemplate asciidoc_legacy default fallback\n");
        exit(1);
    }

    $plainFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'body' => "Plain text review packet\n\n",
    ], null, 'plain');
    if ($plainFallback !== "Plain text review packet\n") {
        fwrite(STDERR, "Missing expected doctemplate plain default fallback\n");
        exit(1);
    }

    $manFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'has-tables' => true,
        'pandoc-version' => '3.7.0',
        'adjusting' => 'l',
        'title' => 'wp-import-review',
        'section' => '7',
        'date' => '2026-06-07',
        'footer' => 'Port Libs',
        'header' => 'WordPress import',
        'header-includes' => ['.mso review custom macro'],
        'include-before' => ['.SH REVIEW QUEUE'],
        'body' => ".PP\nNative man packet.\n",
        'include-after' => ['.SH HANDOFF'],
        'author' => ['Migration bot', 'Content editor'],
    ], null, 'man');
    foreach ([
        "'\\\" t",
        '.\" Automatically generated by Pandoc 3.7.0',
        '.ad l',
        '.TH "wp-import-review" "7" "2026-06-07" "Port Libs" "WordPress import"',
        '.mso review custom macro',
        '.SH REVIEW QUEUE',
        ".PP\nNative man packet.",
        '.SH HANDOFF',
        '.SH AUTHORS',
        'Migration bot; Content editor.',
    ] as $needle) {
        if (!str_contains($manFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate man default fallback: {$needle}\n");
            exit(1);
        }
    }

    $msFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'pandoc-version' => '3.7.0',
        'highlighting-macros' => '.de REVIEWCODE' . "\n" . '..',
        'pointsize' => '11p',
        'lineheight' => '13p',
        'fontfamily' => 'H',
        'indent' => '2m',
        'papersize' => 'a4',
        'adjusting' => 'b',
        'hyphenate' => true,
        'has-inline-math' => true,
        'pdf-engine' => true,
        'title-meta' => 'MS Default Review',
        'author-meta' => 'Migration bot',
        'header-includes' => ['.mso review custom ms macro'],
        'title' => 'MS Default Review',
        'author' => ['Migration bot', 'Content editor'],
        'date' => '2026-06-07',
        'abstract' => 'Native ms abstract.',
        'include-before' => ['.SH REVIEW QUEUE'],
        'body' => ".PP\nNative ms packet.\n",
        'toc' => true,
        'include-after' => ['.SH HANDOFF'],
    ], null, 'ms');
    foreach ([
        '.\" Automatically generated by Pandoc 3.7.0',
        '.de REVIEWCODE',
        '.nr PS 11p',
        '.nr VS 13p',
        '.fam H',
        '.nr PI 2m',
        '.ds paper a4',
        '.ad b',
        '.hy',
        ".EQ\ndelim @@\n.EN",
        '.pdfinfo /Title "MS Default Review"',
        '.pdfinfo /Author "Migration bot"',
        '.mso review custom ms macro',
        ".TL\nMS Default Review",
        ".AU\nMigration bot",
        ".AU\nContent editor",
        ".AB\nNative ms abstract.\n.AE",
        '.SH REVIEW QUEUE',
        ".PP\nNative ms packet.",
        '.TC',
        '.SH HANDOFF',
        '.pdfsync',
    ] as $needle) {
        if (!str_contains($msFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate ms default fallback: {$needle}\n");
            exit(1);
        }
    }

    $officeFallbacks = [
        'latex' => [
            (new DocTemplate())->renderResource('templates/default', [], [
                'documentclass' => 'article',
                'classoption' => ['oneside'],
                'title' => 'LaTeX Default Review',
                'author' => ['Migration bot'],
                'date' => '2026-06-06',
                'body' => '\\section{LaTeX body}',
                'toc' => true,
                'toc-depth' => 2,
                'bibliography' => ['review'],
                'natbib' => true,
            ], null, 'latex'),
            [
                '\\documentclass[oneside]{article}',
                '\\title{LaTeX Default Review}',
                '\\author{Migration bot}',
                '\\date{2026-06-06}',
                '\\setcounter{tocdepth}{2}',
                '\\tableofcontents',
                '\\section{LaTeX body}',
                '\\bibliography{review}',
                '\\end{document}',
            ],
        ],
        'docx' => [
            (new DocTemplate())->renderResource('templates/default', [], [
                'title' => 'DOCX Default Review',
                'author' => ['Migration bot'],
                'body' => '<w:p>DOCX body.</w:p>',
                'sectpr' => '<w:sectPr/>',
            ], null, 'docx'),
            ['DOCX Default Review', 'Migration bot', '<w:p>DOCX body.</w:p>', '<w:sectPr/>'],
        ],
        'odt' => [
            (new DocTemplate())->renderResource('templates/default', [], [
                'automatic-styles' => '<office:automatic-styles/>',
                'title' => '<text:h>ODT Default Review</text:h>',
                'body' => '<text:p>ODT body.</text:p>',
            ], null, 'odt'),
            ['<office:automatic-styles/>', '<text:h>ODT Default Review</text:h>', '<text:p>ODT body.</text:p>'],
        ],
        'epub' => [
            (new DocTemplate())->renderResource('templates/default', [], [
                'titlepage' => true,
                'title' => ['EPUB Default Review'],
                'author' => ['Migration bot'],
                'abstract-title' => 'Abstract',
                'abstract' => 'EPUB metadata body.',
            ], null, 'epub'),
            ['# EPUB Default Review', 'Migration bot', 'Abstract', 'EPUB metadata body.'],
        ],
    ];
    foreach ($officeFallbacks as $format => [$rendered, $needles]) {
        foreach ($needles as $needle) {
            if (!str_contains($rendered, $needle)) {
                fwrite(STDERR, "Missing expected doctemplate {$format} default fallback: {$needle}\n");
                exit(1);
            }
        }
    }

    $docbookFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'article' => true,
        'title' => 'DocBook Default Review',
        'subtitle' => 'Native metadata handoff',
        'author' => ['Migration bot', 'Content editor'],
        'date' => '2026-06-07',
        'abstract' => '<para>DocBook metadata body.</para>',
        'include-before' => ['<section><title>Before DocBook body</title></section>'],
        'body' => '<section><title>DocBook body</title></section>',
        'include-after' => ['<section><title>After DocBook body</title></section>'],
    ], null, 'docbook');
    foreach ([
        '<article xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" version="5.0" xml:lang="en">',
        '<title>DocBook Default Review</title>',
        '<subtitle>Native metadata handoff</subtitle>',
        'Migration bot',
        'Content editor',
        '<date>2026-06-07</date>',
        '<para>DocBook metadata body.</para>',
        '<section><title>Before DocBook body</title></section>',
        '<section><title>DocBook body</title></section>',
        '<section><title>After DocBook body</title></section>',
        '</article>',
    ] as $needle) {
        if (!str_contains($docbookFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate docbook default fallback: {$needle}\n");
            exit(1);
        }
    }

    $beamerFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'documentclass' => 'beamer',
        'fontsize' => '10pt',
        'theme' => 'Madrid',
        'colortheme' => 'dolphin',
        'title' => 'Beamer Default Review',
        'shorttitle' => 'Beamer Review',
        'author' => ['Migration bot'],
        'shortauthor' => 'Migration desk',
        'date' => '2026-06-06',
        'toc' => true,
        'toc-title' => 'Deck Contents',
        'body' => '\\begin{frame}{Imported Body}Review queue.\\end{frame}',
        'biblatex' => true,
        'biblio-title' => 'Imported Sources',
    ], null, 'beamer');
    foreach ([
        '\\documentclass[10pt, ignorenonframetext]{beamer}',
        '\\usetheme{Madrid}',
        '\\usecolortheme{dolphin}',
        '\\title[Beamer Review]{Beamer Default Review}',
        '\\author[Migration desk]{Migration bot}',
        '\\date{2026-06-06}',
        '\\frame{\\titlepage}',
        '\\frametitle{Deck Contents}',
        '\\begin{frame}{Imported Body}Review queue.\\end{frame}',
        '\\begin{frame}[allowframebreaks]{Imported Sources}',
        '\\printbibliography[heading=none]',
    ] as $needle) {
        if (!str_contains($beamerFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate beamer default fallback: {$needle}\n");
            exit(1);
        }
    }

    $legacySlideContext = [
        'lang' => 'en',
        'dir' => 'ltr',
        'pagetitle' => 'Legacy Slide Review',
        'title-prefix' => 'WordPress Import',
        'title' => 'Legacy Slide Defaults',
        'subtitle' => 'Native doctemplate packet',
        'author' => ['Migration bot'],
        'author-meta' => ['Migration bot'],
        'institute' => ['Review desk'],
        'date' => '2026-06-08',
        'date-meta' => '2026-06-08',
        'keywords' => ['migration', 'slides'],
        'css' => ['review-slides.css'],
        'toc' => true,
        'idprefix' => 'legacy-',
        'table-of-contents' => '<ul><li>Imported slides</li></ul>',
        'body' => '<section><h2>Imported slide body</h2></section>',
        's5-url' => 'vendor/s5/default',
        'slidy-url' => 'vendor/slidy',
        'slideous-url' => 'vendor/slideous',
        'duration' => '8',
        'dzslides-core' => '<script>window.__legacySlideReview = true;</script>',
    ];
    $legacySlideFallbacks = [
        's5' => [
            (new DocTemplate())->renderResource('templates/default', [], $legacySlideContext, null, 's5'),
            [
                '<meta name="version" content="S5 1.1" />',
                '<link rel="stylesheet" href="vendor/s5/default/slides.css" type="text/css" media="projection" id="slideProj" />',
                '<script src="vendor/s5/default/slides.js" type="text/javascript"></script>',
                '<div class="title-slide slide">',
                '<section><h2>Imported slide body</h2></section>',
            ],
        ],
        'slidy' => [
            (new DocTemplate())->renderResource('templates/default', [], $legacySlideContext, null, 'slidy'),
            [
                'href="vendor/slidy/styles/slidy.css"',
                '<script src="vendor/slidy/scripts/slidy.js"',
                '<meta name="duration" content="8" />',
                '<div class="slide titlepage">',
                '<section><h2>Imported slide body</h2></section>',
            ],
        ],
        'slideous' => [
            (new DocTemplate())->renderResource('templates/default', [], $legacySlideContext, null, 'slideous'),
            [
                'href="vendor/slideous/slideous.css"',
                '<script src="vendor/slideous/slideous.js"',
                '<div id="statusbar">',
                '<button id="nextslidebutton" title="next slide">&raquo;</button>',
                '<section><h2>Imported slide body</h2></section>',
            ],
        ],
        'dzslides' => [
            (new DocTemplate())->renderResource('templates/default', [], $legacySlideContext, null, 'dzslides'),
            [
                '<head lang="en" dir="ltr">',
                '<section class="title">',
                '<span class="author">Migration bot</span> · <span class="institute">Review desk</span> · <span class="date">2026-06-08</span>',
                '<section id="legacy-TOC">',
                '<script>window.__legacySlideReview = true;</script>',
            ],
        ],
    ];
    foreach ($legacySlideFallbacks as $format => [$rendered, $needles]) {
        foreach ($needles as $needle) {
            if (!str_contains($rendered, $needle)) {
                fwrite(STDERR, "Missing expected doctemplate {$format} legacy slide fallback: {$needle}\n");
                exit(1);
            }
        }
    }

    $typstFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'title' => 'Typst Default Review',
        'subtitle' => 'Native metadata handoff',
        'author' => [
            ['name' => 'Migration bot', 'affiliation' => 'Migration Desk', 'email' => 'bot@example.test'],
            'Content editor',
        ],
        'keywords' => ['migration', 'wordpress'],
        'date' => '2026-06-06',
        'lang' => 'en',
        'region' => 'US',
        'abstract-title' => 'Abstract',
        'abstract' => 'Typst metadata body.',
        'margin' => ['x' => '1.25in', 'y' => '1in'],
        'papersize' => 'a4',
        'mainfont' => 'Atkinson Hyperlegible',
        'columns' => 2,
        'toc' => true,
        'toc-depth' => 3,
        'body' => '#heading[Typst body]',
        'citations' => true,
        'nocite-ids' => ['doe2024'],
        'bibliographystyle' => 'chicago-author-date',
        'bibliography' => ['review.bib'],
    ], null, 'typst');
    foreach ([
        '#let conf(',
        'title: [Typst Default Review],',
        'subtitle: [Native metadata handoff],',
        '(name: [Migration bot], affiliation: [Migration Desk], email: [bot@example.test]),',
        '(name: [Content editor], affiliation: "", email: ""),',
        'keywords: (migration,wordpress),',
        'margin: (x: 1.25in,y: 1in,),',
        'paper: "a4",',
        'font: ("Atkinson Hyperlegible",),',
        'cols: 2,',
        '#outline(title: auto, depth: 3);',
        '#heading[Typst body]',
        '#cite(label("doe2024"), form: none)',
        '#set bibliography(style: "chicago-author-date")',
        '#bibliography(("review.bib"))',
    ] as $needle) {
        if (!str_contains($typstFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate typst default fallback: {$needle}\n");
            exit(1);
        }
    }

    $wrappedTypstFallback = (new DocTemplate())->renderResource('templates/wrapper', [
        'templates/wrapper.typst' => <<<'TYPST'
#let wrapped-review = [
${ default() }
]
TYPST,
    ], [
        'title' => 'Wrapped Typst Review',
        'body' => '#heading[Wrapped review body]',
    ], null, 'typst');
    foreach ([
        '#let conf(',
        'title: [Wrapped Typst Review],',
        '#heading[Wrapped review body]',
    ] as $needle) {
        if (!str_contains($wrappedTypstFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate nested default fallback: {$needle}\n");
            exit(1);
        }
    }

    $filesystemRoot = sys_get_temp_dir() . '/pandoc-doctemplate-example-' . bin2hex(random_bytes(6));
    $writeFilesystemTemplate = static function (string $relativePath, string $contents) use ($filesystemRoot): void {
        $path = $filesystemRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create doctemplate filesystem example directory');
        }

        file_put_contents($path, $contents);
    };
    $removeFilesystemTemplates = static function () use ($filesystemRoot): void {
        if (!is_dir($filesystemRoot)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($filesystemRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }

        rmdir($filesystemRoot);
    };

    if (!mkdir($filesystemRoot, 0777, true) && !is_dir($filesystemRoot)) {
        fwrite(STDERR, "Unable to create doctemplate filesystem example root\n");
        exit(1);
    }

    try {
        $writeFilesystemTemplate('review-packets/review.html', <<<'HTML'
<article class="wp-import-filesystem-review">
${ components/header() }
<section>
${ warnings:components/warning-row()[
] }
</section>
${ footer() }
</article>
HTML);
        $writeFilesystemTemplate('review-packets/components/header.html', '<header><h2>$title$</h2><p>$sources/uppercase[, ]$</p></header>' . "\n");
        $writeFilesystemTemplate('review-packets/components/warning-row.html', '<p data-source="$it.source$">$it.message$</p>' . "\n");
        $writeFilesystemTemplate('review-packets/summary.html', 'Summary: $~$media links layout status$~$');
        $writeFilesystemTemplate('wp-data/templates/footer.html', '<footer>$reviewer$</footer>' . "\n");

        $filesystemOutput = (new DocTemplate())->renderFilesystemResource('review-packets/review', $filesystemRoot, [
            'title' => 'Filesystem Review Packet',
            'sources' => ['media', 'links', 'layout'],
            'reviewer' => 'Migration desk',
            'warnings' => [
                ['source' => 'docx', 'message' => 'Imported heading'],
                ['source' => 'odt', 'message' => 'Styled paragraph'],
            ],
        ], 'wp-data', 'html');

        foreach ([
            '<article class="wp-import-filesystem-review">',
            '<header><h2>Filesystem Review Packet</h2><p>MEDIA, LINKS, LAYOUT</p></header>',
            '<p data-source="docx">Imported heading</p>',
            '<p data-source="odt">Styled paragraph</p>',
            '<footer>Migration desk</footer>',
        ] as $needle) {
            if (!str_contains($filesystemOutput, $needle)) {
                fwrite(STDERR, "Missing expected doctemplate filesystem output: {$needle}\n");
                exit(1);
            }
        }

        $filesystemWrapped = (new DocTemplate())->renderFilesystemResourceWrapped('review-packets/summary', $filesystemRoot, [], 20, null, 'html');
        if ($filesystemWrapped !== "Summary: media links\nlayout status") {
            fwrite(STDERR, "Missing expected doctemplate filesystem wrapped output\n");
            exit(1);
        }
    } finally {
        $removeFilesystemTemplates();
    }

    $loopGuard = (new DocTemplate())->render('${ loop() }', [], [
        'loop' => '${ loop() }',
    ]);
    if ($loopGuard !== '(loop)') {
        fwrite(STDERR, "Missing expected doctemplate partial loop guard\n");
        exit(1);
    }

    $escapedDollar = (new DocTemplate())->render('Cost: $$5', []);
    if ($escapedDollar !== 'Cost: $5') {
        fwrite(STDERR, "Missing expected doctemplate escaped dollar output\n");
        exit(1);
    }

    try {
        (new DocTemplate())->render('Broken: $review-id', ['review-id' => 'PR-42']);
        fwrite(STDERR, "Expected unclosed doctemplate dollar directive rejection\n");
        exit(1);
    } catch (\UnexpectedValueException) {
        // Expected: literal dollar signs must be escaped as $$.
    }

    try {
        (new DocTemplate())->render('Broken wrap: $~$review packet', []);
        fwrite(STDERR, "Expected unclosed doctemplate breakable-space rejection\n");
        exit(1);
    } catch (\UnexpectedValueException) {
        // Expected: breakable-space regions must be closed.
    }

    try {
        (new DocTemplate())->renderResource('review-packets/broken.html', [
            'review-packets/broken.html' => "<article>\n" . '${ components/broken-row() }' . "\n</article>",
            'review-packets/components/broken-row.html' => "<p>\n" . '$if(title)$Missing endif',
        ], [
            'title' => 'Broken review packet',
        ]);
        fwrite(STDERR, "Expected doctemplate partial source-location diagnostic\n");
        exit(1);
    } catch (\UnexpectedValueException $exception) {
        if (!str_contains($exception->getMessage(), 'Unclosed doctemplate if block at review-packets/components/broken-row.html:2:1')) {
            fwrite(STDERR, "Unexpected doctemplate partial source-location diagnostic: {$exception->getMessage()}\n");
            exit(1);
        }
    }

    try {
        (new DocTemplate())->renderResource('review-packets/invalid.html', [
            'review-packets/invalid.html' => '$if(title)$ok$else$$title/no-such-pipe$$endif$',
        ], [
            'title' => 'Invalid review packet',
        ]);
        fwrite(STDERR, "Expected inactive doctemplate branch parser validation\n");
        exit(1);
    } catch (\UnexpectedValueException $exception) {
        if (!str_contains($exception->getMessage(), 'Unsupported doctemplate pipe no-such-pipe at review-packets/invalid.html:1:20')) {
            fwrite(STDERR, "Unexpected inactive branch parser validation diagnostic: {$exception->getMessage()}\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "OK wordpress doctemplate review packet\n");
    exit(0);
}

fwrite(STDOUT, $output . "\n");
