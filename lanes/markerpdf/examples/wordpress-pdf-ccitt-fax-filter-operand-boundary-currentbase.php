<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('CCITT Fax filter operand example payload must fit one deflate stored block.');
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

$before = 'BT /F1 12 Tf 72 720 Td (Before CCITT filter operand import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After CCITT filter operand import) Tj ET';
$unresolvedPayload = 'BT /F1 12 Tf 72 700 Td (Unresolved CCITT filter operand leak) Tj ET';
$malformedPayload = "\x00\x10\x01"
    . 'BT /F1 12 Tf 72 700 Td (Malformed CCITT filter operand leak) Tj ET';
$encodedMalformedPayload = $zlibStored($malformedPayload);
$pageContent = $before . "\n"
    . "q 16 0 0 1 72 700 cm /BadRef Do Q\n"
    . "q 16 0 0 1 72 690 cm /BadArray Do Q\n"
    . $after;

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /BadRef 5 0 R /BadArray 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter 99 0 R /Length " . strlen($unresolvedPayload) . " >>\nstream\n{$unresolvedPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 "
    . "/Filter [/FlateDecode 42 /CCITTFaxDecode] "
    . "/DecodeParms [null null << /K -1 /Columns 16 /Rows 0 /BlackIs1 true /EndOfBlock true >>] "
    . "/Length " . strlen($encodedMalformedPayload) . " >>\nstream\n{$encodedMalformedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entries = [];
foreach ($review['entries'] as $entry) {
    if (isset($entry['resource_name']) && is_string($entry['resource_name'])) {
        $entries[$entry['resource_name']] = $entry;
    }
}
$unresolvedEntry = $entries['BadRef'] ?? [];
$malformedEntry = $entries['BadArray'] ?? [];

$unresolvedRejected = ($unresolvedEntry['filters_resolved'] ?? true) === false
    && ($unresolvedEntry['filters'] ?? []) === ['UnresolvedFilterOperand']
    && ($unresolvedEntry['filter_operand_policy'] ?? null) === 'reject_unresolved_filter_operands';
$malformedRejected = ($malformedEntry['filters_resolved'] ?? true) === false
    && ($malformedEntry['filters'] ?? []) === ['FlateDecode', 'MalformedFilterOperand', 'CCITTFaxDecode']
    && ($malformedEntry['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands'
    && ($malformedEntry['preview_only_filters'] ?? []) === ['CCITTFaxDecode']
    && is_array($malformedEntry['ccitt_fax_filter_boundary'] ?? null)
    && (($malformedEntry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null) === true);
$payloadExcluded = !str_contains($plainText, 'Unresolved CCITT filter operand leak')
    && !str_contains($plainText, 'Malformed CCITT filter operand leak')
    && !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', 'Unresolved CCITT filter operand leak')
    && !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', 'Malformed CCITT filter operand leak');

if (
    $lines !== ['Before CCITT filter operand import', 'After CCITT filter operand import']
    || !$unresolvedRejected
    || !$malformedRejected
    || !$payloadExcluded
) {
    throw new RuntimeException('CCITT Fax filter operand boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-filter-operand-boundary-currentbase',
    'upstream_boundary' => 'PDF image stream /Filter operands must be names, nulls, arrays, or resolvable references; malformed or unresolved operands fail closed before raster decode',
    'unresolved_filter_operand_rejected' => $unresolvedRejected,
    'malformed_filter_operand_rejected' => $malformedRejected,
    'unresolved_filter_operand_policy' => $unresolvedEntry['filter_operand_policy'] ?? null,
    'malformed_filter_operand_policy' => $malformedEntry['filter_operand_policy'] ?? null,
    'malformed_review_filters' => $malformedEntry['filters'] ?? [],
    'ccitt_preview_only_filters' => $malformedEntry['preview_only_filters'] ?? [],
    'payload_excluded_from_paragraphs' => $payloadExcluded,
    'decoded_with_current_filters' => $malformedEntry['decoded_with_current_filters'] ?? null,
    'native_raster_decode' => $malformedEntry['native_raster_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-filter-operand-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
