<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextBlockConverter.php';
require_once __DIR__ . '/../src/PdfTextExtractor.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPMultiRangeSparseSourceWidth-H def\n"
    . "2 begincodespacerange\n"
    . "<000000> <000003>\n"
    . "<100000> <100000>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<000000> <100000> 1000\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "2 begincodespacerange\n"
    . "<000000> <000003>\n"
    . "<100000> <100000>\n"
    . "endcodespacerange\n"
    . "5 beginbfchar\n"
    . "<000000> <0041>\n"
    . "<000001> <0042>\n"
    . "<000002> <0043>\n"
    . "<000003> <0044>\n"
    . "<100000> <0045>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <000000000001000002000003> Tj '
    . '1 0 0 1 120 720 Tm <100000> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPMultiRangeSparseSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPMultiRangeSparseSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1000 1003 1000 1004 1004 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$runs = $extractor->extractTextRuns($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$spanBboxes = array_column($spans, 'bbox');

$flags = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'multi_range_cid_widths_applied' => $spanBboxes === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 51.0, 12.0]],
    'text_runs_preserved' => $runs === ['ABCD', 'E'],
    'default_width_excluded_for_far_source' => ($spanBboxes[1] ?? null) === [48.0, 0.0, 51.0, 12.0],
    'visible_text_clean' => $plainText === 'ABCDE' && !str_contains($plainText, "\0"),
];

$behaviorFlags = array_diff_key($flags, [
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected multi-range sparse CMap source-width smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo "<!-- markerpdf-cmap-multirange-sparse-source-width-smoke " . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
