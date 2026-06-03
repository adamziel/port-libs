<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\DocTemplate;

$template = <<<'HTML'
<article class="wp-import-review">
<header>
<h1>$title$</h1>
<p class="authors">$for(authors)$$it.name$$sep$, $endfor$</p>
</header>
$if(warnings)$
<ul class="warnings">
$for(warnings)$<li data-source="$it.source$">$it.message$</li>
$endfor$</ul>
$endif$
$body$
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
    'body' => '<!-- wp:paragraph --><p>Imported body is already escaped.</p><!-- /wp:paragraph -->',
];

$output = (new DocTemplate())->render($template, $context);

if (in_array('--self-test', $argv, true)) {
    foreach ([
        '<h1>Batch 42 Review</h1>',
        '<p class="authors">Migration bot, Content editor</p>',
        '<li data-source="media">Check &amp; confirm alt text</li>',
        '<!-- wp:paragraph --><p>Imported body is already escaped.</p><!-- /wp:paragraph -->',
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
