<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapFallbackStreamBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /FallbackStreamBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0001> <0001>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $utf16beHex('Fallback Visible Import') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n"
        . "BT /Fcid 12 Tf 72 650 Td (CMap Payload Leak) Tj ET\n";
    $content = "BT /Fcid 12 Tf 72 720 Td <0001> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /FallbackStreamBoundary /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "3 0 obj\n<< /Type /CMap /CMapName /FallbackStreamBoundary-H /Filter [ null ] /DecodeParms 99 0 R /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'excludes all-null filtered CMap streams from stream-only fallback before WordPress text extraction on current base' => static function (TestRunner $t) use ($parserMalformedCMapFallbackStreamBoundaryCurrentBasePdf): void {
        $pdf = $parserMalformedCMapFallbackStreamBoundaryCurrentBasePdf();
        $extractor = new PdfTextExtractor();

        $text = $extractor->extractPlainText($pdf);

        $t->same(['Fallback Visible Import'], $extractor->extractTextLines($pdf));
        $t->same(['Fallback Visible Import'], $extractor->extractTextRuns($pdf));
        $t->same('Fallback Visible Import', $text);
        $t->same("Fallback Visible Import\n", $extractor->naiveGetText($pdf));
        $t->true(str_contains($text, 'Fallback Visible Import'));
        $t->true(!str_contains($text, 'CMap Payload Leak'));
        $t->true(!str_contains($text, 'FallbackStreamBoundary-H'));
        $t->true(!str_contains($text, 'beginbfchar'));
        $t->true(!str_contains($text, 'DecodeParms'));

        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['decoded_cmap_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(0, $review['dictionary_filter_operand_count']);
        $t->same(0, $review['unsupported_filter_count']);
        $t->same(0, $review['filter_decode_error_count']);
        $t->same(0, $review['invalid_decodeparms_operand_count']);

        $entry = $review['entries'][0] ?? [];
        $t->same(3, $entry['object_number'] ?? null);
        $t->same('FallbackStreamBoundary-H', $entry['cmap_name'] ?? null);
        $t->same([], $entry['filters'] ?? null);
        $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
        $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
        $t->same(true, $entry['decoded_with_current_operands'] ?? null);
        $t->same(true, $entry['post_endcmap_bytes_excluded'] ?? null);
        $t->true(($entry['post_endcmap_byte_count'] ?? 0) > 0);
        $t->true(($entry['parser_excluded_cmap_byte_count'] ?? 0) >= ($entry['post_endcmap_byte_count'] ?? 0));
        $t->same(true, $entry['review_only'] ?? null);
        $t->same(1, count($entry['reference_usages'] ?? []));
        $t->same('to_unicode', $entry['reference_usages'][0]['usage'] ?? null);
    },
];
