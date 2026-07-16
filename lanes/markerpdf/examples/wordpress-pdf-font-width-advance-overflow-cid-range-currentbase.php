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
$content = 'BT /Fcidw 12 Tf '
    . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
    . '1 0 0 1 120 720 Tm <00050006000700080009> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcidw 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /OverflowCidWidthRange /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /OverflowCidWidthRange /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1 70000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

$smoke = [
    'scenario' => 'wordpress-pdf-font-width-advance-overflow-cid-range-currentbase',
    'source' => 'native-pdf-cidfont-overflow-w-range-boundary-currentbase',
    'font_width_sources' => [
        'CIDFont /W range with overflowing last CID rejected',
        'CIDFont /DW fallback preserved for WordPress text grouping',
    ],
    'plain_text' => $plainText,
    'lines' => $extractor->extractTextLines($pdf),
    'runs' => $extractor->extractTextRuns($pdf),
    'span_bboxes' => array_column($spans, 'bbox'),
    'overflow_w_range_rejected' => $plainText === 'Wide Block',
    'fallback_advance_gap_preserved' => array_column($spans, 'bbox') === [[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 78.0, 12.0]],
    'font_resource_payload_visible' => str_contains($plainText, 'OverflowCidWidthRange') || str_contains($plainText, 'Fcidw'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $smoke['overflow_w_range_rejected'] !== true
    || $smoke['fallback_advance_gap_preserved'] !== true
    || $smoke['font_resource_payload_visible'] !== false
) {
    throw new RuntimeException('Overflow CIDFont width range boundary smoke failed: ' . json_encode($smoke, JSON_UNESCAPED_SLASHES));
}

echo "<!-- markerpdf-font-width-advance-overflow-cid-range-currentbase-smoke "
    . htmlspecialchars(json_encode($smoke, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
