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
    . "<0057> <0057>\n"
    . "<0069> <0069>\n"
    . "<0064> <0064>\n"
    . "<0065> <0065>\n"
    . "<0042> <0042>\n"
    . "<006C> <006C>\n"
    . "<006F> <006F>\n"
    . "<0063> <0063>\n"
    . "<006B> <006B>\n"
    . "<0054> <0054>\n"
    . "<0068> <0068>\n"
    . "<006E> <006E>\n"
    . "<0078> <0078>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <0057006900640065> Tj '
    . '1 0 0 1 118 720 Tm <0042006C006F0063006B> Tj '
    . 'T* 1 0 0 1 72 704 Tm <005400680069006E> Tj '
    . '1 0 0 1 116 704 Tm <0054006500780074> Tj ET';

$cidSetBytes = array_fill(0, 16, 0);
foreach ([0x42, 0x57, 0x63, 0x64, 0x65, 0x69, 0x6b, 0x6c, 0x6f] as $cid) {
    $cidSetBytes[intdiv($cid, 8)] |= 1 << (7 - ($cid % 8));
}
$cidSetPayload = implode('', array_map('chr', $cidSetBytes));
$compressedCidSet = gzcompress($cidSetPayload);
if (!is_string($compressedCidSet)) {
    throw new RuntimeException('Unable to compress focused Type0 CIDSet descriptor fixture.');
}

$flags = (1 << 1) | (1 << 5);
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IndirectDescendantCIDSet /Encoding /Identity-H /DescendantFonts 8 0 R /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IndirectDescendantCIDSet /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /IndirectCIDSetSerif /Flags {$flags} /CIDSet 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressedCidSet) . " >>\nstream\n{$compressedCidSet}\nendstream\nendobj\n"
    . "8 0 obj\n[4 0 R]\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$pages = $extractor->extractStyledTextPages($pdf);
$firstSpan = $pages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];

echo '<!-- markerpdf:pdf-type0-cidset-descriptor-default-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type0-indirect-descendant-cidset-descriptor-default-boundary',
    'font_width_sources' => [
        'Type0 object-valued /DescendantFonts array',
        'Descendant CIDFont /FontDescriptor /CIDSet',
        'CIDFont default /DW 1000 when /DW is omitted',
    ],
    'indirect_descendant_fonts_resolved' => $plainText === "WideBlock\nThin Text",
    'cidset_default_width_preserves_subset_join' => str_contains($plainText, 'WideBlock') && !str_contains($plainText, 'Wide Block'),
    'non_cidset_glyphs_keep_gap' => str_contains($plainText, 'Thin Text') && !str_contains($plainText, 'ThinText'),
    'descriptor_flags_preserved' => ($firstSpan['font_flags'] ?? null) === $flags,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
