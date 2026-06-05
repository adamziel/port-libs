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
$hangulHan = "\u{1112}\u{1161}\u{11AB}";
$hangulGeul = "\u{1100}\u{1173}\u{11AF}";
$hangulExtended = "\u{A960}\u{D7B0}\u{D7CB}";
$hangulJamoSlices = UnicodeText::splitByDisplayBreakpoints($hangulHan . $hangulGeul . 'X', [2, 4]);
$indicDevanagari = "\u{0915}\u{093F}";
$indicTamil = "\u{0B95}\u{0BC8}";
$indicBengali = "\u{09AC}\u{09BE}\u{0982}\u{09B2}\u{09BE}";
$indicSlices = UnicodeText::splitByDisplayBreakpoints($indicDevanagari . $indicTamil . $indicBengali, [1, 2]);
$thaiSaraAm = "\u{0E01}\u{0E33}";
$laoSaraAm = "\u{0EA5}\u{0EB3}";
$thaiLaoAmSlices = UnicodeText::splitByDisplayBreakpoints($thaiSaraAm . $laoSaraAm . 'X', [2, 4]);
$softBreakAuditLines = UnicodeText::wrapByDisplayWidth("Zero\u{200B}width\u{200B}breaks soft\u{00AD}hyphen \u{9B5A}\u{200B}\u{9B5A} tail", 10, '  ');
$unicodeSeparatorAuditLines = UnicodeText::wrapByDisplayWidth(
    "CJK\u{3000}review\u{2003}queue\u{2028}Hard reset\u{2029}\u{9B5A}\u{3000}\u{9B5A} tail",
    10,
    '  '
);
$emojiCheckbox = "\u{2611}\u{FE0F}";
$emojiKeycap = "1\u{FE0F}\u{20E3}";
$emojiThumb = "\u{1F44D}\u{1F3FD}";
$emojiFlag = "\u{1F1FA}\u{1F1F8}";
$emojiSlices = UnicodeText::splitByDisplayBreakpoints($emojiCheckbox . $emojiKeycap . $emojiThumb . $emojiFlag, [2, 4, 6]);
$emojiTagFlag = "\u{1F3F4}\u{E0067}\u{E0062}\u{E0073}\u{E0063}\u{E0074}\u{E007F}";
$emojiHeartOnFire = "\u{2764}\u{FE0F}\u{200D}\u{1F525}";
$emojiRainbowFlag = "\u{1F3F3}\u{FE0F}\u{200D}\u{1F308}";
$emojiVariationZwjSlices = UnicodeText::splitByDisplayBreakpoints($emojiHeartOnFire . $emojiRainbowFlag, [2]);
$ambiguousText = "\u{00B7}\u{03A9}\u{2014}\u{2026}\u{2122}";
$ambiguousWideSlices = UnicodeText::splitByDisplayBreakpoints($ambiguousText, [2, 4, 6, 8], 'wide');
$supplementaryWideText = "\u{16FE0}\u{1B000}\u{1F200}\u{1F18E}";
$supplementaryWideSlices = UnicodeText::splitByDisplayBreakpoints($supplementaryWideText, [2, 4, 6]);
$defaultIgnorableText = "soft\u{00AD}hyphen / \u{FEFF}Title";
$defaultIgnorableWidth = UnicodeText::displayWidth("soft\u{00AD}hyphen") . ',' . UnicodeText::displayWidth("\u{FEFF}Title");
$formatControlText = "\u{0600}رقم \u{070F}ܣܘܪܝܝܐ \u{110BD}kaithi";
$formatControlWrap = UnicodeText::wrapByDisplayWidth("Audit \u{0600}رقم tail", 9, '  ');
$lineEndingConversions = $source->attr('sourceLineEndings')['conversions'] ?? 0;
$normalizedSource = (new MarkdownReader())->readBytes("# Cafe\xCC\x81 Review\n\nLegacy \xE2\x84\xAB source", 'utf-8', 'nfc');
$compatibilityNormalization = UnicodeText::normalize("\u{2460} \u{FB01} Cafe\u{0301} \u{212B}", 'nfkc');
$fallbackNormalization = UnicodeText::normalize("d\u{0307}\u{0323} Cafe\u{0301} \u{212B}", 'nfc', 'fallback');
$latinExtendedFallback = UnicodeText::normalize(
    "Zaz\u{0307}o\u{0301}łc\u{0301} ge\u{0328}s\u{0301}la\u{0328} jaz\u{0301}n\u{0301}"
        . " / C\u{030C}esky\u{0301} S\u{030C}te\u{030C}pa\u{0301}n, ku\u{030A}n\u{030C}, o\u{030B}u\u{030B}, s\u{0326}t\u{0326}",
    'nfc',
    'fallback'
);
$bomOverrideSource = (new MarkdownReader())->readBytes("\xFE\xFF\x00#\x00 \x8A\x08\x75\x3B\x00\x0A\x00\x0A\x00B\x00E", 'windows-1252');
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Hangul Jamo'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $hangulJamoSlices) . ' / ' . $hangulExtended])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $hangulJamoSlices)) . ' / ' . UnicodeText::displayWidth($hangulExtended)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Indic marks'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $indicSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $indicSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Thai/Lao AM'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $thaiLaoAmSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $thaiLaoAmSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Wrapped note'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $wrappedAuditLines)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $wrappedAuditLines))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Soft breaks'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $softBreakAuditLines)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $softBreakAuditLines))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Unicode separators'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $unicodeSeparatorAuditLines)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $unicodeSeparatorAuditLines))])]),
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Emoji tag flag'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $emojiTagFlag])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => (string) UnicodeText::displayWidth($emojiTagFlag)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Emoji ZWJ variation'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $emojiVariationZwjSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $emojiVariationZwjSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Ambiguous policy'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ambiguousText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => UnicodeText::displayWidth($ambiguousText) . '/' . UnicodeText::displayWidth($ambiguousText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Ambiguous wide slices'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $ambiguousWideSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(static fn (string $slice): int => UnicodeText::displayWidth($slice, 'wide'), $ambiguousWideSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Supplementary wide'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $supplementaryWideSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $supplementaryWideSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Default ignorables'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $defaultIgnorableText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $defaultIgnorableWidth])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Format controls'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $formatControlText . ' / ' . implode(' / ', $formatControlWrap)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => UnicodeText::displayWidth($formatControlText) . ' / ' . implode(',', array_map(UnicodeText::displayWidth(...), $formatControlWrap))])]),
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
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Fallback NFC'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $fallbackNormalization['text']])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $fallbackNormalization['implementation'] . ':' . (($fallbackNormalization['changed'] ?? false) ? 'changed' : 'unchanged')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Latin Extended NFC'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $latinExtendedFallback['text']])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $latinExtendedFallback['implementation'] . ':' . (($latinExtendedFallback['changed'] ?? false) ? 'changed' : 'unchanged')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'BOM override'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $bomOverrideSource->children[0]->attr('text') . ' / ' . $bomOverrideSource->children[1]->attr('text')])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($bomOverrideSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . ($bomOverrideSource->attr('sourceEncoding')['bom'] ?? '')])]),
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
    if (!str_contains($blocks, '<td>Hangul Jamo</td><td>' . $hangulHan . ' / ' . $hangulGeul . ' / X / ' . $hangulExtended . '</td><td>2,2,1 / 2</td>')) {
        throw new RuntimeException('charset handoff self-test missing Hangul Jamo display-width audit');
    }
    if (!str_contains($blocks, '<td>Indic marks</td><td>' . $indicDevanagari . ' / ' . $indicTamil . ' / ' . $indicBengali . '</td><td>1,1,2</td>')) {
        throw new RuntimeException('charset handoff self-test missing Indic spacing-mark display-width audit');
    }
    if (!str_contains($blocks, '<td>Thai/Lao AM</td><td>' . $thaiSaraAm . ' / ' . $laoSaraAm . ' / X</td><td>2,2,1</td>')) {
        throw new RuntimeException('charset handoff self-test missing Thai/Lao AM display-width audit');
    }
    if (!str_contains($blocks, "<td>Wrapped note</td><td>Import \u{9B5A}\u{9B5A} /   emoji \u{1F44D}\u{1F3FD} /   flag \u{1F1FA}\u{1F1F8} /   Cafe\u{0301} trail</td><td>11,10,9,12</td>")) {
        throw new RuntimeException('charset handoff self-test missing display-width wrap audit');
    }
    if (!str_contains($blocks, "<td>Soft breaks</td><td>Zerowidth /   breaks /   soft- /   hyphen /   \u{9B5A}\u{9B5A} /   tail</td><td>9,8,7,8,6,6</td>")) {
        throw new RuntimeException('charset handoff self-test missing soft-break wrap audit');
    }
    if (!str_contains($blocks, "<td>Unicode separators</td><td>CJK /   review /   queue / Hard reset / \u{9B5A}\u{3000}\u{9B5A} /   tail</td><td>3,8,7,10,6,6</td>")) {
        throw new RuntimeException('charset handoff self-test missing Unicode separator wrap audit');
    }
    if (!str_contains($blocks, "<td>Emoji slices</td><td>\u{2611}\u{FE0F} / 1\u{FE0F}\u{20E3} / \u{1F44D}\u{1F3FD} / \u{1F1FA}\u{1F1F8}</td><td>2,2,2,2</td>")) {
        throw new RuntimeException('charset handoff self-test missing emoji display-width audit');
    }
    if (!str_contains($blocks, '<td>Emoji tag flag</td><td>' . $emojiTagFlag . '</td><td>2</td>')) {
        throw new RuntimeException('charset handoff self-test missing emoji tag display-width audit');
    }
    if (!str_contains($blocks, '<td>Emoji ZWJ variation</td><td>' . $emojiHeartOnFire . ' / ' . $emojiRainbowFlag . '</td><td>2,2</td>')) {
        throw new RuntimeException('charset handoff self-test missing emoji ZWJ variation display-width audit');
    }
    if (!str_contains($blocks, "<td>Ambiguous policy</td><td>\u{00B7}\u{03A9}\u{2014}\u{2026}\u{2122}</td><td>5/10</td>")) {
        throw new RuntimeException('charset handoff self-test missing ambiguous-width policy audit');
    }
    if (!str_contains($blocks, "<td>Ambiguous wide slices</td><td>\u{00B7} / \u{03A9} / \u{2014} / \u{2026} / \u{2122}</td><td>2,2,2,2,2</td>")) {
        throw new RuntimeException('charset handoff self-test missing ambiguous-width split audit');
    }
    if (!str_contains($blocks, "<td>Supplementary wide</td><td>\u{16FE0} / \u{1B000} / \u{1F200} / \u{1F18E}</td><td>2,2,2,2</td>")) {
        throw new RuntimeException('charset handoff self-test missing supplementary East Asian wide audit');
    }
    if (!str_contains($blocks, "<td>Default ignorables</td><td>soft\u{00AD}hyphen / \u{FEFF}Title</td><td>10,5</td>")) {
        throw new RuntimeException('charset handoff self-test missing default-ignorable width audit');
    }
    if (!str_contains($blocks, "<td>Format controls</td><td>\u{0600}رقم \u{070F}ܣܘܪܝܝܐ \u{110BD}kaithi / Audit \u{0600}رقم /   tail</td><td>17 / 9,6</td>")) {
        throw new RuntimeException('charset handoff self-test missing prepended format-control width audit');
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
    if (!str_contains($blocks, "<td>Fallback NFC</td><td>\u{1E0D}\u{0307} Café Å</td><td>fallback:changed</td>")) {
        throw new RuntimeException('charset handoff self-test missing fallback NFC normalization audit row');
    }
    if (!str_contains($blocks, "<td>Latin Extended NFC</td><td>Zażółć gęślą jaźń / Český Štěpán, kůň, őű, șț</td><td>fallback:changed</td>")) {
        throw new RuntimeException('charset handoff self-test missing Latin Extended fallback normalization audit row');
    }
    if (($bomOverrideSource->attr('sourceEncoding')['encoding'] ?? '') !== 'utf-16be') {
        throw new RuntimeException('charset handoff self-test missing BOM override source encoding');
    }
    if (!str_contains($blocks, "<td>BOM override</td><td>計画 / BE</td><td>utf-16be:utf-16be</td>")) {
        throw new RuntimeException('charset handoff self-test missing BOM override audit row');
    }

    echo "charset unicode handoff self-test ok\n";
    return;
}

echo 'Encoding: ' . ($document->attr('sourceEncoding')['encoding'] ?? '') . "\n";
echo 'Repairs: ' . ($document->attr('sourceEncoding')['repairs'] ?? 0) . "\n\n";
echo 'Line ending conversions: ' . ($document->attr('sourceLineEndings')['conversions'] ?? 0) . "\n\n";
echo $markdown . "\n\n";
echo $blocks . "\n";
