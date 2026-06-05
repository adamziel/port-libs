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

$utf32le = static function (array $codepoints): string {
    $bytes = '';
    foreach ($codepoints as $codepoint) {
        $bytes .= pack('V', $codepoint);
    }

    return $bytes;
};

$utf32be = static function (array $codepoints): string {
    $bytes = '';
    foreach ($codepoints as $codepoint) {
        $bytes .= pack('N', $codepoint);
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
    'decodes iso 8859 15 latin9 euro text into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Latin9 Import\n\nPrice \xA410; \xBCuvre, c\xBDur, \xBE, \xA6umava, and \xB8.";
        $decoded = UnicodeText::decodeBytes($bytes, 'latin-9');
        $document = (new MarkdownReader())->readBytes($bytes, 'iso-8859-15');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('iso-8859-15', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Latin9 Import\n\nPrice \u{20AC}10; \u{0152}uvre, c\u{0153}ur, \u{0178}, \u{0160}umava, and \u{017E}.", $decoded['text']);
        $t->same(['encoding' => 'iso-8859-15', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Latin9 Import', $document->children[0]->attr('text'));
        $t->same("Price \u{20AC}10; \u{0152}uvre, c\u{0153}ur, \u{0178}, \u{0160}umava, and \u{017E}.", $document->children[1]->attr('text'));
        $t->contains('<h1 id="latin9-import">Latin9 Import</h1>', $blocks);
        $t->contains("<p>Price \u{20AC}10; \u{0152}uvre, c\u{0153}ur, \u{0178}, \u{0160}umava, and \u{017E}.</p>", $blocks);
    },
    'decodes macroman legacy punctuation into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Mac Import\n\nClassic \xD2quoted\xD3 source \xD1 price \xDB10; caf\x8E and \xDEle.";
        $decoded = UnicodeText::decodeBytes($bytes, 'mac-roman');
        $document = (new MarkdownReader())->readBytes($bytes, 'macintosh');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('macintosh', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Mac Import\n\nClassic \u{201C}quoted\u{201D} source \u{2014} price \u{20AC}10; caf\u{00E9} and \u{FB01}le.", $decoded['text']);
        $t->same(['encoding' => 'macintosh', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Mac Import', $document->children[0]->attr('text'));
        $t->same("Classic \u{201C}quoted\u{201D} source \u{2014} price \u{20AC}10; caf\u{00E9} and \u{FB01}le.", $document->children[1]->attr('text'));
        $t->contains('<h1 id="mac-import">Mac Import</h1>', $blocks);
        $t->contains("<p>Classic \u{201C}quoted\u{201D} source \u{2014} price \u{20AC}10; caf\u{00E9} and \u{FB01}le.</p>", $blocks);
    },
    'decodes central european single byte labels into wordpress blocks' => static function (TestRunner $t): void {
        $windowsBytes = "# Central Import\n\nZa\xBF\xF3\xB3\xE6 g\xEA\x9Cl\xB9 ja\x9F\xF1; \xC8esk\xFD \x8At\xECp\xE1n; k\xF9\xF2; \xF5\xFB; \x93quoted\x94 \x97 \x8010.";
        $latin2Bytes = "# Latin2 Import\n\nZa\xBF\xF3\xB3\xE6 g\xEA\xB6l\xB1 ja\xBC\xF1; \xC8esk\xFD \xA9t\xECp\xE1n; k\xF9\xF2; \xF5\xFB.";
        $windowsDecoded = UnicodeText::decodeBytes($windowsBytes, 'cp1250');
        $latin2Decoded = UnicodeText::decodeBytes($latin2Bytes, 'latin-2');
        $windowsDocument = (new MarkdownReader())->readBytes($windowsBytes, 'microsoft-cp1250');
        $latin2Document = (new MarkdownReader())->readBytes($latin2Bytes, 'iso-8859-2:1987');
        $windowsBlocks = (new WordPressBlockWriter())->write($windowsDocument);
        $latin2Blocks = (new WordPressBlockWriter())->write($latin2Document);

        $t->same('windows-1250', $windowsDecoded['encoding']);
        $t->same(0, $windowsDecoded['repairs']);
        $t->same("# Central Import\n\nZażółć gęślą jaźń; Český Štěpán; kůň; őű; “quoted” — €10.", $windowsDecoded['text']);
        $t->same('iso-8859-2', $latin2Decoded['encoding']);
        $t->same(0, $latin2Decoded['repairs']);
        $t->same("# Latin2 Import\n\nZażółć gęślą jaźń; Český Štěpán; kůň; őű.", $latin2Decoded['text']);
        $t->same(['encoding' => 'windows-1250', 'bom' => null, 'repairs' => 0], $windowsDocument->attr('sourceEncoding'));
        $t->same(['encoding' => 'iso-8859-2', 'bom' => null, 'repairs' => 0], $latin2Document->attr('sourceEncoding'));
        $t->same('Central Import', $windowsDocument->children[0]->attr('text'));
        $t->same('Latin2 Import', $latin2Document->children[0]->attr('text'));
        $t->same("Zażółć gęślą jaźń; Český Štěpán; kůň; őű; “quoted” — €10.", $windowsDocument->children[1]->attr('text'));
        $t->same('Zażółć gęślą jaźń; Český Štěpán; kůň; őű.', $latin2Document->children[1]->attr('text'));
        $t->same(57, UnicodeText::displayWidth((string) $windowsDocument->children[1]->attr('text')));
        $t->same(41, UnicodeText::displayWidth((string) $latin2Document->children[1]->attr('text')));
        $t->contains("<p>Zażółć gęślą jaźń; Český Štěpán; kůň; őű; “quoted” — €10.</p>", $windowsBlocks);
        $t->contains('<p>Zażółć gęślą jaźń; Český Štěpán; kůň; őű.</p>', $latin2Blocks);
    },
    'decodes shift jis japanese source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = (string) hex2bin('23208c7689e60a0a967b95b682c694bc8a70b6c0b6c581418adb874094678160fbfc8de88142');
        $decoded = UnicodeText::decodeBytes($bytes, 'windows-31j');
        $document = (new MarkdownReader())->readBytes($bytes, 'shift-jis');
        $blocks = (new WordPressBlockWriter())->write($document);
        $malformed = UnicodeText::decodeBytes("\x82\"A", 'sjis');

        $t->same('shift_jis', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# 計画\n\n本文と半角ｶﾀｶﾅ、丸①波～髙崎。", $decoded['text']);
        $t->same(['encoding' => 'shift_jis', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('計画', $document->children[0]->attr('text'));
        $t->same("本文と半角ｶﾀｶﾅ、丸①波～髙崎。", $document->children[1]->attr('text'));
        $t->same(29, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(30, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="計画">計画</h1>', $blocks);
        $t->contains("<p>本文と半角ｶﾀｶﾅ、丸①波～髙崎。</p>", $blocks);
        $t->same('shift_jis', $malformed['encoding']);
        $t->same("\u{FFFD}\"A", $malformed['text']);
        $t->same(1, $malformed['repairs']);
    },
    'decodes euc jp japanese source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = (string) hex2bin('2320b7d7b2e80a0acbdccab8a4c8c8beb3d18eb68ec08eb68ec5a1a2b4ddada1c7c8a1c1baeaa1a3');
        $decoded = UnicodeText::decodeBytes($bytes, 'x-euc-jp');
        $document = (new MarkdownReader())->readBytes($bytes, 'cseucpkdfmtjapanese');
        $blocks = (new WordPressBlockWriter())->write($document);
        $malformedHalfwidth = UnicodeText::decodeBytes("\x8E A", 'euc-jp');
        $malformedJis0208 = UnicodeText::decodeBytes("\xA4\"A", 'eucjp');

        $t->same('euc-jp', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# 計画\n\n本文と半角ｶﾀｶﾅ、丸①波～崎。", $decoded['text']);
        $t->same(['encoding' => 'euc-jp', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('計画', $document->children[0]->attr('text'));
        $t->same("本文と半角ｶﾀｶﾅ、丸①波～崎。", $document->children[1]->attr('text'));
        $t->same(27, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(28, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="計画">計画</h1>', $blocks);
        $t->contains("<p>本文と半角ｶﾀｶﾅ、丸①波～崎。</p>", $blocks);
        $t->same('euc-jp', $malformedHalfwidth['encoding']);
        $t->same("\u{FFFD} A", $malformedHalfwidth['text']);
        $t->same(1, $malformedHalfwidth['repairs']);
        $t->same("\u{FFFD}\"A", $malformedJis0208['text']);
        $t->same(1, $malformedJis0208['repairs']);
    },
    'decodes iso 2022 jp escape states into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \x1B\$B\x37\x57\x32\x68\x1B(B\n\n"
            . "\x1B\$B\x4B\x5C\x4A\x38\x24\x48\x48\x3E\x33\x51\x1B(I\x36\x40\x36\x45"
            . "\x1B\$B\x21\x22\x34\x5D\x2D\x21\x47\x48\x21\x41\x3A\x6A\x21\x23"
            . "\x1B(J \x5C\x7E\x1B(B ASCII";
        $decoded = UnicodeText::decodeBytes($bytes, 'csiso2022jp');
        $document = (new MarkdownReader())->readBytes($bytes, 'iso-2022-jp');
        $blocks = (new WordPressBlockWriter())->write($document);
        $malformedTrail = UnicodeText::decodeBytes("\x1B\$B\x37 A", 'iso2022jp');
        $malformedEscape = UnicodeText::decodeBytes("A\x1B\$XB", 'iso-2022-jp');

        $t->same('iso-2022-jp', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# 計画\n\n本文と半角ｶﾀｶﾅ、丸①波～崎。 ¥‾ ASCII", $decoded['text']);
        $t->same(['encoding' => 'iso-2022-jp', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('計画', $document->children[0]->attr('text'));
        $t->same("本文と半角ｶﾀｶﾅ、丸①波～崎。 ¥‾ ASCII", $document->children[1]->attr('text'));
        $t->same(36, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(38, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="計画">計画</h1>', $blocks);
        $t->contains("<p>本文と半角ｶﾀｶﾅ、丸①波～崎。 ¥‾ ASCII</p>", $blocks);
        $t->same("\u{FFFD} A", $malformedTrail['text']);
        $t->same(1, $malformedTrail['repairs']);
        $t->same("A\u{FFFD}B", $malformedEscape['text']);
        $t->same(1, $malformedEscape['repairs']);
    },
    'decodes bounded big5 traditional chinese source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = (string) hex2bin('2320a4a4a4e50a0aa4a4a4e5204269673520b4fab8d5a141adbbb4e4a143');
        $decoded = UnicodeText::decodeBytes($bytes, 'big5-hkscs');
        $document = (new MarkdownReader())->readBytes($bytes, 'cn-big5');
        $blocks = (new WordPressBlockWriter())->write($document);
        $malformedLead = UnicodeText::decodeBytes("\xA4 A", 'big5');
        $unmappedPair = UnicodeText::decodeBytes("\x81\x40A", 'x-x-big5');

        $t->same('big5', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# 中文\n\n中文 Big5 測試，香港。", $decoded['text']);
        $t->same(['encoding' => 'big5', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('中文', $document->children[0]->attr('text'));
        $t->same("中文 Big5 測試，香港。", $document->children[1]->attr('text'));
        $t->same(22, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="中文">中文</h1>', $blocks);
        $t->contains('<p>中文 Big5 測試，香港。</p>', $blocks);
        $t->same("\u{FFFD} A", $malformedLead['text']);
        $t->same(1, $malformedLead['repairs']);
        $t->same("\u{FFFD}A", $unmappedPair['text']);
        $t->same(1, $unmappedPair['repairs']);
    },
    'decodes bounded gbk simplified chinese source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = (string) hex2bin('2320bcf2cce50a0ad6d0cec42047424b20b2e2cad4a3acb1b1bea9a1a3');
        $decoded = UnicodeText::decodeBytes($bytes, 'gb18030');
        $document = (new MarkdownReader())->readBytes($bytes, 'gb2312');
        $blocks = (new WordPressBlockWriter())->write($document);
        $malformedLead = UnicodeText::decodeBytes("\xD6\"A", 'gbk');
        $unmappedPair = UnicodeText::decodeBytes("\x81\x40A", 'cp936');

        $t->same('gbk', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# 简体\n\n中文 GBK 测试，北京。", $decoded['text']);
        $t->same(['encoding' => 'gbk', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('简体', $document->children[0]->attr('text'));
        $t->same('中文 GBK 测试，北京。', $document->children[1]->attr('text'));
        $t->same(21, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="简体">简体</h1>', $blocks);
        $t->contains('<p>中文 GBK 测试，北京。</p>', $blocks);
        $t->same("\u{FFFD}\"A", $malformedLead['text']);
        $t->same(1, $malformedLead['repairs']);
        $t->same("\u{FFFD}A", $unmappedPair['text']);
        $t->same(1, $unmappedPair['repairs']);
    },
    'decodes bounded hz gb 2312 escape states into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# ~{<rLe~}\n\n~{VPND~} HZ ~{2bJT#,11>)!#~}\nEscaped ~~ tilde and line~\njoin.";
        $decoded = UnicodeText::decodeBytes($bytes, 'hz-gb-2312');
        $document = (new MarkdownReader())->readBytes($bytes, 'hzgb2312');
        $blocks = (new WordPressBlockWriter())->write($document);
        $malformedPair = UnicodeText::decodeBytes('~{<~}', 'hz');
        $invalidEscape = UnicodeText::decodeBytes('A~xB', 'hz-gb-2312');
        $unmappedPair = UnicodeText::decodeBytes('~{!!~}', 'hz-gb-2312');

        $t->same('hz-gb-2312', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# 简体\n\n中文 HZ 测试，北京。\nEscaped ~ tilde and linejoin.", $decoded['text']);
        $t->same(['encoding' => 'hz-gb-2312', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('简体', $document->children[0]->attr('text'));
        $t->same('中文 HZ 测试，北京。 Escaped ~ tilde and linejoin.', $document->children[1]->attr('text'));
        $t->same(50, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="简体">简体</h1>', $blocks);
        $t->contains("<p>中文 HZ 测试，北京。\nEscaped ~ tilde and linejoin.</p>", $blocks);
        $t->same("\u{FFFD}", $malformedPair['text']);
        $t->same(1, $malformedPair['repairs']);
        $t->same("A\u{FFFD}B", $invalidEscape['text']);
        $t->same(1, $invalidEscape['repairs']);
        $t->same("\u{FFFD}", $unmappedPair['text']);
        $t->same(1, $unmappedPair['repairs']);
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
    'decodes utf32 byte order marks before utf16 fallback' => static function (TestRunner $t) use ($utf32le, $utf32be): void {
        $utf32leSource = "\xFF\xFE\x00\x00" . $utf32le([
            0x0023,
            0x0020,
            0x1f4da,
            0x000a,
            0x000a,
            0x0052,
            0x0065,
            0x0076,
            0x0069,
            0x0065,
            0x0077,
        ]);
        $utf32beSource = "\x00\x00\xFE\xFF" . $utf32be([
            0x0023,
            0x0020,
            0x8a08,
            0x753b,
            0x000a,
            0x000a,
            0x0042,
            0x0045,
        ]);
        $decodedLe = UnicodeText::decodeBytes($utf32leSource, 'windows-1252');
        $decodedBe = UnicodeText::decodeBytes($utf32beSource, 'utf-16le');
        $explicit = UnicodeText::decodeBytes($utf32be([0x0050, 0x006c, 0x0061, 0x006e]), 'utf-32be');
        $invalid = UnicodeText::decodeBytes($utf32be([0x0041, 0x110000]) . "\x00", 'utf-32be');
        $document = (new MarkdownReader())->readBytes($utf32beSource, 'windows-1252');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('utf-32le', $decodedLe['encoding']);
        $t->same('utf-32le', $decodedLe['bom']);
        $t->same("# \u{1F4DA}\n\nReview", $decodedLe['text']);
        $t->same(0, $decodedLe['repairs']);
        $t->same('utf-32be', $decodedBe['encoding']);
        $t->same('utf-32be', $decodedBe['bom']);
        $t->same("# 計画\n\nBE", $decodedBe['text']);
        $t->same(['encoding' => 'utf-32be', 'bom' => 'utf-32be', 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('計画', $document->children[0]->attr('text'));
        $t->same('BE', $document->children[1]->attr('text'));
        $t->contains('<h1 id="計画">計画</h1>', $blocks);
        $t->same('Plan', $explicit['text']);
        $t->same('utf-32be', $explicit['encoding']);
        $t->same("A\u{FFFD}\u{FFFD}", $invalid['text']);
        $t->same(2, $invalid['repairs']);
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
    'keeps thai and lao sara am grapheme clusters intact for display slicing' => static function (TestRunner $t): void {
        $thai = "\u{0E01}\u{0E33}";
        $lao = "\u{0EA5}\u{0EB3}";
        $text = $thai . $lao . 'X';
        $wrapped = UnicodeText::wrapByDisplayWidth("Thai {$thai} {$lao} tail", 9, '  ');

        $t->same(2, UnicodeText::displayWidth($thai));
        $t->same(2, UnicodeText::displayWidth($lao));
        $t->same([$thai, $lao, 'X'], UnicodeText::graphemes($text));
        $t->same([$thai, $lao . 'X'], UnicodeText::splitAtDisplayWidth($text, 1));
        $t->same([$thai, $lao, 'X'], UnicodeText::splitByDisplayBreakpoints($text, [2, 4]));
        $t->same($thai . '  ', UnicodeText::padDisplay($thai, 4));
        $t->same(['Thai ' . $thai, '  ' . $lao . ' tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 9, 'Thai/Lao AM wrapped line exceeds requested width');
        }
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
    'measures text variation selectors as pandoc emoji variation modifiers' => static function (TestRunner $t): void {
        $textSmile = "\u{263A}\u{FE0E}";
        $emojiSmile = "\u{263A}\u{FE0F}";
        $copyrightText = "\u{00A9}\u{FE0E}";
        $heartText = "\u{2764}\u{FE0E}";
        $plainTextSelector = "A\u{FE0E}";
        $standaloneTextSelector = "\u{FE0E}";
        $sample = $textSmile . $copyrightText . $plainTextSelector . 'X';
        $wrapped = UnicodeText::wrapByDisplayWidth("Mood {$textSmile} {$copyrightText} tail", 8, '  ');

        $t->same(2, UnicodeText::displayWidth($textSmile));
        $t->same(2, UnicodeText::displayWidth($emojiSmile));
        $t->same(2, UnicodeText::displayWidth($copyrightText));
        $t->same(2, UnicodeText::displayWidth($heartText));
        $t->same(1, UnicodeText::displayWidth($plainTextSelector));
        $t->same(0, UnicodeText::displayWidth($standaloneTextSelector));
        $t->same([$textSmile, $copyrightText, $plainTextSelector, 'X'], UnicodeText::graphemes($sample));
        $t->same([$textSmile, $copyrightText . $plainTextSelector . 'X'], UnicodeText::splitAtDisplayWidth($sample, 1));
        $t->same([$textSmile, $copyrightText, $plainTextSelector . 'X'], UnicodeText::splitByDisplayBreakpoints($sample, [2, 4]));
        $t->same($textSmile . '  ', UnicodeText::padDisplay($textSmile, 4));
        $t->same(["Mood {$textSmile}", "  {$copyrightText}", '  tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 8, 'Text variation wrapped line exceeds requested width');
        }
    },
    'keeps unattached emoji skin tone modifiers visible for display accounting' => static function (TestRunner $t): void {
        $thumb = "\u{1F44D}\u{1F3FD}";
        $standalone = "\u{1F3FD}";
        $invalidAfterLetter = "A{$standalone}";
        $text = $thumb . $standalone . 'X';
        $wrapped = UnicodeText::wrapByDisplayWidth("Emoji {$thumb} {$standalone} tail", 10, '  ');

        $t->same(2, UnicodeText::displayWidth($thumb));
        $t->same(2, UnicodeText::displayWidth($standalone));
        $t->same(3, UnicodeText::displayWidth($invalidAfterLetter));
        $t->same(5, UnicodeText::displayWidth($text));
        $t->same([$thumb, $standalone, 'X'], UnicodeText::graphemes($text));
        $t->same([$standalone, 'X'], UnicodeText::splitAtDisplayWidth($standalone . 'X', 1));
        $t->same([$thumb, $standalone, 'X'], UnicodeText::splitByDisplayBreakpoints($text, [2, 4]));
        $t->same($standalone . '  ', UnicodeText::padDisplay($standalone, 4));
        $t->same(['Emoji ' . $thumb, '  ' . $standalone . ' tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 10, 'Emoji skin-tone wrapped line exceeds requested width');
        }
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
    'measures supplementary east asian wide symbols for display columns' => static function (TestRunner $t): void {
        $ideographicMarks = "\u{16FE0}\u{16FE1}\u{16FE2}\u{16FE3}";
        $kanaLetters = "\u{1B000}\u{1B11F}\u{1B132}\u{1B150}\u{1B155}\u{1B164}";
        $nushu = "\u{1B170}";
        $enclosedIdeographic = "\u{1F004}\u{1F0CF}\u{1F18E}\u{1F191}\u{1F200}\u{1F210}\u{1F240}\u{1F250}\u{1F260}";
        $sample = "\u{16FE0}\u{1B000}\u{1F200}X";
        $khitanFillerCluster = "\u{16FE0}\u{16FE4}";
        $vietnameseReadingMark = "\u{16FF0}";
        $wrapped = UnicodeText::wrapByDisplayWidth("Wide {$sample} tail", 10, '  ');

        $t->same(8, UnicodeText::displayWidth($ideographicMarks));
        $t->same(12, UnicodeText::displayWidth($kanaLetters));
        $t->same(2, UnicodeText::displayWidth($nushu));
        $t->same(18, UnicodeText::displayWidth($enclosedIdeographic));
        $t->same(2, UnicodeText::displayWidth($khitanFillerCluster));
        $t->same(0, UnicodeText::displayWidth($vietnameseReadingMark));
        $t->same(["\u{16FE0}\u{1B000}", "\u{1F200}X"], UnicodeText::splitAtDisplayWidth($sample, 3));
        $t->same(["\u{16FE0}", "\u{1B000}", "\u{1F200}", 'X'], UnicodeText::splitByDisplayBreakpoints($sample, [2, 4, 6]));
        $t->same("\u{1F200}  ", UnicodeText::padDisplay("\u{1F200}", 4));
        $t->same(['Wide', "  {$sample}", '  tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 10, 'Supplementary East Asian wide wrapped line exceeds requested width');
        }
    },
    'measures kana extended b letters as east asian wide' => static function (TestRunner $t): void {
        $kana = "\u{1AFF0}\u{1AFF5}\u{1AFFD}";
        $sample = $kana . 'X';
        $wrapped = UnicodeText::wrapByDisplayWidth("Kana {$sample} tail", 10, '  ');

        $t->same(6, UnicodeText::displayWidth($kana));
        $t->same(7, UnicodeText::displayWidth($sample));
        $t->same(["\u{1AFF0}", "\u{1AFF5}", "\u{1AFFD}", 'X'], UnicodeText::graphemes($sample));
        $t->same(["\u{1AFF0}", "\u{1AFF5}", "\u{1AFFD}", 'X'], UnicodeText::splitByDisplayBreakpoints($sample, [2, 4, 6]));
        $t->same(["\u{1AFF0}\u{1AFF5}", "\u{1AFFD}X"], UnicodeText::splitAtDisplayWidth($sample, 3));
        $t->same("\u{1AFFD}  ", UnicodeText::padDisplay("\u{1AFFD}", 4));
        $t->same(['Kana', "  {$sample}", '  tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 10, 'Kana Extended-B wrapped line exceeds requested width');
        }
    },
    'measures tangut and khitan supplementary east asian scripts as wide' => static function (TestRunner $t): void {
        $tangut = "\u{17000}\u{187F7}\u{18D00}";
        $components = "\u{18800}\u{18AFF}";
        $khitan = "\u{18B00}\u{18CD5}";
        $sample = "\u{17000}\u{18800}\u{18B00}\u{18D00}X";
        $wrapped = UnicodeText::wrapByDisplayWidth("Rare {$sample} tail", 10, '  ');

        $t->same(6, UnicodeText::displayWidth($tangut));
        $t->same(4, UnicodeText::displayWidth($components));
        $t->same(4, UnicodeText::displayWidth($khitan));
        $t->same(9, UnicodeText::displayWidth($sample));
        $t->same(["\u{17000}", "\u{18800}", "\u{18B00}", "\u{18D00}", 'X'], UnicodeText::splitByDisplayBreakpoints($sample, [2, 4, 6, 8]));
        $t->same(["\u{17000}\u{18800}", "\u{18B00}\u{18D00}X"], UnicodeText::splitAtDisplayWidth($sample, 3));
        $t->same("\u{18B00}  ", UnicodeText::padDisplay("\u{18B00}", 4));
        $t->same(['Rare', "  \u{17000}\u{18800}\u{18B00}\u{18D00}", '  X tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 10, 'Tangut/Khitan wrapped line exceeds requested width');
        }
    },
    'measures bmp east asian wide emoji symbols for display columns' => static function (TestRunner $t): void {
        $transport = "\u{231A}\u{231B}\u{23E9}\u{23F3}";
        $status = "\u{2705}\u{274C}\u{2757}\u{2B50}\u{2B55}";
        $weatherSport = "\u{2614}\u{2615}\u{26BD}\u{26C4}\u{26FD}";
        $zodiac = "\u{2648}\u{2653}";
        $sample = "\u{231A}\u{2705}\u{2B50}X";
        $wrapped = UnicodeText::wrapByDisplayWidth("Emoji {$sample} tail", 10, '  ');

        $t->same(8, UnicodeText::displayWidth($transport));
        $t->same(10, UnicodeText::displayWidth($status));
        $t->same(10, UnicodeText::displayWidth($weatherSport));
        $t->same(4, UnicodeText::displayWidth($zodiac));
        $t->same(["\u{231A}", "\u{2705}", "\u{2B50}", 'X'], UnicodeText::splitByDisplayBreakpoints($sample, [2, 4, 6]));
        $t->same(["\u{231A}\u{2705}", "\u{2B50}X"], UnicodeText::splitAtDisplayWidth($sample, 3));
        $t->same("\u{2B50}  ", UnicodeText::padDisplay("\u{2B50}", 4));
        $t->same(['Emoji', "  {$sample}", '  tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 10, 'BMP East Asian wide emoji wrapped line exceeds requested width');
        }
    },
    'measures geometric emoji symbols for display columns' => static function (TestRunner $t): void {
        $coloredCircles = "\u{1F7E0}\u{1F7E2}\u{1F7E6}";
        $coloredSquares = "\u{1F7E9}\u{1F7EB}";
        $heavyEquals = "\u{1F7F0}";
        $sample = "\u{1F7E0}\u{1F7E9}\u{1F7F0}X";
        $wrapped = UnicodeText::wrapByDisplayWidth("Status {$sample} tail", 10, '  ');

        $t->same(6, UnicodeText::displayWidth($coloredCircles));
        $t->same(4, UnicodeText::displayWidth($coloredSquares));
        $t->same(2, UnicodeText::displayWidth($heavyEquals));
        $t->same(["\u{1F7E0}", "\u{1F7E9}", "\u{1F7F0}", 'X'], UnicodeText::splitByDisplayBreakpoints($sample, [2, 4, 6]));
        $t->same(["\u{1F7E0}\u{1F7E9}", "\u{1F7F0}X"], UnicodeText::splitAtDisplayWidth($sample, 3));
        $t->same("\u{1F7E9}  ", UnicodeText::padDisplay("\u{1F7E9}", 4));
        $t->same(['Status', "  {$sample}", '  tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 10, 'Geometric emoji wrapped line exceeds requested width');
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
