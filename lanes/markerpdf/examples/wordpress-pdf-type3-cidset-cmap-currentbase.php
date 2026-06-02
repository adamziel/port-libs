<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<F000> <F0FF>\n"
    . "endcodespacerange\n"
    . "16 beginbfchar\n"
    . "<F041> <0057>\n"
    . "<F042> <0069>\n"
    . "<F043> <0064>\n"
    . "<F044> <0065>\n"
    . "<F04A> <004A>\n"
    . "<F04B> <006F>\n"
    . "<F04C> <0069>\n"
    . "<F04D> <006E>\n"
    . "<F054> <0054>\n"
    . "<F055> <0068>\n"
    . "<F056> <0069>\n"
    . "<F057> <006E>\n"
    . "<F05A> <004D>\n"
    . "<F05B> <0069>\n"
    . "<F05C> <0073>\n"
    . "<F05D> <0073>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /Type3CidSetCurrentBase-H def\n"
    . "1 begincodespacerange\n"
    . "<F000> <F0FF>\n"
    . "endcodespacerange\n"
    . "16 begincidchar\n"
    . "<F041> 65\n"
    . "<F042> 66\n"
    . "<F043> 67\n"
    . "<F044> 68\n"
    . "<F04A> 74\n"
    . "<F04B> 75\n"
    . "<F04C> 76\n"
    . "<F04D> 77\n"
    . "<F054> 84\n"
    . "<F055> 85\n"
    . "<F056> 86\n"
    . "<F057> 87\n"
    . "<F05A> 90\n"
    . "<F05B> 91\n"
    . "<F05C> 92\n"
    . "<F05D> 93\n"
    . "endcidchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$widths = array_fill(0, 23, 500.0);
foreach ([65, 66, 67, 68] as $code) {
    $widths[$code - 65] = 1000.0;
}
foreach ([74, 75, 76, 77, 84, 85, 86, 87] as $code) {
    $widths[$code - 65] = 250.0;
}
$widthArray = implode(' ', array_map(static fn (float $width): string => rtrim(rtrim(sprintf('%.1F', $width), '0'), '.'), $widths));

$cidSetBytes = str_repeat("\0", 12);
foreach ([90, 91, 92, 93] as $cid) {
    $byteIndex = intdiv($cid, 8);
    $cidSetBytes[$byteIndex] = chr(ord($cidSetBytes[$byteIndex]) | (1 << (7 - ($cid % 8))));
}
$cidSet = gzcompress($cidSetBytes);
if (!is_string($cidSet)) {
    throw new RuntimeException('Unable to compress focused Type3 CMap CIDSet fixture.');
}

$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <F05AF05BF05CF05D> Tj '
    . '1 0 0 1 118 720 Tm <F041F042F043F044> Tj '
    . 'T* 1 0 0 1 72 704 Tm <F054F055F056F057> Tj '
    . '1 0 0 1 96 704 Tm <F04AF04BF04CF04D> Tj ET';
$flags = (1 << 1) | (1 << 5);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 25 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CMapCIDSet /BaseFont /T3CMapCIDSet /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 65 /LastChar 87 /Widths 22 0 R /Encoding 19 0 R /CharProcs << >> /FontDescriptor 23 0 R /ToUnicode 20 0 R >>\nendobj\n"
    . "19 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "22 0 obj\n[{$widthArray}]\nendobj\n"
    . "23 0 obj\n<< /Type /FontDescriptor /FontName /T3CMapCIDSetSerif /Flags {$flags} /CIDSet 26 0 R >>\nendobj\n"
    . "25 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "26 0 obj\n<< /Filter /FlateDecode /Length " . strlen($cidSet) . " >>\nstream\n{$cidSet}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$pages = $extractor->extractStyledTextPages($pdf);
$firstSpan = $pages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];

echo '<!-- markerpdf:pdf-type3-cidset-cmap-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-cidset-cmap-currentbase',
    'font_width_sources' => [
        'Type3 object-valued /Encoding CMap source CIDs',
        'Type3 /Widths explicit CMap CID metrics',
        'FontDescriptor /CIDSet default-width subset',
        'ToUnicode source-code text mapping',
    ],
    'encoding_cmap_source_codes_resolved' => str_contains($plainText, 'MissWide') && str_contains($plainText, 'Thin Join'),
    'cidset_default_widths_preserve_missing_join' => str_contains($plainText, 'MissWide') && !str_contains($plainText, 'Miss Wide'),
    'explicit_widths_preserve_thin_gap' => str_contains($plainText, 'Thin Join') && !str_contains($plainText, 'ThinJoin'),
    'descriptor_flags_preserved' => ($firstSpan['font_flags'] ?? null) === $flags,
    'cidset_payload_visible_text_excluded' => !str_contains($plainText, "\0"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
