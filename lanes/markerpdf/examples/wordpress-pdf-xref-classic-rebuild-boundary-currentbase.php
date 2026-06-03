<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/PdfTextExtractor.php';

use PortLibs\MarkerPDF\PdfTextExtractor;

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale classic rebuild page) Tj T* (Old trailer root leak) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current classic rebuild page) Tj T* (Latest trailer boundary kept) Tj ET';

$pdf = "%PDF-1.4\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber] = $offset;
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 6\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow($offsets[2])
    . $xrefRow($offsets[3])
    . $xrefRow($offsets[4])
    . $xrefRow($offsets[5])
    . "trailer\n<< /Size 15 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(10, '<< /Type /Catalog /Pages 11 0 R >>');
$addObject(11, '<< /Type /Pages /Kids [12 0 R] /Count 1 >>');
$addObject(12, '<< /Type /Page /Parent 11 0 R /Resources << /Font << /F1 13 0 R >> >> /Contents 14 0 R >>');
$addObject(13, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(14, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$pdf .= "xref\n"
    . "10 5\n"
    . $xrefRow($offsets[10])
    . $xrefRow($offsets[11])
    . $xrefRow($offsets[12])
    . $xrefRow($offsets[13])
    . $xrefRow($offsets[14])
    . "trailer\n<< /Size 15 /Root 10 0 R >>\n"
    . "startxref\n999999\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$paragraphs = array_filter(array_map('trim', explode("\n", $plainText)));

echo '<!-- markerpdf-xref-classic-rebuild-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'native_boundary' => 'damaged startxref recovers the latest valid classic xref trailer root before text extraction',
    'uses_latest_classic_trailer_root' => str_contains($plainText, 'Current classic rebuild page'),
    'keeps_latest_trailer_boundary' => str_contains($plainText, 'Latest trailer boundary kept'),
    'excludes_stale_classic_rebuild_page' => !str_contains($plainText, 'Stale classic rebuild page'),
    'excludes_old_trailer_root_leak' => !str_contains($plainText, 'Old trailer root leak'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($paragraphs as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
