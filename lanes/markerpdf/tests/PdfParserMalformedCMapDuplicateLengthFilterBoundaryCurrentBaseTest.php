<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapDuplicateLengthFilterBoundaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

/**
 * @return array{0: string, 1: string, 2: string, 3: string, 4: int}
 */
$parserMalformedCMapDuplicateLengthFilterBoundaryPdf = static function (
    bool $escapedDuplicateLengthKey
) use ($parserMalformedCMapDuplicateLengthFilterBoundaryUtf16beHex): array {
    $safeText = $escapedDuplicateLengthKey
        ? 'Escaped Length Safe Import'
        : 'Duplicate Length Safe Import';
    $leakingText = $escapedDuplicateLengthKey
        ? 'Escaped Length CMap Leak'
        : 'Duplicate Length CMap Leak';
    $safeHex = $parserMalformedCMapDuplicateLengthFilterBoundaryUtf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMapName = $escapedDuplicateLengthKey
        ? 'EscapedDuplicateLengthBoundary-H'
        : 'DuplicateLengthBoundary-H';
    $baseFont = $escapedDuplicateLengthKey
        ? 'EscapedDuplicateLengthBoundary'
        : 'DuplicateLengthBoundary';
    $duplicateLengthOperand = $escapedDuplicateLengthKey
        ? '/L#65ngth 1'
        : '/Length 1';
    $escapedLengthKeyCount = $escapedDuplicateLengthKey ? 1 : 0;

    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$sourceCode}> <" . $parserMalformedCMapDuplicateLengthFilterBoundaryUtf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress duplicate-Length CMap fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " {$duplicateLengthOperand} >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $leakingText, $cMapName, $escapedLengthKeyCount];
};

$assertMalformedCMapDuplicateLengthFilterBoundary = static function (
    TestRunner $t,
    string $pdf,
    string $safeText,
    string $leakingText,
    string $cMapName,
    int $escapedLengthKeyCount
): void {
    $extractor = new PdfTextExtractor();
    $plainText = $extractor->extractPlainText($pdf);
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];
    $lengthOperand = $entry['length_operand'] ?? [];

    $t->same([$safeText], $extractor->extractTextLines($pdf));
    $t->same([$safeText], $extractor->extractTextRuns($pdf));
    $t->same($safeText, $plainText);
    $t->same($safeText . "\n", $extractor->naiveGetText($pdf));
    $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    $t->same(['1'], $extractor->extractPageLabels($pdf));
    $t->true(!str_contains($plainText, $leakingText));
    $t->true(!str_contains($plainText, $cMapName));
    $t->true(!str_contains($plainText, 'beginbfchar'));
    $t->true(!str_contains($plainText, 'FlateDecode'));
    $t->true(!str_contains($plainText, "\0"));

    $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
    $t->true($review['review_only']);
    $t->same(false, $review['encrypted']);
    $t->same(1, $review['cmap_stream_count']);
    $t->same(1, $review['to_unicode_cmap_stream_count']);
    $t->same(0, $review['encoding_cmap_stream_count']);
    $t->same(0, $review['use_cmap_stream_count']);
    $t->same(0, $review['decoded_cmap_count']);
    $t->same(0, $review['duplicate_filter_declaration_count']);
    $t->same(0, $review['duplicate_decodeparms_declaration_count']);
    $t->same(1, $review['duplicate_length_declaration_count']);
    $t->same($escapedLengthKeyCount, $review['escaped_length_key_count']);
    $t->same(0, $review['unsupported_filter_count']);
    $t->same(0, $review['filter_decode_error_count']);
    $t->same(0, $review['filter_end_marker_problem_count']);
    $t->same(false, $review['executes_python_or_models']);
    $t->same(false, $review['executes_external_pdf_tools']);

    $t->same(6, $entry['object_number'] ?? null);
    $t->same(0, $entry['generation'] ?? null);
    $t->same($cMapName, $entry['cmap_name'] ?? null);
    $t->same(null, $entry['declared_length'] ?? null);
    $t->same(['FlateDecode'], $entry['filters'] ?? null);
    $t->same(false, $entry['filter_resolution_failed'] ?? null);
    $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
    $t->same(0, $entry['duplicate_filter_declaration_count'] ?? null);
    $t->same(0, $entry['duplicate_decodeparms_declaration_count'] ?? null);
    $t->same(1, $entry['duplicate_length_declaration_count'] ?? null);
    $t->same($escapedLengthKeyCount, $entry['escaped_length_key_count'] ?? null);
    $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
    $t->same('filter_end_markers_resolved', $entry['filter_end_marker_policy'] ?? null);
    $t->same('filter_decoders_resolved', $entry['filter_decode_policy'] ?? null);
    $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
    $t->same('reject_duplicate_length_declarations', $entry['length_operand_policy'] ?? null);
    $t->same(null, $entry['decoded_cmap_length'] ?? null);
    $t->same(null, $entry['decoded_cmap_sha256'] ?? null);
    $t->same(false, $entry['decoded_with_current_operands'] ?? null);
    $t->same('direct_operands', $entry['owner_policy'] ?? null);
    $t->same('to_unicode', $entry['reference_usages'][0]['usage'] ?? null);
    $t->same('direct', $lengthOperand['kind'] ?? null);
    $t->same('number', $lengthOperand['token_type'] ?? null);
};

return [
    'rejects duplicate direct CMap Length declarations before filtered ToUnicode decoding' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapDuplicateLengthFilterBoundaryPdf, $assertMalformedCMapDuplicateLengthFilterBoundary): void {
        [$pdf, $safeText, $leakingText, $cMapName, $escapedLengthKeyCount] = $parserMalformedCMapDuplicateLengthFilterBoundaryPdf(false);
        $assertMalformedCMapDuplicateLengthFilterBoundary($t, $pdf, $safeText, $leakingText, $cMapName, $escapedLengthKeyCount);
    },
    'rejects duplicate escaped CMap Length declarations before filtered ToUnicode decoding' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapDuplicateLengthFilterBoundaryPdf, $assertMalformedCMapDuplicateLengthFilterBoundary): void {
        [$pdf, $safeText, $leakingText, $cMapName, $escapedLengthKeyCount] = $parserMalformedCMapDuplicateLengthFilterBoundaryPdf(true);
        $assertMalformedCMapDuplicateLengthFilterBoundary($t, $pdf, $safeText, $leakingText, $cMapName, $escapedLengthKeyCount);
    },
];
