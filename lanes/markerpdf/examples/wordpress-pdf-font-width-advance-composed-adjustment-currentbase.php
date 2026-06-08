<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$widths = implode(' ', array_fill(0, 35, '1000'));
$content = 'BT /Fadj 2000 Tf '
    . '1 0 0 1 72 720 Tm [(A) 100000 (B)] TJ '
    . '1 0 0 1 4000 720 Tm (C) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fadj 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+ComposedTjAdjustment /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 66 /Widths [{$widths}] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];
$spanBboxes = array_column($spans, 'bbox');
$allBboxNumbersFinite = true;
$maxBboxMagnitude = 0.0;
foreach ([...$spanBboxes, $line['bbox'] ?? []] as $bbox) {
    foreach ($bbox as $number) {
        $allBboxNumbersFinite = $allBboxNumbersFinite && is_float($number) && is_finite($number);
        $maxBboxMagnitude = max($maxBboxMagnitude, abs((float) $number));
    }
}

$summary = [
    'visible_text' => $plainText,
    'text_lines' => $lines,
    'composed_tj_adjustment_bounded' => $lines === ['ABC'] && $plainText === 'ABC',
    'false_word_gap_excluded' => !str_contains($plainText, 'AB C'),
    'styled_bboxes_bounded' => $spanBboxes === [[0.0, 0.0, 4000.0, 2000.0], [4000.0, 0.0, 6000.0, 2000.0]]
        && ($line['bbox'] ?? null) === [0.0, 0.0, 6000.0, 2000.0],
    'bbox_numbers_finite' => $allBboxNumbersFinite,
    'max_bbox_magnitude' => $maxBboxMagnitude,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $summary['composed_tj_adjustment_bounded'] !== true
    || $summary['false_word_gap_excluded'] !== true
    || $summary['styled_bboxes_bounded'] !== true
    || $summary['bbox_numbers_finite'] !== true
    || $summary['max_bbox_magnitude'] >= 10000.0
) {
    throw new RuntimeException('Composed TJ adjustment boundary smoke failed: ' . json_encode($summary, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-font-width-advance-composed-adjustment-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
