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
<p class="warning-rollup">${ warnings/rest:components/next-warning()/uppercase[ | ] }</p>
<p class="ticket">Ticket: ${ review-id/left 8 "[" "]" }</p>
<p class="authors">$for(authors/pairs)$$it.value.name$$sep$, $endfor$</p>
<p class="review-sources">${ reviewSources/rest/uppercase[ / ] }</p>
<p class="brace-separated-sources">${ reviewSources/uppercase[} ] }</p>
<p class="bracket-separated-sources">${ reviewSources/uppercase[[ ] }</p>
<p class="chomped-review-sources">${ reviewSourcesWithNewlines/chomp/uppercase[ / ] }</p>
<pre class="source-enum">${ reviewSourcesWithNotes/pairs/reverse:components/source-enum() }</pre>
<pre class="review-code">$shortReviewCode/left 4 "[" "]"$</pre>
<p class="review-meta">$for(reviewMeta/pairs)$$it.key$=$it.value$$sep$; $endfor$</p>
<p class="style-metadata">$style:font-name$ / ${ style:family:components/style-token()[, ] }</p>
<p class="digit-metadata">$reviewNumbers.2026-batch.status$ / $reviewNumbers.1st-pass$ / ${ assets.360-view:components/asset-digit-row()[, ] }</p>
<p class="control-key-metadata">$reviewControls.if$ / $for(reviewControls.for)$$it.it$:$it.else$$sep$, $endfor$</p>
<p class="derived-missing-count">${ missingWarnings/length:components/missing-count() }</p>
<pre class="plain-text-summary">$wrappedPlainSummary$</pre>
<p class="labeled-note">$^$Note: $summaryNote$</p>
<p>$^$Blank: $blankLineNote$

   Follow-up: $blankLineFollowup$</p>
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
    'review-packets/components/source-enum.html' => '$it.key/alpha/uppercase$. $^$$it.value$' . "\n\n",
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
    'reviewSourcesWithNotes' => [
        "Media audit\nCheck alt text",
        'Link audit',
        "Layout review\nCheck styles",
    ],
    'shortReviewCode' => 'WP-Review',
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
    'reviewControls' => [
        'if' => 'conditional metadata',
        'for' => [
            [
                'it' => 'first-loop-item',
                'else' => 'first-child-else',
            ],
            [
                'it' => 'second-loop-item',
                'else' => 'second-child-else',
            ],
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
    'blankLineNote' => "First review line\nSecond review line",
    'blankLineFollowup' => "Follow-up first\nFollow-up second",
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
        '<p class="warning-rollup">LINKS/LINKS: VERIFY EDIT LINKS BEFORE PUBLISH | 魚/魚: CONFIRM MULTILINGUAL SOURCE LABEL SPACING | BATCH-4/BATCH-4: GENERATED REVIEWER CHECKPOINT 4',
        '<p class="ticket">Ticket: [        ]</p>',
        '<p class="authors">Migration bot, Content editor</p>',
        '<p class="review-sources">LINKS / LAYOUT</p>',
        '<p class="brace-separated-sources">MEDIA} LINKS} LAYOUT</p>',
        '<p class="bracket-separated-sources">MEDIA[ LINKS[ LAYOUT</p>',
        '<p class="chomped-review-sources">MEDIA / LINKS / LAYOUT</p>',
        "<pre class=\"source-enum\">C. Layout review\n   Check styles\nB. Link audit\nA. Media audit\n   Check alt text\n</pre>",
        "<pre class=\"review-code\">[WP-R]\n[evie]\n[w   ]</pre>",
        '<p class="review-meta">alpha=queued-first; review-id=PR-42; zeta=queued-last</p>',
        '<p class="style-metadata">Atkinson Hyperlegible / Heading_20_1=Alegreya, BodyText=Atkinson Hyperlegible</p>',
        '<p class="digit-metadata">queued / metadata / cover-spin:ok, layout-spin:review</p>',
        '<p class="control-key-metadata">conditional metadata / first-loop-item:first-child-else, second-loop-item:second-child-else</p>',
        '<p class="derived-missing-count">missing=<>; it=0</p>',
        "<pre class=\"plain-text-summary\">Review queue includes media links layout and\nmultilingual source packet follow-ups.</pre>",
        $labeledNotePrefix . 'Note: Review imported title blocks' . "\n"
            . str_repeat(' ', UnicodeText::displayWidth($labeledNotePrefix))
            . 'Confirm reviewer packet spacing</p>',
        "<p>Blank: First review line\n   Second review line\n   \n   Follow-up: Follow-up first\n   Follow-up second</p>",
        "<p class=\"dedented-note\">Dedented review packet close\n</p>",
        "<p>Ready for import\n   Owner: Migration desk\n   Review desk</p>",
        "<p class=\"comment-spacing\">Before preserved comment whitespace</p>\n  \n<p class=\"comment-spacing\">After preserved comment whitespace</p>",
        '<p class="audit-flag" data-suppressed="false">Suppressed: <false></p>',
        "<p class=\"crlf-note\">CRLF partial final line ending stripped</p>\n<p class=\"partial-spacing\">Partial spacing survives reviewer packet boundaries</p>",
        "<p class=\"partial-spacing\">Partial spacing survives reviewer packet boundaries</p>\n</header>",
        $sourceSummaryPrefix . 'Première ligne' . "\n"
            . str_repeat(' ', UnicodeText::displayWidth($sourceSummaryPrefix))
            . 'Deuxième ligne</p>',
        '<li data-index="1" data-source="media" data-review-title="Batch 42 Review"><span class="marker">A.</span> <span class="source">{MEDIA   }</span> <span class="priority">   I</span> Check &amp; confirm alt text</li>',
        '<li data-index="2" data-source="links" data-review-title="Batch 42 Review"><span class="marker">B.</span> <span class="source">{LINKS   }</span> <span class="priority">  IV</span> Verify edit links before publish</li>',
        '<li data-index="3" data-source="魚" data-review-title="Batch 42 Review"><span class="marker">C.</span> <span class="source">{魚      }</span> <span class="priority">  IX</span> Confirm multilingual source label spacing</li>',
        '<li data-index="26" data-source="batch-26" data-review-title="Batch 42 Review"><span class="marker">Z.</span> <span class="source">{BATCH-26}</span> <span class="priority">   I</span> Generated reviewer checkpoint 26</li>',
        '<li data-index="27" data-source="batch-27" data-review-title="Batch 42 Review"><span class="marker">A.</span> <span class="source">{BATCH-27}</span> <span class="priority">   I</span> Generated reviewer checkpoint 27</li>',
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

    try {
        (new DocTemplate())->render('${ warnings[, ]:components/warning-row() }', [
            'warnings' => [
                ['source' => 'media', 'message' => 'Confirm alt text'],
            ],
        ], [
            'components/warning-row' => '<li>$it.source$: $it.message$</li>',
        ]);
        fwrite(STDERR, "Expected doctemplate applied partial separator diagnostic\n");
        exit(1);
    } catch (UnexpectedValueException $exception) {
        if (!str_contains($exception->getMessage(), 'Doctemplate applied partial separators must follow the partial call')) {
            fwrite(STDERR, "Unexpected doctemplate applied partial separator diagnostic\n");
            exit(1);
        }
    }

    $userDataDefaultPacket = $renderer->renderResource('templates/default', [
        'wp-data/templates/default.html5' => '<article class="wp-user-default"><h2>$title$</h2><section>${ default.plain() }</section></article>',
        'wp-data/templates/default.plain' => 'Body: $body$',
    ], [
        'title' => 'User Default Review',
        'body' => 'Imported reviewer packet',
    ], 'wp-data', 'html+smart');
    if ($userDataDefaultPacket !== '<article class="wp-user-default"><h2>User Default Review</h2><section>Body: Imported reviewer packet</section></article>') {
        fwrite(STDERR, "Unexpected doctemplate user-data default template output\n");
        exit(1);
    }

    $mainDefaultPacket = $renderer->renderResource('templates/default', [
        'templates/default.html5' => '<article class="wp-main-default">$body$</article>',
        'wp-data/templates/default.html5' => '<article class="wp-user-default">$body$</article>',
    ], [
        'body' => 'Main reviewer packet',
    ], 'wp-data', 'html');
    if ($mainDefaultPacket !== '<article class="wp-main-default">Main reviewer packet</article>') {
        fwrite(STDERR, "Unexpected doctemplate main default template precedence output\n");
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

    $emptyLineNestingPacket = (new DocTemplate())->render(
        "<section class=\"blank-line-review\">\n  " . '$body$' . "\n</section>\n<p>" . '$^$' . "Note: " . '$note$' . "</p>",
        [
            'body' => "<!-- wp:paragraph --><p>First review block.</p><!-- /wp:paragraph -->\n\n"
                . '<!-- wp:paragraph --><p>Second review block.</p><!-- /wp:paragraph -->',
            'note' => "First reviewer note\n\nSecond reviewer note",
        ],
    );
    if ($emptyLineNestingPacket !== "<section class=\"blank-line-review\">\n  <!-- wp:paragraph --><p>First review block.</p><!-- /wp:paragraph -->\n\n  <!-- wp:paragraph --><p>Second review block.</p><!-- /wp:paragraph -->\n</section>\n<p>Note: First reviewer note\n\n   Second reviewer note</p>") {
        fwrite(STDERR, "Unexpected doctemplate empty-line nesting output\n");
        exit(1);
    }

    $wrappedNestedReviewPacket = (new DocTemplate())->renderWrapped(
        '<p>$^$Note: $~$media links layout status$~$</p>',
        [],
        18,
    );
    if ($wrappedNestedReviewPacket !== "<p>Note: media\n   links layout\n   status</p>") {
        fwrite(STDERR, "Unexpected doctemplate nested wrapping output\n");
        exit(1);
    }

    $sourceContinuationReviewPacket = $renderer->render(
        '$source$ $^$$count$' . "\n" . '          queued $details$',
        [
            'source' => 'wp',
            'count' => 3,
            'details' => "media packet\nlinks packet",
        ],
    );
    if ($sourceContinuationReviewPacket !== "wp 3\n   queued media packet\n   links packet") {
        fwrite(STDERR, "Unexpected doctemplate source-aligned continuation output\n");
        exit(1);
    }

    $dedentedLoopReviewPacket = $renderer->render(
        '$source$ $^$$count$' . "\n"
            . '          queued $details$' . "\n"
            . '$for(reviewItems)$' . "\n"
            . '1. $^$Review $if(it)$' . "\n"
            . '$it$' . "\n"
            . '$endif$' . "\n"
            . '$endfor$',
        [
            'source' => 'wp',
            'count' => 3,
            'details' => "media packet\nlinks packet",
            'reviewItems' => ['media', 'links'],
        ],
    );
    if ($dedentedLoopReviewPacket !== "wp 3\n   queued media packet\n   links packet\n1. Review media\n1. Review links\n") {
        fwrite(STDERR, "Unexpected doctemplate dedented loop continuation output\n");
        exit(1);
    }

    $fullNestReviewPacket = $renderer->render(
        '$details$' . "\n"
            . '$details$' . "\n"
            . '$^$$details$' . "\n"
            . '$review.zub$ $^$$details$' . "\n"
            . '$review.zub$ $^$$count$' . "\n"
            . '             queued $details$' . "\n"
            . '$for(reviewItems)$' . "\n"
            . '1. $^$Review $if(it)$' . "\n"
            . '$it$' . "\n"
            . '$endif$' . "\n"
            . '$endfor$' . "\n"
            . '$^$owner $details$' . "\n"
            . 'owner $details$' . "\n"
            . 'owner $details$' . "\n"
            . 'owner $if(count)$' . "\n"
            . '$count$' . "\n"
            . '$endif$' . "\n"
            . 'owner',
        [
            'count' => 3,
            'reviewItems' => ['media', 'links'],
            'review' => ['zub' => 'wp'],
            'details' => "media packet\nlinks packet",
        ],
    );
    if ($fullNestReviewPacket !== "media packet\nlinks packet\nmedia packet\nlinks packet\nmedia packet\nlinks packet\nwp media packet\n   links packet\nwp 3\n   queued media packet\n   links packet\n1. Review media\n1. Review links\nowner media packet\nlinks packet\nowner media packet\nlinks packet\nowner media packet\nlinks packet\nowner 3\nowner") {
        fwrite(STDERR, "Unexpected doctemplate full nesting fixture output\n");
        exit(1);
    }

    $indentedControlReviewPacket = $renderer->render(
        '$for(reviewItems)$' . "\n"
            . '1. $^$Review' . "\n"
            . '   $if(it.note)$' . "\n"
            . '     $it.note$' . "\n"
            . '   $endif$' . "\n"
            . '$endfor$',
        [
            'reviewItems' => [
                ['note' => 'media packet'],
                ['note' => 'links packet'],
            ],
        ],
    );
    if ($indentedControlReviewPacket !== "1. Review\n     media packet\n1. Review\n     links packet\n") {
        fwrite(STDERR, "Unexpected doctemplate indented control line output\n");
        exit(1);
    }

    $blockPipeReviewPacket = $renderer->render(
        '$source/uppercase/left 8 "| " " | "$$details/left 20 "" "|"$',
        [
            'source' => "media\nlinks",
            'details' => "Check alt text\nReview redirects",
        ],
    );
    if ($blockPipeReviewPacket !== "| MEDIA    | Check alt text      |\n| LINKS    | Review redirects    |") {
        fwrite(STDERR, "Unexpected doctemplate adjacent block-pipe output\n");
        exit(1);
    }

    $trailingBlockPipeTablePacket = $renderer->render(
        "|------------|------------|\n"
            . '$for(reviewers)$' . "\n"
            . '$it.name/uppercase/left 10 "| "$' . '$it.ticket/right 10 " | " " |"$' . "\n"
            . '$endfor$'
            . "|------------|------------|\n\n",
        [
            'reviewers' => [
                ['name' => 'Media', 'ticket' => '17'],
                ['name' => 'Layout', 'ticket' => '103'],
            ],
        ],
    );
    if ($trailingBlockPipeTablePacket !== "|------------|------------|\n| MEDIA      |         17 |\n| LAYOUT     |        103 |\n|------------|------------|\n") {
        fwrite(STDERR, "Unexpected doctemplate trailing block-pipe table output\n");
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

    $zeroPriorityPacket = (new DocTemplate())->render(
        '<span class="priority">$priority/roman/uppercase/right 4$</span>',
        ['priority' => 0],
    );
    if ($zeroPriorityPacket !== '<span class="priority">    </span>') {
        fwrite(STDERR, "Unexpected doctemplate zero-priority roman marker output\n");
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

    $extensionQualifiedPartialFallback = (new DocTemplate())->renderResource('packets/review', [
        'packets/review.html+smart' => <<<'HTML'
<article class="extension-qualified-partials">
${ components/header() }
<section>
${ warnings:components/warning-row()[
] }
</section>
</article>
HTML,
        'packets/components/header.html' => '<header class="base-extension">$title$</header>' . "\n",
        'packets/components/header.html+smart' => '<header class="exact-extension">$title$</header>' . "\n",
        'packets/components/warning-row.html' => '<p data-source="$it.source$">$it.message$</p>' . "\n",
    ], [
        'title' => 'Extension partial packet',
        'warnings' => [
            ['source' => 'docx', 'message' => 'Imported heading'],
            ['source' => 'odt', 'message' => 'Styled paragraph'],
        ],
    ], null, 'html+smart');
    foreach ([
        '<article class="extension-qualified-partials">',
        '<header class="exact-extension">Extension partial packet</header>',
        '<p data-source="docx">Imported heading</p>',
        '<p data-source="odt">Styled paragraph</p>',
    ] as $needle) {
        if (!str_contains($extensionQualifiedPartialFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate extension-qualified partial fallback: {$needle}\n");
            exit(1);
        }
    }

    $explicitBaseExtensionPartialFallback = (new DocTemplate())->renderResource('packets/review', [
        'packets/review.html+smart' => <<<'HTML'
<article class="explicit-base-extension-partials">
${ components/header.html() }
${ components/status.html() }
</article>
HTML,
        'packets/components/header.html+smart' => '<header class="exact-base-fallback">$title$</header>' . "\n",
        'packets/components/status.html+smart' => '<p class="exact-base-fallback">$status$</p>' . "\n",
    ], [
        'title' => 'Explicit base-extension partial packet',
        'status' => 'WordPress review ready',
    ], null, 'html+smart');
    foreach ([
        '<article class="explicit-base-extension-partials">',
        '<header class="exact-base-fallback">Explicit base-extension partial packet</header>',
        '<p class="exact-base-fallback">WordPress review ready</p>',
    ] as $needle) {
        if (!str_contains($explicitBaseExtensionPartialFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate explicit base-extension partial fallback: {$needle}\n");
            exit(1);
        }
    }

    $explicitBaseExtensionPartialPreference = (new DocTemplate())->renderResource('packets/review', [
        'packets/review.html+smart' => '${ components/header.html() }',
        'packets/components/header.html+smart' => '<header class="exact">$title$</header>',
        'packets/components/header.html' => '<header class="base">$title$</header>',
    ], [
        'title' => 'Explicit base extension wins',
    ], null, 'html+smart');
    if ($explicitBaseExtensionPartialPreference !== '<header class="base">Explicit base extension wins</header>') {
        fwrite(STDERR, "Unexpected doctemplate explicit base-extension partial preference\n");
        exit(1);
    }

    $extensionQualifiedDefault = (new DocTemplate())->renderResource('templates/default', [], [
        'body' => 'Extension-qualified Markdown default fallback',
    ], null, 'markdown_strict+emoji-hard_line_breaks');
    if ($extensionQualifiedDefault !== "Extension-qualified Markdown default fallback\n") {
        fwrite(STDERR, "Missing expected doctemplate extension-qualified default fallback\n");
        exit(1);
    }

    $recursionLimitPartials = [];
    for ($index = 0; $index < 49; $index++) {
        $recursionLimitPartials['review-depth-' . $index] = '${ review-depth-' . ($index + 1) . '() }';
    }
    $recursionLimitPartials['review-depth-49'] = '${ missing-over-limit() }';
    $recursionLimitPacket = (new DocTemplate())->render('${ review-depth-0() }', [], $recursionLimitPartials);
    if ($recursionLimitPacket !== '(loop)') {
        fwrite(STDERR, "Unexpected doctemplate partial recursion-limit output\n");
        exit(1);
    }

    $typstDefinitionsFallback = (new DocTemplate())->renderResource('review-packets/typst-review', [
        'review-packets/typst-review.typst' => <<<'TYPST'
${ definitions.typst() }
#let review = [WordPress import Typst review]
TYPST,
    ], [], null, 'typst');
    foreach ([
        "// Some definitions presupposed by pandoc's typst output.",
        '#let horizontalrule = [',
        '#let endnote(num, contents) = [',
        '#let review = [WordPress import Typst review]',
    ] as $needle) {
        if (!str_contains($typstDefinitionsFallback, $needle)) {
            fwrite(STDERR, "Missing expected Typst definitions doctemplate fallback: {$needle}\n");
            exit(1);
        }
    }

    $unicodeDiagnostic = null;
    try {
        (new DocTemplate())->render('Résumé $title/no-such-pipe$', [
            'title' => 'Review',
        ]);
    } catch (UnexpectedValueException $exception) {
        $unicodeDiagnostic = $exception->getMessage();
    }
    if ($unicodeDiagnostic !== 'Unsupported doctemplate pipe no-such-pipe at <template>:1:15') {
        fwrite(STDERR, "Unexpected doctemplate Unicode-column diagnostic: " . (string) $unicodeDiagnostic . "\n");
        exit(1);
    }

    $unsupportedPipeDiagnostic = null;
    try {
        (new DocTemplate())->renderResource('review-packets/broken.html', [
            'review-packets/broken.html' => "<p>\n" . '$title/unknown$',
        ], [
            'title' => 'Review',
        ]);
    } catch (UnexpectedValueException $exception) {
        $unsupportedPipeDiagnostic = $exception->getMessage();
    }
    if ($unsupportedPipeDiagnostic !== 'Unsupported doctemplate pipe unknown at review-packets/broken.html:2:8') {
        fwrite(STDERR, "Unexpected doctemplate unsupported-pipe diagnostic: " . (string) $unsupportedPipeDiagnostic . "\n");
        exit(1);
    }

    $unclosedPipeQuoteDiagnostic = null;
    try {
        (new DocTemplate())->renderResource('review-packets/broken.html', [
            'review-packets/broken.html' => "Intro\n<p>" . '${ title/center 8 "<" " }',
        ], [
            'title' => 'Review',
        ]);
    } catch (UnexpectedValueException $exception) {
        $unclosedPipeQuoteDiagnostic = $exception->getMessage();
    }
    if ($unclosedPipeQuoteDiagnostic !== 'Unclosed doctemplate pipe quoted string at review-packets/broken.html:2:26') {
        fwrite(STDERR, "Unexpected doctemplate pipe quote diagnostic: " . (string) $unclosedPipeQuoteDiagnostic . "\n");
        exit(1);
    }

    $unclosedSeparatorDiagnostic = null;
    try {
        (new DocTemplate())->renderResource('review-packets/broken.html', [
            'review-packets/broken.html' => "<ul>\n" . '${ warnings:components/warning-row()[, }',
        ], [
            'warnings' => [['source' => 'media']],
        ]);
    } catch (UnexpectedValueException $exception) {
        $unclosedSeparatorDiagnostic = $exception->getMessage();
    }
    if ($unclosedSeparatorDiagnostic !== 'Unclosed doctemplate separator at review-packets/broken.html:2:37') {
        fwrite(STDERR, "Unexpected doctemplate separator diagnostic: " . (string) $unclosedSeparatorDiagnostic . "\n");
        exit(1);
    }

    $epub2Default = (new DocTemplate())->renderResource('templates/default', [], [
        'pagetitle' => 'Legacy EPUB Review Packet',
        'titlepage' => true,
        'lang' => 'en',
        'dir' => 'ltr',
        'csl-css' => true,
        'title' => [
            ['text' => 'Legacy EPUB Title'],
            'Fallback Legacy EPUB Title',
        ],
        'subtitle' => 'WordPress archive handoff',
        'author' => ['Migration bot'],
        'creator' => [
            ['role' => 'editor', 'text' => 'Content editor'],
        ],
        'publisher' => 'WordPress Migration',
        'date' => '2026-06-08',
        'rights' => 'Internal review only',
    ], null, 'epub2+smart');
    foreach ([
        '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
        '<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en" dir="ltr">',
        '<style type="text/css">',
        'div.csl-entry',
        '<h1 class="">Legacy EPUB Title</h1>',
        '<h1 class="title">Fallback Legacy EPUB Title</h1>',
        '<p class="subtitle">WordPress archive handoff</p>',
        '<p class="editor">Content editor</p>',
    ] as $needle) {
        if (!str_contains($epub2Default, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate EPUB2 default fallback: {$needle}\n");
            exit(1);
        }
    }
    if (str_contains($epub2Default, 'epub:type=')) {
        fwrite(STDERR, "Unexpected EPUB3 metadata in doctemplate EPUB2 fallback\n");
        exit(1);
    }

    $museDefault = (new DocTemplate())->renderResource('templates/default', [], [
        'author' => ['Migration bot', 'Content editor'],
        'title' => 'Muse Default Review',
        'lang' => 'en-US',
        'LISTtitle' => 'Reviewer Queue',
        'subtitle' => 'Native template fallback',
        'SORTauthors' => 'Migration bot',
        'SORTtopics' => 'migration wordpress',
        'date' => '2026-06-08',
        'notes' => 'Review imported Muse packet metadata.',
        'source' => 'https://example.test/wp-admin/import',
        'header-includes' => ['#custom wp-review-metadata'],
        'include-before' => ['** Before Muse import'],
        'body' => 'Muse body handoff',
        'include-after' => ['** After Muse import'],
    ], null, 'muse+smart');
    foreach ([
        '#author Migration bot; Content editor',
        '#title Muse Default Review',
        '#lang en-US',
        '#LISTtitle Reviewer Queue',
        '#subtitle Native template fallback',
        '#SORTauthors Migration bot',
        '#SORTtopics migration wordpress',
        '#date 2026-06-08',
        '#notes Review imported Muse packet metadata.',
        '#source https://example.test/wp-admin/import',
        '#custom wp-review-metadata',
        '** Before Muse import',
        'Muse body handoff',
        '** After Muse import',
    ] as $needle) {
        if (!str_contains($museDefault, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate Muse default fallback: {$needle}\n");
            exit(1);
        }
    }

    $orgDefault = (new DocTemplate())->renderResource('templates/default', [], [
        'title' => 'Org Default Review',
        'author' => ['Migration bot', 'Content editor'],
        'date' => '2026-06-08',
        'options' => [
            'toc' => 'nil',
            'num' => 't',
        ],
        'header-includes' => ['#+setupfile: reviewer.org'],
        'abstract' => 'Review imported Org packet metadata.',
        'include-before' => ['* Before Org import'],
        'body' => 'Org body handoff',
        'include-after' => ['* After Org import'],
    ], null, 'org+smart');
    foreach ([
        '#+title: Org Default Review',
        '#+author: Migration bot; Content editor',
        '#+date: 2026-06-08',
        '#+options: num:t',
        '#+options: toc:nil',
        '#+setupfile: reviewer.org',
        '#+begin_abstract',
        'Review imported Org packet metadata.',
        '#+end_abstract',
        '* Before Org import',
        'Org body handoff',
        '* After Org import',
    ] as $needle) {
        if (!str_contains($orgDefault, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate Org default fallback: {$needle}\n");
            exit(1);
        }
    }

    $texinfoDefault = (new DocTemplate())->renderResource('templates/default', [], [
        'filename' => 'review.info',
        'title' => 'Texinfo Default Review',
        'version' => '1.2',
        'header-includes' => ['@syncodeindex fn cp'],
        'strikeout' => true,
        'titlepage' => true,
        'author' => ['Migration bot', 'Content editor'],
        'date' => '2026-06-08',
        'include-before' => ['@node Before Texinfo import' . "\n" . '@chapter Before Texinfo import'],
        'toc' => true,
        'body' => '@node Imported Body' . "\n" . '@chapter Imported Body' . "\n" . 'Texinfo body handoff',
        'include-after' => ['@node Texinfo Handoff' . "\n" . '@chapter Texinfo Handoff'],
    ], null, 'texinfo+smart');
    foreach ([
        '@setfilename review.info',
        '@settitle Texinfo Default Review 1.2',
        '@syncodeindex fn cp',
        '@macro textstrikeout{text}',
        '@titlepage',
        '@author Migration bot',
        '@author Content editor',
        '@contents',
        '@node Imported Body',
        '@chapter Texinfo Handoff',
        '@bye',
    ] as $needle) {
        if (!str_contains($texinfoDefault, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate Texinfo default fallback: {$needle}\n");
            exit(1);
        }
    }

    $html4Default = (new DocTemplate())->renderResource('templates/default', [], [
        'pandoc-version' => '3.7.0',
        'pagetitle' => 'HTML4 Default Review',
        'title' => 'HTML4 Review Packet',
        'body' => '<p>HTML4 default review body.</p>',
    ], null, 'html4+smart');
    foreach ([
        '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"',
        '<meta http-equiv="Content-Style-Type" content="text/css" />',
        '<title>HTML4 Default Review</title>',
        '<div id="header">',
        '<h1 class="title">HTML4 Review Packet</h1>',
        '<p>HTML4 default review body.</p>',
    ] as $needle) {
        if (!str_contains($html4Default, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate html4 default fallback: {$needle}\n");
            exit(1);
        }
    }

    $latexPartialFallback = (new DocTemplate())->renderResource('review-packets/latex-partial-smoke.latex', [
        'review-packets/latex-partial-smoke.latex' => <<<'LATEX'
${ fonts.latex() }
${ hypersetup.latex() }
LATEX,
    ], [
        'fontenc' => 'T1',
        'title-meta' => 'WordPress LaTeX Partial Review',
        'author-meta' => 'Migration bot',
        'lang' => 'en-US',
        'keywords' => ['migration', 'review'],
    ]);
    foreach ([
        '\\usepackage[T1]{fontenc}',
        '\\usepackage{textcomp} % provide euro and other symbols',
        '\\hypersetup{',
        'pdftitle={WordPress LaTeX Partial Review},',
        'pdfauthor={Migration bot},',
        'pdflang={en-US},',
        'pdfkeywords={\\xmpquote{migration}, \\xmpquote{review}},',
        'pdfcreator={LaTeX via pandoc}}',
    ] as $needle) {
        if (!str_contains($latexPartialFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate LaTeX partial fallback: {$needle}\n");
            exit(1);
        }
    }

    $chunkedDefault = (new DocTemplate())->renderResource('templates/default', [], [
        'lang' => 'en',
        'dir' => 'ltr',
        'title-prefix' => 'WordPress Import',
        'pagetitle' => 'Chunked Review Packet',
        'title' => 'Chunked Template Review',
        'subtitle' => 'Native split-page metadata',
        'author' => ['Migration bot', 'Content editor'],
        'author-meta' => ['Migration bot', 'Content editor'],
        'date' => '2026-06-08',
        'date-meta' => '2026-06-08',
        'keywords' => ['migration', 'wordpress', 'chunked'],
        'description-meta' => 'Chunked HTML review packet',
        'css' => ['chunked-review.css'],
        'header-includes' => ['<meta name="robots" content="noindex">'],
        'math' => '<script type="math/tex">queued</script>',
        'include-before' => ['<main class="chunked-before">Queued</main>'],
        'up' => ['url' => '../index.html', 'title' => 'Manual Root'],
        'next' => ['url' => 'next.html', 'title' => 'Next Chunk'],
        'previous' => ['url' => 'previous.html', 'title' => 'Previous Chunk'],
        'abstract-title' => 'Abstract',
        'abstract' => '<p>Chunked abstract survives.</p>',
        'toc' => true,
        'idprefix' => 'wp-chunked-',
        'toc-title' => 'Chunk Contents',
        'table-of-contents' => '<ul><li>Chunk body</li></ul>',
        'body' => '<!-- wp:paragraph --><p>Chunked body.</p><!-- /wp:paragraph -->',
        'include-after' => ['<footer>Chunk done</footer>'],
        'document-css' => true,
        'mainfont' => 'Atkinson Hyperlegible',
        'csl-css' => true,
        'csl-entry-spacing' => '0.25em',
    ], null, 'chunkedhtml+smart');
    foreach ([
        '<title>WordPress Import – Chunked Review Packet</title>',
        'div.sitenav { display: flex; flex-direction: row; flex-wrap: wrap; }',
        'font-family: Atkinson Hyperlegible;',
        '/* CSS for citations */',
        '<span class="navlink-label">Up:</span> <a href="../index.html" accesskey="u" rel="up">Manual Root</a>',
        '<span class="navlink-label">Next:</span> <a href="next.html" accesskey="n" rel="next">Next Chunk</a>',
        '<span class="navlink-label">Previous:</span> <a href="previous.html" accesskey="p" rel="previous">Previous Chunk</a>',
        '<h1 class="title">Chunked Template Review</h1>',
        '<nav id="wp-chunked-TOC" role="doc-toc">',
        '<!-- wp:paragraph --><p>Chunked body.</p><!-- /wp:paragraph -->',
    ] as $needle) {
        if (!str_contains($chunkedDefault, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate chunkedhtml default fallback: {$needle}\n");
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

    $userDataDefaultPartial = (new DocTemplate())->renderResource('review-packets/user-data-default.html', [
        'review-packets/user-data-default.html' => '<article class="user-data-default-partial"><section>${ default.plain() }</section></article>',
        'wp-data/templates/default.plain' => 'user-data default: $body$',
    ], [
        'body' => 'WordPress review body',
    ], 'wp-data');
    if ($userDataDefaultPartial !== '<article class="user-data-default-partial"><section>user-data default: WordPress review body</section></article>') {
        fwrite(STDERR, "Missing expected doctemplate user-data default partial override\n");
        exit(1);
    }

    $basenameDefaultFallback = (new DocTemplate())->renderResource('review-packets/default.html5', [], [
        'pandoc-version' => '3.7.0',
        'pagetitle' => 'Basename Fallback Review',
        'body' => '<p>Basename fallback body.</p>',
    ]);
    foreach ([
        '<!DOCTYPE html>',
        '<title>Basename Fallback Review</title>',
        '<p>Basename fallback body.</p>',
    ] as $needle) {
        if (!str_contains($basenameDefaultFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate basename default fallback: {$needle}\n");
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
        'titleblock' => "Plain Review Packet\n===================",
        'header-includes' => ['Plain header metadata'],
        'include-before' => ['Before plain import'],
        'toc' => true,
        'table-of-contents' => 'Plain contents',
        'body' => "Plain text review packet\n\n",
        'include-after' => ['After plain import'],
    ], null, 'plain');
    foreach ([
        "Plain Review Packet\n===================",
        'Plain header metadata',
        'Before plain import',
        'Plain contents',
        "Plain text review packet\n",
        'After plain import',
    ] as $needle) {
        if (!str_contains($plainFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate plain default fallback: {$needle}\n");
            exit(1);
        }
    }

    $ansiFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'titleblock' => "ANSI Review Packet\n==================",
        'header-includes' => ['ANSI header metadata'],
        'include-before' => ['Before ANSI import'],
        'toc' => true,
        'table-of-contents' => 'ANSI contents',
        'body' => 'ANSI review body',
        'include-after' => ['After ANSI import'],
    ], null, 'ansi+smart');
    foreach ([
        'ANSI Review Packet',
        'ANSI header metadata',
        'Before ANSI import',
        'ANSI contents',
        'ANSI review body',
        'After ANSI import',
    ] as $needle) {
        if (!str_contains($ansiFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate ANSI default fallback: {$needle}\n");
            exit(1);
        }
    }

    $bibtexFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'body' => '@book{review2026, title = {Review Packet}}',
    ], null, 'bibtex');
    if (!str_contains($bibtexFallback, '@book{review2026, title = {Review Packet}}')) {
        fwrite(STDERR, "Missing expected doctemplate BibTeX default fallback\n");
        exit(1);
    }

    $biblatexFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'body' => '@online{migration2026, title = {Migration Review}}',
    ], null, 'biblatex+smart');
    if (!str_contains($biblatexFallback, '@online{migration2026, title = {Migration Review}}')) {
        fwrite(STDERR, "Missing expected doctemplate BibLaTeX default fallback\n");
        exit(1);
    }

    $pdfDefault = (new DocTemplate())->renderResource('templates/default', [], [
        'documentclass' => 'article',
        'title' => 'PDF Template Review Packet',
        'author' => ['Migration bot'],
        'date' => '2026-06-08',
        'include-before' => ['\\section*{WordPress Review}'],
        'body' => '\\section{PDF Body}',
        'include-after' => ['\\appendix'],
    ], null, 'pdf');
    foreach ([
        '\\documentclass{article}',
        '\\title{PDF Template Review Packet}',
        '\\author{Migration bot}',
        '\\date{2026-06-08}',
        '\\section*{WordPress Review}',
        '\\section{PDF Body}',
        '\\appendix',
        '\\end{document}',
    ] as $needle) {
        if (!str_contains($pdfDefault, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate PDF default fallback: {$needle}\n");
            exit(1);
        }
    }

    $rtfFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'header-includes' => ['{\\*\\generator PortLibs Review;}'],
        'title' => 'RTF Review Packet',
        'author' => ['Migration bot', 'Content editor'],
        'date' => '2026-06-08',
        'spacer' => true,
        'toc' => true,
        'table-of-contents' => '{\\pard \\ql RTF contents\\par}',
        'include-before' => ['{\\pard \\ql Before RTF import\\par}'],
        'body' => '{\\pard \\ql WordPress RTF review body\\par}',
        'include-after' => ['{\\pard \\ql After RTF import\\par}'],
    ], null, 'rtf+smart');
    foreach ([
        '{\\rtf1\\ansi\\deff0',
        '{\\*\\generator PortLibs Review;}',
        '{\\pard \\qc \\f0 \\sa180 \\li0 \\fi0 \\b \\fs36 RTF Review Packet\\par}',
        '{\\pard \\qc \\f0 \\sa180 \\li0 \\fi0 Migration bot\\par}',
        '{\\pard \\qc \\f0 \\sa180 \\li0 \\fi0 Content editor\\par}',
        '{\\pard \\qc \\f0 \\sa180 \\li0 \\fi0 2026-06-08\\par}',
        '{\\pard \\ql RTF contents\\par}',
        '{\\pard \\ql Before RTF import\\par}',
        '{\\pard \\ql WordPress RTF review body\\par}',
        '{\\pard \\ql After RTF import\\par}',
    ] as $needle) {
        if (!str_contains($rtfFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate RTF default fallback: {$needle}\n");
            exit(1);
        }
    }

    $wikiFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'include-before' => ['== WordPress review queue =='],
        'toc' => true,
        'body' => '== Imported wiki body ==',
        'include-after' => ['== Handoff =='],
    ], null, 'mediawiki+smart');
    foreach ([
        '== WordPress review queue ==',
        '__TOC__',
        '== Imported wiki body ==',
        '== Handoff ==',
    ] as $needle) {
        if (!str_contains($wikiFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate wiki default fallback: {$needle}\n");
            exit(1);
        }
    }

    $vimdocFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'filename' => 'wp-import-review.txt',
        'abstract' => 'WordPress review packet.',
        'combined-title' => 'WP Import Review',
        'toc-reminder' => 'Use gO for review sections.',
        'toc' => '|wp-import-review-toc|',
        'body' => '*wp-import-body* Vimdoc body handoff.',
        'modeline' => 'vim:tw=78:ft=help:norl:',
    ], null, 'vimdoc');
    foreach ([
        '*wp-import-review.txt*',
        'WordPress review packet.',
        'WP Import Review',
        'Use gO for review sections.',
        '|wp-import-review-toc|',
        '*wp-import-body* Vimdoc body handoff.',
        'vim:tw=78:ft=help:norl:',
    ] as $needle) {
        if (!str_contains($vimdocFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate Vimdoc default fallback: {$needle}\n");
            exit(1);
        }
    }

    $opmlFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'title' => 'OPML Default Review',
        'date' => '2026-06-08',
        'author' => ['Migration bot', 'Content editor'],
        'body' => '<outline text="Imported WordPress body"/>',
    ], null, 'opml+smart');
    foreach ([
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<opml version="2.0">',
        '<title>OPML Default Review</title>',
        '<dateModified>2026-06-08</dateModified>',
        '<ownerName>Migration bot; Content editor</ownerName>',
        '<outline text="Imported WordPress body"/>',
    ] as $needle) {
        if (!str_contains($opmlFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate OPML default fallback: {$needle}\n");
            exit(1);
        }
    }

    $teiFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'lang' => 'en',
        'title' => 'TEI Default Review',
        'author' => ['Migration bot'],
        'publisher' => 'Port Libs',
        'date' => '2026-06-08',
        'body' => '<div><p>Imported TEI review body.</p></div>',
    ], null, 'tei');
    foreach ([
        '<TEI xmlns="http://www.tei-c.org/ns/1.0" xml:lang="en">',
        '<title>TEI Default Review</title>',
        '<author>Migration bot</author>',
        '<publisher>Port Libs</publisher>',
        '<date>2026-06-08</date>',
        '<p>Produced by pandoc.</p>',
        '<div><p>Imported TEI review body.</p></div>',
    ] as $needle) {
        if (!str_contains($teiFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate TEI default fallback: {$needle}\n");
            exit(1);
        }
    }

    $lightweightDefaults = [
        'djot' => [
            (new DocTemplate())->renderResource('templates/default', [], [
                'title' => 'Djot Default Review',
                'author' => ['Migration bot'],
                'date' => '2026-06-08',
                'header-includes' => [':::{.review-meta}'],
                'include-before' => [':::note' . "\n" . 'Review before import.' . "\n" . ':::'],
                'body' => '## Imported Djot body',
                'include-after' => [':::handoff' . "\n" . 'Done' . "\n" . ':::'],
            ], null, 'djot+smart'),
            ['# Djot Default Review', 'Migration bot', ':::{.review-meta}', '## Imported Djot body', ":::handoff\nDone\n:::"],
        ],
        'markua' => [
            (new DocTemplate())->renderResource('templates/default', [], [
                'titleblock' => '# Markua Default Review',
                'toc' => true,
                'table-of-contents' => '{toc}',
                'body' => '# Imported Markua body',
            ], null, 'markua'),
            ['# Markua Default Review', '{toc}', '# Imported Markua body'],
        ],
        'textile' => [
            (new DocTemplate())->renderResource('templates/default', [], [
                'include-before' => ['h1. Textile Review'],
                'body' => 'h2. Imported Textile body',
                'include-after' => ['h1. Textile Handoff'],
            ], null, 'textile'),
            ['h1. Textile Review', 'h2. Imported Textile body', 'h1. Textile Handoff'],
        ],
        'haddock' => [
            (new DocTemplate())->renderResource('templates/default', [], [
                'body' => 'Haddock imported body',
            ], null, 'haddock'),
            ['Haddock imported body'],
        ],
        'xwiki' => [
            (new DocTemplate())->renderResource('templates/default', [], [
                'toc' => true,
                'body' => '= XWiki imported body =',
            ], null, 'xwiki'),
            ['{{toc /}}', '= XWiki imported body ='],
        ],
        'zimwiki' => [
            (new DocTemplate())->renderResource('templates/default', [], [
                'toc' => true,
                'body' => '====== ZimWiki imported body ======',
            ], null, 'zimwiki'),
            ['Content-Type: text/x-zim-wiki', 'Wiki-Format: zim 0.4', '__TOC__', '====== ZimWiki imported body ======'],
        ],
    ];
    foreach ($lightweightDefaults as $format => [$rendered, $needles]) {
        foreach ($needles as $needle) {
            if (!str_contains($rendered, $needle)) {
                fwrite(STDERR, "Missing expected doctemplate {$format} default fallback: {$needle}\n");
                exit(1);
            }
        }
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

    $docbook4Fallback = (new DocTemplate())->renderResource('templates/default', [], [
        'title' => 'DocBook 4 Default Review',
        'author' => ['Migration bot', 'Content editor'],
        'date' => '2026-06-08',
        'include-before' => ['<section><title>Before DocBook 4 body</title></section>'],
        'body' => '<para>DocBook 4 body.</para>',
        'include-after' => ['<section><title>After DocBook 4 body</title></section>'],
    ], null, 'docbook4');
    foreach ([
        '<?xml version="1.0" encoding="utf-8" ?>',
        '<!DOCTYPE article PUBLIC "-//OASIS//DTD DocBook XML V4.5//EN"',
        '"http://www.oasis-open.org/docbook/xml/4.5/docbookx.dtd">',
        '<articleinfo>',
        '<title>DocBook 4 Default Review</title>',
        '<authorgroup>',
        'Migration bot',
        'Content editor',
        '<date>2026-06-08</date>',
        '<section><title>Before DocBook 4 body</title></section>',
        '<para>DocBook 4 body.</para>',
        '<section><title>After DocBook 4 body</title></section>',
        '</article>',
    ] as $needle) {
        if (!str_contains($docbook4Fallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate docbook4 default fallback: {$needle}\n");
            exit(1);
        }
    }
    if (str_contains($docbook4Fallback, 'xmlns="http://docbook.org/ns/docbook"')) {
        fwrite(STDERR, "DocBook 4 fallback unexpectedly used DocBook 5 namespace\n");
        exit(1);
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

    $dzslidesNoCssFallback = (new DocTemplate())->renderResource('templates/default', [], [
        'lang' => 'en',
        'dir' => 'ltr',
        'pagetitle' => 'Legacy Slide No CSS Review',
        'title' => 'Legacy Slide Native Defaults',
        'subtitle' => 'WordPress review deck',
        'author' => ['Migration bot'],
        'institute' => ['Review desk'],
        'date' => '2026-06-09',
        'body' => '<section><h2>Imported no-CSS slide body</h2><figure><img src="review.png"><figcaption>Review image</figcaption></figure></section>',
        'dzslides-core' => '<script>window.__legacySlideNoCss = true;</script>',
    ], null, 'dzslides+smart');
    foreach ([
        "<link href='https://fonts.googleapis.com/css?family=Oswald' rel='stylesheet'>",
        "/* A section is a slide. It's size is 800x600, and this will never change */",
        'counter-increment: slideidx;',
        'blockquote:before {',
        'figure > img, figure > video {',
        '.view section[aria-selected] {',
        '.incremental > *[aria-selected] ~ * { opacity: 0; }',
        '<h1 class="title">Legacy Slide Native Defaults</h1>',
        '<span class="author">Migration bot</span> · <span class="institute">Review desk</span> · <span class="date">2026-06-09</span>',
        '<script>window.__legacySlideNoCss = true;</script>',
    ] as $needle) {
        if (!str_contains($dzslidesNoCssFallback, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate dzslides no-css fallback: {$needle}\n");
            exit(1);
        }
    }
    if (str_contains($dzslidesNoCssFallback, '<link rel="stylesheet" href="review-slides.css">')) {
        fwrite(STDERR, "Unexpected custom CSS link in doctemplate dzslides no-css fallback\n");
        exit(1);
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

    $breakablePartialSummary = (new DocTemplate())->renderWrapped(
        'Summary: $~$${ components/review-summary() }$~$',
        [],
        22,
        ['components/review-summary' => 'media links layout status'],
    );
    if ($breakablePartialSummary !== "Summary: media links\nlayout status") {
        fwrite(STDERR, "Unexpected doctemplate inherited breakable partial output\n");
        exit(1);
    }

    $nowrapBreakablePartialSummary = (new DocTemplate())->renderWrapped(
        'Summary: $~$${ components/review-summary()/nowrap }$~$',
        [],
        22,
        ['components/review-summary' => 'media links layout status'],
    );
    if ($nowrapBreakablePartialSummary !== 'Summary: media links layout status') {
        fwrite(STDERR, "Unexpected doctemplate nowrap inherited breakable partial output\n");
        exit(1);
    }

    $pipedVariableSources = (new DocTemplate())->render('Sources: $reviewSources/uppercase[ / ]$', $context);
    if ($pipedVariableSources !== 'Sources: MEDIA / LINKS / LAYOUT') {
        fwrite(STDERR, "Missing expected doctemplate piped variable separator output\n");
        exit(1);
    }

    $dollarSeparatedSources = (new DocTemplate())->render(
        'Sources: $reviewSources/uppercase[ $ ]$ / Ticket: $reviewMeta.review-id/left 8 "$" " USD"$',
        $context,
    );
    if ($dollarSeparatedSources !== 'Sources: MEDIA $ LINKS $ LAYOUT / Ticket: $PR-42    USD') {
        fwrite(STDERR, "Missing expected doctemplate dollar separator and border output\n");
        exit(1);
    }

    try {
        (new DocTemplate())->render('Broken: $reviewSources[ / ]/uppercase$', $context);
        fwrite(STDERR, "Expected doctemplate variable separator order rejection\n");
        exit(1);
    } catch (\UnexpectedValueException $exception) {
        if (!str_contains($exception->getMessage(), 'Doctemplate variable separators must follow pipe suffixes in reviewSources[ / ]/uppercase')) {
            fwrite(STDERR, "Unexpected doctemplate variable separator order diagnostic: {$exception->getMessage()}\n");
            exit(1);
        }
    }

    try {
        (new DocTemplate())->render('Broken: $reviewSources[a]b]$', $context);
        fwrite(STDERR, "Expected doctemplate malformed separator rejection\n");
        exit(1);
    } catch (\UnexpectedValueException $exception) {
        if (!str_contains($exception->getMessage(), 'Malformed doctemplate separator in reviewSources[a]b] at <template>:1:9')) {
            fwrite(STDERR, "Unexpected doctemplate malformed separator diagnostic: {$exception->getMessage()}\n");
            exit(1);
        }
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
        (new DocTemplate())->render('$if(primary)$primary$else$fallback$elseif(secondary)$secondary$endif$', [
            'primary' => false,
            'secondary' => true,
        ]);
        fwrite(STDERR, "Expected doctemplate conditional branch ordering rejection\n");
        exit(1);
    } catch (\UnexpectedValueException $exception) {
        if (!str_contains($exception->getMessage(), 'Unexpected doctemplate conditional branch elseif after else at <template>:1:35')) {
            fwrite(STDERR, "Unexpected doctemplate conditional branch diagnostic: {$exception->getMessage()}\n");
            exit(1);
        }
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
        if (!str_contains($exception->getMessage(), 'Unsupported doctemplate pipe no-such-pipe at review-packets/invalid.html:1:27')) {
            fwrite(STDERR, "Unexpected inactive branch parser validation diagnostic: {$exception->getMessage()}\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "OK wordpress doctemplate review packet\n");
    exit(0);
}

fwrite(STDOUT, $output . "\n");
