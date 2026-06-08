<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPNotdefRangeAfterLargeRangeSourceWidth-H def\n"
    . "1 begincodespacerange\n"
    . "<0000> <1FFF>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<0000> <1FFF> 1000\n"
    . "endcidrange\n"
    . "1 beginnotdefrange\n"
    . "<1800> <1807> 300\n"
    . "endnotdefrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<0000> <1FFF>\n"
    . "endcodespacerange\n"
    . "8 beginbfchar\n"
    . "<1800> <0041>\n"
    . "<1801> <0042>\n"
    . "<1802> <0043>\n"
    . "<1803> <0044>\n"
    . "<1804> <0045>\n"
    . "<1805> <0046>\n"
    . "<1806> <0047>\n"
    . "<1807> <0048>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <1800180118021803> Tj '
    . '1 0 0 1 120 720 Tm <1804180518061807> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPNotdefRangeAfterLargeRangeSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPNotdefRangeAfterLargeRangeSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [300 300 250 7144 7147 1000 7148 7151 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$runs = $extractor->extractTextRuns($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];
$spanBboxes = array_column($spans, 'bbox');

$flags = [
    'source' => 'native-pdf-cmap-notdef-range-order-source-width-currentbase',
    'lazy_cidrange_beats_later_notdef_range' => $spanBboxes === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'notdef_range_width_excluded' => !in_array([0.0, 0.0, 12.0, 12.0], $spanBboxes, true),
    'text_runs_preserved' => $runs === ['ABCD', 'EFGH'],
    'false_word_gap_excluded' => $plainText === 'ABCDEFGH' && !str_contains($plainText, 'ABCD EFGH'),
    'cmap_program_bytes_visible_text_excluded' => !str_contains($plainText, 'NotdefRangeAfterLargeRangeSourceWidth'),
    'nul_bytes_excluded' => !str_contains($plainText, "\0"),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'executes_python_pdftext' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected CMap notdef-range order source-width smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf:pdf-cmap-notdef-range-order-source-width-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
