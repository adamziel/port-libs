<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapEscapedKeyFilterBoundaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapEscapedKeyFilterBoundaryPdf = static function (
    string $safeText,
    string $mappedText,
    string $cMapName,
    string $baseFont,
    bool $extraFilterAfterLength
) use ($parserMalformedCMapEscapedKeyFilterBoundaryUtf16beHex): array {
    $safeHex = $parserMalformedCMapEscapedKeyFilterBoundaryUtf16beHex($safeText);
    $sourceCode = $extraFilterAfterLength ? substr($safeHex, 0, 4) : '0001';
    $contentHex = $extraFilterAfterLength ? $safeHex : '0001';
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$sourceCode}> <" . $parserMalformedCMapEscapedKeyFilterBoundaryUtf16beHex($mappedText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress focused escaped-key CMap fixture.');
    }

    $extraFilterOperand = $extraFilterAfterLength ? ' /ASCIIHexDecode' : '';
    $content = "BT /Fcid 12 Tf 72 720 Td <{$contentHex}> Tj ET";
    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Fil#74er /FlateDecode /Decode#50arms << /Predictor 1 >> /Len#67th "
        . strlen($compressedCMap) . "{$extraFilterOperand} >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, strlen($compressedCMap)];
};

return [
    'decodes CMap streams whose Filter DecodeParms and Length keys use PDF name escapes' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapEscapedKeyFilterBoundaryPdf): void {
        [$pdf, $declaredLength] = $parserMalformedCMapEscapedKeyFilterBoundaryPdf(
            'Escaped Key Fallback',
            'Escaped Key CMap Import',
            'EscapedKeyFilterBoundary-H',
            'EscapedKeyFilterBoundary',
            false
        );
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];

        $t->same(['Escaped Key CMap Import'], $extractor->extractTextLines($pdf));
        $t->same(['Escaped Key CMap Import'], $extractor->extractTextRuns($pdf));
        $t->same('Escaped Key CMap Import', $plainText);
        $t->same("Escaped Key CMap Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, 'EscapedKeyFilterBoundary-H'));
        $t->true(!str_contains($plainText, 'Fil#74er'));
        $t->true(!str_contains($plainText, 'Decode#50arms'));
        $t->true(!str_contains($plainText, 'Len#67th'));
        $t->true(!str_contains($plainText, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(1, $review['decoded_cmap_count']);
        $t->same(0, $review['invalid_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(1, $review['escaped_filter_key_count'] ?? null);
        $t->same(1, $review['escaped_decodeparms_key_count'] ?? null);
        $t->same(1, $review['escaped_length_key_count'] ?? null);
        $t->same(3, $review['escaped_stream_dictionary_key_count'] ?? null);

        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same('EscapedKeyFilterBoundary-H', $entry['cmap_name'] ?? null);
        $t->same($declaredLength, $entry['declared_length'] ?? null);
        $t->same(['FlateDecode'], $entry['filters'] ?? null);
        $t->same(false, $entry['filter_resolution_failed'] ?? null);
        $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
        $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
        $t->same('filter_decoders_resolved', $entry['filter_decode_policy'] ?? null);
        $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
        $t->same(true, $entry['decoded_with_current_operands'] ?? null);
        $t->same(1, $entry['escaped_filter_key_count'] ?? null);
        $t->same(1, $entry['escaped_decodeparms_key_count'] ?? null);
        $t->same(1, $entry['escaped_length_key_count'] ?? null);
        $t->same(3, $entry['escaped_stream_dictionary_key_count'] ?? null);
        $t->same('FlateDecode', $entry['filter_operands'][0]['value'] ?? null);
        $t->same('<< /Predictor 1 >>', $entry['decodeparms_operands'][0]['value'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'fails closed when an escaped CMap Length key is followed by an extra filter-name operand' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapEscapedKeyFilterBoundaryPdf): void {
        [$pdf, $declaredLength] = $parserMalformedCMapEscapedKeyFilterBoundaryPdf(
            'Escaped Key Safe Import',
            'Escaped Key CMap Leak',
            'EscapedKeyExtraFilterBoundary-H',
            'EscapedKeyExtraFilterBoundary',
            true
        );
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperand = $entry['filter_operands'][0] ?? [];

        $t->same(['Escaped Key Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Escaped Key Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Escaped Key Safe Import', $plainText);
        $t->same("Escaped Key Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, 'Escaped Key CMap Leak'));
        $t->true(!str_contains($plainText, 'EscapedKeyExtraFilterBoundary-H'));
        $t->true(!str_contains($plainText, 'ASCIIHexDecode'));
        $t->true(!str_contains($plainText, 'beginbfchar'));
        $t->true(!str_contains($plainText, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(1, $review['invalid_filter_operand_count']);
        $t->same(1, $review['malformed_filter_operand_count']);
        $t->same(1, $review['escaped_filter_key_count'] ?? null);
        $t->same(1, $review['escaped_decodeparms_key_count'] ?? null);
        $t->same(1, $review['escaped_length_key_count'] ?? null);
        $t->same(3, $review['escaped_stream_dictionary_key_count'] ?? null);

        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same('EscapedKeyExtraFilterBoundary-H', $entry['cmap_name'] ?? null);
        $t->same($declaredLength, $entry['declared_length'] ?? null);
        $t->same([], $entry['filters'] ?? null);
        $t->same(true, $entry['filter_resolution_failed'] ?? null);
        $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
        $t->same('reject_malformed_filter_operands', $entry['filter_operand_policy'] ?? null);
        $t->same('filter_resolution_failed', $entry['filter_decode_policy'] ?? null);
        $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same(1, $entry['escaped_filter_key_count'] ?? null);
        $t->same(1, $entry['escaped_decodeparms_key_count'] ?? null);
        $t->same(1, $entry['escaped_length_key_count'] ?? null);
        $t->same(3, $entry['escaped_stream_dictionary_key_count'] ?? null);

        $t->same('direct', $filterOperand['kind'] ?? null);
        $t->same('name', $filterOperand['token_type'] ?? null);
        $t->same('FlateDecode', $filterOperand['value'] ?? null);
        $t->same(false, $filterOperand['valid_filter_operand'] ?? null);
        $t->same(true, $filterOperand['extra_filter_operand'] ?? null);
        $t->same('name', $filterOperand['extra_filter_operand_type'] ?? null);
        $t->same('/ASCIIHexDecode', $filterOperand['extra_filter_operand_preview'] ?? null);
        $t->same(true, $filterOperand['extra_filter_name_operand'] ?? null);
        $t->same('ASCIIHexDecode', $filterOperand['extra_filter_name'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
