<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPCodespaceSequenceCIDRange-H def\n"
    . "1 begincodespacerange\n"
    . "<3030> <3232>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<3030> <3232> 100\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<3030> <3232>\n"
    . "endcodespacerange\n"
    . "9 beginbfchar\n"
    . "<3030> <0041>\n"
    . "<3031> <0042>\n"
    . "<3032> <0043>\n"
    . "<3130> <0044>\n"
    . "<3131> <0045>\n"
    . "<3132> <0046>\n"
    . "<3230> <0047>\n"
    . "<3231> <0048>\n"
    . "<3232> <0049>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <303030313032> Tj '
    . '1 0 0 1 120 720 Tm <323032313232> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPCodespaceSequenceCIDRange /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPCodespaceSequenceCIDRange /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [100 102 1000 106 108 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$spanBboxes = array_column($spans, 'bbox');

$flags = [
    'source' => 'native-pdf-cmap-codespace-sequence-source-width',
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'codespace_sequence_cid_widths_applied' => $spanBboxes === [[0.0, 0.0, 36.0, 12.0], [36.0, 0.0, 45.0, 12.0]],
    'numeric_gap_default_width_excluded' => ($spans[1]['bbox'] ?? null) !== [36.0, 0.0, 72.0, 12.0],
    'text_lines_preserved' => $lines === ['ABC GHI'],
    'text_runs_preserved' => $runs === ['ABC', 'GHI'],
    'raw_nul_bytes_excluded' => !str_contains($plainText, "\0"),
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected CMap code-space sequence source-width smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-cmap-codespace-sequence-source-width-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
