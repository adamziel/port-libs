<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Duplicate CCITT Fax filter example payload must fit one deflate stored block.');
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

$before = 'BT /F1 12 Tf 72 720 Td (Before duplicate CCITT filter) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After duplicate CCITT filter) Tj ET';
$faxPayload = "\x00\x10\x01"
    . 'BT /F1 12 Tf 72 700 Td (Duplicate CCITT filter payload leak) Tj ET';
$encodedPayload = $zlibStored($faxPayload);
$pageContent = $before . "\nq 16 0 0 1 72 680 cm /FaxDup Do Q\n" . $after;
$imageDictionary = '<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 '
    . '/Filter /FlateDecode /Filter /CCITTFaxDecode '
    . '/DecodeParms [null << /K -1 /Columns 16 /Rows 0 /BlackIs1 true /EndOfBlock true >>] '
    . '/Length ' . strlen($encodedPayload) . ' >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxDup 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$encodedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererDictionary = '<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 '
    . '/Filter /FlateDecode /Filter /CCITTFaxDecode '
    . '/DecodeParms [null null << /K -1 /Columns 16 /Rows 0 /BlackIs1 true /EndOfBlock true >>] '
    . '/Decode [1 0] /Length ' . strlen($encodedPayload) . ' >>';

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? null;
$plan = $renderer->imageColorSpaceSoftMaskPlan($rendererDictionary);

$payloadExcluded = !str_contains($plainText, 'Duplicate CCITT filter payload leak')
    && !str_contains($plainText, 'endstream')
    && !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', 'Duplicate CCITT filter payload leak');
$duplicateRejected = is_array($entry)
    && ($entry['filters_resolved'] ?? true) === false
    && ($entry['duplicate_filter_declaration_count'] ?? 0) === 1
    && ($entry['filter_operand_policy'] ?? null) === 'reject_duplicate_filter_declarations';
$ccittReviewOnly = is_array($entry)
    && ($entry['preview_only_filters'] ?? []) === ['CCITTFaxDecode']
    && is_array($entry['ccitt_fax_filter_boundary'] ?? null)
    && (($entry['ccitt_fax_filter_boundary']['review_only'] ?? false) === true)
    && (($entry['ccitt_fax_decode_boundary']['effective_decode_parms']['k'] ?? null) === -1);
$rendererReviewOnly = ($plan['image_filter_boundary']['native_raster_decode'] ?? true) === false
    && ($plan['image_filter_boundary']['duplicate_filter_declaration_count'] ?? 0) === 1
    && is_array($plan['ccitt_fax_filter_boundary'] ?? null)
    && in_array('ccitt_fax_duplicate_filter_declarations_fail_closed', $plan['notes'] ?? [], true);

if (
    $lines !== ['Before duplicate CCITT filter', 'After duplicate CCITT filter']
    || !$payloadExcluded
    || !$duplicateRejected
    || !$ccittReviewOnly
    || !$rendererReviewOnly
) {
    throw new RuntimeException('Duplicate CCITT Fax filter boundary leaked image bytes or allowed native raster preview.');
}

echo '<!-- markerpdf:pdf-ccitt-fax-duplicate-filter-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-ccitt-fax-duplicate-filter-boundary',
    'upstream_boundary' => 'PDF stream filters are declared once per stream dictionary; duplicate /Filter declarations are malformed and fail closed',
    'duplicate_filter_declarations_rejected' => $duplicateRejected,
    'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
    'filters_resolved' => $entry['filters_resolved'] ?? null,
    'review_filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'ccitt_fax_filter_review_only' => $ccittReviewOnly,
    'renderer_ccitt_duplicate_filter_note_present' => $rendererReviewOnly,
    'payload_excluded_from_paragraphs' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
