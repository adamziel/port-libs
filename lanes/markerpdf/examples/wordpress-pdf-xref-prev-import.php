<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$cmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "2 beginbfchar\n"
    . "<41> <00440072006100660074>\n"
    . "<42> <005000750062006C006900730068006500640020005500700064006100740065>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /IncrementalImportCMap defineresource pop\n"
    . "end\n"
    . "end\n";
$baseContent = 'BT /Fcid 12 Tf 72 720 Td <41> Tj ET';
$currentContent = 'BT /Fcid 12 Tf 72 720 Td <42> Tj ET';

$pdf = "%PDF-1.4\n";
$offsets = [];
$appendObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$appendObject(1, '<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>');
$appendObject(2, '<< /Type /Font /Subtype /Type0 /BaseFont /IncrementalSubset /Encoding /Identity-H /ToUnicode 3 0 R >>');
$appendObject(3, "<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream");
$appendObject(4, "<< /Length " . strlen($baseContent) . " >>\nstream\n{$baseContent}\nendstream");

$baseXrefOffset = strlen($pdf);
$pdf .= "xref\n0 5\n0000000000 65535 f \n";
for ($objectNumber = 1; $objectNumber <= 4; $objectNumber++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
}
$pdf .= "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{$baseXrefOffset}\n%%EOF\n";

$currentContentOffset = strlen($pdf);
$pdf .= "4 0 obj\n<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream\nendobj\n";
$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n4 1\n" . sprintf("%010d 00000 n \n", $currentContentOffset)
    . "trailer\n<< /Size 5 /Root 1 0 R /Prev {$baseXrefOffset} >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo "<!-- markerpdf-xref-prev-smoke " . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'classic xref Prev-chain incremental update object resolution before Gutenberg paragraph rendering',
    'superseded_text_removed' => !in_array('Draft', $lines, true),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
