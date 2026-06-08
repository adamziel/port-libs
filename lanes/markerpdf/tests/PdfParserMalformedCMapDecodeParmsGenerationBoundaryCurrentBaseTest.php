<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapDecodeParmsGenerationBoundaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapDecodeParmsGenerationBoundaryPdf = static function () use (
    $parserMalformedCMapDecodeParmsGenerationBoundaryUtf16beHex
): array {
    $safeText = 'DecodeParms Gen Safe Import';
    $leakingText = 'DecodeParms Gen CMap Leak';
    $safeHex = $parserMalformedCMapDecodeParmsGenerationBoundaryUtf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMapName = 'DecodeParmsGenerationBoundary-H';
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$sourceCode}> <" . $parserMalformedCMapDecodeParmsGenerationBoundaryUtf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress DecodeParms generation-boundary CMap fixture.');
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
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /DecodeParmsGenerationBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /DecodeParms 8 0 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(8, 0, '<< /Predictor 1 >>');
    $addObject(8, 1, '<< /Predictor /Twelve /Columns 1 >>');

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

    return [$pdf, $safeText, $leakingText, $cMapName, strlen($compressedCMap)];
};

return [
    'rejects stale-generation CMap DecodeParms helpers before ToUnicode decoding' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapDecodeParmsGenerationBoundaryPdf): void {
        [$pdf, $safeText, $leakingText, $cMapName, $declaredLength] = $parserMalformedCMapDecodeParmsGenerationBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperand = $entry['filter_operands'][0] ?? [];
        $decodeParmsOperand = $entry['decodeparms_operands'][0] ?? [];

        $t->same([$safeText], $extractor->extractTextLines($pdf));
        $t->same([$safeText], $extractor->extractTextRuns($pdf));
        $t->same($safeText, $plainText);
        $t->same($safeText . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, $leakingText));
        $t->true(!str_contains($plainText, $cMapName));
        $t->true(!str_contains($plainText, 'Predictor'));
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
        $t->same(0, $review['xref_selected_operand_count']);
        $t->same(1, $review['unresolved_operand_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(0, $review['invalid_filter_operand_count']);
        $t->same(1, $review['invalid_decodeparms_operand_count']);
        $t->same(0, $review['malformed_decodeparms_operand_count']);
        $t->same(0, $review['invalid_decodeparms_parameter_count']);
        $t->same(0, $review['filter_decode_error_count']);

        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same($cMapName, $entry['cmap_name'] ?? null);
        $t->same($declaredLength, $entry['declared_length'] ?? null);
        $t->same(['FlateDecode'], $entry['filters'] ?? null);
        $t->same(false, $entry['filter_resolution_failed'] ?? null);
        $t->same(true, $entry['decodeparms_resolution_failed'] ?? null);
        $t->same(1, $entry['unresolved_operand_count'] ?? null);
        $t->same(1, $entry['invalid_decodeparms_operand_count'] ?? null);
        $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
        $t->same('decodeparms_resolution_failed', $entry['filter_end_marker_policy'] ?? null);
        $t->same('decodeparms_resolution_failed', $entry['filter_decode_policy'] ?? null);
        $t->same('reject_unresolved_decodeparms_operands', $entry['decodeparms_operand_policy'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(null, $entry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('unresolved_or_unselected_indirect_operands', $entry['owner_policy'] ?? null);
        $t->same('to_unicode', $entry['reference_usages'][0]['usage'] ?? null);

        $t->same('direct', $filterOperand['kind'] ?? null);
        $t->same('FlateDecode', $filterOperand['value'] ?? null);
        $t->same(true, $filterOperand['valid_filter_operand'] ?? null);

        $t->same('indirect', $decodeParmsOperand['kind'] ?? null);
        $t->same(8, $decodeParmsOperand['object_number'] ?? null);
        $t->same(0, $decodeParmsOperand['generation'] ?? null);
        $t->same(true, $decodeParmsOperand['resolved'] ?? null);
        $t->same(false, $decodeParmsOperand['xref_selected'] ?? null);
        $t->same(1, $decodeParmsOperand['xref_entry_type'] ?? null);
        $t->same(1, $decodeParmsOperand['selected_generation'] ?? null);
        $t->same('xref_entry_points_elsewhere', $decodeParmsOperand['owner_policy'] ?? null);
        $t->same('<< /Predictor /Twelve /Columns 1 >>', $decodeParmsOperand['value_preview'] ?? null);
        $t->same('dictionary', $decodeParmsOperand['token_type'] ?? null);
        $t->same(true, $decodeParmsOperand['valid_decodeparms_operand'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
