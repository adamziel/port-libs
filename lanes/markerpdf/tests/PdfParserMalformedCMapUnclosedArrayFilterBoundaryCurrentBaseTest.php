<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapUnclosedArrayFilterBoundaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapUnclosedArrayFilterBoundaryPdf = static function (
    string $kind,
    string $safeText,
    string $leakingText,
    string $baseFont,
    string $cMapName
) use ($parserMalformedCMapUnclosedArrayFilterBoundaryUtf16beHex): array {
    $safeHex = $parserMalformedCMapUnclosedArrayFilterBoundaryUtf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $targetHex = $parserMalformedCMapUnclosedArrayFilterBoundaryUtf16beHex($leakingText);
    $mappingBlock = $kind === 'range'
        ? "1 beginbfrange\n"
            . "<{$sourceCode}> <{$sourceCode}> <{$targetHex}>\n"
            . "endbfrange\n"
        : "1 beginbfchar\n"
            . "<{$sourceCode}> <{$targetHex}>\n"
            . "endbfchar\n";

    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "[ /MalformedArray (unterminated array owns later tokens)\n"
        . $mappingBlock
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress unclosed-array CMap fixture.');
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

    return [$pdf, $safeText, $leakingText, $cMapName, $kind];
};

$assertMalformedCMapUnclosedArrayFilterBoundary = static function (
    TestRunner $t,
    array $fixture
): void {
    [$pdf, $safeText, $leakingText, $cMapName, $kind] = $fixture;
    $extractor = new PdfTextExtractor();
    $plainText = $extractor->extractPlainText($pdf);
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];
    $filterOperand = $entry['filter_operands'][0] ?? [];

    $t->same([$safeText], $extractor->extractTextLines($pdf));
    $t->same([$safeText], $extractor->extractTextRuns($pdf));
    $t->same($safeText, $plainText);
    $t->same($safeText . "\n", $extractor->naiveGetText($pdf));
    $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    $t->same(['1'], $extractor->extractPageLabels($pdf));
    $t->true(!str_contains($plainText, $leakingText));
    $t->true(!str_contains($plainText, $cMapName));
    $t->true(!str_contains($plainText, 'MalformedArray'));
    $t->true(!str_contains($plainText, 'beginbf'));
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
    $t->same(true, $entry['decoded_with_current_operands'] ?? null);
    $t->true(($entry['decoded_cmap_length'] ?? 0) > 0);
    $t->true(($entry['parser_bounded_cmap_length'] ?? 0) > 0);

    $t->same('direct', $filterOperand['kind'] ?? null);
    $t->same('name', $filterOperand['token_type'] ?? null);
    $t->same('FlateDecode', $filterOperand['value'] ?? null);
    $t->same(true, $filterOperand['valid_filter_operand'] ?? null);
    $t->same(false, $filterOperand['dictionary_filter_operand'] ?? null);
    $t->same(false, $review['executes_python_or_models']);
    $t->same(false, $review['executes_external_pdf_tools']);
    $t->true(in_array($kind, ['char', 'range'], true));
};

return [
    'ignores filtered ToUnicode bfchar blocks inside unclosed CMap arrays before current-base text extraction' => static function (
        TestRunner $t
    ) use (
        $parserMalformedCMapUnclosedArrayFilterBoundaryPdf,
        $assertMalformedCMapUnclosedArrayFilterBoundary
    ): void {
        $assertMalformedCMapUnclosedArrayFilterBoundary(
            $t,
            $parserMalformedCMapUnclosedArrayFilterBoundaryPdf(
                'char',
                'Unclosed Array Char Safe Import',
                'Unclosed Array Char CMap Leak',
                'UnclosedArrayCharBoundary',
                'UnclosedArrayCharBoundary-H'
            )
        );
    },
    'ignores filtered ToUnicode bfrange blocks inside unclosed CMap arrays before current-base text extraction' => static function (
        TestRunner $t
    ) use (
        $parserMalformedCMapUnclosedArrayFilterBoundaryPdf,
        $assertMalformedCMapUnclosedArrayFilterBoundary
    ): void {
        $assertMalformedCMapUnclosedArrayFilterBoundary(
            $t,
            $parserMalformedCMapUnclosedArrayFilterBoundaryPdf(
                'range',
                'Unclosed Array Range Safe Import',
                'Unclosed Array Range CMap Leak',
                'UnclosedArrayRangeBoundary',
                'UnclosedArrayRangeBoundary-H'
            )
        );
    },
];
