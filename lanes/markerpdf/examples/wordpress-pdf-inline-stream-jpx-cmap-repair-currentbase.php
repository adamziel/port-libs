<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
    throw new RuntimeException('Unable to build inline JPX CMap repair smoke fixture.');
}

$inlineJpxPayload = "\xff\x4ftruncated JPEG 2000 payload without EOC\n"
    . "/CMapName /FakeInlinePayload-H def\n"
    . "2 beginbfchar\n<01> <" . $utf16beHex('Inline JPX CMap Noise') . ">\nendbfchar\n"
    . "BT /Fcid 12 Tf 72 660 Td (Inline JPX stream text noise) Tj ET";
$content = "BT /Fcid 12 Tf 72 720 Td <01> Tj ET\n"
    . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /JPXDecode ID\n"
    . $inlineJpxPayload . "\nEI\n"
    . "BT /Fcid 12 Tf 72 704 Td <02> Tj ET";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPInlineJpxRepair /Encoding /Identity-H /DescendantFonts [7 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /WPInlineJpxRepair-H /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPInlineJpxRepair /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$text = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$metadata = [
    'source' => 'native-pdf-inline-stream-jpx-cmap-repair-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text pdftext/pypdfium visible text excludes image payload bytes',
    'visible_text_imported' => $text === "Before JPX CMap\nAfter JPX CMap",
    'excluded_inline_jpx_cmap_payload_text' => !str_contains($text, 'Inline JPX CMap Noise'),
    'excluded_inline_jpx_stream_text' => !str_contains($text, 'Inline JPX stream text noise'),
    'cmap_stream_count' => $review['cmap_stream_count'],
    'to_unicode_cmap_stream_count' => $review['to_unicode_cmap_stream_count'],
    'decoded_cmap_count' => $review['decoded_cmap_count'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:inline-stream-jpx-cmap-repair-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(str_replace("\n", ' ', $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
