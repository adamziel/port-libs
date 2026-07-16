<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$contentPrefix = "% Stored Flate bytes deliberately contain stream-owner decoys.\n"
    . "endstream\nendobj\n"
    . "20 0 obj\n/ASCIIHexDecode\nendobj\n"
    . "21 0 obj\n<< /Predictor /Twelve /Columns 1 >>\nendobj\n";
$visibleContent = 'BT /F1 12 Tf 72 720 Td (Indirect Filter Current Owner) Tj T* '
    . '(DecodeParms Current Helper) Tj T* '
    . '(Fake Stream Objects Excluded) Tj ET';
$compressed = gzcompress($contentPrefix . $visibleContent, 0);
if (!is_string($compressed) || !str_contains($compressed, "\nendstream\nendobj\n20 0 obj")) {
    throw new RuntimeException('Unable to build stored Flate owner-boundary smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset ?? 0,
    $generation,
    $state
);

$selectedOffsets = [
    1 => $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>'),
    2 => $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>'),
    3 => $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>'),
    20 => $addObject(20, 0, '/FlateDecode'),
    21 => $addObject(21, 0, '<< /Predictor 1 >>'),
];
$selectedOffsets[4] = $addObject(4, 0, "<< /Filter 20 0 R /DecodeParms 21 0 R >>\nstream\n{$compressed}\nendstream");
$selectedOffsets[5] = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

$addObject(20, 0, '/ASCIIHexDecode');
$addObject(21, 0, '<< /Predictor /Twelve /Columns 1 >>');

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 22\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 21; $objectNumber++) {
    $pdf .= isset($selectedOffsets[$objectNumber])
        ? $xrefRow($selectedOffsets[$objectNumber])
        : $xrefRow(0, 65535, 'f');
}
$pdf .= "trailer\n<< /Size 22 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf-parser-indirect-filter-decodeparms-owner-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF direct-object scanning resolves current indirect Filter and DecodeParms helpers before accepting endstream-looking owner bytes',
    'uses_current_indirect_filter' => str_contains($plainText, 'Indirect Filter Current Owner'),
    'uses_current_indirect_decodeparms' => str_contains($plainText, 'DecodeParms Current Helper'),
    'fake_stream_owned_helper_excluded' => !str_contains($plainText, 'ASCIIHexDecode')
        && !str_contains($plainText, 'Twelve')
        && !str_contains($plainText, '20 0 obj')
        && !str_contains($plainText, 'endstream'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
    'paragraphs' => $lines,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
