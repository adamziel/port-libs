<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserCMapFilterEodBoundaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserCMapFilterEodBoundaryPdf = static function (
    bool $includeAsciiHexEod
) use ($parserCMapFilterEodBoundaryUtf16beHex): array {
    $safeText = $includeAsciiHexEod ? 'ASCIIHex EOD CMap Import' : 'Missing EOD Safe Import';
    $mappedText = $includeAsciiHexEod ? 'ASCIIHex EOD CMap Import' : 'Missing EOD CMap Leak';
    $sourceHex = $includeAsciiHexEod ? '0001' : $parserCMapFilterEodBoundaryUtf16beHex($safeText);
    $cMapSourceHex = $includeAsciiHexEod ? '0001' : substr($sourceHex, 0, 4);
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /CMapFilterEodBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$cMapSourceHex}> <" . $parserCMapFilterEodBoundaryUtf16beHex($mappedText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $encodedCMap = strtoupper(bin2hex($cMap)) . ($includeAsciiHexEod ? '>' : '');
    $content = "BT /Fcid 12 Tf 72 720 Td <{$sourceHex}> Tj ET";

    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CMapFilterEodBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /CMapFilterEodBoundary-H /Filter /ASCIIHexDecode /Length " . strlen($encodedCMap) . " >>\nstream\n{$encodedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $mappedText];
};

return [
    'requires explicit CMap ASCIIHex EOD before ToUnicode replacement on current base' => static function (TestRunner $t) use ($parserCMapFilterEodBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        [$missingEodPdf, $safeText, $leakingText] = $parserCMapFilterEodBoundaryPdf(false);
        [$validEodPdf, $validMappedText] = $parserCMapFilterEodBoundaryPdf(true);

        $missingPlainText = $extractor->extractPlainText($missingEodPdf);
        $missingReview = $extractor->extractCMapStreamFilterLengthOwnerReview($missingEodPdf);
        $missingEntry = $missingReview['entries'][0] ?? [];

        $t->same([$safeText], $extractor->extractTextLines($missingEodPdf));
        $t->same([$safeText], $extractor->extractTextRuns($missingEodPdf));
        $t->same($safeText, $missingPlainText);
        $t->same($safeText . "\n", $extractor->naiveGetText($missingEodPdf));
        $t->same(1, $extractor->extractOutlineMetadata($missingEodPdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($missingEodPdf));
        $t->true(!str_contains($missingPlainText, $leakingText));
        $t->true(!str_contains($missingPlainText, 'CMapFilterEodBoundary-H'));
        $t->true(!str_contains($missingPlainText, 'beginbfchar'));
        $t->true(!str_contains($missingPlainText, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $missingReview['source']);
        $t->true($missingReview['review_only']);
        $t->same(false, $missingReview['encrypted']);
        $t->same(1, $missingReview['cmap_stream_count']);
        $t->same(1, $missingReview['to_unicode_cmap_stream_count']);
        $t->same(0, $missingReview['decoded_cmap_count']);
        $t->same(0, $missingReview['unsupported_filter_count']);
        $t->same(0, $missingReview['invalid_filter_operand_count']);
        $t->same(0, $missingReview['malformed_filter_operand_count']);
        $t->same('CMapFilterEodBoundary-H', $missingEntry['cmap_name'] ?? null);
        $t->same(['ASCIIHexDecode'], $missingEntry['filters'] ?? null);
        $t->same(false, $missingEntry['filter_resolution_failed'] ?? null);
        $t->same('filters_resolved', $missingEntry['filter_operand_policy'] ?? null);
        $t->same('decodeparms_resolved', $missingEntry['decodeparms_operand_policy'] ?? null);
        $t->same(null, $missingEntry['decoded_cmap_length'] ?? null);
        $t->same(null, $missingEntry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $missingEntry['decoded_with_current_operands'] ?? null);

        $validPlainText = $extractor->extractPlainText($validEodPdf);
        $validReview = $extractor->extractCMapStreamFilterLengthOwnerReview($validEodPdf);
        $validEntry = $validReview['entries'][0] ?? [];

        $t->same([$validMappedText], $extractor->extractTextLines($validEodPdf));
        $t->same([$validMappedText], $extractor->extractTextRuns($validEodPdf));
        $t->same($validMappedText, $validPlainText);
        $t->same(1, $validReview['decoded_cmap_count']);
        $t->same(['ASCIIHexDecode'], $validEntry['filters'] ?? null);
        $t->same(true, $validEntry['decoded_with_current_operands'] ?? null);
        $t->true(($validEntry['decoded_cmap_length'] ?? 0) > 0);
        $t->same(false, $validReview['executes_python_or_models']);
        $t->same(false, $validReview['executes_external_pdf_tools']);
    },
];
