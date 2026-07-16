<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapVerticalTabFilterUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapVerticalTabFilterPdf = static function (
    string $mappingKind
) use ($parserMalformedCMapVerticalTabFilterUtf16beHex): array {
    $safeText = $mappingKind === 'range'
        ? 'Vertical Tab Range Safe Import'
        : 'Vertical Tab Char Safe Import';
    $leakingText = $mappingKind === 'range'
        ? 'Vertical Tab Range CMap Leak'
        : 'Vertical Tab Char CMap Leak';
    $safeHex = $parserMalformedCMapVerticalTabFilterUtf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMapName = $mappingKind === 'range'
        ? 'VerticalTabRangeFilterBoundary-H'
        : 'VerticalTabCharFilterBoundary-H';
    $baseFont = $mappingKind === 'range'
        ? 'VerticalTabRangeFilterBoundary'
        : 'VerticalTabCharFilterBoundary';
    $verticalTab = "\v";
    $mappingBlock = $mappingKind === 'range'
        ? "1 beginbfrange\n"
            . "<{$sourceCode}>{$verticalTab}<{$sourceCode}> <"
            . $parserMalformedCMapVerticalTabFilterUtf16beHex($leakingText)
            . ">\n"
            . "endbfrange\n"
        : "1 beginbfchar\n"
            . "<{$sourceCode}>{$verticalTab}<"
            . $parserMalformedCMapVerticalTabFilterUtf16beHex($leakingText)
            . ">\n"
            . "endbfchar\n";

    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . $mappingBlock
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress vertical-tab CMap fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $leakingText, $cMapName, $mappingKind];
};

$assertMalformedCMapVerticalTabFilter = static function (TestRunner $t, array $fixture): void {
    [$pdf, $safeText, $leakingText, $cMapName, $mappingKind] = $fixture;
    $extractor = new PdfTextExtractor();
    $plainText = $extractor->extractPlainText($pdf);
    $pages = $extractor->extractStyledTextPages($pdf);
    $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
    $spans = $line['spans'] ?? [];
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];

    $t->same([$safeText], $extractor->extractTextLines($pdf));
    $t->same([$safeText], $extractor->extractTextRuns($pdf));
    $t->same($safeText, $plainText);
    $t->same($safeText . "\n", $extractor->naiveGetText($pdf));
    $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    $t->same(['1'], $extractor->extractPageLabels($pdf));
    $t->same([$safeText], array_column($spans, 'text'));
    $t->true(!str_contains($plainText, $leakingText));
    $t->true(!str_contains($plainText, $cMapName));
    $t->true(!str_contains($plainText, 'beginbf'));
    $t->true(!str_contains($plainText, "\0"));
    $t->true(str_contains($pdf, "\v"));

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
    $t->same(true, $entry['decoded_with_current_operands'] ?? null);
    $t->true(($entry['decoded_cmap_length'] ?? 0) > 0);
    $t->true(($entry['parser_bounded_cmap_length'] ?? 0) > 0);
    $t->same('to_unicode', $entry['reference_usages'][0]['usage'] ?? null);
    $t->same(false, $review['executes_python_or_models']);
    $t->same(false, $review['executes_external_pdf_tools']);
    $t->same($mappingKind === 'range' ? 'range' : 'char', $mappingKind);
};

return [
    'rejects filtered ToUnicode bfchar rows separated by vertical tab before current-base text extraction' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapVerticalTabFilterPdf, $assertMalformedCMapVerticalTabFilter): void {
        $assertMalformedCMapVerticalTabFilter($t, $parserMalformedCMapVerticalTabFilterPdf('char'));
    },
    'rejects filtered ToUnicode bfrange rows separated by vertical tab before current-base text extraction' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapVerticalTabFilterPdf, $assertMalformedCMapVerticalTabFilter): void {
        $assertMalformedCMapVerticalTabFilter($t, $parserMalformedCMapVerticalTabFilterPdf('range'));
    },
];
