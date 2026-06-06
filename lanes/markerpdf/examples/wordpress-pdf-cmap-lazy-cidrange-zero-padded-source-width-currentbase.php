<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPLazyCIDRangeZeroPadded-H def\n"
    . "1 begincodespacerange\n"
    . "<0000> <1FFF>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<0000> <1FFF> 1000\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<1000> <1007>\n"
    . "endcodespacerange\n"
    . "8 beginbfchar\n"
    . "<1000> <0041>\n"
    . "<1001> <0042>\n"
    . "<1002> <0043>\n"
    . "<1003> <0044>\n"
    . "<1004> <0045>\n"
    . "<1005> <0046>\n"
    . "<1006> <0047>\n"
    . "<1007> <0048>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <00001000000010010000100200001003> Tj '
    . '1 0 0 1 132 720 Tm <00001004000010050000100600001007> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPLazyCIDRangeZeroPadded /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPLazyCIDRangeZeroPadded /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [5096 5099 1000 5100 5103 250] >>\nendobj\n"
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

$flags = [
    'source' => 'native-pdf-cmap-lazy-cidrange-zero-padded-source-width-currentbase',
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'lazy_cidrange_suffix_widths_applied' => $lines === ['ABCD EFGH'],
    'text_runs_preserved' => $runs === ['ABCD', 'EFGH'],
    'zero_padding_width_excluded' => $spanBboxes === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'false_merged_word_gap_excluded' => $plainText === 'ABCD EFGH' && !str_contains($plainText, 'ABCDEFGH'),
    'cmap_program_bytes_visible_text_excluded' => !str_contains($plainText, 'WPLazyCIDRangeZeroPadded'),
    'raw_nul_bytes_excluded' => !str_contains($plainText, "\0"),
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected lazy CID-range zero-padded source-width smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-cmap-lazy-cidrange-zero-padded-source-width-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
