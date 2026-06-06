<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$utf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$safeText = 'WordPress Indirect Array Tail Safe Import';
$leakingText = 'WordPress Indirect Array Tail CMap Leak';
$cMapName = 'WPIndirectArrayTailFilterBoundary-H';
$extraFilterName = 'ASCIIHexDecode';
$safeHex = $utf16beHex($safeText);
$sourceCode = substr($safeHex, 0, 4);
$cMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /{$cMapName} def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<{$sourceCode}> <" . $utf16beHex($leakingText) . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedCMap = gzcompress($cMap, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress WordPress indirect-array-tail CMap fixture.');
}

$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset ?? 0,
    $generation,
    $state
);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /WPIndirectArrayTailFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(6, 0, "<< /Type /CMap /CMapName /{$cMapName} /Filter 7 0 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
$addObject(7, 0, "[ /FlateDecode ] /{$extraFilterName}");

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 8\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 7; $objectNumber++) {
    $pdf .= $xrefRow($offsets[$objectNumber] ?? null);
}
$pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];
$filterOperand = $entry['filter_operands'][0] ?? [];

echo "markerpdf-cmap-indirect-filter-array-tail-currentbase-smoke\n";
echo 'safe_text_preserved=' . ($plainText === $safeText ? 'yes' : 'no') . "\n";
echo 'leaking_cmap_text_excluded=' . (!str_contains($plainText, $leakingText) ? 'yes' : 'no') . "\n";
echo 'indirect_array_tail_rejected=' . (($entry['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands' ? 'yes' : 'no') . "\n";
echo 'extra_filter_operand_recorded=' . (($filterOperand['extra_filter_operand'] ?? false) === true ? 'yes' : 'no') . "\n";
echo 'extra_filter_name=' . ($filterOperand['extra_filter_name'] ?? '') . "\n";
echo 'decoded_cmap_count=' . ($review['decoded_cmap_count'] ?? 0) . "\n";
echo 'executes_python_or_models=' . (($review['executes_python_or_models'] ?? true) ? 'true' : 'false') . "\n";
echo 'executes_external_pdf_tools=' . (($review['executes_external_pdf_tools'] ?? true) ? 'true' : 'false') . "\n";
