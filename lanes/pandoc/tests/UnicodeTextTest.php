<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\UnicodeText;
use PortLibs\Pandoc\WordPressBlockWriter;

$utf16le = static function (array $codepoints): string {
    $bytes = '';
    foreach ($codepoints as $codepoint) {
        if ($codepoint <= 0xffff) {
            $bytes .= pack('v', $codepoint);
            continue;
        }

        $value = $codepoint - 0x10000;
        $bytes .= pack('v', 0xd800 + ($value >> 10));
        $bytes .= pack('v', 0xdc00 + ($value & 0x03ff));
    }

    return $bytes;
};

return [
    'decodes utf bom and utf16 source bytes for markdown readers' => static function (TestRunner $t) use ($utf16le): void {
        $utf8 = UnicodeText::decodeBytes("\xEF\xBB\xBF# Cafe\xCC\x81\n\nUnicode body");
        $utf16 = UnicodeText::decodeBytes("\xFF\xFE" . $utf16le([
            0x0023,
            0x0020,
            0x9b5a,
            0x000a,
            0x000a,
            0x0048,
            0x0061,
            0x006e,
            0x0064,
            0x006f,
            0x0066,
            0x0066,
        ]));
        $document = (new MarkdownReader())->readBytes("\xFF\xFE" . $utf16le([
            0x0023,
            0x0020,
            0x9b5a,
            0x000a,
            0x000a,
            0x0048,
            0x0061,
            0x006e,
            0x0064,
            0x006f,
            0x0066,
            0x0066,
        ]));

        $t->same('utf-8', $utf8['encoding']);
        $t->same('utf-8', $utf8['bom']);
        $t->same("Cafe\u{0301}", substr($utf8['text'], 2, 6));
        $t->same('utf-16le', $utf16['encoding']);
        $t->same('utf-16le', $utf16['bom']);
        $t->same(0, $utf16['repairs']);
        $t->same('魚', $document->children[0]->attr('text'));
        $t->same('Handoff', $document->children[1]->attr('text'));
        $t->same(['encoding' => 'utf-16le', 'bom' => 'utf-16le', 'repairs' => 0], $document->attr('sourceEncoding'));
    },
    'decodes windows 1252 smart punctuation into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Legacy Import\n\nEditor \x93quoted\x94 source \x97 Cafe\xE9 costs \x8010.";
        $document = (new MarkdownReader())->readBytes($bytes, 'windows-1252');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(['encoding' => 'windows-1252', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same("Editor \u{201C}quoted\u{201D} source \u{2014} Cafe\u{00E9} costs \u{20AC}10.", $document->children[1]->attr('text'));
        $t->contains('<h1 id="legacy-import">Legacy Import</h1>', $blocks);
        $t->contains("<p>Editor \u{201C}quoted\u{201D} source \u{2014} Cafe\u{00E9} costs \u{20AC}10.</p>", $blocks);
    },
    'repairs malformed utf8 with replacement characters' => static function (TestRunner $t): void {
        $decoded = UnicodeText::decodeBytes("Broken \xE2(\xA1 UTF-8");
        $document = (new MarkdownReader())->readBytes("Broken \xE2(\xA1 UTF-8");

        $t->same('utf-8-repaired', $decoded['encoding']);
        $t->same(2, $decoded['repairs']);
        $t->same("Broken \u{FFFD}(\u{FFFD} UTF-8", $decoded['text']);
        $t->same(['encoding' => 'utf-8-repaired', 'bom' => null, 'repairs' => 2], $document->attr('sourceEncoding'));
        $t->same("Broken \u{FFFD}(\u{FFFD} UTF-8", $document->children[0]->attr('text'));
    },
    'normalizes decoded carriage return line endings before markdown parsing' => static function (TestRunner $t) use ($utf16le): void {
        $decoded = UnicodeText::decodeBytes("# Import\r\n\r\nFirst paragraph\rSecond paragraph", 'utf-8');
        $document = (new MarkdownReader())->readBytes("\xFF\xFE" . $utf16le([
            0x0023,
            0x0020,
            0x8A08,
            0x753B,
            0x000d,
            0x000a,
            0x000d,
            0x000a,
            0x0052,
            0x0065,
            0x0076,
            0x0069,
            0x0065,
            0x0077,
            0x000d,
            0x0051,
            0x0075,
            0x0065,
            0x0075,
            0x0065,
        ]));
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same("# Import\n\nFirst paragraph\nSecond paragraph", $decoded['text']);
        $t->same(['normalized' => true, 'crlf' => 2, 'cr' => 1, 'conversions' => 3], $decoded['lineEndings']);
        $t->same(['encoding' => 'utf-16le', 'bom' => 'utf-16le', 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same(['normalized' => true, 'crlf' => 2, 'cr' => 1, 'conversions' => 3], $document->attr('sourceLineEndings'));
        $t->same('計画', $document->children[0]->attr('text'));
        $t->same('Review Queue', $document->children[1]->attr('text'));
        $t->contains('<h1 id="計画">計画</h1>', $blocks);
        $t->contains("<p>Review\nQueue</p>", $blocks);
    },
    'normalizes unicode forms before markdown handoff when requested' => static function (TestRunner $t): void {
        $nfc = UnicodeText::normalize("Cafe\u{0301} \u{212B}", 'NFC');
        $nfd = UnicodeText::normalize("É Å", 'nfd');
        $nfkc = UnicodeText::normalize("\u{2460} \u{FB01} \u{212B} Cafe\u{0301}", 'nfkc');
        $nfkd = UnicodeText::normalize("\u{2460} \u{FB01} É", 'nfkd');
        $decoded = UnicodeText::decodeBytes("# Cafe\xCC\x81 Review\n\nLegacy \xE2\x84\xAB source", 'utf-8', 'nfc');
        $document = (new MarkdownReader())->readBytes("# Cafe\xCC\x81 Review\n\nLegacy \xE2\x84\xAB source", 'utf-8', 'nfc');
        $blocks = (new WordPressBlockWriter())->write($document);
        $normalization = $document->attr('sourceNormalization');

        $t->same("Café Å", $nfc['text']);
        $t->same('nfc', $nfc['form']);
        $t->same(true, $nfc['changed']);
        $t->true(in_array($nfc['implementation'], ['intl', 'fallback'], true), 'Unicode normalization should use an available native implementation');
        $t->same("E\u{0301} A\u{030A}", $nfd['text']);
        $t->same(3, UnicodeText::displayWidth($nfd['text']));
        $t->same("1 fi Å Café", $nfkc['text']);
        $t->same('nfkc', $nfkc['form']);
        $t->same("1 fi E\u{0301}", $nfkd['text']);
        $t->same('nfkd', $nfkd['form']);
        $t->same(6, UnicodeText::displayWidth($nfkd['text']));
        $t->same("# Café Review\n\nLegacy Å source", $decoded['text']);
        $t->same(['form' => 'nfc', 'changed' => true, 'implementation' => $decoded['normalization']['implementation']], $decoded['normalization']);
        $t->same('Café Review', $document->children[0]->attr('text'));
        $t->same('Legacy Å source', $document->children[1]->attr('text'));
        $t->same('nfc', is_array($normalization) ? ($normalization['form'] ?? null) : null);
        $t->same(true, is_array($normalization) ? ($normalization['changed'] ?? null) : null);
        $t->true(is_array($normalization) && in_array($normalization['implementation'] ?? '', ['intl', 'fallback'], true), 'Markdown source normalization metadata should name a native implementation');
        $t->contains('<h1 id="café-review">Café Review</h1>', $blocks);
        $t->contains('<p>Legacy Å source</p>', $blocks);
        $t->throws(\InvalidArgumentException::class, static fn (): array => UnicodeText::normalize('text', 'nfz'));
    },
    'measures display width for cjk combining emoji and zero width marks' => static function (TestRunner $t): void {
        $accent = "A\u{0301}";
        $persian = "\u{0645}\u{06CC}\u{200C}\u{062E}\u{0648}\u{0627}\u{0647}\u{0645}";
        $emoji = "\u{1F469}\u{200D}\u{1F4BB}";

        $t->same(1, UnicodeText::displayWidth($accent));
        $t->same(2, UnicodeText::displayWidth("\u{9B5A}"));
        $t->same(7, UnicodeText::displayWidth("Auf\u{200C}lage"));
        $t->same(7, UnicodeText::displayWidth($persian));
        $t->same(2, UnicodeText::displayWidth($emoji));
        $t->same([$accent, ' ', $emoji], UnicodeText::graphemes($accent . ' ' . $emoji));
        $t->same("  \u{9B5A}", UnicodeText::padDisplay("\u{9B5A}", 4, 'right'));
        $t->same("A\u{0301}   ", UnicodeText::padDisplay($accent, 4));
    },
    'measures emoji presentation sequences as single display clusters' => static function (TestRunner $t): void {
        $checkbox = "\u{2611}\u{FE0F}";
        $keycap = "1\u{FE0F}\u{20E3}";
        $thumb = "\u{1F44D}\u{1F3FD}";
        $technologist = "\u{1F9D1}\u{1F3FE}\u{200D}\u{1F4BB}";
        $flag = "\u{1F1FA}\u{1F1F8}";
        $rocket = "\u{1F680}";
        $extendedCombining = "a\u{1AB0}";

        $t->same(2, UnicodeText::displayWidth($checkbox));
        $t->same(2, UnicodeText::displayWidth($keycap));
        $t->same(2, UnicodeText::displayWidth($thumb));
        $t->same(2, UnicodeText::displayWidth($technologist));
        $t->same(2, UnicodeText::displayWidth($flag));
        $t->same(2, UnicodeText::displayWidth($rocket));
        $t->same(1, UnicodeText::displayWidth($extendedCombining));
        $t->same(
            [$checkbox, $keycap, $thumb, $technologist, $flag, $rocket, $extendedCombining],
            UnicodeText::graphemes($checkbox . $keycap . $thumb . $technologist . $flag . $rocket . $extendedCombining)
        );
        $t->same([$flag, 'X'], UnicodeText::splitAtDisplayWidth($flag . 'X', 1));
        $t->same(
            [$checkbox, $keycap, $thumb, $technologist, $flag, $rocket, $extendedCombining, 'Z'],
            UnicodeText::splitByDisplayBreakpoints(
                $checkbox . $keycap . $thumb . $technologist . $flag . $rocket . $extendedCombining . 'Z',
                [2, 4, 6, 8, 10, 12, 13]
            )
        );
        $t->same($checkbox . '  ', UnicodeText::padDisplay($checkbox, 4));
    },
    'applies east asian ambiguous width policy for display columns' => static function (TestRunner $t): void {
        $ambiguous = "\u{00B7}\u{03A9}\u{2014}\u{2026}\u{2122}";
        $combining = "A\u{0301}\u{00B7}";
        $copyrightEmoji = "\u{00A9}\u{FE0F}";

        $t->same(5, UnicodeText::displayWidth($ambiguous));
        $t->same(5, UnicodeText::displayWidth($ambiguous, 'narrow'));
        $t->same(10, UnicodeText::displayWidth($ambiguous, 'wide'));
        $t->same(2, UnicodeText::displayWidth($combining));
        $t->same(3, UnicodeText::displayWidth($combining, 'wide'));
        $t->same(2, UnicodeText::displayWidth($copyrightEmoji));
        $t->same(2, UnicodeText::displayWidth($copyrightEmoji, 'wide'));
        $t->throws(\InvalidArgumentException::class, static fn (): int => UnicodeText::displayWidth('x', 'full'));
    },
    'splits pads and wraps ambiguous width text with a wide policy' => static function (TestRunner $t): void {
        $text = "A\u{00B7}\u{03A9}B";

        $t->same(["A\u{00B7}\u{03A9}", 'B'], UnicodeText::splitAtDisplayWidth($text, 3));
        $t->same(["A\u{00B7}", "\u{03A9}B"], UnicodeText::splitAtDisplayWidth($text, 3, 'wide'));
        $t->same(["A\u{00B7}", "\u{03A9}", 'B'], UnicodeText::splitByDisplayBreakpoints($text, [3, 5], 'wide'));
        $t->same(" \u{00B7}\u{03A9}", UnicodeText::padDisplay("\u{00B7}\u{03A9}", 5, 'right', 'wide'));
        $t->same([
            'Review',
            "  \u{00B7}\u{03A9}",
            "  \u{2014}",
            '  text',
        ], UnicodeText::wrapByDisplayWidth("Review \u{00B7}\u{03A9} \u{2014} text", 8, '  ', 'wide'));
    },
    'splits display width breakpoints without cutting unicode graphemes' => static function (TestRunner $t): void {
        $accent = "A\u{0301}";
        $emoji = "\u{1F469}\u{200D}\u{1F4BB}";
        $text = "\u{9B5A}" . $accent . $emoji . 'B';

        $t->same(6, UnicodeText::displayWidth($text));
        $t->same(['', $text], UnicodeText::splitAtDisplayWidth($text, 0));
        $t->same(["\u{9B5A}", $accent . $emoji . 'B'], UnicodeText::splitAtDisplayWidth($text, 1));
        $t->same(["\u{9B5A}", $accent . $emoji . 'B'], UnicodeText::splitAtDisplayWidth($text, 2));
        $t->same(["\u{9B5A}" . $accent, $emoji . 'B'], UnicodeText::splitAtDisplayWidth($text, 3));
        $t->same(["\u{9B5A}" . $accent . $emoji, 'B'], UnicodeText::splitAtDisplayWidth($text, 4));
        $t->same(["\u{9B5A}", $accent, $emoji, 'B'], UnicodeText::splitByDisplayBreakpoints($text, [2, 3, 5]));
        $t->same(["\u{9B5A}", '', $accent . $emoji . 'B'], UnicodeText::splitByDisplayBreakpoints($text, [2, 1]));
    },
    'wraps display width lines without cutting unicode graphemes' => static function (TestRunner $t): void {
        $accent = "Cafe\u{0301}";
        $wrapped = UnicodeText::wrapByDisplayWidth(
            "Import \u{9B5A}\u{9B5A} emoji \u{1F44D}\u{1F3FD} flag \u{1F1FA}\u{1F1F8} {$accent} trail",
            12,
            '  '
        );

        $t->same([
            "Import \u{9B5A}\u{9B5A}",
            "  emoji \u{1F44D}\u{1F3FD}",
            "  flag \u{1F1FA}\u{1F1F8}",
            "  {$accent} trail",
        ], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 12, 'Wrapped display line exceeds requested width');
        }
        $t->same(["\u{9B5A}\u{9B5A}", "  \u{9B5A}", "  A\u{0301}B"], UnicodeText::wrapByDisplayWidth("\u{9B5A}\u{9B5A}\u{9B5A}A\u{0301}B", 4, '  '));
        $t->same(['Hard', 'Break', "  \u{9B5A}\u{9B5A}"], UnicodeText::wrapByDisplayWidth("Hard\r\nBreak \u{9B5A}\u{9B5A}", 8, '  '));
        $t->same(['No wrap'], UnicodeText::wrapByDisplayWidth('No wrap', 0, '  '));
    },
    'writes markdown pipe table padding with unicode display widths' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('table', [
                'alignments' => ['default', 'default', 'default'],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', ['header' => true], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'CJK'])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Plain'])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Comb'])]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => "\u{9B5A}\u{9B5A}"])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'ok'])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => "A\u{0301}"])]),
                    ]),
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => "Auf\u{200C}lage"])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'long'])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => "Cafe\u{0301}"])]),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '| CJK     | Plain | Comb |',
            '|-------|-----|----|',
            "| \u{9B5A}\u{9B5A}    | ok    | A\u{0301}    |",
            "| Auf\u{200C}lage | long  | Cafe\u{0301} |",
        ]), (new MarkdownWriter())->write($document));
    },
    'keeps decoded legacy text and unicode tables on the wordpress handoff path' => static function (TestRunner $t): void {
        $bytes = "# Cafe\xE9 Review\n\nEditor \x91source\x92 note.";
        $source = (new MarkdownReader())->readBytes($bytes, 'windows-1252');
        $table = new AstNode('table', [], [
            new AstNode('table_head', [], [
                new AstNode('table_row', ['header' => true], [
                    new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Source'])]),
                    new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Value'])]),
                ]),
            ]),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [], [new AstNode('text', ['text' => "\u{9B5A}\u{9B5A}"])]),
                    new AstNode('table_cell', [], [new AstNode('text', ['text' => "Auf\u{200C}lage"])]),
                ]),
            ]),
        ]);
        $document = new AstNode('document', $source->attrs, [...$source->children, $table]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same("Cafe\u{00E9} Review", $document->children[0]->attr('text'));
        $t->same("Editor \u{2018}source\u{2019} note.", $document->children[1]->attr('text'));
        $t->contains("<p>Editor \u{2018}source\u{2019} note.</p>", $blocks);
        $t->contains("<td>\u{9B5A}\u{9B5A}</td><td>Auf\u{200C}lage</td>", $blocks);
    },
];
