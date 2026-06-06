<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hugeFontSize = '1' . str_repeat('0', 308);
$content = 'BT /Fhugefs 12 Tf 1 0 0 1 72 720 Tm <4142> Tj '
    . '/Fhugefs ' . $hugeFontSize . ' Tf <4344> Tj '
    . '1 0 0 1 144 720 Tm <4546> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fhugefs 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+HugeFiniteFontSizeAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 70 /Widths [1000 1000 1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D /E /F] >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];
$spanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $spans
);

$allBboxNumbersFinite = true;
foreach ($spanBboxes as $bbox) {
    foreach ($bbox as $number) {
        $allBboxNumbersFinite = $allBboxNumbersFinite
            && is_float($number)
            && is_finite($number);
    }
}

$plainText = implode("\n", $lines);
$flags = [
    'scenario' => 'wordpress-pdf-font-huge-finite-tf-boundary-currentbase',
    'source' => 'native-pdf-font-size-advance-finite-number-boundary',
    'huge_finite_tf_ignored' => $lines === ['ABCD EF'],
    'previous_font_size_preserved' => array_column($spans, 'font_size') === [12.0, 12.0, 12.0],
    'word_gap_preserved_after_rejected_tf' => str_contains($plainText, 'ABCD EF'),
    'false_joined_text_excluded' => !str_contains($plainText, 'ABCDEF'),
    'styled_bboxes_are_finite' => $allBboxNumbersFinite,
    'styled_bboxes_preserved' => $spanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0], [72.0, 0.0, 96.0, 12.0]],
    'line_bbox_preserved' => ($line['bbox'] ?? null) === [0.0, 0.0, 96.0, 12.0],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'huge_finite_tf_ignored',
    'previous_font_size_preserved',
    'word_gap_preserved_after_rejected_tf',
    'false_joined_text_excluded',
    'styled_bboxes_are_finite',
    'styled_bboxes_preserved',
    'line_bbox_preserved',
] as $flag) {
    if (($flags[$flag] ?? false) !== true) {
        throw new RuntimeException('Expected huge finite Tf boundary smoke flag to pass: ' . $flag);
    }
}

if ($flags['executes_python_or_models'] !== false || $flags['executes_external_pdf_tools'] !== false) {
    throw new RuntimeException('Huge finite Tf boundary smoke must stay native and no-GPU.');
}

echo '<!-- markerpdf:pdf-font-huge-finite-tf-boundary-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
