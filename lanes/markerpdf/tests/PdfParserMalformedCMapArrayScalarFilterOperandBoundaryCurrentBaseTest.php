<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapArrayScalarFilterOperandUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapArrayScalarFilterOperandPdf = static function (
    string $filterItem,
    string $safeText,
    string $leakingText,
    string $cMapName,
    string $baseFont
) use ($parserMalformedCMapArrayScalarFilterOperandUtf16beHex): array {
    $safeHex = $parserMalformedCMapArrayScalarFilterOperandUtf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$sourceCode}> <" . $parserMalformedCMapArrayScalarFilterOperandUtf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress malformed CMap array scalar filter operand fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter [ {$filterItem} /FlateDecode ] /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $leakingText, $cMapName];
};

$assertMalformedCMapArrayScalarFilterOperandBoundary = static function (
    TestRunner $t,
    array $fixture,
    string $expectedTokenType,
    mixed $expectedValue
): void {
    [$pdf, $safeText, $leakingText, $cMapName] = $fixture;
    $extractor = new PdfTextExtractor();
    $plainText = $extractor->extractPlainText($pdf);
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];
    $filterOperands = $entry['filter_operands'] ?? [];

    $t->same([$safeText], $extractor->extractTextLines($pdf));
    $t->same([$safeText], $extractor->extractTextRuns($pdf));
    $t->same($safeText, $plainText);
    $t->same($safeText . "\n", $extractor->naiveGetText($pdf));
    $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    $t->same(['1'], $extractor->extractPageLabels($pdf));
    $t->true(!str_contains($plainText, $leakingText));
    $t->true(!str_contains($plainText, $cMapName));
    $t->true(!str_contains($plainText, 'beginbfchar'));
    $t->true(!str_contains($plainText, "\0"));

    $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
    $t->true($review['review_only']);
    $t->same(false, $review['encrypted']);
    $t->same(1, $review['cmap_stream_count']);
    $t->same(1, $review['to_unicode_cmap_stream_count']);
    $t->same(0, $review['encoding_cmap_stream_count']);
    $t->same(0, $review['decoded_cmap_count']);
    $t->same(1, $review['invalid_filter_operand_count']);
    $t->same(0, $review['dictionary_filter_operand_count']);
    $t->same(1, $review['malformed_filter_operand_count']);
    $t->same(0, $review['duplicate_filter_declaration_count']);
    $t->same(0, $review['unsupported_filter_count']);
    $t->same(0, $review['filter_decode_error_count']);

    $t->same(6, $entry['object_number'] ?? null);
    $t->same(0, $entry['generation'] ?? null);
    $t->same($cMapName, $entry['cmap_name'] ?? null);
    $t->same([], $entry['filters'] ?? null);
    $t->same(true, $entry['filter_resolution_failed'] ?? null);
    $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
    $t->same(1, $entry['invalid_filter_operand_count'] ?? null);
    $t->same(0, $entry['dictionary_filter_operand_count'] ?? null);
    $t->same(1, $entry['malformed_filter_operand_count'] ?? null);
    $t->same('reject_malformed_filter_operands', $entry['filter_operand_policy'] ?? null);
    $t->same('filter_resolution_failed', $entry['filter_end_marker_policy'] ?? null);
    $t->same('filter_resolution_failed', $entry['filter_decode_policy'] ?? null);
    $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
    $t->same(null, $entry['decoded_cmap_length'] ?? null);
    $t->same(null, $entry['decoded_cmap_sha256'] ?? null);
    $t->same(false, $entry['decoded_with_current_operands'] ?? null);
    $t->same('direct_operands', $entry['owner_policy'] ?? null);
    $t->same('to_unicode', $entry['reference_usages'][0]['usage'] ?? null);

    $t->same(2, count($filterOperands));
    $t->same('direct', $filterOperands[0]['kind'] ?? null);
    $t->same($expectedTokenType, $filterOperands[0]['token_type'] ?? null);
    $t->same($expectedValue, $filterOperands[0]['value'] ?? null);
    $t->same(false, $filterOperands[0]['valid_filter_operand'] ?? null);
    $t->same(false, $filterOperands[0]['dictionary_filter_operand'] ?? null);
    $t->same(true, $filterOperands[0]['array_item'] ?? null);
    $t->same(0, $filterOperands[0]['array_index'] ?? null);

    $t->same('direct', $filterOperands[1]['kind'] ?? null);
    $t->same('name', $filterOperands[1]['token_type'] ?? null);
    $t->same('FlateDecode', $filterOperands[1]['value'] ?? null);
    $t->same(true, $filterOperands[1]['valid_filter_operand'] ?? null);
    $t->same(false, $filterOperands[1]['dictionary_filter_operand'] ?? null);
    $t->same(true, $filterOperands[1]['array_item'] ?? null);
    $t->same(1, $filterOperands[1]['array_index'] ?? null);
    $t->same(false, $review['executes_python_or_models']);
    $t->same(false, $review['executes_external_pdf_tools']);
};

return [
    'fails closed when a boolean appears inside a CMap Filter array before current-base text extraction' => static function (TestRunner $t) use (
        $parserMalformedCMapArrayScalarFilterOperandPdf,
        $assertMalformedCMapArrayScalarFilterOperandBoundary
    ): void {
        $assertMalformedCMapArrayScalarFilterOperandBoundary(
            $t,
            $parserMalformedCMapArrayScalarFilterOperandPdf(
                'true',
                'Array Boolean Filter Safe Import',
                'Array Boolean Filter CMap Leak',
                'ArrayBooleanScalarFilterBoundary-H',
                'ArrayBooleanScalarFilterBoundary'
            ),
            'boolean',
            true
        );
    },
    'fails closed when a real number appears inside a CMap Filter array before current-base text extraction' => static function (TestRunner $t) use (
        $parserMalformedCMapArrayScalarFilterOperandPdf,
        $assertMalformedCMapArrayScalarFilterOperandBoundary
    ): void {
        $assertMalformedCMapArrayScalarFilterOperandBoundary(
            $t,
            $parserMalformedCMapArrayScalarFilterOperandPdf(
                '1.5',
                'Array Number Filter Safe Import',
                'Array Number Filter CMap Leak',
                'ArrayNumberScalarFilterBoundary-H',
                'ArrayNumberScalarFilterBoundary'
            ),
            'number',
            1.5
        );
    },
];
