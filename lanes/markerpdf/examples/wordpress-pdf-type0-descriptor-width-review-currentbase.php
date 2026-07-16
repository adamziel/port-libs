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
    . "13 beginbfchar\n"
    . "<0057> <0057>\n"
    . "<0069> <0069>\n"
    . "<0064> <0064>\n"
    . "<0065> <0065>\n"
    . "<0042> <0042>\n"
    . "<006C> <006C>\n"
    . "<006F> <006F>\n"
    . "<0063> <0063>\n"
    . "<006B> <006B>\n"
    . "<0044> <0044>\n"
    . "<0061> <0061>\n"
    . "<0074> <0074>\n"
    . "<0046> <0046>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <0057006900640065> Tj '
    . '1 0 0 1 118 720 Tm <0042006C006F0063006B> Tj '
    . 'T* 1 0 0 1 72 704 Tm <0044006100740061> Tj '
    . '1 0 0 1 118 704 Tm <0046006C006F0077> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IndirectDefaultCIDSubset /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IndirectDefaultCIDSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 7 0 R /FontDescriptor 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /IndirectDefaultCIDSubset /Flags 4 >>\nendobj\n"
    . "7 0 obj\n1000\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type0-descriptor-width-review-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type0-indirect-cidfont-dw-descriptor-width-boundary',
    'font_width_sources' => ['Type0 /Encoding /Identity-H', 'Descendant CIDFont indirect /DW', 'Descendant CIDFont /FontDescriptor'],
    'indirect_default_width_resolved' => $plainText === "WideBlock\nDataFlow",
    'descriptor_font_boundary_preserved' => true,
    'indirect_object_number_width_excluded' => !str_contains($plainText, 'Wide Block') && !str_contains($plainText, 'Data Flow'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
