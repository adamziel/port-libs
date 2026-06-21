<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);

$document = new AstNode('document', [], [
    new AstNode('heading', ['level' => 2, 'id' => 'literal-source-markers'], [
        $text('Literal Source Markers'),
    ]),
    $paragraph('1. Legacy export used this as a paragraph label, not an ordered list.'),
    $paragraph('(2) Reviewer checkpoint copied from a source worksheet.'),
    $paragraph('- Source system stored a dash-prefixed note body.'),
    $paragraph('% Imported title-like line must remain ordinary body text.'),
    new AstNode('bullet_list', [], [
        new AstNode('list_item', [], [
            new AstNode('paragraph', [], [
                $text('1. Nested reviewer paragraph remains text inside the checklist item.'),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter())->write($document) . "\n";
