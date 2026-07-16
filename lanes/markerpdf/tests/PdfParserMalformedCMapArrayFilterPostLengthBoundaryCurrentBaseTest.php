<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapArrayFilterPostLengthUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapArrayFilterPostLengthPdf = static function (
    string $extraOperand,
    string $safeText,
    string $leakingText,
    string $baseFont,
    string $cMapName
) use ($parserMalformedCMapArrayFilterPostLengthUtf16beHex): array {
    $safeHex = $parserMalformedCMapArrayFilterPostLengthUtf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$sourceCode}> <" . $parserMalformedCMapArrayFilterPostLengthUtf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress array-filter post-Length CMap fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter [ /FlateDecode ] /Length "
        . strlen($compressedCMap) . " {$extraOperand} >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, strlen($compressedCMap)];
};

return [
    'reports malformed post-Length operands after array CMap Filter before current-base text extraction' => static function (TestRunner $t) use (
        $parserMalformedCMapArrayFilterPostLengthPdf
    ): void {
        $extractor = new PdfTextExtractor();
        $cases = [
            [
                '<< /Owner (post Length array filter dictionary is not a decoder) >>',
                'dictionary',
                'Array Post Length Dictionary Safe Import',
                'Array Post Length Dictionary CMap Leak',
                'ArrayPostLengthDictionaryBoundary',
                'ArrayPostLengthDictionaryBoundary-H',
                '<< /Owner (post Length array filter dictionary is not a decoder) >>',
                null,
                0,
                1,
                'reject_malformed_filter_operands',
            ],
        ];

        foreach ($cases as [
            $extraOperand,
            $extraOperandType,
            $safeText,
            $leakingText,
            $baseFont,
            $cMapName,
            $extraPreview,
            $extraName,
            $expectedDictionaryCount,
            $expectedMalformedCount,
            $expectedPolicy,
        ]) {
            [$pdf, $declaredLength] = $parserMalformedCMapArrayFilterPostLengthPdf(
                $extraOperand,
                $safeText,
                $leakingText,
                $baseFont,
                $cMapName
            );
            $text = $extractor->extractPlainText($pdf);
            $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
            $entry = $review['entries'][0] ?? [];
            $filterOperands = $entry['filter_operands'] ?? [];
            $lengthOperand = $entry['length_operand'] ?? [];

            $t->same([$safeText], $extractor->extractTextLines($pdf));
            $t->same([$safeText], $extractor->extractTextRuns($pdf));
            $t->same($safeText, $text);
            $t->same($safeText . "\n", $extractor->naiveGetText($pdf));
            $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
            $t->same(['1'], $extractor->extractPageLabels($pdf));
            $t->true(!str_contains($text, $leakingText));
            $t->true(!str_contains($text, $cMapName));
            $t->true(!str_contains($text, 'beginbfchar'));
            $t->true(!str_contains($text, "\0"));

            $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
            $t->true($review['review_only']);
            $t->same(false, $review['encrypted']);
            $t->same(1, $review['cmap_stream_count']);
            $t->same(1, $review['to_unicode_cmap_stream_count']);
            $t->same(0, $review['encoding_cmap_stream_count']);
            $t->same(0, $review['use_cmap_stream_count']);
            $t->same(0, $review['decoded_cmap_count']);
            $t->same(1, $review['invalid_filter_operand_count']);
            $t->same($expectedDictionaryCount, $review['dictionary_filter_operand_count']);
            $t->same($expectedMalformedCount, $review['malformed_filter_operand_count']);
            $t->same(0, $review['unsupported_filter_count']);
            $t->same(0, $review['filter_end_marker_problem_count']);
            $t->same(0, $review['filter_decode_error_count']);
            $t->same(0, $review['invalid_decodeparms_operand_count']);
            $t->same(0, $review['malformed_decodeparms_operand_count']);
            $t->same(0, $review['invalid_decodeparms_parameter_count']);

            $t->same(6, $entry['object_number'] ?? null);
            $t->same(0, $entry['generation'] ?? null);
            $t->same($cMapName, $entry['cmap_name'] ?? null);
            $t->same($declaredLength, $entry['declared_length'] ?? null);
            $t->same([], $entry['filters'] ?? null);
            $t->same(true, $entry['filter_resolution_failed'] ?? null);
            $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
            $t->same(1, $entry['invalid_filter_operand_count'] ?? null);
            $t->same($expectedDictionaryCount, $entry['dictionary_filter_operand_count'] ?? null);
            $t->same($expectedMalformedCount, $entry['malformed_filter_operand_count'] ?? null);
            $t->same($expectedPolicy, $entry['filter_operand_policy'] ?? null);
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
            $t->same($expectedDictionaryCount === 1, $filterOperands[0]['dictionary_filter_operand'] ?? null);
            $t->same(true, $filterOperands[0]['extra_filter_operand'] ?? null);
            $t->same($extraOperandType, $filterOperands[0]['extra_filter_operand_type'] ?? null);
            $t->same($extraPreview, $filterOperands[0]['extra_filter_operand_preview'] ?? null);
            if ($extraName !== null) {
                $t->same(true, $filterOperands[0]['extra_filter_name_operand'] ?? null);
                $t->same($extraName, $filterOperands[0]['extra_filter_name'] ?? null);
            }

            $t->same('direct', $lengthOperand['kind'] ?? null);
            $t->same('number', $lengthOperand['token_type'] ?? null);
            $t->same($declaredLength, $lengthOperand['value'] ?? null);
            $t->same(false, $review['executes_python_or_models']);
            $t->same(false, $review['executes_external_pdf_tools']);
        }
    },
];
