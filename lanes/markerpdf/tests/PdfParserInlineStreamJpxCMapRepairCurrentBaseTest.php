<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserInlineStreamJpxCMapRepairCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPInlineJpxRepair-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "2 beginbfchar\n"
        . "<01> <" . $utf16beHex('Before JPX CMap') . ">\n"
        . "<02> <" . $utf16beHex('After JPX CMap') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($toUnicode, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to build focused CMap fixture.');
    }

    $inlineJpxPayload = "\xff\x4ftruncated JPEG 2000 payload without EOC\n"
        . "/CMapName /FakeInlinePayload-H def\n"
        . "2 beginbfchar\n<01> <" . $utf16beHex('Inline JPX CMap Noise') . ">\nendbfchar\n"
        . "BT /Fcid 12 Tf 72 660 Td (Inline JPX stream text noise) Tj ET";
    $content = "BT /Fcid 12 Tf 72 720 Td <01> Tj ET\n"
        . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /JPXDecode ID\n"
        . $inlineJpxPayload . "\nEI\n"
        . "BT /Fcid 12 Tf 72 704 Td <02> Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPInlineJpxRepair /Encoding /Identity-H /DescendantFonts [7 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /WPInlineJpxRepair-H /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPInlineJpxRepair /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 >>\nendobj\n"
        . "%%EOF";
};

return [
    'repairs truncated inline JPX boundaries without leaking CMap-like payload text' => static function (TestRunner $t) use ($parserInlineStreamJpxCMapRepairCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserInlineStreamJpxCMapRepairCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];

        $expected = ['Before JPX CMap', 'After JPX CMap'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Before JPX CMap\nAfter JPX CMap", $text);
        $t->same("Before JPX CMap\nAfter JPX CMap\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Inline JPX CMap Noise'));
        $t->true(!str_contains($text, 'Inline JPX stream text noise'));
        $t->true(!str_contains($text, 'FakeInlinePayload'));
        $t->true(!str_contains($text, 'JPXDecode'));
        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(1, $review['decoded_cmap_count']);
        $t->same('WPInlineJpxRepair-H', $entry['cmap_name']);
        $t->same(['FlateDecode'], $entry['filters']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
