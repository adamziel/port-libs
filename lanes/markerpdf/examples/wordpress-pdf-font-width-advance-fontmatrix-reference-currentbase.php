<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$charProc = "1 0 d0\nBT /Fghost 9 Tf (font matrix reference payload leak) Tj ET\n";
$content = 'BT /Ft3ref 12 Tf '
    . '1 0 0 1 72 720 Tm <4142> Tj '
    . '1 0 0 1 96 720 Tm <4344> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3ref 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3FontMatrixReference /BaseFont /T3FontMatrixReference "
    . "/FontBBox [0 0 1000 700] /FontMatrix [99 0 R 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 68 /Widths [500 500 500 500] /Encoding /WinAnsiEncoding "
    . "/CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R >> >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];
$spanBboxes = array_column($spans, 'bbox');

$allBboxNumbersFinite = true;
$maxBboxMagnitude = 0.0;
foreach (array_merge($spanBboxes, [$line['bbox'] ?? []]) as $bbox) {
    foreach ($bbox as $number) {
        $allBboxNumbersFinite = $allBboxNumbersFinite && is_float($number) && is_finite($number);
        $maxBboxMagnitude = max($maxBboxMagnitude, abs((float) $number));
    }
}

$firstEnd = (float) (($spanBboxes[0][2] ?? 0.0));
$secondStart = (float) (($spanBboxes[1][0] ?? 0.0));
$wordGapPreserved = $secondStart - $firstEnd > 12.0;
$payloadExcluded = !str_contains($plainText, 'font matrix reference payload leak')
    && !str_contains($plainText, 'T3FontMatrixReference')
    && !str_contains($plainText, 'Ft3ref')
    && !str_contains($plainText, "\0");

if (
    $lines !== ['AB CD']
    || $runs !== ['AB', 'CD']
    || $plainText !== 'AB CD'
    || array_column($spans, 'text') !== ['AB', 'CD']
    || !$allBboxNumbersFinite
    || $maxBboxMagnitude >= 100.0
    || !$wordGapPreserved
    || !$payloadExcluded
) {
    throw new RuntimeException('Type3 FontMatrix reference boundary smoke failed before WordPress import.');
}

echo '<!-- markerpdf-font-width-advance-fontmatrix-reference-boundary ';
echo htmlspecialchars(json_encode([
    'upstream_boundary' => 'PDF Type3 FontMatrix arrays are tokenized arrays; unresolved indirect-reference tokens must not be regex-parsed into numeric matrix entries',
    'wordpress_import_text' => $plainText,
    'lines' => $lines,
    'runs' => $runs,
    'span_bboxes' => $spanBboxes,
    'line_bbox' => $line['bbox'] ?? null,
    'bboxes_are_finite' => $allBboxNumbersFinite,
    'max_bbox_magnitude' => $maxBboxMagnitude,
    'word_gap_preserved' => $wordGapPreserved,
    'payload_excluded' => $payloadExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
