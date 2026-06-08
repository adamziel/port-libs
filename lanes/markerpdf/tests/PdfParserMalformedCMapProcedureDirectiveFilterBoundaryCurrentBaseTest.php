<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapProcedureDirectiveUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapProcedureDirectiveFilteredWModePdf = static function (string $wModeFragment): string {
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
        throw new RuntimeException('Unable to compress focused WMode procedure CMap fixture.');
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
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MalformedProcedureWModeCIDSubset /Encoding 5 0 R /DescendantFonts [7 0 R] /ToUnicode 8 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /CMap /CMapName /MalformedProcedureWModeBoundary-H /Filter /FlateDecode /Length " . strlen($compressedEncodingCMap) . " >>\nstream\n{$compressedEncodingCMap}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MalformedProcedureWModeCIDSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [40 [500 500 500 500 500 500 500 500 500 500] 60 [500 500 500 500 500 500 500 500]] /DW2 [880 -1000] /W2 [40 49 -500 500 880 60 67 -250 500 880] >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$parserMalformedCMapProcedureDirectiveToUnicode = static function (
    string $cMapName,
    string $body
): string {
    return "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . ($cMapName === '' ? '' : "/CMapName /{$cMapName} def\n")
        . $body
        . "endcmap\n"
        . "end\n"
        . "end\n";
};

$parserMalformedCMapProcedureDirectivePdf = static function (
    string $derivedCMap,
    string $baseCMap
): string {
    $compressedDerivedCMap = gzcompress($derivedCMap, 0);
    $compressedBaseCMap = gzcompress($baseCMap, 0);
    if (!is_string($compressedDerivedCMap) || !is_string($compressedBaseCMap)) {
        throw new RuntimeException('Unable to compress procedure directive CMap fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj T* <0002> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ProcedureDirectiveBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /Filter /FlateDecode /Length " . strlen($compressedDerivedCMap) . " >>\nstream\n{$compressedDerivedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /CMap /Filter /FlateDecode /Length " . strlen($compressedBaseCMap) . " >>\nstream\n{$compressedBaseCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'ignores filtered CMap procedure-body WMode decoys while keeping top-level writing mode semantics' => static function (TestRunner $t) use ($parserMalformedCMapProcedureDirectiveFilteredWModePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapProcedureDirectiveFilteredWModePdf("{ /WMode 1 def } pop\n");
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);

        $t->same(['Vert', 'Import', 'Data', 'Flow'], $extractor->extractTextLines($pdf));
        $t->same("Vert\nImport\nData\nFlow", $plainText);
        $t->true(!str_contains($plainText, 'VertImport'));
        $t->true(!str_contains($plainText, 'Data Flow'));
        $t->same(2, $review['decoded_cmap_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(0, $review['unsupported_filter_count']);
        $t->same(['FlateDecode'], $review['entries'][0]['filters']);
        $t->same('filters_resolved', $review['entries'][0]['filter_operand_policy']);
    },

    'ignores filtered CMap procedure-body usecmap decoys before source mapping operators' => static function (TestRunner $t) use (
        $parserMalformedCMapProcedureDirectiveUtf16beHex,
        $parserMalformedCMapProcedureDirectiveToUnicode,
        $parserMalformedCMapProcedureDirectivePdf
    ): void {
        $extractor = new PdfTextExtractor();
        $safeText = 'Procedure UseCMap Safe';
        $leakingText = 'Procedure UseCMap Leak';
        $derivedCMap = $parserMalformedCMapProcedureDirectiveToUnicode(
            'ProcedureUseCMapDerived-H',
            "{ /ProcedureUseCMapBase-H usecmap } pop\n"
                . "1 begincodespacerange\n"
                . "<0000> <FFFF>\n"
                . "endcodespacerange\n"
                . "1 beginbfchar\n"
                . "<0001> <" . $parserMalformedCMapProcedureDirectiveUtf16beHex($safeText) . ">\n"
                . "endbfchar\n"
        );
        $baseCMap = $parserMalformedCMapProcedureDirectiveToUnicode(
            'ProcedureUseCMapBase-H',
            "1 begincodespacerange\n"
                . "<0000> <FFFF>\n"
                . "endcodespacerange\n"
                . "1 beginbfchar\n"
                . "<0002> <" . $parserMalformedCMapProcedureDirectiveUtf16beHex($leakingText) . ">\n"
                . "endbfchar\n"
        );
        $pdf = $parserMalformedCMapProcedureDirectivePdf($derivedCMap, $baseCMap);
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entries = [];
        foreach ($review['entries'] as $entry) {
            $entries[$entry['object_number']] = $entry;
        }

        $t->same([$safeText], $extractor->extractTextLines($pdf));
        $t->same($safeText, $plainText);
        $t->true(!str_contains($plainText, $leakingText));
        $t->same(2, $review['decoded_cmap_count']);
        $t->same(0, $review['use_cmap_stream_count']);
        $t->same([], $entries[7]['reference_usages'] ?? null);
        $t->same(['FlateDecode'], $entries[6]['filters'] ?? null);
        $t->same(['FlateDecode'], $entries[7]['filters'] ?? null);
    },

    'ignores filtered CMap procedure-body CMapName decoys when building named CMap imports' => static function (TestRunner $t) use (
        $parserMalformedCMapProcedureDirectiveUtf16beHex,
        $parserMalformedCMapProcedureDirectiveToUnicode,
        $parserMalformedCMapProcedureDirectivePdf
    ): void {
        $extractor = new PdfTextExtractor();
        $safeText = 'Procedure CMapName Safe';
        $leakingText = 'Procedure CMapName Leak';
        $derivedCMap = $parserMalformedCMapProcedureDirectiveToUnicode(
            'ProcedureCMapNameDerived-H',
            "/ProcedureCMapNameBase-H usecmap\n"
                . "1 begincodespacerange\n"
                . "<0000> <FFFF>\n"
                . "endcodespacerange\n"
                . "1 beginbfchar\n"
                . "<0001> <" . $parserMalformedCMapProcedureDirectiveUtf16beHex($safeText) . ">\n"
                . "endbfchar\n"
        );
        $baseCMap = $parserMalformedCMapProcedureDirectiveToUnicode(
            '',
            "{ /CMapName /ProcedureCMapNameBase-H def } pop\n"
                . "1 begincodespacerange\n"
                . "<0000> <FFFF>\n"
                . "endcodespacerange\n"
                . "1 beginbfchar\n"
                . "<0002> <" . $parserMalformedCMapProcedureDirectiveUtf16beHex($leakingText) . ">\n"
                . "endbfchar\n"
        );
        $pdf = $parserMalformedCMapProcedureDirectivePdf($derivedCMap, $baseCMap);
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entries = [];
        foreach ($review['entries'] as $entry) {
            $entries[$entry['object_number']] = $entry;
        }

        $t->same([$safeText], $extractor->extractTextLines($pdf));
        $t->same($safeText, $plainText);
        $t->true(!str_contains($plainText, $leakingText));
        $t->same(2, $review['decoded_cmap_count']);
        $t->same(null, $entries[7]['cmap_name'] ?? null);
        $t->same(['FlateDecode'], $entries[6]['filters'] ?? null);
        $t->same(['FlateDecode'], $entries[7]['filters'] ?? null);
    },
];
