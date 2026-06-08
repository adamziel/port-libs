<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapUnterminatedDictionaryUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserMalformedCMapUnterminatedDictionaryPdf = static function (
    string $kind,
    string $safeText,
    string $leakingText,
    string $cMapName,
    string $baseFont
) use ($parserMalformedCMapUnterminatedDictionaryUtf16beHex): array {
    $safeHex = $parserMalformedCMapUnterminatedDictionaryUtf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $targetHex = $parserMalformedCMapUnterminatedDictionaryUtf16beHex($leakingText);
    $mappingBlock = $kind === 'range'
        ? "1 beginbfrange\n<{$sourceCode}> <{$sourceCode}> <{$targetHex}>\nendbfrange\n"
        : "1 beginbfchar\n<{$sourceCode}> <{$targetHex}>\nendbfchar\n";

    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "<< /Malformed (unterminated dictionary owns later mapping operators)\n"
        . $mappingBlock
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress focused unterminated-dictionary CMap fixture.');
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

    return [$pdf, $safeText, $leakingText, $cMapName];
};

$assertMalformedCMapUnterminatedDictionaryBoundary = static function (TestRunner $t, array $fixture): void {
    [$pdf, $safeText, $leakingText, $cMapName] = $fixture;
    $extractor = new PdfTextExtractor();
    $plainText = $extractor->extractPlainText($pdf);
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];

    $t->same([$safeText], $extractor->extractTextLines($pdf));
    $t->same([$safeText], $extractor->extractTextRuns($pdf));
    $t->same($safeText, $plainText);
    $t->same($safeText . "\n", $extractor->naiveGetText($pdf));
    $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    $t->same(['1'], $extractor->extractPageLabels($pdf));
    $t->true(!str_contains($plainText, $leakingText));
    $t->true(!str_contains($plainText, $cMapName));
    $t->true(!str_contains($plainText, 'beginbf'));
    $t->true(!str_contains($plainText, 'unterminated dictionary owns later mapping operators'));
    $t->true(!str_contains($plainText, "\0"));

    $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source'] ?? null);
    $t->same(true, $review['review_only'] ?? null);
    $t->same(false, $review['encrypted'] ?? null);
    $t->same(1, $review['cmap_stream_count'] ?? null);
    $t->same(1, $review['to_unicode_cmap_stream_count'] ?? null);
    $t->same(0, $review['encoding_cmap_stream_count'] ?? null);
    $t->same(1, $review['decoded_cmap_count'] ?? null);
    $t->same(0, $review['malformed_filter_operand_count'] ?? null);
    $t->same(0, $review['unsupported_filter_count'] ?? null);
    $t->same(0, $review['filter_decode_error_count'] ?? null);

    $t->same(6, $entry['object_number'] ?? null);
    $t->same(0, $entry['generation'] ?? null);
    $t->same($cMapName, $entry['cmap_name'] ?? null);
    $t->same(['FlateDecode'], $entry['filters'] ?? null);
    $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
    $t->same('filter_end_markers_resolved', $entry['filter_end_marker_policy'] ?? null);
    $t->same('filter_decoders_resolved', $entry['filter_decode_policy'] ?? null);
    $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
    $t->same(true, is_int($entry['decoded_cmap_length'] ?? null) && ($entry['decoded_cmap_length'] ?? 0) > 0);
    $t->same(true, is_string($entry['decoded_cmap_sha256'] ?? null) && strlen((string) ($entry['decoded_cmap_sha256'] ?? '')) === 64);
    $t->same(true, $entry['decoded_with_current_operands'] ?? null);
    $t->same('to_unicode', $entry['reference_usages'][0]['usage'] ?? null);
    $t->same(false, $review['executes_python_or_models'] ?? null);
    $t->same(false, $review['executes_external_pdf_tools'] ?? null);
};

return [
    'ignores filtered ToUnicode bfchar mappings after an unterminated dictionary before WordPress import' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapUnterminatedDictionaryPdf, $assertMalformedCMapUnterminatedDictionaryBoundary): void {
        $assertMalformedCMapUnterminatedDictionaryBoundary(
            $t,
            $parserMalformedCMapUnterminatedDictionaryPdf(
                'char',
                'Dictionary Char Safe Import',
                'Dictionary Char CMap Leak',
                'UnterminatedDictionaryCharBoundary-H',
                'UnterminatedDictionaryCharBoundary'
            )
        );
    },
    'ignores filtered ToUnicode bfrange mappings after an unterminated dictionary before WordPress import' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapUnterminatedDictionaryPdf, $assertMalformedCMapUnterminatedDictionaryBoundary): void {
        $assertMalformedCMapUnterminatedDictionaryBoundary(
            $t,
            $parserMalformedCMapUnterminatedDictionaryPdf(
                'range',
                'Dictionary Range Safe Import',
                'Dictionary Range CMap Leak',
                'UnterminatedDictionaryRangeBoundary-H',
                'UnterminatedDictionaryRangeBoundary'
            )
        );
    },
    'does not trust CMapName directives after unterminated dictionaries with hex tokens' => static function (
        TestRunner $t
    ) use ($parserMalformedCMapUnterminatedDictionaryUtf16beHex): void {
        $safeText = 'OK';
        $leakingText = 'NO';
        $safeHex = $parserMalformedCMapUnterminatedDictionaryUtf16beHex($safeText);
        $leakHex = $parserMalformedCMapUnterminatedDictionaryUtf16beHex($leakingText);
        $derivedName = 'UnterminatedDictionaryDerived-H';
        $forgedBaseName = 'ForgedUnterminatedDictionaryBase-H';

        $derivedCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CMapName /{$derivedName} def\n"
            . "/{$forgedBaseName} usecmap\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "endcmap\n"
            . "end\n"
            . "end\n";
        $baseCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "<< /Malformed <0000> /CMapName /{$forgedBaseName} def\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "1 beginbfchar\n"
            . "<{$safeHex}> <{$leakHex}>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "end\n"
            . "end\n";
        $compressedDerived = gzcompress($derivedCMap, 0);
        $compressedBase = gzcompress($baseCMap, 0);
        if (!is_string($compressedDerived) || !is_string($compressedBase)) {
            throw new RuntimeException('Unable to compress focused named unterminated-dictionary CMap fixture.');
        }

        $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
        $pdf = "%PDF-1.5\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UnterminatedDictionaryNamedBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /CMap /Filter /FlateDecode /Length " . strlen($compressedDerived) . " >>\nstream\n{$compressedDerived}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /CMap /Filter /FlateDecode /Length " . strlen($compressedBase) . " >>\nstream\n{$compressedBase}\nendstream\nendobj\n"
            . "%%EOF";

        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entries = [];
        foreach ($review['entries'] as $entry) {
            $entries[$entry['object_number']] = $entry;
        }

        $t->same([$safeText], $extractor->extractTextLines($pdf));
        $t->same($safeText, $plainText);
        $t->true(!str_contains($plainText, $leakingText));
        $t->same(2, $review['decoded_cmap_count']);
        $t->same($derivedName, $entries[6]['cmap_name'] ?? null);
        $t->same(null, $entries[7]['cmap_name'] ?? null);
        $t->same([], $entries[7]['reference_usages'] ?? null);
        $t->same(['FlateDecode'], $entries[6]['filters'] ?? null);
        $t->same(['FlateDecode'], $entries[7]['filters'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
