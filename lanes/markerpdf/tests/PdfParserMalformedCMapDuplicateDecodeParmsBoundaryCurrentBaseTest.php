<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapDuplicateDecodeParmsBoundaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapDuplicateDecodeParmsBoundaryPdf = static function () use (
    $parserMalformedCMapDuplicateDecodeParmsBoundaryUtf16beHex
): string {
    $safeText = 'Duplicate DecodeParms Safe Import';
    $leakingText = 'Duplicate DecodeParms CMap Leak';
    $safeHex = $parserMalformedCMapDuplicateDecodeParmsBoundaryUtf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /DuplicateDecodeParmsBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$sourceCode}> <" . $parserMalformedCMapDuplicateDecodeParmsBoundaryUtf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress duplicate DecodeParms CMap fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DuplicateDecodeParmsBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /DuplicateDecodeParmsBoundary-H /Filter /FlateDecode /DecodeParms << /Predictor 1 >> /Decode#50arms << /Predictor 12 /Columns 1 >> /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects duplicate escaped CMap DecodeParms declarations before current-base text extraction' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapDuplicateDecodeParmsBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapDuplicateDecodeParmsBoundaryPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];

        $t->same(['Duplicate DecodeParms Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Duplicate DecodeParms Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Duplicate DecodeParms Safe Import', $plainText);
        $t->same("Duplicate DecodeParms Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, 'Duplicate DecodeParms CMap Leak'));
        $t->true(!str_contains($plainText, 'DuplicateDecodeParmsBoundary-H'));
        $t->true(!str_contains($plainText, 'Decode#50arms'));
        $t->true(!str_contains($plainText, 'beginbfchar'));
        $t->true(!str_contains($plainText, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(0, $review['duplicate_filter_declaration_count']);
        $t->same(1, $review['duplicate_decodeparms_declaration_count']);
        $t->same(0, $review['invalid_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(1, $review['escaped_decodeparms_key_count']);
        $t->same(1, $review['escaped_stream_dictionary_key_count']);
        $t->same(0, $review['filter_end_marker_problem_count']);
        $t->same(0, $review['filter_decode_error_count']);

        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same('DuplicateDecodeParmsBoundary-H', $entry['cmap_name'] ?? null);
        $t->same(['FlateDecode'], $entry['filters'] ?? null);
        $t->same(false, $entry['filter_resolution_failed'] ?? null);
        $t->same(true, $entry['decodeparms_resolution_failed'] ?? null);
        $t->same(0, $entry['duplicate_filter_declaration_count'] ?? null);
        $t->same(1, $entry['duplicate_decodeparms_declaration_count'] ?? null);
        $t->same(0, $entry['invalid_decodeparms_operand_count'] ?? null);
        $t->same(0, $entry['malformed_decodeparms_operand_count'] ?? null);
        $t->same(0, $entry['invalid_decodeparms_parameter_count'] ?? null);
        $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
        $t->same('reject_duplicate_decodeparms_declarations', $entry['filter_end_marker_policy'] ?? null);
        $t->same('reject_duplicate_decodeparms_declarations', $entry['filter_decode_policy'] ?? null);
        $t->same('reject_duplicate_decodeparms_declarations', $entry['decodeparms_operand_policy'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(null, $entry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same(1, $entry['escaped_decodeparms_key_count'] ?? null);
        $t->same(1, $entry['escaped_stream_dictionary_key_count'] ?? null);
        $t->same('<< /Predictor 1 >>', $entry['decodeparms_operands'][0]['value'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
