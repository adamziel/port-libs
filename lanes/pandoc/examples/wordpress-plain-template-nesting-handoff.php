<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$document = new AstNode('document', [
    'meta' => [
        'batch' => 'wp-nesting-42',
        'description' => "Media archive import needs alt text review\n\nCaption reconciliation must stay grouped",
        'status' => 'needs review',
        'owner' => 'editorial',
        'summary' => "Source batch has posts, media, and comments\nReviewer packet should keep this continuation aligned",
        'legalHold' => 'Preserve source attachments before publish',
    ],
], [
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Converted source body references ']),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [new AstNode('text', ['text' => 'source edit'])]),
        new AstNode('text', ['text' => ' and reviewer-only import notes.']),
    ]),
]);

$template = <<<'TPL'
WordPress plain nesting template packet
Batch: $batch$
Review: $^$$description$ [$status$]
        Owner: $owner$
Summary:
  $summary$
Notice: $^$${ notice() }
Legal:
$if(legalHold)$

  $legalHold$
$endif$

$body/chomp$
TPL;

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => $template,
    'partials' => [
        'notice' => "Legal hold details stay visible\nbefore publish",
    ],
]))->write($document) . "\n";
