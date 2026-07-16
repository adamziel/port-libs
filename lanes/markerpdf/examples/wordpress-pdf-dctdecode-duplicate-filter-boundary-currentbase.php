<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Duplicate DCTDecode filter example payload must fit one deflate stored block.');
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

$before = 'BT /F1 12 Tf 72 720 Td (Before duplicate DCT filter) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After duplicate DCT filter) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0\x00\x10JFIF\0duplicate-filter review bytes\n"
    . "BT /F1 12 Tf 72 700 Td (Duplicate DCT filter payload leak) Tj ET"
    . "\xff\xd9";
$encodedPayload = $zlibStored($jpegPayload);
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Filter /DCTDecode /Length ' . strlen($encodedPayload) . ' >>';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$encodedPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace [/ICCBased 30 0 R] /BitsPerComponent 8 /Filter /FlateDecode /Filter /DCTDecode /Length ' . strlen($encodedPayload) . ' >>';
$rendererImage = "{$rendererDictionary}\nstream\n{$encodedPayload}\nendstream";
$rendererObjects = [
    30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? null;
$preview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);
$plan = $renderer->imageColorSpaceSoftMaskPlan($rendererDictionary, $rendererObjects);

$payloadExcluded = !str_contains($plainText, 'Duplicate DCT filter payload leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'endstream');
$duplicateRejected = is_array($entry)
    && ($entry['filters_resolved'] ?? true) === false
    && ($entry['duplicate_filter_declaration_count'] ?? 0) === 1
    && ($entry['filter_operand_policy'] ?? null) === 'reject_duplicate_filter_declarations';
$dctReviewOnly = is_array($entry)
    && ($entry['preview_only_filters'] ?? []) === ['DCTDecode']
    && is_array($entry['dctdecode_filter_boundary'] ?? null)
    && (($entry['dctdecode_filter_boundary']['review_only'] ?? false) === true);
$rendererReviewOnly = ($preview['review_only_image_stream'] ?? false) === true
    && ($preview['preview_pixel_count'] ?? 1) === 0
    && ($preview['image_filter_boundary']['duplicate_filter_declaration_count'] ?? 0) === 1
    && is_array($plan['dctdecode_filter_boundary'] ?? null);

if (
    $lines !== ['Before duplicate DCT filter', 'After duplicate DCT filter']
    || !$payloadExcluded
    || !$duplicateRejected
    || !$dctReviewOnly
    || !$rendererReviewOnly
) {
    throw new RuntimeException('Duplicate DCTDecode filter boundary leaked image bytes or allowed native raster preview.');
}

echo '<!-- markerpdf:pdf-dctdecode-duplicate-filter-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-duplicate-filter-boundary',
    'upstream_boundary' => 'PDF stream filters are declared once per stream dictionary; duplicate /Filter declarations are malformed and fail closed',
    'duplicate_filter_declarations_rejected' => $duplicateRejected,
    'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
    'filters_resolved' => $entry['filters_resolved'] ?? null,
    'review_filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'dctdecode_filter_review_only' => $dctReviewOnly,
    'renderer_duplicate_filter_review_only' => $rendererReviewOnly,
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
