<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/PdfTextExtractor.php';

use PortLibs\MarkerPDF\PdfTextExtractor;

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale currentbase repair page) Tj T* (Prev offset leak) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current xref repair page) Tj T* (Current base repair boundary) Tj ET';

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
    . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
$addObject(2, 1, '<< /Type /Pages /Kids [4 1 R] /Count 1 >>');
$addObject(4, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 1 R >>');
$addObject(9, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 1\n" . $xrefRow(0, 65535, 'f')
    . "1 2\n" . $xrefRow($offsets['1:1'], 1) . $xrefRow($offsets['2:1'], 1)
    . "4 1\n" . $xrefRow(0, 1)
    . "9 1\n" . $xrefRow(0, 1)
    . "trailer\n<< /Size 10 /Root 1 1 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$paragraphs = array_filter(array_map('trim', explode("\n", $plainText)));

echo "<!-- markerpdf-xref-currentbase-repair-boundary-smoke " . htmlspecialchars(json_encode([
    'native_boundary' => 'PDF xref current-base direct-generation repair for invalid in-use offsets',
    'uses_current_xref_repair_page' => str_contains($plainText, 'Current xref repair page'),
    'uses_current_base_repair_boundary' => str_contains($plainText, 'Current base repair boundary'),
    'excluded_stale_prev_page' => !str_contains($plainText, 'Stale currentbase repair page'),
    'excluded_prev_offset_leak' => !str_contains($plainText, 'Prev offset leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($paragraphs as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
