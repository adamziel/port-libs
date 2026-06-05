<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "6 beginbfchar\n"
    . "<01> <0056>\n"
    . "<02> <0065>\n"
    . "<03> <0072>\n"
    . "<04> <0074>\n"
    . "<05> <0058>\n"
    . "<06> <0059>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fv 12 Tf '
    . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
    . '1 0 0 1 72 690 Tm <00050006> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPVerticalW2SourceWidth /Encoding /MissingCustom-V /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /BaseFont /WPVerticalW2SourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [1 4 -500 500 880 5 6 -250 500 880] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$spanBboxes = array_column($spans, 'bbox');

$flags = [
    'scenario' => 'wordpress-pdf-cmap-vertical-w2-source-width-currentbase',
    'source' => 'native-pdf-cmap-vertical-w2-source-width-fallback',
    'support_component' => 'pdf-text-dictionary-core',
    'vertical_source_width_text_preserved' => $lines === ['VertXY'],
    'vertical_source_width_runs_preserved' => $runs === ['Vert', 'XY'],
    'vertical_w2_spans_applied' => $spanBboxes === [[0.0, 0.0, 12.0, 24.0], [12.0, 0.0, 24.0, 6.0]],
    'padding_bytes_not_counted_as_vertical_glyphs' => !in_array([0.0, 0.0, 12.0, 72.0], $spanBboxes, true),
    'false_word_gap_excluded' => !str_contains($plainText, 'Vert XY'),
    'raw_nul_bytes_excluded' => !str_contains($plainText, "\0"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

$behaviorFlags = array_diff_key($flags, [
    'scenario' => true,
    'source' => true,
    'support_component' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);
if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected vertical W2 source-width smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-cmap-vertical-w2-source-width-currentbase-smoke ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
