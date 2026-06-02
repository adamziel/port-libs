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

$currentCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPCurrentCMapOwner-H def\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "2 beginbfchar\n"
    . "<01> <" . $utf16beHex('Current CMap Owner') . ">\n"
    . "<02> <" . $utf16beHex('Length Filter Review') . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n"
    . "endstream\nendobj\n"
    . "99 0 obj\n<< /Length 64 >>\nstream\nBT /Fcid 12 Tf 72 620 Td (Fake CMap stream owner leak) Tj ET\nendstream\nendobj\n";
$currentCompressed = gzcompress($currentCMap, 0);
if (!is_string($currentCompressed)) {
    throw new RuntimeException('Unable to build current CMap stream fixture.');
}

$staleCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<01> <" . $utf16beHex('Stale CMap Leak') . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "end\n"
    . "end\n";
$content = 'BT /Fcid 12 Tf 72 720 Td <01> Tj T* <02> Tj ET';

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
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
$addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /WPCurrentCMapOwner /Encoding /Identity-H /DescendantFonts [7 0 R] /ToUnicode 6 1 R >>');
$addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(6, 0, "<< /Type /CMap /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream");
$addObject(7, 0, '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPCurrentCMapOwner /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 >>');
$addObject(20, 0, '/ASCIIHexDecode');
$addObject(21, 0, '1');
$addObject(6, 1, "<< /Type /CMap /CMapName /WPCurrentCMapOwner-H /Filter 20 1 R /Length 21 1 R >>\nstream\n{$currentCompressed}\nendstream");
$addObject(20, 1, '/FlateDecode');
$addObject(21, 1, (string) strlen($currentCompressed));

$selected = [
    1 => ['generation' => 0, 'offset' => $offsets['1:0']],
    2 => ['generation' => 0, 'offset' => $offsets['2:0']],
    3 => ['generation' => 0, 'offset' => $offsets['3:0']],
    4 => ['generation' => 0, 'offset' => $offsets['4:0']],
    5 => ['generation' => 0, 'offset' => $offsets['5:0']],
    6 => ['generation' => 1, 'offset' => $offsets['6:1']],
    7 => ['generation' => 0, 'offset' => $offsets['7:0']],
    20 => ['generation' => 1, 'offset' => $offsets['20:1']],
    21 => ['generation' => 1, 'offset' => $offsets['21:1']],
];

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 22\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 21; $objectNumber++) {
    if (!isset($selected[$objectNumber])) {
        $pdf .= $xrefRow(0, 65535, 'f');
        continue;
    }

    $pdf .= $xrefRow($selected[$objectNumber]['offset'], $selected[$objectNumber]['generation']);
}
$pdf .= "trailer\n<< /Size 22 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];
$encryptedPdf = str_replace(
    "trailer\n<< /Size 22 /Root 1 0 R >>",
    "trailer\n<< /Size 22 /Root 1 0 R /Encrypt 8 0 R >>",
    $pdf
);
$encryptedReview = $extractor->extractCMapStreamFilterLengthOwnerReview($encryptedPdf);

if ($lines !== ['Current CMap Owner', 'Length Filter Review']) {
    throw new RuntimeException('Expected current CMap ToUnicode text lines.');
}

if (
    str_contains($plainText, 'Stale CMap Leak')
    || str_contains($plainText, 'Fake CMap stream owner leak')
    || str_contains($plainText, '99 0 obj')
    || str_contains($plainText, 'ASCIIHexDecode')
) {
    throw new RuntimeException('Expected stale and stream-owned CMap payload text to stay excluded.');
}

if (($review['decoded_cmap_count'] ?? null) !== 1 || ($entry['owner_policy'] ?? null) !== 'xref_selected_indirect_operands') {
    throw new RuntimeException('Expected current xref-selected CMap operand owner review metadata.');
}

if (($encryptedReview['encrypted'] ?? false) !== true || ($encryptedReview['entries'] ?? []) !== []) {
    throw new RuntimeException('Expected encrypted CMap review to fail closed.');
}

echo '<!-- markerpdf-parser-cmap-filter-owner-stream-length-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'filtered ToUnicode CMap streams use current xref-selected Length and Filter owners before WordPress text import',
    'uses_current_cmap_owner_text' => str_contains($plainText, 'Current CMap Owner'),
    'uses_current_length_filter_review_text' => str_contains($plainText, 'Length Filter Review'),
    'stale_cmap_generation_excluded' => !str_contains($plainText, 'Stale CMap Leak'),
    'stream_owned_fake_object_excluded' => !str_contains($plainText, 'Fake CMap stream owner leak') && !str_contains($plainText, '99 0 obj'),
    'stale_filter_helper_excluded' => !str_contains($plainText, 'ASCIIHexDecode'),
    'cmap_owner_policy' => $entry['owner_policy'] ?? null,
    'cmap_name' => $entry['cmap_name'] ?? null,
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'encrypted_review_fails_closed' => ($encryptedReview['encrypted'] ?? false) === true && ($encryptedReview['entries'] ?? []) === [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
