<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Converted body ']),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [new AstNode('text', ['text' => 'source edit'])]),
        new AstNode('text', ['text' => '.']),
    ]),
]);

try {
    echo (new MarkdownWriter([
        'variant' => 'plain',
        'templatePath' => 'templates/review-packet.txt',
        'template' => <<<'TPL'
Review packet
${ reviewer-card() }
Body: $body/chomp$
TPL,
        'partials' => [
            'reviewer-card' => "Reviewer: Editor\n" . '$with syntax error',
        ],
    ]))->write($document);
} catch (InvalidArgumentException $exception) {
    echo "Template diagnostic:\n";
    echo $exception->getMessage() . "\n";
}
