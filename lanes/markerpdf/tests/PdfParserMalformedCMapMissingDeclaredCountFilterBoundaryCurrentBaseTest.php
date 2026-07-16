<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapMissingDeclaredCountUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapMissingDeclaredCountPdf = static function (
    string $countOperand,
    string $kind,
    string $safeText,
    string $leakingText,
    string $baseFont,
    string $cMapName
) use ($parserMalformedCMapMissingDeclaredCountUtf16beHex): array {
    $safeHex = $parserMalformedCMapMissingDeclaredCountUtf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $targetHex = $parserMalformedCMapMissingDeclaredCountUtf16beHex($leakingText);

    if ($kind === 'char') {
        $beginOperator = 'beginbfchar';
        $endOperator = 'endbfchar';
        $rows = "<{$sourceCode}> <{$targetHex}>\n";
    } else {
        $beginOperator = 'beginbfrange';
        $endOperator = 'endbfrange';
        $rows = "<{$sourceCode}> <{$sourceCode}> <{$targetHex}>\n";
    }

    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "{$countOperand}{$beginOperator}\n"
        . $rows
        . "{$endOperator}\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress missing declared-count CMap fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $leakingText, $cMapName, $kind, $countOperand];
};

$assertMalformedCMapMissingDeclaredCountRejected = static function (
    TestRunner $t,
    array $fixture
): void {
    [$pdf, $safeText, $leakingText, $cMapName, $kind, $countOperand] = $fixture;
    $extractor = new PdfTextExtractor();
    $plainText = $extractor->extractPlainText($pdf);
    $pages = $extractor->extractStyledTextPages($pdf);
    $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
    $spans = $line['spans'] ?? [];
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];

    $t->same([$safeText], $extractor->extractTextLines($pdf));
    $t->same([$safeText], $extractor->extractTextRuns($pdf));
    $t->same($safeText, $plainText);
    $t->same($safeText . "\n", $extractor->naiveGetText($pdf));
    $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    $t->same(['1'], $extractor->extractPageLabels($pdf));
    $t->same([$safeText], array_column($spans, 'text'));
    $t->true(!str_contains($plainText, $leakingText));
    $t->true(!str_contains($plainText, $cMapName));
    $t->true(!str_contains($plainText, 'beginbfchar'));
    $t->true(!str_contains($plainText, 'beginbfrange'));
    $t->true(!str_contains($plainText, "\0"));

    $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
    $t->true($review['review_only']);
    $t->same(false, $review['encrypted']);
    $t->same(1, $review['cmap_stream_count']);
    $t->same(1, $review['to_unicode_cmap_stream_count']);
    $t->same(0, $review['encoding_cmap_stream_count']);
    $t->same(1, $review['decoded_cmap_count']);
    $t->same(0, $review['invalid_filter_operand_count']);
    $t->same(0, $review['malformed_filter_operand_count']);
    $t->same(0, $review['unsupported_filter_count']);
    $t->same(0, $review['filter_end_marker_problem_count']);
    $t->same(0, $review['filter_decode_error_count']);

    $t->same(6, $entry['object_number'] ?? null);
    $t->same(0, $entry['generation'] ?? null);
    $t->same($cMapName, $entry['cmap_name'] ?? null);
    $t->same(['FlateDecode'], $entry['filters'] ?? null);
    $t->same(false, $entry['filter_resolution_failed'] ?? null);
    $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
    $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
    $t->same('filter_end_markers_resolved', $entry['filter_end_marker_policy'] ?? null);
    $t->same('filter_decoders_resolved', $entry['filter_decode_policy'] ?? null);
    $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
    $t->same(true, $entry['decoded_with_current_operands'] ?? null);
    $t->true(($entry['decoded_cmap_length'] ?? 0) > 0);
    $t->true(($entry['parser_bounded_cmap_length'] ?? 0) > 0);
    $t->same('to_unicode', $entry['reference_usages'][0]['usage'] ?? null);
    $t->true(($entry['decoded_cmap_sha256'] ?? '') !== '');
    $t->same(false, $review['executes_python_or_models']);
    $t->same(false, $review['executes_external_pdf_tools']);
    $t->true(in_array($kind, ['char', 'range'], true));
    $t->true($countOperand === '' || trim($countOperand) !== '1');
};

return [
    'rejects filtered ToUnicode bfchar blocks without integer declared counts before current-base text extraction' => static function (
        TestRunner $t
    ) use (
        $parserMalformedCMapMissingDeclaredCountPdf,
        $assertMalformedCMapMissingDeclaredCountRejected
    ): void {
        foreach ([
            ['', 'MissingDeclaredBfcharCountBoundary', 'MissingBfcharCountBoundary-H'],
            ['/Rows ', 'NameDeclaredBfcharCountBoundary', 'NameBfcharCountBoundary-H'],
            ['[] ', 'ArrayDeclaredBfcharCountBoundary', 'ArrayBfcharCountBoundary-H'],
            ['true ', 'BooleanDeclaredBfcharCountBoundary', 'BooleanBfcharCountBoundary-H'],
        ] as [$countOperand, $baseFont, $cMapName]) {
            $assertMalformedCMapMissingDeclaredCountRejected(
                $t,
                $parserMalformedCMapMissingDeclaredCountPdf(
                    $countOperand,
                    'char',
                    'Missing Count Char Safe Import',
                    'Missing Count Char CMap Leak',
                    $baseFont,
                    $cMapName
                )
            );
        }
    },
    'rejects filtered ToUnicode bfrange blocks without integer declared counts before current-base text extraction' => static function (
        TestRunner $t
    ) use (
        $parserMalformedCMapMissingDeclaredCountPdf,
        $assertMalformedCMapMissingDeclaredCountRejected
    ): void {
        foreach ([
            ['', 'MissingDeclaredBfrangeCountBoundary', 'MissingBfrangeCountBoundary-H'],
            ['/Rows ', 'NameDeclaredBfrangeCountBoundary', 'NameBfrangeCountBoundary-H'],
            ['[] ', 'ArrayDeclaredBfrangeCountBoundary', 'ArrayBfrangeCountBoundary-H'],
            ['true ', 'BooleanDeclaredBfrangeCountBoundary', 'BooleanBfrangeCountBoundary-H'],
        ] as [$countOperand, $baseFont, $cMapName]) {
            $assertMalformedCMapMissingDeclaredCountRejected(
                $t,
                $parserMalformedCMapMissingDeclaredCountPdf(
                    $countOperand,
                    'range',
                    'Missing Count Range Safe Import',
                    'Missing Count Range CMap Leak',
                    $baseFont,
                    $cMapName
                )
            );
        }
    },
];
