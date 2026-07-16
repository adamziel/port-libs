<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$heading = static fn (int $level, string $text): AstNode => new AstNode('heading', ['level' => $level], [
    new AstNode('text', ['text' => $text]),
]);

$document = new AstNode('document', [], [
    $heading(1, 'Imported Article Review'),
    $heading(2, 'Source Notes'),
    $paragraph([
        $text('Source URL needs verification'),
        new AstNode('note', [], [
            $paragraph([
                $text('Open the original import packet before publishing.'),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=77&action=edit',
                ], [$text('Review source post')]),
            ]),
        ]),
        $text('.'),
    ]),
    $heading(2, 'Publish Checklist'),
    $paragraph([$text('Confirm media ownership.')]),
]);

echo (new HtmlWriter([
    'referenceLocation' => 'end_of_section',
    'writerSectionDivs' => true,
]))->write($document) . "\n";
