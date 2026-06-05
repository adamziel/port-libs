<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\UnicodeText;
use PortLibs\Pandoc\WordPressBlockWriter;

$utf32beBytes = static function (array $codepoints): string {
    $bytes = '';
    foreach ($codepoints as $codepoint) {
        $bytes .= pack('N', $codepoint);
    }

    return $bytes;
};

$legacyBytes = "# Cafe\xE9 Review\r\n\r\nEditor \x93quoted\x94 source \x97 price \x8010.\rReviewer line ending note.";

$source = (new MarkdownReader())->readBytes($legacyBytes, 'windows-1252');
$latin9Source = (new MarkdownReader())->readBytes("# Latin9 Import\n\nPrice \xA410; \xBCuvre, c\xBDur, \xBE, \xA6umava, and \xB8.", 'latin-9');
$latin9Text = (string) $latin9Source->children[1]->attr('text');
$macRomanSource = (new MarkdownReader())->readBytes("# Mac Import\n\nClassic \xD2quoted\xD3 source \xD1 price \xDB10; caf\x8E and \xDEle.", 'mac-roman');
$macRomanText = (string) $macRomanSource->children[1]->attr('text');
$windows1250Bytes = "# Central Import\n\nZa\xBF\xF3\xB3\xE6 g\xEA\x9Cl\xB9 ja\x9F\xF1; \xC8esk\xFD \x8At\xECp\xE1n; k\xF9\xF2; \xF5\xFB; \x93quoted\x94 \x97 \x8010.";
$windows1250Source = (new MarkdownReader())->readBytes($windows1250Bytes, 'cp1250');
$windows1250Text = (string) $windows1250Source->children[1]->attr('text');
$latin2Bytes = "# Latin2 Import\n\nZa\xBF\xF3\xB3\xE6 g\xEA\xB6l\xB1 ja\xBC\xF1; \xC8esk\xFD \xA9t\xECp\xE1n; k\xF9\xF2; \xF5\xFB.";
$latin2Source = (new MarkdownReader())->readBytes($latin2Bytes, 'latin-2');
$latin2Text = (string) $latin2Source->children[1]->attr('text');
$shiftJisBytes = (string) hex2bin('23208c7689e60a0a967b95b682c694bc8a70b6c0b6c581418adb874094678160fbfc8de88142');
$shiftJisSource = (new MarkdownReader())->readBytes($shiftJisBytes, 'windows-31j');
$shiftJisText = (string) $shiftJisSource->children[1]->attr('text');
$eucJpBytes = (string) hex2bin('2320b7d7b2e80a0acbdccab8a4c8c8beb3d18eb68ec08eb68ec5a1a2b4ddada1c7c8a1c1baeaa1a3');
$eucJpSource = (new MarkdownReader())->readBytes($eucJpBytes, 'x-euc-jp');
$eucJpText = (string) $eucJpSource->children[1]->attr('text');
$iso2022JpBytes = "# \x1B\$B\x37\x57\x32\x68\x1B(B\n\n"
    . "\x1B\$B\x4B\x5C\x4A\x38\x24\x48\x48\x3E\x33\x51\x1B(I\x36\x40\x36\x45"
    . "\x1B\$B\x21\x22\x34\x5D\x2D\x21\x47\x48\x21\x41\x3A\x6A\x21\x23"
    . "\x1B(J \x5C\x7E\x1B(B ASCII";
