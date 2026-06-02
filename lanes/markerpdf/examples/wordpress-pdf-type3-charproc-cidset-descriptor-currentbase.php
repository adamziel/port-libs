<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "1000 0 d0\n";
$thinCharProc = "250 0 0 0 250 700 d1\n";
$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "16 beginbfchar\n"
    . "<41> <0057>\n"
    . "<42> <0069>\n"
    . "<43> <0064>\n"
    . "<44> <0065>\n"
    . "<4A> <004A>\n"
    . "<4B> <006F>\n"
    . "<4C> <0069>\n"
    . "<4D> <006E>\n"
    . "<54> <0054>\n"
    . "<55> <0068>\n"
    . "<56> <0069>\n"
    . "<57> <006E>\n"
    . "<5A> <004D>\n"
    . "<5B> <0069>\n"
    . "<5C> <0073>\n"
    . "<5D> <0073>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <5A5B5C5D> Tj '
    . '1 0 0 1 118 720 Tm <41424344> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 96 704 Tm <4A4B4C4D> Tj ET';
$cidSet = gzcompress('cidset descriptor payload must stay review-only');
if (!is_string($cidSet)) {
    throw new RuntimeException('Unable to compress focused Type3 CIDSet descriptor fixture.');
}
$flags = (1 << 1) | (1 << 5) | (1 << 18);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 25 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3DescriptorSubset /BaseFont /T3DescriptorSubset /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 65 /LastChar 68 /Widths 22 0 R /Encoding 19 0 R /CharProcs 21 0 R /FontDescriptor 23 0 R /ToUnicode 20 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
    . "19 0 obj\n<< /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [65 /W.wide /i.wide /d.wide /e.wide 74 /J.thin /o.thin /i.thin /n.thin 84 /T.thin /h.thin /i.thin /n.thin 90 /M.missing /i.missing /s.missing /s.missing] >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "21 0 obj\n<< /W.wide 3 0 R /i.wide 3 0 R /d.wide 3 0 R /e.wide 3 0 R /J.thin 4 0 R /o.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R /T.thin 4 0 R /h.thin 4 0 R >>\nendobj\n"
    . "22 0 obj\n[1000 1000 1000 1000]\nendobj\n"
    . "23 0 obj\n<< /Type /FontDescriptor /FontName /T3DescriptorBoldSerif /Flags {$flags} /FontWeight 700 /MissingWidth 24 0 R /CIDSet 26 0 R >>\nendobj\n"
    . "24 0 obj\n1000\nendobj\n"
    . "25 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "26 0 obj\n<< /Filter /FlateDecode /Length " . strlen($cidSet) . " >>\nstream\n{$cidSet}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$pages = $extractor->extractStyledTextPages($pdf);
$firstSpan = $pages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];

echo '<!-- markerpdf:pdf-type3-charproc-cidset-descriptor-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charproc-fontdescriptor-missingwidth-boundary',
    'font_width_sources' => [
        'Type3 /CharProcs d0 widths',
        'Type3 /CharProcs d1 widths',
        'FontDescriptor indirect /MissingWidth',
    ],
    'descriptor_flags_preserved' => ($firstSpan['font_flags'] ?? null) === $flags,
    'descriptor_weight_preserved' => ($firstSpan['font_weight'] ?? null) === 700.0,
    'missing_width_gap_preserved' => str_contains($plainText, 'MissWide') && !str_contains($plainText, 'Miss Wide'),
    'charproc_width_gap_preserved' => str_contains($plainText, 'Thin Join') && !str_contains($plainText, 'ThinJoin'),
    'cidset_payload_visible_text_excluded' => !str_contains($plainText, 'cidset descriptor payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
