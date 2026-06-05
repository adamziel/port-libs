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

$safeText = 'Reference Extra Safe Import';
$leakingText = 'Reference Extra CMap Leak';
$safeHex = $utf16beHex($safeText);
$sourceCode = substr($safeHex, 0, 4);
$cMapName = 'WPReferenceExtraFilterBoundary-H';
$extraFilterName = 'ASCIIHexDecode';
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
    throw new RuntimeException('Unable to compress reference-extra CMap filter smoke fixture.');
}

$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
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
$addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /WPReferenceExtraFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(6, 0, "<< /Type /CMap /CMapName /{$cMapName} /Filter 7 0 R /{$extraFilterName} /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
$addObject(7, 0, '/FlateDecode');

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 8\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 7; $objectNumber++) {
    $pdf .= $xrefRow($offsets[$objectNumber . ':0']);
}
$pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];
$filterOperand = $entry['filter_operands'][0] ?? [];

$flags = [
    'source' => 'native-pdf-malformed-cmap-reference-extra-filter-currentbase',
    'safe_import_text_preserved' => $lines === [$safeText] && $plainText === $safeText,
    'cmap_payload_excluded' => !str_contains($plainText, $leakingText) && !str_contains($plainText, $cMapName),
    'extra_filter_name_excluded' => !str_contains($plainText, $extraFilterName),
    'indirect_filter_reviewed' => ($entry['indirect_filter_count'] ?? null) === 1
        && ($filterOperand['object_number'] ?? null) === 7
        && ($filterOperand['xref_selected'] ?? null) === true,
    'extra_decoder_rejected' => ($entry['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands'
        && ($filterOperand['extra_filter_name'] ?? null) === $extraFilterName
        && ($filterOperand['valid_filter_operand'] ?? null) === false,
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'executes_python_or_models' => $review['executes_python_or_models'] ?? true,
    'executes_external_pdf_tools' => $review['executes_external_pdf_tools'] ?? true,
];

foreach ([
    'safe_import_text_preserved',
    'cmap_payload_excluded',
    'extra_filter_name_excluded',
    'indirect_filter_reviewed',
    'extra_decoder_rejected',
] as $requiredFlag) {
    if (($flags[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Expected malformed reference-extra CMap filter smoke flag to pass: ' . $requiredFlag);
    }
}

if (($flags['decoded_cmap_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected malformed reference-extra CMap filter stream to remain undecoded.');
}

echo '<!-- markerpdf:pdf-malformed-cmap-reference-extra-filter-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<p>Reference-extra CMap filter operands are rejected before WordPress PDF text import.</p>\n";
