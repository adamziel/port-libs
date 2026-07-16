<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('CMap stale-length smoke payload must fit one stored deflate block.');
    }

    $s1 = 1;
    $s2 = 0;
    for ($index = 0; $index < $length; $index++) {
        $s1 = ($s1 + ord($bytes[$index])) % 65521;
        $s2 = ($s2 + $s1) % 65521;
    }

    return "\x78\x01"
        . "\x01"
        . pack('v', $length)
        . pack('v', (~$length) & 0xffff)
        . $bytes
        . pack('N', ($s2 << 16) | $s1);
};

$utf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$mappedText = 'Recovered Null Length CMap Import';
$fakeObjectText = 'Null Filter Length Fake Object Leak';
$cMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /NullFilterLengthBoundary-H def\n"
    . "1 begincodespacerange\n"
    . "<0001> <0001>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<0001> <" . $utf16beHex($mappedText) . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n"
    . "endstream\nendobj\n"
    . "90 0 obj\n<< /Length " . strlen($fakeObjectText) . " >>\nstream\n"
    . "BT /Fcid 12 Tf 72 650 Td ({$fakeObjectText}) Tj ET\n"
    . "endstream\nendobj\n";
$compressedCMap = $zlibStored($cMap);
$fakeTerminatorOffset = strpos($compressedCMap, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('CMap stale-length smoke must expose a fake raw endstream marker.');
}

$content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NullFilterLengthBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /NullFilterLengthBoundary-H /Filter [ null /FlateDecode ] /DecodeParms [ 99 0 R << /Predictor 1 >> ] /Length {$fakeTerminatorOffset} >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

$payloadExcluded = !str_contains($plainText, $fakeObjectText)
    && !str_contains($plainText, '90 0 obj')
    && !str_contains($plainText, 'endstream')
    && !str_contains($plainText, '99 0 R');
$boundaryRecovered = $lines === [$mappedText]
    && ($review['decoded_cmap_count'] ?? null) === 1
    && ($entry['declared_length'] ?? null) === $fakeTerminatorOffset
    && ($entry['decoded_with_current_operands'] ?? null) === true
    && ($entry['post_endcmap_bytes_excluded'] ?? null) === true
    && ($entry['parser_bounded_cmap_bytes_excluded'] ?? null) === true
    && ($review['invalid_decodeparms_operand_count'] ?? null) === 0
    && ($review['invalid_decodeparms_parameter_count'] ?? null) === 0;

if (!$payloadExcluded || !$boundaryRecovered) {
    throw new RuntimeException('Malformed CMap null-filter stale-length boundary leaked or failed to recover.');
}

echo '<!-- markerpdf:pdf-malformed-cmap-null-filter-length-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-malformed-cmap-null-filter-length-boundary',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks delegated CMap stream decoding',
    'filter_stack' => [null, 'FlateDecode'],
    'decodeparms_null_slot_ignored' => true,
    'declared_length_stopped_at_fake_endstream' => $fakeTerminatorOffset,
    'compressed_cmap_length' => strlen($compressedCMap),
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'decoded_with_current_operands' => $entry['decoded_with_current_operands'] ?? null,
    'post_endcmap_bytes_excluded' => $entry['post_endcmap_bytes_excluded'] ?? null,
    'parser_bounded_cmap_bytes_excluded' => $entry['parser_bounded_cmap_bytes_excluded'] ?? null,
    'fake_stream_owner_excluded_from_text' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
