<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapUseCMapPostNameBoundaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapUseCMapPostNameBoundaryPdf = static function () use ($parserMalformedCMapUseCMapPostNameBoundaryUtf16beHex): string {
    $safeText = 'Safe Import';
    $sourceHex = strtoupper(bin2hex($safeText));
    $derivedCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /DerivedPostEndNameBoundary-H def\n"
        . "/PostEndNamedBase-H usecmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $baseCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<53> <" . $parserMalformedCMapUseCMapPostNameBoundaryUtf16beHex('PostEnd Named Base Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n"
        . "/CMapName /PostEndNamedBase-H def\n"
        . "1 beginbfchar\n"
        . "<61> <" . $parserMalformedCMapUseCMapPostNameBoundaryUtf16beHex('PostEnd Trailing Mapping Leak') . ">\n"
        . "endbfchar\n";
    $compressedBaseCMap = gzcompress($baseCMap, 0);
    if (!is_string($compressedBaseCMap)) {
        throw new RuntimeException('Unable to compress focused post-end CMap-name fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$sourceHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PostEndNameBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /DerivedPostEndNameBoundary-H /Length " . strlen($derivedCMap) . " >>\nstream\n{$derivedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /CMap /Filter /FlateDecode /Length " . strlen($compressedBaseCMap) . " >>\nstream\n{$compressedBaseCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'does not register a filtered base CMap by a post-endcmap CMapName for current-base usecmap imports' => static function (TestRunner $t) use ($parserMalformedCMapUseCMapPostNameBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapUseCMapPostNameBoundaryPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entries = [];
        foreach ($review['entries'] as $entry) {
            $entries[$entry['object_number']] = $entry;
        }
        $derivedEntry = $entries[6] ?? [];
        $baseEntry = $entries[7] ?? [];

        $t->same(['Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Safe Import', $plainText);
        $t->same("Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, 'PostEnd Named Base Leak'));
        $t->true(!str_contains($plainText, 'PostEnd Trailing Mapping Leak'));
        $t->true(!str_contains($plainText, 'PostEndNamedBase-H'));
        $t->true(!str_contains($plainText, 'DerivedPostEndNameBoundary-H'));
        $t->true(!str_contains($plainText, 'beginbfchar'));
        $t->true(!str_contains($plainText, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(2, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(0, $review['use_cmap_stream_count']);
        $t->same(2, $review['decoded_cmap_count']);
        $t->same(0, $review['invalid_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(0, $review['unsupported_filter_count']);
        $t->same(0, $review['filter_end_marker_problem_count']);

        $t->same(6, $derivedEntry['object_number'] ?? null);
        $t->same(0, $derivedEntry['generation'] ?? null);
        $t->same('DerivedPostEndNameBoundary-H', $derivedEntry['cmap_name'] ?? null);
        $t->same([], $derivedEntry['filters'] ?? null);
        $t->same(false, $derivedEntry['filter_resolution_failed'] ?? null);
        $t->same(false, $derivedEntry['decodeparms_resolution_failed'] ?? null);
        $t->same('filters_resolved', $derivedEntry['filter_operand_policy'] ?? null);
        $t->same('no_filters', $derivedEntry['filter_end_marker_policy'] ?? null);
        $t->same('decodeparms_resolved', $derivedEntry['decodeparms_operand_policy'] ?? null);
        $t->same(true, $derivedEntry['decoded_with_current_operands'] ?? null);
        $t->true(($derivedEntry['post_endcmap_byte_count'] ?? 0) > 0);
        $t->same(true, $derivedEntry['post_endcmap_bytes_excluded'] ?? null);
        $t->true(($derivedEntry['parser_excluded_cmap_byte_count'] ?? 0) > 0);
        $t->same(true, $derivedEntry['parser_bounded_cmap_bytes_excluded'] ?? null);
        $t->same('direct_operands', $derivedEntry['owner_policy'] ?? null);

        $t->same(7, $baseEntry['object_number'] ?? null);
        $t->same(0, $baseEntry['generation'] ?? null);
        $t->same(null, $baseEntry['cmap_name'] ?? null);
        $t->same(['FlateDecode'], $baseEntry['filters'] ?? null);
        $t->same(false, $baseEntry['filter_resolution_failed'] ?? null);
        $t->same(false, $baseEntry['decodeparms_resolution_failed'] ?? null);
        $t->same('filters_resolved', $baseEntry['filter_operand_policy'] ?? null);
        $t->same('filter_end_markers_resolved', $baseEntry['filter_end_marker_policy'] ?? null);
        $t->same('decodeparms_resolved', $baseEntry['decodeparms_operand_policy'] ?? null);
        $t->same(true, $baseEntry['decoded_with_current_operands'] ?? null);
        $t->same([], $baseEntry['reference_usages'] ?? null);
        $t->true(($baseEntry['decoded_cmap_length'] ?? 0) > ($baseEntry['parser_bounded_cmap_length'] ?? 0));
        $t->true(($baseEntry['post_endcmap_byte_count'] ?? 0) > 0);
        $t->same(true, $baseEntry['post_endcmap_bytes_excluded'] ?? null);
        $t->true(($baseEntry['parser_excluded_cmap_byte_count'] ?? 0) > 0);
        $t->same(true, $baseEntry['parser_bounded_cmap_bytes_excluded'] ?? null);
        $t->same('direct_operands', $baseEntry['owner_policy'] ?? null);

        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
