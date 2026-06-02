<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPSpanAdvance-H def\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "7 begincidchar\n"
    . "<01> 40\n"
    . "<02> 41\n"
    . "<03> 42\n"
    . "<04> 43\n"
    . "<20> 32\n"
    . "<41> 65\n"
    . "<42> 66\n"
    . "endcidchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "7 beginbfchar\n"
    . "<01> <0057>\n"
    . "<02> <0069>\n"
    . "<03> <0064>\n"
    . "<04> <0065>\n"
    . "<20> <2060>\n"
    . "<41> <0041>\n"
    . "<42> <0042>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf 18 Tw 1 0 0 1 72 720 Tm <01020304> Tj <412042> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SpanAdvanceCID /Encoding /WPSpanAdvance-H /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /SpanAdvanceCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [32 32 500 40 43 1000 65 66 500] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$spanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $spans
);

echo '<!-- markerpdf-font-cid-width-resource-spacing-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-font-cid-width-resource-spacing-currentbase',
    'source' => 'native-pdf-cid-width-resource-spacing-styled-span-boundary',
    'named_encoding_cmap_resource_resolved' => str_contains($plainText, "WideA\u{2060}B"),
    'cid_width_span_bbox_applied' => ($spanBboxes[0] ?? null) === [0.0, 0.0, 48.0, 12.0],
    'word_spacing_span_bbox_applied' => ($spanBboxes[1] ?? null) === [48.0, 0.0, 84.0, 12.0],
    'raw_font_resource_text_excluded' => !str_contains($plainText, 'Fcid') && !str_contains($plainText, 'WPSpanAdvance'),
    'span_bboxes' => $spanBboxes,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