$iso2022JpSource = (new MarkdownReader())->readBytes($iso2022JpBytes, 'csiso2022jp');
$iso2022JpText = (string) $iso2022JpSource->children[1]->attr('text');
$big5Bytes = (string) hex2bin('2320a4a4a4e50a0aa4a4a4e5204269673520b4fab8d5a141adbbb4e4a143');
$big5Source = (new MarkdownReader())->readBytes($big5Bytes, 'big5-hkscs');
$big5Text = (string) $big5Source->children[1]->attr('text');
$gbkBytes = (string) hex2bin('2320bcf2cce50a0ad6d0cec42047424b20b2e2cad4a3acb1b1bea9a1a3');
$gbkSource = (new MarkdownReader())->readBytes($gbkBytes, 'gb18030');
$gbkText = (string) $gbkSource->children[1]->attr('text');
$eucKrBytes = (string) hex2bin('2320c7d1b1db0a0ac7d1b1db204555432d4b5220c5d7bdbac6ae2c20bcadbfef2e');
$eucKrSource = (new MarkdownReader())->readBytes($eucKrBytes, 'ks_c_5601-1987');
$eucKrText = (string) $eucKrSource->children[1]->attr('text');
$hzGb2312Bytes = "# ~{<rLe~}\n\n~{VPND~} HZ ~{2bJT#,11>)!#~}";
$hzGb2312Source = (new MarkdownReader())->readBytes($hzGb2312Bytes, 'hz-gb-2312');
$hzGb2312Text = (string) $hzGb2312Source->children[1]->attr('text');
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
$indicViramaDevanagari = "\u{0915}\u{094D}\u{0937}";
$indicViramaZwjDevanagari = "\u{0915}\u{094D}\u{200D}\u{0937}";
$indicViramaBengali = "\u{0995}\u{09CD}\u{09A4}";
$indicViramaSlices = UnicodeText::splitByDisplayBreakpoints(
    $indicViramaDevanagari . $indicViramaZwjDevanagari . $indicViramaBengali,
    [1, 2]
);
$myanmarConjunct = "\u{1000}\u{1039}\u{1000}";
$khmerConjunct = "\u{1780}\u{17D2}\u{1780}";
$southeastAsianConjunctSlices = UnicodeText::splitByDisplayBreakpoints($myanmarConjunct . $khmerConjunct . 'X', [1, 2]);
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
$textVariationSmile = "\u{263A}\u{FE0E}";
$textVariationCopyright = "\u{00A9}\u{FE0E}";
$textVariationHeart = "\u{2764}\u{FE0E}";
$textVariationPlain = "A\u{FE0E}";
$emojiKeycap = "1\u{FE0F}\u{20E3}";
$emojiThumb = "\u{1F44D}\u{1F3FD}";
$emojiStandaloneSkinTone = "\u{1F3FD}";
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
$kanaExtendedBText = "\u{1AFF0}\u{1AFF5}\u{1AFFD}";
$kanaExtendedBSlices = UnicodeText::splitByDisplayBreakpoints($kanaExtendedBText . 'X', [2, 4, 6]);
$rareEastAsianScriptText = "\u{17000}\u{18800}\u{18B00}\u{18D00}";
$rareEastAsianScriptSlices = UnicodeText::splitByDisplayBreakpoints($rareEastAsianScriptText . 'X', [2, 4, 6, 8]);
$bmpWideEmojiText = "\u{231A}\u{2705}\u{2B50}\u{26FD}";
$bmpWideEmojiSlices = UnicodeText::splitByDisplayBreakpoints($bmpWideEmojiText, [2, 4, 6]);
$geometricEmojiText = "\u{1F7E0}\u{1F7E9}\u{1F7F0}";
$geometricEmojiSlices = UnicodeText::splitByDisplayBreakpoints($geometricEmojiText, [2, 4]);
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
$utf32BomSource = (new MarkdownReader())->readBytes("\x00\x00\xFE\xFF" . $utf32beBytes([
    0x0023,
    0x0020,
    0x1f4da,
    0x0020,
    0x0052,
    0x0065,
    0x0076,
    0x0069,
    0x0065,
    0x0077,
    0x000a,
    0x000a,
    0x8a08,
    0x753b,
]), 'windows-1252');
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Indic virama'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $indicViramaSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $indicViramaSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Myanmar/Khmer conjuncts'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $southeastAsianConjunctSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $southeastAsianConjunctSlices))])]),
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Text variation'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $textVariationSmile . ' / ' . $textVariationCopyright . ' / ' . $textVariationHeart . ' / ' . $textVariationPlain])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => UnicodeText::displayWidth($textVariationSmile) . ',' . UnicodeText::displayWidth($textVariationCopyright) . ',' . UnicodeText::displayWidth($textVariationHeart) . ',' . UnicodeText::displayWidth($textVariationPlain)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Emoji modifier'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $emojiThumb . ' / ' . $emojiFlag])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => UnicodeText::displayWidth($emojiThumb) . ',' . UnicodeText::displayWidth($emojiFlag)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Emoji skin tone'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $emojiThumb . ' / ' . $emojiStandaloneSkinTone . ' / A' . $emojiStandaloneSkinTone])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => UnicodeText::displayWidth($emojiThumb) . ',' . UnicodeText::displayWidth($emojiStandaloneSkinTone) . ',' . UnicodeText::displayWidth('A' . $emojiStandaloneSkinTone)])]),
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Kana Extended-B'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $kanaExtendedBSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $kanaExtendedBSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Rare CJK scripts'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $rareEastAsianScriptSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $rareEastAsianScriptSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'BMP emoji wide'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $bmpWideEmojiSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $bmpWideEmojiSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Geometric emoji wide'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $geometricEmojiSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $geometricEmojiSlices))])]),
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Latin-9 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $latin9Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($latin9Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($latin9Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'MacRoman source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $macRomanText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($macRomanSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($macRomanText)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Windows-1250 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $windows1250Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($windows1250Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($windows1250Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Latin-2 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $latin2Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($latin2Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($latin2Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Shift_JIS source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $shiftJisText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($shiftJisSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($shiftJisText) . '/' . UnicodeText::displayWidth($shiftJisText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'EUC-JP source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $eucJpText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($eucJpSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($eucJpText) . '/' . UnicodeText::displayWidth($eucJpText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'ISO-2022-JP source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $iso2022JpText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($iso2022JpSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($iso2022JpText) . '/' . UnicodeText::displayWidth($iso2022JpText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Big5 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $big5Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($big5Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($big5Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'GBK source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $gbkText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($gbkSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($gbkText)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'EUC-KR source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $eucKrText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($eucKrSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($eucKrText)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'HZ-GB-2312 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $hzGb2312Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($hzGb2312Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($hzGb2312Text)])]),
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
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'UTF-32 BOM source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $utf32BomSource->children[0]->attr('text') . ' / ' . $utf32BomSource->children[1]->attr('text')])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($utf32BomSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . ($utf32BomSource->attr('sourceEncoding')['bom'] ?? '') . ':' . UnicodeText::displayWidth((string) $utf32BomSource->children[0]->attr('text'))])]),
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
    if (!str_contains($blocks, '<td>Indic virama</td><td>' . $indicViramaDevanagari . ' / ' . $indicViramaZwjDevanagari . ' / ' . $indicViramaBengali . '</td><td>1,1,1</td>')) {
        throw new RuntimeException('charset handoff self-test missing Indic virama display-width audit');
    }
    if (!str_contains($blocks, '<td>Myanmar/Khmer conjuncts</td><td>' . $myanmarConjunct . ' / ' . $khmerConjunct . ' / X</td><td>1,1,1</td>')) {
        throw new RuntimeException('charset handoff self-test missing Myanmar/Khmer conjunct display-width audit');
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
    if (!str_contains($blocks, '<td>Text variation</td><td>' . $textVariationSmile . ' / ' . $textVariationCopyright . ' / ' . $textVariationHeart . ' / ' . $textVariationPlain . '</td><td>2,2,2,1</td>')) {
        throw new RuntimeException('charset handoff self-test missing text variation-selector width audit');
    }
    if (!str_contains($blocks, "<td>Emoji skin tone</td><td>\u{1F44D}\u{1F3FD} / \u{1F3FD} / A\u{1F3FD}</td><td>2,2,3</td>")) {
        throw new RuntimeException('charset handoff self-test missing unattached emoji skin-tone width audit');
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
    if (!str_contains($blocks, "<td>Kana Extended-B</td><td>\u{1AFF0} / \u{1AFF5} / \u{1AFFD} / X</td><td>2,2,2,1</td>")) {
        throw new RuntimeException('charset handoff self-test missing Kana Extended-B width audit');
    }
    if (!str_contains($blocks, "<td>Rare CJK scripts</td><td>\u{17000} / \u{18800} / \u{18B00} / \u{18D00} / X</td><td>2,2,2,2,1</td>")) {
        throw new RuntimeException('charset handoff self-test missing rare East Asian script width audit');
    }
    if (!str_contains($blocks, "<td>BMP emoji wide</td><td>\u{231A} / \u{2705} / \u{2B50} / \u{26FD}</td><td>2,2,2,2</td>")) {
        throw new RuntimeException('charset handoff self-test missing BMP East Asian wide emoji audit');
    }
    if (!str_contains($blocks, "<td>Geometric emoji wide</td><td>\u{1F7E0} / \u{1F7E9} / \u{1F7F0}</td><td>2,2,2</td>")) {
        throw new RuntimeException('charset handoff self-test missing geometric emoji wide audit');
    }
    if (!str_contains($blocks, "<td>Default ignorables</td><td>soft\u{00AD}hyphen / \u{FEFF}Title</td><td>10,5</td>")) {
        throw new RuntimeException('charset handoff self-test missing default-ignorable width audit');
    }
    if (!str_contains($blocks, "<td>Format controls</td><td>\u{0600}رقم \u{070F}ܣܘܪܝܝܐ \u{110BD}kaithi / Audit \u{0600}رقم /   tail</td><td>17 / 9,6</td>")) {
        throw new RuntimeException('charset handoff self-test missing prepended format-control width audit');
    }
    if (($latin9Source->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-8859-15') {
        throw new RuntimeException('charset handoff self-test missing Latin-9 source encoding');
    }
    if (!str_contains($blocks, "<td>Latin-9 source</td><td>Price €10; Œuvre, cœur, Ÿ, Šumava, and ž.</td><td>iso-8859-15:41</td>")) {
        throw new RuntimeException('charset handoff self-test missing Latin-9 decode audit row');
    }
    if (($macRomanSource->attr('sourceEncoding')['encoding'] ?? '') !== 'macintosh') {
        throw new RuntimeException('charset handoff self-test missing MacRoman source encoding');
    }
    if (!str_contains($blocks, "<td>MacRoman source</td><td>Classic “quoted” source — price €10; café and ﬁle.</td><td>macintosh:50</td>")) {
        throw new RuntimeException('charset handoff self-test missing MacRoman decode audit row');
    }
    if (($windows1250Source->attr('sourceEncoding')['encoding'] ?? '') !== 'windows-1250') {
        throw new RuntimeException('charset handoff self-test missing Windows-1250 source encoding');
    }
    if (!str_contains($blocks, "<td>Windows-1250 source</td><td>Zażółć gęślą jaźń; Český Štěpán; kůň; őű; “quoted” — €10.</td><td>windows-1250:57</td>")) {
        throw new RuntimeException('charset handoff self-test missing Windows-1250 decode audit row');
    }
    if (($latin2Source->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-8859-2') {
        throw new RuntimeException('charset handoff self-test missing Latin-2 source encoding');
    }
    if (!str_contains($blocks, "<td>Latin-2 source</td><td>Zażółć gęślą jaźń; Český Štěpán; kůň; őű.</td><td>iso-8859-2:41</td>")) {
        throw new RuntimeException('charset handoff self-test missing Latin-2 decode audit row');
    }
    if (($shiftJisSource->attr('sourceEncoding')['encoding'] ?? '') !== 'shift_jis') {
        throw new RuntimeException('charset handoff self-test missing Shift_JIS source encoding');
    }
    if (!str_contains($blocks, "<td>Shift_JIS source</td><td>本文と半角ｶﾀｶﾅ、丸①波～髙崎。</td><td>shift_jis:29/30</td>")) {
        throw new RuntimeException('charset handoff self-test missing Shift_JIS decode audit row');
    }
    if (($eucJpSource->attr('sourceEncoding')['encoding'] ?? '') !== 'euc-jp') {
        throw new RuntimeException('charset handoff self-test missing EUC-JP source encoding');
    }
    if (!str_contains($blocks, "<td>EUC-JP source</td><td>本文と半角ｶﾀｶﾅ、丸①波～崎。</td><td>euc-jp:27/28</td>")) {
        throw new RuntimeException('charset handoff self-test missing EUC-JP decode audit row');
    }
    if (($iso2022JpSource->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-2022-jp') {
        throw new RuntimeException('charset handoff self-test missing ISO-2022-JP source encoding');
    }
    if (!str_contains($blocks, "<td>ISO-2022-JP source</td><td>本文と半角ｶﾀｶﾅ、丸①波～崎。 ¥‾ ASCII</td><td>iso-2022-jp:36/38</td>")) {
        throw new RuntimeException('charset handoff self-test missing ISO-2022-JP decode audit row');
    }
    if (($big5Source->attr('sourceEncoding')['encoding'] ?? '') !== 'big5') {
        throw new RuntimeException('charset handoff self-test missing Big5 source encoding');
    }
    if (!str_contains($blocks, '<td>Big5 source</td><td>中文 Big5 測試，香港。</td><td>big5:22</td>')) {
        throw new RuntimeException('charset handoff self-test missing Big5 decode audit row');
    }
    if (($gbkSource->attr('sourceEncoding')['encoding'] ?? '') !== 'gbk') {
        throw new RuntimeException('charset handoff self-test missing GBK source encoding');
    }
    if (!str_contains($blocks, '<td>GBK source</td><td>中文 GBK 测试，北京。</td><td>gbk:21</td>')) {
        throw new RuntimeException('charset handoff self-test missing GBK decode audit row');
    }
    if (($eucKrSource->attr('sourceEncoding')['encoding'] ?? '') !== 'euc-kr') {
        throw new RuntimeException('charset handoff self-test missing EUC-KR source encoding');
    }
    if (!str_contains($blocks, '<td>EUC-KR source</td><td>한글 EUC-KR 테스트, 서울.</td><td>euc-kr:25</td>')) {
        throw new RuntimeException('charset handoff self-test missing EUC-KR decode audit row');
    }
    if (($hzGb2312Source->attr('sourceEncoding')['encoding'] ?? '') !== 'hz-gb-2312') {
        throw new RuntimeException('charset handoff self-test missing HZ-GB-2312 source encoding');
    }
    if (!str_contains($blocks, '<td>HZ-GB-2312 source</td><td>中文 HZ 测试，北京。</td><td>hz-gb-2312:20</td>')) {
        throw new RuntimeException('charset handoff self-test missing HZ-GB-2312 decode audit row');
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
    if (($utf32BomSource->attr('sourceEncoding')['encoding'] ?? '') !== 'utf-32be') {
        throw new RuntimeException('charset handoff self-test missing UTF-32 source encoding');
    }
    if (!str_contains($blocks, "<td>UTF-32 BOM source</td><td>\u{1F4DA} Review / 計画</td><td>utf-32be:utf-32be:9</td>")) {
        throw new RuntimeException('charset handoff self-test missing UTF-32 BOM audit row');
    }

    echo "charset unicode handoff self-test ok\n";
    return;
}

echo 'Encoding: ' . ($document->attr('sourceEncoding')['encoding'] ?? '') . "\n";
echo 'Repairs: ' . ($document->attr('sourceEncoding')['repairs'] ?? 0) . "\n\n";
echo 'Line ending conversions: ' . ($document->attr('sourceLineEndings')['conversions'] ?? 0) . "\n\n";
echo $markdown . "\n\n";
echo $blocks . "\n";
