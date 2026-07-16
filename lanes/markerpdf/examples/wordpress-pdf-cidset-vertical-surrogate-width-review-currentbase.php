<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$cmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "4 beginbfchar\n"
    . "<0001> <2067D83DDE002069>\n"
    . "<0002> <0057006F00720064>\n"
    . "<0003> <0056006500720074>\n"
    . "<0004> <004A006F0069006E>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fv 12 Tf 1 0 0 1 72 720 Tm <0001> Tj 1 0 0 1 72 702 Tm <0002> Tj '
    . '1 0 0 1 96 720 Tm <0003> Tj 1 0 0 1 96 708 Tm <0004> Tj ET';
$cidSet = "\x38";
$compressedCidSet = gzcompress($cidSet);
if (!is_string($compressedCidSet)) {
    throw new RuntimeException('Unable to compress focused vertical surrogate CIDSet fixture.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CIDSetVerticalSurrogateSubset /Encoding /Identity-V /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CIDSetVerticalSurrogateSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /DW2 [880 -1000] /FontDescriptor 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /CIDSetVerticalSurrogateSubset /Flags 4 /CIDSet 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedCidSet) . " >>\nstream\n{$compressedCidSet}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$expected = "\u{2067}\u{1F600}\u{2069} Word";

echo '<!-- markerpdf:pdf-cidset-vertical-surrogate-width-review ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-vertical-cidset-surrogate-source-width-boundary',
    'font_width_sources' => ['/Encoding /Identity-V', '/CIDSet', '/DW2', 'ToUnicode source CIDs'],
    'surrogate_scalar_decoded' => str_contains($plainText, "\u{1F600}"),
    'bidi_isolate_controls_preserved' => str_contains($plainText, "\u{2067}") && str_contains($plainText, "\u{2069}"),
    'excluded_cid_fallback_gap_preserved' => str_contains($plainText, $expected),
    'included_cid_default_vertical_width_preserved' => str_contains($plainText, 'VertJoin') && !str_contains($plainText, 'Vert Join'),
    'nul_bytes_removed' => !str_contains($plainText, "\0"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
