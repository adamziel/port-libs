<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$hintContent = 'BT /F1 12 Tf 72 720 Td (Linearized hint stale leak) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Linearized current fallback) Tj T* (Hint table boundary) Tj ET';

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, 0, '<< /Linearized 1 /L LLLLLLLLLL /H [ HHHHHHHHHA HHHHHHHHHB ] /O 4 /E EEEEEEEEEE /N 1 /T TTTTTTTTTT >>');
$addObject(2, 0, "<< /Length " . strlen($hintContent) . " >>\nstream\n{$hintContent}\nendstream");
$addObject(3, 0, '<< /Type /Catalog /NeedsRendering false >>');
$addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$xrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 5\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1])
    . $xrefRow($offsets[2])
    . $xrefRow($offsets[3])
    . $xrefRow($offsets[4])
    . "trailer\n<< /Size 5 /Root 3 0 R >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$pdf = strtr($pdf, [
    'LLLLLLLLLL' => sprintf('%010d', strlen($pdf)),
    'HHHHHHHHHA' => sprintf('%010d', $offsets[2]),
    'HHHHHHHHHB' => sprintf('%010d', $offsets[3] - $offsets[2]),
    'EEEEEEEEEE' => sprintf('%010d', $offsets[3]),
    'TTTTTTTTTT' => sprintf('%010d', $xrefOffset),
]);

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-linearized-hint-table-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'linearized PDF /H hint-table byte ranges are skipped before native stream fallback',
    'uses_current_fallback_stream' => str_contains($plainText, 'Linearized current fallback'),
    'excludes_hint_table_text' => !str_contains($plainText, 'Linearized hint stale leak'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
