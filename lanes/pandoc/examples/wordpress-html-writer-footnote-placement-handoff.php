<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [], [
    new AstNode('heading', ['level' => 2], [$text('Reviewer HTML Packet')]),
    $paragraph([
        $text('Import source trail'),
        new AstNode('note', [], [
            $paragraph([
                $text('Confirm the legacy post before publishing in WordPress.'),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [$text('Edit imported post')]),
            ]),
        ]),
        $text(' stays attached to the paragraph.'),
    ]),
    new AstNode('blockquote', [], [
        $paragraph([
            $text('Editorial caveat'),
            new AstNode('note', [], [
                $paragraph([$text('Keep this reviewer note scoped to the quote block.')]),
            ]),
            $text('.'),
        ]),
    ]),
]);

echo (new HtmlWriter(['referenceLocation' => 'end_of_block']))->write($document) . "\n";
