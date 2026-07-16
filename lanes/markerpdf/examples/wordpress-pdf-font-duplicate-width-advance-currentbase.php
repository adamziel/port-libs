<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Fdup 12 Tf '
    . '1 0 0 1 72 720 Tm <4142> Tj '
    . '1 0 0 1 96 720 Tm <4344> Tj '
    . 'T* 1 0 0 1 72 704 Tm <4546> Tj '
    . '1 0 0 1 96 704 Tm <4748> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdup 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+DuplicateWidthAdvance "
    . "/Encoding 6 0 R /FirstChar 67 /LastChar 70 /Widths [250 250 250 250] "
    . "/FirstChar 65 /LastChar 72 /Widths [1000 1000 1000 1000 1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D /E /F /G /H] >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$styledLines = [];
foreach (($pages[0]['blocks'] ?? []) as $block) {
    foreach (($block['lines'] ?? []) as $line) {
        $styledLines[] = $line;
    }
}

$spanBboxes = [];
foreach ($styledLines as $line) {
    $spanBboxes[] = array_column($line['spans'] ?? [], 'bbox');
}

$smoke = [
    'scenario' => 'wordpress-pdf-font-duplicate-width-advance-currentbase',
    'source' => 'native-pdf-simple-font-duplicate-width-range-advance-boundary',
    'font_width_sources' => [
        'duplicate top-level /FirstChar key',
        'duplicate top-level /LastChar key',
        'duplicate top-level /Widths array',
        'current simple-font range advances',
    ],
    'lines' => $lines,
    'duplicate_width_current_range_selected' => $lines === ['ABCD', 'EFGH'],
    'stale_width_false_gap_excluded' => !str_contains($plainText, 'AB CD')
        && !str_contains($plainText, 'EF GH'),
    'stale_width_tiny_bboxes_excluded' => ($spanBboxes[0] ?? []) !== [
        [0.0, 0.0, 6.0, 12.0],
        [24.0, 0.0, 30.0, 12.0],
    ],
    'styled_span_bboxes_preserved' => ($spanBboxes[0] ?? []) === [
        [0.0, 0.0, 24.0, 12.0],
        [24.0, 0.0, 48.0, 12.0],
    ],
    'font_resource_names_visible' => str_contains($plainText, 'DuplicateWidthAdvance')
        || str_contains($plainText, 'Fdup'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo "<!-- markerpdf-font-duplicate-width-advance-currentbase-smoke "
    . json_encode($smoke, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
