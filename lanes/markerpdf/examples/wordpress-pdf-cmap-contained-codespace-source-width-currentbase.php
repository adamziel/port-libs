<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPContainedCodespaceSourceWidth-H def\n"
    . "2 begincodespacerange\n"
    . "<000000> <FF0000>\n"
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
    . "<000000> <FF0000>\n"
    . "<100000> <100000>\n"
    . "endcodespacerange\n"
    . "5 beginbfchar\n"
    . "<000000> <0041>\n"
    . "<010000> <0042>\n"
    . "<020000> <0043>\n"
    . "<030000> <0044>\n"
    . "<100000> <0045>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <000000010000020000030000> Tj '
    . '1 0 0 1 120 720 Tm <100000> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPContainedCodespaceSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPContainedCodespaceSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /W [1000 1003 1000 1016 1016 250] >>\nendobj\n"
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
    'source' => 'native-pdf-cmap-contained-codespace-source-width-currentbase',
    'contained_codespace_range_collapsed' => $lines === ['ABCDE'] && $plainText === 'ABCDE',
    'lazy_cid_range_far_source_ranked' => $runs === ['ABCD', 'E'],
    'far_source_thin_width_preserved' => ($spans[1]['bbox'] ?? null) === [48.0, 0.0, 51.0, 12.0],
    'false_word_gap_excluded' => !str_contains($plainText, 'ABCD E'),
    'cmap_program_bytes_visible_text_excluded' => !str_contains($plainText, 'WPContainedCodespaceSourceWidth'),
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
    throw new RuntimeException('Contained codespace CMap source-width smoke failed: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf:pdf-cmap-contained-codespace-source-width-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
