<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$longToken = str_repeat('A', 100);
$widths = array_fill(0, 84, '1000');
$widths[65 - 32] = '100000';
$widthArray = implode(' ', $widths);
$content = 'BT /Fcompose 12 Tf '
    . '1 0 0 1 72 720 Tm (' . $longToken . ') Tj '
    . '1 0 0 1 720 720 Tm (WordPress) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcompose 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+WPComposedMetricAdvance /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 115 /Widths [{$widthArray}] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];

$result = [
    'source' => 'wordpress_pdf_font_width_composed_advance_currentbase',
    'native_boundary' => 'composed font-width advances are bounded before WordPress paragraph gap decisions',
    'plain_text' => $plainText,
    'line_bbox' => $line['bbox'] ?? null,
    'span_bboxes' => array_column($spans, 'bbox'),
    'paragraph_gap_preserved' => $plainText === $longToken . ' WordPress',
    'bbox_is_bounded' => ($line['bbox'][2] ?? 0.0) === 756.0,
    'visible_text_excludes_font_metadata' => !str_contains($plainText, 'WPComposedMetricAdvance')
        && !str_contains($plainText, 'Fcompose'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach (['paragraph_gap_preserved', 'bbox_is_bounded', 'visible_text_excludes_font_metadata'] as $flag) {
    if (($result[$flag] ?? false) !== true) {
        throw new RuntimeException('Failed markerPDF composed font-width advance smoke check: ' . $flag);
    }
}

$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    throw new RuntimeException('Failed to encode markerPDF composed font-width advance smoke result.');
}

echo "<!-- markerpdf-font-width-composed-advance-currentbase\n{$json}\n-->\n";
