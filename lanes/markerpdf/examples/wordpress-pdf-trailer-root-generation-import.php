<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale catalog page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Recovered trailer root page) Tj T* (Generation one catalog) Tj ET';

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 6\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets['1:0'])
    . $xrefRow($offsets['2:0'])
    . $xrefRow($offsets['3:0'])
    . $xrefRow($offsets['4:0'])
    . $xrefRow($offsets['5:0'])
    . "trailer\n<< /Size 14 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(10, 1, '<< /Type /Catalog /Pages 11 1 R >>');
$addObject(11, 1, '<< /Type /Pages /Kids [12 1 R] /Count 1 >>');
$addObject(12, 1, '<< /Type /Page /Parent 11 1 R /Resources << /Font << /F1 4 0 R >> >> /Contents 13 1 R >>');
$addObject(13, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$latestXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "10 4\n"
    . $xrefRow($offsets['10:1'], 1)
    . $xrefRow($offsets['11:1'], 1)
    . $xrefRow($offsets['12:1'], 1)
    . $xrefRow($offsets['13:1'], 1)
    . "trailer\n<< /Size 14 /Root 10 1 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n{$latestXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-trailer-root-generation-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'latest trailer /Root object generation selects the current catalog before Gutenberg paragraph rendering',
    'uses_latest_trailer_root_catalog' => str_contains($plainText, 'Recovered trailer root page'),
    'excludes_stale_catalog_page' => !str_contains($plainText, 'Stale catalog page'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
