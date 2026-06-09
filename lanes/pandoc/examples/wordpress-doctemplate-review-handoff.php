<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\PandocTemplate;

$template = <<<'TEMPLATE'
---
title: $title$
needs_review: $needsReview$
---
$if(needsReview)$
<!-- wp:paragraph -->
<p>Review source: <a href="$source.url$">$source.label$</a></p>
<!-- /wp:paragraph -->
$endif$
$for(items)$
- $it.title$ ($it.status$)
$endfor$
TEMPLATE;

$context = [
    'title' => 'Migration packet',
    'needsReview' => true,
    'source' => [
        'url' => 'https://example.test/export?post=42&raw=1',
        'label' => 'original export',
    ],
    'items' => [
        ['title' => 'Post 42', 'status' => 'ready'],
        ['title' => 'Media 7', 'status' => 'needs alt text'],
    ],
];

$rendered = PandocTemplate::renderString($template, $context);

if (($argv[1] ?? '') === '--self-test') {
    $required = [
        'title: Migration packet',
        '<p>Review source: <a href="https://example.test/export?post=42&raw=1">original export</a></p>',
        "- Post 42 (ready)\n- Media 7 (needs alt text)",
    ];

    foreach ($required as $needle) {
        if (!str_contains($rendered, $needle)) {
            fwrite(STDERR, "Missing expected template output: {$needle}\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "wordpress doctemplate review handoff self-test passed\n");
    exit(0);
}

echo $rendered . "\n";
