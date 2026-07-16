<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapUnknownFilterNameBoundaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapUnknownFilterNameBoundaryPdf = static function () use ($parserMalformedCMapUnknownFilterNameBoundaryUtf16beHex): array {
    $safeText = 'Unknown Filter Name Safe Import';
    $leakingText = 'Unknown Filter Name CMap Leak';
    $safeHex = $parserMalformedCMapUnknownFilterNameBoundaryUtf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMapName = 'UnknownNameFilterBoundary-H';
    $extraName = 'UnknownFilterName';
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$sourceCode}> <" . $parserMalformedCMapUnknownFilterNameBoundaryUtf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress unknown CMap filter-name fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UnknownNameFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /{$extraName} /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $leakingText, $cMapName, $extraName];
};

return [
    'fails closed when a scalar CMap Filter is followed by an unknown unkeyed filter-name before Length' => static function (TestRunner $t) use ($parserMalformedCMapUnknownFilterNameBoundaryPdf): void {
        [$pdf, $safeText, $leakingText, $cMapName, $extraName] = $parserMalformedCMapUnknownFilterNameBoundaryPdf();
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
        $t->true(!str_contains($plainText, $extraName));
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
        $t->same(0, $review['unsupported_filter_count']);

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

        $t->same('direct', $filterOperands[0]['kind'] ?? null);
        $t->same('name', $filterOperands[0]['token_type'] ?? null);
        $t->same('FlateDecode', $filterOperands[0]['value'] ?? null);
        $t->same(false, $filterOperands[0]['valid_filter_operand'] ?? null);
        $t->same(false, $filterOperands[0]['dictionary_filter_operand'] ?? null);
        $t->same(true, $filterOperands[0]['extra_filter_operand'] ?? null);
        $t->same('name', $filterOperands[0]['extra_filter_operand_type'] ?? null);
        $t->same('/' . $extraName, $filterOperands[0]['extra_filter_operand_preview'] ?? null);
        $t->same(true, $filterOperands[0]['extra_filter_name_operand'] ?? null);
        $t->same($extraName, $filterOperands[0]['extra_filter_name'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
