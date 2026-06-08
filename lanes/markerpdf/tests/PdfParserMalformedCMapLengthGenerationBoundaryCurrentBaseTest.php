<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapLengthGenerationBoundaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapLengthGenerationBoundaryPdf = static function () use (
    $parserMalformedCMapLengthGenerationBoundaryUtf16beHex
): array {
    $safeText = 'Length Gen Safe Import';
    $leakingText = 'Length Gen CMap Leak';
    $safeHex = $parserMalformedCMapLengthGenerationBoundaryUtf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMapName = 'LengthGenerationBoundary-H';
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$sourceCode}> <" . $parserMalformedCMapLengthGenerationBoundaryUtf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress Length generation-boundary CMap fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset ?? 0,
        $generation,
        $state
    );

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /LengthGenerationBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length 8 0 R >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(8, 0, (string) strlen($compressedCMap));
    $addObject(8, 1, '/UnselectedLengthHelper');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 9\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 8; $objectNumber++) {
        if ($objectNumber === 7) {
            $pdf .= $xrefRow(0, 65535, 'f');
            continue;
        }

        $pdf .= $objectNumber === 8
            ? $xrefRow($offsets['8:1'], 1)
            : $xrefRow($offsets[$objectNumber . ':0']);
    }
    $pdf .= "trailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $safeText, $leakingText, $cMapName];
};

return [
    'rejects stale-generation CMap Length helpers before ToUnicode decoding' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapLengthGenerationBoundaryPdf): void {
        [$pdf, $safeText, $leakingText, $cMapName] = $parserMalformedCMapLengthGenerationBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperand = $entry['filter_operands'][0] ?? [];
        $lengthOperand = $entry['length_operand'] ?? [];

        $t->same([$safeText], $extractor->extractTextLines($pdf));
        $t->same([$safeText], $extractor->extractTextRuns($pdf));
        $t->same($safeText, $plainText);
        $t->same($safeText . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, $leakingText));
        $t->true(!str_contains($plainText, $cMapName));
        $t->true(!str_contains($plainText, 'UnselectedLengthHelper'));
        $t->true(!str_contains($plainText, 'beginbfchar'));
        $t->true(!str_contains($plainText, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(0, $review['use_cmap_stream_count']);
        $t->same(0, $review['indirect_filter_count']);
        $t->same(1, $review['indirect_length_count']);
        $t->same(0, $review['xref_selected_operand_count']);
        $t->same(1, $review['unresolved_operand_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(0, $review['invalid_filter_operand_count']);
        $t->same(0, $review['invalid_decodeparms_operand_count']);
        $t->same(0, $review['malformed_decodeparms_operand_count']);
        $t->same(0, $review['invalid_decodeparms_parameter_count']);
        $t->same(0, $review['filter_decode_error_count']);

        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same('LengthGenerationBoundary-H', $entry['cmap_name'] ?? null);
        $t->same(null, $entry['declared_length'] ?? null);
        $t->same(['FlateDecode'], $entry['filters'] ?? null);
        $t->same(false, $entry['filter_resolution_failed'] ?? null);
        $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
        $t->same(1, $entry['unresolved_operand_count'] ?? null);
        $t->same(0, $entry['invalid_filter_operand_count'] ?? null);
        $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
        $t->same('filter_end_markers_resolved', $entry['filter_end_marker_policy'] ?? null);
        $t->same('filter_decoders_resolved', $entry['filter_decode_policy'] ?? null);
        $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(null, $entry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('unresolved_or_unselected_indirect_operands', $entry['owner_policy'] ?? null);
        $t->same('to_unicode', $entry['reference_usages'][0]['usage'] ?? null);

        $t->same('direct', $filterOperand['kind'] ?? null);
        $t->same('FlateDecode', $filterOperand['value'] ?? null);
        $t->same(true, $filterOperand['valid_filter_operand'] ?? null);

        $t->same('indirect', $lengthOperand['kind'] ?? null);
        $t->same(8, $lengthOperand['object_number'] ?? null);
        $t->same(0, $lengthOperand['generation'] ?? null);
        $t->same(true, $lengthOperand['resolved'] ?? null);
        $t->same(false, $lengthOperand['xref_selected'] ?? null);
        $t->same(1, $lengthOperand['xref_entry_type'] ?? null);
        $t->same(1, $lengthOperand['selected_generation'] ?? null);
        $t->same('xref_entry_points_elsewhere', $lengthOperand['owner_policy'] ?? null);
        $t->same('/UnselectedLengthHelper', $lengthOperand['value_preview'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
