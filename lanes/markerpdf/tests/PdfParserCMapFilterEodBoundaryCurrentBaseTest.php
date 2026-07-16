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

$parserCMapFilterEodBoundaryAscii85 = static function (string $bytes, bool $includeTerminator = true): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($chunkLength === 4 && $value === 0) {
            $encoded .= 'z';
            continue;
        }

        $digits = '';
        for ($index = 0; $index < 5; $index++) {
            $digits = chr(($value % 85) + 33) . $digits;
            $value = intdiv($value, 85);
        }

        $encoded .= substr($digits, 0, $chunkLength + 1);
    }

    return $encoded . ($includeTerminator ? '~>' : '');
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

$parserCMapFilterEodBoundaryStackedInnerAscii85Pdf = static function (
    bool $includeAscii85Eod
) use ($parserCMapFilterEodBoundaryUtf16beHex, $parserCMapFilterEodBoundaryAscii85): array {
    $safeText = $includeAscii85Eod ? 'Inner ASCII85 CMap Import' : 'Inner ASCII85 Safe Import';
    $mappedText = $includeAscii85Eod ? 'Inner ASCII85 CMap Import' : 'Inner ASCII85 CMap Leak';
    $sourceHex = $includeAscii85Eod ? '0001' : $parserCMapFilterEodBoundaryUtf16beHex($safeText);
    $cMapSourceHex = $includeAscii85Eod ? '0001' : substr($sourceHex, 0, 4);
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /StackedInnerAscii85EodBoundary-H def\n"
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
    $innerAscii85CMap = $parserCMapFilterEodBoundaryAscii85($cMap, $includeAscii85Eod);
    $encodedCMap = gzcompress($innerAscii85CMap, 0);
    if (!is_string($encodedCMap)) {
        throw new RuntimeException('Unable to compress focused stacked CMap filter EOD fixture.');
    }
    $content = "BT /Fcid 12 Tf 72 720 Td <{$sourceHex}> Tj ET";

    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StackedInnerAscii85EodBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /StackedInnerAscii85EodBoundary-H /Filter [/FlateDecode /ASCII85Decode] /Length " . strlen($encodedCMap) . " >>\nstream\n{$encodedCMap}\nendstream\nendobj\n"
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
        $t->same(1, $missingReview['filter_end_marker_problem_count']);
        $t->same(0, $missingReview['invalid_filter_operand_count']);
        $t->same(0, $missingReview['malformed_filter_operand_count']);
        $t->same('CMapFilterEodBoundary-H', $missingEntry['cmap_name'] ?? null);
        $t->same(['ASCIIHexDecode'], $missingEntry['filters'] ?? null);
        $t->same(false, $missingEntry['filter_resolution_failed'] ?? null);
        $t->same('filters_resolved', $missingEntry['filter_operand_policy'] ?? null);
        $t->same('reject_malformed_filter_end_markers', $missingEntry['filter_end_marker_policy'] ?? null);
        $t->same(1, $missingEntry['filter_end_marker_problem_count'] ?? null);
        $t->same('missing_explicit_end_marker', $missingEntry['filter_end_marker_problems'][0]['problem'] ?? null);
        $t->same(0, $missingEntry['filter_end_marker_problems'][0]['filter_index'] ?? null);
        $t->same('ASCIIHexDecode', $missingEntry['filter_end_marker_problems'][0]['filter'] ?? null);
        $t->same(true, $missingEntry['filter_end_marker_problems'][0]['requires_explicit_end_marker'] ?? null);
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
        $t->same(0, $validReview['filter_end_marker_problem_count']);
        $t->same(['ASCIIHexDecode'], $validEntry['filters'] ?? null);
        $t->same('filter_end_markers_resolved', $validEntry['filter_end_marker_policy'] ?? null);
        $t->same([], $validEntry['filter_end_marker_problems'] ?? null);
        $t->same(true, $validEntry['decoded_with_current_operands'] ?? null);
        $t->true(($validEntry['decoded_cmap_length'] ?? 0) > 0);
        $t->same(false, $validReview['executes_python_or_models']);
        $t->same(false, $validReview['executes_external_pdf_tools']);
    },
    'reports missing inner ASCII85 EOD after an outer Flate CMap filter stage' => static function (TestRunner $t) use ($parserCMapFilterEodBoundaryStackedInnerAscii85Pdf): void {
        $extractor = new PdfTextExtractor();
        [$missingEodPdf, $safeText, $leakingText] = $parserCMapFilterEodBoundaryStackedInnerAscii85Pdf(false);
        [$validEodPdf, $validMappedText] = $parserCMapFilterEodBoundaryStackedInnerAscii85Pdf(true);

        $missingPlainText = $extractor->extractPlainText($missingEodPdf);
        $missingReview = $extractor->extractCMapStreamFilterLengthOwnerReview($missingEodPdf);
        $missingEntry = $missingReview['entries'][0] ?? [];
        $missingProblems = $missingEntry['filter_end_marker_problems'] ?? [];

        $t->same([$safeText], $extractor->extractTextLines($missingEodPdf));
        $t->same([$safeText], $extractor->extractTextRuns($missingEodPdf));
        $t->same($safeText, $missingPlainText);
        $t->same($safeText . "\n", $extractor->naiveGetText($missingEodPdf));
        $t->true(!str_contains($missingPlainText, $leakingText));
        $t->true(!str_contains($missingPlainText, 'StackedInnerAscii85EodBoundary-H'));
        $t->true(!str_contains($missingPlainText, 'beginbfchar'));
        $t->same(1, $missingReview['cmap_stream_count']);
        $t->same(1, $missingReview['to_unicode_cmap_stream_count']);
        $t->same(0, $missingReview['decoded_cmap_count']);
        $t->same(1, $missingReview['filter_end_marker_problem_count']);
        $t->same(['FlateDecode', 'ASCII85Decode'], $missingEntry['filters'] ?? null);
        $t->same(false, $missingEntry['filter_resolution_failed'] ?? null);
        $t->same('filters_resolved', $missingEntry['filter_operand_policy'] ?? null);
        $t->same('reject_malformed_filter_end_markers', $missingEntry['filter_end_marker_policy'] ?? null);
        $t->same(1, $missingEntry['filter_end_marker_problem_count'] ?? null);
        $t->same(1, count($missingProblems));
        $t->same(1, $missingProblems[0]['filter_index'] ?? null);
        $t->same('ASCII85Decode', $missingProblems[0]['filter'] ?? null);
        $t->same('missing_explicit_end_marker', $missingProblems[0]['problem'] ?? null);
        $t->same(true, $missingProblems[0]['requires_explicit_end_marker'] ?? null);
        $t->same(null, $missingEntry['decoded_cmap_length'] ?? null);
        $t->same(false, $missingEntry['decoded_with_current_operands'] ?? null);

        $validPlainText = $extractor->extractPlainText($validEodPdf);
        $validReview = $extractor->extractCMapStreamFilterLengthOwnerReview($validEodPdf);
        $validEntry = $validReview['entries'][0] ?? [];

        $t->same([$validMappedText], $extractor->extractTextLines($validEodPdf));
        $t->same([$validMappedText], $extractor->extractTextRuns($validEodPdf));
        $t->same($validMappedText, $validPlainText);
        $t->same(1, $validReview['decoded_cmap_count']);
        $t->same(0, $validReview['filter_end_marker_problem_count']);
        $t->same(['FlateDecode', 'ASCII85Decode'], $validEntry['filters'] ?? null);
        $t->same('filter_end_markers_resolved', $validEntry['filter_end_marker_policy'] ?? null);
        $t->same([], $validEntry['filter_end_marker_problems'] ?? null);
        $t->same(true, $validEntry['decoded_with_current_operands'] ?? null);
        $t->true(($validEntry['decoded_cmap_length'] ?? 0) > 0);
        $t->same(false, $validReview['executes_python_or_models']);
        $t->same(false, $validReview['executes_external_pdf_tools']);
    },
];
