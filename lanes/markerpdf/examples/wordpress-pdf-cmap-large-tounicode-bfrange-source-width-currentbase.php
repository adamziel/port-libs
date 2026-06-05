<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPLargeToUnicodeBfrangeSourceWidth-H def\n"
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
    . "<0000> <1FFF>\n"
    . "endcodespacerange\n"
    . "1 beginbfrange\n"
    . "<0000> <1FFF> <0041>\n"
    . "endbfrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <1000100110021003> Tj '
    . '1 0 0 1 120 720 Tm <1004100510061007> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPLargeToUnicodeBfrangeSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPLargeToUnicodeBfrangeSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [5096 5099 1000 5100 5103 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$spanBboxes = array_column($spans, 'bbox');
$expectedFirstRun = "\u{1041}\u{1042}\u{1043}\u{1044}";
$expectedSecondRun = "\u{1045}\u{1046}\u{1047}\u{1048}";
$expectedLine = $expectedFirstRun . $expectedSecondRun;

$flags = [
    'source' => 'native-pdf-cmap-large-tounicode-bfrange-source-width-currentbase',
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'large_tounicode_bfrange_decoded' => $lines === [$expectedLine],
    'text_runs_preserved' => $runs === [$expectedFirstRun, $expectedSecondRun],
    'large_cidrange_source_widths_applied' => $spanBboxes === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'unmapped_source_fallback_excluded' => !str_contains($plainText, "\u{1000}\u{1001}\u{1002}\u{1003}"),
    'raw_nul_bytes_excluded' => !str_contains($plainText, "\0"),
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected large ToUnicode bfrange source-width smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-cmap-large-tounicode-bfrange-source-width-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
