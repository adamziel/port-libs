<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\UnicodeText;
use PortLibs\Pandoc\WordPressBlockWriter;

$legacyBytes = "# Cafe\xE9 Review\n\nEditor \x93quoted\x94 source \x97 price \x8010.";

$source = (new MarkdownReader())->readBytes($legacyBytes, 'windows-1252');
$table = new AstNode('table', [
    'caption' => 'Unicode width audit',
    'alignments' => ['default', 'default', 'default'],
], [
    new AstNode('table_head', [], [
        new AstNode('table_row', ['header' => true], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Text'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Width'])]),
        ]),
    ]),
    new AstNode('table_body', [], [
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'CJK title'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => "\u{9B5A}\u{9B5A}"])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => (string) UnicodeText::displayWidth("\u{9B5A}\u{9B5A}")])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'German slug'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => "Auf\u{200C}lage"])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => (string) UnicodeText::displayWidth("Auf\u{200C}lage")])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Combining mark'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => "Cafe\u{0301}"])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => (string) UnicodeText::displayWidth("Cafe\u{0301}")])]),
        ]),
    ]),
]);
$document = new AstNode('document', $source->attrs, [...$source->children, $table]);

$markdown = (new MarkdownWriter())->write($document);
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    if (($document->attr('sourceEncoding')['encoding'] ?? '') !== 'windows-1252') {
        throw new RuntimeException('charset handoff self-test missing Windows-1252 source encoding');
    }
    if (!str_contains($document->children[1]->attr('text'), "\u{201C}quoted\u{201D} source \u{2014} price \u{20AC}10")) {
        throw new RuntimeException('charset handoff self-test missing decoded smart punctuation');
    }
    if (!str_contains($markdown, "| \u{9B5A}\u{9B5A}")) {
        throw new RuntimeException('charset handoff self-test missing Unicode markdown table row');
    }
    if (!str_contains($blocks, "<td>\u{9B5A}\u{9B5A}</td><td>4</td>")) {
        throw new RuntimeException('charset handoff self-test missing WordPress Unicode table cells');
    }

    echo "charset unicode handoff self-test ok\n";
    return;
}

echo 'Encoding: ' . ($document->attr('sourceEncoding')['encoding'] ?? '') . "\n";
echo 'Repairs: ' . ($document->attr('sourceEncoding')['repairs'] ?? 0) . "\n\n";
echo $markdown . "\n\n";
echo $blocks . "\n";
