<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hugeCoordinate = '1' . str_repeat('0', 308);
$content = 'BT /Fpos 12 Tf '
    . "1 0 0 1 72 720 Tm <4142> Tj 1 0 0 1 {$hugeCoordinate} 720 Tm <4344> Tj "
    . "T* 1 0 0 1 72 704 Tm <4546> Tj {$hugeCoordinate} 0 Td <4748> Tj "
    . "T* {$hugeCoordinate} 0 0 1 72 688 Tm <494A> Tj 1 0 0 1 96 688 Tm <4B4C> Tj ET";
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fpos 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+HugeFinitePositionAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 76 /Widths [1000 1000 1000 1000 1000 1000 1000 1000 1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D /E /F /G /H /I /J /K /L] >>\nendobj\n%%EOF";

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

$bboxes = [];
foreach ($styledLines as $line) {
    foreach (($line['spans'] ?? []) as $span) {
        $bboxes[] = $span['bbox'] ?? [];
    }
    $bboxes[] = $line['bbox'] ?? [];
}

$allBboxNumbersFinite = true;
$maxBboxMagnitude = 0.0;
foreach ($bboxes as $bbox) {
    foreach ($bbox as $number) {
        $allBboxNumbersFinite = $allBboxNumbersFinite && is_float($number) && is_finite($number);
        $maxBboxMagnitude = max($maxBboxMagnitude, abs((float) $number));
    }
}

$summary = [
    'visible_text' => $plainText,
    'text_lines' => $lines,
    'overlarge_tm_x_rejected' => ($lines[0] ?? null) === 'ABCD',
    'overlarge_td_x_rejected' => ($lines[1] ?? null) === 'EFGH',
    'overlarge_tm_scale_rejected' => ($lines[2] ?? null) === 'IJKL',
    'false_word_gaps_excluded' => !str_contains($plainText, 'AB CD')
        && !str_contains($plainText, 'EF GH')
        && !str_contains($plainText, 'IJ KL'),
    'styled_bboxes_preserved' => array_column($styledLines[0]['spans'] ?? [], 'bbox') === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]]
        && array_column($styledLines[1]['spans'] ?? [], 'bbox') === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]]
        && array_column($styledLines[2]['spans'] ?? [], 'bbox') === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'bbox_numbers_finite' => $allBboxNumbersFinite,
    'max_bbox_magnitude' => $maxBboxMagnitude,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'overlarge_tm_x_rejected',
    'overlarge_td_x_rejected',
    'overlarge_tm_scale_rejected',
    'false_word_gaps_excluded',
    'styled_bboxes_preserved',
    'bbox_numbers_finite',
] as $flag) {
    if ($summary[$flag] !== true) {
        throw new RuntimeException('Expected font-width position operand boundary smoke flag to pass: ' . $flag);
    }
}
if ($summary['max_bbox_magnitude'] >= 1000.0) {
    throw new RuntimeException('Expected bounded styled bboxes for font-width position operand boundary smoke.');
}

echo '<!-- markerpdf:pdf-font-width-position-operand-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
