<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceHex = str_repeat('0001', 100);
$expectedText = str_repeat('A', 100);

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/WMode 1 def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<0001> <0001> 40\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<0001> <0041>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = "BT /Fvbound 12 Tf 1 0 0 1 72 720 Tm <{$sourceHex}> Tj ET";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fvbound 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VerticalComposedAdvanceCID /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /CMap /CMapName /VerticalComposedAdvanceCID-V /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /VerticalComposedAdvanceCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [40 40 -100000 500 880] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

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

$boundedVerticalAdvance = $spanBboxes === [[0.0, 0.0, 12.0, 1200.0]]
    && ($line['bbox'] ?? null) === [0.0, 0.0, 12.0, 1200.0]
    && $maxBboxMagnitude < 2000.0;
$payloadExcluded = !str_contains($plainText, 'VerticalComposedAdvanceCID')
    && !str_contains($plainText, 'Fvbound')
    && !str_contains($plainText, "\0");

if (
    $lines !== [$expectedText]
    || $runs !== [$expectedText]
    || $plainText !== $expectedText
    || array_column($spans, 'text') !== [$expectedText]
    || !$allBboxNumbersFinite
    || !$boundedVerticalAdvance
    || !$payloadExcluded
) {
    throw new RuntimeException('Vertical CIDFont W2 composed advance boundary smoke failed before WordPress import.');
}

echo '<!-- markerpdf-font-width-advance-vertical-composed-boundary ';
echo htmlspecialchars(json_encode([
    'upstream_boundary' => 'PDF vertical CIDFont W2 glyph displacements are font metrics; composed advances must be bounded before styled text geometry drives import layout',
    'wordpress_import_text_length' => strlen($plainText),
    'line_count' => count($lines),
    'run_count' => count($runs),
    'span_bboxes' => $spanBboxes,
    'line_bbox' => $line['bbox'] ?? null,
    'bboxes_are_finite' => $allBboxNumbersFinite,
    'max_bbox_magnitude' => $maxBboxMagnitude,
    'bounded_vertical_advance' => $boundedVerticalAdvance,
    'payload_excluded' => $payloadExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
