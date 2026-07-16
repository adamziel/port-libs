<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapFreeFilterOwnerUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapFreeFilterOwnerPdf = static function () use ($parserMalformedCMapFreeFilterOwnerUtf16beHex): array {
    $safeText = 'Free Helper Safe Import';
    $leakingText = 'Free Helper CMap Leak';
    $safeHex = $parserMalformedCMapFreeFilterOwnerUtf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMapName = 'FreeFilterHelperBoundary-H';
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$sourceCode}> <" . $parserMalformedCMapFreeFilterOwnerUtf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress free-helper CMap filter fixture.');
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
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /FreeFilterHelperBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /{$cMapName} /Filter 7 0 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(7, 0, '/FlateDecode');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 8\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 7; $objectNumber++) {
        $pdf .= $objectNumber === 7
            ? $xrefRow(0, 1, 'f')
            : $xrefRow($offsets[$objectNumber . ':0']);
    }
    $pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $safeText, $leakingText, $cMapName];
};

return [
    'does not expose stale CMap Filter helper bodies when the current xref row is free' => static function (TestRunner $t) use (
        $parserMalformedCMapFreeFilterOwnerPdf
    ): void {
        [$pdf, $safeText, $leakingText, $cMapName] = $parserMalformedCMapFreeFilterOwnerPdf();
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
        $t->true(!str_contains($plainText, 'FlateDecode'));
        $t->true(!str_contains($plainText, 'beginbfchar'));
        $t->true(!str_contains($plainText, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(1, $review['indirect_filter_count']);
        $t->same(0, $review['xref_selected_operand_count']);
        $t->same(1, $review['unresolved_operand_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(1, $review['invalid_filter_operand_count']);
        $t->same(0, $review['dictionary_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(0, $review['unsupported_filter_count']);
        $t->same(0, $review['filter_decode_error_count']);

        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same($cMapName, $entry['cmap_name'] ?? null);
        $t->same([], $entry['filters'] ?? null);
        $t->same(true, $entry['filter_resolution_failed'] ?? null);
        $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
        $t->same(1, $entry['indirect_filter_count'] ?? null);
        $t->same(0, $entry['xref_selected_operand_count'] ?? null);
        $t->same(1, $entry['unresolved_operand_count'] ?? null);
        $t->same(1, $entry['invalid_filter_operand_count'] ?? null);
        $t->same(0, $entry['dictionary_filter_operand_count'] ?? null);
        $t->same(0, $entry['malformed_filter_operand_count'] ?? null);
        $t->same('reject_unresolved_filter_operands', $entry['filter_operand_policy'] ?? null);
        $t->same('filter_resolution_failed', $entry['filter_end_marker_policy'] ?? null);
        $t->same('filter_resolution_failed', $entry['filter_decode_policy'] ?? null);
        $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(null, $entry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('unresolved_or_unselected_indirect_operands', $entry['owner_policy'] ?? null);
        $t->same('to_unicode', $entry['reference_usages'][0]['usage'] ?? null);

        $t->same('indirect', $filterOperand['kind'] ?? null);
        $t->same(7, $filterOperand['object_number'] ?? null);
        $t->same(0, $filterOperand['generation'] ?? null);
        $t->same(false, $filterOperand['resolved'] ?? null);
        $t->same(false, $filterOperand['xref_selected'] ?? null);
        $t->same(0, $filterOperand['xref_entry_type'] ?? null);
        $t->same(0, $filterOperand['xref_offset'] ?? null);
        $t->same(null, $filterOperand['selected_generation'] ?? null);
        $t->same(null, $filterOperand['selected_offset'] ?? null);
        $t->same('free_xref_entry', $filterOperand['owner_policy'] ?? null);
        $t->same(null, $filterOperand['value_preview'] ?? null);
        $t->same(false, array_key_exists('token_type', $filterOperand));
        $t->same(false, array_key_exists('valid_filter_operand', $filterOperand));
        $t->same(false, array_key_exists('dictionary_filter_operand', $filterOperand));
        $t->same(false, array_key_exists('extra_filter_operand', $filterOperand));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
