<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserCMapIndirectUseCMapNameFilterBoundaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserCMapIndirectUseCMapNameFilterBoundaryPdf = static function () use (
    $parserCMapIndirectUseCMapNameFilterBoundaryUtf16beHex
): array {
    $mappedText = 'Comment Name UseCMap Import';
    $baseCMapName = 'CommentSplitUseCMapBase-H';
    $derivedCMapName = 'CommentSplitUseCMapDerived-H';

    $derivedCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$derivedCMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $baseCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$baseCMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0002> <" . $parserCMapIndirectUseCMapNameFilterBoundaryUtf16beHex($mappedText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedBaseCMap = gzcompress($baseCMap, 0);
    if (!is_string($compressedBaseCMap)) {
        throw new RuntimeException('Unable to compress focused indirect UseCMap name fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0002> Tj ET';

    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CommentSplitUseCMap /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$derivedCMapName} /UseCMap 8 % comment splits the indirect name reference\n 0 R /Length " . strlen($derivedCMap) . " >>\nstream\n{$derivedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /CMap /CMapName /{$baseCMapName} /Filter /FlateDecode /Length " . strlen($compressedBaseCMap) . " >>\nstream\n{$compressedBaseCMap}\nendstream\nendobj\n"
        . "8 0 obj\n/{$baseCMapName}\nendobj\n"
        . "%%EOF";

    return [$pdf, $mappedText, $baseCMapName, $derivedCMapName];
};

return [
    'resolves comment-split indirect UseCMap name objects before filtered base CMap inheritance' => static function (
        TestRunner $t
    ) use ($parserCMapIndirectUseCMapNameFilterBoundaryPdf): void {
        [$pdf, $mappedText, $baseCMapName, $derivedCMapName] = $parserCMapIndirectUseCMapNameFilterBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $derivedEntry = null;
        $baseEntry = null;
        foreach ($review['entries'] as $entry) {
            if (($entry['object_number'] ?? null) === 6) {
                $derivedEntry = $entry;
            } elseif (($entry['object_number'] ?? null) === 7) {
                $baseEntry = $entry;
            }
        }
        $baseUsage = $baseEntry['reference_usages'][0] ?? [];

        $t->same([$mappedText], $extractor->extractTextLines($pdf));
        $t->same([$mappedText], $extractor->extractTextRuns($pdf));
        $t->same($mappedText, $plainText);
        $t->same($mappedText . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, $baseCMapName));
        $t->true(!str_contains($plainText, $derivedCMapName));
        $t->true(!str_contains($plainText, 'FlateDecode'));
        $t->true(!str_contains($plainText, 'usecmap'));
        $t->true(!str_contains($plainText, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(2, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(1, $review['use_cmap_stream_count']);
        $t->same(2, $review['decoded_cmap_count']);
        $t->same(0, $review['invalid_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(0, $review['unsupported_filter_count']);
        $t->same(0, $review['filter_decode_error_count']);
        $t->same(0, $review['filter_end_marker_problem_count']);

        $t->true(is_array($derivedEntry));
        $t->same(6, $derivedEntry['object_number'] ?? null);
        $t->same($derivedCMapName, $derivedEntry['cmap_name'] ?? null);
        $t->same([], $derivedEntry['filters'] ?? null);
        $t->same('filters_resolved', $derivedEntry['filter_operand_policy'] ?? null);
        $t->same('no_filters', $derivedEntry['filter_end_marker_policy'] ?? null);
        $t->same('no_filters', $derivedEntry['filter_decode_policy'] ?? null);
        $t->same('decodeparms_resolved', $derivedEntry['decodeparms_operand_policy'] ?? null);
        $t->same(true, $derivedEntry['decoded_with_current_operands'] ?? null);
        $t->same(['to_unicode'], array_column($derivedEntry['reference_usages'] ?? [], 'usage'));

        $t->true(is_array($baseEntry));
        $t->same(7, $baseEntry['object_number'] ?? null);
        $t->same($baseCMapName, $baseEntry['cmap_name'] ?? null);
        $t->same(['FlateDecode'], $baseEntry['filters'] ?? null);
        $t->same('filters_resolved', $baseEntry['filter_operand_policy'] ?? null);
        $t->same('filter_end_markers_resolved', $baseEntry['filter_end_marker_policy'] ?? null);
        $t->same('filter_decoders_resolved', $baseEntry['filter_decode_policy'] ?? null);
        $t->same('decodeparms_resolved', $baseEntry['decodeparms_operand_policy'] ?? null);
        $t->same(true, $baseEntry['decoded_with_current_operands'] ?? null);
        $t->true(($baseEntry['decoded_cmap_length'] ?? 0) > 0);
        $t->same('use_cmap', $baseUsage['usage'] ?? null);
        $t->same(6, $baseUsage['source_object'] ?? null);
        $t->same($baseCMapName, $baseUsage['name'] ?? null);
        $t->same($baseCMapName, $baseUsage['reference'] ?? null);
        $t->same('named_usecmap', $baseUsage['reference_kind'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
