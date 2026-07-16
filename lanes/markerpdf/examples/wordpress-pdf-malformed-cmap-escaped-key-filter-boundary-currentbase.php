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

$buildEscapedKeyCMapPdf = static function (
    string $safeText,
    string $mappedText,
    string $cMapName,
    string $baseFont,
    bool $extraFilterAfterLength
) use ($utf16beHex): string {
    $safeHex = $utf16beHex($safeText);
    $sourceCode = $extraFilterAfterLength ? substr($safeHex, 0, 4) : '0001';
    $contentHex = $extraFilterAfterLength ? $safeHex : '0001';
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$sourceCode}> <" . $utf16beHex($mappedText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress escaped-key CMap smoke fixture.');
    }

    $extraFilterOperand = $extraFilterAfterLength ? ' /ASCIIHexDecode' : '';
    $content = "BT /Fcid 12 Tf 72 720 Td <{$contentHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Fil#74er /FlateDecode /Decode#50arms << /Predictor 1 >> /Len#67th "
        . strlen($compressedCMap) . "{$extraFilterOperand} >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$extractor = new PdfTextExtractor();
$validPdf = $buildEscapedKeyCMapPdf(
    'Escaped Key Fallback',
    'Escaped Key CMap Import',
    'WPEscapedKeyFilterBoundary-H',
    'WPEscapedKeyFilterBoundary',
    false
);
$malformedPdf = $buildEscapedKeyCMapPdf(
    'Escaped Key Safe Import',
    'Escaped Key CMap Leak',
    'WPEscapedKeyExtraFilterBoundary-H',
    'WPEscapedKeyExtraFilterBoundary',
    true
);

$validLines = $extractor->extractTextLines($validPdf);
$malformedLines = $extractor->extractTextLines($malformedPdf);
$validReview = $extractor->extractCMapStreamFilterLengthOwnerReview($validPdf);
$malformedReview = $extractor->extractCMapStreamFilterLengthOwnerReview($malformedPdf);
$validEntry = $validReview['entries'][0] ?? [];
$malformedEntry = $malformedReview['entries'][0] ?? [];

if ($validLines !== ['Escaped Key CMap Import']) {
    throw new RuntimeException('Expected escaped CMap stream dictionary keys to decode before WordPress import.');
}

if ($malformedLines !== ['Escaped Key Safe Import']) {
    throw new RuntimeException('Expected escaped-key malformed CMap filter boundary to preserve fallback text.');
}

$visibleText = implode("\n", array_merge($validLines, $malformedLines));
if (
    str_contains($visibleText, 'Escaped Key CMap Leak')
    || str_contains($visibleText, 'WPEscapedKeyExtraFilterBoundary-H')
    || str_contains($visibleText, 'ASCIIHexDecode')
    || str_contains($visibleText, 'Fil#74er')
    || str_contains($visibleText, 'Decode#50arms')
    || str_contains($visibleText, 'Len#67th')
) {
    throw new RuntimeException('Expected escaped CMap dictionary internals to stay out of visible WordPress text.');
}

if (($validReview['escaped_stream_dictionary_key_count'] ?? null) !== 3) {
    throw new RuntimeException('Expected valid escaped-key CMap review to count Filter, DecodeParms, and Length keys.');
}

if (($malformedReview['escaped_stream_dictionary_key_count'] ?? null) !== 3) {
    throw new RuntimeException('Expected malformed escaped-key CMap review to count Filter, DecodeParms, and Length keys.');
}

if (($malformedEntry['filter_operand_policy'] ?? null) !== 'reject_malformed_filter_operands') {
    throw new RuntimeException('Expected malformed escaped-key CMap filter policy to reject the post-Length decoder name.');
}

echo '<!-- markerpdf-malformed-cmap-escaped-key-filter-boundary-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'escaped CMap stream Filter DecodeParms and Length dictionary keys resolve before WordPress text import, while post-Length extra filter operands fail closed',
    'valid_visible_text' => $validLines,
    'malformed_visible_text' => $malformedLines,
    'valid_decoded_cmap_count' => $validReview['decoded_cmap_count'] ?? null,
    'malformed_decoded_cmap_count' => $malformedReview['decoded_cmap_count'] ?? null,
    'valid_escaped_stream_dictionary_key_count' => $validReview['escaped_stream_dictionary_key_count'] ?? null,
    'malformed_escaped_stream_dictionary_key_count' => $malformedReview['escaped_stream_dictionary_key_count'] ?? null,
    'malformed_filter_policy' => $malformedEntry['filter_operand_policy'] ?? null,
    'malformed_extra_filter_name' => $malformedEntry['filter_operands'][0]['extra_filter_name'] ?? null,
    'valid_filters' => $validEntry['filters'] ?? [],
    'malformed_filters' => $malformedEntry['filters'] ?? [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_merge($validLines, $malformedLines) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
