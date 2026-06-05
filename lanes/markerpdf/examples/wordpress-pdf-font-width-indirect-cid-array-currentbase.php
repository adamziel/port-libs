<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "17 beginbfchar\n"
    . "<0001> <0057>\n"
    . "<0002> <0069>\n"
    . "<0003> <0064>\n"
    . "<0004> <0065>\n"
    . "<0005> <0042>\n"
    . "<0006> <006C>\n"
    . "<0007> <006F>\n"
    . "<0008> <0063>\n"
    . "<0009> <006B>\n"
    . "<0014> <0054>\n"
    . "<0015> <0068>\n"
    . "<0016> <0069>\n"
    . "<0017> <006E>\n"
    . "<0018> <0054>\n"
    . "<0019> <0065>\n"
    . "<001A> <0078>\n"
    . "<001B> <0074>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
    . '1 0 0 1 118 720 Tm <00050006000700080009> Tj '
    . 'T* 1 0 0 1 72 704 Tm <0014001500160017> Tj '
    . '1 0 0 1 96 704 Tm <00180019001A001B> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IndirectCidWidthArray /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IndirectCidWidthArray /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1 6 0 R 20 27 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n[1000 1000 1000 1000 1000 1000 1000 1000 1000]\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$pages = $extractor->extractStyledTextPages($pdf);
$firstSpans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

echo '<!-- markerpdf-font-width-indirect-cid-array-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-font-width-indirect-cid-array-currentbase',
    'source' => 'native-pdf-cidfont-indirect-w-array-advance-boundary',
    'font_width_sources' => [
        'Type0 /ToUnicode source keys',
        'Descendant CIDFont /W array',
        'indirect per-CID width list object',
        'CIDFont /DW fallback for unmapped ranges',
    ],
    'indirect_cid_w_array_resolved' => str_contains($plainText, 'WideBlock') && str_contains($plainText, 'Thin Text'),
    'wide_cid_runs_not_split' => !str_contains($plainText, 'Wide Block'),
    'thin_cid_gap_preserved' => !str_contains($plainText, 'ThinText'),
    'styled_span_widths' => array_column($firstSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 108.0, 12.0]],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
