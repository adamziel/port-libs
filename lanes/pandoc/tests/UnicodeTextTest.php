<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\PandocConverter;
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

$utf16be = static function (array $codepoints): string {
    $bytes = '';
    foreach ($codepoints as $codepoint) {
        if ($codepoint <= 0xffff) {
            $bytes .= pack('n', $codepoint);
            continue;
        }

        $value = $codepoint - 0x10000;
        $bytes .= pack('n', 0xd800 + ($value >> 10));
        $bytes .= pack('n', 0xdc00 + ($value & 0x03ff));
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
    'routes encoded markdown bytes through the converter reader path' => static function (TestRunner $t): void {
        $document = PandocConverter::read("# Caf\xE9\n\nPrice \x8010", 'markdown', ['sourceEncoding' => 'windows-1252']);
        $blocks = PandocConverter::write($document, 'wordpress');

        $t->same(['encoding' => 'windows-1252', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Café', $document->children[0]->attr('text'));
        $t->same('Price €10', $document->children[1]->attr('text'));
        $t->contains('<h1 id="café">Café</h1>', $blocks);
        $t->contains('<p>Price €10</p>', $blocks);
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
    'decodes mac turkish source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Mac Turkish\n\nYazar \xD2\xDCstanbul\xD3 \xD1 \x82a\xDB; \xDEi\xDFli, \xDDl\xDDk; \xDA\xDB \xF5.";
        $decoded = UnicodeText::decodeBytes($bytes, 'x-mac-turkish');
        $document = (new MarkdownReader())->readBytes($bytes, 'mac-turkish');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xDA\xDB\xDC\xDD\xDE\xDF\xF5", 'macturkish');
        $macRomanComparison = UnicodeText::decodeBytes("\xDA\xDB\xDC\xDD\xDE\xDF\xF5", 'macintosh');

        $t->same('mac-turkish', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Mac Turkish\n\nYazar “İstanbul” — Çağ; Şişli, ılık; Ğğ \u{F8A0}.", $decoded['text']);
        $t->same("ĞğİıŞş\u{F8A0}", $specials['text']);
        $t->same('mac-turkish', $specials['encoding']);
        $t->same(0, $specials['repairs']);
        $t->same("⁄€‹›ﬁﬂı", $macRomanComparison['text']);
        $t->same(['encoding' => 'mac-turkish', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Mac Turkish', $document->children[0]->attr('text'));
        $t->same("Yazar “İstanbul” — Çağ; Şişli, ılık; Ğğ \u{F8A0}.", $document->children[1]->attr('text'));
        $t->same(42, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(48, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="mac-turkish">Mac Turkish</h1>', $blocks);
        $t->contains("<p>Yazar “İstanbul” — Çağ; Şişli, ılık; Ğğ \u{F8A0}.</p>", $blocks);
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
    'decodes iso 8859 3 latin3 source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Latin3 Import\n\nMalti \xA1\xB1 u \xD5\xF5; Esperanto \xC6\xE6 \xD8\xF8 \xDD\xFD \xDE\xFE; Turk \xA9\xB9; \xAF\xBF.";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso-ir-109');
        $document = (new MarkdownReader())->readBytes($bytes, 'latin3');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA1\xA2\xA6\xA9\xAA\xAB\xAC\xAF\xB1\xB6\xB9\xBA\xBB\xBC\xBF\xC5\xC6\xD5\xD8\xDD\xDE\xE5\xE6\xF5\xF8\xFD\xFE\xFF", 'iso-8859-3');
        $undefined = UnicodeText::decodeBytes("A\xA5B\xAEC\xBED\xC3E\xD0F\xE3G\xF0H", 'iso-8859-3');

        $t->same('iso-8859-3', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Latin3 Import\n\nMalti Ħħ u Ġġ; Esperanto Ĉĉ Ĝĝ Ŭŭ Ŝŝ; Turk İı; Żż.", $decoded['text']);
        $t->same("Ħ˘ĤİŞĞĴŻħĥışğĵżĊĈĠĜŬŜċĉġĝŭŝ˙", $specials['text']);
        $t->same("A\u{FFFD}B\u{FFFD}C\u{FFFD}D\u{FFFD}E\u{FFFD}F\u{FFFD}G\u{FFFD}H", $undefined['text']);
        $t->same(7, $undefined['repairs']);
        $t->same(['encoding' => 'iso-8859-3', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Latin3 Import', $document->children[0]->attr('text'));
        $t->same('Malti Ħħ u Ġġ; Esperanto Ĉĉ Ĝĝ Ŭŭ Ŝŝ; Turk İı; Żż.', $document->children[1]->attr('text'));
        $t->same(50, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="latin3-import">Latin3 Import</h1>', $blocks);
        $t->contains('<p>Malti Ħħ u Ġġ; Esperanto Ĉĉ Ĝĝ Ŭŭ Ŝŝ; Turk İı; Żż.</p>', $blocks);
    },
    'decodes iso 8859 4 latin4 source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Latin4 Import\n\nBaltic \xC0\xE0 \xD3\xF3 \xD1\xF1 \xA9\xB9 \xAE\xBE; \xBD\xBF.";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso-ir-110');
        $document = (new MarkdownReader())->readBytes($bytes, 'latin4');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA1\xA2\xA3\xA5\xA6\xA9\xAA\xAB\xAC\xAE\xB1\xB2\xB3\xB5\xB6\xB7\xB9\xBA\xBB\xBC\xBD\xBE\xBF\xC0\xC7\xC8\xCA\xCC\xCF\xD0\xD1\xD2\xD3\xD9\xDD\xDE\xE0\xE7\xE8\xEA\xEC\xEF\xF0\xF1\xF2\xF3\xF9\xFD\xFE\xFF", 'iso-8859-4');

        $t->same('iso-8859-4', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Latin4 Import\n\nBaltic Āā Ķķ Ņņ Šš Žž; Ŋŋ.", $decoded['text']);
        $t->same('ĄĸŖĨĻŠĒĢŦŽą˛ŗĩļˇšēģŧŊžŋĀĮČĘĖĪĐŅŌĶŲŨŪāįčęėīđņōķųũū˙', $specials['text']);
        $t->same(['encoding' => 'iso-8859-4', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Latin4 Import', $document->children[0]->attr('text'));
        $t->same('Baltic Āā Ķķ Ņņ Šš Žž; Ŋŋ.', $document->children[1]->attr('text'));
        $t->same(26, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="latin4-import">Latin4 Import</h1>', $blocks);
        $t->contains('<p>Baltic Āā Ķķ Ņņ Šš Žž; Ŋŋ.</p>', $blocks);
    },
    'decodes iso 8859 10 latin6 source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Latin6 Import\n\nNordic \xA6\xB6 \xAB\xBB; Sami \xAF\xBF\xFF; Baltic \xA1\xB1 \xA2\xB2 \xAE\xBE; \xBD and \xD7\xF7.";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso-ir-157');
        $document = (new MarkdownReader())->readBytes($bytes, 'latin6');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA1\xA2\xA3\xA4\xA5\xA6\xA8\xA9\xAA\xAB\xAC\xAE\xAF\xB1\xB2\xB3\xB4\xB5\xB6\xB8\xB9\xBA\xBB\xBC\xBD\xBE\xBF\xC0\xC7\xC8\xCA\xCC\xD1\xD2\xD7\xD9\xE0\xE7\xE8\xEA\xEC\xF1\xF2\xF7\xF9\xFF", 'csisolatin6');

        $t->same('iso-8859-10', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Latin6 Import\n\nNordic Ķķ Ŧŧ; Sami Ŋŋĸ; Baltic Ąą Ēē Ūū; ― and Ũũ.", $decoded['text']);
        $t->same('ĄĒĢĪĨĶĻĐŠŦŽŪŊąēģīĩķļđšŧž―ūŋĀĮČĘĖŅŌŨŲāįčęėņōũųĸ', $specials['text']);
        $t->same(0, $specials['repairs']);
        $t->same(['encoding' => 'iso-8859-10', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Latin6 Import', $document->children[0]->attr('text'));
        $t->same('Nordic Ķķ Ŧŧ; Sami Ŋŋĸ; Baltic Ąą Ēē Ūū; ― and Ũũ.', $document->children[1]->attr('text'));
        $t->same(50, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(58, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="latin6-import">Latin6 Import</h1>', $blocks);
        $t->contains('<p>Nordic Ķķ Ŧŧ; Sami Ŋŋĸ; Baltic Ąą Ēē Ūū; ― and Ũũ.</p>', $blocks);
    },
    'decodes iso 8859 13 latin7 source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Latin7 Import\n\nBaltic \xC2\xE2 \xD1\xF1 \xD2\xF2 \xD8\xF8 \xDA\xFA \xDD\xFD \xFE; quotes \xA5\xB4text\xA1\xFF.";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso-ir-179');
        $document = (new MarkdownReader())->readBytes($bytes, 'latin7');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA1\xA5\xA8\xAA\xAF\xB4\xB8\xBA\xBF\xC0\xC1\xC2\xC3\xC6\xC7\xC8\xCA\xCB\xCC\xCD\xCE\xCF\xD0\xD1\xD2\xD4\xD8\xD9\xDA\xDB\xDD\xDE\xE0\xE1\xE2\xE3\xE6\xE7\xE8\xEA\xEB\xEC\xED\xEE\xEF\xF0\xF1\xF2\xF4\xF8\xF9\xFA\xFB\xFD\xFE\xFF", 'iso-8859-13');

        $t->same('iso-8859-13', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Latin7 Import\n\nBaltic Āā Ńń Ņņ Ųų Śś Żż ž; quotes „“text”’.", $decoded['text']);
        $t->same('”„ØŖÆ“øŗæĄĮĀĆĘĒČŹĖĢĶĪĻŠŃŅŌŲŁŚŪŻŽąįāćęēčźėģķīļšńņōųłśūżž’', $specials['text']);
        $t->same(0, $specials['repairs']);
        $t->same(['encoding' => 'iso-8859-13', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Latin7 Import', $document->children[0]->attr('text'));
        $t->same('Baltic Āā Ńń Ņņ Ųų Śś Żż ž; quotes „“text”’.', $document->children[1]->attr('text'));
        $t->same(44, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="latin7-import">Latin7 Import</h1>', $blocks);
        $t->contains('<p>Baltic Āā Ńń Ņņ Ųų Śś Żż ž; quotes „“text”’.</p>', $blocks);
    },
    'decodes windows 1257 baltic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Windows Baltic\n\nCena \x8020; \x93R\xEEga\x94 \x97 \xC0\xE0 \xC1\xE1 \xC6\xE6 \xCC\xEC \xD8\xF8 \xDA\xFA \xDD\xFD; \x8D\x8E\x8F\x9D\x9E.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp1257');
        $document = (new MarkdownReader())->readBytes($bytes, 'microsoft-cp1257');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x80\x82\x84\x85\x86\x87\x89\x8B\x8D\x8E\x8F\x91\x92\x93\x94\x95\x96\x97\x99\x9B\x9D\x9E\xA8\xAA\xAF\xB8\xBA\xBF\xFF", 'windows-1257');
        $controls = UnicodeText::decodeBytes("\x81\x83\x88\x8A\x8C\x90\x98\x9A\x9C\x9F", 'windows-1257');
        $undefined = UnicodeText::decodeBytes("A\xA1B\xA5C", 'windows-1257');

        $t->same('windows-1257', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Windows Baltic\n\nCena €20; “Rīga” — Ąą Įį Ęę Ģģ Ųų Śś Żż; ¨ˇ¸¯˛.", $decoded['text']);
        $t->same("€‚„…†‡‰‹¨ˇ¸‘’“”•–—™›¯˛ØŖÆøŗæ˙", $specials['text']);
        $t->same("\u{0081}\u{0083}\u{0088}\u{008A}\u{008C}\u{0090}\u{0098}\u{009A}\u{009C}\u{009F}", $controls['text']);
        $t->same(0, UnicodeText::displayWidth($controls['text']));
        $t->same("A\u{FFFD}B\u{FFFD}C", $undefined['text']);
        $t->same(2, $undefined['repairs']);
        $t->same(['encoding' => 'windows-1257', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Windows Baltic', $document->children[0]->attr('text'));
        $t->same('Cena €20; “Rīga” — Ąą Įį Ęę Ģģ Ųų Śś Żż; ¨ˇ¸¯˛.', $document->children[1]->attr('text'));
        $t->same(47, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="windows-baltic">Windows Baltic</h1>', $blocks);
        $t->contains('<p>Cena €20; “Rīga” — Ąą Įį Ęę Ģģ Ųų Śś Żż; ¨ˇ¸¯˛.</p>', $blocks);
    },
    'decodes iso 8859 14 latin8 celtic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Latin8 Import\n\nCeltic \xC0\xE0 \xD0\xF0 \xDE\xFE; dotted \xA1\xA2 \xA4\xA5 \xAA\xBA \xBB\xBF; Welsh \xD7\xF7.";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso-ir-199');
        $document = (new MarkdownReader())->readBytes($bytes, 'latin8');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA1\xA2\xA4\xA5\xA6\xA8\xAA\xAB\xAC\xAF\xB0\xB1\xB2\xB3\xB4\xB5\xB7\xB8\xB9\xBA\xBB\xBC\xBD\xBE\xBF\xD0\xD7\xDE\xF0\xF7\xFE", 'iso-8859-14');

        $t->same('iso-8859-14', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Latin8 Import\n\nCeltic Àà Ŵŵ Ŷŷ; dotted Ḃḃ Ċċ Ẃẃ Ṡṡ; Welsh Ṫṫ.", $decoded['text']);
        $t->same('ḂḃĊċḊẀẂḋỲŸḞḟĠġṀṁṖẁṗẃṠỳẄẅṡŴṪŶŵṫŷ', $specials['text']);
        $t->same(0, $specials['repairs']);
        $t->same(['encoding' => 'iso-8859-14', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Latin8 Import', $document->children[0]->attr('text'));
        $t->same('Celtic Àà Ŵŵ Ŷŷ; dotted Ḃḃ Ċċ Ẃẃ Ṡṡ; Welsh Ṫṫ.', $document->children[1]->attr('text'));
        $t->same(46, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="latin8-import">Latin8 Import</h1>', $blocks);
        $t->contains('<p>Celtic Àà Ŵŵ Ŷŷ; dotted Ḃḃ Ċċ Ẃẃ Ṡṡ; Welsh Ṫṫ.</p>', $blocks);
    },
    'decodes iso 8859 16 latin10 source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Latin10 Import\n\nRomanian \xAA\xBA \xDE\xFE; Central \xD7\xF7 \xD8\xF8 \xDD\xFD; Euro \xA4; quotes \xA5text\xB5.";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso-ir-226');
        $document = (new MarkdownReader())->readBytes($bytes, 'latin10');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA1\xA2\xA3\xA4\xA5\xA6\xA8\xAA\xAC\xAE\xAF\xB2\xB3\xB4\xB5\xB8\xB9\xBA\xBC\xBD\xBE\xBF\xC3\xC5\xD0\xD1\xD5\xD7\xD8\xDD\xDE\xE3\xE5\xF0\xF1\xF5\xF7\xF8\xFD\xFE", 'iso-8859-16:2001');

        $t->same('iso-8859-16', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Latin10 Import\n\nRomanian Șș Țț; Central Śś Űű Ęę; Euro €; quotes „text”.", $decoded['text']);
        $t->same('ĄąŁ€„ŠšȘŹźŻČłŽ”žčșŒœŸżĂĆĐŃŐŚŰĘȚăćđńőśűęț', $specials['text']);
        $t->same(0, $specials['repairs']);
        $t->same(['encoding' => 'iso-8859-16', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Latin10 Import', $document->children[0]->attr('text'));
        $t->same('Romanian Șș Țț; Central Śś Űű Ęę; Euro €; quotes „text”.', $document->children[1]->attr('text'));
        $t->same(56, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="latin10-import">Latin10 Import</h1>', $blocks);
        $t->contains('<p>Romanian Șș Țț; Central Śś Űű Ęę; Euro €; quotes „text”.</p>', $blocks);
    },
    'decodes windows 1251 cyrillic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xC8\xEC\xEF\xEE\xF0\xF2\n\n\xD0\xE5\xE4\xE0\xEA\xF2\xEE\xF0 \x93\xEF\xF0\xE8\xE2\xE5\xF2\x94 \x97 \x8810; \xA8\xEB\xEA\xE0 \xB9 7.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp1251');
        $document = (new MarkdownReader())->readBytes($bytes, 'microsoft-cp1251');
        $blocks = (new WordPressBlockWriter())->write($document);
        $malformed = UnicodeText::decodeBytes("A\x98B", 'windows-1251');

        $t->same('windows-1251', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Импорт\n\nРедактор “привет” — €10; Ёлка № 7.", $decoded['text']);
        $t->same(['encoding' => 'windows-1251', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Импорт', $document->children[0]->attr('text'));
        $t->same('Редактор “привет” — €10; Ёлка № 7.', $document->children[1]->attr('text'));
        $t->same(34, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="импорт">Импорт</h1>', $blocks);
        $t->contains('<p>Редактор “привет” — €10; Ёлка № 7.</p>', $blocks);
        $t->same('windows-1251', $malformed['encoding']);
        $t->same("A\u{FFFD}B", $malformed['text']);
        $t->same(1, $malformed['repairs']);
    },
    'decodes koi8 r cyrillic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xE9\xCD\xD0\xCF\xD2\xD4\n\n\xF2\xC5\xC4\xC1\xCB\xD4\xCF\xD2 \xD0\xD2\xC9\xD7\xC5\xD4; \xB3\xCC\xCB\xC1; \x82\x80\x83.";
        $decoded = UnicodeText::decodeBytes($bytes, 'koi8-r');
        $document = (new MarkdownReader())->readBytes($bytes, 'cskoi8r');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('koi8-r', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Импорт\n\nРедактор привет; Ёлка; ┌─┐.", $decoded['text']);
        $t->same(['encoding' => 'koi8-r', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Импорт', $document->children[0]->attr('text'));
        $t->same('Редактор привет; Ёлка; ┌─┐.', $document->children[1]->attr('text'));
        $t->same(27, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(48, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="импорт">Импорт</h1>', $blocks);
        $t->contains('<p>Редактор привет; Ёлка; ┌─┐.</p>', $blocks);
    },
    'decodes koi8 u ukrainian source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xF5\xCB\xD2\xC1\xA7\xCE\xC1\n\n\xF2\xC5\xC4\xC1\xCB\xD4\xCF\xD2 \xEB\xC9\xA7\xD7; \xA7\xD6\xC1\xCB \xA6 \xAD\xC1\xCE\xCF\xCB; \xB4\xB6\xB7\xBD.";
        $decoded = UnicodeText::decodeBytes($bytes, 'koi8-u');
        $document = (new MarkdownReader())->readBytes($bytes, 'cskoi8u');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA4\xA6\xA7\xAD\xB4\xB6\xB7\xBD", 'koi8-u');
        $koi8RComparison = UnicodeText::decodeBytes("\xA4\xA6\xA7\xAD\xB4\xB6\xB7\xBD", 'koi8-r');

        $t->same('koi8-u', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Україна\n\nРедактор Київ; їжак і ґанок; ЄІЇҐ.", $decoded['text']);
        $t->same('єіїґЄІЇҐ', $specials['text']);
        $t->same('╓╕╖╜╢╤╥╫', $koi8RComparison['text']);
        $t->same(['encoding' => 'koi8-u', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Україна', $document->children[0]->attr('text'));
        $t->same('Редактор Київ; їжак і ґанок; ЄІЇҐ.', $document->children[1]->attr('text'));
        $t->same(34, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(52, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="україна">Україна</h1>', $blocks);
        $t->contains('<p>Редактор Київ; їжак і ґанок; ЄІЇҐ.</p>', $blocks);
    },
    'decodes koi8 ru belarusian ukrainian source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xE2\xC5\xCC\xC1\xD2\xD5\xD3\xD8\n\n\xF2\xC5\xC4\xC1\xCB\xD4\xCF\xD2 \xED\xA6\xCE\xD3\xCB; \xE2\xC5\xCC\xC1\xD2\xD5\xD3\xD8: \xBE\xAE; \xF5\xCB\xD2\xC1\xA7\xCE\xC1 \xB4\xB6\xB7\xBD.";
        $decoded = UnicodeText::decodeBytes($bytes, 'koi8-ru');
        $document = (new MarkdownReader())->readBytes($bytes, 'cskoi8ru');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA4\xA6\xA7\xAD\xAE\xB4\xB6\xB7\xBD\xBE", 'koi8-ru');
        $koi8UComparison = UnicodeText::decodeBytes("\xAE\xBE", 'koi8-u');

        $t->same('koi8-ru', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Беларусь\n\nРедактор Мінск; Беларусь: Ўў; Україна ЄІЇҐ.", $decoded['text']);
        $t->same('єіїґўЄІЇҐЎ', $specials['text']);
        $t->same('╝╬', $koi8UComparison['text']);
        $t->same(['encoding' => 'koi8-ru', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Беларусь', $document->children[0]->attr('text'));
        $t->same('Редактор Мінск; Беларусь: Ўў; Україна ЄІЇҐ.', $document->children[1]->attr('text'));
        $t->same(43, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(69, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="беларусь">Беларусь</h1>', $blocks);
        $t->contains('<p>Редактор Мінск; Беларусь: Ўў; Україна ЄІЇҐ.</p>', $blocks);
    },
    'decodes koi8 t tajik source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xF4\xCF\x8D\xC9\xCB\xC9\xD3\xD4\xCF\xCE\n\n\xED\xC1\xD4\xCE \x93\xD4\xCF\x8D\xC9\xCB\xA5\x94 \x97 \xB9 7; \x83\xC1\xC6\xD5\xD2; \x90\xA1\x80\xCF\xCE; \xA2\x8C\x8E\x8D.";
        $decoded = UnicodeText::decodeBytes($bytes, 'koi8-tajik');
        $document = (new MarkdownReader())->readBytes($bytes, 'cskoi8t');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x80\x81\x83\x8A\x8C\x8D\x8E\x90\xA1\xA2\xA5\xB5", 'koi8-t');
        $undefined = UnicodeText::decodeBytes("A\x88B\x8FC\x9AD\xA0E\xB4F\xBEG", 'koi8-t');

        $t->same('koi8-t', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Тоҷикистон\n\nМатн “тоҷикӣ” — № 7; Ғафур; Қӯқон; ӮҲҶҷ.", $decoded['text']);
        $t->same('қғҒҳҲҷҶҚӯӮӣӢ', $specials['text']);
        $t->same(0, $specials['repairs']);
        $t->same("A\u{FFFD}B\u{FFFD}C\u{FFFD}D\u{FFFD}E\u{FFFD}F\u{FFFD}G", $undefined['text']);
        $t->same(6, $undefined['repairs']);
        $t->same(['encoding' => 'koi8-t', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Тоҷикистон', $document->children[0]->attr('text'));
        $t->same('Матн “тоҷикӣ” — № 7; Ғафур; Қӯқон; ӮҲҶҷ.', $document->children[1]->attr('text'));
        $t->same(40, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(58, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="тоҷикистон">Тоҷикистон</h1>', $blocks);
        $t->contains('<p>Матн “тоҷикӣ” — № 7; Ғафур; Қӯқон; ӮҲҶҷ.</p>', $blocks);
    },
    'decodes mac cyrillic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \x88\xEC\xEF\xEE\xF0\xF2\n\n\x90\xE5\xE4\xE0\xEA\xF2\xEE\xF0 \xD2\xEF\xF0\xE8\xE2\xE5\xF2\xD3 \xD1 \xFF20; \xDD\xEB\xEA\xE0 \xDC 7; \xBA\xBB\xB8\xB9.";
        $decoded = UnicodeText::decodeBytes($bytes, 'x-mac-cyrillic');
        $document = (new MarkdownReader())->readBytes($bytes, 'maccyrillic');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA2\xA7\xB4\xB6\xB8\xB9\xBA\xBB\xD0\xD1\xD2\xD3\xDC\xDD\xDE\xDF\xFF", 'mac-cyrillic');

        $t->same('mac-cyrillic', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Импорт\n\nРедактор “привет” — €20; Ёлка № 7; ЇїЄє.", $decoded['text']);
        $t->same('ҐІіґЄєЇї–—“”№Ёёя€', $specials['text']);
        $t->same('mac-cyrillic', $specials['encoding']);
        $t->same(0, $specials['repairs']);
        $t->same(['encoding' => 'mac-cyrillic', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Импорт', $document->children[0]->attr('text'));
        $t->same('Редактор “привет” — €20; Ёлка № 7; ЇїЄє.', $document->children[1]->attr('text'));
        $t->same(40, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(63, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="импорт">Импорт</h1>', $blocks);
        $t->contains('<p>Редактор “привет” — €20; Ёлка № 7; ЇїЄє.</p>', $blocks);
    },
    'decodes mac ukrainian source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \x93\xEA\xF0\xE0\xBB\xED\xE0\n\n\x90\xE5\xE4\xE0\xEA\xF2\xEE\xF0 \x8A\xE8\xBB\xE2; \xBA\xE6\xE0\xEA \xB6\xE0\xED\xEE\xEA; \xB8\xA7\xBA\xA2; currency \xFF20.";
        $decoded = UnicodeText::decodeBytes($bytes, 'x-mac-ukrainian');
        $document = (new MarkdownReader())->readBytes($bytes, 'mac-ukraine');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA2\xA7\xB6\xB8\xB9\xBA\xBB\xFF", 'macukrainian');
        $macCyrillicComparison = UnicodeText::decodeBytes("\xFF", 'mac-cyrillic');

        $t->same('mac-ukrainian', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Україна\n\nРедактор Київ; Їжак ґанок; ЄІЇҐ; currency ¤20.", $decoded['text']);
        $t->same('ҐІґЄєЇї¤', $specials['text']);
        $t->same('mac-ukrainian', $specials['encoding']);
        $t->same(0, $specials['repairs']);
        $t->same('€', $macCyrillicComparison['text']);
        $t->same(['encoding' => 'mac-ukrainian', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Україна', $document->children[0]->attr('text'));
        $t->same('Редактор Київ; Їжак ґанок; ЄІЇҐ; currency ¤20.', $document->children[1]->attr('text'));
        $t->same(46, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(65, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="україна">Україна</h1>', $blocks);
        $t->contains('<p>Редактор Київ; Їжак ґанок; ЄІЇҐ; currency ¤20.</p>', $blocks);
    },
    'decodes mac greek source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xB6\xEC\xEC\xC0\xE4\xE1\n\n\xAA\xF9\xEE\xF4\xC0\xEB\xF4\xE8\xF7 \xD2\xF0\xE8\xE7\xDC\xD3 \xD1 \xA9 20; \xD9\xDF \xFD\xFE; \xFF.";
        $decoded = UnicodeText::decodeBytes($bytes, 'x-mac-greek');
        $document = (new MarkdownReader())->readBytes($bytes, 'macgreek');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA1\xA2\xA3\xAA\xAB\xB0\xB5\xBC\xBD\xBE\xBF\xC0\xCD\xCE\xD7\xD8\xD9\xDA\xDB\xDC\xDD\xDE\xDF\xE0\xE1\xE2\xE3\xF0\xF1\xF7\xFD\xFE\xFF", 'mac-greek');

        $t->same('mac-greek', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Ελλάδα\n\nΣυντάκτης “πηγή” ― © 20; ΌΏ ΐΰ; \u{F8A0}.", $decoded['text']);
        $t->same("ΓΔΘΣΪΑΒΦΫΨΩάΆΈΉΊΌΎέήίόΏύαβψπώςΐΰ\u{F8A0}", $specials['text']);
        $t->same('mac-greek', $specials['encoding']);
        $t->same(0, $specials['repairs']);
        $t->same(['encoding' => 'mac-greek', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Ελλάδα', $document->children[0]->attr('text'));
        $t->same("Συντάκτης “πηγή” ― © 20; ΌΏ ΐΰ; \u{F8A0}.", $document->children[1]->attr('text'));
        $t->same(34, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(48, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="ελλάδα">Ελλάδα</h1>', $blocks);
        $t->contains("<p>Συντάκτης “πηγή” ― © 20; ΌΏ ΐΰ; \u{F8A0}.</p>", $blocks);
    },
    'decodes mac icelandic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Mac Iceland\n\nRitstj\x97ri \xD2\x92sland\xD3 \xD1 \xDB20; \xDEorn og \xDDa\xE0; \xDC/\xDD, \xA0/\xE0; \xD5.";
        $decoded = UnicodeText::decodeBytes($bytes, 'x-mac-icelandic');
        $document = (new MarkdownReader())->readBytes($bytes, 'maciceland');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA0\xD5\xDB\xDC\xDD\xDE\xDF\xE0\xF0", 'mac-iceland');
        $macRomanComparison = UnicodeText::decodeBytes("\xA0\xD5\xDC\xDD\xDE\xDF\xE0", 'macintosh');

        $t->same('mac-iceland', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Mac Iceland\n\nRitstjóri “ísland” — €20; Þorn og ðaý; Ð/ð, Ý/ý; ’.", $decoded['text']);
        $t->same("Ý’€ÐðÞþý\u{F8FF}", $specials['text']);
        $t->same('†’‹›ﬁﬂ‡', $macRomanComparison['text']);
        $t->same('mac-iceland', $specials['encoding']);
        $t->same(0, $specials['repairs']);
        $t->same(['encoding' => 'mac-iceland', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Mac Iceland', $document->children[0]->attr('text'));
        $t->same('Ritstjóri “ísland” — €20; Þorn og ðaý; Ð/ð, Ý/ý; ’.', $document->children[1]->attr('text'));
        $t->same(51, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(62, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="mac-iceland">Mac Iceland</h1>', $blocks);
        $t->contains('<p>Ritstjóri “ísland” — €20; Þorn og ðaý; Ð/ð, Ý/ý; ’.</p>', $blocks);
    },
    'decodes mac central european source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Mac Central\n\nCzech \x89esk\x8E \xE4kola \xDBeka; Polish Za\xFD\x97\xB8\x8D g\xAB\xE6l\x88 ja\x90\xC4; Hungarian \xCC\xCE \xF4\xF5; quotes \xD2text\xD3 \xD1 \xA310.";
        $decoded = UnicodeText::decodeBytes($bytes, 'x-mac-cent-euro');
        $document = (new MarkdownReader())->readBytes($bytes, 'mac-ce');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x89\x8B\xE1\xE4\xEB\xEC\xCC\xCE\xF4\xF5\xD2\xD3\xD1\xA3", 'maccenteuro');
        $macRomanComparison = UnicodeText::decodeBytes("\x89\xDB\xFC", 'macintosh');

        $t->same('mac-central-europe', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Mac Central\n\nCzech České škola Řeka; Polish Zażółć gęślą jaźń; Hungarian Őő Űű; quotes “text” — £10.", $decoded['text']);
        $t->same('ČčŠšŽžŐőŰű“”—£', $specials['text']);
        $t->same('mac-central-europe', $specials['encoding']);
        $t->same(0, $specials['repairs']);
        $t->same('â€¸', $macRomanComparison['text']);
        $t->same(['encoding' => 'mac-central-europe', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Mac Central', $document->children[0]->attr('text'));
        $t->same('Czech České škola Řeka; Polish Zażółć gęślą jaźń; Hungarian Őő Űű; quotes “text” — £10.', $document->children[1]->attr('text'));
        $t->same(87, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(94, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="mac-central">Mac Central</h1>', $blocks);
        $t->contains('<p>Czech České škola Řeka; Polish Zażółć gęślą jaźń; Hungarian Őő Űű; quotes “text” — £10.</p>', $blocks);
    },
    'decodes mac romanian source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Mac Romania\n\nEditor \xD2rom\x89n\xBE\xD3 \xD1 Bra\xBFov; \xDEar\xBE \xBFi fa\xDF\xBE; cost \xDB10; \xBD.";
        $decoded = UnicodeText::decodeBytes($bytes, 'x-mac-romanian');
        $document = (new MarkdownReader())->readBytes($bytes, 'macromania');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xAE\xBE\xAF\xBF\xDE\xDF\xDB\xBD", 'mac-romania');
        $macRomanComparison = UnicodeText::decodeBytes("\xAE\xBE\xAF\xBF\xDE\xDF\xDB\xBD", 'macintosh');

        $t->same('mac-romania', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Mac Romania\n\nEditor “română” — Braşov; Ţară şi faţă; cost ¤10; Ω.", $decoded['text']);
        $t->same('ĂăŞşŢţ¤Ω', $specials['text']);
        $t->same('mac-romania', $specials['encoding']);
        $t->same(0, $specials['repairs']);
        $t->same('ÆæØøﬁﬂ€Ω', $macRomanComparison['text']);
        $t->same(['encoding' => 'mac-romania', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Mac Romania', $document->children[0]->attr('text'));
        $t->same('Editor “română” — Braşov; Ţară şi faţă; cost ¤10; Ω.', $document->children[1]->attr('text'));
        $t->same(52, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(57, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="mac-romania">Mac Romania</h1>', $blocks);
        $t->contains('<p>Editor “română” — Braşov; Ţară şi faţă; cost ¤10; Ω.</p>', $blocks);
    },
    'decodes mac croatian source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Mac Croatian\n\nNovinar \xD2\xA9ibenik\xD3 \xD1 \xC6evapi; \xAEupanija, \xB9uma, \xBEar; \xC6\xC8\xD0/\xE6\xE8\xF0.";
        $decoded = UnicodeText::decodeBytes($bytes, 'x-mac-croatian');
        $document = (new MarkdownReader())->readBytes($bytes, 'maccroatian');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA9\xAE\xB9\xBE\xC6\xC8\xD0\xE6\xE8\xF0\xB4\xD8\xD9\xDE\xDF\xE0\xF9\xFA\xFD\xFE", 'mac-croatian');
        $macRomanComparison = UnicodeText::decodeBytes("\xA9\xAE\xB9\xBE\xC6\xC8\xD0\xE6\xE8\xF0", 'macintosh');

        $t->same('mac-croatian', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Mac Croatian\n\nNovinar “Šibenik” — Ćevapi; Županija, šuma, žar; ĆČĐ/ćčđ.", $decoded['text']);
        $t->same("ŠŽšžĆČĐćčđ∆\u{F8FF}©Æ»–πËÊæ", $specials['text']);
        $t->same('mac-croatian', $specials['encoding']);
        $t->same(0, $specials['repairs']);
        $t->same("©Æπæ∆»–ÊË\u{F8FF}", $macRomanComparison['text']);
        $t->same(['encoding' => 'mac-croatian', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Mac Croatian', $document->children[0]->attr('text'));
        $t->same('Novinar “Šibenik” — Ćevapi; Županija, šuma, žar; ĆČĐ/ćčđ.', $document->children[1]->attr('text'));
        $t->same(57, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(61, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="mac-croatian">Mac Croatian</h1>', $blocks);
        $t->contains('<p>Novinar “Šibenik” — Ćevapi; Županija, šuma, žar; ĆČĐ/ćčđ.</p>', $blocks);
    },
    'decodes mac dingbats source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "\x21\x22\x23 \x33\x34 \x48 \xA8\xAA\xAB \xAC\xB6\xBF \xD5\xD6\xD7 \xE0\xEF \x80.";
        $decoded = UnicodeText::decodeBytes($bytes, 'x-mac-dingbats');
        $document = (new MarkdownReader())->readBytes($bytes, 'macdingbats');
        $blocks = (new WordPressBlockWriter())->write($document);
        $privateUse = UnicodeText::decodeBytes("\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8A\x8B\x8C\x8D", 'mac-dingbats');
        $undefined = UnicodeText::decodeBytes("A\x8EB\xA0C\xF0D\xFFE", 'mac-dingbats');

        $t->same('mac-dingbats', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("✁✂✃ ✓✔ ★ ♣♥♠ ①❶❿ →↔↕ ➠➯ \u{F8D7}✎", $decoded['text']);
        $t->same("\u{F8D7}\u{F8D8}\u{F8D9}\u{F8DA}\u{F8DB}\u{F8DC}\u{F8DD}\u{F8DE}\u{F8DF}\u{F8E0}\u{F8E1}\u{F8E2}\u{F8E3}\u{F8E4}", $privateUse['text']);
        $t->same(14, UnicodeText::displayWidth($privateUse['text']));
        $t->same(28, UnicodeText::displayWidth($privateUse['text'], 'wide'));
        $t->same("✡\u{FFFD}✢\u{FFFD}✣\u{FFFD}✤\u{FFFD}✥", $undefined['text']);
        $t->same(4, $undefined['repairs']);
        $t->same(['encoding' => 'mac-dingbats', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same(1, count($document->children));
        $t->same('paragraph', $document->children[0]->type);
        $t->same("✁✂✃ ✓✔ ★ ♣♥♠ ①❶❿ →↔↕ ➠➯ \u{F8D7}✎", $document->children[0]->attr('text'));
        $t->same(26, UnicodeText::displayWidth((string) $document->children[0]->attr('text')));
        $t->same(37, UnicodeText::displayWidth((string) $document->children[0]->attr('text'), 'wide'));
        $t->contains("<p>✁✂✃ ✓✔ ★ ♣♥♠ ①❶❿ →↔↕ ➠➯ \u{F8D7}✎</p>", $blocks);
    },
    'decodes mac symbol source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "A B G W a b g w \xB3 \xB9\xBA\xBB \xD5\xD6\xE5 \xF2 \xF0.";
        $decoded = UnicodeText::decodeBytes($bytes, 'symbol');
        $document = (new MarkdownReader())->readBytes($bytes, 'x-mac-symbol');
        $blocks = (new WordPressBlockWriter())->write($document);
        $undefined = UnicodeText::decodeBytes("A\x80B\xA0C\xFFD", 'mac-symbol');

        $t->same('mac-symbol', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("Α Β Γ Ω α β γ ω ≥ ≠≡≈ ∏√∑ ∫ \u{F8FF}.", $decoded['text']);
        $t->same("Α\u{FFFD}Β\u{FFFD}Χ\u{FFFD}Δ", $undefined['text']);
        $t->same('mac-symbol', $undefined['encoding']);
        $t->same(3, $undefined['repairs']);
        $t->same(['encoding' => 'mac-symbol', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same(1, count($document->children));
        $t->same('paragraph', $document->children[0]->type);
        $t->same("Α Β Γ Ω α β γ ω ≥ ≠≡≈ ∏√∑ ∫ \u{F8FF}.", $document->children[0]->attr('text'));
        $t->same(30, UnicodeText::displayWidth((string) $document->children[0]->attr('text')));
        $t->same(47, UnicodeText::displayWidth((string) $document->children[0]->attr('text'), 'wide'));
        $t->contains("<p>Α Β Γ Ω α β γ ω ≥ ≠≡≈ ∏√∑ ∫ \u{F8FF}.</p>", $blocks);
    },
    'decodes mac thai source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Mac Thai\n\n\xE0\xB9\xD7\xE9\xCD\xCB\xD2 \xE0\xCD\xA1\xCA\xD2\xC3; \x80\x81 \x8Dtext\x8E \xDD \xDF20; \xDB\xDC.";
        $decoded = UnicodeText::decodeBytes($bytes, 'x-mac-thai');
        $document = (new MarkdownReader())->readBytes($bytes, 'macthai');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x80\x81\x82\x8D\x8E\x91\x9D\x9E\xA0\xDB\xDC\xDD\xDE\xDF\xE0\xE6\xE7\xE8\xED\xEE\xEF\xF0\xF9\xFA\xFB", 'mac-thai');
        $repaired = UnicodeText::decodeBytes("A\x90B\x9FC\xFCD\xFDE\xFEF\xFFG", 'mac-thai');
        $macRomanComparison = UnicodeText::decodeBytes("\x80\x81\x8D\x8E\xDB\xDC\xDD", 'macintosh');

        $t->same('mac-thai', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Mac Thai\n\nเนื้อหา เอกสาร; «» “text” – ฿20; \u{FEFF}\u{200B}.", $decoded['text']);
        $t->same("«»…“”•‘’\u{00A0}\u{FEFF}\u{200B}–—฿เๆ็่ํ™๏๐๙®©", $specials['text']);
        $t->same('mac-thai', $specials['encoding']);
        $t->same(0, $specials['repairs']);
        $t->same("A\u{FFFD}B\u{FFFD}C\u{FFFD}D\u{FFFD}E\u{FFFD}F\u{FFFD}G", $repaired['text']);
        $t->same('mac-thai', $repaired['encoding']);
        $t->same(6, $repaired['repairs']);
        $t->same('ÄÅçé€‹›', $macRomanComparison['text']);
        $t->same(['encoding' => 'mac-thai', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Mac Thai', $document->children[0]->attr('text'));
        $t->same("เนื้อหา เอกสาร; «» “text” – ฿20; \u{FEFF}\u{200B}.", $document->children[1]->attr('text'));
        $t->same(32, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="mac-thai">Mac Thai</h1>', $blocks);
        $t->contains("<p>เนื้อหา เอกสาร; «» “text” – ฿20; \u{FEFF}\u{200B}.</p>", $blocks);
    },
    'decodes ibm866 dos cyrillic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \x88\xAC\xAF\xAE\xE0\xE2\n\n\x90\xA5\xA4\xA0\xAA\xE2\xAE\xE0 \xAF\xE0\xA8\xA2\xA5\xE2; \xF0\xAB\xAA\xA0 \xFC 7; \xB3\xC4\xDA.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp866');
        $document = (new MarkdownReader())->readBytes($bytes, 'csibm866');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xB3\xC4\xDA\xF0\xF1\xF2\xF3\xF4\xF5\xF6\xF7\xF8\xF9\xFA\xFB\xFC\xFD\xFE\xFF", '866');

        $t->same('ibm866', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Импорт\n\nРедактор привет; Ёлка № 7; │─┌.", $decoded['text']);
        $t->same("│─┌ЁёЄєЇїЎў°∙·√№¤■\u{00A0}", $specials['text']);
        $t->same('ibm866', $specials['encoding']);
        $t->same(['encoding' => 'ibm866', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Импорт', $document->children[0]->attr('text'));
        $t->same('Редактор привет; Ёлка № 7; │─┌.', $document->children[1]->attr('text'));
        $t->same(31, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(53, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="импорт">Импорт</h1>', $blocks);
        $t->contains('<p>Редактор привет; Ёлка № 7; │─┌.</p>', $blocks);
    },
    'decodes ibm855 dos cyrillic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# DOS 855\n\n\xE2\xA8\xA6\xA0\xC6\xE5\xD6\xE1 \xD8\xE1\xB7\xEB\xA8\xE5; \x85\xD0\xC6\xA0; \x91\x90 \x93\x92; box \xB3\xC4\xDA; \xEF\xFD.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp855');
        $document = (new MarkdownReader())->readBytes($bytes, 'csibm855');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x80\x81\x84\x85\x90\x91\x92\x93\x9A\x9B\x9C\x9D\x9E\x9F\xEF\xF0\xFD\xFE\xFF", 'ibm855');
        $ibm866Comparison = UnicodeText::decodeBytes("\x80\x84\x85\x90\x91\xA0\xE2\xE5\xEF\xF0", 'ibm866');

        $t->same('ibm855', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# DOS 855\n\nРедактор привет; Ёлка; Љљ Њњ; box │─┌; №§.", $decoded['text']);
        $t->same("ђЂёЁљЉњЊџЏюЮъЪ№\u{00AD}§■\u{00A0}", $specials['text']);
        $t->same('АДЕРСатхяЁ', $ibm866Comparison['text']);
        $t->same(0, UnicodeText::displayWidth("\u{00AD}"));
        $t->same(1, UnicodeText::displayWidth("\u{00A0}"));
        $t->same(['encoding' => 'ibm855', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('DOS 855', $document->children[0]->attr('text'));
        $t->same('Редактор привет; Ёлка; Љљ Њњ; box │─┌; №§.', $document->children[1]->attr('text'));
        $t->same(42, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(65, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="dos-855">DOS 855</h1>', $blocks);
        $t->contains('<p>Редактор привет; Ёлка; Љљ Њњ; box │─┌; №§.</p>', $blocks);
    },
    'decodes ibm737 dos greek source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# DOS 737\n\n\x84\xA2\xA2\x9E\xA4\xA0\xA1\xE1 CP737: \x98\x99\x9A\x9B\x9C; \xEA\xEB\xEC\xED\xEE\xEF\xF0; box \xB3\xC4\xDA; math \xF1\xF2\xF3; \xFF.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp737');
        $document = (new MarkdownReader())->readBytes($bytes, 'csibm737');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8A\x8B\x8C\x8D\x8E\x8F\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9A\x9B\x9C\x9D\x9E\x9F\xE0\xE1\xE2\xE3\xE4\xE5\xE6\xE7\xE8\xE9\xEA\xEB\xEC\xED\xEE\xEF\xF0", 'dos737');

        $t->same('ibm737', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# DOS 737\n\nΕλληνικά CP737: αβγδε; ΆΈΉΊΌΎΏ; box │─┌; math ±≥≤; \u{00A0}.", $decoded['text']);
        $t->same('ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩαβγδεζηθωάέήϊίόύϋώΆΈΉΊΌΎΏ', $specials['text']);
        $t->same('ibm737', $specials['encoding']);
        $t->same(0, $specials['repairs']);
        $t->same(['encoding' => 'ibm737', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('DOS 737', $document->children[0]->attr('text'));
        $t->same("Ελληνικά CP737: αβγδε; ΆΈΉΊΌΎΏ; box │─┌; math ±≥≤; \u{00A0}.", $document->children[1]->attr('text'));
        $t->same(53, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(71, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="dos-737">DOS 737</h1>', $blocks);
        $t->contains("<p>Ελληνικά CP737: αβγδε; ΆΈΉΊΌΎΏ; box │─┌; math ±≥≤; \u{00A0}.</p>", $blocks);
    },
    'decodes ibm869 dos greek source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xA8\xE5\xE5\xE1\xE7\xE3\xE4\x9B\n\n\xCF\xF2\xE7\xEE\x9B\xE4\xEE\xE1\xED \xAB\xEA\xE1\xD8\x9E\xAF; \x86\x88\x8D\x8F\x90\x92\x95\x98; \xDA\xC4\xBF.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp869');
        $document = (new MarkdownReader())->readBytes($bytes, 'csibm869');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x86\x8D\x8F\x90\x91\x92\x95\x96\x98\x9B\x9D\x9E\x9F\xA0\xA1\xA2\xA3\xFC\xFD", 'ibm869');
        $repaired = UnicodeText::decodeBytes("ok\x80\x85\x87\x93\x94", 'dos869');

        $t->same('ibm869', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Ελληνικά\n\nΣυντάκτης ½πηγή»; Ά·ΈΉΊΌΎΏ; ┌─┐.", $decoded['text']);
        $t->same('ΆΈΉΊΪΌΎΫΏάέήίϊΐόύΰώ', $specials['text']);
        $t->same('ibm869', $specials['encoding']);
        $t->same(0, $specials['repairs']);
        $t->same("ok�����", $repaired['text']);
        $t->same(5, $repaired['repairs']);
        $t->same(['encoding' => 'ibm869', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Ελληνικά', $document->children[0]->attr('text'));
        $t->same('Συντάκτης ½πηγή»; Ά·ΈΉΊΌΎΏ; ┌─┐.', $document->children[1]->attr('text'));
        $t->same(32, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(47, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="ελληνικά">Ελληνικά</h1>', $blocks);
        $t->contains('<p>Συντάκτης ½πηγή»; Ά·ΈΉΊΌΎΏ; ┌─┐.</p>', $blocks);
    },
    'decodes ibm437 dos box drawing source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# DOS 437\n\nBox \xC9\xCD\xBB\xBA\xCC; r\x82sum\x82; \xE0\xE1 \xF8\xF1.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp437');
        $document = (new MarkdownReader())->readBytes($bytes, 'cspc8codepage437');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x80\x82\x9A\xB3\xC4\xC5\xDA\xE0\xE1\xF1\xF8\xFE\xFF", 'ibm437');

        $t->same('ibm437', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# DOS 437\n\nBox ╔═╗║╠; résumé; αß °±.", $decoded['text']);
        $t->same("ÇéÜ│─┼┌αß±°■\u{00A0}", $specials['text']);
        $t->same('ibm437', $specials['encoding']);
        $t->same(['encoding' => 'ibm437', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('DOS 437', $document->children[0]->attr('text'));
        $t->same('Box ╔═╗║╠; résumé; αß °±.', $document->children[1]->attr('text'));
        $t->same(25, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(36, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="dos-437">DOS 437</h1>', $blocks);
        $t->contains('<p>Box ╔═╗║╠; résumé; αß °±.</p>', $blocks);
    },
    'decodes ibm850 dos western european source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# DOS 850\n\nEspa\xA4ol Fran\x87ais; \xB5rvore e \xD5zmir; fractions \xAB\xAC\xF3; box \xC9\xCD\xBB; \xF2.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp850');
        $document = (new MarkdownReader())->readBytes($bytes, 'cspc850multilingual');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x9E\xB5\xD5\xDD\xE7\xE8\xF0\xF2\xF3\xFE\xFF", 'ibm850');
        $ibm437Comparison = UnicodeText::decodeBytes("\xB5\xD5\xF2\xF3", 'ibm437');

        $t->same('ibm850', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# DOS 850\n\nEspañol Français; Árvore e ızmir; fractions ½¼¾; box ╔═╗; ‗.", $decoded['text']);
        $t->same("×Áı¦þÞ\u{00AD}‗¾■\u{00A0}", $specials['text']);
        $t->same('╡╒≥≤', $ibm437Comparison['text']);
        $t->same(['encoding' => 'ibm850', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('DOS 850', $document->children[0]->attr('text'));
        $t->same('Español Français; Árvore e ızmir; fractions ½¼¾; box ╔═╗; ‗.', $document->children[1]->attr('text'));
        $t->same(60, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(67, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="dos-850">DOS 850</h1>', $blocks);
        $t->contains('<p>Español Français; Árvore e ızmir; fractions ½¼¾; box ╔═╗; ‗.</p>', $blocks);
    },
    'decodes ibm857 dos turkish source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# DOS 857\n\nT\x81rkiye \x98stanbul; \xA6a\xA7, \x9Ei\x9Fli; box \xC9\xCD\xBB; \xF5.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp857');
        $document = (new MarkdownReader())->readBytes($bytes, 'csibm857');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x8D\x98\x9E\x9F\xA6\xA7\xD0\xD1\xE8\xEC\xED", 'ibm857');
        $undefined = UnicodeText::decodeBytes("A\xD5B\xE7C\xF2D", 'dos857');
        $ibm850Comparison = UnicodeText::decodeBytes("\x8D\x98\x9E\x9F\xA6\xA7\xD0\xD1\xE8\xEC\xED", 'ibm850');

        $t->same('ibm857', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# DOS 857\n\nTürkiye İstanbul; Ğağ, Şişli; box ╔═╗; §.", $decoded['text']);
        $t->same('ıİŞşĞğºª×ìÿ', $specials['text']);
        $t->same('ibm857', $specials['encoding']);
        $t->same(0, $specials['repairs']);
        $t->same("A\u{FFFD}B\u{FFFD}C\u{FFFD}D", $undefined['text']);
        $t->same(3, $undefined['repairs']);
        $t->same('ìÿ×ƒªºðÐÞýÝ', $ibm850Comparison['text']);
        $t->same(['encoding' => 'ibm857', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('DOS 857', $document->children[0]->attr('text'));
        $t->same('Türkiye İstanbul; Ğağ, Şişli; box ╔═╗; §.', $document->children[1]->attr('text'));
        $t->same(41, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(46, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="dos-857">DOS 857</h1>', $blocks);
        $t->contains('<p>Türkiye İstanbul; Ğağ, Şişli; box ╔═╗; §.</p>', $blocks);
    },
    'decodes ibm862 dos hebrew source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# DOS 862\n\nHebrew \x92\x81\x98\x89\x9A: \x99\x8C\x85\x8D \x8E\x97\x85\x98; box \xC9\xCD\xBB; Latin \xA0\xA1.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp862');
        $document = (new MarkdownReader())->readBytes($bytes, 'csibm862');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8A\x8B\x8C\x8D\x8E\x8F\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9A\x9B\x9C\x9D\x9E\x9F", 'ibm862');
        $ibm437Comparison = UnicodeText::decodeBytes("\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8A\x8B\x8C\x8D\x8E\x8F\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9A", 'ibm437');

        $t->same('ibm862', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# DOS 862\n\nHebrew עברית: שלום מקור; box ╔═╗; Latin áí.", $decoded['text']);
        $t->same('אבגדהוזחטיךכלםמןנסעףפץצקרשת¢£¥₧ƒ', $specials['text']);
        $t->same('ibm862', $specials['encoding']);
        $t->same(0, $specials['repairs']);
        $t->same('ÇüéâäàåçêëèïîìÄÅÉæÆôöòûùÿÖÜ', $ibm437Comparison['text']);
        $t->same(['encoding' => 'ibm862', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('DOS 862', $document->children[0]->attr('text'));
        $t->same('Hebrew עברית: שלום מקור; box ╔═╗; Latin áí.', $document->children[1]->attr('text'));
        $t->same(43, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(48, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="dos-862">DOS 862</h1>', $blocks);
        $t->contains('<p>Hebrew עברית: שלום מקור; box ╔═╗; Latin áí.</p>', $blocks);
    },
    'decodes ibm864 dos arabic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# DOS 864\n\nArabic \xC7\xE4\xDF\xD1\xC8\xEA\xC9; digits \xB1\xB2\xB3; lam-alef \x9D\x9E; marks \xF0\xF1; box \x8D\x85\x8C; soft\xA1hyphen.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp864');
        $document = (new MarkdownReader())->readBytes($bytes, 'csibm864');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x99\x9A\x9D\x9E\xA1\xA2\xA5\xAC\xB0\xB1\xB2\xB3\xB4\xB5\xB6\xB7\xB8\xB9\xBA\xBB\xBF\xC1\xE0\xF0\xF1\xF2\xFE", 'ibm864');
        $undefined = UnicodeText::decodeBytes("A\x9BB\x9CC\x9FD\xA6E\xA7F\xFFG", 'dos864');
        $body = "Arabic \u{FE8D}\u{FEDF}\u{FEC9}\u{FEAD}\u{FE91}\u{FEF3}\u{FE93}; digits \u{0661}\u{0662}\u{0663}; lam-alef \u{FEFB}\u{FEFC}; marks \u{FE7D}\u{0651}; box ┌─┐; soft\u{00AD}hyphen.";

        $t->same('ibm864', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# DOS 864\n\n{$body}", $decoded['text']);
        $t->same("\u{FEF7}\u{FEF8}\u{FEFB}\u{FEFC}\u{00AD}\u{FE82}\u{FE84}\u{060C}\u{0660}\u{0661}\u{0662}\u{0663}\u{0664}\u{0665}\u{0666}\u{0667}\u{0668}\u{0669}\u{FED1}\u{061B}\u{061F}\u{FE80}\u{0640}\u{FE7D}\u{0651}\u{FEE5}\u{25A0}", $specials['text']);
        $t->same(0, $specials['repairs']);
        $t->same(25, UnicodeText::displayWidth($specials['text']));
        $t->same(26, UnicodeText::displayWidth($specials['text'], 'wide'));
        $t->same("A\u{FFFD}B\u{FFFD}C\u{FFFD}D\u{FFFD}E\u{FFFD}F\u{FFFD}G", $undefined['text']);
        $t->same(6, $undefined['repairs']);
        $t->same(['encoding' => 'ibm864', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('DOS 864', $document->children[0]->attr('text'));
        $t->same($body, $document->children[1]->attr('text'));
        $t->same(70, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(73, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="dos-864">DOS 864</h1>', $blocks);
        $t->contains("<p>{$body}</p>", $blocks);
    },
    'decodes cp165 dos arabic variant source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# CP165 Arabic\n\nArabic \xC7\xE4\xDF\xD1\xC8\xEA\xC9; percent \x2420; lam-alef \x9D\x9E; extras \x9B\x9C\x9F\xA6\xA7\xFF.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp165');
        $document = (new MarkdownReader())->readBytes($bytes, 'csibm165');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x24\x9B\x9C\x9F\xA6\xA7\xFF", 'ibm165');
        $ibm864Comparison = UnicodeText::decodeBytes("\x24\x9B\x9C\x9F\xA6\xA7\xFF", 'ibm864');
        $body = "Arabic \u{FE8D}\u{FEDF}\u{FEC9}\u{FEAD}\u{FE91}\u{FEF3}\u{FE93}; percent \u{066A}20; lam-alef \u{FEFB}\u{FEFC}; extras \u{FEF9}\u{FEFA}\u{FE73}\u{FE87}\u{FE88}\u{00A0}.";

        $t->same('cp165', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# CP165 Arabic\n\n{$body}", $decoded['text']);
        $t->same("\u{066A}\u{FEF9}\u{FEFA}\u{FE73}\u{FE87}\u{FE88}\u{00A0}", $specials['text']);
        $t->same(0, $specials['repairs']);
        $t->same('$������', $ibm864Comparison['text']);
        $t->same(6, $ibm864Comparison['repairs']);
        $t->same(['encoding' => 'cp165', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('CP165 Arabic', $document->children[0]->attr('text'));
        $t->same($body, $document->children[1]->attr('text'));
        $t->same(56, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(56, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="cp165-arabic">CP165 Arabic</h1>', $blocks);
        $t->contains("<p>{$body}</p>", $blocks);
    },
    'decodes ibm852 dos central european source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# DOS 852\n\nCzech \xAC\x9F \xB7\xD8 \xE6\xE7 \xA6\xA7 \xFC\xFD; Polish \x9D\x88 \xA4\xA5 \xBD\xBE; Hungarian \x8A\x8B \xEB\xFB; box \xC9\xCD\xBB; \xF1.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp852');
        $document = (new MarkdownReader())->readBytes($bytes, 'cspc852');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x85\x86\x88\x8A\x8B\x91\x92\x95\x96\xA4\xA5\xA6\xA7\xAC\xB7\xE6\xE7\xEB\xF0\xF1\xFC\xFD\xFF", 'ibm852');
        $ibm850Comparison = UnicodeText::decodeBytes("\x85\xA6\xAC\xEB\xFC", 'ibm850');

        $t->same('ibm852', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# DOS 852\n\nCzech Čč Ěě Šš Žž Řř; Polish Łł Ąą Żż; Hungarian Őő Űű; box ╔═╗; ˝.", $decoded['text']);
        $t->same("ůćłŐőĹĺĽľĄąŽžČĚŠšŰ\u{00AD}˝Řř\u{00A0}", $specials['text']);
        $t->same(22, UnicodeText::displayWidth($specials['text']));
        $t->same('àª¼Ù³', $ibm850Comparison['text']);
        $t->same(['encoding' => 'ibm852', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('DOS 852', $document->children[0]->attr('text'));
        $t->same('Czech Čč Ěě Šš Žž Řř; Polish Łł Ąą Żż; Hungarian Őő Űű; box ╔═╗; ˝.', $document->children[1]->attr('text'));
        $t->same(67, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(74, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="dos-852">DOS 852</h1>', $blocks);
        $t->contains('<p>Czech Čč Ěě Šš Žž Řř; Polish Łł Ąą Żż; Hungarian Őő Űű; box ╔═╗; ˝.</p>', $blocks);
    },
    'decodes ibm860 dos portuguese source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Importa\x87\x84o\n\nPortugu\x88s: Conte\xA3do, \x8Cnibus, S\x84o Tom\x82, a\x87\xA3car; \xAEcita\x87\x84o\xAF; \x9C/\x9E.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp860');
        $document = (new MarkdownReader())->readBytes($bytes, 'csibm860');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x84\x86\x8C\x8E\x9D\xA9\xAE\xAF", 'ibm860');
        $ibm437Comparison = UnicodeText::decodeBytes("\x84\x86\x8C\x8E\x9D\xA9", 'ibm437');

        $t->same('ibm860', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Importação\n\nPortuguês: Conteúdo, Ônibus, São Tomé, açúcar; «citação»; £/₧.", $decoded['text']);
        $t->same('ãÁÔÃÙÒ«»', $specials['text']);
        $t->same('äåîÄ¥⌐', $ibm437Comparison['text']);
        $t->same(['encoding' => 'ibm860', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Importação', $document->children[0]->attr('text'));
        $t->same('Português: Conteúdo, Ônibus, São Tomé, açúcar; «citação»; £/₧.', $document->children[1]->attr('text'));
        $t->same(62, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="importação">Importação</h1>', $blocks);
        $t->contains('<p>Português: Conteúdo, Ônibus, São Tomé, açúcar; «citação»; £/₧.</p>', $blocks);
    },
    'decodes ibm861 dos icelandic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# DOS 861\n\nIcelandic: \xA4\xA1 \xA5sland, \x8Dingvellir, \x8B/\x8C, \x95orn; vowels \xA0\xA1\xA2\xA3 \xA4\xA5\xA6\xA7; box \xC9\xCD\xBB; \x9C.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp861');
        $document = (new MarkdownReader())->readBytes($bytes, 'csibm861');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x8B\x8C\x8D\x8E\x95\x97\x98\xA4\xA5\xA6\xA7", 'ibm861');
        $ibm437Comparison = UnicodeText::decodeBytes("\x8B\x8C\x8D\x8E\x95\x97\x98\xA4\xA5\xA6\xA7", 'ibm437');

        $t->same('ibm861', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# DOS 861\n\nIcelandic: Áí Ísland, Þingvellir, Ð/ð, þorn; vowels áíóú ÁÍÓÚ; box ╔═╗; £.", $decoded['text']);
        $t->same('ÐðÞÄþÝýÁÍÓÚ', $specials['text']);
        $t->same('ïîìÄòùÿñÑªº', $ibm437Comparison['text']);
        $t->same(['encoding' => 'ibm861', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('DOS 861', $document->children[0]->attr('text'));
        $t->same('Icelandic: Áí Ísland, Þingvellir, Ð/ð, þorn; vowels áíóú ÁÍÓÚ; box ╔═╗; £.', $document->children[1]->attr('text'));
        $t->same(74, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="dos-861">DOS 861</h1>', $blocks);
        $t->contains('<p>Icelandic: Áí Ísland, Þingvellir, Ð/ð, þorn; vowels áíóú ÁÍÓÚ; box ╔═╗; £.</p>', $blocks);
    },
    'decodes ibm865 dos nordic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# DOS 865\n\nDansk: K\x9Bbenhavn, sm\x9Brrebr\x9Bd, bl\x86b\x91r; Norsk: \x92\x9D\x8F; Islandsk: \xD1\xD0 \xE8\xE7; \xAF.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp865');
        $document = (new MarkdownReader())->readBytes($bytes, 'csibm865');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x86\x8F\x91\x92\x9B\x9D\xD0\xD1\xE7\xE8\xAF", 'ibm865');
        $ibm850Comparison = UnicodeText::decodeBytes("\xAF", 'ibm850');

        $t->same('ibm865', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# DOS 865\n\nDansk: København, smørrebrød, blåbær; Norsk: ÆØÅ; Islandsk: Ðð Þþ; ¤.", $decoded['text']);
        $t->same('åÅæÆøØðÐþÞ¤', $specials['text']);
        $t->same('»', $ibm850Comparison['text']);
        $t->same(['encoding' => 'ibm865', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('DOS 865', $document->children[0]->attr('text'));
        $t->same('Dansk: København, smørrebrød, blåbær; Norsk: ÆØÅ; Islandsk: Ðð Þþ; ¤.', $document->children[1]->attr('text'));
        $t->same(69, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(80, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="dos-865">DOS 865</h1>', $blocks);
        $t->contains('<p>Dansk: København, smørrebrød, blåbær; Norsk: ÆØÅ; Islandsk: Ðð Þþ; ¤.</p>', $blocks);
    },
    'decodes ibm775 dos baltic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# DOS 775\n\nBaltic \xA0\x83 \xED\x89 \xA1\x8C \xE2\x93; Latvian \x95\x85 \xE8\xE9 \xEA\xEB \xEE\xEC; Lithuanian \xB5\xD0 \xB6\xD1 \xB7\xD2 \xB8\xD3 \xBD\xD4 \xBE\xD5 \xC6\xD6 \xC7\xD7 \xCF\xD8; quotes \xF2avots\xA6 \xF7zems\xA6; box \xC9\xCD\xBB; soft\xF0hyphen\xFFtail.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp775');
        $document = (new MarkdownReader())->readBytes($bytes, 'csibm775');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x80\x83\x85\x8A\x8B\x8C\x95\xA0\xA1\xA3\xA4\xA5\xAD\xB5\xB6\xB7\xB8\xBD\xBE\xC6\xC7\xCF\xD0\xD1\xD2\xD3\xD4\xD5\xD6\xD7\xD8\xE0\xE2\xE3\xE7\xE8\xE9\xEA\xEB\xEC\xED\xEE\xEF\xF0\xF2\xF7\xFF", 'ibm775');
        $ibm850Comparison = UnicodeText::decodeBytes("\x80\x8A\x8B\xA0\xB5\xCF\xD0\xEF\xF7\xFF", 'ibm850');

        $t->same('ibm775', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# DOS 775\n\nBaltic Āā Ēē Īī Ōō; Latvian Ģģ Ķķ Ļļ Ņņ; Lithuanian Ąą Čč Ęę Ėė Įį Šš Ųų Ūū Žž; quotes “avots” „zems”; box ╔═╗; soft\u{00AD}hyphen\u{00A0}tail.", $decoded['text']);
        $t->same("ĆāģŖŗīĢĀĪŻżźŁĄČĘĖĮŠŲŪŽąčęėįšųūžÓŌŃńĶķĻļņĒŅ’\u{00AD}“„\u{00A0}", $specials['text']);
        $t->same('ÇèïáÁ¤ð´¸ ', $ibm850Comparison['text']);
        $t->same(0, UnicodeText::displayWidth("\u{00AD}"));
        $t->same(1, UnicodeText::displayWidth("\u{00A0}"));
        $t->same(['encoding' => 'ibm775', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('DOS 775', $document->children[0]->attr('text'));
        $t->same("Baltic Āā Ēē Īī Ōō; Latvian Ģģ Ķķ Ļļ Ņņ; Lithuanian Ąą Čč Ęę Ėė Įį Šš Ųų Ūū Žž; quotes “avots” „zems”; box ╔═╗; soft\u{00AD}hyphen\u{00A0}tail.", $document->children[1]->attr('text'));
        $t->same(128, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(139, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="dos-775">DOS 775</h1>', $blocks);
        $t->contains("<p>Baltic Āā Ēē Īī Ōō; Latvian Ģģ Ķķ Ļļ Ņņ; Lithuanian Ąą Čč Ęę Ėė Įį Šš Ųų Ūū Žž; quotes “avots” „zems”; box ╔═╗; soft\u{00AD}hyphen\u{00A0}tail.</p>", $blocks);
    },
    'decodes ibm863 dos canadian french source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# DOS 863\n\nQu\x82bec H\x93tel; co\x96t; \x90t\x82; fractions \xAB\xAC\xAD; monnaie \x9B\x9C\x98; box \xC9\xCD\xBB; \x8D.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp863');
        $document = (new MarkdownReader())->readBytes($bytes, 'csibm863');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x84\x86\x8D\x8E\x90\x95\x98\x9D\x9E\xA0\xA7\xA8\xAB\xAC\xAD", 'ibm863');
        $ibm437Comparison = UnicodeText::decodeBytes("\x84\x86\x8D\x8E\x95\x98\x9D\x9E\xA0\xA7\xA8", 'ibm437');

        $t->same('ibm863', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# DOS 863\n\nQuébec Hôtel; coût; Été; fractions ½¼¾; monnaie ¢£¤; box ╔═╗; ‗.", $decoded['text']);
        $t->same("Â¶‗ÀÉÏ¤ÙÛ¦¯Î½¼¾", $specials['text']);
        $t->same('äåìÄòÿ¥₧áº¿', $ibm437Comparison['text']);
        $t->same(['encoding' => 'ibm863', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('DOS 863', $document->children[0]->attr('text'));
        $t->same('Québec Hôtel; coût; Été; fractions ½¼¾; monnaie ¢£¤; box ╔═╗; ‗.', $document->children[1]->attr('text'));
        $t->same(64, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(73, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="dos-863">DOS 863</h1>', $blocks);
        $t->contains('<p>Québec Hôtel; coût; Été; fractions ½¼¾; monnaie ¢£¤; box ╔═╗; ‗.</p>', $blocks);
    },
    'decodes iso 8859 5 cyrillic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xB8\xDC\xDF\xDE\xE0\xE2\n\n\xC0\xD5\xD4\xD0\xDA\xE2\xDE\xE0 \xDF\xE0\xD8\xD2\xD5\xE2; \xA1\xDB\xDA\xD0 \xF0 7.";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso-ir-144');
        $document = (new MarkdownReader())->readBytes($bytes, 'csisolatincyrillic');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xF1\xF2\xF3\xF4\xF5\xF6\xF7\xF8\xF9\xFA\xFB\xFC\xFD\xFE\xFF", 'iso-8859-5');
        $softHyphen = UnicodeText::decodeBytes("A\xADB", 'cyrillic');

        $t->same('iso-8859-5', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Импорт\n\nРедактор привет; Ёлка № 7.", $decoded['text']);
        $t->same("ёђѓєѕіїјљњћќ§ўџ", $specials['text']);
        $t->same("A\u{00AD}B", $softHyphen['text']);
        $t->same(2, UnicodeText::displayWidth($softHyphen['text']));
        $t->same(['encoding' => 'iso-8859-5', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Импорт', $document->children[0]->attr('text'));
        $t->same('Редактор привет; Ёлка № 7.', $document->children[1]->attr('text'));
        $t->same(26, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="импорт">Импорт</h1>', $blocks);
        $t->contains('<p>Редактор привет; Ёлка № 7.</p>', $blocks);
    },
    'decodes iso 8859 6 arabic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xC7\xE4\xD9\xD1\xC8\xEA\xC9\n\n\xE5\xCD\xD1\xD1 \xD9\xD1\xC8\xEA\xC9\xAC \xD3\xC4\xC7\xE4\xBB \xE7\xE4\xBF";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso-ir-127');
        $document = (new MarkdownReader())->readBytes($bytes, 'arabic');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA0\xA4\xAC\xAD\xBB\xBF\xC1\xE0\xEB\xEC\xED\xEE\xEF\xF0\xF1\xF2", 'iso-8859-6');
        $undefined = UnicodeText::decodeBytes("A\xA1B\xBAC\xDBD\xFFE", 'iso-8859-6');

        $t->same('iso-8859-6', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# العربية\n\nمحرر عربية، سؤال؛ هل؟", $decoded['text']);
        $t->same("\u{00A0}¤،\u{00AD}؛؟ءـًٌٍَُِّْ", $specials['text']);
        $t->same(7, UnicodeText::displayWidth($specials['text']));
        $t->same("A\u{FFFD}B\u{FFFD}C\u{FFFD}D\u{FFFD}E", $undefined['text']);
        $t->same(4, $undefined['repairs']);
        $t->same(['encoding' => 'iso-8859-6', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('العربية', $document->children[0]->attr('text'));
        $t->same('محرر عربية، سؤال؛ هل؟', $document->children[1]->attr('text'));
        $t->same(21, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="العربية">العربية</h1>', $blocks);
        $t->contains('<p>محرر عربية، سؤال؛ هل؟</p>', $blocks);
    },
    'decodes windows 1256 arabic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xC7\xE1\xDA\xD1\xC8\xED\xC9\n\n\xE3\xCD\xD1\xD1 \x93\xDA\xD1\xC8\xED\xC9\x94 \x97 \x8020; \xDD\xC7\xD1\xD3\xED: \x81\x8D\x8E\x90 \x98\xBA \xC7\xD1\xCF\xE6: \x9A\x9F\xFF.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp1256');
        $document = (new MarkdownReader())->readBytes($bytes, 'microsoft-cp1256');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x81\x8A\x8D\x8E\x8F\x90\x98\x9A\x9D\x9E\x9F\xAA\xBA\xBF\xC0\xF0\xF1\xF2\xF3\xF5\xF6\xF8\xFA\xFD\xFE\xFF", 'windows-1256');

        $t->same('windows-1256', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# العربية\n\nمحرر “عربية” — €20; فارسي: پچژگ ک؛ اردو: ڑںے.", $decoded['text']);
        $t->same("پٹچژڈگکڑ\u{200C}\u{200D}ںھ؛؟ہًٌٍَُِّْ\u{200E}\u{200F}ے", $specials['text']);
        $t->same(0, $specials['repairs']);
        $t->same(14, UnicodeText::displayWidth($specials['text']));
        $t->same(['encoding' => 'windows-1256', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('العربية', $document->children[0]->attr('text'));
        $t->same('محرر “عربية” — €20; فارسي: پچژگ ک؛ اردو: ڑںے.', $document->children[1]->attr('text'));
        $t->same(45, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="العربية">العربية</h1>', $blocks);
        $t->contains('<p>محرر “عربية” — €20; فارسي: پچژگ ک؛ اردو: ڑںے.</p>', $blocks);
    },
    'decodes mac arabic source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Mac Arabic\n\nLegacy \xD9\xD1\xC8\xEA\xC9 \x8C\xCE\xC8\xD1\x98 \xAD \xA520; Persian \xF3\xF5\xF7\xF8; digits \xB1\xB2\xB3; punctuation \xAC\xBB\xBF.";
        $decoded = UnicodeText::decodeBytes($bytes, 'x-mac-arabic');
        $document = (new MarkdownReader())->readBytes($bytes, 'macarabic');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x81\x8B\x8C\x93\x98\x9B\xA5\xAC\xB0\xB1\xB2\xB3\xBB\xBF\xC0\xC1\xD9\xEA\xEB\xF0\xF1\xF2\xF3\xF5\xF7\xF8\xFE\xFF", 'mac-arabic');
        $body = "Legacy عربية «خبر» - ٪20; Persian پچڤگ; digits ١٢٣; punctuation ،؛؟.";

        $t->same('mac-arabic', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Mac Arabic\n\n{$body}", $decoded['text']);
        $t->same("\u{00A0}ں«…»÷٪،٠١٢٣؛؟❊ءعي\u{064B}\u{0650}\u{0651}\u{0652}پچڤگژے", $specials['text']);
        $t->same(0, $specials['repairs']);
        $t->same(24, UnicodeText::displayWidth($specials['text']));
        $t->same(['encoding' => 'mac-arabic', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Mac Arabic', $document->children[0]->attr('text'));
        $t->same($body, $document->children[1]->attr('text'));
        $t->same(68, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="mac-arabic">Mac Arabic</h1>', $blocks);
        $t->contains("<p>{$body}</p>", $blocks);
    },
    'decodes windows 1258 vietnamese combining tone bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Ti\xEA\xECng Vi\xEA\xF2t\n\nGia \xFE 20; \xD0\xF4\xECng \xFDu ti\xEAn: \xD5\xD2, \xFD\xDE, a\xCC, e\xF2.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp1258');
        $document = (new MarkdownReader())->readBytes($bytes, 'microsoft-cp1258');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xC3\xCC\xD0\xD2\xD5\xDD\xDE\xEC\xF0\xF2\xF5\xFD\xFE", 'windows-1258');
        $controls = UnicodeText::decodeBytes("\x8A\x8E\x9A\x9E", 'windows-1258');
        $wrapped = UnicodeText::wrapByDisplayWidth("Audit Ti\u{00EA}\u{0301}ng Vi\u{00EA}\u{0323}t tail", 12, '  ');

        $t->same('windows-1258', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Ti\u{00EA}\u{0301}ng Vi\u{00EA}\u{0323}t\n\nGia \u{20AB} 20; \u{0110}\u{00F4}\u{0301}ng \u{01B0}u ti\u{00EA}n: \u{01A0}\u{0309}, \u{01B0}\u{0303}, a\u{0300}, e\u{0323}.", $decoded['text']);
        $t->same("\u{0102}\u{0300}\u{0110}\u{0309}\u{01A0}\u{01AF}\u{0303}\u{0301}\u{0111}\u{0323}\u{01A1}\u{01B0}\u{20AB}", $specials['text']);
        $t->same(0, $specials['repairs']);
        $t->same("\u{008A}\u{008E}\u{009A}\u{009E}", $controls['text']);
        $t->same(0, UnicodeText::displayWidth($controls['text']));
        $t->same(['encoding' => 'windows-1258', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same("Ti\u{00EA}\u{0301}ng Vi\u{00EA}\u{0323}t", $document->children[0]->attr('text'));
        $t->same("Gia \u{20AB} 20; \u{0110}\u{00F4}\u{0301}ng \u{01B0}u ti\u{00EA}n: \u{01A0}\u{0309}, \u{01B0}\u{0303}, a\u{0300}, e\u{0323}.", $document->children[1]->attr('text'));
        $t->same(35, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(["Audit Ti\u{00EA}\u{0301}ng", "  Vi\u{00EA}\u{0323}t tail"], $wrapped);
        $t->contains("<p>Gia \u{20AB} 20; \u{0110}\u{00F4}\u{0301}ng \u{01B0}u ti\u{00EA}n: \u{01A0}\u{0309}, \u{01B0}\u{0303}, a\u{0300}, e\u{0323}.</p>", $blocks);
    },
    'detects declared html and xml charset labels before byte decoding' => static function (TestRunner $t): void {
        $transport = UnicodeText::declaredCharset('<meta charset=windows-1258><p>x</p>', 'text/html; charset="windows-1257"');
        $html = "<!doctype html><meta charset=windows-1258><p>Ti\xEA\xECng Vi\xEA\xF2t</p>";
        $meta = UnicodeText::declaredCharset($html);
        $decodedHtml = UnicodeText::decodeBytes($html, $meta['encoding']);
        $httpEquivBytes = '<meta http-equiv="content-type" content="text/html; charset=iso-ir-199"><p>Celtic ' . "\xD0\xF0" . '</p>';
        $httpEquiv = UnicodeText::declaredCharset($httpEquivBytes);
        $decodedHttpEquiv = UnicodeText::decodeBytes($httpEquivBytes, $httpEquiv['encoding']);
        $xmlBytes = "<?xml version=\"1.0\" encoding=\"ISO-8859-7\"?><root>\xD3\xF5\xED</root>";
        $xml = UnicodeText::declaredCharset($xmlBytes);
        $decodedXml = UnicodeText::decodeBytes($xmlBytes, $xml['encoding']);
        $unknown = UnicodeText::declaredCharset('<meta charset="x-fallback-only"><p>raw</p>');
        $none = UnicodeText::declaredCharset('<p>No declaration</p>');

        $t->same('content-type', $transport['source']);
        $t->same('windows-1257', $transport['label']);
        $t->same('windows-1257', $transport['encoding']);
        $t->same([], $transport['diagnostics']);
        $t->true(is_int($transport['offset']) && $transport['offset'] > 0, 'transport charset offset should point into the header value');
        $t->same('html-meta-charset', $meta['source']);
        $t->same('windows-1258', $meta['encoding']);
        $t->same([], $meta['diagnostics']);
        $t->contains("Ti\u{00EA}\u{0301}ng Vi\u{00EA}\u{0323}t", $decodedHtml['text']);
        $t->same(10, UnicodeText::displayWidth("Ti\u{00EA}\u{0301}ng Vi\u{00EA}\u{0323}t"));
        $t->same('html-meta-http-equiv', $httpEquiv['source']);
        $t->same('iso-ir-199', $httpEquiv['label']);
        $t->same('iso-8859-14', $httpEquiv['encoding']);
        $t->contains('Celtic Ŵŵ', $decodedHttpEquiv['text']);
        $t->same('xml-declaration', $xml['source']);
        $t->same('iso-8859-7', $xml['encoding']);
        $t->contains('Συν', $decodedXml['text']);
        $t->same('utf-8', $unknown['encoding']);
        $t->same('x-fallback-only', $unknown['label']);
        $t->same(['unknown-charset-label-defaulted-to-utf-8'], $unknown['diagnostics']);
        $t->same(['encoding' => null, 'label' => null, 'source' => null, 'offset' => null, 'diagnostics' => []], $none);
    },
    'detects unquoted html pragma charset content with mime slashes' => static function (TestRunner $t): void {
        $html = '<meta http-equiv=Content-Type content=text/html;charset=windows-1252><p>Editor ' . "\x93quoted\x94" . '</p>';
        $selfClosing = '<meta charset=utf-8/><p>Native text</p>';
        $meta = UnicodeText::declaredCharset($html);
        $decoded = UnicodeText::decodeBytes($html, $meta['encoding']);
        $closed = UnicodeText::declaredCharset($selfClosing);

        $t->same('html-meta-http-equiv', $meta['source']);
        $t->same('windows-1252', $meta['label']);
        $t->same('windows-1252', $meta['encoding']);
        $t->same([], $meta['diagnostics']);
        $t->contains("Editor \u{201C}quoted\u{201D}", $decoded['text']);
        $t->same('html-meta-charset', $closed['source']);
        $t->same('utf-8', $closed['label']);
        $t->same('utf-8', $closed['encoding']);
        $t->same([], $closed['diagnostics']);
    },
    'detects unicode byte order marks before declared charset labels' => static function (TestRunner $t) use ($utf32le): void {
        $utf8 = UnicodeText::declaredCharset("\xEF\xBB\xBF<meta charset=windows-1252><p>x</p>", 'text/html; charset=windows-1252');
        $utf8Matched = UnicodeText::declaredCharset("\xEF\xBB\xBF<meta charset=utf-8><p>x</p>", 'text/html; charset=UTF-8');
        $utf16 = UnicodeText::declaredCharset("\xFE\xFF\x00<\x00?\x00x\x00m\x00l encoding=\"windows-1252\"?>");
        $utf32 = UnicodeText::declaredCharset("\xFF\xFE\x00\x00" . $utf32le([0x003c, 0x006d, 0x0065, 0x0074, 0x0061]));
        $decoded = UnicodeText::decodeBytes("\xEF\xBB\xBF# Bom\n\nSource", $utf8['encoding']);

        $t->same('byte-order-mark', $utf8['source']);
        $t->same('utf-8', $utf8['label']);
        $t->same('utf-8', $utf8['encoding']);
        $t->same(0, $utf8['offset']);
        $t->same([
            'ignored-content-type-charset:windows-1252',
            'ignored-html-meta-charset:windows-1252',
        ], $utf8['diagnostics']);
        $t->same('byte-order-mark', $utf8Matched['source']);
        $t->same('utf-8', $utf8Matched['encoding']);
        $t->same('utf-8', $utf8Matched['label']);
        $t->same(0, $utf8Matched['offset']);
        $t->same([], $utf8Matched['diagnostics']);
        $t->same('byte-order-mark', $utf16['source']);
        $t->same('utf-16be', $utf16['encoding']);
        $t->same('utf-16be', $utf16['label']);
        $t->same('byte-order-mark', $utf32['source']);
        $t->same('utf-32le', $utf32['encoding']);
        $t->same('utf-32le', $utf32['label']);
        $t->same("# Bom\n\nSource", $decoded['text']);
        $t->same('utf-8', $decoded['encoding']);
        $t->same('utf-8', $decoded['bom']);
    },
    'decodes x user defined private use bytes from declared html charset labels into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Private Glyphs\n\nLegacy \x80\x81\xFE\xFF source.";
        $decoded = UnicodeText::decodeBytes($bytes, 'x-user-defined');
        $document = (new MarkdownReader())->readBytes($bytes, 'x-user-defined');
        $blocks = (new WordPressBlockWriter())->write($document);
        $html = '<meta charset=x-user-defined><p>Legacy ' . "\x80\xFF" . '</p>';
        $meta = UnicodeText::declaredCharset($html);
        $decodedHtml = UnicodeText::decodeBytes($html, $meta['encoding']);
        $text = "Legacy \u{F780}\u{F781}\u{F7FE}\u{F7FF} source.";

        $t->same('x-user-defined', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Private Glyphs\n\n{$text}", $decoded['text']);
        $t->same(['encoding' => 'x-user-defined', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Private Glyphs', $document->children[0]->attr('text'));
        $t->same($text, $document->children[1]->attr('text'));
        $t->same(19, UnicodeText::displayWidth($text));
        $t->same(23, UnicodeText::displayWidth($text, 'wide'));
        $t->same('html-meta-charset', $meta['source']);
        $t->same('x-user-defined', $meta['encoding']);
        $t->same([], $meta['diagnostics']);
        $t->contains("Legacy \u{F780}\u{F7FF}", $decodedHtml['text']);
        $t->contains('<h1 id="private-glyphs">Private Glyphs</h1>', $blocks);
        $t->contains("<p>{$text}</p>", $blocks);
    },
    'decodes iso 8859 7 greek source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xC5\xEB\xEB\xE7\xED\xE9\xEA\xDC\n\n\xD3\xF5\xED\xF4\xDC\xEA\xF4\xE7\xF2 \xAB\xEA\xE5\xDF\xEC\xE5\xED\xEF\xBB \xAF \xA420; \xD4\xFC\xED\xEF\xF2 \xEA\xE1\xE9 \xEF\xF2.";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso-ir-126');
        $document = (new MarkdownReader())->readBytes($bytes, 'greek');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xB4\xB5\xB6\xB8\xB9\xBA\xBC\xBE\xBF\xC0\xE0", 'iso-8859-7');
        $undefined = UnicodeText::decodeBytes("A\xAEB\xD2C\xFFD", 'iso-8859-7');

        $t->same('iso-8859-7', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Ελληνικά\n\nΣυντάκτης «κείμενο» ― €20; Τόνος και ος.", $decoded['text']);
        $t->same("΄΅ΆΈΉΊΌΎΏΐΰ", $specials['text']);
        $t->same("A\u{FFFD}B\u{FFFD}C\u{FFFD}D", $undefined['text']);
        $t->same(3, $undefined['repairs']);
        $t->same(['encoding' => 'iso-8859-7', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Ελληνικά', $document->children[0]->attr('text'));
        $t->same('Συντάκτης «κείμενο» ― €20; Τόνος και ος.', $document->children[1]->attr('text'));
        $t->same(40, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(62, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="ελληνικά">Ελληνικά</h1>', $blocks);
        $t->contains('<p>Συντάκτης «κείμενο» ― €20; Τόνος και ος.</p>', $blocks);
    },
    'decodes windows 1253 greek source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xC5\xEB\xEB\xE7\xED\xE9\xEA\xDC\n\n\xD3\xF5\xED\xF4\xDC\xEA\xF4\xE7\xF2 \x93\xEA\xE5\xDF\xEC\xE5\xED\xEF\x94 \x97 \x8020; \xD4\xFC\xED\xEF\xF2.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp1253');
        $document = (new MarkdownReader())->readBytes($bytes, 'microsoft-cp1253');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\x80\x82\x83\x84\x85\x86\x87\x89\x8B\x91\x92\x93\x94\x95\x96\x97\x99\x9B\xA1\xA2\xAF\xB4\xB8\xB9\xBA\xBC\xBE\xBF\xC0\xE0", 'windows-1253');
        $undefined = UnicodeText::decodeBytes("A\x81B\x88C\x8AD\xD2E\xFFF", 'windows-1253');

        $t->same('windows-1253', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Ελληνικά\n\nΣυντάκτης “κείμενο” — €20; Τόνος.", $decoded['text']);
        $t->same("€‚ƒ„…†‡‰‹‘’“”•–—™›΅Ά―΄ΈΉΊΌΎΏΐΰ", $specials['text']);
        $t->same(0, $specials['repairs']);
        $t->same("A\u{FFFD}B\u{FFFD}C\u{FFFD}D\u{FFFD}E\u{FFFD}F", $undefined['text']);
        $t->same(5, $undefined['repairs']);
        $t->same(['encoding' => 'windows-1253', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Ελληνικά', $document->children[0]->attr('text'));
        $t->same('Συντάκτης “κείμενο” — €20; Τόνος.', $document->children[1]->attr('text'));
        $t->same(33, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(53, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="ελληνικά">Ελληνικά</h1>', $blocks);
        $t->contains('<p>Συντάκτης “κείμενο” — €20; Τόνος.</p>', $blocks);
    },
    'decodes iso 8859 8 hebrew source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xF2\xE1\xF8\xE9\xFA\n\n\xF2\xE5\xF8\xEA \xF2\xE1\xF8\xE9\xFA \xAB\xEE\xF7\xE5\xF8\xBB \xDF 12; \xFERTL.";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso-ir-138');
        $document = (new MarkdownReader())->readBytes($bytes, 'hebrew');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA0\xAA\xBA\xDF\xFD\xFE", 'iso-8859-8');
        $undefined = UnicodeText::decodeBytes("A\xA1B\xBFC\xFBD\xFFE", 'iso-8859-8');

        $t->same('iso-8859-8', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# עברית\n\nעורך עברית «מקור» ‗ 12; \u{200F}RTL.", $decoded['text']);
        $t->same("\u{00A0}×÷‗\u{200E}\u{200F}", $specials['text']);
        $t->same(4, UnicodeText::displayWidth($specials['text']));
        $t->same("A\u{FFFD}B\u{FFFD}C\u{FFFD}D\u{FFFD}E", $undefined['text']);
        $t->same(4, $undefined['repairs']);
        $t->same(['encoding' => 'iso-8859-8', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('עברית', $document->children[0]->attr('text'));
        $t->same("עורך עברית «מקור» ‗ 12; \u{200F}RTL.", $document->children[1]->attr('text'));
        $t->same(28, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="עברית">עברית</h1>', $blocks);
        $t->contains("<p>עורך עברית «מקור» ‗ 12; \u{200F}RTL.</p>", $blocks);
    },
    'decodes windows 1255 hebrew source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xF2\xE1\xF8\xE9\xFA\n\n\xF2\xE5\xF8\xEA \xF9\xC8\xD1\xEC\xE5\xC9\xED \x93\xEE\xF7\xE5\xF8\x94 \x97 \xA420; \xD7\xD8.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp1255');
        $document = (new MarkdownReader())->readBytes($bytes, 'x-cp1255');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA4\xC0\xC8\xCA\xCC\xD1\xD2\xD7\xD8\xFD\xFE", 'windows-1255');
        $undefined = UnicodeText::decodeBytes("A\x81B\x8AC\xD9D\xFBE\xFFF", 'microsoft-cp1255');

        $t->same('windows-1255', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# עברית\n\nעורך שָׁלוֹם “מקור” — ₪20; ׳״.", $decoded['text']);
        $t->same("₪ְָֺּׁׂ׳״\u{200E}\u{200F}", $specials['text']);
        $t->same(3, UnicodeText::displayWidth($specials['text']));
        $t->same("A\u{FFFD}B\u{FFFD}C\u{FFFD}D\u{FFFD}E\u{FFFD}F", $undefined['text']);
        $t->same(5, $undefined['repairs']);
        $t->same(['encoding' => 'windows-1255', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('עברית', $document->children[0]->attr('text'));
        $t->same("עורך שָׁלוֹם “מקור” — ₪20; ׳״.", $document->children[1]->attr('text'));
        $t->same(27, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="עברית">עברית</h1>', $blocks);
        $t->contains('<p>עורך שָׁלוֹם “מקור” — ₪20; ׳״.</p>', $blocks);
    },
    'decodes iso 8859 9 latin5 turkish source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Latin5 Import\n\nTurkish \xDDstanbul, \xD0a\xF0, \xDEi\xFEli, \xFDl\xFDk; \xD6\xDC remain.";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso-ir-148');
        $document = (new MarkdownReader())->readBytes($bytes, 'latin5');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xD0\xDD\xDE\xF0\xFD\xFE", 'csisolatin5');

        $t->same('iso-8859-9', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Latin5 Import\n\nTurkish İstanbul, Ğağ, Şişli, ılık; ÖÜ remain.", $decoded['text']);
        $t->same('ĞİŞğış', $specials['text']);
        $t->same(0, $specials['repairs']);
        $t->same(['encoding' => 'iso-8859-9', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Latin5 Import', $document->children[0]->attr('text'));
        $t->same('Turkish İstanbul, Ğağ, Şişli, ılık; ÖÜ remain.', $document->children[1]->attr('text'));
        $t->same(46, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="latin5-import">Latin5 Import</h1>', $blocks);
        $t->contains('<p>Turkish İstanbul, Ğağ, Şişli, ılık; ÖÜ remain.</p>', $blocks);
    },
    'decodes windows 1254 turkish source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Windows Turkish\n\nYazar \x93\xDDstanbul\x94 \x97 \x8010; \xD0a\xF0, \xDEi\xFEli, \xFDl\xFDk; \xD6\xDC remain.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp1254');
        $document = (new MarkdownReader())->readBytes($bytes, 'microsoft-cp1254');
        $blocks = (new WordPressBlockWriter())->write($document);
        $controls = UnicodeText::decodeBytes("\x80\x82\x83\x84\x85\x86\x87\x88\x89\x8A\x8B\x8C\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9A\x9B\x9C\x9F", 'windows-1254');
        $undefined = UnicodeText::decodeBytes("A\x81B\x8DC\x8ED\x8FE\x90F\x9DG\x9EH", 'windows-1254');

        $t->same('windows-1254', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Windows Turkish\n\nYazar “İstanbul” — €10; Ğağ, Şişli, ılık; ÖÜ remain.", $decoded['text']);
        $t->same('€‚ƒ„…†‡ˆ‰Š‹Œ‘’“”•–—˜™š›œŸ', $controls['text']);
        $t->same(0, $controls['repairs']);
        $t->same("A\u{FFFD}B\u{FFFD}C\u{FFFD}D\u{FFFD}E\u{FFFD}F\u{FFFD}G\u{FFFD}H", $undefined['text']);
        $t->same(7, $undefined['repairs']);
        $t->same(['encoding' => 'windows-1254', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Windows Turkish', $document->children[0]->attr('text'));
        $t->same('Yazar “İstanbul” — €10; Ğağ, Şişli, ılık; ÖÜ remain.', $document->children[1]->attr('text'));
        $t->same(52, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="windows-turkish">Windows Turkish</h1>', $blocks);
        $t->contains('<p>Yazar “İstanbul” — €10; Ğağ, Şişli, ılık; ÖÜ remain.</p>', $blocks);
    },
    'decodes tis 620 thai source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xE4\xB7\xC2\n\n\xE0\xB9\xD7\xE9\xCD\xCB\xD2 \xE0\xCD\xA1\xCA\xD2\xC3.";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso-ir-166');
        $document = (new MarkdownReader())->readBytes($bytes, 'iso-8859-11');
        $blocks = (new WordPressBlockWriter())->write($document);
        $specials = UnicodeText::decodeBytes("\xA0\xA1\xDA\xDF\xE0\xF0\xFB", 'tis-620');
        $undefined = UnicodeText::decodeBytes("A\xDBB\xDEC\xFCD\xFFE", 'thai');

        $t->same('tis-620', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# ไทย\n\nเนื้อหา เอกสาร.", $decoded['text']);
        $t->same("\u{00A0}กฺ฿เ๐๛", $specials['text']);
        $t->same(6, UnicodeText::displayWidth($specials['text']));
        $t->same("A\u{FFFD}B\u{FFFD}C\u{FFFD}D\u{FFFD}E", $undefined['text']);
        $t->same(4, $undefined['repairs']);
        $t->same(['encoding' => 'tis-620', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('ไทย', $document->children[0]->attr('text'));
        $t->same('เนื้อหา เอกสาร.', $document->children[1]->attr('text'));
        $t->same(13, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="ไทย">ไทย</h1>', $blocks);
        $t->contains('<p>เนื้อหา เอกสาร.</p>', $blocks);
    },
    'decodes windows 874 thai source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# \xE4\xB7\xC2 Windows\n\n\xE0\xB9\xD7\xE9\xCD\xCB\xD2 \x93\xE0\xCD\xA1\xCA\xD2\xC3\x94 \x97 \x8020; \x85";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp874');
        $document = (new MarkdownReader())->readBytes($bytes, 'windows-874');
        $blocks = (new WordPressBlockWriter())->write($document);
        $controls = UnicodeText::decodeBytes("\x81\x82\x98\x99\x9F", 'windows-874');
        $undefined = UnicodeText::decodeBytes("A\xDBB\xDEC\xFCD\xFFD", 'windows-874');

        $t->same('windows-874', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# ไทย Windows\n\nเนื้อหา “เอกสาร” — €20; …", $decoded['text']);
        $t->same("\u{0081}\u{0082}\u{0098}\u{0099}\u{009F}", $controls['text']);
        $t->same(0, UnicodeText::displayWidth($controls['text']));
        $t->same("A\u{FFFD}B\u{FFFD}C\u{FFFD}D\u{FFFD}D", $undefined['text']);
        $t->same(4, $undefined['repairs']);
        $t->same(['encoding' => 'windows-874', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('ไทย Windows', $document->children[0]->attr('text'));
        $t->same('เนื้อหา “เอกสาร” — €20; …', $document->children[1]->attr('text'));
        $t->same(23, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="ไทย-windows">ไทย Windows</h1>', $blocks);
        $t->contains('<p>เนื้อหา “เอกสาร” — €20; …</p>', $blocks);
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
    'decodes euc jp jis0212 plane two source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# JIS0212\n\nPlane2 \x8F\xA9\xA1\x8F\xA9\xAD; \x8F\xA7\xC4\x8F\xA7\xF4; \x8F\xA6\xF1\x8F\xA6\xF7.";
        $decoded = UnicodeText::decodeBytes($bytes, 'euc-jp');
        $document = (new MarkdownReader())->readBytes($bytes, 'x-euc-jp');
        $blocks = (new WordPressBlockWriter())->write($document);
        $badLead = UnicodeText::decodeBytes("\x8F A", 'euc-jp');
        $badTrail = UnicodeText::decodeBytes("\x8F\xA9\"A", 'euc-jp');
        $missingTrail = UnicodeText::decodeBytes("\x8F\xA9", 'euc-jp');
        $unmappedPair = UnicodeText::decodeBytes("\x8F\xA2\xA1A", 'euc-jp');

        $t->same('euc-jp', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# JIS0212\n\nPlane2 ÆŒ; Єє; άό.", $decoded['text']);
        $t->same(['encoding' => 'euc-jp', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('JIS0212', $document->children[0]->attr('text'));
        $t->same('Plane2 ÆŒ; Єє; άό.', $document->children[1]->attr('text'));
        $t->same(18, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="jis0212">JIS0212</h1>', $blocks);
        $t->contains('<p>Plane2 ÆŒ; Єє; άό.</p>', $blocks);
        $t->same("\u{FFFD} A", $badLead['text']);
        $t->same(1, $badLead['repairs']);
        $t->same("\u{FFFD}\"A", $badTrail['text']);
        $t->same(1, $badTrail['repairs']);
        $t->same("\u{FFFD}", $missingTrail['text']);
        $t->same(1, $missingTrail['repairs']);
        $t->same("\u{FFFD}A", $unmappedPair['text']);
        $t->same(1, $unmappedPair['repairs']);
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
    'decodes iso 2022 jp jis0212 plane two escape state into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# ISO2022 JIS0212\n\nPlane2 "
            . "\x1B\x24\x28\x44\x29\x21\x29\x2D\x1B\x28\x42; "
            . "\x1B\x24\x28\x44\x27\x44\x27\x74\x1B\x28\x42; "
            . "\x1B\x24\x28\x44\x26\x71\x26\x77\x1B\x28\x42.";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso-2022-jp');
        $document = (new MarkdownReader())->readBytes($bytes, 'csiso2022jp');
        $blocks = (new WordPressBlockWriter())->write($document);
        $badLead = UnicodeText::decodeBytes("\x1B\x24\x28\x44\x7F\x1B\x28\x42A", 'iso-2022-jp');
        $badTrail = UnicodeText::decodeBytes("\x1B\x24\x28\x44\x29 A", 'iso-2022-jp');
        $unmappedPair = UnicodeText::decodeBytes("\x1B\x24\x28\x44\x22\x21\x1B\x28\x42A", 'iso-2022-jp');

        $t->same('iso-2022-jp', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# ISO2022 JIS0212\n\nPlane2 ÆŒ; Єє; άό.", $decoded['text']);
        $t->same(['encoding' => 'iso-2022-jp', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('ISO2022 JIS0212', $document->children[0]->attr('text'));
        $t->same('Plane2 ÆŒ; Єє; άό.', $document->children[1]->attr('text'));
        $t->same(18, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="iso2022-jis0212">ISO2022 JIS0212</h1>', $blocks);
        $t->contains('<p>Plane2 ÆŒ; Єє; άό.</p>', $blocks);
        $t->same("\u{FFFD}A", $badLead['text']);
        $t->same(1, $badLead['repairs']);
        $t->same("\u{FFFD} A", $badTrail['text']);
        $t->same(1, $badTrail['repairs']);
        $t->same("\u{FFFD}A", $unmappedPair['text']);
        $t->same(1, $unmappedPair['repairs']);
    },
    'repairs iso 2022 jp sources that end before returning to ascii state' => static function (TestRunner $t): void {
        $bytes = "# \x1B\$B\x37\x57\x32\x68\x1B(B\n\n\x1B\$B\x4B\x5C\x4A\x38";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso-2022-jp');
        $document = (new MarkdownReader())->readBytes($bytes, 'csiso2022jp');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('iso-2022-jp', $decoded['encoding']);
        $t->same("# 計画\n\n本文\u{FFFD}", $decoded['text']);
        $t->same(1, $decoded['repairs']);
        $t->same(['encoding' => 'iso-2022-jp', 'bom' => null, 'repairs' => 1], $document->attr('sourceEncoding'));
        $t->same('計画', $document->children[0]->attr('text'));
        $t->same("本文\u{FFFD}", $document->children[1]->attr('text'));
        $t->same(5, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<p>本文�</p>', $blocks);
    },
    'decodes bounded mac japanese source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Mac Japanese\n\nLegacy \xB6\xC0\xB6\xC5 \x81\x41\x81\x42 \x82\xA0\x82\xA2\x82\xA4 \x83\x41\x83\x43\x83\x45 \xFD\xFE\xFF.";
        $decoded = UnicodeText::decodeBytes($bytes, 'x-mac-japanese');
        $document = (new MarkdownReader())->readBytes($bytes, 'macjapan');
        $blocks = (new WordPressBlockWriter())->write($document);
        $malformedTrail = UnicodeText::decodeBytes("\x82\"A", 'mac-japan');
        $unmappedPair = UnicodeText::decodeBytes("\x88\x40A", 'x-mac-japanese');
        $undefinedSingle = UnicodeText::decodeBytes("A\x80B\xE0", 'mac-japanese');

        $t->same('mac-japan', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Mac Japanese\n\nLegacy ｶﾀｶﾅ 、。 あいう アイウ ©™….", $decoded['text']);
        $t->same(['encoding' => 'mac-japan', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Mac Japanese', $document->children[0]->attr('text'));
        $t->same("Legacy ｶﾀｶﾅ 、。 あいう アイウ ©™….", $document->children[1]->attr('text'));
        $t->same(35, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(37, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="mac-japanese">Mac Japanese</h1>', $blocks);
        $t->contains('<p>Legacy ｶﾀｶﾅ 、。 あいう アイウ ©™….</p>', $blocks);
        $t->same("\u{FFFD}\"A", $malformedTrail['text']);
        $t->same(1, $malformedTrail['repairs']);
        $t->same("\u{FFFD}A", $unmappedPair['text']);
        $t->same(1, $unmappedPair['repairs']);
        $t->same("A\u{FFFD}B\u{FFFD}", $undefinedSingle['text']);
        $t->same(2, $undefinedSingle['repairs']);
    },
    'decodes bounded big5 traditional chinese source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = (string) hex2bin('2320a4a4a4e50a0aa4a4a4e5204269673520b4fab8d5a141adbbb4e4a143');
        $decoded = UnicodeText::decodeBytes($bytes, 'big5-hkscs');
        $document = (new MarkdownReader())->readBytes($bytes, 'cn-big5');
        $blocks = (new WordPressBlockWriter())->write($document);
        $malformedLead = UnicodeText::decodeBytes("\xA4 A", 'big5');
        $unmappedPair = UnicodeText::decodeBytes("\x81\x40A", 'x-x-big5');
        $twoCodepointPointers = UnicodeText::decodeBytes("\x88\x62\x88\x64\x88\xA3\x88\xA5", 'big5');

        $t->same('big5', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# 中文\n\n中文 Big5 測試，香港。", $decoded['text']);
        $t->same("Ê\u{0304}Ê\u{030C}ê\u{0304}ê\u{030C}", $twoCodepointPointers['text']);
        $t->same(0, $twoCodepointPointers['repairs']);
        $t->same(["Ê\u{0304}", "Ê\u{030C}", "ê\u{0304}", "ê\u{030C}"], UnicodeText::graphemes($twoCodepointPointers['text']));
        $t->same(4, UnicodeText::displayWidth($twoCodepointPointers['text']));
        $t->same(["Ê\u{0304}Ê\u{030C}", "ê\u{0304}ê\u{030C}"], UnicodeText::splitByDisplayBreakpoints($twoCodepointPointers['text'], [2]));
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
    'decodes big5 punctuation and quote bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Big5 Punctuation\n\n\xA1\x40\xA1\x75\xA4\xA4\xA4\xE5\xA1\x76\xA1\xA7quote\xA1\xA8\xA1\x48\xA1\x49\xA1\x46\xA1\x47\xA1\x42\xA1\x43\xA1\xB0\xA1\xB1\xA1\xB2 \xA1\x45.";
        $decoded = UnicodeText::decodeBytes($bytes, 'big5');
        $document = (new MarkdownReader())->readBytes($bytes, 'big5-hkscs');
        $blocks = (new WordPressBlockWriter())->write($document);
        $quoteRun = UnicodeText::decodeBytes("\xA1\x75\xA1\x76\xA1\xA5\xA1\xA6\xA1\xA7\xA1\xA8", 'big5');
        $cp950Bullet = UnicodeText::decodeBytes("\xA1\x45", 'windows-950');

        $t->same('big5', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Big5 Punctuation\n\n　「中文」“quote”？！；：、。※§〃 •.", $decoded['text']);
        $t->same('「」‘’“”', $quoteRun['text']);
        $t->same(0, $quoteRun['repairs']);
        $t->same('‧', $cp950Bullet['text']);
        $t->same('cp950', $cp950Bullet['encoding']);
        $t->same(['encoding' => 'big5', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Big5 Punctuation', $document->children[0]->attr('text'));
        $t->same('　「中文」“quote”？！；：、。※§〃 •.', $document->children[1]->attr('text'));
        $t->same(36, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(41, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="big5-punctuation">Big5 Punctuation</h1>', $blocks);
        $t->contains('<p>　「中文」“quote”？！；：、。※§〃 •.</p>', $blocks);
    },
    'decodes big5 kana and fullwidth extension bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Big5 Kana\n\nKana \xC6\xA1\xC6\xA2\xC6\xA3\xC6\xA4 \xC6\xA5\xC6\xA6; digits \xA2\xAF\xA2\xB0\xA2\xB1.";
        $decoded = UnicodeText::decodeBytes($bytes, 'big5');
        $document = (new MarkdownReader())->readBytes($bytes, 'big5-hkscs');
        $blocks = (new WordPressBlockWriter())->write($document);
        $baseFixtureComparison = UnicodeText::decodeBytes((string) hex2bin('a4a4a4e5'), 'big5');

        $t->same('big5', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Big5 Kana\n\nKana ヾゝゞ々 ぁあ; digits ０１２.", $decoded['text']);
        $t->same('中文', $baseFixtureComparison['text']);
        $t->same(['encoding' => 'big5', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Big5 Kana', $document->children[0]->attr('text'));
        $t->same('Kana ヾゝゞ々 ぁあ; digits ０１２.', $document->children[1]->attr('text'));
        $t->same(34, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(["ヾゝ", "ゞ々", 'ぁあ', '０１２'], UnicodeText::splitByDisplayBreakpoints("ヾゝゞ々ぁあ０１２", [4, 8, 12]));
        $t->contains('<h1 id="big5-kana">Big5 Kana</h1>', $blocks);
        $t->contains('<p>Kana ヾゝゞ々 ぁあ; digits ０１２.</p>', $blocks);
    },
    'decodes big5 greek and bopomofo row bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# Big5 A3\n\nGreek \xA3\x44\xA3\x50\xA3\x5B \xA3\x5C\xA3\x73; bopomofo \xA3\x74\xA3\x75\xA3\x7E.";
        $decoded = UnicodeText::decodeBytes($bytes, 'big5-hkscs');
        $document = (new MarkdownReader())->readBytes($bytes, 'cn-big5');
        $blocks = (new WordPressBlockWriter())->write($document);
        $rowProbe = UnicodeText::decodeBytes("\xA3\x44\xA3\x50\xA3\x5B\xA3\x5C\xA3\x73\xA3\x74\xA3\x75\xA3\x7E", 'big5');
        $unmappedNeighbor = UnicodeText::decodeBytes("\xA3\xBFZ", 'big5');

        $t->same('big5', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# Big5 A3\n\nGreek ΑΝΩ αω; bopomofo ㄅㄆㄏ.", $decoded['text']);
        $t->same('ΑΝΩαωㄅㄆㄏ', $rowProbe['text']);
        $t->same(0, $rowProbe['repairs']);
        $t->same(11, UnicodeText::displayWidth($rowProbe['text']));
        $t->same(16, UnicodeText::displayWidth($rowProbe['text'], 'wide'));
        $t->same(['ΑΝΩ', 'αω', 'ㄅㄆㄏ'], UnicodeText::splitByDisplayBreakpoints($rowProbe['text'], [6, 10], 'wide'));
        $t->same(['encoding' => 'big5', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('Big5 A3', $document->children[0]->attr('text'));
        $t->same('Greek ΑΝΩ αω; bopomofo ㄅㄆㄏ.', $document->children[1]->attr('text'));
        $t->same(30, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(35, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="big5-a3">Big5 A3</h1>', $blocks);
        $t->contains('<p>Greek ΑΝΩ αω; bopomofo ㄅㄆㄏ.</p>', $blocks);
        $t->same("\u{FFFD}Z", $unmappedNeighbor['text']);
        $t->same(1, $unmappedNeighbor['repairs']);
    },
    'decodes cp950 big5 extension bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# CP950\n\nCP950 Euro \xA3\xE1 glyphs \xF9\xD6\xF9\xD7 box \xF9\xDD\xF9\xDE\xF9\xDF.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp950');
        $document = (new MarkdownReader())->readBytes($bytes, 'windows-950');
        $blocks = (new WordPressBlockWriter())->write($document);
        $big5Comparison = UnicodeText::decodeBytes("\xA3\xE1\xF9\xD6", 'big5');
        $punctuation = UnicodeText::decodeBytes("\xA1\x45\xA1\xC2\xA1\xE3\xA2\x40", 'ms950');
        $malformedTrail = UnicodeText::decodeBytes("\xF9\"A", 'windows-950');
        $unmappedPair = UnicodeText::decodeBytes("\x81\x40A", 'cp950');

        $t->same('cp950', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# CP950\n\nCP950 Euro \u{20AC} glyphs \u{7881}\u{92B9} box \u{2554}\u{2566}\u{2557}.", $decoded['text']);
        $t->same("\u{FFFD}\u{FFFD}", $big5Comparison['text']);
        $t->same(2, $big5Comparison['repairs']);
        $t->same('cp950', $punctuation['encoding']);
        $t->same("\u{2027}\u{00AF}\u{FF5E}\u{FF3C}", $punctuation['text']);
        $t->same(0, $punctuation['repairs']);
        $t->same(6, UnicodeText::displayWidth($punctuation['text']));
        $t->same(7, UnicodeText::displayWidth($punctuation['text'], 'wide'));
        $t->same(['encoding' => 'cp950', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('CP950', $document->children[0]->attr('text'));
        $t->same("CP950 Euro \u{20AC} glyphs \u{7881}\u{92B9} box \u{2554}\u{2566}\u{2557}.", $document->children[1]->attr('text'));
        $t->same(33, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(37, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="cp950">CP950</h1>', $blocks);
        $t->contains("<p>CP950 Euro \u{20AC} glyphs \u{7881}\u{92B9} box \u{2554}\u{2566}\u{2557}.</p>", $blocks);
        $t->same("\u{FFFD}\"A", $malformedTrail['text']);
        $t->same(1, $malformedTrail['repairs']);
        $t->same("\u{FFFD}A", $unmappedPair['text']);
        $t->same(1, $unmappedPair['repairs']);
    },
    'decodes bounded euc tw plane one source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# EUC TW\n\nPlane1 \xA1\xA1\xA1\xA2\xA1\xA3.";
        $decoded = UnicodeText::decodeBytes($bytes, 'euc-tw');
        $document = (new MarkdownReader())->readBytes($bytes, 'x-euc-tw');
        $blocks = (new WordPressBlockWriter())->write($document);
        $malformedTrail = UnicodeText::decodeBytes("\xA1\"A", 'euctw');
        $unsupportedPlane = UnicodeText::decodeBytes("\x8E\xA2\xA1\xA1A", 'euc-tw');
        $truncatedPlane = UnicodeText::decodeBytes("\x8E\xA2\xA1", 'euc-tw');
        $unmappedPair = UnicodeText::decodeBytes("\xFE\xFEA", 'cseuctw');

        $t->same('euc-tw', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# EUC TW\n\nPlane1 \u{4E28}\u{4E36}\u{4E3F}.", $decoded['text']);
        $t->same(['encoding' => 'euc-tw', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('EUC TW', $document->children[0]->attr('text'));
        $t->same("Plane1 \u{4E28}\u{4E36}\u{4E3F}.", $document->children[1]->attr('text'));
        $t->same(14, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(14, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="euc-tw">EUC TW</h1>', $blocks);
        $t->contains("<p>Plane1 \u{4E28}\u{4E36}\u{4E3F}.</p>", $blocks);
        $t->same("\u{FFFD}\"A", $malformedTrail['text']);
        $t->same(1, $malformedTrail['repairs']);
        $t->same("\u{FFFD}A", $unsupportedPlane['text']);
        $t->same(1, $unsupportedPlane['repairs']);
        $t->same("\u{FFFD}", $truncatedPlane['text']);
        $t->same(1, $truncatedPlane['repairs']);
        $t->same("\u{FFFD}A", $unmappedPair['text']);
        $t->same(1, $unmappedPair['repairs']);
    },
    'decodes bounded euc tw cns row pairs into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# EUC TW Rows\n\nRows \xA2\xA1\xA2\xA2\xA2\xA3; \xA3\xA1\xA3\xA2\xA3\xA3.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cseuctw');
        $document = (new MarkdownReader())->readBytes($bytes, 'euc-tw');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('euc-tw', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# EUC TW Rows\n\nRows \u{5322}\u{5304}\u{5303}; \u{4F64}\u{51E8}\u{4F67}.", $decoded['text']);
        $t->same(['encoding' => 'euc-tw', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('EUC TW Rows', $document->children[0]->attr('text'));
        $t->same("Rows \u{5322}\u{5304}\u{5303}; \u{4F64}\u{51E8}\u{4F67}.", $document->children[1]->attr('text'));
        $t->same(20, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(20, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="euc-tw-rows">EUC TW Rows</h1>', $blocks);
        $t->contains("<p>Rows \u{5322}\u{5304}\u{5303}; \u{4F64}\u{51E8}\u{4F67}.</p>", $blocks);
    },
    'decodes bounded gbk simplified chinese source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = (string) hex2bin('2320bcf2cce50a0ad6d0cec42047424b20b2e2cad4a3acb1b1bea9a1a3');
        $decoded = UnicodeText::decodeBytes($bytes, 'gbk');
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
    'decodes gb2312 symbol rows into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# GB2312 Symbols\n\nSymbols \xA1\xA1\xA1\xA2\xA1\xA3; fullwidth \xA3\xC1\xA3\xE1\xA3\xB0; kana \xA4\xA2\xA4\xA4\xA5\xA2; greek \xA6\xA1\xA6\xC1.";
        $decoded = UnicodeText::decodeBytes($bytes, 'euc-cn');
        $document = (new MarkdownReader())->readBytes($bytes, 'gb2312');
        $blocks = (new WordPressBlockWriter())->write($document);
        $gb18030Comparison = UnicodeText::decodeBytes("\xA3\xC1\xA4\xA2\xA6\xA1", 'gb18030');
        $gb12345Comparison = UnicodeText::decodeBytes("\xA1\xA1\xA3\xE1\xA5\xA2\xA6\xC1", 'gb12345');
        $unmappedSymbol = UnicodeText::decodeBytes("\xA2\xA1A", 'gb2312');
        $text = 'Symbols 　、。; fullwidth Ａａ０; kana あいア; greek Αα.';

        $t->same('gbk', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# GB2312 Symbols\n\n{$text}", $decoded['text']);
        $t->same('ＡあΑ', $gb18030Comparison['text']);
        $t->same(0, $gb18030Comparison['repairs']);
        $t->same('　ａアα', $gb12345Comparison['text']);
        $t->same(0, $gb12345Comparison['repairs']);
        $t->same(['encoding' => 'gbk', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('GB2312 Symbols', $document->children[0]->attr('text'));
        $t->same($text, $document->children[1]->attr('text'));
        $t->same(56, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(58, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="gb2312-symbols">GB2312 Symbols</h1>', $blocks);
        $t->contains("<p>{$text}</p>", $blocks);
        $t->same("\u{FFFD}A", $unmappedSymbol['text']);
        $t->same(1, $unmappedSymbol['repairs']);
    },
    'decodes gb2312 enclosed number and box drawing symbols into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# GB2312 Enclosed\n\nEnclosed \xA2\xB1\xA2\xB2\xA2\xB3 \xA2\xC5\xA2\xC6\xA2\xC7 \xA2\xD9\xA2\xDA \xA2\xE5\xA2\xE6 \xA2\xF1\xA2\xF2; box \xA9\xA4\xA9\xA5\xA9\xA6\xA9\xA7\xA9\xA8\xA9\xA9.";
        $decoded = UnicodeText::decodeBytes($bytes, 'euc-cn');
        $document = (new MarkdownReader())->readBytes($bytes, 'gb2312');
        $blocks = (new WordPressBlockWriter())->write($document);
        $gb18030Comparison = UnicodeText::decodeBytes("\xA2\xB1\xA2\xC5\xA2\xD9\xA2\xE5\xA2\xF1\xA9\xA4", 'gb18030');
        $gb12345Comparison = UnicodeText::decodeBytes("\xA2\xB2\xA2\xC6\xA2\xDA\xA2\xE6\xA2\xF2\xA9\xA5", 'gb12345');
        $unmappedNeighbor = UnicodeText::decodeBytes("\xA2\xA1A", 'gb2312');
        $text = 'Enclosed ⒈⒉⒊ ⑴⑵⑶ ①② ㈠㈡ ⅠⅡ; box ─━│┃┄┅.';

        $t->same('gbk', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# GB2312 Enclosed\n\n{$text}", $decoded['text']);
        $t->same('⒈⑴①㈠Ⅰ─', $gb18030Comparison['text']);
        $t->same(0, $gb18030Comparison['repairs']);
        $t->same('⒉⑵②㈡Ⅱ━', $gb12345Comparison['text']);
        $t->same(0, $gb12345Comparison['repairs']);
        $t->same(['encoding' => 'gbk', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('GB2312 Enclosed', $document->children[0]->attr('text'));
        $t->same($text, $document->children[1]->attr('text'));
        $t->same(40, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(56, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->same(['⒈⒉⒊', '⑴⑵⑶', '①②', '㈠㈡', 'ⅠⅡ─━│┃┄┅'], UnicodeText::splitByDisplayBreakpoints('⒈⒉⒊⑴⑵⑶①②㈠㈡ⅠⅡ─━│┃┄┅', [6, 12, 16, 20], 'wide'));
        $t->contains('<h1 id="gb2312-enclosed">GB2312 Enclosed</h1>', $blocks);
        $t->contains("<p>{$text}</p>", $blocks);
        $t->same("\u{FFFD}A", $unmappedNeighbor['text']);
        $t->same(1, $unmappedNeighbor['repairs']);
    },
    'decodes gb1988 yen overline and halfwidth punctuation into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# GB1988\n\nCurrency \$~ halfwidth \xA1\xB0\xDF ASCII.";
        $decoded = UnicodeText::decodeBytes($bytes, 'gb_1988-80');
        $document = (new MarkdownReader())->readBytes($bytes, 'csISO57GB1988');
        $blocks = (new WordPressBlockWriter())->write($document);
        $utf8Comparison = UnicodeText::decodeBytes($bytes, 'utf-8');
        $undefined = UnicodeText::decodeBytes("A\x80\xA0\xE0B", 'gb1988');

        $t->same('gb1988', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# GB1988\n\nCurrency ¥‾ halfwidth ｡ｰﾟ ASCII.", $decoded['text']);
        $t->same("# GB1988\n\nCurrency \$~ halfwidth ��� ASCII.", $utf8Comparison['text']);
        $t->same(['encoding' => 'gb1988', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('GB1988', $document->children[0]->attr('text'));
        $t->same('Currency ¥‾ halfwidth ｡ｰﾟ ASCII.', $document->children[1]->attr('text'));
        $t->same(32, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->same(33, UnicodeText::displayWidth((string) $document->children[1]->attr('text'), 'wide'));
        $t->contains('<h1 id="gb1988">GB1988</h1>', $blocks);
        $t->contains('<p>Currency ¥‾ halfwidth ｡ｰﾟ ASCII.</p>', $blocks);
        $t->same("A\u{FFFD}\u{FFFD}\u{FFFD}B", $undefined['text']);
        $t->same(3, $undefined['repairs']);
    },
    'decodes bounded gb12345 traditional chinese source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = (string) hex2bin('2320bcf2cce50a0ad6d0cec4204742313233343520b2e2cad4a3acb1b1bea9a1a3');
        $decoded = UnicodeText::decodeBytes($bytes, 'gb12345');
        $document = (new MarkdownReader())->readBytes($bytes, 'gb12345-90');
        $blocks = (new WordPressBlockWriter())->write($document);
        $gbkComparison = UnicodeText::decodeBytes($bytes, 'gbk');
        $badTrail = UnicodeText::decodeBytes("\xD6\"A", 'gb12345');
        $unmappedPair = UnicodeText::decodeBytes("\xA2\xA1A", 'csgb12345');

        $t->same('gb12345', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# 簡體\n\n中文 GB12345 測試，北京。", $decoded['text']);
        $t->same("# 简体\n\n中文 GB12345 测试，北京。", $gbkComparison['text']);
        $t->same(['encoding' => 'gb12345', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('簡體', $document->children[0]->attr('text'));
        $t->same('中文 GB12345 測試，北京。', $document->children[1]->attr('text'));
        $t->same(25, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="簡體">簡體</h1>', $blocks);
        $t->contains('<p>中文 GB12345 測試，北京。</p>', $blocks);
        $t->same("\u{FFFD}\"A", $badTrail['text']);
        $t->same(1, $badTrail['repairs']);
        $t->same("\u{FFFD}A", $unmappedPair['text']);
        $t->same(1, $unmappedPair['repairs']);
    },
    'decodes bounded gb18030 four byte source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# GB18030\n\nEmoji \x94\x39\xFC\x36 CJK \x95\x32\x82\x36 Latin \x81\x30\x8B\x38 Euro \xA2\xE3.";
        $decoded = UnicodeText::decodeBytes($bytes, 'gb18030');
        $document = (new MarkdownReader())->readBytes($bytes, 'gb-18030');
        $blocks = (new WordPressBlockWriter())->write($document);
        $malformedFourByte = UnicodeText::decodeBytes("\x94\x39\"A", 'gb18030');
        $firstRangeFourByte = UnicodeText::decodeBytes("\x81\x30\x81\x30A", 'gb18030');

        $t->same('gb18030', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# GB18030\n\nEmoji \u{1F600} CJK \u{20000} Latin \u{0100} Euro \u{20AC}.", $decoded['text']);
        $t->same(['encoding' => 'gb18030', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('GB18030', $document->children[0]->attr('text'));
        $t->same("Emoji \u{1F600} CJK \u{20000} Latin \u{0100} Euro \u{20AC}.", $document->children[1]->attr('text'));
        $t->same(31, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="gb18030">GB18030</h1>', $blocks);
        $t->contains("<p>Emoji \u{1F600} CJK \u{20000} Latin \u{0100} Euro \u{20AC}.</p>", $blocks);
        $t->same("\u{FFFD}9\"A", $malformedFourByte['text']);
        $t->same(1, $malformedFourByte['repairs']);
        $t->same("\u{0080}A", $firstRangeFourByte['text']);
        $t->same(0, $firstRangeFourByte['repairs']);
        $t->same(1, UnicodeText::displayWidth($firstRangeFourByte['text']));
    },
    'decodes gb18030 four byte range pointers into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# GB18030 Ranges\n\nRange \x81\x30\xA4\x38 \x81\x30\xD3\x32 \x82\x35\x8F\x33 \x84\x31\x82\x36 \x90\x30\x81\x30 \x81\x35\xF4\x37.";
        $decoded = UnicodeText::decodeBytes($bytes, 'gb18030');
        $document = (new MarkdownReader())->readBytes($bytes, 'gb18030');
        $blocks = (new WordPressBlockWriter())->write($document);
        $gap = UnicodeText::decodeBytes("\x84\x31\xA5\x30", 'gb18030');
        $beyondUnicode = UnicodeText::decodeBytes("\xE3\x32\x9A\x36", 'gb18030');
        $text = "Range \u{020B} \u{0454} \u{9FA6} \u{FE10} \u{10000} \u{E7C7}.";

        $t->same('gb18030', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# GB18030 Ranges\n\n{$text}", $decoded['text']);
        $t->same(['encoding' => 'gb18030', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('GB18030 Ranges', $document->children[0]->attr('text'));
        $t->same($text, $document->children[1]->attr('text'));
        $t->same(20, UnicodeText::displayWidth($text));
        $t->same(21, UnicodeText::displayWidth($text, 'wide'));
        $t->contains('<h1 id="gb18030-ranges">GB18030 Ranges</h1>', $blocks);
        $t->contains("<p>{$text}</p>", $blocks);
        $t->same("\u{FFFD}", $gap['text']);
        $t->same(1, $gap['repairs']);
        $t->same("\u{FFFD}", $beyondUnicode['text']);
        $t->same(1, $beyondUnicode['repairs']);
    },
    'decodes bounded euc kr korean source bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = (string) hex2bin('2320c7d1b1db0a0ac7d1b1db204555432d4b5220c5d7bdbac6ae2c20bcadbfef2e');
        $decoded = UnicodeText::decodeBytes($bytes, 'ks_c_5601-1987');
        $document = (new MarkdownReader())->readBytes($bytes, 'euc-kr');
        $blocks = (new WordPressBlockWriter())->write($document);
        $malformedLead = UnicodeText::decodeBytes("\xC7\"A", 'euc-kr');
        $unmappedPair = UnicodeText::decodeBytes("\x81\x41A", 'cseuckr');
        $truncatedLead = UnicodeText::decodeBytes("\xC7", 'korean');

        $t->same('euc-kr', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# 한글\n\n한글 EUC-KR 테스트, 서울.", $decoded['text']);
        $t->same(['encoding' => 'euc-kr', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('한글', $document->children[0]->attr('text'));
        $t->same('한글 EUC-KR 테스트, 서울.', $document->children[1]->attr('text'));
        $t->same(25, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="한글">한글</h1>', $blocks);
        $t->contains('<p>한글 EUC-KR 테스트, 서울.</p>', $blocks);
        $t->same("\u{FFFD}\"A", $malformedLead['text']);
        $t->same(1, $malformedLead['repairs']);
        $t->same("\u{FFFD}A", $unmappedPair['text']);
        $t->same(1, $unmappedPair['repairs']);
        $t->same("\u{FFFD}", $truncatedLead['text']);
        $t->same(1, $truncatedLead['repairs']);
    },
    'decodes bounded windows 949 uhc korean extension bytes into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# UHC\n\nWindows-949 UHC \x81\x41\x81\x42\x81\x43 \x81\x51\x81\x52 \x81\xA1\x81\xA2.";
        $decoded = UnicodeText::decodeBytes($bytes, 'cp949');
        $document = (new MarkdownReader())->readBytes($bytes, 'windows-949');
        $blocks = (new WordPressBlockWriter())->write($document);
        $eucKrRepairsExtension = UnicodeText::decodeBytes("\x81\x41A", 'euc-kr');
        $malformedTrail = UnicodeText::decodeBytes("\x81\x30A", 'uhc');

        $t->same('windows-949', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# UHC\n\nWindows-949 UHC 갂갃갅 갦갧 걾걿.", $decoded['text']);
        $t->same(['encoding' => 'windows-949', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('UHC', $document->children[0]->attr('text'));
        $t->same('Windows-949 UHC 갂갃갅 갦갧 걾걿.', $document->children[1]->attr('text'));
        $t->same(33, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="uhc">UHC</h1>', $blocks);
        $t->contains('<p>Windows-949 UHC 갂갃갅 갦갧 걾걿.</p>', $blocks);
        $t->same('euc-kr', $eucKrRepairsExtension['encoding']);
        $t->same("\u{FFFD}A", $eucKrRepairsExtension['text']);
        $t->same(1, $eucKrRepairsExtension['repairs']);
        $t->same('windows-949', $malformedTrail['encoding']);
        $t->same("\u{FFFD}0A", $malformedTrail['text']);
        $t->same(1, $malformedTrail['repairs']);
    },
    'decodes bounded iso 2022 kr shift states into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "\x1B\$)C# \x0E\x47\x51\x31\x5B\x0F\n\n\x0E\x47\x51\x31\x5B\x0F ISO-2022-KR \x0E\x45\x57\x3D\x3A\x46\x2E\x0F, \x0E\x3C\x2D\x3F\x6F\x0F.";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso2022kr');
        $document = (new MarkdownReader())->readBytes($bytes, 'csiso2022kr');
        $blocks = (new WordPressBlockWriter())->write($document);
        $invalidEscape = UnicodeText::decodeBytes("A\x1B(B", 'iso-2022-kr');
        $unmappedPair = UnicodeText::decodeBytes("\x1B\$)C\x0E!!\x0F", 'iso-2022-kr');
        $finalStateRepair = UnicodeText::decodeBytes("\x1B\$)C\x0E\x47\x51", 'iso-2022-kr');
        $missingTrailBeforeShift = UnicodeText::decodeBytes("\x1B\$)C\x0E\x47\x0F", 'iso-2022-kr');

        $t->same('iso-2022-kr', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# 한글\n\n한글 ISO-2022-KR 테스트, 서울.", $decoded['text']);
        $t->same(['encoding' => 'iso-2022-kr', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('한글', $document->children[0]->attr('text'));
        $t->same('한글 ISO-2022-KR 테스트, 서울.', $document->children[1]->attr('text'));
        $t->same(30, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="한글">한글</h1>', $blocks);
        $t->contains('<p>한글 ISO-2022-KR 테스트, 서울.</p>', $blocks);
        $t->same("A\u{FFFD}", $invalidEscape['text']);
        $t->same(1, $invalidEscape['repairs']);
        $t->same("\u{FFFD}", $unmappedPair['text']);
        $t->same(1, $unmappedPair['repairs']);
        $t->same("한\u{FFFD}", $finalStateRepair['text']);
        $t->same(1, $finalStateRepair['repairs']);
        $t->same("\u{FFFD}", $missingTrailBeforeShift['text']);
        $t->same(1, $missingTrailBeforeShift['repairs']);
    },
    'decodes bounded iso 2022 cn gb2312 shift states into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "\x1B\$)A# \x0E\x3C\x72\x4C\x65\x0F\n\n\x0E\x56\x50\x4E\x44\x0F ISO-2022-CN \x0E\x32\x62\x4A\x54\x0F, \x0E\x31\x31\x3E\x29\x0F.";
        $decoded = UnicodeText::decodeBytes($bytes, 'iso2022cn');
        $document = (new MarkdownReader())->readBytes($bytes, 'csiso2022cn');
        $blocks = (new WordPressBlockWriter())->write($document);
        $invalidEscape = UnicodeText::decodeBytes("A\x1B(B", 'iso-2022-cn');
        $shiftWithoutDesignation = UnicodeText::decodeBytes("\x0E\x56\x50\x0F", 'iso-2022-cn');
        $unmappedPair = UnicodeText::decodeBytes("\x1B\$)A\x0E\"!\x0F", 'iso-2022-cn');
        $missingTrailBeforeShift = UnicodeText::decodeBytes("\x1B\$)A\x0E\x56\x0F", 'iso-2022-cn');
        $unsupportedCnsDesignation = UnicodeText::decodeBytes("A\x1B\$)GB", 'iso-2022-cn');
        $finalStateRepair = UnicodeText::decodeBytes("\x1B\$)A\x0E\x56\x50", 'iso-2022-cn');

        $t->same('iso-2022-cn', $decoded['encoding']);
        $t->same(0, $decoded['repairs']);
        $t->same("# 简体\n\n中文 ISO-2022-CN 测试, 北京.", $decoded['text']);
        $t->same(['encoding' => 'iso-2022-cn', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('简体', $document->children[0]->attr('text'));
        $t->same('中文 ISO-2022-CN 测试, 北京.', $document->children[1]->attr('text'));
        $t->same(28, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="简体">简体</h1>', $blocks);
        $t->contains('<p>中文 ISO-2022-CN 测试, 北京.</p>', $blocks);
        $t->same("A\u{FFFD}", $invalidEscape['text']);
        $t->same(1, $invalidEscape['repairs']);
        $t->same("\u{FFFD}", $shiftWithoutDesignation['text']);
        $t->same(1, $shiftWithoutDesignation['repairs']);
        $t->same("\u{FFFD}", $unmappedPair['text']);
        $t->same(1, $unmappedPair['repairs']);
        $t->same("\u{FFFD}", $missingTrailBeforeShift['text']);
        $t->same(1, $missingTrailBeforeShift['repairs']);
        $t->same("A\u{FFFD}B", $unsupportedCnsDesignation['text']);
        $t->same(1, $unsupportedCnsDesignation['repairs']);
        $t->same("中\u{FFFD}", $finalStateRepair['text']);
        $t->same(1, $finalStateRepair['repairs']);
    },
    'decodes bounded hz gb 2312 escape states into wordpress blocks' => static function (TestRunner $t): void {
        $bytes = "# ~{<rLe~}\n\n~{VPND~} HZ ~{2bJT#,11>)!#~}\nEscaped ~~ tilde and line~\njoin.";
        $decoded = UnicodeText::decodeBytes($bytes, 'hz-gb-2312');
        $document = (new MarkdownReader())->readBytes($bytes, 'hzgb2312');
        $blocks = (new WordPressBlockWriter())->write($document);
        $malformedPair = UnicodeText::decodeBytes('~{<~}', 'hz');
        $invalidEscape = UnicodeText::decodeBytes('A~xB', 'hz-gb-2312');
        $unmappedPair = UnicodeText::decodeBytes('~{"!~}', 'hz-gb-2312');

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
        $t->same(['byte-order-mark-overrode-encoding:windows-1252'], $utf8['diagnostics']);
        $t->same('utf-16le', $utf16['encoding']);
        $t->same('utf-16le', $utf16['bom']);
        $t->same("# \u{9B5A}\n\nBOM override", $utf16['text']);
        $t->same(0, $utf16['repairs']);
        $t->same(['byte-order-mark-overrode-encoding:windows-1252'], $utf16['diagnostics']);
        $t->same([
            'encoding' => 'utf-16be',
            'bom' => 'utf-16be',
            'repairs' => 0,
            'diagnostics' => ['byte-order-mark-overrode-encoding:windows-1252'],
        ], $document->attr('sourceEncoding'));
        $t->same('計画', $document->children[0]->attr('text'));
        $t->same('BE', $document->children[1]->attr('text'));
        $t->contains('<h1 id="計画">計画</h1>', $blocks);
        $t->contains('<p>BE</p>', $blocks);
    },
    'decodes ucs 2 labels as utf16 family source bytes' => static function (TestRunner $t) use ($utf16le, $utf16be): void {
        $leBytes = $utf16le([
            0x0023,
            0x0020,
            0x0055,
            0x0043,
            0x0053,
            0x0032,
            0x000a,
            0x000a,
            0x0043,
            0x0061,
            0x0066,
            0x00e9,
            0x0020,
            0x2014,
            0x0020,
            0x9b5a,
        ]);
        $beBytes = $utf16be([
            0x0023,
            0x0020,
            0x8a08,
            0x753b,
            0x000a,
            0x000a,
            0x0042,
            0x0045,
        ]);
        $defaultBytes = $utf16le([0x0044, 0x0065, 0x0066, 0x0061, 0x0075, 0x006c, 0x0074]);
        $decodedLe = UnicodeText::decodeBytes($leBytes, 'ucs-2le');
        $decodedBe = UnicodeText::decodeBytes($beBytes, 'ucs_2be');
        $decodedDefault = UnicodeText::decodeBytes($defaultBytes, 'ucs-2');
        $document = (new MarkdownReader())->readBytes($leBytes, 'ucs-2le');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('utf-16le', $decodedLe['encoding']);
        $t->same(null, $decodedLe['bom']);
        $t->same("# UCS2\n\nCafé — 魚", $decodedLe['text']);
        $t->same(0, $decodedLe['repairs']);
        $t->same('utf-16be', $decodedBe['encoding']);
        $t->same("# 計画\n\nBE", $decodedBe['text']);
        $t->same('utf-16le', $decodedDefault['encoding']);
        $t->same('Default', $decodedDefault['text']);
        $t->same(['encoding' => 'utf-16le', 'bom' => null, 'repairs' => 0], $document->attr('sourceEncoding'));
        $t->same('UCS2', $document->children[0]->attr('text'));
        $t->same('Café — 魚', $document->children[1]->attr('text'));
        $t->same(9, UnicodeText::displayWidth((string) $document->children[1]->attr('text')));
        $t->contains('<h1 id="ucs2">UCS2</h1>', $blocks);
        $t->contains("<p>Café — 魚</p>", $blocks);
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
        $t->same([
            'encoding' => 'utf-32be',
            'bom' => 'utf-32be',
            'repairs' => 0,
            'diagnostics' => ['byte-order-mark-overrode-encoding:windows-1252'],
        ], $document->attr('sourceEncoding'));
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
        $t->same(['invalid-utf8-repaired:2'], $decoded['diagnostics']);
        $t->same("Broken \u{FFFD}(\u{FFFD} UTF-8", $decoded['text']);
        $t->same([
            'encoding' => 'utf-8-repaired',
            'bom' => null,
            'repairs' => 2,
            'diagnostics' => ['invalid-utf8-repaired:2'],
        ], $document->attr('sourceEncoding'));
        $t->same("Broken \u{FFFD}(\u{FFFD} UTF-8", $document->children[0]->attr('text'));
    },
    'preserves unicode decode diagnostics for review handoff' => static function (TestRunner $t) use ($utf16le): void {
        $bomOverride = UnicodeText::decodeBytes("\xFF\xFE" . $utf16le([0x004F, 0x004B]), 'windows-1252');
        $unknown = UnicodeText::decodeBytes('Plain text', 'x-pandoc-fallback');
        $document = (new MarkdownReader())->readBytes("Bad \xED\xA0\x80 scalar", 'utf-8');

        $t->same('utf-16le', $bomOverride['encoding']);
        $t->same('OK', $bomOverride['text']);
        $t->same(['byte-order-mark-overrode-encoding:windows-1252'], $bomOverride['diagnostics']);
        $t->same('utf-8', $unknown['encoding']);
        $t->same(['unknown-charset-label-defaulted-to-utf-8'], $unknown['diagnostics']);
        $t->same([
            'encoding' => 'utf-8-repaired',
            'bom' => null,
            'repairs' => 1,
            'diagnostics' => ['invalid-utf8-repaired:1'],
        ], $document->attr('sourceEncoding'));
        $t->same("Bad \u{FFFD} scalar", $document->children[0]->attr('text'));
    },
    'repairs complete invalid utf8 scalar sequences once' => static function (TestRunner $t): void {
        $bytes = "# UTF-8 Repair\n\nBad \xED\xA0\x80 high \xED\xB0\x80 low \xE0\x80\x80 overlong \xF0\x80\x80\x80 wide \xF4\x90\x80\x80 beyond.";
        $decoded = UnicodeText::decodeBytes($bytes);
        $document = (new MarkdownReader())->readBytes($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);
        $text = "Bad \u{FFFD} high \u{FFFD} low \u{FFFD} overlong \u{FFFD} wide \u{FFFD} beyond.";

        $t->same('utf-8-repaired', $decoded['encoding']);
        $t->same(5, $decoded['repairs']);
        $t->same(['invalid-utf8-repaired:5'], $decoded['diagnostics']);
        $t->same("# UTF-8 Repair\n\n{$text}", $decoded['text']);
        $t->same([
            'encoding' => 'utf-8-repaired',
            'bom' => null,
            'repairs' => 5,
            'diagnostics' => ['invalid-utf8-repaired:5'],
        ], $document->attr('sourceEncoding'));
        $t->same('UTF-8 Repair', $document->children[0]->attr('text'));
        $t->same($text, $document->children[1]->attr('text'));
        $t->same(44, UnicodeText::displayWidth($text));
        $t->same(49, UnicodeText::displayWidth($text, 'wide'));
        $t->contains('<h1 id="utf-8-repair">UTF-8 Repair</h1>', $blocks);
        $t->contains("<p>{$text}</p>", $blocks);

        $broken = UnicodeText::decodeBytes("Broken \xE2(\xA1 UTF-8");
        $t->same("Broken \u{FFFD}(\u{FFFD} UTF-8", $broken['text']);
        $t->same(2, $broken['repairs']);
        $t->same(['invalid-utf8-repaired:2'], $broken['diagnostics']);
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
    'normalizes unicode letterlike sign aliases with fallback data' => static function (TestRunner $t): void {
        $fallbackNfc = UnicodeText::normalize("\u{2126} \u{212A} \u{212B}", 'nfc', 'fallback');
        $fallbackNfd = UnicodeText::normalize("\u{2126} \u{212A} \u{212B}", 'nfd', 'fallback');
        $decoded = UnicodeText::decodeBytes("# \xE2\x84\xA6 \xE2\x84\xAA Review\n\nLegacy \xE2\x84\xAB source", 'utf-8', 'nfc');
        $document = (new MarkdownReader())->readBytes("# \xE2\x84\xA6 \xE2\x84\xAA Review\n\nLegacy \xE2\x84\xAB source", 'utf-8', 'nfc');

        $t->same("\u{03A9} K \u{00C5}", $fallbackNfc['text']);
        $t->same('nfc', $fallbackNfc['form']);
        $t->same(true, $fallbackNfc['changed']);
        $t->same('fallback', $fallbackNfc['implementation']);
        $t->same("\u{03A9} K A\u{030A}", $fallbackNfd['text']);
        $t->same('nfd', $fallbackNfd['form']);
        $t->same('fallback', $fallbackNfd['implementation']);
        $t->same("# \u{03A9} K Review\n\nLegacy \u{00C5} source", $decoded['text']);
        $t->same("\u{03A9} K Review", $document->children[0]->attr('text'));
        $t->same("Legacy \u{00C5} source", $document->children[1]->attr('text'));
        $t->same(['form' => 'nfc', 'changed' => true, 'implementation' => $decoded['normalization']['implementation']], $decoded['normalization']);
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
    'normalizes latin macron reviewer text with fallback unicode data' => static function (TestRunner $t): void {
        $decomposed = "A\u{0304}land a\u{0304}r; E\u{0304}ka e\u{0304}na; I\u{0304}re i\u{0304}sa; O\u{0304}saka o\u{0304}kami; U\u{0304}dens u\u{0304}pe";
        $composed = 'Āland ār; Ēka ēna; Īre īsa; Ōsaka ōkami; Ūdens ūpe';
        $fallbackNfc = UnicodeText::normalize($decomposed, 'nfc', 'fallback');
        $fallbackNfd = UnicodeText::normalize($composed, 'nfd', 'fallback');
        $decoded = UnicodeText::decodeBytes("# {$decomposed}\n\nReviewer {$decomposed}", 'utf-8', 'nfc');
        $document = (new MarkdownReader())->readBytes("# {$decomposed}\n\nReviewer {$decomposed}", 'utf-8', 'nfc');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same($composed, $fallbackNfc['text']);
        $t->same('nfc', $fallbackNfc['form']);
        $t->same('fallback', $fallbackNfc['implementation']);
        $t->same(true, $fallbackNfc['changed']);
        $t->same($decomposed, $fallbackNfd['text']);
        $t->same('nfd', $fallbackNfd['form']);
        $t->same('fallback', $fallbackNfd['implementation']);
        $t->same(true, $fallbackNfd['changed']);
        $t->same(UnicodeText::displayWidth($fallbackNfc['text']), UnicodeText::displayWidth($fallbackNfd['text']));
        $t->same("# {$composed}\n\nReviewer {$composed}", $decoded['text']);
        $t->same($composed, $document->children[0]->attr('text'));
        $t->same("Reviewer {$composed}", $document->children[1]->attr('text'));
        $t->contains("<p>Reviewer {$composed}</p>", $blocks);
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
    'measures south and southeast asian vowel signs as display clusters' => static function (TestRunner $t): void {
        $teluguKi = "\u{0C15}\u{0C3F}";
        $kannadaKe = "\u{0C95}\u{0CC6}";
        $malayalamKe = "\u{0D15}\u{0D46}";
        $sinhalaKaa = "\u{0D9A}\u{0DCF}";
        $laoKi = "\u{0EA5}\u{0EB4}";
        $clusterRun = $teluguKi . $kannadaKe . $malayalamKe . $sinhalaKaa . $laoKi;
        $text = $clusterRun . 'X';
        $wrapped = UnicodeText::wrapByDisplayWidth("Marks {$clusterRun} tail", 10, '  ');

        $t->same(1, UnicodeText::displayWidth($teluguKi));
        $t->same(1, UnicodeText::displayWidth($kannadaKe));
        $t->same(1, UnicodeText::displayWidth($malayalamKe));
        $t->same(1, UnicodeText::displayWidth($sinhalaKaa));
        $t->same(1, UnicodeText::displayWidth($laoKi));
        $t->same(6, UnicodeText::displayWidth($text));
        $t->same([$teluguKi, $kannadaKe, $malayalamKe, $sinhalaKaa, $laoKi, 'X'], UnicodeText::graphemes($text));
        $t->same([$teluguKi . $kannadaKe . $malayalamKe, $sinhalaKaa . $laoKi . 'X'], UnicodeText::splitAtDisplayWidth($text, 3));
        $t->same([$teluguKi, $kannadaKe, $malayalamKe, $sinhalaKaa, $laoKi, 'X'], UnicodeText::splitByDisplayBreakpoints($text, [1, 2, 3, 4, 5]));
        $t->same($sinhalaKaa . '   ', UnicodeText::padDisplay($sinhalaKaa, 4));
        $t->same(['Marks', '  ' . $clusterRun, '  tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 10, 'South Asian mark wrapped line exceeds requested width');
        }
    },
    'keeps indic virama conjuncts intact for display slicing' => static function (TestRunner $t): void {
        $devanagariKsha = "\u{0915}\u{094D}\u{0937}";
        $devanagariZwjKsha = "\u{0915}\u{094D}\u{200D}\u{0937}";
        $bengaliKta = "\u{0995}\u{09CD}\u{09A4}";
        $tamilKssa = "\u{0B95}\u{0BCD}\u{0BB7}";
        $text = $devanagariKsha . $devanagariZwjKsha . $bengaliKta . 'X';
        $wrapped = UnicodeText::wrapByDisplayWidth("Indic {$devanagariKsha}{$devanagariZwjKsha} {$bengaliKta} tail", 9, '  ');

        $t->same(1, UnicodeText::displayWidth($devanagariKsha));
        $t->same(1, UnicodeText::displayWidth($devanagariZwjKsha));
        $t->same(1, UnicodeText::displayWidth($bengaliKta));
        $t->same(1, UnicodeText::displayWidth($tamilKssa));
        $t->same([$devanagariKsha, $devanagariZwjKsha, $bengaliKta, 'X'], UnicodeText::graphemes($text));
        $t->same([$devanagariKsha, $devanagariZwjKsha . $bengaliKta . 'X'], UnicodeText::splitAtDisplayWidth($text, 1));
        $t->same([$devanagariKsha, $devanagariZwjKsha, $bengaliKta, 'X'], UnicodeText::splitByDisplayBreakpoints($text, [1, 2, 3]));
        $t->same($devanagariKsha . '   ', UnicodeText::padDisplay($devanagariKsha, 4));
        $t->same(['Indic ' . $devanagariKsha . $devanagariZwjKsha, '  ' . $bengaliKta . ' tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 9, 'Indic virama wrapped line exceeds requested width');
        }
    },
    'keeps myanmar and khmer conjuncts intact for display slicing' => static function (TestRunner $t): void {
        $myanmarKka = "\u{1000}\u{1039}\u{1000}";
        $khmerKka = "\u{1780}\u{17D2}\u{1780}";
        $text = $myanmarKka . $khmerKka . 'X';
        $wrapped = UnicodeText::wrapByDisplayWidth("SEA {$myanmarKka}{$khmerKka} tail", 8, '  ');

        $t->same(1, UnicodeText::displayWidth($myanmarKka));
        $t->same(1, UnicodeText::displayWidth($khmerKka));
        $t->same(3, UnicodeText::displayWidth($text));
        $t->same([$myanmarKka, $khmerKka, 'X'], UnicodeText::graphemes($text));
        $t->same([$myanmarKka, $khmerKka . 'X'], UnicodeText::splitAtDisplayWidth($text, 1));
        $t->same([$myanmarKka, $khmerKka, 'X'], UnicodeText::splitByDisplayBreakpoints($text, [1, 2]));
        $t->same($khmerKka . '   ', UnicodeText::padDisplay($khmerKka, 4));
        $t->same(['SEA ' . $myanmarKka . $khmerKka, '  tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 8, 'Myanmar/Khmer conjunct wrapped line exceeds requested width');
        }
    },
    'keeps javanese balinese and sundanese virama stacks intact for display slicing' => static function (TestRunner $t): void {
        $javaneseKna = "\u{A98F}\u{A9C0}\u{A9A4}";
        $balineseKsa = "\u{1B13}\u{1B44}\u{1B31}";
        $sundaneseKna = "\u{1B8A}\u{1BAA}\u{1B94}";
        $text = $javaneseKna . $balineseKsa . $sundaneseKna . 'X';
        $wrapped = UnicodeText::wrapByDisplayWidth("Stack {$javaneseKna}{$balineseKsa} {$sundaneseKna} tail", 9, '  ');

        $t->same(1, UnicodeText::displayWidth($javaneseKna));
        $t->same(1, UnicodeText::displayWidth($balineseKsa));
        $t->same(1, UnicodeText::displayWidth($sundaneseKna));
        $t->same(4, UnicodeText::displayWidth($text));
        $t->same([$javaneseKna, $balineseKsa, $sundaneseKna, 'X'], UnicodeText::graphemes($text));
        $t->same([$javaneseKna, $balineseKsa . $sundaneseKna . 'X'], UnicodeText::splitAtDisplayWidth($text, 1));
        $t->same([$javaneseKna, $balineseKsa, $sundaneseKna, 'X'], UnicodeText::splitByDisplayBreakpoints($text, [1, 2, 3]));
        $t->same($balineseKsa . '   ', UnicodeText::padDisplay($balineseKsa, 4));
        $t->same(['Stack ' . $javaneseKna . $balineseKsa, '  ' . $sundaneseKna . ' tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 9, 'Javanese/Balinese/Sundanese virama stack wrapped line exceeds requested width');
        }
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
    'wraps tibetan tsheg as a visible break after separator' => static function (TestRunner $t): void {
        $bod = "\u{0F56}\u{0F7C}\u{0F51}\u{0F0B}";
        $yig = "\u{0F61}\u{0F72}\u{0F42}\u{0F0B}";
        $dpe = "\u{0F51}\u{0F54}\u{0F7A}\u{0F0B}";
        $mdzod = "\u{0F58}\u{0F5B}\u{0F7C}\u{0F51}";
        $text = $bod . $yig . $dpe . $mdzod . ' tail';
        $mixed = $bod . 'review' . "\u{0F0B}" . 'queue';

        $t->same(17, UnicodeText::displayWidth($text));
        $t->same(15, UnicodeText::displayWidth($mixed));
        $t->same([
            $bod . $yig,
            '  ' . $dpe . $mdzod,
            '  tail',
        ], UnicodeText::wrapByDisplayWidth($text, 8, '  '));
        $t->same([
            $bod,
            '  review' . "\u{0F0B}",
            '  queue',
        ], UnicodeText::wrapByDisplayWidth($mixed, 9, '  '));
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
    'keeps multi person emoji zwj skin tone sequences as one display cluster' => static function (TestRunner $t): void {
        $handshake = "\u{1F9D1}\u{1F3FD}\u{200D}\u{1F91D}\u{200D}\u{1F9D1}\u{1F3FB}";
        $kiss = "\u{1F9D1}\u{1F3FD}\u{200D}\u{2764}\u{FE0F}\u{200D}\u{1F48B}\u{200D}\u{1F9D1}\u{1F3FF}";
        $text = $handshake . $kiss . 'X';
        $wrapped = UnicodeText::wrapByDisplayWidth("Emoji {$handshake} {$kiss} tail", 9, '  ');

        $t->same(2, UnicodeText::displayWidth($handshake));
        $t->same(2, UnicodeText::displayWidth($kiss));
        $t->same(5, UnicodeText::displayWidth($text));
        $t->same([$handshake, $kiss, 'X'], UnicodeText::graphemes($text));
        $t->same([$handshake, $kiss . 'X'], UnicodeText::splitAtDisplayWidth($text, 1));
        $t->same([$handshake, $kiss, 'X'], UnicodeText::splitByDisplayBreakpoints($text, [2, 4]));
        $t->same($kiss . '  ', UnicodeText::padDisplay($kiss, 4));
        $t->same(['Emoji ' . $handshake, '  ' . $kiss . ' tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 9, 'Emoji multi-skin ZWJ wrapped line exceeds requested width');
        }
    },
    'does not let plain zero width joiners collapse display columns' => static function (TestRunner $t): void {
        $plain = "A\u{200D}B";
        $cjk = "\u{9B5A}\u{200D}\u{9B5A}";
        $emoji = "\u{1F469}\u{200D}\u{1F4BB}";
        $indic = "\u{0915}\u{094D}\u{200D}\u{0937}";
        $wrapped = UnicodeText::wrapByDisplayWidth("Plain {$plain} CJK {$cjk} tail", 10, '  ');

        $t->same(["A\u{200D}", 'B'], UnicodeText::graphemes($plain));
        $t->same(["\u{9B5A}\u{200D}", "\u{9B5A}"], UnicodeText::graphemes($cjk));
        $t->same([$emoji], UnicodeText::graphemes($emoji));
        $t->same([$indic], UnicodeText::graphemes($indic));
        $t->same(2, UnicodeText::displayWidth($plain));
        $t->same(4, UnicodeText::displayWidth($cjk));
        $t->same(2, UnicodeText::displayWidth($emoji));
        $t->same(1, UnicodeText::displayWidth($indic));
        $t->same(["A\u{200D}", 'B'], UnicodeText::splitAtDisplayWidth($plain, 1));
        $t->same(["\u{9B5A}\u{200D}", "\u{9B5A}"], UnicodeText::splitAtDisplayWidth($cjk, 2));
        $t->same(["A\u{200D}", 'B'], UnicodeText::splitByDisplayBreakpoints($plain, [1]));
        $t->same(["\u{9B5A}\u{200D}", "\u{9B5A}"], UnicodeText::splitByDisplayBreakpoints($cjk, [2]));
        $t->same(["Plain {$plain}", "  CJK {$cjk}", '  tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 10, 'Plain ZWJ wrapped line exceeds requested width');
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
    'keeps yijing hexagram symbols narrow inside cjk display ranges' => static function (TestRunner $t): void {
        $hexagrams = "\u{4DC0}\u{4DDF}\u{4DFF}";
        $han = "\u{4E00}";
        $sample = $hexagrams . $han . 'X';
        $wrapped = UnicodeText::wrapByDisplayWidth("Hex {$hexagrams} {$han} tail", 9, '  ');

        $t->same(3, UnicodeText::displayWidth($hexagrams));
        $t->same(6, UnicodeText::displayWidth($sample));
        $t->same(["\u{4DC0}", "\u{4DDF}", "\u{4DFF}", $han, 'X'], UnicodeText::splitByDisplayBreakpoints($sample, [1, 2, 3, 5]));
        $t->same(["\u{4DC0}\u{4DDF}", "\u{4DFF}{$han}X"], UnicodeText::splitAtDisplayWidth($sample, 2));
        $t->same("\u{4DC0}   ", UnicodeText::padDisplay("\u{4DC0}", 4));
        $t->same(["Hex {$hexagrams}", "  {$han} tail"], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 9, 'Yijing hexagram wrapped line exceeds requested width');
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
    'measures east asian wide divination and counting symbols for display columns' => static function (TestRunner $t): void {
        $trigrams = "\u{2630}\u{2637}";
        $monogramsAndDigrams = "\u{268A}\u{268B}\u{268C}";
        $taiXuan = "\u{1D300}\u{1D306}\u{1D356}";
        $countingRods = "\u{1D360}\u{1D369}\u{1D376}";
        $sample = "\u{2630}\u{268A}\u{1D300}\u{1D360}X";
        $wrapped = UnicodeText::wrapByDisplayWidth("Audit {$sample} tail", 10, '  ');

        $t->same(4, UnicodeText::displayWidth($trigrams));
        $t->same(6, UnicodeText::displayWidth($monogramsAndDigrams));
        $t->same(6, UnicodeText::displayWidth($taiXuan));
        $t->same(6, UnicodeText::displayWidth($countingRods));
        $t->same(["\u{2630}", "\u{268A}", "\u{1D300}", "\u{1D360}", 'X'], UnicodeText::splitByDisplayBreakpoints($sample, [2, 4, 6, 8]));
        $t->same(["\u{2630}\u{268A}", "\u{1D300}\u{1D360}X"], UnicodeText::splitAtDisplayWidth($sample, 3));
        $t->same("  \u{1D360}", UnicodeText::padDisplay("\u{1D360}", 4, 'right'));
        $t->same(['Audit', "  \u{2630}\u{268A}\u{1D300}\u{1D360}", '  X tail'], $wrapped);
        foreach ($wrapped as $line) {
            $t->true(UnicodeText::displayWidth($line) <= 10, 'Divination/counting symbol wrapped line exceeds requested width');
        }

        $document = new AstNode('document', [], [
            new AstNode('table', [
                'alignments' => ['default', 'default'],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', ['header' => true], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Symbol'])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Width'])]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => "\u{2630}\u{268A}"])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => '4'])]),
                    ]),
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => "\u{1D300}\u{1D360}"])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => '4'])]),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '| Symbol | Width |',
            '|------|-----|',
            "| \u{2630}\u{268A}   | 4     |",
            "| \u{1D300}\u{1D360}   | 4     |",
        ]), (new MarkdownWriter())->write($document));
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
    'reports unicode line break opportunity metadata for width audits' => static function (TestRunner $t): void {
        $describe = static fn (array $row): string => implode(':', [
            $row['type'],
            $row['codepoint'],
            $row['break'],
            (string) $row['column'],
            (string) $row['columnAfter'],
            str_replace(["\n"], ['\\n'], $row['emitted'] ?? ''),
        ]);

        $softAudit = UnicodeText::lineBreakOpportunities("A\u{200B}B C\u{00AD}D");
        $t->same(3, $softAudit['opportunityCount']);
        $t->same(3, $softAudit['softBreakCount']);
        $t->same(0, $softAudit['hardBreakCount']);
        $t->same(0, $softAudit['protectedSeparatorCount']);
        $t->same([
            'zero-width-space:U+200B:soft:1:1:',
            'space:U+0020:soft:2:3: ',
            'soft-hyphen:U+00AD:soft:4:4:-',
        ], array_map($describe, $softAudit['opportunities']));

        $hardAudit = UnicodeText::lineBreakOpportunities("A\u{0F0B}B\u{2028}C\u{2029}D");
        $t->same(3, $hardAudit['opportunityCount']);
        $t->same(1, $hardAudit['softBreakCount']);
        $t->same(2, $hardAudit['hardBreakCount']);
        $t->same([
            'visible-break-after:U+0F0B:soft-after:1:2:་',
            'line-separator:U+2028:hard:3:3:\\n',
            'paragraph-separator:U+2029:hard:1:1:\\n\\n',
        ], array_map($describe, $hardAudit['opportunities']));

        $protectedAudit = UnicodeText::lineBreakOpportunities("A\u{00A0}B\u{202F}C\u{2007}D\u{2060}E");
        $t->same(0, $protectedAudit['opportunityCount']);
        $t->same(4, $protectedAudit['protectedSeparatorCount']);
        $t->same([
            'no-break-space:U+00A0:1:2',
            'narrow-no-break-space:U+202F:3:4',
            'figure-space:U+2007:5:6',
            'word-joiner:U+2060:7:7',
        ], array_map(
            static fn (array $row): string => $row['type'] . ':' . $row['codepoint'] . ':' . $row['column'] . ':' . $row['columnAfter'],
            $protectedAudit['protectedSeparators']
        ));

        $lineEndingAudit = UnicodeText::lineBreakOpportunities("A\r\nB\rC");
        $t->same(['normalized' => true, 'crlf' => 1, 'cr' => 1, 'conversions' => 2], $lineEndingAudit['lineEndings']);
        $t->same(2, $lineEndingAudit['opportunityCount']);
        $t->same(2, $lineEndingAudit['hardBreakCount']);
        $t->same([
            'line-feed:U+000A:hard:1:1:\\n',
            'line-feed:U+000A:hard:1:1:\\n',
        ], array_map($describe, $lineEndingAudit['opportunities']));

        $narrow = UnicodeText::lineBreakOpportunities("\u{00B7} \u{03A9}");
        $wide = UnicodeText::lineBreakOpportunities("\u{00B7} \u{03A9}", 'wide');
        $t->same(1, $narrow['opportunities'][0]['column']);
        $t->same(2, $wide['opportunities'][0]['column']);
        $t->same(3, $wide['opportunities'][0]['columnAfter']);
        $t->same(["A\u{0F0B}", '  B'], UnicodeText::wrapByDisplayWidth("A\u{0F0B}B", 2, '  '));
        $t->same(['keep', "  A\u{00A0}B"], UnicodeText::wrapByDisplayWidth("keep A\u{00A0}B", 7, '  '));
    },
    'keeps line and paragraph separators zero width in display accounting' => static function (TestRunner $t): void {
        $lineSeparator = "\u{2028}";
        $paragraphSeparator = "\u{2029}";
        $sample = "A{$lineSeparator}B{$paragraphSeparator}\u{9B5A}";

        $t->same(0, UnicodeText::displayWidth($lineSeparator));
        $t->same(0, UnicodeText::displayWidth($paragraphSeparator));
        $t->same(4, UnicodeText::displayWidth($sample));
        $t->same(['A', "{$lineSeparator}B{$paragraphSeparator}\u{9B5A}"], UnicodeText::splitAtDisplayWidth($sample, 1));
        $t->same(['A', "{$lineSeparator}B", "{$paragraphSeparator}\u{9B5A}"], UnicodeText::splitByDisplayBreakpoints($sample, [1, 2]));
        $t->same($sample . '  ', UnicodeText::padDisplay($sample, 6));
        $t->same(6, UnicodeText::displayWidth(UnicodeText::padDisplay($sample, 6)));
        $t->same(['A', 'B', "\u{9B5A}"], UnicodeText::wrapByDisplayWidth($sample, 10, '  '));
    },
    'expands tabs to four column stops for display width accounting' => static function (TestRunner $t): void {
        $tabbed = "A\tB\t\u{9B5A}";

        $t->same(4, UnicodeText::displayWidth("\t"));
        $t->same(5, UnicodeText::displayWidth("A\tB"));
        $t->same(9, UnicodeText::displayWidth("ABCD\tE"));
        $t->same(10, UnicodeText::displayWidth($tabbed));
        $t->same(["A\t", "B\t", "\u{9B5A}"], UnicodeText::splitByDisplayBreakpoints($tabbed, [4, 8]));
        $t->same(["A\t", "B"], UnicodeText::splitAtDisplayWidth("A\tB", 2));
        $t->same("A\tB ", UnicodeText::padDisplay("A\tB", 6));
        $t->same(6, UnicodeText::displayWidth(UnicodeText::padDisplay("A\tB", 6)));

        $document = new AstNode('document', [], [
            new AstNode('table', [
                'alignments' => ['default', 'default'],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', ['header' => true], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Label'])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Value'])]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => "A\tB"])]),
                        new AstNode('table_cell', [], [new AstNode('text', ['text' => 'ok'])]),
                    ]),
                ]),
            ]),
        ]);

        $t->same(implode("\n", [
            '| Label | Value |',
            '|-----|-----|',
            "| A\tB | ok    |",
        ]), (new MarkdownWriter())->write($document));
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
        $t->same(['A', "{$arabicPound}B"], UnicodeText::splitAtDisplayWidth("A{$arabicPound}B", 1));
        $t->same(["Audit {$arabicNumber}رقم", '  tail'], UnicodeText::wrapByDisplayWidth("Audit {$arabicNumber}رقم tail", 9, '  '));
        $t->same(" {$arabicNumber}رقم", UnicodeText::padDisplay("{$arabicNumber}رقم", 4, 'right'));
    },
    'keeps prepended format controls attached to following display clusters' => static function (TestRunner $t): void {
        $arabicNumber = "\u{0600}";
        $syriacAbbrev = "\u{070F}";
        $kaithiNumber = "\u{110BD}";
        $egyptianHieroglyph = "\u{13430}";
        $sample = "A{$arabicNumber}رق{$syriacAbbrev}ܣ{$kaithiNumber}ka";
        $trailingPrepend = "A{$arabicNumber}";

        $t->same(6, UnicodeText::displayWidth($sample));
        $t->same(['A', "{$arabicNumber}ر"], UnicodeText::splitAtDisplayWidth("A{$arabicNumber}ر", 1));
        $t->same(['A', "{$arabicNumber}ر", 'ق', "{$syriacAbbrev}ܣ", "{$kaithiNumber}k", 'a'], UnicodeText::graphemes($sample));
        $t->same(['A', "{$arabicNumber}ر", 'ق', "{$syriacAbbrev}ܣ", "{$kaithiNumber}k", 'a'], UnicodeText::splitByDisplayBreakpoints($sample, [1, 2, 3, 4, 5]));
        $t->same(['X', "{$egyptianHieroglyph}Y"], UnicodeText::splitAtDisplayWidth("X{$egyptianHieroglyph}Y", 1));
        $t->same(["A{$arabicNumber}"], UnicodeText::graphemes($trailingPrepend));
        $t->same(["A{$arabicNumber}", ''], UnicodeText::splitAtDisplayWidth($trailingPrepend, 1));
        $t->same("  {$kaithiNumber}ka", UnicodeText::padDisplay("{$kaithiNumber}ka", 4, 'right'));
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
