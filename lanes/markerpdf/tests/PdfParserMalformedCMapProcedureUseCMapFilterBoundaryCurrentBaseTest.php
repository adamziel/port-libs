<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapProcedureUseCMapFilterBoundaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapProcedureUseCMapFilterBoundaryPdf = static function () use (
    $parserMalformedCMapProcedureUseCMapFilterBoundaryUtf16beHex
): string {
    $safeText = 'Procedure UseCMap Safe Import';
    $leakingText = 'Procedure UseCMap Leak';
    $safeHex = $parserMalformedCMapProcedureUseCMapFilterBoundaryUtf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $baseCMapName = 'ProcedureUseCMapDecoy-H';

    $derivedCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /ProcedureUseCMapDerived-H def\n"
        . "{ /{$baseCMapName} usecmap } bind def\n"
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
        . "<{$sourceCode}> <" . $parserMalformedCMapProcedureUseCMapFilterBoundaryUtf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $compressedDerivedCMap = gzcompress($derivedCMap, 0);
    $compressedBaseCMap = gzcompress($baseCMap, 0);
    if (!is_string($compressedDerivedCMap) || !is_string($compressedBaseCMap)) {
        throw new RuntimeException('Unable to compress focused procedure-usecmap CMap fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ProcedureUseCMapBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /ProcedureUseCMapDerived-H /Filter /FlateDecode /Length " . strlen($compressedDerivedCMap) . " >>\nstream\n{$compressedDerivedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /CMap /CMapName /{$baseCMapName} /Filter /FlateDecode /Length " . strlen($compressedBaseCMap) . " >>\nstream\n{$compressedBaseCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'ignores usecmap tokens inside filtered CMap procedure bodies before current-base text extraction' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapProcedureUseCMapFilterBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapProcedureUseCMapFilterBoundaryPdf();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entries = $review['entries'] ?? [];
        $derivedEntry = $entries[0] ?? [];
        $baseEntry = $entries[1] ?? [];

        $t->same(['Procedure UseCMap Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Procedure UseCMap Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Procedure UseCMap Safe Import', $plainText);
        $t->same("Procedure UseCMap Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($plainText, 'Procedure UseCMap Leak'));
        $t->true(!str_contains($plainText, 'ProcedureUseCMapDecoy-H'));
        $t->true(!str_contains($plainText, 'usecmap'));
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
        $t->same(0, $review['filter_decode_error_count']);

        $t->same(6, $derivedEntry['object_number'] ?? null);
        $t->same('ProcedureUseCMapDerived-H', $derivedEntry['cmap_name'] ?? null);
        $t->same(['FlateDecode'], $derivedEntry['filters'] ?? null);
        $t->same('filters_resolved', $derivedEntry['filter_operand_policy'] ?? null);
        $t->same('filter_decoders_resolved', $derivedEntry['filter_decode_policy'] ?? null);
        $t->same('filter_end_markers_resolved', $derivedEntry['filter_end_marker_policy'] ?? null);
        $t->same('decodeparms_resolved', $derivedEntry['decodeparms_operand_policy'] ?? null);
        $t->same(true, $derivedEntry['decoded_with_current_operands'] ?? null);
        $t->same('direct_operands', $derivedEntry['owner_policy'] ?? null);
        $t->same('to_unicode', $derivedEntry['reference_usages'][0]['usage'] ?? null);
        $t->same('ProcedureUseCMapDecoy-H', $baseEntry['cmap_name'] ?? null);
        $t->same([], $baseEntry['reference_usages'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
