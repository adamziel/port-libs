<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

/**
 * @param array<int, int> $advanceWidths
 * @param array<int, string> $glyphNames
 */
function markerpdf_true_type_embedded_width_fixture(array $advanceWidths, array $glyphNames = []): string
{
    $maxGlyphId = 0;
    foreach (array_keys($advanceWidths + $glyphNames) as $glyphId) {
        $maxGlyphId = max($maxGlyphId, (int) $glyphId);
    }
    $numGlyphs = $maxGlyphId + 1;

    $head = str_repeat("\0", 54);
    $head = substr_replace($head, pack('n', 1000), 18, 2);

    $hhea = str_repeat("\0", 36);
    $hhea = substr_replace($hhea, pack('n', $numGlyphs), 34, 2);

    $maxp = pack('Nn', 0x00010000, $numGlyphs);

    $hmtx = '';
    for ($glyphId = 0; $glyphId < $numGlyphs; $glyphId++) {
        $hmtx .= pack('nn', $advanceWidths[$glyphId] ?? 500, 0);
    }

    $tables = [
        'head' => $head,
        'hhea' => $hhea,
        'maxp' => $maxp,
        'hmtx' => $hmtx,
    ];

    if ($glyphNames !== []) {
        $nameIndexes = [];
        $customNames = [];
        foreach ($glyphNames as $glyphId => $name) {
            $nameIndexes[(int) $glyphId] = 258 + count($customNames);
            $customNames[] = $name;
        }

        $post = pack('N', 0x00020000) . str_repeat("\0", 28) . pack('n', $numGlyphs);
        for ($glyphId = 0; $glyphId < $numGlyphs; $glyphId++) {
            $post .= pack('n', $nameIndexes[$glyphId] ?? 0);
        }
        foreach ($customNames as $name) {
            $post .= chr(strlen($name)) . $name;
        }

        $tables['post'] = $post;
    }

    ksort($tables);
    $numTables = count($tables);
    $directory = pack('Nnnnn', 0x00010000, $numTables, 0, 0, 0);
    $records = '';
    $payload = '';
    $offset = 12 + ($numTables * 16);
    foreach ($tables as $tag => $data) {
        $padding = (4 - (strlen($payload) % 4)) % 4;
        if ($padding > 0) {
            $payload .= str_repeat("\0", $padding);
            $offset += $padding;
        }

        $records .= $tag . pack('NNN', 0, $offset, strlen($data));
        $payload .= $data;
        $offset += strlen($data);
    }

    return $directory . $records . $payload;
}

/**
 * @param array<int, int> $cidToGid
 */
function markerpdf_true_type_cid_to_gid_map(array $cidToGid): string
{
    $maxCid = max(array_keys($cidToGid));
    $bytes = str_repeat("\0", ($maxCid + 1) * 2);
    foreach ($cidToGid as $cid => $gid) {
        $bytes = substr_replace($bytes, pack('n', $gid), ((int) $cid) * 2, 2);
    }

    return $bytes;
}

function markerpdf_flate_stream_object(int $objectNumber, string $payload): string
{
    $compressed = gzcompress($payload);

    return $objectNumber . " 0 obj\n"
        . '<< /Filter /FlateDecode /Length ' . strlen($compressed) . " >>\n"
        . "stream\n{$compressed}\nendstream\nendobj\n";
}

