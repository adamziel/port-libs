<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current owner boundary page) Tj T* (Xref offset owner kept) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale owner xref page) Tj T* (Stream-owned xref leak) Tj ET';

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$addObject(9, 0, '<< /Type /Catalog /Pages 10 0 R >>');
$addObject(10, 0, '<< /Type /Pages /Kids [11 0 R] /Count 1 >>');
$addObject(11, 0, '<< /Type /Page /Parent 10 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 12 0 R >>');
$addObject(12, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$fakeXref = "xref\n"
    . "0 13\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow(0, 1, 'f')
    . $xrefRow(0, 1, 'f')
    . $xrefRow($offsets['3:0'])
    . $xrefRow(0, 1, 'f')
    . $xrefRow(0, 1, 'f')
    . $xrefRow(0, 1, 'f')
    . $xrefRow(0, 1, 'f')
    . $xrefRow(0, 1, 'f')
    . $xrefRow($offsets['9:0'])
    . $xrefRow($offsets['10:0'])
    . $xrefRow($offsets['11:0'])
    . $xrefRow($offsets['12:0'])
    . "trailer\n<< /Size 13 /Root 9 0 R >>\n";
$fakeOwnerPrefix = "8 0 obj\n<< /Length " . strlen($fakeXref) . " >>\nstream\n";
$fakeXrefOffset = strlen($pdf) + strlen($fakeOwnerPrefix);
$offsets['8:0'] = strlen($pdf);
$pdf .= $fakeOwnerPrefix . $fakeXref . "endstream\nendobj\n";

$pdf .= "xref\n"
    . "0 13\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets['1:0'])
    . $xrefRow($offsets['2:0'])
    . $xrefRow($offsets['3:0'])
    . $xrefRow($offsets['4:0'])
    . $xrefRow($offsets['5:0'])
    . $xrefRow(0, 1, 'f')
    . $xrefRow(0, 1, 'f')
    . $xrefRow($offsets['8:0'])
    . $xrefRow(0, 1, 'f')
    . $xrefRow(0, 1, 'f')
    . $xrefRow(0, 1, 'f')
    . $xrefRow(0, 1, 'f')
    . "trailer\n<< /Size 13 /Root 1 0 R >>\n"
    . "startxref\n{$fakeXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-parser-xref-offset-owner-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-table offsets must not be accepted from inside another direct object stream body',
    'uses_current_owner_boundary_page' => str_contains($plainText, 'Current owner boundary page'),
    'keeps_xref_offset_owner' => str_contains($plainText, 'Xref offset owner kept'),
    'excluded_stream_owned_xref_page' => !str_contains($plainText, 'Stale owner xref page'),
    'excluded_stream_owned_xref_text' => !str_contains($plainText, 'Stream-owned xref leak'),
    'excluded_raw_xref_payload' => !str_contains($plainText, 'xref'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
