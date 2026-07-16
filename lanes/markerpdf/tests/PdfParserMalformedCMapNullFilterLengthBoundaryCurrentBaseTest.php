<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapNullFilterLengthZlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Focused CMap stale-length payload must fit one stored deflate block.');
    }

    $s1 = 1;
    $s2 = 0;
    for ($index = 0; $index < $length; $index++) {
        $s1 = ($s1 + ord($bytes[$index])) % 65521;
        $s2 = ($s2 + $s1) % 65521;
    }

    return "\x78\x01"
        . "\x01"
        . pack('v', $length)
        . pack('v', (~$length) & 0xffff)
        . $bytes
        . pack('N', ($s2 << 16) | $s1);
};

$parserMalformedCMapNullFilterLengthUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapNullFilterLengthBoundaryCurrentBasePdf = static function () use (
    $parserMalformedCMapNullFilterLengthZlibStored,
    $parserMalformedCMapNullFilterLengthUtf16beHex
): array {
    $mappedText = 'Recovered Null Length CMap Import';
    $fakeObjectText = 'Null Filter Length Fake Object Leak';
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /NullFilterLengthBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0001> <0001>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $parserMalformedCMapNullFilterLengthUtf16beHex($mappedText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n"
        . "endstream\nendobj\n"
        . "90 0 obj\n<< /Length " . strlen($fakeObjectText) . " >>\nstream\n"
        . "BT /Fcid 12 Tf 72 650 Td ({$fakeObjectText}) Tj ET\n"
        . "endstream\nendobj\n";
    $compressedCMap = $parserMalformedCMapNullFilterLengthZlibStored($cMap);
    $fakeTerminatorOffset = strpos($compressedCMap, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused CMap stale-length fixture must expose a fake raw endstream marker.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NullFilterLengthBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /NullFilterLengthBoundary-H /Filter [ null /FlateDecode ] /DecodeParms [ 99 0 R << /Predictor 1 >> ] /Length {$fakeTerminatorOffset} >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $fakeTerminatorOffset, strlen($compressedCMap), $mappedText, $fakeObjectText];
};

return [
    'recovers stale CMap stream Length when malformed DecodeParms is aligned to a null filter slot' => static function (TestRunner $t) use ($parserMalformedCMapNullFilterLengthBoundaryCurrentBasePdf): void {
        [$pdf, $fakeTerminatorOffset, $compressedLength, $mappedText, $fakeObjectText] = $parserMalformedCMapNullFilterLengthBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperands = $entry['filter_operands'] ?? [];
        $decodeParmsOperands = $entry['decodeparms_operands'] ?? [];

        $t->same([$mappedText], $extractor->extractTextLines($pdf));
        $t->same([$mappedText], $extractor->extractTextRuns($pdf));
        $t->same($mappedText, $plainText);
        $t->same($mappedText . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, $fakeObjectText));
        $t->true(!str_contains($plainText, '90 0 obj'));
        $t->true(!str_contains($plainText, 'endstream'));
        $t->true(!str_contains($plainText, '99 0 R'));
        $t->true(!str_contains($plainText, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(1, $review['decoded_cmap_count']);
        $t->same(0, $review['unresolved_operand_count']);
        $t->same(0, $review['invalid_filter_operand_count']);
        $t->same(0, $review['dictionary_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(0, $review['unsupported_filter_count']);
        $t->same(0, $review['invalid_decodeparms_operand_count']);
        $t->same(0, $review['malformed_decodeparms_operand_count']);
        $t->same(0, $review['invalid_decodeparms_parameter_count']);

        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same('NullFilterLengthBoundary-H', $entry['cmap_name'] ?? null);
        $t->same($fakeTerminatorOffset, $entry['declared_length'] ?? null);
        $t->true($compressedLength > $fakeTerminatorOffset);
        $t->same([null, 'FlateDecode'], $entry['filters'] ?? null);
        $t->same(false, $entry['filter_resolution_failed'] ?? null);
        $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
        $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
        $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
        $t->same(true, $entry['decoded_with_current_operands'] ?? null);
        $t->true(($entry['decoded_cmap_length'] ?? 0) > 0);
        $t->true(is_string($entry['decoded_cmap_sha256'] ?? null));
        $t->same(true, $entry['post_endcmap_bytes_excluded'] ?? null);
        $t->same(true, $entry['parser_bounded_cmap_bytes_excluded'] ?? null);
        $t->true(($entry['post_endcmap_byte_count'] ?? 0) > 0);
        $t->true(($entry['parser_excluded_cmap_byte_count'] ?? 0) > 0);
        $t->same('direct_operands', $entry['owner_policy'] ?? null);

        $t->same('direct', $filterOperands[0]['kind'] ?? null);
        $t->same('null', $filterOperands[0]['token_type'] ?? null);
        $t->true(array_key_exists('value', $filterOperands[0] ?? []));
        $t->same(null, $filterOperands[0]['value']);
        $t->same(true, $filterOperands[0]['valid_filter_operand'] ?? null);
        $t->same('direct', $filterOperands[1]['kind'] ?? null);
        $t->same('name', $filterOperands[1]['token_type'] ?? null);
        $t->same('FlateDecode', $filterOperands[1]['value'] ?? null);
        $t->same(true, $filterOperands[1]['valid_filter_operand'] ?? null);

        $t->same('indirect', $decodeParmsOperands[0]['kind'] ?? null);
        $t->same(99, $decodeParmsOperands[0]['object_number'] ?? null);
        $t->same(false, $decodeParmsOperands[0]['resolved'] ?? null);
        $t->same(false, $decodeParmsOperands[0]['xref_selected'] ?? null);
        $t->same('missing_object', $decodeParmsOperands[0]['owner_policy'] ?? null);
        $t->same('direct', $decodeParmsOperands[1]['kind'] ?? null);
        $t->same('dictionary', $decodeParmsOperands[1]['token_type'] ?? null);
        $t->same('<< /Predictor 1 >>', $decodeParmsOperands[1]['value'] ?? null);
        $t->same(true, $decodeParmsOperands[1]['valid_decodeparms_operand'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