$fontTrueTypeCidToGidWidthCurrentBasePdf = static function (): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /EmbeddedGlyphOrder-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "2 begincidrange\n"
        . "<01> <09> 40\n"
        . "<14> <1B> 60\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "17 beginbfchar\n"
        . "<01> <0057>\n"
        . "<02> <0069>\n"
        . "<03> <0064>\n"
        . "<04> <0065>\n"
        . "<05> <0042>\n"
        . "<06> <006C>\n"
        . "<07> <006F>\n"
        . "<08> <0063>\n"
        . "<09> <006B>\n"
        . "<14> <0054>\n"
        . "<15> <0068>\n"
        . "<16> <0069>\n"
        . "<17> <006E>\n"
        . "<18> <0054>\n"
        . "<19> <0065>\n"
        . "<1A> <0078>\n"
        . "<1B> <0074>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <01020304> Tj '
        . '1 0 0 1 118 720 Tm <0506070809> Tj '
        . 'T* 1 0 0 1 72 704 Tm <14151617> Tj '
        . '1 0 0 1 96 704 Tm <18191A1B> Tj ET';

    $cidToGid = [];
    $advanceWidths = [];
    for ($offset = 0; $offset < 9; $offset++) {
        $cidToGid[40 + $offset] = 5 + $offset;
        $advanceWidths[5 + $offset] = 1000;
    }
    for ($offset = 0; $offset < 8; $offset++) {
        $cidToGid[60 + $offset] = 20 + $offset;
        $advanceWidths[20 + $offset] = 250;
    }
    $cidToGidMap = markerpdf_true_type_cid_to_gid_map($cidToGid);
    $fontFile = markerpdf_true_type_embedded_width_fixture($advanceWidths);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /EmbeddedGlyphOrder /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /EmbeddedGlyphOrder /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /CIDToGIDMap 10 0 R /FontDescriptor 9 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /FontDescriptor /FontName /EmbeddedGlyphOrder /Flags 32 /FontFile2 11 0 R >>\nendobj\n"
        . markerpdf_flate_stream_object(10, $cidToGidMap)
        . markerpdf_flate_stream_object(11, $fontFile)
        . "%%EOF";
};

$fontTrueTypePostGlyphNameWidthCurrentBasePdf = static function (): string {
    $content = 'BT /Fttf 12 Tf '
        . '1 0 0 1 72 720 Tm <41424344> Tj '
        . '1 0 0 1 118 720 Tm <4546474849> Tj '
        . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
        . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';

    $glyphNames = [
        5 => 'W.wide',
        6 => 'i.wide',
        7 => 'd.wide',
        8 => 'e.wide',
        9 => 'B.wide',
        10 => 'l.wide',
        11 => 'o.wide',
        12 => 'c.wide',
        13 => 'k.wide',
        20 => 'T.thin',
        21 => 'h.thin',
        22 => 'i.thin',
        23 => 'n.thin',
        24 => 'T.thin.2',
        25 => 'e.thin',
        26 => 'x.thin',
        27 => 't.thin',
    ];
    $advanceWidths = [];
    for ($glyphId = 5; $glyphId <= 13; $glyphId++) {
        $advanceWidths[$glyphId] = 1000;
    }
    for ($glyphId = 20; $glyphId <= 27; $glyphId++) {
        $advanceWidths[$glyphId] = 250;
    }
    $fontFile = markerpdf_true_type_embedded_width_fixture($advanceWidths, $glyphNames);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fttf 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /EmbeddedPostNames /Encoding 6 0 R /FontDescriptor 9 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [65 /W.wide /i.wide /d.wide /e.wide /B.wide /l.wide /o.wide /c.wide /k.wide 84 /T.thin /h.thin /i.thin /n.thin /T.thin.2 /e.thin /x.thin /t.thin] >>\nendobj\n"
        . "9 0 obj\n<< /Type /FontDescriptor /FontName /EmbeddedPostNames /Flags 32 /FontFile2 10 0 R >>\nendobj\n"
        . markerpdf_flate_stream_object(10, $fontFile)
        . "%%EOF";
};

return [
    'uses embedded TrueType hmtx widths through CIDToGIDMap glyph order before text gaps on current base' => static function (
        TestRunner $t
    ) use ($fontTrueTypeCidToGidWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontTrueTypeCidToGidWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->same("WideBlock\nThin Text\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, "\0"));
    },

    'uses embedded TrueType post glyph name indexes for simple-font hmtx widths on current base' => static function (
        TestRunner $t
    ) use ($fontTrueTypePostGlyphNameWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $fontTrueTypePostGlyphNameWidthCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideBlock', 'Thin Text'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Block', 'Thin', 'Text'], $extractor->extractTextRuns($pdf));
        $t->same("WideBlock\nThin Text", $plainText);
        $t->same("WideBlock\nThin Text\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Wide Block'));
        $t->true(!str_contains($plainText, 'ThinText'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
