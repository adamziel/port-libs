<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapNamedUseCMapFilterBoundaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapNamedUseCMapFilterBoundaryPdf = static function () use (
    $parserMalformedCMapNamedUseCMapFilterBoundaryUtf16beHex
): string {
    $safeText = 'Named UseCMap Safe Import';
    $leakingText = 'Named UseCMap Filter Leak';
    $derivedCMapName = 'NamedUseCMapDerived-H';
    $baseCMapName = 'NamedMalformedBase-H';
    $derivedCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$derivedCMapName} def\n"
        . "/{$baseCMapName} usecmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $parserMalformedCMapNamedUseCMapFilterBoundaryUtf16beHex($safeText) . ">\n"
        . "endbfchar\n"
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
        . "<0002> <" . $parserMalformedCMapNamedUseCMapFilterBoundaryUtf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedBaseCMap = gzcompress($baseCMap, 0);
    if (!is_string($compressedBaseCMap)) {
        throw new RuntimeException('Unable to compress malformed named UseCMap base fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <0001> Tj T* <0002> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NamedUseCMapFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$derivedCMapName} /Length " . strlen($derivedCMap) . " >>\nstream\n{$derivedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /CMap /CMapName /{$baseCMapName} /Filter [ << /Owner (named UseCMap base dictionary is not a decoder) >> /FlateDecode ] /Length " . strlen($compressedBaseCMap) . " >>\nstream\n{$compressedBaseCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'records named usecmap inheritance to malformed filtered base streams before current-base text extraction' => static function (TestRunner $t) use ($parserMalformedCMapNamedUseCMapFilterBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapNamedUseCMapFilterBoundaryPdf();
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
        $baseFilterOperands = $baseEntry['filter_operands'] ?? [];

        $t->same(['Named UseCMap Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Named UseCMap Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Named UseCMap Safe Import', $plainText);
        $t->same("Named UseCMap Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, 'Named UseCMap Filter Leak'));
        $t->true(!str_contains($plainText, 'named UseCMap base dictionary is not a decoder'));
        $t->true(!str_contains($plainText, 'NamedMalformedBase-H'));
        $t->true(!str_contains($plainText, 'beginbfchar'));
        $t->true(!str_contains($plainText, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(2, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(1, $review['use_cmap_stream_count']);
        $t->same(1, $review['decoded_cmap_count']);
        $t->same(1, $review['invalid_filter_operand_count']);
        $t->same(1, $review['dictionary_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(0, $review['unsupported_filter_count']);
        $t->same(0, $review['filter_end_marker_problem_count']);
        $t->same(0, $review['filter_decode_error_count']);

        $t->true(is_array($derivedEntry));
        $t->true(is_array($baseEntry));
        $t->same(6, $derivedEntry['object_number'] ?? null);
        $t->same('NamedUseCMapDerived-H', $derivedEntry['cmap_name'] ?? null);
        $t->same(['to_unicode'], array_column($derivedEntry['reference_usages'] ?? [], 'usage'));
        $t->same([], $derivedEntry['filters'] ?? null);
        $t->same('filters_resolved', $derivedEntry['filter_operand_policy'] ?? null);
        $t->same('no_filters', $derivedEntry['filter_end_marker_policy'] ?? null);
        $t->same('no_filters', $derivedEntry['filter_decode_policy'] ?? null);
        $t->same(true, $derivedEntry['decoded_with_current_operands'] ?? null);
        $t->true(($derivedEntry['decoded_cmap_length'] ?? 0) > 0);

        $t->same(7, $baseEntry['object_number'] ?? null);
        $t->same('NamedMalformedBase-H', $baseEntry['cmap_name'] ?? null);
        $t->same([], $baseEntry['filters'] ?? null);
        $t->same(true, $baseEntry['filter_resolution_failed'] ?? null);
        $t->same(false, $baseEntry['decodeparms_resolution_failed'] ?? null);
        $t->same(1, $baseEntry['invalid_filter_operand_count'] ?? null);
        $t->same(1, $baseEntry['dictionary_filter_operand_count'] ?? null);
        $t->same(0, $baseEntry['malformed_filter_operand_count'] ?? null);
        $t->same('reject_dictionary_filter_operands', $baseEntry['filter_operand_policy'] ?? null);
        $t->same('filter_resolution_failed', $baseEntry['filter_end_marker_policy'] ?? null);
        $t->same('filter_resolution_failed', $baseEntry['filter_decode_policy'] ?? null);
        $t->same(null, $baseEntry['decoded_cmap_length'] ?? null);
        $t->same(null, $baseEntry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $baseEntry['decoded_with_current_operands'] ?? null);
        $t->same('use_cmap', $baseUsage['usage'] ?? null);
        $t->same(6, $baseUsage['source_object'] ?? null);
        $t->same('NamedMalformedBase-H', $baseUsage['name'] ?? null);
        $t->same('NamedMalformedBase-H', $baseUsage['reference'] ?? null);
        $t->same('named_usecmap', $baseUsage['reference_kind'] ?? null);
        $t->same('direct', $baseFilterOperands[0]['kind'] ?? null);
        $t->same('dictionary', $baseFilterOperands[0]['token_type'] ?? null);
        $t->same(true, $baseFilterOperands[0]['dictionary_filter_operand'] ?? null);
        $t->same(false, $baseFilterOperands[0]['valid_filter_operand'] ?? null);
        $t->same('name', $baseFilterOperands[1]['token_type'] ?? null);
        $t->same('FlateDecode', $baseFilterOperands[1]['value'] ?? null);
        $t->same(true, $baseFilterOperands[1]['valid_filter_operand'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
