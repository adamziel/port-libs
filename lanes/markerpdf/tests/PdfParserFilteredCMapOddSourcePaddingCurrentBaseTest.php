<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserFilteredCMapOddSourcePaddingUtf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$parserFilteredCMapOddSourcePaddingPdf = static function (
    string $mappingBlock
) use ($parserFilteredCMapOddSourcePaddingUtf16beHex): array {
    $sourceText = '@dd Source Safe Import';
    $mappedText = 'Pdd Source Safe Import';
    $sourceHex = $parserFilteredCMapOddSourcePaddingUtf16beHex($sourceText);
    $oddSourceToken = substr($sourceHex, 0, 3);
    $cMapName = 'FilteredOddSourcePadding-H';

    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . str_replace(
            ['{{ODD_SOURCE}}', '{{PADDED_TARGET}}'],
            [$oddSourceToken, $parserFilteredCMapOddSourcePaddingUtf16beHex('P')],
            $mappingBlock
        )
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress focused odd-source padding CMap fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$sourceHex}> Tj ET";
    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /FilteredOddSourcePadding /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $mappedText, $sourceText, $cMapName, $oddSourceToken];
};

$parserFilteredCMapOddSourcePaddingAssert = static function (
    TestRunner $t,
    string $pdf,
    string $mappedText,
    string $sourceText,
    string $cMapName,
    string $oddSourceToken
): void {
    $extractor = new PdfTextExtractor();
    $plainText = $extractor->extractPlainText($pdf);
    $pages = $extractor->extractStyledTextPages($pdf);
    $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
    $spans = $line['spans'] ?? [];
    $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
    $entry = $review['entries'][0] ?? [];

    $t->same([$mappedText], $extractor->extractTextLines($pdf));
    $t->same([$mappedText], $extractor->extractTextRuns($pdf));
    $t->same($mappedText, $plainText);
    $t->same($mappedText . "\n", $extractor->naiveGetText($pdf));
    $t->same([$mappedText], array_column($spans, 'text'));
    $t->true(!str_contains($plainText, $sourceText));
    $t->true(!str_contains($plainText, $cMapName));
    $t->true(!str_contains($plainText, 'beginbfchar'));
    $t->true(!str_contains($plainText, 'beginbfrange'));
    $t->true(!str_contains($plainText, "\0"));

    $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
    $t->true($review['review_only']);
    $t->same(false, $review['encrypted']);
    $t->same(1, $review['cmap_stream_count']);
    $t->same(1, $review['to_unicode_cmap_stream_count']);
    $t->same(1, $review['decoded_cmap_count']);
    $t->same(0, $review['invalid_filter_operand_count']);
    $t->same(0, $review['malformed_filter_operand_count']);
    $t->same(0, $review['unsupported_filter_count']);
    $t->same(0, $review['filter_decode_error_count']);

    $t->same(6, $entry['object_number'] ?? null);
    $t->same(0, $entry['generation'] ?? null);
    $t->same($cMapName, $entry['cmap_name'] ?? null);
    $t->same(['FlateDecode'], $entry['filters'] ?? null);
    $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
    $t->same(true, $entry['decoded_with_current_operands'] ?? null);
    $t->same(3, strlen($oddSourceToken));
    $t->same(false, $review['executes_python_or_models']);
    $t->same(false, $review['executes_external_pdf_tools']);
};

return [
    'pads filtered ToUnicode bfchar odd-nibble source token before current-base text extraction' => static function (
        TestRunner $t
    ) use (
        $parserFilteredCMapOddSourcePaddingPdf,
        $parserFilteredCMapOddSourcePaddingAssert
    ): void {
        [$pdf, $mappedText, $sourceText, $cMapName, $oddSourceToken] = $parserFilteredCMapOddSourcePaddingPdf(
            "1 beginbfchar\n"
            . "<{{ODD_SOURCE}}> <{{PADDED_TARGET}}>\n"
            . "endbfchar\n"
        );

        $parserFilteredCMapOddSourcePaddingAssert(
            $t,
            $pdf,
            $mappedText,
            $sourceText,
            $cMapName,
            $oddSourceToken
        );
    },

    'pads filtered ToUnicode bfrange odd-nibble source token before current-base text extraction' => static function (
        TestRunner $t
    ) use (
        $parserFilteredCMapOddSourcePaddingPdf,
        $parserFilteredCMapOddSourcePaddingAssert
    ): void {
        [$pdf, $mappedText, $sourceText, $cMapName, $oddSourceToken] = $parserFilteredCMapOddSourcePaddingPdf(
            "1 beginbfrange\n"
            . "<{{ODD_SOURCE}}> <{{ODD_SOURCE}}> <{{PADDED_TARGET}}>\n"
            . "endbfrange\n"
        );

        $parserFilteredCMapOddSourcePaddingAssert(
            $t,
            $pdf,
            $mappedText,
            $sourceText,
            $cMapName,
            $oddSourceToken
        );
    },
];
