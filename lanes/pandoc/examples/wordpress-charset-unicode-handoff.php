<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\UnicodeText;
use PortLibs\Pandoc\WordPressBlockWriter;

$utf16leBytes = static function (array $codepoints): string {
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
$latin3Bytes = "# Latin3 Import\n\nMalti \xA1\xB1 u \xD5\xF5; Esperanto \xC6\xE6 \xD8\xF8 \xDD\xFD \xDE\xFE; Turk \xA9\xB9; \xAF\xBF.";
$latin3Source = (new MarkdownReader())->readBytes($latin3Bytes, 'iso-ir-109');
$latin3Text = (string) $latin3Source->children[1]->attr('text');
$latin4Bytes = "# Latin4 Import\n\nBaltic \xC0\xE0 \xD3\xF3 \xD1\xF1 \xA9\xB9 \xAE\xBE; \xBD\xBF.";
$latin4Source = (new MarkdownReader())->readBytes($latin4Bytes, 'latin4');
$latin4Text = (string) $latin4Source->children[1]->attr('text');
$latin6Bytes = "# Latin6 Import\n\nNordic \xA6\xB6 \xAB\xBB; Sami \xAF\xBF\xFF; Baltic \xA1\xB1 \xA2\xB2 \xAE\xBE; \xBD and \xD7\xF7.";
$latin6Source = (new MarkdownReader())->readBytes($latin6Bytes, 'latin6');
$latin6Text = (string) $latin6Source->children[1]->attr('text');
$latin7Bytes = "# Latin7 Import\n\nBaltic \xC2\xE2 \xD1\xF1 \xD2\xF2 \xD8\xF8 \xDA\xFA \xDD\xFD \xFE; quotes \xA5\xB4text\xA1\xFF.";
$latin7Source = (new MarkdownReader())->readBytes($latin7Bytes, 'latin7');
$latin7Text = (string) $latin7Source->children[1]->attr('text');
$latin8Bytes = "# Latin8 Import\n\nCeltic \xC0\xE0 \xD0\xF0 \xDE\xFE; dotted \xA1\xA2 \xA4\xA5 \xAA\xBA \xBB\xBF; Welsh \xD7\xF7.";
$latin8Source = (new MarkdownReader())->readBytes($latin8Bytes, 'latin8');
$latin8Text = (string) $latin8Source->children[1]->attr('text');
$latin10Bytes = "# Latin10 Import\n\nRomanian \xAA\xBA \xDE\xFE; Central \xD7\xF7 \xD8\xF8 \xDD\xFD; Euro \xA4; quotes \xA5text\xB5.";
$latin10Source = (new MarkdownReader())->readBytes($latin10Bytes, 'iso-ir-226');
$latin10Text = (string) $latin10Source->children[1]->attr('text');
$windows1251Bytes = "# \xC8\xEC\xEF\xEE\xF0\xF2\n\n\xD0\xE5\xE4\xE0\xEA\xF2\xEE\xF0 \x93\xEF\xF0\xE8\xE2\xE5\xF2\x94 \x97 \x8810; \xA8\xEB\xEA\xE0 \xB9 7.";
$windows1251Source = (new MarkdownReader())->readBytes($windows1251Bytes, 'cp1251');
$windows1251Text = (string) $windows1251Source->children[1]->attr('text');
$koi8RBytes = "# \xE9\xCD\xD0\xCF\xD2\xD4\n\n\xF2\xC5\xC4\xC1\xCB\xD4\xCF\xD2 \xD0\xD2\xC9\xD7\xC5\xD4; \xB3\xCC\xCB\xC1; \x82\x80\x83.";
$koi8RSource = (new MarkdownReader())->readBytes($koi8RBytes, 'cskoi8r');
$koi8RText = (string) $koi8RSource->children[1]->attr('text');
$koi8UBytes = "# \xF5\xCB\xD2\xC1\xA7\xCE\xC1\n\n\xF2\xC5\xC4\xC1\xCB\xD4\xCF\xD2 \xEB\xC9\xA7\xD7; \xA7\xD6\xC1\xCB \xA6 \xAD\xC1\xCE\xCF\xCB; \xB4\xB6\xB7\xBD.";
$koi8USource = (new MarkdownReader())->readBytes($koi8UBytes, 'koi8-u');
$koi8UText = (string) $koi8USource->children[1]->attr('text');
$koi8RuBytes = "# \xE2\xC5\xCC\xC1\xD2\xD5\xD3\xD8\n\n\xF2\xC5\xC4\xC1\xCB\xD4\xCF\xD2 \xED\xA6\xCE\xD3\xCB; \xE2\xC5\xCC\xC1\xD2\xD5\xD3\xD8: \xBE\xAE; \xF5\xCB\xD2\xC1\xA7\xCE\xC1 \xB4\xB6\xB7\xBD.";
$koi8RuSource = (new MarkdownReader())->readBytes($koi8RuBytes, 'koi8-ru');
$koi8RuText = (string) $koi8RuSource->children[1]->attr('text');
$koi8TBytes = "# \xF4\xCF\x8D\xC9\xCB\xC9\xD3\xD4\xCF\xCE\n\n\xED\xC1\xD4\xCE \x93\xD4\xCF\x8D\xC9\xCB\xA5\x94 \x97 \xB9 7; \x83\xC1\xC6\xD5\xD2; \x90\xA1\x80\xCF\xCE; \xA2\x8C\x8E\x8D.";
$koi8TSource = (new MarkdownReader())->readBytes($koi8TBytes, 'koi8-tajik');
$koi8TText = (string) $koi8TSource->children[1]->attr('text');
$macCyrillicBytes = "# \x88\xEC\xEF\xEE\xF0\xF2\n\n\x90\xE5\xE4\xE0\xEA\xF2\xEE\xF0 \xD2\xEF\xF0\xE8\xE2\xE5\xF2\xD3 \xD1 \xFF20; \xDD\xEB\xEA\xE0 \xDC 7; \xBA\xBB\xB8\xB9.";
$macCyrillicSource = (new MarkdownReader())->readBytes($macCyrillicBytes, 'x-mac-cyrillic');
$macCyrillicText = (string) $macCyrillicSource->children[1]->attr('text');
$macUkrainianBytes = "# \x93\xEA\xF0\xE0\xBB\xED\xE0\n\n\x90\xE5\xE4\xE0\xEA\xF2\xEE\xF0 \x8A\xE8\xBB\xE2; \xBA\xE6\xE0\xEA \xB6\xE0\xED\xEE\xEA; \xB8\xA7\xBA\xA2; currency \xFF20.";
$macUkrainianSource = (new MarkdownReader())->readBytes($macUkrainianBytes, 'x-mac-ukrainian');
$macUkrainianText = (string) $macUkrainianSource->children[1]->attr('text');
$macGreekBytes = "# \xB6\xEC\xEC\xC0\xE4\xE1\n\n\xAA\xF9\xEE\xF4\xC0\xEB\xF4\xE8\xF7 \xD2\xF0\xE8\xE7\xDC\xD3 \xD1 \xA9 20; \xD9\xDF \xFD\xFE; \xFF.";
$macGreekSource = (new MarkdownReader())->readBytes($macGreekBytes, 'x-mac-greek');
$macGreekText = (string) $macGreekSource->children[1]->attr('text');
$macIcelandBytes = "# Mac Iceland\n\nRitstj\x97ri \xD2\x92sland\xD3 \xD1 \xDB20; \xDEorn og \xDDa\xE0; \xDC/\xDD, \xA0/\xE0; \xD5.";
$macIcelandSource = (new MarkdownReader())->readBytes($macIcelandBytes, 'x-mac-icelandic');
$macIcelandText = (string) $macIcelandSource->children[1]->attr('text');
$macCentralBytes = "# Mac Central\n\nCzech \x89esk\x8E \xE4kola \xDBeka; Polish Za\xFD\x97\xB8\x8D g\xAB\xE6l\x88 ja\x90\xC4; Hungarian \xCC\xCE \xF4\xF5; quotes \xD2text\xD3 \xD1 \xA310.";
$macCentralSource = (new MarkdownReader())->readBytes($macCentralBytes, 'x-mac-cent-euro');
$macCentralText = (string) $macCentralSource->children[1]->attr('text');
$macRomaniaBytes = "# Mac Romania\n\nEditor \xD2rom\x89n\xBE\xD3 \xD1 Bra\xBFov; \xDEar\xBE \xBFi fa\xDF\xBE; cost \xDB10; \xBD.";
$macRomaniaSource = (new MarkdownReader())->readBytes($macRomaniaBytes, 'x-mac-romanian');
$macRomaniaText = (string) $macRomaniaSource->children[1]->attr('text');
$macCroatianBytes = "# Mac Croatian\n\nNovinar \xD2\xA9ibenik\xD3 \xD1 \xC6evapi; \xAEupanija, \xB9uma, \xBEar; \xC6\xC8\xD0/\xE6\xE8\xF0.";
$macCroatianSource = (new MarkdownReader())->readBytes($macCroatianBytes, 'x-mac-croatian');
$macCroatianText = (string) $macCroatianSource->children[1]->attr('text');
$macDingbatsBytes = "\x21\x22\x23 \x33\x34 \x48 \xA8\xAA\xAB \xAC\xB6\xBF \xD5\xD6\xD7 \xE0\xEF \x80.";
$macDingbatsSource = (new MarkdownReader())->readBytes($macDingbatsBytes, 'x-mac-dingbats');
$macDingbatsText = (string) $macDingbatsSource->children[0]->attr('text');
$macSymbolBytes = "A B G W a b g w \xB3 \xB9\xBA\xBB \xD5\xD6\xE5 \xF2 \xF0.";
$macSymbolSource = (new MarkdownReader())->readBytes($macSymbolBytes, 'x-mac-symbol');
$macSymbolText = (string) $macSymbolSource->children[0]->attr('text');
$ibm855Bytes = "# DOS 855\n\n\xE2\xA8\xA6\xA0\xC6\xE5\xD6\xE1 \xD8\xE1\xB7\xEB\xA8\xE5; \x85\xD0\xC6\xA0; \x91\x90 \x93\x92; box \xB3\xC4\xDA; \xEF\xFD.";
$ibm855Source = (new MarkdownReader())->readBytes($ibm855Bytes, 'cp855');
$ibm855Text = (string) $ibm855Source->children[1]->attr('text');
$ibm869Bytes = "# \xA8\xE5\xE5\xE1\xE7\xE3\xE4\x9B\n\n\xCF\xF2\xE7\xEE\x9B\xE4\xEE\xE1\xED \xAB\xEA\xE1\xD8\x9E\xAF; \x86\x88\x8D\x8F\x90\x92\x95\x98; \xDA\xC4\xBF.";
$ibm869Source = (new MarkdownReader())->readBytes($ibm869Bytes, 'cp869');
$ibm869Text = (string) $ibm869Source->children[1]->attr('text');
$ibm737Bytes = "# DOS 737\n\n\x84\xA2\xA2\x9E\xA4\xA0\xA1\xE1 CP737: \x98\x99\x9A\x9B\x9C; \xEA\xEB\xEC\xED\xEE\xEF\xF0; box \xB3\xC4\xDA; math \xF1\xF2\xF3; \xFF.";
$ibm737Source = (new MarkdownReader())->readBytes($ibm737Bytes, 'cp737');
$ibm737Text = (string) $ibm737Source->children[1]->attr('text');
$ibm437Bytes = "# DOS 437\n\nBox \xC9\xCD\xBB\xBA\xCC; r\x82sum\x82; \xE0\xE1 \xF8\xF1.";
$ibm437Source = (new MarkdownReader())->readBytes($ibm437Bytes, 'cp437');
$ibm437Text = (string) $ibm437Source->children[1]->attr('text');
$ibm850Bytes = "# DOS 850\n\nEspa\xA4ol Fran\x87ais; \xB5rvore e \xD5zmir; fractions \xAB\xAC\xF3; box \xC9\xCD\xBB; \xF2.";
$ibm850Source = (new MarkdownReader())->readBytes($ibm850Bytes, 'cspc850multilingual');
$ibm850Text = (string) $ibm850Source->children[1]->attr('text');
$ibm857Bytes = "# DOS 857\n\nT\x81rkiye \x98stanbul; \xA6a\xA7, \x9Ei\x9Fli; box \xC9\xCD\xBB; \xF5.";
$ibm857Source = (new MarkdownReader())->readBytes($ibm857Bytes, 'csibm857');
$ibm857Text = (string) $ibm857Source->children[1]->attr('text');
$ibm862Bytes = "# DOS 862\n\nHebrew \x92\x81\x98\x89\x9A: \x99\x8C\x85\x8D \x8E\x97\x85\x98; box \xC9\xCD\xBB; Latin \xA0\xA1.";
$ibm862Source = (new MarkdownReader())->readBytes($ibm862Bytes, 'csibm862');
$ibm862Text = (string) $ibm862Source->children[1]->attr('text');
$ibm864Bytes = "# DOS 864\n\nArabic \xC7\xE4\xDF\xD1\xC8\xEA\xC9; digits \xB1\xB2\xB3; lam-alef \x9D\x9E; marks \xF0\xF1; box \x8D\x85\x8C; soft\xA1hyphen.";
$ibm864Source = (new MarkdownReader())->readBytes($ibm864Bytes, 'csibm864');
$ibm864Text = (string) $ibm864Source->children[1]->attr('text');
$cp165Bytes = "# CP165 Arabic\n\nArabic \xC7\xE4\xDF\xD1\xC8\xEA\xC9; percent \x2420; lam-alef \x9D\x9E; extras \x9B\x9C\x9F\xA6\xA7\xFF.";
$cp165Source = (new MarkdownReader())->readBytes($cp165Bytes, 'csibm165');
$cp165Text = (string) $cp165Source->children[1]->attr('text');
$ibm852Bytes = "# DOS 852\n\nCzech \xAC\x9F \xB7\xD8 \xE6\xE7 \xA6\xA7 \xFC\xFD; Polish \x9D\x88 \xA4\xA5 \xBD\xBE; Hungarian \x8A\x8B \xEB\xFB; box \xC9\xCD\xBB; \xF1.";
$ibm852Source = (new MarkdownReader())->readBytes($ibm852Bytes, 'cspc852');
$ibm852Text = (string) $ibm852Source->children[1]->attr('text');
$ibm860Bytes = "# Importa\x87\x84o\n\nPortugu\x88s: Conte\xA3do, \x8Cnibus, S\x84o Tom\x82, a\x87\xA3car; \xAEcita\x87\x84o\xAF; \x9C/\x9E.";
$ibm860Source = (new MarkdownReader())->readBytes($ibm860Bytes, 'cp860');
$ibm860Text = (string) $ibm860Source->children[1]->attr('text');
$ibm861Bytes = "# DOS 861\n\nIcelandic: \xA4\xA1 \xA5sland, \x8Dingvellir, \x8B/\x8C, \x95orn; vowels \xA0\xA1\xA2\xA3 \xA4\xA5\xA6\xA7; box \xC9\xCD\xBB; \x9C.";
$ibm861Source = (new MarkdownReader())->readBytes($ibm861Bytes, 'cp861');
$ibm861Text = (string) $ibm861Source->children[1]->attr('text');
$ibm865Bytes = "# DOS 865\n\nDansk: K\x9Bbenhavn, sm\x9Brrebr\x9Bd, bl\x86b\x91r; Norsk: \x92\x9D\x8F; Islandsk: \xD1\xD0 \xE8\xE7; \xAF.";
$ibm865Source = (new MarkdownReader())->readBytes($ibm865Bytes, 'cp865');
$ibm865Text = (string) $ibm865Source->children[1]->attr('text');
$ibm775Bytes = "# DOS 775\n\nBaltic \xA0\x83 \xED\x89 \xA1\x8C \xE2\x93; Latvian \x95\x85 \xE8\xE9 \xEA\xEB \xEE\xEC; Lithuanian \xB5\xD0 \xB6\xD1 \xB7\xD2 \xB8\xD3 \xBD\xD4 \xBE\xD5 \xC6\xD6 \xC7\xD7 \xCF\xD8; quotes \xF2avots\xA6 \xF7zems\xA6; box \xC9\xCD\xBB; soft\xF0hyphen\xFFtail.";
$ibm775Source = (new MarkdownReader())->readBytes($ibm775Bytes, 'cp775');
$ibm775Text = (string) $ibm775Source->children[1]->attr('text');
$ibm863Bytes = "# DOS 863\n\nQu\x82bec H\x93tel; co\x96t; \x90t\x82; fractions \xAB\xAC\xAD; monnaie \x9B\x9C\x98; box \xC9\xCD\xBB; \x8D.";
$ibm863Source = (new MarkdownReader())->readBytes($ibm863Bytes, 'cp863');
$ibm863Text = (string) $ibm863Source->children[1]->attr('text');
$iso88595Bytes = "# \xB8\xDC\xDF\xDE\xE0\xE2\n\n\xC0\xD5\xD4\xD0\xDA\xE2\xDE\xE0 \xDF\xE0\xD8\xD2\xD5\xE2; \xA1\xDB\xDA\xD0 \xF0 7.";
$iso88595Source = (new MarkdownReader())->readBytes($iso88595Bytes, 'iso-ir-144');
$iso88595Text = (string) $iso88595Source->children[1]->attr('text');
$iso88596Bytes = "# \xC7\xE4\xD9\xD1\xC8\xEA\xC9\n\n\xE5\xCD\xD1\xD1 \xD9\xD1\xC8\xEA\xC9\xAC \xD3\xC4\xC7\xE4\xBB \xE7\xE4\xBF";
$iso88596Source = (new MarkdownReader())->readBytes($iso88596Bytes, 'iso-ir-127');
$iso88596Text = (string) $iso88596Source->children[1]->attr('text');
$windows1256Bytes = "# \xC7\xE1\xDA\xD1\xC8\xED\xC9\n\n\xE3\xCD\xD1\xD1 \x93\xDA\xD1\xC8\xED\xC9\x94 \x97 \x8020; \xDD\xC7\xD1\xD3\xED: \x81\x8D\x8E\x90 \x98\xBA \xC7\xD1\xCF\xE6: \x9A\x9F\xFF.";
$windows1256Source = (new MarkdownReader())->readBytes($windows1256Bytes, 'cp1256');
$windows1256Text = (string) $windows1256Source->children[1]->attr('text');
$macArabicBytes = "# Mac Arabic\n\nLegacy \xD9\xD1\xC8\xEA\xC9 \x8C\xCE\xC8\xD1\x98 \xAD \xA520; Persian \xF3\xF5\xF7\xF8; digits \xB1\xB2\xB3; punctuation \xAC\xBB\xBF.";
$macArabicSource = (new MarkdownReader())->readBytes($macArabicBytes, 'x-mac-arabic');
$macArabicText = (string) $macArabicSource->children[1]->attr('text');
$xUserDefinedBytes = "# Private Glyphs\n\nLegacy \x80\x81\xFE\xFF source.";
$xUserDefinedSource = (new MarkdownReader())->readBytes($xUserDefinedBytes, 'x-user-defined');
$xUserDefinedText = (string) $xUserDefinedSource->children[1]->attr('text');
$iso88597Bytes = "# \xC5\xEB\xEB\xE7\xED\xE9\xEA\xDC\n\n\xD3\xF5\xED\xF4\xDC\xEA\xF4\xE7\xF2 \xAB\xEA\xE5\xDF\xEC\xE5\xED\xEF\xBB \xAF \xA420; \xD4\xFC\xED\xEF\xF2 \xEA\xE1\xE9 \xEF\xF2.";
$iso88597Source = (new MarkdownReader())->readBytes($iso88597Bytes, 'iso-ir-126');
$iso88597Text = (string) $iso88597Source->children[1]->attr('text');
$windows1253Bytes = "# \xC5\xEB\xEB\xE7\xED\xE9\xEA\xDC\n\n\xD3\xF5\xED\xF4\xDC\xEA\xF4\xE7\xF2 \x93\xEA\xE5\xDF\xEC\xE5\xED\xEF\x94 \x97 \x8020; \xD4\xFC\xED\xEF\xF2.";
$windows1253Source = (new MarkdownReader())->readBytes($windows1253Bytes, 'cp1253');
$windows1253Text = (string) $windows1253Source->children[1]->attr('text');
$iso88598Bytes = "# \xF2\xE1\xF8\xE9\xFA\n\n\xF2\xE5\xF8\xEA \xF2\xE1\xF8\xE9\xFA \xAB\xEE\xF7\xE5\xF8\xBB \xDF 12; \xFERTL.";
$iso88598Source = (new MarkdownReader())->readBytes($iso88598Bytes, 'iso-ir-138');
$iso88598Text = (string) $iso88598Source->children[1]->attr('text');
$windows1255Bytes = "# \xF2\xE1\xF8\xE9\xFA\n\n\xF2\xE5\xF8\xEA \xF9\xC8\xD1\xEC\xE5\xC9\xED \x93\xEE\xF7\xE5\xF8\x94 \x97 \xA420; \xD7\xD8.";
$windows1255Source = (new MarkdownReader())->readBytes($windows1255Bytes, 'cp1255');
$windows1255Text = (string) $windows1255Source->children[1]->attr('text');
$iso88599Bytes = "# Latin5 Import\n\nTurkish \xDDstanbul, \xD0a\xF0, \xDEi\xFEli, \xFDl\xFDk; \xD6\xDC remain.";
$iso88599Source = (new MarkdownReader())->readBytes($iso88599Bytes, 'latin5');
$iso88599Text = (string) $iso88599Source->children[1]->attr('text');
$windows1254Bytes = "# Windows Turkish\n\nYazar \x93\xDDstanbul\x94 \x97 \x8010; \xD0a\xF0, \xDEi\xFEli, \xFDl\xFDk; \xD6\xDC remain.";
$windows1254Source = (new MarkdownReader())->readBytes($windows1254Bytes, 'cp1254');
$windows1254Text = (string) $windows1254Source->children[1]->attr('text');
$macTurkishBytes = "# Mac Turkish\n\nYazar \xD2\xDCstanbul\xD3 \xD1 \x82a\xDB; \xDEi\xDFli, \xDDl\xDDk; \xDA\xDB \xF5.";
$macTurkishSource = (new MarkdownReader())->readBytes($macTurkishBytes, 'x-mac-turkish');
$macTurkishText = (string) $macTurkishSource->children[1]->attr('text');
$tis620Bytes = "# \xE4\xB7\xC2\n\n\xE0\xB9\xD7\xE9\xCD\xCB\xD2 \xE0\xCD\xA1\xCA\xD2\xC3.";
$tis620Source = (new MarkdownReader())->readBytes($tis620Bytes, 'tis-620');
$tis620Text = (string) $tis620Source->children[1]->attr('text');
$macThaiBytes = "# Mac Thai\n\n\xE0\xB9\xD7\xE9\xCD\xCB\xD2 \xE0\xCD\xA1\xCA\xD2\xC3; \x80\x81 \x8Dtext\x8E \xDD \xDF20; \xDB\xDC.";
$macThaiSource = (new MarkdownReader())->readBytes($macThaiBytes, 'x-mac-thai');
$macThaiText = (string) $macThaiSource->children[1]->attr('text');
$shiftJisBytes = (string) hex2bin('23208c7689e60a0a967b95b682c694bc8a70b6c0b6c581418adb874094678160fbfc8de88142');
$shiftJisSource = (new MarkdownReader())->readBytes($shiftJisBytes, 'windows-31j');
$shiftJisText = (string) $shiftJisSource->children[1]->attr('text');
$eucJpBytes = (string) hex2bin('2320b7d7b2e80a0acbdccab8a4c8c8beb3d18eb68ec08eb68ec5a1a2b4ddada1c7c8a1c1baeaa1a3');
$eucJpSource = (new MarkdownReader())->readBytes($eucJpBytes, 'x-euc-jp');
$eucJpText = (string) $eucJpSource->children[1]->attr('text');
$eucJpPlane2Bytes = "# JIS0212\n\nPlane2 \x8F\xA9\xA1\x8F\xA9\xAD; \x8F\xA7\xC4\x8F\xA7\xF4; \x8F\xA6\xF1\x8F\xA6\xF7.";
$eucJpPlane2Source = (new MarkdownReader())->readBytes($eucJpPlane2Bytes, 'euc-jp');
$eucJpPlane2Text = (string) $eucJpPlane2Source->children[1]->attr('text');
$iso2022JpPlane2Bytes = "# ISO2022 JIS0212\n\nPlane2 "
    . "\x1B\x24\x28\x44\x29\x21\x29\x2D\x1B\x28\x42; "
    . "\x1B\x24\x28\x44\x27\x44\x27\x74\x1B\x28\x42; "
    . "\x1B\x24\x28\x44\x26\x71\x26\x77\x1B\x28\x42.";
$iso2022JpPlane2Source = (new MarkdownReader())->readBytes($iso2022JpPlane2Bytes, 'iso-2022-jp');
$iso2022JpPlane2Text = (string) $iso2022JpPlane2Source->children[1]->attr('text');
$iso2022JpBytes = "# \x1B\$B\x37\x57\x32\x68\x1B(B\n\n"
    . "\x1B\$B\x4B\x5C\x4A\x38\x24\x48\x48\x3E\x33\x51\x1B(I\x36\x40\x36\x45"
    . "\x1B\$B\x21\x22\x34\x5D\x2D\x21\x47\x48\x21\x41\x3A\x6A\x21\x23"
    . "\x1B(J \x5C\x7E\x1B(B ASCII";
$iso2022JpSource = (new MarkdownReader())->readBytes($iso2022JpBytes, 'csiso2022jp');
$iso2022JpText = (string) $iso2022JpSource->children[1]->attr('text');
$iso2022JpTruncatedBytes = "# \x1B\$B\x37\x57\x32\x68\x1B(B\n\n\x1B\$B\x4B\x5C\x4A\x38";
$iso2022JpTruncatedSource = (new MarkdownReader())->readBytes($iso2022JpTruncatedBytes, 'iso-2022-jp');
$iso2022JpTruncatedText = (string) $iso2022JpTruncatedSource->children[1]->attr('text');
$macJapaneseBytes = "# Mac Japanese\n\nLegacy \xB6\xC0\xB6\xC5 \x81\x41\x81\x42 \x82\xA0\x82\xA2\x82\xA4 \x83\x41\x83\x43\x83\x45 \xFD\xFE\xFF.";
$macJapaneseSource = (new MarkdownReader())->readBytes($macJapaneseBytes, 'x-mac-japanese');
$macJapaneseText = (string) $macJapaneseSource->children[1]->attr('text');
$big5Bytes = (string) hex2bin('2320a4a4a4e50a0aa4a4a4e5204269673520b4fab8d5a141adbbb4e4a143');
$big5Source = (new MarkdownReader())->readBytes($big5Bytes, 'big5-hkscs');
$big5Text = (string) $big5Source->children[1]->attr('text');
$big5PointerText = UnicodeText::decodeBytes("\x88\x62\x88\x64\x88\xA3\x88\xA5", 'big5')['text'];
$big5PunctuationBytes = "# Big5 Punctuation\n\n\xA1\x40\xA1\x75\xA4\xA4\xA4\xE5\xA1\x76\xA1\xA7quote\xA1\xA8\xA1\x48\xA1\x49\xA1\x46\xA1\x47\xA1\x42\xA1\x43\xA1\xB0\xA1\xB1\xA1\xB2 \xA1\x45.";
$big5PunctuationSource = (new MarkdownReader())->readBytes($big5PunctuationBytes, 'big5-hkscs');
$big5PunctuationText = (string) $big5PunctuationSource->children[1]->attr('text');
$big5KanaBytes = "# Big5 Kana\n\nKana \xC6\xA1\xC6\xA2\xC6\xA3\xC6\xA4 \xC6\xA5\xC6\xA6; digits \xA2\xAF\xA2\xB0\xA2\xB1.";
$big5KanaSource = (new MarkdownReader())->readBytes($big5KanaBytes, 'big5-hkscs');
$big5KanaText = (string) $big5KanaSource->children[1]->attr('text');
$big5A3Bytes = "# Big5 A3\n\nGreek \xA3\x44\xA3\x50\xA3\x5B \xA3\x5C\xA3\x73; bopomofo \xA3\x74\xA3\x75\xA3\x7E.";
$big5A3Source = (new MarkdownReader())->readBytes($big5A3Bytes, 'cn-big5');
$big5A3Text = (string) $big5A3Source->children[1]->attr('text');
$cp950Bytes = "# CP950\n\nCP950 Euro \xA3\xE1 glyphs \xF9\xD6\xF9\xD7 box \xF9\xDD\xF9\xDE\xF9\xDF.";
$cp950Source = (new MarkdownReader())->readBytes($cp950Bytes, 'windows-950');
$cp950Text = (string) $cp950Source->children[1]->attr('text');
$eucTwBytes = "# EUC TW\n\nPlane1 \xA1\xA1\xA1\xA2\xA1\xA3.";
$eucTwSource = (new MarkdownReader())->readBytes($eucTwBytes, 'x-euc-tw');
$eucTwText = (string) $eucTwSource->children[1]->attr('text');
$eucTwRowsBytes = "# EUC TW Rows\n\nRows \xA2\xA1\xA2\xA2\xA2\xA3; \xA3\xA1\xA3\xA2\xA3\xA3.";
$eucTwRowsSource = (new MarkdownReader())->readBytes($eucTwRowsBytes, 'cseuctw');
$eucTwRowsText = (string) $eucTwRowsSource->children[1]->attr('text');
$gbkBytes = (string) hex2bin('2320bcf2cce50a0ad6d0cec42047424b20b2e2cad4a3acb1b1bea9a1a3');
$gbkSource = (new MarkdownReader())->readBytes($gbkBytes, 'gbk');
$gbkText = (string) $gbkSource->children[1]->attr('text');
$gb2312SymbolBytes = "# GB2312 Symbols\n\nSymbols \xA1\xA1\xA1\xA2\xA1\xA3; fullwidth \xA3\xC1\xA3\xE1\xA3\xB0; kana \xA4\xA2\xA4\xA4\xA5\xA2; greek \xA6\xA1\xA6\xC1.";
$gb2312SymbolSource = (new MarkdownReader())->readBytes($gb2312SymbolBytes, 'euc-cn');
$gb2312SymbolText = (string) $gb2312SymbolSource->children[1]->attr('text');
$gb2312EnclosedBytes = "# GB2312 Enclosed\n\nEnclosed \xA2\xB1\xA2\xB2\xA2\xB3 \xA2\xC5\xA2\xC6\xA2\xC7 \xA2\xD9\xA2\xDA \xA2\xE5\xA2\xE6 \xA2\xF1\xA2\xF2; box \xA9\xA4\xA9\xA5\xA9\xA6\xA9\xA7\xA9\xA8\xA9\xA9.";
$gb2312EnclosedSource = (new MarkdownReader())->readBytes($gb2312EnclosedBytes, 'gb2312');
$gb2312EnclosedText = (string) $gb2312EnclosedSource->children[1]->attr('text');
$gb1988Bytes = "# GB1988\n\nCurrency \$~ halfwidth \xA1\xB0\xDF ASCII.";
$gb1988Source = (new MarkdownReader())->readBytes($gb1988Bytes, 'gb_1988-80');
$gb1988Text = (string) $gb1988Source->children[1]->attr('text');
$gb12345Bytes = (string) hex2bin('2320bcf2cce50a0ad6d0cec4204742313233343520b2e2cad4a3acb1b1bea9a1a3');
$gb12345Source = (new MarkdownReader())->readBytes($gb12345Bytes, 'gb12345-90');
$gb12345Text = (string) $gb12345Source->children[1]->attr('text');
$gb18030Bytes = "# GB18030\n\nEmoji \x94\x39\xFC\x36 CJK \x95\x32\x82\x36 Latin \x81\x30\x8B\x38 Euro \xA2\xE3.";
$gb18030Source = (new MarkdownReader())->readBytes($gb18030Bytes, 'gb18030');
$gb18030Text = (string) $gb18030Source->children[1]->attr('text');
$gb18030RangeBytes = "# GB18030 Ranges\n\nRange \x81\x30\xA4\x38 \x81\x30\xD3\x32 \x82\x35\x8F\x33 \x84\x31\x82\x36 \x90\x30\x81\x30 \x81\x35\xF4\x37.";
$gb18030RangeSource = (new MarkdownReader())->readBytes($gb18030RangeBytes, 'gb18030');
$gb18030RangeText = (string) $gb18030RangeSource->children[1]->attr('text');
$eucKrBytes = (string) hex2bin('2320c7d1b1db0a0ac7d1b1db204555432d4b5220c5d7bdbac6ae2c20bcadbfef2e');
$eucKrSource = (new MarkdownReader())->readBytes($eucKrBytes, 'ks_c_5601-1987');
$eucKrText = (string) $eucKrSource->children[1]->attr('text');
$iso2022KrBytes = "\x1B\$)C# \x0E\x47\x51\x31\x5B\x0F\n\n\x0E\x47\x51\x31\x5B\x0F ISO-2022-KR \x0E\x45\x57\x3D\x3A\x46\x2E\x0F, \x0E\x3C\x2D\x3F\x6F\x0F.";
$iso2022KrSource = (new MarkdownReader())->readBytes($iso2022KrBytes, 'csiso2022kr');
$iso2022KrText = (string) $iso2022KrSource->children[1]->attr('text');
$iso2022CnBytes = "\x1B\$)A# \x0E\x3C\x72\x4C\x65\x0F\n\n\x0E\x56\x50\x4E\x44\x0F ISO-2022-CN \x0E\x32\x62\x4A\x54\x0F, \x0E\x31\x31\x3E\x29\x0F.";
$iso2022CnSource = (new MarkdownReader())->readBytes($iso2022CnBytes, 'csiso2022cn');
$iso2022CnText = (string) $iso2022CnSource->children[1]->attr('text');
$windows949Bytes = "# UHC\n\nWindows-949 UHC \x81\x41\x81\x42\x81\x43 \x81\x51\x81\x52 \x81\xA1\x81\xA2.";
$windows949Source = (new MarkdownReader())->readBytes($windows949Bytes, 'windows-949');
$windows949Text = (string) $windows949Source->children[1]->attr('text');
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
$southAsianTelugu = "\u{0C15}\u{0C3F}";
$southAsianKannada = "\u{0C95}\u{0CC6}";
$southAsianMalayalam = "\u{0D15}\u{0D46}";
$southAsianSinhala = "\u{0D9A}\u{0DCF}";
$southeastAsianLao = "\u{0EA5}\u{0EB4}";
$southAsianMarkSlices = UnicodeText::splitByDisplayBreakpoints(
    $southAsianTelugu . $southAsianKannada . $southAsianMalayalam . $southAsianSinhala . $southeastAsianLao . 'X',
    [1, 2, 3, 4, 5]
);
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
$javaneseViramaStack = "\u{A98F}\u{A9C0}\u{A9A4}";
$balineseViramaStack = "\u{1B13}\u{1B44}\u{1B31}";
$sundaneseViramaStack = "\u{1B8A}\u{1BAA}\u{1B94}";
$insularViramaStackSlices = UnicodeText::splitByDisplayBreakpoints($javaneseViramaStack . $balineseViramaStack . $sundaneseViramaStack . 'X', [1, 2, 3]);
$thaiSaraAm = "\u{0E01}\u{0E33}";
$laoSaraAm = "\u{0EA5}\u{0EB3}";
$thaiLaoAmSlices = UnicodeText::splitByDisplayBreakpoints($thaiSaraAm . $laoSaraAm . 'X', [2, 4]);
$tibetanTshegText = "\u{0F56}\u{0F7C}\u{0F51}\u{0F0B}\u{0F61}\u{0F72}\u{0F42}\u{0F0B}\u{0F51}\u{0F54}\u{0F7A}\u{0F0B}\u{0F58}\u{0F5B}\u{0F7C}\u{0F51} tail";
$tibetanTshegWrap = UnicodeText::wrapByDisplayWidth($tibetanTshegText, 8, '  ');
$softBreakAuditLines = UnicodeText::wrapByDisplayWidth("Zero\u{200B}width\u{200B}breaks soft\u{00AD}hyphen \u{9B5A}\u{200B}\u{9B5A} tail", 10, '  ');
$unicodeSeparatorAuditLines = UnicodeText::wrapByDisplayWidth(
    "CJK\u{3000}review\u{2003}queue\u{2028}Hard reset\u{2029}\u{9B5A}\u{3000}\u{9B5A} tail",
    10,
    '  '
);
$lineBoundarySeparator = "\u{2028}";
$paragraphBoundarySeparator = "\u{2029}";
$lineBoundaryText = "A{$lineBoundarySeparator}B{$paragraphBoundarySeparator}\u{9B5A}";
$lineBoundaryAuditText = str_replace(
    [$lineBoundarySeparator, $paragraphBoundarySeparator],
    ['[LS]', '[PS]'],
    $lineBoundaryText
);
$lineBoundaryAuditLines = UnicodeText::wrapByDisplayWidth($lineBoundaryText, 10, '  ');
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
$emojiMultiSkinHandshake = "\u{1F9D1}\u{1F3FD}\u{200D}\u{1F91D}\u{200D}\u{1F9D1}\u{1F3FB}";
$emojiMultiSkinKiss = "\u{1F9D1}\u{1F3FD}\u{200D}\u{2764}\u{FE0F}\u{200D}\u{1F48B}\u{200D}\u{1F9D1}\u{1F3FF}";
$emojiMultiSkinZwjSlices = UnicodeText::splitByDisplayBreakpoints($emojiMultiSkinHandshake . $emojiMultiSkinKiss, [2]);
$plainZwjText = "A\u{200D}B";
$cjkZwjText = "\u{9B5A}\u{200D}\u{9B5A}";
$plainZwjSlices = UnicodeText::splitByDisplayBreakpoints($plainZwjText, [1]);
$cjkZwjSlices = UnicodeText::splitByDisplayBreakpoints($cjkZwjText, [2]);
$plainZwjWrap = UnicodeText::wrapByDisplayWidth("Plain {$plainZwjText} CJK {$cjkZwjText} tail", 10, '  ');
$ambiguousText = "\u{00B7}\u{03A9}\u{2014}\u{2026}\u{2122}";
$ambiguousWideSlices = UnicodeText::splitByDisplayBreakpoints($ambiguousText, [2, 4, 6, 8], 'wide');
$supplementaryWideText = "\u{16FE0}\u{1B000}\u{1F200}\u{1F18E}";
$supplementaryWideSlices = UnicodeText::splitByDisplayBreakpoints($supplementaryWideText, [2, 4, 6]);
$yijingHexagramText = "\u{4DC0}\u{4DDF}\u{4DFF}\u{4E00}";
$yijingHexagramSlices = UnicodeText::splitByDisplayBreakpoints($yijingHexagramText . 'X', [1, 2, 3, 5]);
$kanaExtendedBText = "\u{1AFF0}\u{1AFF5}\u{1AFFD}";
$kanaExtendedBSlices = UnicodeText::splitByDisplayBreakpoints($kanaExtendedBText . 'X', [2, 4, 6]);
$rareEastAsianScriptText = "\u{17000}\u{18800}\u{18B00}\u{18D00}";
$rareEastAsianScriptSlices = UnicodeText::splitByDisplayBreakpoints($rareEastAsianScriptText . 'X', [2, 4, 6, 8]);
$bmpWideEmojiText = "\u{231A}\u{2705}\u{2B50}\u{26FD}";
$bmpWideEmojiSlices = UnicodeText::splitByDisplayBreakpoints($bmpWideEmojiText, [2, 4, 6]);
$geometricEmojiText = "\u{1F7E0}\u{1F7E9}\u{1F7F0}";
$geometricEmojiSlices = UnicodeText::splitByDisplayBreakpoints($geometricEmojiText, [2, 4]);
$divinationWideText = "\u{2630}\u{268A}\u{1D300}\u{1D360}";
$divinationWideSlices = UnicodeText::splitByDisplayBreakpoints($divinationWideText . 'X', [2, 4, 6, 8]);
$defaultIgnorableText = "soft\u{00AD}hyphen / \u{FEFF}Title";
$defaultIgnorableWidth = UnicodeText::displayWidth("soft\u{00AD}hyphen") . ',' . UnicodeText::displayWidth("\u{FEFF}Title");
$formatControlText = "\u{0600}رقم \u{070F}ܣܘܪܝܝܐ \u{110BD}kaithi";
$formatControlWrap = UnicodeText::wrapByDisplayWidth("Audit \u{0600}رقم tail", 9, '  ');
$formatControlSlices = UnicodeText::splitByDisplayBreakpoints("A\u{0600}رق\u{110BD}ka", [1, 2, 3, 4]);
$tabStopText = "A\tB\t\u{9B5A}";
$tabStopSlices = UnicodeText::splitByDisplayBreakpoints($tabStopText, [4, 8]);
$lineBreakAudit = UnicodeText::lineBreakOpportunities("A\u{200B}B C\u{00AD}D\u{3000}wide A\u{00A0}B \u{0F0B}\u{0F56}\u{2028}Next\u{2029}End");
$lineBreakOpportunityTypes = implode(',', array_map(
    static fn (array $row): string => $row['type'],
    $lineBreakAudit['opportunities']
));
$lineBreakOpportunityColumns = implode(',', array_map(
    static fn (array $row): string => $row['codepoint'] . '@' . $row['column'] . '-' . $row['columnAfter'],
    $lineBreakAudit['opportunities']
));
$lineBreakProtectedTypes = implode(',', array_map(
    static fn (array $row): string => $row['type'],
    $lineBreakAudit['protectedSeparators']
));
$lineBreakProtectedColumns = implode(',', array_map(
    static fn (array $row): string => $row['codepoint'] . '@' . $row['column'] . '-' . $row['columnAfter'],
    $lineBreakAudit['protectedSeparators']
));
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
$ucs2LabelSource = (new MarkdownReader())->readBytes($utf16leBytes([
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
]), 'ucs-2le');
$ucs2LabelText = (string) $ucs2LabelSource->children[1]->attr('text');
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
$declaredUtf8Bom = UnicodeText::declaredCharset("\xEF\xBB\xBF<meta charset=windows-1252><p>x</p>", 'text/html; charset=windows-1252');
$declaredUtf16Bom = UnicodeText::declaredCharset("\xFE\xFF\x00<\x00?\x00x\x00m\x00l encoding=\"windows-1252\"?>");
$surrogateRepairSource = (new MarkdownReader())->readBytes("# UTF-8 Repair\n\nBad \xED\xA0\x80 high \xED\xB0\x80 low \xE0\x80\x80 overlong \xF0\x80\x80\x80 wide \xF4\x90\x80\x80 beyond.");
$surrogateRepairText = (string) $surrogateRepairSource->children[1]->attr('text');
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'South Asian marks'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $southAsianMarkSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $southAsianMarkSlices))])]),
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Javanese/Balinese/Sundanese stacks'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $insularViramaStackSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $insularViramaStackSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Thai/Lao AM'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $thaiLaoAmSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $thaiLaoAmSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Tibetan tsheg'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $tibetanTshegWrap)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $tibetanTshegWrap))])]),
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Line separators'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $lineBoundaryAuditText . ' / ' . implode(' / ', $lineBoundaryAuditLines)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => UnicodeText::displayWidth($lineBoundaryText) . ':' . implode(',', array_map(UnicodeText::displayWidth(...), $lineBoundaryAuditLines))])]),
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Emoji multi-skin ZWJ'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $emojiMultiSkinZwjSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $emojiMultiSkinZwjSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Plain/CJK ZWJ'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $plainZwjSlices) . ' / ' . implode(' / ', $cjkZwjSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => UnicodeText::displayWidth($plainZwjText) . ',' . UnicodeText::displayWidth($cjkZwjText) . ' / ' . implode(',', array_map(UnicodeText::displayWidth(...), $plainZwjWrap))])]),
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Yijing hexagrams'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $yijingHexagramSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $yijingHexagramSlices))])]),
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'I Ching/counting wide'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $divinationWideSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $divinationWideSlices))])]),
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Format control slices'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $formatControlSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(',', array_map(UnicodeText::displayWidth(...), $formatControlSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Tab stops'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(' / ', $tabStopSlices)])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => UnicodeText::displayWidth($tabStopText) . ' / ' . implode(',', array_map(UnicodeText::displayWidth(...), $tabStopSlices))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Break opportunities'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $lineBreakOpportunityTypes])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $lineBreakAudit['softBreakCount'] . '/' . $lineBreakAudit['hardBreakCount'] . '/' . $lineBreakAudit['protectedSeparatorCount'] . ':' . $lineBreakOpportunityColumns])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Protected separators'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $lineBreakProtectedTypes])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $lineBreakProtectedColumns])]),
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Latin-3 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $latin3Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($latin3Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($latin3Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Latin-4 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $latin4Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($latin4Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($latin4Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Latin-6 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $latin6Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($latin6Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($latin6Text) . '/' . UnicodeText::displayWidth($latin6Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Latin-7 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $latin7Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($latin7Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($latin7Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Latin-8 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $latin8Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($latin8Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($latin8Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Latin-10 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $latin10Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($latin10Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($latin10Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Windows-1251 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $windows1251Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($windows1251Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($windows1251Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'KOI8-R source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $koi8RText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($koi8RSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($koi8RText) . '/' . UnicodeText::displayWidth($koi8RText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'KOI8-U source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $koi8UText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($koi8USource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($koi8UText) . '/' . UnicodeText::displayWidth($koi8UText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'KOI8-RU source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $koi8RuText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($koi8RuSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($koi8RuText) . '/' . UnicodeText::displayWidth($koi8RuText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'KOI8-T source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $koi8TText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($koi8TSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($koi8TText) . '/' . UnicodeText::displayWidth($koi8TText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Mac Cyrillic source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $macCyrillicText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($macCyrillicSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($macCyrillicText) . '/' . UnicodeText::displayWidth($macCyrillicText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Mac Ukrainian source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $macUkrainianText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($macUkrainianSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($macUkrainianText) . '/' . UnicodeText::displayWidth($macUkrainianText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Mac Greek source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $macGreekText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($macGreekSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($macGreekText) . '/' . UnicodeText::displayWidth($macGreekText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Mac Iceland source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $macIcelandText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($macIcelandSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($macIcelandText) . '/' . UnicodeText::displayWidth($macIcelandText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Mac Central European source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $macCentralText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($macCentralSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($macCentralText) . '/' . UnicodeText::displayWidth($macCentralText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Mac Romanian source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $macRomaniaText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($macRomaniaSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($macRomaniaText) . '/' . UnicodeText::displayWidth($macRomaniaText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Mac Croatian source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $macCroatianText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($macCroatianSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($macCroatianText) . '/' . UnicodeText::displayWidth($macCroatianText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Mac Dingbats source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $macDingbatsText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($macDingbatsSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($macDingbatsText) . '/' . UnicodeText::displayWidth($macDingbatsText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Mac Symbol source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $macSymbolText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($macSymbolSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($macSymbolText) . '/' . UnicodeText::displayWidth($macSymbolText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'IBM855 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ibm855Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ibm855Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ibm855Text) . '/' . UnicodeText::displayWidth($ibm855Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'IBM737 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ibm737Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ibm737Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ibm737Text) . '/' . UnicodeText::displayWidth($ibm737Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'IBM869 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ibm869Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ibm869Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ibm869Text) . '/' . UnicodeText::displayWidth($ibm869Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'IBM437 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ibm437Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ibm437Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ibm437Text) . '/' . UnicodeText::displayWidth($ibm437Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'IBM850 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ibm850Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ibm850Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ibm850Text) . '/' . UnicodeText::displayWidth($ibm850Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'IBM857 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ibm857Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ibm857Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ibm857Text) . '/' . UnicodeText::displayWidth($ibm857Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'IBM862 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ibm862Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ibm862Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ibm862Text) . '/' . UnicodeText::displayWidth($ibm862Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'IBM864 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ibm864Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ibm864Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ibm864Text) . '/' . UnicodeText::displayWidth($ibm864Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'CP165 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $cp165Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($cp165Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($cp165Text) . '/' . UnicodeText::displayWidth($cp165Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'IBM852 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ibm852Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ibm852Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ibm852Text) . '/' . UnicodeText::displayWidth($ibm852Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'IBM860 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ibm860Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ibm860Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ibm860Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'IBM861 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ibm861Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ibm861Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ibm861Text) . '/' . UnicodeText::displayWidth($ibm861Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'IBM865 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ibm865Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ibm865Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ibm865Text) . '/' . UnicodeText::displayWidth($ibm865Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'IBM775 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ibm775Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ibm775Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ibm775Text) . '/' . UnicodeText::displayWidth($ibm775Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'IBM863 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ibm863Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ibm863Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ibm863Text) . '/' . UnicodeText::displayWidth($ibm863Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'ISO-8859-5 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $iso88595Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($iso88595Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($iso88595Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'ISO-8859-6 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $iso88596Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($iso88596Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($iso88596Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Windows-1256 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $windows1256Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($windows1256Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($windows1256Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Mac Arabic source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $macArabicText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($macArabicSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($macArabicText)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'X-user-defined source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $xUserDefinedText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($xUserDefinedSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($xUserDefinedText) . '/' . UnicodeText::displayWidth($xUserDefinedText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'ISO-8859-7 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $iso88597Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($iso88597Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($iso88597Text) . '/' . UnicodeText::displayWidth($iso88597Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Windows-1253 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $windows1253Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($windows1253Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($windows1253Text) . '/' . UnicodeText::displayWidth($windows1253Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'ISO-8859-8 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $iso88598Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($iso88598Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($iso88598Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Windows-1255 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $windows1255Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($windows1255Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($windows1255Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'ISO-8859-9 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $iso88599Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($iso88599Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($iso88599Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Windows-1254 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $windows1254Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($windows1254Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($windows1254Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Mac Turkish source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $macTurkishText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($macTurkishSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($macTurkishText) . '/' . UnicodeText::displayWidth($macTurkishText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'TIS-620 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $tis620Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($tis620Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($tis620Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Mac Thai source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $macThaiText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($macThaiSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($macThaiText)])]),
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'EUC-JP JIS0212 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $eucJpPlane2Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($eucJpPlane2Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($eucJpPlane2Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'ISO-2022-JP JIS0212 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $iso2022JpPlane2Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($iso2022JpPlane2Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($iso2022JpPlane2Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'ISO-2022-JP source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $iso2022JpText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($iso2022JpSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($iso2022JpText) . '/' . UnicodeText::displayWidth($iso2022JpText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'ISO-2022-JP truncated'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $iso2022JpTruncatedText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($iso2022JpTruncatedSource->attr('sourceEncoding')['encoding'] ?? '') . ':repairs=' . ($iso2022JpTruncatedSource->attr('sourceEncoding')['repairs'] ?? 0) . ':width=' . UnicodeText::displayWidth($iso2022JpTruncatedText)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Mac Japanese source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $macJapaneseText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($macJapaneseSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($macJapaneseText) . '/' . UnicodeText::displayWidth($macJapaneseText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Big5 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $big5Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($big5Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($big5Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Big5 punctuation source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $big5PunctuationText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($big5PunctuationSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($big5PunctuationText) . '/' . UnicodeText::displayWidth($big5PunctuationText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Big5 pointer sequences'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $big5PointerText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'big5:' . UnicodeText::displayWidth($big5PointerText)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Big5 kana source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $big5KanaText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($big5KanaSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($big5KanaText)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Big5 A3 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $big5A3Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($big5A3Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($big5A3Text) . '/' . UnicodeText::displayWidth($big5A3Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'CP950 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $cp950Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($cp950Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($cp950Text) . '/' . UnicodeText::displayWidth($cp950Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'EUC-TW source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $eucTwText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($eucTwSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($eucTwText) . '/' . UnicodeText::displayWidth($eucTwText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'EUC-TW row pairs'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $eucTwRowsText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($eucTwRowsSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($eucTwRowsText) . '/' . UnicodeText::displayWidth($eucTwRowsText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'GBK source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $gbkText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($gbkSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($gbkText)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'GB2312 symbol rows'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $gb2312SymbolText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($gb2312SymbolSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($gb2312SymbolText) . '/' . UnicodeText::displayWidth($gb2312SymbolText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'GB2312 enclosed symbols'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $gb2312EnclosedText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($gb2312EnclosedSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($gb2312EnclosedText) . '/' . UnicodeText::displayWidth($gb2312EnclosedText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'GB1988 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $gb1988Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($gb1988Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($gb1988Text) . '/' . UnicodeText::displayWidth($gb1988Text, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'GB12345 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $gb12345Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($gb12345Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($gb12345Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'GB18030 source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $gb18030Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($gb18030Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($gb18030Text)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'GB18030 ranges'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $gb18030RangeText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($gb18030RangeSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($gb18030RangeText)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'EUC-KR source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $eucKrText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($eucKrSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($eucKrText)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'ISO-2022-KR source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $iso2022KrText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($iso2022KrSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($iso2022KrText)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'ISO-2022-CN source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $iso2022CnText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($iso2022CnSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($iso2022CnText)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Windows-949 UHC source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $windows949Text])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($windows949Source->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($windows949Text)])]),
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
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'UCS-2LE source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $ucs2LabelText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($ucs2LabelSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . UnicodeText::displayWidth($ucs2LabelText)])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'UTF-32 BOM source'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $utf32BomSource->children[0]->attr('text') . ' / ' . $utf32BomSource->children[1]->attr('text')])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($utf32BomSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . ($utf32BomSource->attr('sourceEncoding')['bom'] ?? '') . ':' . UnicodeText::displayWidth((string) $utf32BomSource->children[0]->attr('text'))])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'UTF-8 scalar repair'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => $surrogateRepairText])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($surrogateRepairSource->attr('sourceEncoding')['encoding'] ?? '') . ':' . ($surrogateRepairSource->attr('sourceEncoding')['repairs'] ?? 0) . ':' . UnicodeText::displayWidth($surrogateRepairText) . '/' . UnicodeText::displayWidth($surrogateRepairText, 'wide')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'Declared BOM'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($declaredUtf8Bom['encoding'] ?? '') . ' / ' . ($declaredUtf16Bom['encoding'] ?? '')])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => ($declaredUtf8Bom['source'] ?? '') . ':' . ($declaredUtf8Bom['offset'] ?? '')])]),
        ]),
        new AstNode('table_row', [], [
            new AstNode('table_cell', [], [new AstNode('text', ['text' => 'BOM stale labels'])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => implode(', ', $declaredUtf8Bom['diagnostics'] ?? [])])]),
            new AstNode('table_cell', [], [new AstNode('text', ['text' => (string) count($declaredUtf8Bom['diagnostics'] ?? [])])]),
        ]),
    ]),
]);
$document = new AstNode('document', $source->attrs, [...$source->children, $table]);

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
    if (!str_contains($blocks, '<td>South Asian marks</td><td>' . implode(' / ', $southAsianMarkSlices) . '</td><td>1,1,1,1,1,1</td>')) {
        throw new RuntimeException('charset handoff self-test missing South Asian spacing-mark display-width audit');
    }
    if (!str_contains($blocks, '<td>Indic virama</td><td>' . $indicViramaDevanagari . ' / ' . $indicViramaZwjDevanagari . ' / ' . $indicViramaBengali . '</td><td>1,1,1</td>')) {
        throw new RuntimeException('charset handoff self-test missing Indic virama display-width audit');
    }
    if (!str_contains($blocks, '<td>Myanmar/Khmer conjuncts</td><td>' . $myanmarConjunct . ' / ' . $khmerConjunct . ' / X</td><td>1,1,1</td>')) {
        throw new RuntimeException('charset handoff self-test missing Myanmar/Khmer conjunct display-width audit');
    }
    if (!str_contains($blocks, '<td>Javanese/Balinese/Sundanese stacks</td><td>' . $javaneseViramaStack . ' / ' . $balineseViramaStack . ' / ' . $sundaneseViramaStack . ' / X</td><td>1,1,1,1</td>')) {
        throw new RuntimeException('charset handoff self-test missing Javanese/Balinese/Sundanese stack display-width audit');
    }
    if (!str_contains($blocks, '<td>Thai/Lao AM</td><td>' . $thaiSaraAm . ' / ' . $laoSaraAm . ' / X</td><td>2,2,1</td>')) {
        throw new RuntimeException('charset handoff self-test missing Thai/Lao AM display-width audit');
    }
    if (!str_contains($blocks, '<td>Tibetan tsheg</td><td>བོད་ཡིག་ /   དཔེ་མཛོད /   tail</td><td>6,8,6</td>')) {
        throw new RuntimeException('charset handoff self-test missing Tibetan tsheg wrap audit');
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
    if (!str_contains($blocks, "<td>Line separators</td><td>A[LS]B[PS]\u{9B5A} / A / B / \u{9B5A}</td><td>4:1,1,2</td>")) {
        throw new RuntimeException('charset handoff self-test missing line separator width audit');
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
    if (!str_contains($blocks, '<td>Emoji multi-skin ZWJ</td><td>' . $emojiMultiSkinHandshake . ' / ' . $emojiMultiSkinKiss . '</td><td>2,2</td>')) {
        throw new RuntimeException('charset handoff self-test missing multi-skin emoji ZWJ display-width audit');
    }
    if (!str_contains($blocks, '<td>Plain/CJK ZWJ</td><td>A' . "\u{200D}" . ' / B / ' . "\u{9B5A}\u{200D}" . ' / ' . "\u{9B5A}" . '</td><td>2,4 / 8,10,6</td>')) {
        throw new RuntimeException('charset handoff self-test missing plain/CJK ZWJ split display-width audit');
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
    if (!str_contains($blocks, '<td>Yijing hexagrams</td><td>' . implode(' / ', $yijingHexagramSlices) . '</td><td>1,1,1,2,1</td>')) {
        throw new RuntimeException('charset handoff self-test missing Yijing hexagram narrow-width audit');
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
    if (!str_contains($blocks, "<td>I Ching/counting wide</td><td>\u{2630} / \u{268A} / \u{1D300} / \u{1D360} / X</td><td>2,2,2,2,1</td>")) {
        throw new RuntimeException('charset handoff self-test missing I Ching/counting symbol width audit');
    }
    if (!str_contains($blocks, "<td>Default ignorables</td><td>soft\u{00AD}hyphen / \u{FEFF}Title</td><td>10,5</td>")) {
        throw new RuntimeException('charset handoff self-test missing default-ignorable width audit');
    }
    if (!str_contains($blocks, "<td>Format controls</td><td>\u{0600}رقم \u{070F}ܣܘܪܝܝܐ \u{110BD}kaithi / Audit \u{0600}رقم /   tail</td><td>17 / 9,6</td>")) {
        throw new RuntimeException('charset handoff self-test missing prepended format-control width audit');
    }
    if (!str_contains($blocks, "<td>Format control slices</td><td>A / \u{0600}ر / ق / \u{110BD}k / a</td><td>1,1,1,1,1</td>")) {
        throw new RuntimeException('charset handoff self-test missing prepended format-control slice audit');
    }
    if (!str_contains($blocks, '<td>Tab stops</td><td>' . implode(' / ', $tabStopSlices) . '</td><td>10 / 4,4,2</td>')) {
        throw new RuntimeException('charset handoff self-test missing tab-stop display-width audit');
    }
    if (!str_contains($blocks, '<td>Break opportunities</td><td>' . $lineBreakOpportunityTypes . '</td><td>' . $lineBreakAudit['softBreakCount'] . '/' . $lineBreakAudit['hardBreakCount'] . '/' . $lineBreakAudit['protectedSeparatorCount'] . ':' . $lineBreakOpportunityColumns . '</td>')) {
        throw new RuntimeException('charset handoff self-test missing Unicode line-break opportunity audit');
    }
    if (!str_contains($blocks, '<td>Protected separators</td><td>' . $lineBreakProtectedTypes . '</td><td>' . $lineBreakProtectedColumns . '</td>')) {
        throw new RuntimeException('charset handoff self-test missing protected Unicode separator audit');
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
    if (($latin3Source->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-8859-3') {
        throw new RuntimeException('charset handoff self-test missing Latin-3 source encoding');
    }
    if (!str_contains($blocks, '<td>Latin-3 source</td><td>Malti Ħħ u Ġġ; Esperanto Ĉĉ Ĝĝ Ŭŭ Ŝŝ; Turk İı; Żż.</td><td>iso-8859-3:50</td>')) {
        throw new RuntimeException('charset handoff self-test missing Latin-3 decode audit row');
    }
    if (($latin4Source->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-8859-4') {
        throw new RuntimeException('charset handoff self-test missing Latin-4 source encoding');
    }
    if (!str_contains($blocks, '<td>Latin-4 source</td><td>Baltic Āā Ķķ Ņņ Šš Žž; Ŋŋ.</td><td>iso-8859-4:26</td>')) {
        throw new RuntimeException('charset handoff self-test missing Latin-4 decode audit row');
    }
    if (($latin6Source->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-8859-10') {
        throw new RuntimeException('charset handoff self-test missing Latin-6 source encoding');
    }
    if (!str_contains($blocks, '<td>Latin-6 source</td><td>Nordic Ķķ Ŧŧ; Sami Ŋŋĸ; Baltic Ąą Ēē Ūū; ― and Ũũ.</td><td>iso-8859-10:50/58</td>')) {
        throw new RuntimeException('charset handoff self-test missing Latin-6 decode audit row');
    }
    if (($latin7Source->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-8859-13') {
        throw new RuntimeException('charset handoff self-test missing Latin-7 source encoding');
    }
    if (!str_contains($blocks, '<td>Latin-7 source</td><td>Baltic Āā Ńń Ņņ Ųų Śś Żż ž; quotes „“text”’.</td><td>iso-8859-13:44</td>')) {
        throw new RuntimeException('charset handoff self-test missing Latin-7 decode audit row');
    }
    if (($latin8Source->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-8859-14') {
        throw new RuntimeException('charset handoff self-test missing Latin-8 source encoding');
    }
    if (!str_contains($blocks, '<td>Latin-8 source</td><td>Celtic Àà Ŵŵ Ŷŷ; dotted Ḃḃ Ċċ Ẃẃ Ṡṡ; Welsh Ṫṫ.</td><td>iso-8859-14:46</td>')) {
        throw new RuntimeException('charset handoff self-test missing Latin-8 Celtic decode audit row');
    }
    if (($latin10Source->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-8859-16') {
        throw new RuntimeException('charset handoff self-test missing Latin-10 source encoding');
    }
    if (!str_contains($blocks, '<td>Latin-10 source</td><td>Romanian Șș Țț; Central Śś Űű Ęę; Euro €; quotes „text”.</td><td>iso-8859-16:56</td>')) {
        throw new RuntimeException('charset handoff self-test missing Latin-10 decode audit row');
    }
    if (($windows1251Source->attr('sourceEncoding')['encoding'] ?? '') !== 'windows-1251') {
        throw new RuntimeException('charset handoff self-test missing Windows-1251 source encoding');
    }
    if (!str_contains($blocks, '<td>Windows-1251 source</td><td>Редактор “привет” — €10; Ёлка № 7.</td><td>windows-1251:34</td>')) {
        throw new RuntimeException('charset handoff self-test missing Windows-1251 decode audit row');
    }
    if (($koi8RSource->attr('sourceEncoding')['encoding'] ?? '') !== 'koi8-r') {
        throw new RuntimeException('charset handoff self-test missing KOI8-R source encoding');
    }
    if (!str_contains($blocks, '<td>KOI8-R source</td><td>Редактор привет; Ёлка; ┌─┐.</td><td>koi8-r:27/48</td>')) {
        throw new RuntimeException('charset handoff self-test missing KOI8-R decode audit row');
    }
    if (($koi8USource->attr('sourceEncoding')['encoding'] ?? '') !== 'koi8-u') {
        throw new RuntimeException('charset handoff self-test missing KOI8-U source encoding');
    }
    if (!str_contains($blocks, '<td>KOI8-U source</td><td>Редактор Київ; їжак і ґанок; ЄІЇҐ.</td><td>koi8-u:34/52</td>')) {
        throw new RuntimeException('charset handoff self-test missing KOI8-U Ukrainian decode audit row');
    }
    if (($koi8RuSource->attr('sourceEncoding')['encoding'] ?? '') !== 'koi8-ru') {
        throw new RuntimeException('charset handoff self-test missing KOI8-RU source encoding');
    }
    if (!str_contains($blocks, '<td>KOI8-RU source</td><td>Редактор Мінск; Беларусь: Ўў; Україна ЄІЇҐ.</td><td>koi8-ru:43/69</td>')) {
        throw new RuntimeException('charset handoff self-test missing KOI8-RU Belarusian decode audit row');
    }
    if (($koi8TSource->attr('sourceEncoding')['encoding'] ?? '') !== 'koi8-t') {
        throw new RuntimeException('charset handoff self-test missing KOI8-T source encoding');
    }
    if (!str_contains($blocks, '<td>KOI8-T source</td><td>Матн “тоҷикӣ” — № 7; Ғафур; Қӯқон; ӮҲҶҷ.</td><td>koi8-t:40/58</td>')) {
        throw new RuntimeException('charset handoff self-test missing KOI8-T Tajik decode audit row');
    }
    if (($macCyrillicSource->attr('sourceEncoding')['encoding'] ?? '') !== 'mac-cyrillic') {
        throw new RuntimeException('charset handoff self-test missing Mac Cyrillic source encoding');
    }
    if (!str_contains($blocks, '<td>Mac Cyrillic source</td><td>Редактор “привет” — €20; Ёлка № 7; ЇїЄє.</td><td>mac-cyrillic:40/63</td>')) {
        throw new RuntimeException('charset handoff self-test missing Mac Cyrillic decode audit row');
    }
    if (($macUkrainianSource->attr('sourceEncoding')['encoding'] ?? '') !== 'mac-ukrainian') {
        throw new RuntimeException('charset handoff self-test missing Mac Ukrainian source encoding');
    }
    if (!str_contains($blocks, '<td>Mac Ukrainian source</td><td>Редактор Київ; Їжак ґанок; ЄІЇҐ; currency ¤20.</td><td>mac-ukrainian:46/65</td>')) {
        throw new RuntimeException('charset handoff self-test missing Mac Ukrainian decode audit row');
    }
    if (($macGreekSource->attr('sourceEncoding')['encoding'] ?? '') !== 'mac-greek') {
        throw new RuntimeException('charset handoff self-test missing Mac Greek source encoding');
    }
    if (!str_contains($blocks, "<td>Mac Greek source</td><td>Συντάκτης “πηγή” ― © 20; ΌΏ ΐΰ; \u{F8A0}.</td><td>mac-greek:34/48</td>")) {
        throw new RuntimeException('charset handoff self-test missing Mac Greek decode audit row');
    }
    if (($macIcelandSource->attr('sourceEncoding')['encoding'] ?? '') !== 'mac-iceland') {
        throw new RuntimeException('charset handoff self-test missing Mac Iceland source encoding');
    }
    if (!str_contains($blocks, '<td>Mac Iceland source</td><td>Ritstjóri “ísland” — €20; Þorn og ðaý; Ð/ð, Ý/ý; ’.</td><td>mac-iceland:51/62</td>')) {
        throw new RuntimeException('charset handoff self-test missing Mac Iceland decode audit row');
    }
    if (($macCentralSource->attr('sourceEncoding')['encoding'] ?? '') !== 'mac-central-europe') {
        throw new RuntimeException('charset handoff self-test missing Mac Central European source encoding');
    }
    if (!str_contains($blocks, '<td>Mac Central European source</td><td>Czech České škola Řeka; Polish Zażółć gęślą jaźń; Hungarian Őő Űű; quotes “text” — £10.</td><td>mac-central-europe:87/94</td>')) {
        throw new RuntimeException('charset handoff self-test missing Mac Central European decode audit row');
    }
    if (($macRomaniaSource->attr('sourceEncoding')['encoding'] ?? '') !== 'mac-romania') {
        throw new RuntimeException('charset handoff self-test missing Mac Romanian source encoding');
    }
    if (!str_contains($blocks, '<td>Mac Romanian source</td><td>Editor “română” — Braşov; Ţară şi faţă; cost ¤10; Ω.</td><td>mac-romania:52/57</td>')) {
        throw new RuntimeException('charset handoff self-test missing Mac Romanian decode audit row');
    }
    if (($macCroatianSource->attr('sourceEncoding')['encoding'] ?? '') !== 'mac-croatian') {
        throw new RuntimeException('charset handoff self-test missing Mac Croatian source encoding');
    }
    if (!str_contains($blocks, '<td>Mac Croatian source</td><td>Novinar “Šibenik” — Ćevapi; Županija, šuma, žar; ĆČĐ/ćčđ.</td><td>mac-croatian:57/61</td>')) {
        throw new RuntimeException('charset handoff self-test missing Mac Croatian decode audit row');
    }
    if (($macDingbatsSource->attr('sourceEncoding')['encoding'] ?? '') !== 'mac-dingbats') {
        throw new RuntimeException('charset handoff self-test missing Mac Dingbats source encoding');
    }
    if (!str_contains($blocks, "<td>Mac Dingbats source</td><td>✁✂✃ ✓✔ ★ ♣♥♠ ①❶❿ →↔↕ ➠➯ \u{F8D7}✎</td><td>mac-dingbats:26/37</td>")) {
        throw new RuntimeException('charset handoff self-test missing Mac Dingbats decode audit row');
    }
    if (($macSymbolSource->attr('sourceEncoding')['encoding'] ?? '') !== 'mac-symbol') {
        throw new RuntimeException('charset handoff self-test missing Mac Symbol source encoding');
    }
    if (!str_contains($blocks, "<td>Mac Symbol source</td><td>Α Β Γ Ω α β γ ω ≥ ≠≡≈ ∏√∑ ∫ \u{F8FF}.</td><td>mac-symbol:30/47</td>")) {
        throw new RuntimeException('charset handoff self-test missing Mac Symbol decode audit row');
    }
    if (($ibm855Source->attr('sourceEncoding')['encoding'] ?? '') !== 'ibm855') {
        throw new RuntimeException('charset handoff self-test missing IBM855 source encoding');
    }
    if (!str_contains($blocks, '<td>IBM855 source</td><td>Редактор привет; Ёлка; Љљ Њњ; box │─┌; №§.</td><td>ibm855:42/65</td>')) {
        throw new RuntimeException('charset handoff self-test missing IBM855 DOS Cyrillic decode audit row');
    }
    if (($ibm737Source->attr('sourceEncoding')['encoding'] ?? '') !== 'ibm737') {
        throw new RuntimeException('charset handoff self-test missing IBM737 source encoding');
    }
    if (!str_contains($blocks, "<td>IBM737 source</td><td>Ελληνικά CP737: αβγδε; ΆΈΉΊΌΎΏ; box │─┌; math ±≥≤; \u{00A0}.</td><td>ibm737:53/71</td>")) {
        throw new RuntimeException('charset handoff self-test missing IBM737 DOS Greek decode audit row');
    }
    if (($ibm869Source->attr('sourceEncoding')['encoding'] ?? '') !== 'ibm869') {
        throw new RuntimeException('charset handoff self-test missing IBM869 source encoding');
    }
    if (!str_contains($blocks, '<td>IBM869 source</td><td>Συντάκτης ½πηγή»; Ά·ΈΉΊΌΎΏ; ┌─┐.</td><td>ibm869:32/47</td>')) {
        throw new RuntimeException('charset handoff self-test missing IBM869 DOS Greek decode audit row');
    }
    if (($ibm437Source->attr('sourceEncoding')['encoding'] ?? '') !== 'ibm437') {
        throw new RuntimeException('charset handoff self-test missing IBM437 source encoding');
    }
    if (!str_contains($blocks, '<td>IBM437 source</td><td>Box ╔═╗║╠; résumé; αß °±.</td><td>ibm437:25/36</td>')) {
        throw new RuntimeException('charset handoff self-test missing IBM437 DOS decode audit row');
    }
    if (($ibm850Source->attr('sourceEncoding')['encoding'] ?? '') !== 'ibm850') {
        throw new RuntimeException('charset handoff self-test missing IBM850 source encoding');
    }
    if (!str_contains($blocks, '<td>IBM850 source</td><td>Español Français; Árvore e ızmir; fractions ½¼¾; box ╔═╗; ‗.</td><td>ibm850:60/67</td>')) {
        throw new RuntimeException('charset handoff self-test missing IBM850 DOS decode audit row');
    }
    if (($ibm857Source->attr('sourceEncoding')['encoding'] ?? '') !== 'ibm857') {
        throw new RuntimeException('charset handoff self-test missing IBM857 source encoding');
    }
    if (!str_contains($blocks, '<td>IBM857 source</td><td>Türkiye İstanbul; Ğağ, Şişli; box ╔═╗; §.</td><td>ibm857:41/46</td>')) {
        throw new RuntimeException('charset handoff self-test missing IBM857 DOS Turkish decode audit row');
    }
    if (($ibm862Source->attr('sourceEncoding')['encoding'] ?? '') !== 'ibm862') {
        throw new RuntimeException('charset handoff self-test missing IBM862 source encoding');
    }
    if (!str_contains($blocks, '<td>IBM862 source</td><td>Hebrew עברית: שלום מקור; box ╔═╗; Latin áí.</td><td>ibm862:43/48</td>')) {
        throw new RuntimeException('charset handoff self-test missing IBM862 DOS Hebrew decode audit row');
    }
    if (($ibm864Source->attr('sourceEncoding')['encoding'] ?? '') !== 'ibm864') {
        throw new RuntimeException('charset handoff self-test missing IBM864 source encoding');
    }
    if (!str_contains($blocks, "<td>IBM864 source</td><td>Arabic \u{FE8D}\u{FEDF}\u{FEC9}\u{FEAD}\u{FE91}\u{FEF3}\u{FE93}; digits \u{0661}\u{0662}\u{0663}; lam-alef \u{FEFB}\u{FEFC}; marks \u{FE7D}\u{0651}; box ┌─┐; soft\u{00AD}hyphen.</td><td>ibm864:70/73</td>")) {
        throw new RuntimeException('charset handoff self-test missing IBM864 DOS Arabic decode audit row');
    }
    if (($cp165Source->attr('sourceEncoding')['encoding'] ?? '') !== 'cp165') {
        throw new RuntimeException('charset handoff self-test missing CP165 source encoding');
    }
    if (!str_contains($blocks, "<td>CP165 source</td><td>Arabic \u{FE8D}\u{FEDF}\u{FEC9}\u{FEAD}\u{FE91}\u{FEF3}\u{FE93}; percent \u{066A}20; lam-alef \u{FEFB}\u{FEFC}; extras \u{FEF9}\u{FEFA}\u{FE73}\u{FE87}\u{FE88}\u{00A0}.</td><td>cp165:56/56</td>")) {
        throw new RuntimeException('charset handoff self-test missing CP165 DOS Arabic variant decode audit row');
    }
    if (($ibm852Source->attr('sourceEncoding')['encoding'] ?? '') !== 'ibm852') {
        throw new RuntimeException('charset handoff self-test missing IBM852 source encoding');
    }
    if (!str_contains($blocks, '<td>IBM852 source</td><td>Czech Čč Ěě Šš Žž Řř; Polish Łł Ąą Żż; Hungarian Őő Űű; box ╔═╗; ˝.</td><td>ibm852:67/74</td>')) {
        throw new RuntimeException('charset handoff self-test missing IBM852 DOS Central European decode audit row');
    }
    if (($ibm860Source->attr('sourceEncoding')['encoding'] ?? '') !== 'ibm860') {
        throw new RuntimeException('charset handoff self-test missing IBM860 source encoding');
    }
    if (!str_contains($blocks, '<td>IBM860 source</td><td>Português: Conteúdo, Ônibus, São Tomé, açúcar; «citação»; £/₧.</td><td>ibm860:62</td>')) {
        throw new RuntimeException('charset handoff self-test missing IBM860 DOS Portuguese decode audit row');
    }
    if (($ibm861Source->attr('sourceEncoding')['encoding'] ?? '') !== 'ibm861') {
        throw new RuntimeException('charset handoff self-test missing IBM861 source encoding');
    }
    if (!str_contains($blocks, '<td>IBM861 source</td><td>Icelandic: Áí Ísland, Þingvellir, Ð/ð, þorn; vowels áíóú ÁÍÓÚ; box ╔═╗; £.</td><td>ibm861:74/86</td>')) {
        throw new RuntimeException('charset handoff self-test missing IBM861 DOS Icelandic decode audit row');
    }
    if (($ibm865Source->attr('sourceEncoding')['encoding'] ?? '') !== 'ibm865') {
        throw new RuntimeException('charset handoff self-test missing IBM865 source encoding');
    }
    if (!str_contains($blocks, '<td>IBM865 source</td><td>Dansk: København, smørrebrød, blåbær; Norsk: ÆØÅ; Islandsk: Ðð Þþ; ¤.</td><td>ibm865:69/80</td>')) {
        throw new RuntimeException('charset handoff self-test missing IBM865 DOS Nordic decode audit row');
    }
    if (($ibm775Source->attr('sourceEncoding')['encoding'] ?? '') !== 'ibm775') {
        throw new RuntimeException('charset handoff self-test missing IBM775 source encoding');
    }
    if (!str_contains($blocks, "<td>IBM775 source</td><td>Baltic Āā Ēē Īī Ōō; Latvian Ģģ Ķķ Ļļ Ņņ; Lithuanian Ąą Čč Ęę Ėė Įį Šš Ųų Ūū Žž; quotes “avots” „zems”; box ╔═╗; soft\u{00AD}hyphen\u{00A0}tail.</td><td>ibm775:128/139</td>")) {
        throw new RuntimeException('charset handoff self-test missing IBM775 DOS Baltic decode audit row');
    }
    if (($ibm863Source->attr('sourceEncoding')['encoding'] ?? '') !== 'ibm863') {
        throw new RuntimeException('charset handoff self-test missing IBM863 source encoding');
    }
    if (!str_contains($blocks, '<td>IBM863 source</td><td>Québec Hôtel; coût; Été; fractions ½¼¾; monnaie ¢£¤; box ╔═╗; ‗.</td><td>ibm863:64/73</td>')) {
        throw new RuntimeException('charset handoff self-test missing IBM863 DOS Canadian French decode audit row');
    }
    if (($iso88595Source->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-8859-5') {
        throw new RuntimeException('charset handoff self-test missing ISO-8859-5 source encoding');
    }
    if (!str_contains($blocks, '<td>ISO-8859-5 source</td><td>Редактор привет; Ёлка № 7.</td><td>iso-8859-5:26</td>')) {
        throw new RuntimeException('charset handoff self-test missing ISO-8859-5 decode audit row');
    }
    if (($iso88596Source->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-8859-6') {
        throw new RuntimeException('charset handoff self-test missing ISO-8859-6 source encoding');
    }
    if (!str_contains($blocks, '<td>ISO-8859-6 source</td><td>محرر عربية، سؤال؛ هل؟</td><td>iso-8859-6:21</td>')) {
        throw new RuntimeException('charset handoff self-test missing ISO-8859-6 Arabic decode audit row');
    }
    if (($windows1256Source->attr('sourceEncoding')['encoding'] ?? '') !== 'windows-1256') {
        throw new RuntimeException('charset handoff self-test missing Windows-1256 source encoding');
    }
    if (!str_contains($blocks, '<td>Windows-1256 source</td><td>محرر “عربية” — €20; فارسي: پچژگ ک؛ اردو: ڑںے.</td><td>windows-1256:45</td>')) {
        throw new RuntimeException('charset handoff self-test missing Windows-1256 Arabic decode audit row');
    }
    if (($macArabicSource->attr('sourceEncoding')['encoding'] ?? '') !== 'mac-arabic') {
        throw new RuntimeException('charset handoff self-test missing Mac Arabic source encoding');
    }
    if (!str_contains($blocks, '<td>Mac Arabic source</td><td>Legacy عربية «خبر» - ٪20; Persian پچڤگ; digits ١٢٣; punctuation ،؛؟.</td><td>mac-arabic:68</td>')) {
        throw new RuntimeException('charset handoff self-test missing Mac Arabic decode audit row');
    }
    if (($xUserDefinedSource->attr('sourceEncoding')['encoding'] ?? '') !== 'x-user-defined') {
        throw new RuntimeException('charset handoff self-test missing x-user-defined source encoding');
    }
    if (!str_contains($blocks, "<td>X-user-defined source</td><td>Legacy \u{F780}\u{F781}\u{F7FE}\u{F7FF} source.</td><td>x-user-defined:19/23</td>")) {
        throw new RuntimeException('charset handoff self-test missing x-user-defined private-use decode audit row');
    }
    if (($iso88597Source->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-8859-7') {
        throw new RuntimeException('charset handoff self-test missing ISO-8859-7 source encoding');
    }
    if (!str_contains($blocks, '<td>ISO-8859-7 source</td><td>Συντάκτης «κείμενο» ― €20; Τόνος και ος.</td><td>iso-8859-7:40/62</td>')) {
        throw new RuntimeException('charset handoff self-test missing ISO-8859-7 Greek decode audit row');
    }
    if (($windows1253Source->attr('sourceEncoding')['encoding'] ?? '') !== 'windows-1253') {
        throw new RuntimeException('charset handoff self-test missing Windows-1253 source encoding');
    }
    if (!str_contains($blocks, '<td>Windows-1253 source</td><td>Συντάκτης “κείμενο” — €20; Τόνος.</td><td>windows-1253:33/53</td>')) {
        throw new RuntimeException('charset handoff self-test missing Windows-1253 Greek decode audit row');
    }
    if (($iso88598Source->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-8859-8') {
        throw new RuntimeException('charset handoff self-test missing ISO-8859-8 source encoding');
    }
    if (!str_contains($blocks, "<td>ISO-8859-8 source</td><td>עורך עברית «מקור» ‗ 12; \u{200F}RTL.</td><td>iso-8859-8:28</td>")) {
        throw new RuntimeException('charset handoff self-test missing ISO-8859-8 Hebrew decode audit row');
    }
    if (($windows1255Source->attr('sourceEncoding')['encoding'] ?? '') !== 'windows-1255') {
        throw new RuntimeException('charset handoff self-test missing Windows-1255 source encoding');
    }
    if (!str_contains($blocks, '<td>Windows-1255 source</td><td>עורך שָׁלוֹם “מקור” — ₪20; ׳״.</td><td>windows-1255:27</td>')) {
        throw new RuntimeException('charset handoff self-test missing Windows-1255 Hebrew decode audit row');
    }
    if (($iso88599Source->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-8859-9') {
        throw new RuntimeException('charset handoff self-test missing ISO-8859-9 source encoding');
    }
    if (!str_contains($blocks, '<td>ISO-8859-9 source</td><td>Turkish İstanbul, Ğağ, Şişli, ılık; ÖÜ remain.</td><td>iso-8859-9:46</td>')) {
        throw new RuntimeException('charset handoff self-test missing ISO-8859-9 Turkish decode audit row');
    }
    if (($windows1254Source->attr('sourceEncoding')['encoding'] ?? '') !== 'windows-1254') {
        throw new RuntimeException('charset handoff self-test missing Windows-1254 source encoding');
    }
    if (!str_contains($blocks, '<td>Windows-1254 source</td><td>Yazar “İstanbul” — €10; Ğağ, Şişli, ılık; ÖÜ remain.</td><td>windows-1254:52</td>')) {
        throw new RuntimeException('charset handoff self-test missing Windows-1254 Turkish decode audit row');
    }
    if (($macTurkishSource->attr('sourceEncoding')['encoding'] ?? '') !== 'mac-turkish') {
        throw new RuntimeException('charset handoff self-test missing Mac Turkish source encoding');
    }
    if (!str_contains($blocks, "<td>Mac Turkish source</td><td>Yazar “İstanbul” — Çağ; Şişli, ılık; Ğğ \u{F8A0}.</td><td>mac-turkish:42/48</td>")) {
        throw new RuntimeException('charset handoff self-test missing Mac Turkish decode audit row');
    }
    if (($tis620Source->attr('sourceEncoding')['encoding'] ?? '') !== 'tis-620') {
        throw new RuntimeException('charset handoff self-test missing TIS-620 source encoding');
    }
    if (!str_contains($blocks, '<td>TIS-620 source</td><td>เนื้อหา เอกสาร.</td><td>tis-620:13</td>')) {
        throw new RuntimeException('charset handoff self-test missing TIS-620 Thai decode audit row');
    }
    if (($macThaiSource->attr('sourceEncoding')['encoding'] ?? '') !== 'mac-thai') {
        throw new RuntimeException('charset handoff self-test missing Mac Thai source encoding');
    }
    if (!str_contains($blocks, "<td>Mac Thai source</td><td>เนื้อหา เอกสาร; «» “text” – ฿20; \u{FEFF}\u{200B}.</td><td>mac-thai:32</td>")) {
        throw new RuntimeException('charset handoff self-test missing Mac Thai decode audit row');
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
    if (($eucJpPlane2Source->attr('sourceEncoding')['encoding'] ?? '') !== 'euc-jp') {
        throw new RuntimeException('charset handoff self-test missing EUC-JP JIS0212 source encoding');
    }
    if (!str_contains($blocks, '<td>EUC-JP JIS0212 source</td><td>Plane2 ÆŒ; Єє; άό.</td><td>euc-jp:18</td>')) {
        throw new RuntimeException('charset handoff self-test missing EUC-JP JIS0212 decode audit row');
    }
    if (($iso2022JpPlane2Source->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-2022-jp') {
        throw new RuntimeException('charset handoff self-test missing ISO-2022-JP JIS0212 source encoding');
    }
    if (!str_contains($blocks, '<td>ISO-2022-JP JIS0212 source</td><td>Plane2 ÆŒ; Єє; άό.</td><td>iso-2022-jp:18</td>')) {
        throw new RuntimeException('charset handoff self-test missing ISO-2022-JP JIS0212 decode audit row');
    }
    if (($iso2022JpSource->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-2022-jp') {
        throw new RuntimeException('charset handoff self-test missing ISO-2022-JP source encoding');
    }
    if (!str_contains($blocks, "<td>ISO-2022-JP source</td><td>本文と半角ｶﾀｶﾅ、丸①波～崎。 ¥‾ ASCII</td><td>iso-2022-jp:36/38</td>")) {
        throw new RuntimeException('charset handoff self-test missing ISO-2022-JP decode audit row');
    }
    if (($iso2022JpTruncatedSource->attr('sourceEncoding')['repairs'] ?? 0) !== 1) {
        throw new RuntimeException('charset handoff self-test missing ISO-2022-JP final-state repair metadata');
    }
    if (!str_contains($blocks, "<td>ISO-2022-JP truncated</td><td>本文\u{FFFD}</td><td>iso-2022-jp:repairs=1:width=5</td>")) {
        throw new RuntimeException('charset handoff self-test missing ISO-2022-JP final-state repair audit row');
    }
    if (($macJapaneseSource->attr('sourceEncoding')['encoding'] ?? '') !== 'mac-japan') {
        throw new RuntimeException('charset handoff self-test missing Mac Japanese source encoding');
    }
    if (!str_contains($blocks, '<td>Mac Japanese source</td><td>Legacy ｶﾀｶﾅ 、。 あいう アイウ ©™….</td><td>mac-japan:35/37</td>')) {
        throw new RuntimeException('charset handoff self-test missing Mac Japanese decode audit row');
    }
    if (($big5Source->attr('sourceEncoding')['encoding'] ?? '') !== 'big5') {
        throw new RuntimeException('charset handoff self-test missing Big5 source encoding');
    }
    if (!str_contains($blocks, '<td>Big5 source</td><td>中文 Big5 測試，香港。</td><td>big5:22</td>')) {
        throw new RuntimeException('charset handoff self-test missing Big5 decode audit row');
    }
    if (($big5PunctuationSource->attr('sourceEncoding')['encoding'] ?? '') !== 'big5') {
        throw new RuntimeException('charset handoff self-test missing Big5 punctuation source encoding');
    }
    if (!str_contains($blocks, '<td>Big5 punctuation source</td><td>　「中文」“quote”？！；：、。※§〃 •.</td><td>big5:36/41</td>')) {
        throw new RuntimeException('charset handoff self-test missing Big5 punctuation decode audit row');
    }
    if (!str_contains($blocks, "<td>Big5 pointer sequences</td><td>Ê\u{0304}Ê\u{030C}ê\u{0304}ê\u{030C}</td><td>big5:4</td>")) {
        throw new RuntimeException('charset handoff self-test missing Big5 two-codepoint pointer audit row');
    }
    if (($big5KanaSource->attr('sourceEncoding')['encoding'] ?? '') !== 'big5') {
        throw new RuntimeException('charset handoff self-test missing Big5 kana source encoding');
    }
    if (!str_contains($blocks, '<td>Big5 kana source</td><td>Kana ヾゝゞ々 ぁあ; digits ０１２.</td><td>big5:34</td>')) {
        throw new RuntimeException('charset handoff self-test missing Big5 kana extension decode audit row');
    }
    if (($big5A3Source->attr('sourceEncoding')['encoding'] ?? '') !== 'big5') {
        throw new RuntimeException('charset handoff self-test missing Big5 A3 source encoding');
    }
    if (!str_contains($blocks, '<td>Big5 A3 source</td><td>Greek ΑΝΩ αω; bopomofo ㄅㄆㄏ.</td><td>big5:30/35</td>')) {
        throw new RuntimeException('charset handoff self-test missing Big5 A3 Greek/Bopomofo decode audit row');
    }
    if (($cp950Source->attr('sourceEncoding')['encoding'] ?? '') !== 'cp950') {
        throw new RuntimeException('charset handoff self-test missing CP950 source encoding');
    }
    if (!str_contains($blocks, "<td>CP950 source</td><td>CP950 Euro € glyphs 碁銹 box ╔╦╗.</td><td>cp950:33/37</td>")) {
        throw new RuntimeException('charset handoff self-test missing CP950 extension decode audit row');
    }
    if (($eucTwSource->attr('sourceEncoding')['encoding'] ?? '') !== 'euc-tw') {
        throw new RuntimeException('charset handoff self-test missing EUC-TW source encoding');
    }
    if (!str_contains($blocks, "<td>EUC-TW source</td><td>Plane1 \u{4E28}\u{4E36}\u{4E3F}.</td><td>euc-tw:14/14</td>")) {
        throw new RuntimeException('charset handoff self-test missing EUC-TW plane one decode audit row');
    }
    if (($eucTwRowsSource->attr('sourceEncoding')['encoding'] ?? '') !== 'euc-tw') {
        throw new RuntimeException('charset handoff self-test missing EUC-TW row-pair source encoding');
    }
    if (!str_contains($blocks, "<td>EUC-TW row pairs</td><td>Rows \u{5322}\u{5304}\u{5303}; \u{4F64}\u{51E8}\u{4F67}.</td><td>euc-tw:20/20</td>")) {
        throw new RuntimeException('charset handoff self-test missing EUC-TW row-pair decode audit row');
    }
    if (($gbkSource->attr('sourceEncoding')['encoding'] ?? '') !== 'gbk') {
        throw new RuntimeException('charset handoff self-test missing GBK source encoding');
    }
    if (!str_contains($blocks, '<td>GBK source</td><td>中文 GBK 测试，北京。</td><td>gbk:21</td>')) {
        throw new RuntimeException('charset handoff self-test missing GBK decode audit row');
    }
    if (($gb2312SymbolSource->attr('sourceEncoding')['encoding'] ?? '') !== 'gbk') {
        throw new RuntimeException('charset handoff self-test missing GB2312 symbol row source encoding');
    }
    if (!str_contains($blocks, '<td>GB2312 symbol rows</td><td>Symbols 　、。; fullwidth Ａａ０; kana あいア; greek Αα.</td><td>gbk:56/58</td>')) {
        throw new RuntimeException('charset handoff self-test missing GB2312 symbol row audit');
    }
    if (($gb2312EnclosedSource->attr('sourceEncoding')['encoding'] ?? '') !== 'gbk') {
        throw new RuntimeException('charset handoff self-test missing GB2312 enclosed symbol source encoding');
    }
    if (!str_contains($blocks, '<td>GB2312 enclosed symbols</td><td>Enclosed ⒈⒉⒊ ⑴⑵⑶ ①② ㈠㈡ ⅠⅡ; box ─━│┃┄┅.</td><td>gbk:40/56</td>')) {
        throw new RuntimeException('charset handoff self-test missing GB2312 enclosed symbol audit');
    }
    if (($gb1988Source->attr('sourceEncoding')['encoding'] ?? '') !== 'gb1988') {
        throw new RuntimeException('charset handoff self-test missing GB1988 source encoding');
    }
    if (!str_contains($blocks, '<td>GB1988 source</td><td>Currency ¥‾ halfwidth ｡ｰﾟ ASCII.</td><td>gb1988:32/33</td>')) {
        throw new RuntimeException('charset handoff self-test missing GB1988 decode audit row');
    }
    if (($gb12345Source->attr('sourceEncoding')['encoding'] ?? '') !== 'gb12345') {
        throw new RuntimeException('charset handoff self-test missing GB12345 source encoding');
    }
    if (!str_contains($blocks, '<td>GB12345 source</td><td>中文 GB12345 測試，北京。</td><td>gb12345:25</td>')) {
        throw new RuntimeException('charset handoff self-test missing GB12345 decode audit row');
    }
    if (($gb18030Source->attr('sourceEncoding')['encoding'] ?? '') !== 'gb18030') {
        throw new RuntimeException('charset handoff self-test missing GB18030 source encoding');
    }
    if (!str_contains($blocks, "<td>GB18030 source</td><td>Emoji \u{1F600} CJK \u{20000} Latin \u{0100} Euro \u{20AC}.</td><td>gb18030:31</td>")) {
        throw new RuntimeException('charset handoff self-test missing GB18030 four-byte decode audit row');
    }
    if (($gb18030RangeSource->attr('sourceEncoding')['encoding'] ?? '') !== 'gb18030') {
        throw new RuntimeException('charset handoff self-test missing GB18030 range source encoding');
    }
    if (!str_contains($blocks, "<td>GB18030 ranges</td><td>Range \u{020B} \u{0454} \u{9FA6} \u{FE10} \u{10000} \u{E7C7}.</td><td>gb18030:20</td>")) {
        throw new RuntimeException('charset handoff self-test missing GB18030 range-pointer decode audit row');
    }
    if (($eucKrSource->attr('sourceEncoding')['encoding'] ?? '') !== 'euc-kr') {
        throw new RuntimeException('charset handoff self-test missing EUC-KR source encoding');
    }
    if (!str_contains($blocks, '<td>EUC-KR source</td><td>한글 EUC-KR 테스트, 서울.</td><td>euc-kr:25</td>')) {
        throw new RuntimeException('charset handoff self-test missing EUC-KR decode audit row');
    }
    if (($iso2022KrSource->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-2022-kr') {
        throw new RuntimeException('charset handoff self-test missing ISO-2022-KR source encoding');
    }
    if (!str_contains($blocks, '<td>ISO-2022-KR source</td><td>한글 ISO-2022-KR 테스트, 서울.</td><td>iso-2022-kr:30</td>')) {
        throw new RuntimeException('charset handoff self-test missing ISO-2022-KR decode audit row');
    }
    if (($iso2022CnSource->attr('sourceEncoding')['encoding'] ?? '') !== 'iso-2022-cn') {
        throw new RuntimeException('charset handoff self-test missing ISO-2022-CN source encoding');
    }
    if (!str_contains($blocks, '<td>ISO-2022-CN source</td><td>中文 ISO-2022-CN 测试, 北京.</td><td>iso-2022-cn:28</td>')) {
        throw new RuntimeException('charset handoff self-test missing ISO-2022-CN decode audit row');
    }
    if (($windows949Source->attr('sourceEncoding')['encoding'] ?? '') !== 'windows-949') {
        throw new RuntimeException('charset handoff self-test missing Windows-949 source encoding');
    }
    if (!str_contains($blocks, '<td>Windows-949 UHC source</td><td>Windows-949 UHC 갂갃갅 갦갧 걾걿.</td><td>windows-949:33</td>')) {
        throw new RuntimeException('charset handoff self-test missing Windows-949 UHC decode audit row');
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
    if (($ucs2LabelSource->attr('sourceEncoding')['encoding'] ?? '') !== 'utf-16le') {
        throw new RuntimeException('charset handoff self-test missing UCS-2 source label decoding');
    }
    if (!str_contains($blocks, "<td>UCS-2LE source</td><td>Café — 魚</td><td>utf-16le:9</td>")) {
        throw new RuntimeException('charset handoff self-test missing UCS-2LE label audit row');
    }
    if (($utf32BomSource->attr('sourceEncoding')['encoding'] ?? '') !== 'utf-32be') {
        throw new RuntimeException('charset handoff self-test missing UTF-32 source encoding');
    }
    if (!str_contains($blocks, "<td>UTF-32 BOM source</td><td>\u{1F4DA} Review / 計画</td><td>utf-32be:utf-32be:9</td>")) {
        throw new RuntimeException('charset handoff self-test missing UTF-32 BOM audit row');
    }
    if (($surrogateRepairSource->attr('sourceEncoding')['repairs'] ?? 0) !== 5) {
        throw new RuntimeException('charset handoff self-test missing UTF-8 scalar repair count');
    }
    if (!str_contains($blocks, "<td>UTF-8 scalar repair</td><td>Bad \u{FFFD} high \u{FFFD} low \u{FFFD} overlong \u{FFFD} wide \u{FFFD} beyond.</td><td>utf-8-repaired:5:44/49</td>")) {
        throw new RuntimeException('charset handoff self-test missing UTF-8 scalar repair audit row');
    }
    if (($declaredUtf8Bom['source'] ?? '') !== 'byte-order-mark' || ($declaredUtf8Bom['encoding'] ?? '') !== 'utf-8') {
        throw new RuntimeException('charset handoff self-test missing declared UTF-8 BOM preflight');
    }
    if (($declaredUtf16Bom['source'] ?? '') !== 'byte-order-mark' || ($declaredUtf16Bom['encoding'] ?? '') !== 'utf-16be') {
        throw new RuntimeException('charset handoff self-test missing declared UTF-16 BOM preflight');
    }
    if (!str_contains($blocks, '<td>Declared BOM</td><td>utf-8 / utf-16be</td><td>byte-order-mark:0</td>')) {
        throw new RuntimeException('charset handoff self-test missing declared BOM audit row');
    }
    if (!str_contains($blocks, '<td>BOM stale labels</td><td>ignored-content-type-charset:windows-1252, ignored-html-meta-charset:windows-1252</td><td>2</td>')) {
        throw new RuntimeException('charset handoff self-test missing stale BOM label diagnostics audit row');
    }

    echo "charset unicode handoff self-test ok\n";
    return;
}

echo 'Encoding: ' . ($document->attr('sourceEncoding')['encoding'] ?? '') . "\n";
echo 'Repairs: ' . ($document->attr('sourceEncoding')['repairs'] ?? 0) . "\n\n";
echo 'Line ending conversions: ' . ($document->attr('sourceLineEndings')['conversions'] ?? 0) . "\n\n";
echo $blocks . "\n";
