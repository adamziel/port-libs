<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\DocTemplate;

$templatePath = 'review-packets/review.html';
$resources = [
    $templatePath => <<<'HTML'
<article class="wp-import-review">
${ review-header() }
${ warning-list() }
${ review-body() }
</article>
HTML,
    'review-packets/review-header.html' => <<<'HTML'
<header>
<h1>$title/uppercase$</h1>
<p class="summary">$~$$warnings/length$ warnings queued for $title$$~$</p>
<p class="authors">$for(authors/pairs)$$it.value.name$$sep$, $endfor$</p>
</header>
HTML,
    'review-packets/warning-list.html' => <<<'HTML'
$if(warnings)$
<ul class="warnings">
$for(warnings/pairs)$<li data-index="$it.key$" data-source="$it.value.source$">$~$<span class="marker">$it.key/alpha/uppercase$.</span> <span class="source">$it.value.source/uppercase/left 8$</span> <span class="priority">$it.value.priority/roman/uppercase/right 4$</span> $it.value.message$$~$</li>
$endfor$</ul>
$endif$
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
    'warnings' => [
        ['source' => 'media', 'priority' => 1, 'message' => 'Check &amp; confirm alt text'],
        ['source' => 'links', 'priority' => 4, 'message' => 'Verify edit links before publish'],
        ['source' => '魚', 'priority' => 9, 'message' => 'Confirm multilingual source label spacing'],
    ],
    'body' => implode("\n", [
        '<!-- wp:paragraph --><p>Imported body is already escaped.</p><!-- /wp:paragraph -->',
        '<!-- wp:paragraph --><p>Second reviewer packet line stays nested.</p><!-- /wp:paragraph -->',
    ]) . "\n",
];

$output = (new DocTemplate())->renderResource($templatePath, $resources, $context, 'wp-data');

if (in_array('--self-test', $argv, true)) {
    foreach ([
        '<h1>BATCH 42 REVIEW</h1>',
        '<p class="summary">3 warnings queued for Batch 42 Review</p>',
        '<p class="authors">Migration bot, Content editor</p>',
        '<li data-index="1" data-source="media"><span class="marker">A.</span> <span class="source">MEDIA   </span> <span class="priority">   I</span> Check &amp; confirm alt text</li>',
        '<li data-index="2" data-source="links"><span class="marker">B.</span> <span class="source">LINKS   </span> <span class="priority">  IV</span> Verify edit links before publish</li>',
        '<li data-index="3" data-source="魚"><span class="marker">C.</span> <span class="source">魚      </span> <span class="priority">  IX</span> Confirm multilingual source label spacing</li>',
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
