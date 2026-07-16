<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /NotdefRangeSourceWidth-H def\n"
    . "1 begincodespacerange\n"
    . "<20> <27>\n"
    . "endcodespacerange\n"
    . "1 beginnotdefrange\n"
    . "<20> <27> 100\n"
    . "endnotdefrange\n"
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
    . '1 0 0 1 132 720 Tm <24252627> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NotdefRangeSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NotdefRangeSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [100 103 1000 104 107 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];
$spanBboxes = array_column($spans, 'bbox');

if (
    $lines !== ['ABCD EFGH']
    || $runs !== ['ABCD', 'EFGH']
    || $spanBboxes !== [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 96.0, 12.0]]
) {
    throw new RuntimeException('Expected Encoding CMap notdef ranges to use one CID width before WordPress import.');
}

echo '<!-- markerpdf-cmap-notdef-source-width-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-cmap-notdef-source-width-currentbase',
    'source' => 'native-pdf-cmap-notdef-range-source-width-fallback',
    'notdef_range_constant_cid_widths_applied' => $spanBboxes === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 96.0, 12.0]],
    'word_gap_preserved' => $lines === ['ABCD EFGH'],
    'text_runs_preserved' => $runs === ['ABCD', 'EFGH'],
    'sequential_notdef_widths_excluded' => ($spanBboxes[1] ?? null) !== [48.0, 0.0, 60.0, 12.0],
    'raw_source_default_width_excluded' => ($spanBboxes[0] ?? null) !== [0.0, 0.0, 24.0, 12.0],
    'raw_nul_bytes_excluded' => !str_contains(implode('', $lines), "\0"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
