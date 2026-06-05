<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\DocTemplate;

$templatePath = 'review-packets/review.html';
$resources = [
    $templatePath => <<<'HTML'
<article class="wp-import-review">
${ components/review-header() }
${ components/résumé() }
${ components/warning-list() }
${ review-body() }
</article>
HTML,
    'review-packets/components/review-header.html' => <<<'HTML'
<header>
<h1>$title/uppercase$</h1>
<p class="summary">$~$$warnings/length$ warnings queued for $title$$~$</p>
<p class="next-warning">${ warnings/rest/first:components/next-warning()/uppercase }</p>
<p class="ticket">Ticket: ${ review-id/left 8 "[" "]" }</p>
<p class="authors">$for(authors/pairs)$$it.value.name$$sep$, $endfor$</p>
<p class="audit-flag" data-suppressed="$suppressed$">Suppressed: <$suppressed$></p>
</header>
HTML,
    'review-packets/components/résumé.html' => <<<'HTML'
<p class="source-summary" data-état="$révision.état$">$révision.titre$</p>
HTML,
    'review-packets/components/next-warning.html' => '$it.source$: $it.message$',
    'review-packets/components/warning-list.html' => <<<'HTML'
$if(warnings)$
<ul class="warnings">
$for(warnings)$${ components/warning-row() }
$endfor$</ul>
$endif$
HTML,
    'review-packets/components/warning-row.html' => <<<'HTML'
<li data-index="$it.index$" data-source="$it.source$" data-review-title="$title$">$~$<span class="marker">$it.index/alpha/uppercase$.</span> <span class="source">${ it.source/uppercase/left 8 "{" "}" }</span> <span class="priority">$it.priority/roman/uppercase/right 4$</span> $it.message$$~$</li>
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
    'suppressed' => false,
    'révision' => ['état' => 'prêt', 'titre' => 'Résumé de migration'],
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

$output = (new DocTemplate())->renderResource($templatePath, $resources, $context, 'wp-data');

if (in_array('--self-test', $argv, true)) {
    foreach ([
        '<h1>BATCH 42 REVIEW</h1>',
        '<p class="summary">27 warnings queued for Batch 42 Review</p>',
        '<p class="next-warning">LINKS: VERIFY EDIT LINKS BEFORE PUBLISH</p>',
        '<p class="ticket">Ticket: [        ]</p>',
        '<p class="authors">Migration bot, Content editor</p>',
        '<p class="audit-flag" data-suppressed="">Suppressed: <></p>',
        '<p class="source-summary" data-état="prêt">Résumé de migration</p>',
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

    if (str_contains($output, "\n\n</section>")) {
        fwrite(STDERR, "Unexpected blank line before doctemplate review body close\n");
        exit(1);
    }

    $loopGuard = (new DocTemplate())->render('${ loop() }', [], [
        'loop' => '${ loop() }',
    ]);
    if ($loopGuard !== '(loop)') {
        fwrite(STDERR, "Missing expected doctemplate partial loop guard\n");
        exit(1);
    }

    fwrite(STDOUT, "OK wordpress doctemplate review packet\n");
    exit(0);
}

fwrite(STDOUT, $output . "\n");
