<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\DocTemplate;

$template = <<<'HTML'
<article class="wp-import-review">
<header>
<h1>$title/uppercase$</h1>
<p class="summary">$warnings/length$ warnings queued for $title$</p>
<p class="authors">$for(authors/pairs)$$it.value.name$$sep$, $endfor$</p>
</header>
$if(warnings)$
<ul class="warnings">
$for(warnings/pairs)$<li data-index="$it.key$" data-source="$it.value.source$">$it.value.message$</li>
$endfor$</ul>
$endif$
<section class="wp-import-body">
  $^$$body$
</section>
</article>
HTML;

$context = [
    'title' => 'Batch 42 Review',
    'authors' => [
        ['name' => 'Migration bot'],
        ['name' => 'Content editor'],
    ],
    'warnings' => [
        ['source' => 'media', 'message' => 'Check &amp; confirm alt text'],
        ['source' => 'links', 'message' => 'Verify edit links before publish'],
    ],
    'body' => implode("\n", [
        '<!-- wp:paragraph --><p>Imported body is already escaped.</p><!-- /wp:paragraph -->',
        '<!-- wp:paragraph --><p>Second reviewer packet line stays nested.</p><!-- /wp:paragraph -->',
    ]),
];

$output = (new DocTemplate())->render($template, $context);

if (in_array('--self-test', $argv, true)) {
    foreach ([
        '<h1>BATCH 42 REVIEW</h1>',
        '<p class="summary">2 warnings queued for Batch 42 Review</p>',
        '<p class="authors">Migration bot, Content editor</p>',
        '<li data-index="1" data-source="media">Check &amp; confirm alt text</li>',
        '  <!-- wp:paragraph --><p>Imported body is already escaped.</p><!-- /wp:paragraph -->',
        '  <!-- wp:paragraph --><p>Second reviewer packet line stays nested.</p><!-- /wp:paragraph -->',
    ] as $needle) {
        if (!str_contains($output, $needle)) {
            fwrite(STDERR, "Missing expected doctemplate output: {$needle}\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "OK wordpress doctemplate review packet\n");
    exit(0);
}

fwrite(STDOUT, $output . "\n");
