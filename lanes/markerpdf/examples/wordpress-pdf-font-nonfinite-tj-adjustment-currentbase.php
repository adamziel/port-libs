<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hugeAdjustment = str_repeat('9', 400);
$content = 'BT /Ftjinf 12 Tf 1 0 0 1 72 720 Tm [(AB) ' . $hugeAdjustment . ' (CD)] TJ '
    . '1 0 0 1 120 720 Tm <4546> Tj ET';
$widths = implode(' ', array_fill(0, 39, '1000'));

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftjinf 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NonFiniteTjAdjustment /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 70 /Widths [{$widths}] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $line['spans'] ?? []
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

echo '<!-- markerpdf:pdf-font-nonfinite-tj-adjustment-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-font-nonfinite-tj-adjustment-currentbase',
    'source' => 'native-pdf-tj-adjustment-finite-number-boundary',
    'nonfinite_tj_adjustment_ignored' => $lines === ['ABCDEF'],
    'phantom_word_gap_excluded' => !str_contains($plainText, 'ABCD EF'),
    'styled_bboxes_are_finite' => $allBboxNumbersFinite,
    'styled_bboxes_preserved' => $spanBboxes === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 72.0, 12.0]],
    'line_bbox_preserved' => ($line['bbox'] ?? null) === [0.0, 0.0, 72.0, 12.0],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
