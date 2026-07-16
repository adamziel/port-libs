<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WpCidSetZeroPaddingFallback-H def\n"
    . "2 begincodespacerange\n"
    . "<00> <FF>\n"
    . "<0000> <00FF>\n"
    . "endcodespacerange\n"
    . "16 begincidchar\n"
    . "<41> 65\n"
    . "<42> 66\n"
    . "<43> 67\n"
    . "<44> 68\n"
    . "<45> 69\n"
    . "<46> 70\n"
    . "<47> 71\n"
    . "<48> 72\n"
    . "<0041> 1000\n"
    . "<0042> 1001\n"
    . "<0043> 1002\n"
    . "<0044> 1003\n"
    . "<0045> 2000\n"
    . "<0046> 2001\n"
    . "<0047> 2002\n"
    . "<0048> 2003\n"
    . "endcidchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "8 beginbfchar\n"
    . "<41> <0041>\n"
    . "<42> <0042>\n"
    . "<43> <0043>\n"
    . "<44> <0044>\n"
    . "<45> <0045>\n"
    . "<46> <0046>\n"
    . "<47> <0047>\n"
    . "<48> <0048>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$cidSetBytes = array_fill(0, 10, 0);
foreach (range(65, 72) as $cid) {
    $cidSetBytes[intdiv($cid, 8)] |= 1 << (7 - ($cid % 8));
}
$cidSet = implode('', array_map('chr', $cidSetBytes));
$compressedCidSet = gzcompress($cidSet);
if (!is_string($compressedCidSet)) {
    throw new RuntimeException('Unable to compress focused WordPress CIDSet source-width fixture.');
}

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
    . '1 0 0 1 132 720 Tm <0045004600470048> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WpCidSetZeroPaddingFallback /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WpCidSetZeroPaddingFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [65 68 1000 69 72 250] /FontDescriptor 7 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /FontDescriptor /FontName /WpCidSetZeroPaddingFallback /Flags 4 /CIDSet 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedCidSet) . " >>\nstream\n{$compressedCidSet}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

echo '<!-- markerpdf:pdf-cmap-cidset-zero-padding-source-width-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-cmap-cidset-zero-padding-source-width-boundary',
    'font_width_sources' => ['Type0 Encoding CMap', 'ToUnicode suffix source keys', 'CIDFont /W', 'FontDescriptor /CIDSet'],
    'source_width_boundaries_preserved' => $plainText === 'ABCD EFGH',
    'wide_suffix_cids_used' => ($spans[0]['bbox'] ?? null) === [0.0, 0.0, 48.0, 12.0],
    'absent_padded_cid_width_excluded' => ($spans[0]['bbox'] ?? null) !== [0.0, 0.0, 24.0, 12.0],
    'thin_suffix_cids_used' => ($spans[1]['bbox'] ?? null) === [48.0, 0.0, 60.0, 12.0],
    'nul_bytes_removed' => !str_contains($plainText, "\0"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
