<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\UnicodeText;
use PortLibs\Pandoc\WordPressBlockWriter;

$legacyBytes = "# Cafe\xE9 Review\r\n\r\nEditor \x93quoted\x94 source \x97 price \x8010.\rReviewer line ending note.";

$source = (new MarkdownReader())->readBytes($legacyBytes, 'windows-1252');
$displaySlices = UnicodeText::splitByDisplayBreakpoints("\u{9B5A}A\u{0301}\u{1F469}\u{200D}\u{1F4BB}B", [2, 3, 5]);
$wrappedAuditLines = UnicodeText::wrapByDisplayWidth(
    "Import \u{9B5A}\u{9B5A} emoji \u{1F44D}\u{1F3FD} flag \u{1F1FA}\u{1F1F8} Cafe\u{0301} trail",
    12,
    '  '
);
$emojiCheckbox = "\u{2611}\u{FE0F}";
$emojiKeycap = "1\u{FE0F}\u{20E3}";
$emojiThumb = "\u{1F44D}\u{1F3FD}";
$emojiFlag = "\u{1F1FA}\u{1F1F8}";
$emojiSlices = UnicodeText::splitByDisplayBreakpoints($emojiCheckbox . $emojiKeycap . $emojiThumb . $emojiFlag, [2, 4, 6]);
$lineEndingConversions = $source->attr('sourceLineEndings')['conversions'] ?? 0;
$normalizedSource = (new MarkdownReader())->readBytes("# Cafe\xCC\x81 Review\n\nLegacy \xE2\x84\xAB source", 'utf-8', 'nfc');
$compatibilityNormalization = UnicodeText::normalize("\u{2460} \u{FB01} Cafe\u{0301} \u{212B}", 'nfkc');
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
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Display slices'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $displaySlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $displaySlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Wrapped note'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $wrappedAuditLines)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $wrappedAuditLines))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Emoji checkbox'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $emojiCheckbox])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => (string) UnicodeText::displayWidth($emojiCheckbox)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Emoji modifier'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $emojiThumb . ' / ' . $emojiFlag])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => UnicodeText::displayWidth($emojiThumb) . ',' . UnicodeText::displayWidth($emojiFlag)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Emoji slices'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $emojiSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $emojiSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Line endings'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'CRLF and CR normalized'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => (string) $lineEndingConversions])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'NFC source title'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $normalizedSource->children[0]->attr('text')])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($normalizedSource->attr('sourceNormalization')['form'] ?? '') . ':' . (($normalizedSource->attr('sourceNormalization')['changed'] ?? false) ? 'changed' : 'unchanged')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'NFKC audit'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $compatibilityNormalization['text']])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $compatibilityNormalization['form'] . ':' . ($compatibilityNormalization['changed'] ? 'changed' : 'unchanged')])]),
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
    if (($document->attr('sourceLineEndings')['conversions'] ?? 0) !== 3) {
        throw new RuntimeException('charset handoff self-test missing line ending normalization audit');
    }
    if (str_contains($document->children[1]->attr('text'), "\r")) {
        throw new RuntimeException('charset handoff self-test leaked raw carriage returns');
    }
    if (!str_contains($markdown, "| \u{9B5A}\u{9B5A}")) {
        throw new RuntimeException('charset handoff self-test missing Unicode markdown table row');
    }
    if (!str_contains($blocks, "<td>\u{9B5A}\u{9B5A}</td><td>4</td>")) {
        throw new RuntimeException('charset handoff self-test missing WordPress Unicode table cells');
    }
    if (!str_contains($blocks, "<td>\u{9B5A} / A\u{0301} / \u{1F469}\u{200D}\u{1F4BB} / B</td><td>2,1,2,1</td>")) {
        throw new RuntimeException('charset handoff self-test missing display-width split audit');
    }
    if (!str_contains($blocks, "<td>Wrapped note</td><td>Import \u{9B5A}\u{9B5A} /   emoji \u{1F44D}\u{1F3FD} /   flag \u{1F1FA}\u{1F1F8} /   Cafe\u{0301} trail</td><td>11,10,9,12</td>")) {
        throw new RuntimeException('charset handoff self-test missing display-width wrap audit');
    }
    if (!str_contains($blocks, "<td>Emoji slices</td><td>\u{2611}\u{FE0F} / 1\u{FE0F}\u{20E3} / \u{1F44D}\u{1F3FD} / \u{1F1FA}\u{1F1F8}</td><td>2,2,2,2</td>")) {
        throw new RuntimeException('charset handoff self-test missing emoji display-width audit');
    }
    if (!str_contains($blocks, '<td>Line endings</td><td>CRLF and CR normalized</td><td>3</td>')) {
        throw new RuntimeException('charset handoff self-test missing line ending table audit');
    }
    if (($normalizedSource->attr('sourceNormalization')['form'] ?? '') !== 'nfc') {
        throw new RuntimeException('charset handoff self-test missing NFC source normalization metadata');
    }
    if ($normalizedSource->children[0]->attr('text') !== "Café Review") {
        throw new RuntimeException('charset handoff self-test missing normalized source heading');
    }
    if (!str_contains($blocks, "<td>NFC source title</td><td>Café Review</td><td>nfc:changed</td>")) {
        throw new RuntimeException('charset handoff self-test missing NFC normalization audit row');
    }
    if (!str_contains($blocks, "<td>NFKC audit</td><td>1 fi Café Å</td><td>nfkc:changed</td>")) {
        throw new RuntimeException('charset handoff self-test missing NFKC normalization audit row');
    }

    echo "charset unicode handoff self-test ok\n";
    return;
}

echo 'Encoding: ' . ($document->attr('sourceEncoding')['encoding'] ?? '') . "\n";
echo 'Repairs: ' . ($document->attr('sourceEncoding')['repairs'] ?? 0) . "\n\n";
echo 'Line ending conversions: ' . ($document->attr('sourceLineEndings')['conversions'] ?? 0) . "\n\n";
echo $markdown . "\n\n";
echo $blocks . "\n";
