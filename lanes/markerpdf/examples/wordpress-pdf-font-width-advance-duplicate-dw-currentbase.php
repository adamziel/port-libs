<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "9 beginbfchar\n"
    . "<0001> <0057>\n"
    . "<0002> <0069>\n"
    . "<0003> <0064>\n"
    . "<0004> <0065>\n"
    . "<0005> <0042>\n"
    . "<0006> <006C>\n"
    . "<0007> <006F>\n"
    . "<0008> <0063>\n"
    . "<0009> <006B>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fdwdup 12 Tf '
    . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
    . '1 0 0 1 96 720 Tm <00050006000700080009> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdwdup 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DuplicateDefaultWidthCID /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DuplicateDefaultWidthCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /DW 250 >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$spanBboxes = array_column($spans, 'bbox');

$smoke = [
    'scenario' => 'wordpress-pdf-font-width-advance-duplicate-dw-currentbase',
    'source' => 'native-pdf-cidfont-duplicate-dw-default-width-boundary',
    'font_width_sources' => [
        'duplicate top-level CIDFont /DW default-width keys',
        'selected current /DW scalar',
        'Type0 Identity-H ToUnicode source grouping',
    ],
    'lines' => $lines,
    'duplicate_dw_current_default_selected' => $lines === ['Wide Block'],
    'stale_first_dw_false_join_excluded' => !str_contains($plainText, 'WideBlock'),
    'styled_span_bboxes_preserved' => $spanBboxes === [
        [0.0, 0.0, 12.0, 12.0],
        [24.0, 0.0, 39.0, 12.0],
    ],
    'font_resource_names_visible' => str_contains($plainText, 'DuplicateDefaultWidthCID')
        || str_contains($plainText, 'Fdwdup'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $smoke['duplicate_dw_current_default_selected'] !== true
    || $smoke['stale_first_dw_false_join_excluded'] !== true
    || $smoke['styled_span_bboxes_preserved'] !== true
    || $smoke['font_resource_names_visible'] !== false
) {
    throw new RuntimeException('Duplicate CIDFont DW boundary smoke failed: ' . json_encode($smoke, JSON_UNESCAPED_SLASHES));
}

echo "<!-- markerpdf-font-width-advance-duplicate-dw-currentbase-smoke "
    . json_encode($smoke, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
