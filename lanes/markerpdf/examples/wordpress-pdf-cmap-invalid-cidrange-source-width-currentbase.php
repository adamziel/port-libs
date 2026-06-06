<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /InvalidLaterCidRangeWordSpacing-H def\n"
    . "1 begincodespacerange\n"
    . "<1000> <1003>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<1000> <1003> 32\n"
    . "endcidrange\n"
    . "1 begincidrange\n"
    . "<1000> <1003> 70000\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<1000> <1003>\n"
    . "endcodespacerange\n"
    . "4 beginbfchar\n"
    . "<1000> <0041>\n"
    . "<1001> <0042>\n"
    . "<1002> <0043>\n"
    . "<1003> <0044>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf 24 Tw 1 0 0 1 72 720 Tm <1000100110021003> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /InvalidLaterCidRangeWordSpacing /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /InvalidLaterCidRangeWordSpacing /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [32 35 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];

if ($lines !== ['ABCD'] || count($spans) !== 1) {
    throw new RuntimeException('Invalid later CID range source-width fixture did not import as one WordPress paragraph.');
}

$metadata = [
    'source' => 'native-pdf-cmap-invalid-cidrange-source-width-currentbase',
    'invalid_later_cidrange_ignored' => ($spans[0]['bbox'] ?? null) === [0.0, 0.0, 72.0, 12.0],
    'visible_text_imported' => $plainText === 'ABCD',
    'false_decoded_word_gap_excluded' => !str_contains($plainText, 'AB CD'),
    'cmap_program_bytes_visible_text_excluded' => !str_contains($plainText, 'InvalidLaterCidRangeWordSpacing'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-cmap-invalid-cidrange-source-width-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
