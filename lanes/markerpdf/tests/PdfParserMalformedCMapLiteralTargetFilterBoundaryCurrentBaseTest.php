<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapLiteralTargetFilterBoundaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapLiteralTargetFilterBoundaryPdf = static function (string $targetShape) use (
    $parserMalformedCMapLiteralTargetFilterBoundaryUtf16beHex
): array {
    $safeText = 'Literal Surrogate Safe Import';
    $safeHex = $parserMalformedCMapLiteralTargetFilterBoundaryUtf16beHex($safeText);
    $sourceStart = substr($safeHex, 0, 4);
    $sourceNext = substr($safeHex, 4, 4);
    $literalSurrogateTarget = '(\\330\\000)';
    $cMapName = match ($targetShape) {
        'bfchar' => 'LiteralTargetBfcharSurrogateFilterBoundary-H',
        'bfrange-scalar' => 'LiteralTargetBfrangeScalarSurrogateFilterBoundary-H',
        'bfrange-array' => 'LiteralTargetBfrangeArraySurrogateFilterBoundary-H',
    };

    $mappingBlock = match ($targetShape) {
        'bfchar' => "1 beginbfchar\n"
            . "<{$sourceStart}> {$literalSurrogateTarget}\n"
            . "endbfchar\n",
        'bfrange-scalar' => "1 beginbfrange\n"
            . "<{$sourceStart}> <{$sourceStart}> {$literalSurrogateTarget}\n"
            . "endbfrange\n",
        'bfrange-array' => "1 beginbfrange\n"
            . "<{$sourceStart}> <{$sourceNext}> [ {$literalSurrogateTarget} <{$sourceNext}> ]\n"
            . "endbfrange\n",
    };

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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
    $compressedCMap = gzcompress($toUnicode, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress malformed literal-target CMap fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralTargetSurrogateFilterBoundary /Encoding /Identity-H /DescendantFonts [7 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LiteralTargetSurrogateFilterBoundary /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 >>\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $cMapName, $targetShape];
};

$assertMalformedCMapLiteralTargetFilterBoundary = static function (
    TestRunner $t,
    string $pdf,
    string $safeText,
    string $cMapName,
    string $targetShape
): void {
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
    $t->true(is_array($spans[0]['bbox'] ?? null));
    $t->true(is_array($line['bbox'] ?? null));
    $t->true((float) ($spans[0]['bbox'][2] ?? 0.0) > 0.0);
    $t->true(!str_contains($plainText, $cMapName));
    $t->true(!str_contains($plainText, 'beginbfchar'));
    $t->true(!str_contains($plainText, 'beginbfrange'));
    $t->true(!str_contains($plainText, "\0"));
    $t->true(!str_contains($plainText, "\u{FFFD}"));
    $t->true(!str_contains($plainText, "\xD8"));

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
    $t->true(in_array($targetShape, ['bfchar', 'bfrange-scalar', 'bfrange-array'], true));
};

return [
    'rejects malformed literal ToUnicode bfchar targets after filtered current-base CMap decoding' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapLiteralTargetFilterBoundaryPdf, $assertMalformedCMapLiteralTargetFilterBoundary): void {
        [$pdf, $safeText, $cMapName, $targetShape] = $parserMalformedCMapLiteralTargetFilterBoundaryPdf('bfchar');
        $assertMalformedCMapLiteralTargetFilterBoundary($t, $pdf, $safeText, $cMapName, $targetShape);
    },

    'rejects malformed literal ToUnicode scalar bfrange targets after filtered current-base CMap decoding' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapLiteralTargetFilterBoundaryPdf, $assertMalformedCMapLiteralTargetFilterBoundary): void {
        [$pdf, $safeText, $cMapName, $targetShape] = $parserMalformedCMapLiteralTargetFilterBoundaryPdf('bfrange-scalar');
        $assertMalformedCMapLiteralTargetFilterBoundary($t, $pdf, $safeText, $cMapName, $targetShape);
    },

    'rejects malformed literal ToUnicode bfrange array targets after filtered current-base CMap decoding' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapLiteralTargetFilterBoundaryPdf, $assertMalformedCMapLiteralTargetFilterBoundary): void {
        [$pdf, $safeText, $cMapName, $targetShape] = $parserMalformedCMapLiteralTargetFilterBoundaryPdf('bfrange-array');
        $assertMalformedCMapLiteralTargetFilterBoundary($t, $pdf, $safeText, $cMapName, $targetShape);
    },
];
