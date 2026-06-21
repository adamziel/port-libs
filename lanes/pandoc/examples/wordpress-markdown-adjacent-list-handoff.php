<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$item = static fn (string $value): AstNode => new AstNode('list_item', [], [$text($value)]);

$document = new AstNode('document', [], [
    new AstNode('heading', ['level' => 2, 'id' => 'review-queues'], [$text('Review Queues')]),
    new AstNode('paragraph', [], [
        $text('Adjacent Markdown lists stay separate for WordPress reviewer handoff.'),
    ]),
    new AstNode('bullet_list', [], [
        $item('Review imported posts'),
        $item('Confirm source permalinks'),
    ]),
    new AstNode('bullet_list', [], [
        $item('Audit media attachments'),
        $item('Record missing alt text'),
    ]),
    new AstNode('ordered_list', ['start' => 1], [
        $item('Import first batch'),
        $item('Import second batch'),
    ]),
    new AstNode('ordered_list', [
        'start' => 3,
        'style' => 'lower_alpha',
        'delimiter' => 'period',
    ], [
        $item('Reviewer alpha queue'),
        $item('Publish approved posts'),
    ]),
]);

echo (new MarkdownWriter())->write($document) . "\n";
