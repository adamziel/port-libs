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

$leakingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPMalformedFilterBoundary-H def\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "2 beginbfchar\n"
    . "<01> <" . $utf16beHex('Decoded CMap Leak') . ">\n"
    . "<02> <" . $utf16beHex('Dictionary Filter Leak') . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedCMap = gzcompress($leakingCMap, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress CMap filter-boundary fixture.');
}

$safeText = 'Safe Import';
$safeHex = '';
for ($index = 0, $length = strlen($safeText); $index < $length; $index++) {
    $safeHex .= sprintf('%04X', ord($safeText[$index]));
}
$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPMalformedFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /WPMalformedFilterBoundary-H /Filter [ << /Owner (Filter dictionary is not a decoder) /Fake [ /Nested ] >> /FlateDecode ] /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

if ($lines !== ['Safe Import']) {
    throw new RuntimeException('Expected malformed CMap filter fallback text.');
}

if (
    str_contains($plainText, 'Decoded CMap Leak')
    || str_contains($plainText, 'Dictionary Filter Leak')
    || str_contains($plainText, 'Filter dictionary is not a decoder')
) {
    throw new RuntimeException('Expected malformed CMap filter payload to stay excluded.');
}

if (($entry['filter_operand_policy'] ?? null) !== 'reject_dictionary_filter_operands') {
    throw new RuntimeException('Expected malformed CMap filter operand review metadata.');
}

echo '<!-- markerpdf-malformed-cmap-filter-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'malformed ToUnicode CMap Filter array operands fail closed before WordPress text import',
    'fallback_text' => $plainText,
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'invalid_filter_operand_count' => $review['invalid_filter_operand_count'] ?? null,
    'dictionary_filter_operand_count' => $review['dictionary_filter_operand_count'] ?? null,
    'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
    'cmap_name' => $entry['cmap_name'] ?? null,
    'leaking_cmap_text_excluded' => !str_contains($plainText, 'Decoded CMap Leak') && !str_contains($plainText, 'Dictionary Filter Leak'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
