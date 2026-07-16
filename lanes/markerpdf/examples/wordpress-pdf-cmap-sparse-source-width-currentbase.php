<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /SparseCodespaceSourceWidth-H def\n"
    . "1 begincodespacerange\n"
    . "<000000> <FF0000>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<000000> <FF0000> 1000\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<000000> <FF0000>\n"
    . "endcodespacerange\n"
    . "8 beginbfchar\n"
    . "<000000> <0041>\n"
    . "<010000> <0042>\n"
    . "<020000> <0043>\n"
    . "<030000> <0044>\n"
    . "<200000> <0045>\n"
    . "<210000> <0046>\n"
    . "<220000> <0047>\n"
    . "<230000> <0048>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <000000010000020000030000> Tj '
    . '1 0 0 1 120 720 Tm <200000210000220000230000> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SparseCodespaceSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /SparseCodespaceSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1000 1003 1000 1032 1035 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];

if ($lines !== ['ABCDEFGH'] || count($spans) !== 2) {
    throw new RuntimeException('Sparse CMap source-width fixture did not import as one WordPress paragraph.');
}

$metadata = [
    'source' => 'native-pdf-cmap-sparse-source-width-currentbase',
    'sparse_codespace_source_widths_resolved' => ($spans[1]['bbox'] ?? null) === [48.0, 0.0, 60.0, 12.0],
    'visible_text_imported' => $plainText === 'ABCDEFGH',
    'false_word_gap_excluded' => !str_contains($plainText, 'ABCD EFGH'),
    'cmap_program_bytes_visible_text_excluded' => !str_contains($plainText, 'SparseCodespaceSourceWidth'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-cmap-sparse-source-width-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
