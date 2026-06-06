<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapWModeFilterBoundaryCurrentBasePdf = static function (string $wModeFragment): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . $wModeFragment
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "2 begincidrange\n"
        . "<0001> <000A> 40\n"
        . "<0014> <001B> 60\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedEncodingCMap = gzcompress($encodingCMap, 0);
    if (!is_string($compressedEncodingCMap)) {
        throw new RuntimeException('Unable to compress focused WMode CMap fixture.');
    }

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "18 beginbfchar\n"
        . "<0001> <0056>\n"
        . "<0002> <0065>\n"
        . "<0003> <0072>\n"
        . "<0004> <0074>\n"
        . "<0005> <0049>\n"
        . "<0006> <006D>\n"
        . "<0007> <0070>\n"
        . "<0008> <006F>\n"
        . "<0009> <0072>\n"
        . "<000A> <0074>\n"
        . "<0014> <0044>\n"
        . "<0015> <0061>\n"
        . "<0016> <0074>\n"
        . "<0017> <0061>\n"
        . "<0018> <0046>\n"
        . "<0019> <006C>\n"
        . "<001A> <006F>\n"
        . "<001B> <0077>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fv 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '0 -24 Td <00050006000700080009000A> Tj '
        . '24 24 Td <0014001500160017> Tj '
        . '0 -24 Td <00180019001A001B> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fv 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MalformedWModeCIDSubset /Encoding 5 0 R /DescendantFonts [7 0 R] /ToUnicode 8 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /CMap /CMapName /MalformedWModeBoundary-H /Filter /FlateDecode /Length " . strlen($compressedEncodingCMap) . " >>\nstream\n{$compressedEncodingCMap}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MalformedWModeCIDSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [40 [500 500 500 500 500 500 500 500 500 500] 60 [500 500 500 500 500 500 500 500]] /DW2 [880 -1000] /W2 [40 49 -500 500 880 60 67 -250 500 880] >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

return [
    'ignores malformed filtered CMap WMode decoys in literal and dictionary values on current base' => static function (TestRunner $t) use ($parserMalformedCMapWModeFilterBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();

        foreach ([
            'literal' => "(/WMode 1 def) pop\n",
            'dictionary-literal' => "<< /Note (/WMode 1 def) >> pop\n",
        ] as $label => $fragment) {
            $pdf = $parserMalformedCMapWModeFilterBoundaryCurrentBasePdf($fragment);
            $plainText = $extractor->extractPlainText($pdf);
            $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);

            $t->same(['Vert', 'Import', 'Data', 'Flow'], $extractor->extractTextLines($pdf), $label . ' keeps horizontal text-line grouping');
            $t->same("Vert\nImport\nData\nFlow", $plainText, $label . ' keeps horizontal plain text');
            $t->true(!str_contains($plainText, 'VertImport'), $label . ' does not treat literal WMode text as vertical writing');
            $t->true(!str_contains($plainText, 'Data Flow'), $label . ' does not apply vertical W2 grouping');
            $t->same(2, $review['decoded_cmap_count'], $label . ' still decodes the filtered Encoding and ToUnicode CMap streams');
            $t->same(0, $review['malformed_filter_operand_count'], $label . ' keeps the valid FlateDecode filter operand');
            $t->same(0, $review['unsupported_filter_count'], $label . ' does not reject the valid filter stack');
            $t->same(['FlateDecode'], $review['entries'][0]['filters'], $label . ' keeps the filtered Encoding CMap native decoder stack');
            $t->same('filters_resolved', $review['entries'][0]['filter_operand_policy'], $label . ' resolves the direct filter stack');
        }
    },

    'keeps top-level filtered CMap WMode directives after malformed decoy filtering on current base' => static function (TestRunner $t) use ($parserMalformedCMapWModeFilterBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapWModeFilterBoundaryCurrentBasePdf("/WMode 1 def\n");
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['VertImport', 'Data Flow'], $extractor->extractTextLines($pdf));
        $t->same("VertImport\nData Flow", $plainText);
        $t->true(!str_contains($plainText, "Vert\nImport"));
        $t->true(!str_contains($plainText, "Data\nFlow"));
    },
];
