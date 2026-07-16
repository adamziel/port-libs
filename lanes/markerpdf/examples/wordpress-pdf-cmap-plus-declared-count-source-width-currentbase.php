<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /PlusDeclaredCountSourceWidth-H def\n"
    . "1 begincodespacerange\n"
    . "<20> <27>\n"
    . "endcodespacerange\n"
    . "+4 begincidchar\n"
    . "<20> 100\n"
    . "<21> 101\n"
    . "<22> 102\n"
    . "<23> 103\n"
    . "<24> 200\n"
    . "<25> 201\n"
    . "<26> 202\n"
    . "<27> 203\n"
    . "endcidchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<20> <27>\n"
    . "endcodespacerange\n"
    . "8 beginbfchar\n"
    . "<20> <0041>\n"
    . "<21> <0042>\n"
    . "<22> <0043>\n"
    . "<23> <0044>\n"
    . "<24> <0045>\n"
    . "<25> <0046>\n"
    . "<26> <0047>\n"
    . "<27> <0048>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <20212223> Tj '
    . '1 0 0 1 120 720 Tm <24252627> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PlusDeclaredCountSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PlusDeclaredCountSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [100 103 250 200 203 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];

$secondSpanBox = $spans[1]['bbox'] ?? null;
$lineBox = $line['bbox'] ?? null;
$plusDeclaredCountHonored = $secondSpanBox === [12.0, 0.0, 36.0, 12.0]
    && $lineBox === [0.0, 0.0, 36.0, 12.0];

if ($lines !== ['ABCD EFGH'] || count($spans) !== 2 || !$plusDeclaredCountHonored) {
    throw new RuntimeException('Plus-signed CMap declared-count source-width fixture did not import as a bounded WordPress paragraph.');
}

$metadata = [
    'source' => 'native-pdf-cmap-plus-declared-count-source-width-currentbase',
    'plus_declared_count_honored' => $plusDeclaredCountHonored,
    'surplus_cmap_rows_excluded_from_widths' => $secondSpanBox !== [12.0, 0.0, 60.0, 12.0],
    'visible_text_imported' => $plainText === 'ABCD EFGH',
    'false_default_width_gap_excluded' => !str_contains($plainText, 'ABCD  EFGH'),
    'cmap_program_bytes_visible_text_excluded' => !str_contains($plainText, 'PlusDeclaredCountSourceWidth'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-cmap-plus-declared-count-source-width-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
