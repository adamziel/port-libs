<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$hintContent = 'BT /F1 12 Tf 72 720 Td (Linearized indirect hint object leak) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Incremental current page) Tj T* (Hint object boundary) Tj ET';

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, 0, '<< /Linearized 1 /L LLLLLLLLLL /H [20 0 R 21 0 R] /O 5 /E EEEEEEEEEE /N 1 /T TTTTTTTTTT >>');
$addObject(2, 0, "<< /Length " . strlen($hintContent) . " >>\nstream\n{$hintContent}\nendstream");
$addObject(20, 0, 'HHHHHHHHHA');
$addObject(21, 0, 'HHHHHHHHHB');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 3\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow($offsets[2])
    . "20 2\n"
    . $xrefRow($offsets[20])
    . $xrefRow($offsets[21])
    . "trailer\n<< /Size 22 >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(3, 0, '<< /Type /Catalog /Pages 4 0 R >>');
$addObject(4, 0, '<< /Type /Pages /Kids [5 0 R] /Count 1 >>');
$addObject(5, 0, '<< /Type /Page /Parent 4 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents [2 0 R 6 0 R] >>');
$addObject(6, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(7, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

$latestXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "3 5\n"
    . $xrefRow($offsets[3])
    . $xrefRow($offsets[4])
    . $xrefRow($offsets[5])
    . $xrefRow($offsets[6])
    . $xrefRow($offsets[7])
    . "trailer\n<< /Size 22 /Root 3 0 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n{$latestXrefOffset}\n%%EOF";

$pdf = strtr($pdf, [
    'LLLLLLLLLL' => sprintf('%010d', strlen($pdf)),
    'HHHHHHHHHA' => sprintf('%010d', $offsets[2]),
    'HHHHHHHHHB' => sprintf('%010d', $offsets[20] - $offsets[2]),
    'EEEEEEEEEE' => sprintf('%010d', $offsets[20]),
    'TTTTTTTTTT' => sprintf('%010d', $latestXrefOffset),
]);

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-linearized-incremental-hint-object-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'linearized PDF indirect /H byte ranges remove hint stream objects before current incremental page extraction',
    'uses_incremental_current_xref' => str_contains($plainText, 'Incremental current page'),
    'resolves_indirect_h_array_operands' => true,
    'excludes_hint_object_text' => !str_contains($plainText, 'Linearized indirect hint object leak'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
