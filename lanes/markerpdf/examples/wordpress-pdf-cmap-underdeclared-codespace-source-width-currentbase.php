<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "2 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 begincodespacerange\n"
    . "<0020> <0027>\n"
    . "endcodespacerange\n"
    . "8 beginbfchar\n"
    . "<0020> <0041>\n"
    . "<0021> <0042>\n"
    . "<0022> <0043>\n"
    . "<0023> <0044>\n"
    . "<0024> <0045>\n"
    . "<0025> <0046>\n"
    . "<0026> <0047>\n"
    . "<0027> <0048>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <0020002100220023> Tj '
    . '1 0 0 1 132 720 Tm <0024002500260027> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPUnderdeclaredCodeSpaceSourceWidth /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPUnderdeclaredCodeSpaceSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [32 35 1000 36 39 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];

$flags = [
    'source' => 'native-pdf-cmap-underdeclared-codespace-source-width-currentbase',
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'later_valid_codespace_recovered' => $lines === ['ABCD EFGH']
        && $runs === ['ABCD', 'EFGH']
        && $plainText === 'ABCD EFGH',
    'source_width_geometry_preserved' => array_column($spans, 'bbox') === [
        [0.0, 0.0, 48.0, 12.0],
        [60.0, 0.0, 72.0, 12.0],
    ],
    'word_gap_preserved' => !str_contains($plainText, 'ABCDEFGH'),
    'cmap_program_bytes_visible_text_excluded' => !str_contains($plainText, 'begincodespacerange')
        && !str_contains($plainText, 'WPUnderdeclaredCodeSpaceSourceWidth'),
    'raw_nul_bytes_excluded' => !str_contains($plainText, "\0"),
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected underdeclared CMap code-space source-width smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-cmap-underdeclared-codespace-source-width-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
