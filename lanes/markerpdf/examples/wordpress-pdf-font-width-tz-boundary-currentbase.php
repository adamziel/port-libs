<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Ftzhuge 12 Tf '
    . '1000000 Tz 1 0 0 1 72 720 Tm <4142> Tj 1 0 0 1 96 720 Tm <4344> Tj '
    . 'T* -1000000 Tz 1 0 0 1 72 704 Tm <4546> Tj 1 0 0 1 96 704 Tm <4748> Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftzhuge 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+HugeFiniteTzAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 72 /Widths [1000 1000 1000 1000 1000 1000 1000 1000] >>\nendobj\n"
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

$firstLine = $styledLines[0] ?? [];
$secondLine = $styledLines[1] ?? [];
$firstSpanBboxes = array_column($firstLine['spans'] ?? [], 'bbox');
$secondSpanBboxes = array_column($secondLine['spans'] ?? [], 'bbox');
$allBboxNumbersFinite = true;
$maxBboxMagnitude = 0.0;
foreach (array_merge($firstSpanBboxes, $secondSpanBboxes, [$firstLine['bbox'] ?? [], $secondLine['bbox'] ?? []]) as $bbox) {
    foreach ($bbox as $number) {
        $allBboxNumbersFinite = $allBboxNumbersFinite && is_float($number) && is_finite($number);
        $maxBboxMagnitude = max($maxBboxMagnitude, abs((float) $number));
    }
}

$summary = [
    'visible_text' => $plainText,
    'text_lines' => $lines,
    'overlarge_positive_tz_rejected' => ($lines[0] ?? null) === 'ABCD',
    'overlarge_negative_tz_rejected' => ($lines[1] ?? null) === 'EFGH',
    'false_word_gap_excluded' => !str_contains($plainText, 'AB CD') && !str_contains($plainText, 'EF GH'),
    'styled_bboxes_preserved' => $firstSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]]
        && $secondSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]]
        && ($firstLine['bbox'] ?? null) === [0.0, 0.0, 48.0, 12.0]
        && ($secondLine['bbox'] ?? null) === [0.0, 0.0, 48.0, 12.0],
    'bbox_numbers_finite' => $allBboxNumbersFinite,
    'max_bbox_magnitude' => $maxBboxMagnitude,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $summary['overlarge_positive_tz_rejected'] !== true
    || $summary['overlarge_negative_tz_rejected'] !== true
    || $summary['false_word_gap_excluded'] !== true
    || $summary['styled_bboxes_preserved'] !== true
    || $summary['bbox_numbers_finite'] !== true
    || $summary['max_bbox_magnitude'] >= 1000.0
) {
    throw new RuntimeException('Overlarge finite Tz boundary smoke failed: ' . json_encode($summary, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-font-width-tz-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
