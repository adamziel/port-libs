<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapSplitRowFilterBoundaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapSplitRowFilterBoundaryPdf = static function (
    string $mappingBlock,
    string $mappedText,
    string $cMapName,
    string $baseFont
) use ($parserMalformedCMapSplitRowFilterBoundaryUtf16beHex): string {
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0001>\n"
        . "<0001>\n"
        . "endcodespacerange\n"
        . $mappingBlock
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress split-row CMap filter fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$assertParserMalformedCMapSplitRowFilterBoundary = static function (
    TestRunner $t,
    string $pdf,
    string $mappedText,
    string $cMapName
): void {
    $extractor = new PdfTextExtractor();
    $plainText = $extractor->extractPlainText($pdf);
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];

    $t->same([$mappedText], $extractor->extractTextLines($pdf));
    $t->same([$mappedText], $extractor->extractTextRuns($pdf));
    $t->same($mappedText, $plainText);
    $t->same($mappedText . "\n", $extractor->naiveGetText($pdf));
    $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    $t->same(['1'], $extractor->extractPageLabels($pdf));
    $t->true(!str_contains($plainText, $cMapName));
    $t->true(!str_contains($plainText, 'begincodespacerange'));
    $t->true(!str_contains($plainText, 'beginbfchar'));
    $t->true(!str_contains($plainText, 'beginbfrange'));
    $t->true(!str_contains($plainText, "\0"));

    $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
    $t->true($review['review_only']);
    $t->same(false, $review['encrypted']);
    $t->same(1, $review['cmap_stream_count']);
    $t->same(1, $review['to_unicode_cmap_stream_count']);
    $t->same(0, $review['encoding_cmap_stream_count']);
    $t->same(1, $review['decoded_cmap_count']);
    $t->same(0, $review['invalid_filter_operand_count']);
    $t->same(0, $review['malformed_filter_operand_count']);
    $t->same(0, $review['unsupported_filter_count']);
    $t->same(0, $review['filter_end_marker_problem_count']);
    $t->same(0, $review['filter_decode_error_count']);

    $t->same(6, $entry['object_number'] ?? null);
    $t->same(0, $entry['generation'] ?? null);
    $t->same($cMapName, $entry['cmap_name'] ?? null);
    $t->same(['FlateDecode'], $entry['filters'] ?? null);
    $t->same(false, $entry['filter_resolution_failed'] ?? null);
    $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
    $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
    $t->same('filter_end_markers_resolved', $entry['filter_end_marker_policy'] ?? null);
    $t->same('filter_decoders_resolved', $entry['filter_decode_policy'] ?? null);
    $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
    $t->true(is_int($entry['decoded_cmap_length'] ?? null) && ($entry['decoded_cmap_length'] ?? 0) > 0);
    $t->true(is_string($entry['decoded_cmap_sha256'] ?? null) && strlen((string) $entry['decoded_cmap_sha256']) === 64);
    $t->same(true, $entry['decoded_with_current_operands'] ?? null);
    $t->same('direct_operands', $entry['owner_policy'] ?? null);
    $t->same('to_unicode', $entry['reference_usages'][0]['usage'] ?? null);
    $t->same(false, $review['executes_python_or_models']);
    $t->same(false, $review['executes_external_pdf_tools']);
};

return [
    'decodes filtered ToUnicode bfchar rows split across PDF whitespace before current-base text extraction' => static function (
        TestRunner $t
    ) use (
        $parserMalformedCMapSplitRowFilterBoundaryUtf16beHex,
        $parserMalformedCMapSplitRowFilterBoundaryPdf,
        $assertParserMalformedCMapSplitRowFilterBoundary
    ): void {
        $mappedText = 'Split Bfchar Import';
        $pdf = $parserMalformedCMapSplitRowFilterBoundaryPdf(
            "1 beginbfchar\n"
                . "<0001>\n"
                . "<" . $parserMalformedCMapSplitRowFilterBoundaryUtf16beHex($mappedText) . ">\n"
                . "endbfchar\n",
            $mappedText,
            'SplitBfcharFilterBoundary-H',
            'SplitBfcharFilterBoundary'
        );

        $assertParserMalformedCMapSplitRowFilterBoundary($t, $pdf, $mappedText, 'SplitBfcharFilterBoundary-H');
    },
    'decodes filtered ToUnicode bfrange rows split across PDF whitespace before current-base text extraction' => static function (
        TestRunner $t
    ) use (
        $parserMalformedCMapSplitRowFilterBoundaryUtf16beHex,
        $parserMalformedCMapSplitRowFilterBoundaryPdf,
        $assertParserMalformedCMapSplitRowFilterBoundary
    ): void {
        $mappedText = 'Split Bfrange Import';
        $pdf = $parserMalformedCMapSplitRowFilterBoundaryPdf(
            "1 beginbfrange\n"
                . "<0001>\n"
                . "<0001>\n"
                . "<" . $parserMalformedCMapSplitRowFilterBoundaryUtf16beHex($mappedText) . ">\n"
                . "endbfrange\n",
            $mappedText,
            'SplitBfrangeFilterBoundary-H',
            'SplitBfrangeFilterBoundary'
        );

        $assertParserMalformedCMapSplitRowFilterBoundary($t, $pdf, $mappedText, 'SplitBfrangeFilterBoundary-H');
    },
    'keeps filtered ToUnicode bfrange declared counts when split rows force token recovery before current-base text extraction' => static function (
        TestRunner $t
    ) use (
        $parserMalformedCMapSplitRowFilterBoundaryUtf16beHex,
        $parserMalformedCMapSplitRowFilterBoundaryPdf,
        $assertParserMalformedCMapSplitRowFilterBoundary
    ): void {
        $mappedText = 'Declared Split Bfrange Import';
        $ignoredText = 'Ignored Overdeclared Bfrange Import';
        $pdf = $parserMalformedCMapSplitRowFilterBoundaryPdf(
            "1 beginbfrange\n"
                . "<0001>\n"
                . "<0001>\n"
                . "<" . $parserMalformedCMapSplitRowFilterBoundaryUtf16beHex($mappedText) . ">\n"
                . "<0001> <0001> <" . $parserMalformedCMapSplitRowFilterBoundaryUtf16beHex($ignoredText) . ">\n"
                . "endbfrange\n",
            $mappedText,
            'DeclaredSplitBfrangeFilterBoundary-H',
            'DeclaredSplitBfrangeFilterBoundary'
        );

        $assertParserMalformedCMapSplitRowFilterBoundary($t, $pdf, $mappedText, 'DeclaredSplitBfrangeFilterBoundary-H');
        $t->true(!str_contains($extractorText = (new PdfTextExtractor())->extractPlainText($pdf), $ignoredText));
        $t->same($mappedText, $extractorText);
    },
];
