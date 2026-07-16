<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextBlockConverter.php';
require_once __DIR__ . '/../src/PdfTextExtractor.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPSparseArrayBfrangeCIDRange-H def\n"
    . "2 begincodespacerange\n"
    . "<10> <11>\n"
    . "<14> <15>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<10> <15> 32\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "2 begincodespacerange\n"
    . "<10> <11>\n"
    . "<14> <15>\n"
    . "endcodespacerange\n"
    . "1 beginbfrange\n"
    . "<10> <15> [<0041> <0042> <0058> <0059> <0043> <0044>]\n"
    . "endbfrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf 24 Tw '
    . '1 0 0 1 72 720 Tm <1011> Tj '
    . '1 0 0 1 111 720 Tm <1415> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPSparseArrayBfrangeSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPSparseArrayBfrangeSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [32 32 1000 33 35 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$runs = $extractor->extractTextRuns($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];

$flags = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'sparse_array_bfrange_resolved' => $lines === ['ABCD'] && $plainText === 'ABCD',
    'dense_gap_targets_excluded' => !str_contains($plainText, 'XY'),
    'first_codespace_source_width_word_spacing_applied' => ($spans[0]['bbox'] ?? null) === [0.0, 0.0, 39.0, 12.0],
    'second_codespace_uses_dense_array_source_offset' => $runs === ['AB', 'CD'] && ($spans[1]['bbox'] ?? null) === [39.0, 0.0, 45.0, 12.0],
    'visible_text_clean' => !str_contains($plainText, "\0") && !str_contains($plainText, 'AB CD'),
];

$behaviorFlags = array_diff_key($flags, [
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected sparse array bfrange source-width smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo "<!-- markerpdf-cmap-sparse-array-bfrange-source-width-smoke " . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
