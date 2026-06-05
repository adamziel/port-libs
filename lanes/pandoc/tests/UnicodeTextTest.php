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
    'lets unicode byte order marks override stale source encoding hints' => static function (TestRunner $t) use ($utf16le): void {
        $utf8 = UnicodeText::decodeBytes("\xEF\xBB\xBF# Cafe\xCC\x81\n\nUTF-8 source", 'windows-1252');
        $utf16 = UnicodeText::decodeBytes("\xFF\xFE" . $utf16le([
            0x0023,
            0x0020,
            0x9b5a,
            0x000a,
            0x000a,
            0x0042,
            0x004f,
            0x004d,
            0x0020,
            0x006f,
            0x0076,
            0x0065,
            0x0072,
            0x0072,
            0x0069,
            0x0064,
            0x0065,
        ]), 'windows-1252');
        $document = (new MarkdownReader())->readBytes("\xFE\xFF\x00#\x00 \x8A\x08\x75\x3B\x00\x0A\x00\x0A\x00B\x00E", 'windows-1252');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('utf-8', $utf8['encoding']);
        $t->same('utf-8', $utf8['bom']);
        $t->same("# Cafe\u{0301}\n\nUTF-8 source", $utf8['text']);
        $t->same(0, $utf8['repairs']);
        $t->same('utf-16le', $utf16['encoding']);
        $t->same('utf-16le', $utf16['bom']);
        $t->same("# \u{9B5A}\n\nBOM override", $utf16['text']);
        $t->same(0, $utf16['repairs']);
        $t->same(['encoding' => 'utf-16be', 'bom' => 'utf-16be', 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('計画', $document->children[0]->attr('text'));
        $t->same('BE', $document->children[1]->attr('text'));
        $t->contains('<h1 id="計画">計画</h1>', $blocks);
        $t->contains('<p>BE</p>', $blocks);
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
    'orders combining marks in fallback unicode normalization' => static function (TestRunner $t): void {
        $source = "d\u{0307}\u{0323} Cafe\u{0301} \u{212B}";
        $nfd = UnicodeText::normalize($source, 'nfd', 'fallback');
        $nfc = UnicodeText::normalize($source, 'nfc', 'fallback');
        $nfkd = UnicodeText::normalize("\u{2460}\u{00A0}\u{FB01} \u{00E7}ade", 'nfkd', 'fallback');

        $t->same("d\u{0323}\u{0307} Cafe\u{0301} A\u{030A}", $nfd['text']);
        $t->same('nfd', $nfd['form']);
        $t->same(true, $nfd['changed']);
        $t->same('fallback', $nfd['implementation']);
        $t->same("\u{1E0D}\u{0307} Café Å", $nfc['text']);
        $t->same('nfc', $nfc['form']);
        $t->same(true, $nfc['changed']);
        $t->same('fallback', $nfc['implementation']);
        $t->same("1 fi c\u{0327}ade", $nfkd['text']);
        $t->same('nfkd', $nfkd['form']);
        $t->same('fallback', $nfkd['implementation']);
        $t->same(8, UnicodeText::displayWidth($nfc['text']));
        $t->same(["\u{1E0D}\u{0307}", ' ', 'C', 'a', 'f', 'é', ' ', 'Å'], UnicodeText::graphemes($nfc['text']));
        $t->throws(\InvalidArgumentException::class, static fn (): array => UnicodeText::normalize('text', 'nfc', 'remote'));
    },
    'normalizes latin extended reviewer names with fallback unicode data' => static function (TestRunner $t): void {
        $polishDecomposed = "Zaz\u{0307}o\u{0301}łc\u{0301} ge\u{0328}s\u{0301}la\u{0328} jaz\u{0301}n\u{0301}";
        $polishComposed = "Zażółć gęślą jaźń";
        $centralDecomposed = "C\u{030C}esky\u{0301} S\u{030C}te\u{030C}pa\u{0301}n, ku\u{030A}n\u{030C}, o\u{030B}u\u{030B}, s\u{0326}t\u{0326}";
        $centralComposed = "Český Štěpán, kůň, őű, șț";
        $fallbackNfc = UnicodeText::normalize($polishDecomposed . ' / ' . $centralDecomposed, 'nfc', 'fallback');
        $fallbackNfd = UnicodeText::normalize($polishComposed . ' / ' . $centralComposed, 'nfd', 'fallback');
        $decoded = UnicodeText::decodeBytes("# {$polishDecomposed}\n\n{$centralDecomposed}", 'utf-8', 'nfc');
        $document = (new MarkdownReader())->readBytes("# {$polishDecomposed}\n\n{$centralDecomposed}", 'utf-8', 'nfc');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same($polishComposed . ' / ' . $centralComposed, $fallbackNfc['text']);
        $t->same('nfc', $fallbackNfc['form']);
        $t->same('fallback', $fallbackNfc['implementation']);
        $t->same(true, $fallbackNfc['changed']);
        $t->same($polishDecomposed . ' / ' . $centralDecomposed, $fallbackNfd['text']);
        $t->same('nfd', $fallbackNfd['form']);
        $t->same('fallback', $fallbackNfd['implementation']);
        $t->same(true, $fallbackNfd['changed']);
        $t->same(UnicodeText::displayWidth($fallbackNfc['text']), UnicodeText::displayWidth($fallbackNfd['text']));
        $t->same("# {$polishComposed}\n\n{$centralComposed}", $decoded['text']);
        $t->same($polishComposed, $document->children[0]->attr('text'));
        $t->same($centralComposed, $document->children[1]->attr('text'));
        $t->contains("<p>{$centralComposed}</p>", $blocks);
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
    'measures decomposed hangul jamo syllables as display clusters' => static function (TestRunner $t): void {
        $han = "\u{1112}\u{1161}\u{11AB}";
        $geul = "\u{1100}\u{1173}\u{11AF}";
        $extended = "\u{A960}\u{D7B0}\u{D7CB}";
        $text = $han . $geul . 'X';

        $t->same(2, UnicodeText::displayWidth($han));
        $t->same(4, UnicodeText::displayWidth($han . $geul));
        $t->same(2, UnicodeText::displayWidth($extended));
        $t->same(0, UnicodeText::displayWidth("\u{1161}\u{11AF}"));
        $t->same([$han, $geul, 'X'], UnicodeText::graphemes($text));
        $t->same([$han, $geul . 'X'], UnicodeText::splitAtDisplayWidth($text, 2));
        $t->same([$han, $geul, 'X'], UnicodeText::splitByDisplayBreakpoints($text, [2, 4]));
        $t->same($han . '  ', UnicodeText::padDisplay($han, 4));
        $t->same(['Review', '  ' . $han . $geul, '  tail'], UnicodeText::wrapByDisplayWidth('Review ' . $han . $geul . ' tail', 10, '  '));
    },
    'measures indic spacing vowel signs as display clusters' => static function (TestRunner $t): void {
        $devanagariKi = "\u{0915}\u{093F}";
        $devanagariKau = "\u{0915}\u{094C}";
        $tamilKai = "\u{0B95}\u{0BC8}";
        $bengaliBangla = "\u{09AC}\u{09BE}\u{0982}\u{09B2}\u{09BE}";
        $bengaliBan = "\u{09AC}\u{09BE}\u{0982}";
        $bengaliLa = "\u{09B2}\u{09BE}";
        $clusterRun = $devanagariKi . $tamilKai . $bengaliBangla;
        $text = $clusterRun . 'X';

        $t->same(1, UnicodeText::displayWidth($devanagariKi));
        $t->same(1, UnicodeText::displayWidth($devanagariKau));
        $t->same(1, UnicodeText::displayWidth($tamilKai));
        $t->same(2, UnicodeText::displayWidth($bengaliBangla));
        $t->same(5, UnicodeText::displayWidth($text));
        $t->same([$devanagariKi, $tamilKai, $bengaliBan, $bengaliLa, 'X'], UnicodeText::graphemes($text));
        $t->same([$devanagariKi, $tamilKai . $bengaliBangla . 'X'], UnicodeText::splitAtDisplayWidth($text, 1));
        $t->same([$devanagariKi, $tamilKai, $bengaliBangla, 'X'], UnicodeText::splitByDisplayBreakpoints($text, [1, 2, 4]));
        $t->same($tamilKai . '   ', UnicodeText::padDisplay($tamilKai, 4));
        $t->same(["Indic {$clusterRun}", '  tail'], UnicodeText::wrapByDisplayWidth("Indic {$clusterRun} tail", 10, '  '));
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
    'measures emoji tag sequences as a single display cluster' => static function (TestRunner $t): void {
        $scotland = "\u{1F3F4}\u{E0067}\u{E0062}\u{E0073}\u{E0063}\u{E0074}\u{E007F}";
        $standaloneTags = "\u{E0067}\u{E0062}\u{E007F}";
        $wrapped = UnicodeText::wrapByDisplayWidth("Flag {$scotland} tail", 8, '  ');

        $t->same(2, UnicodeText::displayWidth($scotland));
        $t->same(0, UnicodeText::displayWidth($standaloneTags));
        $t->same([$scotland, 'X'], UnicodeText::graphemes($scotland . 'X'));
        $t->same([$standaloneTags], UnicodeText::graphemes($standaloneTags));
        $t->same([$scotland, 'X'], UnicodeText::splitAtDisplayWidth($scotland . 'X', 1));
        $t->same([$scotland, 'X'], UnicodeText::splitByDisplayBreakpoints($scotland . 'X', [2]));
        $t->same($scotland . '  ', UnicodeText::padDisplay($scotland, 4));
        $t->same(['Flag ' . $scotland, '  tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 8, 'Emoji tag wrapped line exceeds requested width');
        }
    },
    'measures emoji zwj variation sequences as a single display cluster' => static function (TestRunner $t): void {
        $heartOnFire = "\u{2764}\u{FE0F}\u{200D}\u{1F525}";
        $rainbowFlag = "\u{1F3F3}\u{FE0F}\u{200D}\u{1F308}";
        $eyeBubble = "\u{1F441}\u{FE0F}\u{200D}\u{1F5E8}\u{FE0F}";
        $text = $heartOnFire . $rainbowFlag . 'X';
        $wrapped = UnicodeText::wrapByDisplayWidth("Emoji {$heartOnFire} {$rainbowFlag} tail", 9, '  ');

        $t->same(2, UnicodeText::displayWidth($heartOnFire));
        $t->same(2, UnicodeText::displayWidth($rainbowFlag));
        $t->same(2, UnicodeText::displayWidth($eyeBubble));
        $t->same([$heartOnFire, $rainbowFlag, 'X'], UnicodeText::graphemes($text));
        $t->same([$heartOnFire, $rainbowFlag . 'X'], UnicodeText::splitAtDisplayWidth($text, 1));
        $t->same([$heartOnFire, $rainbowFlag, 'X'], UnicodeText::splitByDisplayBreakpoints($text, [2, 4]));
        $t->same($heartOnFire . '  ', UnicodeText::padDisplay($heartOnFire, 4));
        $t->same(['Emoji ' . $heartOnFire, '  ' . $rainbowFlag . ' tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 9, 'Emoji ZWJ variation wrapped line exceeds requested width');
        }
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
    'wraps unicode soft break opportunities without leaking controls' => static function (TestRunner $t): void {
        $wrapped = UnicodeText::wrapByDisplayWidth("Zero\u{200B}width\u{200B}breaks soft\u{00AD}hyphen \u{9B5A}\u{200B}\u{9B5A} tail", 10, '  ');

        $t->same([
            'Zerowidth',
            '  breaks',
            '  soft-',
            '  hyphen',
            "  \u{9B5A}\u{9B5A}",
            '  tail',
        ], $wrapped);
        $t->same(['reviewpacket'], UnicodeText::wrapByDisplayWidth("review\u{200B}packet", 20));
        $t->same(['softhyphen'], UnicodeText::wrapByDisplayWidth("soft\u{00AD}hyphen", 20));
        $t->same('', implode('', array_intersect(UnicodeText::characters(implode('', $wrapped)), ["\u{200B}", "\u{00AD}"])));
        $t->contains('soft-', implode("\n", $wrapped));
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 10, 'Soft-break wrapped line exceeds requested width');
        }
    },
    'wraps unicode separator classes without treating no break spaces as breakpoints' => static function (TestRunner $t): void {
        $ideographicSpace = "\u{3000}";
        $emSpace = "\u{2003}";
        $lineSeparator = "\u{2028}";
        $paragraphSeparator = "\u{2029}";
        $noBreak = "keep\u{00A0}together";
        $narrowNoBreak = "page\u{202F}12";
        $wrapped = UnicodeText::wrapByDisplayWidth(
            "CJK{$ideographicSpace}review{$emSpace}queue{$lineSeparator}Hard reset{$paragraphSeparator}\u{9B5A}{$ideographicSpace}\u{9B5A} tail",
            10,
            '  '
        );

        $t->same([
            'CJK',
            '  review',
            '  queue',
            'Hard reset',
            "\u{9B5A}\u{3000}\u{9B5A}",
            '  tail',
        ], $wrapped);
        $t->same(["A{$ideographicSpace}B"], UnicodeText::wrapByDisplayWidth("A{$ideographicSpace}B", 10));
        $t->same(['keep', "  {$noBreak}"], UnicodeText::wrapByDisplayWidth("keep {$noBreak}", 15, '  '));
        $t->same([$narrowNoBreak, '  tail'], UnicodeText::wrapByDisplayWidth("{$narrowNoBreak} tail", 8, '  '));
        $t->same('', implode('', array_intersect(UnicodeText::characters(implode('', $wrapped)), [$lineSeparator, $paragraphSeparator])));
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 10, 'Unicode separator wrapped line exceeds requested width');
        }
    },
    'keeps default ignorable controls zero width for display accounting' => static function (TestRunner $t): void {
        $softHyphen = "soft\u{00AD}hyphen";
        $leadingBom = "\u{FEFF}Title";
        $embeddedBom = "A\u{FEFF}B\u{00AD}C";

        $t->same(10, UnicodeText::displayWidth($softHyphen));
        $t->same(5, UnicodeText::displayWidth($leadingBom));
        $t->same(3, UnicodeText::displayWidth($embeddedBom));
        $t->same(["soft\u{00AD}", 'hyphen'], UnicodeText::splitAtDisplayWidth($softHyphen, 4));
        $t->same([$leadingBom, 'X'], UnicodeText::splitAtDisplayWidth($leadingBom . 'X', 5));
        $t->same($leadingBom . ' ', UnicodeText::padDisplay($leadingBom, 6));
        $t->same(["Pre {$leadingBom}"], UnicodeText::wrapByDisplayWidth("Pre {$leadingBom}", 9, '  '));
        $t->same(3, UnicodeText::displayWidth("A\u{00AD}\u{00B7}", 'wide'));
    },
    'keeps prepended format controls zero width for multilingual display accounting' => static function (TestRunner $t): void {
        $arabicNumber = "\u{0600}";
        $arabicEnd = "\u{06DD}";
        $syriacAbbrev = "\u{070F}";
        $arabicPound = "\u{0890}";
        $kaithiNumber = "\u{110BD}";
        $kaithiNumberJoiner = "\u{110CD}";
        $label = "{$arabicNumber}رقم {$syriacAbbrev}ܣܘܪܝܝܐ {$kaithiNumber}kaithi";

        $t->same(17, UnicodeText::displayWidth($label));
        $t->same(2, UnicodeText::displayWidth("A{$arabicNumber}{$arabicEnd}B"));
        $t->same(2, UnicodeText::displayWidth("A{$arabicPound}{$kaithiNumberJoiner}B"));
        $t->same(["{$arabicNumber}ر", 'ق', 'م'], UnicodeText::splitByDisplayBreakpoints("{$arabicNumber}رقم", [1, 2]));
        $t->same(["A{$arabicPound}", 'B'], UnicodeText::splitAtDisplayWidth("A{$arabicPound}B", 1));
        $t->same(["Audit {$arabicNumber}رقم", '  tail'], UnicodeText::wrapByDisplayWidth("Audit {$arabicNumber}رقم tail", 9, '  '));
        $t->same(" {$arabicNumber}رقم", UnicodeText::padDisplay("{$arabicNumber}رقم", 4, 'right'));
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
