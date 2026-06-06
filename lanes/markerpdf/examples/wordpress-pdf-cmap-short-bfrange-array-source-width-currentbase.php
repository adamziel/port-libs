<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /ShortBfrangeArraySourceWidth-H def\n"
    . "1 begincodespacerange\n"
    . "<20> <27>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<20> <27> 100\n"
    . "endcidrange\n"
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
    . "1 beginbfrange\n"
    . "<20> <27> [<0058> <0059> <005A> <0057>]\n"
    . "endbfrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <20212223> Tj '
    . '1 0 0 1 120 720 Tm <24252627> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ShortBfrangeArraySourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ShortBfrangeArraySourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [100 103 1000 104 107 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];
$spanBboxes = array_column($spans, 'bbox');
$plainText = $extractor->extractPlainText($pdf);

if (
    $lines !== ['ABCDEFGH']
    || $runs !== ['ABCD', 'EFGH']
    || $spanBboxes !== [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]]
    || str_contains($plainText, 'XYZW')
) {
    throw new RuntimeException('Expected short ToUnicode bfrange arrays to preserve exact source mappings before WordPress import.');
}

echo '<!-- markerpdf-cmap-short-bfrange-array-source-width-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-cmap-short-bfrange-array-source-width-currentbase',
    'source' => 'native-pdf-cmap-short-bfrange-array-source-width-fallback',
    'exact_bfchar_rows_preserved' => $lines === ['ABCDEFGH'],
    'source_width_runs_preserved' => $runs === ['ABCD', 'EFGH'],
    'cid_widths_applied' => $spanBboxes === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'short_bfrange_array_excluded' => !str_contains($plainText, 'XYZW'),
    'raw_source_glyphs_excluded' => !str_contains($plainText, "\$%&'"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
