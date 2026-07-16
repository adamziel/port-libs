<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /SparseOverflowCIDRangeSourceWidth-H def\n"
    . "1 begincodespacerange\n"
    . "<1000> <1003>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<1000> <1003> 32\n"
    . "endcidrange\n"
    . "1 begincidrange\n"
    . "<1000> <10FF> 65534\n"
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
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SparseOverflowCIDRangeSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /SparseOverflowCIDRangeSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [32 35 1000 65534 65535 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];
$spanBox = $spans[0]['bbox'] ?? null;
$lineBox = $line['bbox'] ?? null;

$overflowRejected = $lines === ['ABCD']
    && array_column($spans, 'text') === ['ABCD']
    && $spanBox === [0.0, 0.0, 72.0, 12.0]
    && $lineBox === [0.0, 0.0, 72.0, 12.0];

if (!$overflowRejected) {
    throw new RuntimeException('Sparse overflow CID range source-width fixture did not import as bounded WordPress text.');
}

$metadata = [
    'source' => 'native-pdf-cmap-sparse-overflow-cidrange-source-width-currentbase',
    'sparse_codespace_overflow_cidrange_rejected' => $overflowRejected,
    'prior_cidrange_widths_preserved' => $spanBox === [0.0, 0.0, 72.0, 12.0],
    'overflow_cid_width_override_excluded' => $spanBox !== [0.0, 0.0, 30.0, 12.0],
    'visible_text_imported' => $plainText === 'ABCD',
    'false_word_gap_excluded' => !str_contains($plainText, 'A BCD'),
    'cmap_program_bytes_visible_text_excluded' => !str_contains($plainText, 'SparseOverflowCIDRangeSourceWidth'),
    'nul_bytes_excluded' => !str_contains($plainText, "\0"),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-cmap-sparse-overflow-cidrange-source-width-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
