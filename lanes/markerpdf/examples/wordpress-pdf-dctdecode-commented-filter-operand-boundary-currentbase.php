<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before commented DCT filter operand) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After commented DCT filter operand) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress commented DCT filter operand leak) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0commented filter operand bytes\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\xff\xd9";
$fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('Commented DCT filter operand smoke must expose a fake endstream marker.');
}

$imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 '
    . "/Filter /DCTDecode % malformed filter tail follows a PDF comment\n"
    . "/Crypt null /DecodeParms << /ColorTransform 1 >> /Length {$fakeTerminatorOffset} >>";
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /CommentedDct Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /CommentedDct 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererDictionary = str_replace('/ColorSpace /DeviceRGB', '/ColorSpace [/ICCBased 30 0 R]', $imageDictionary);
$rendererImage = "{$rendererDictionary}\nstream\n{$jpegPayload}\nendstream";
$rendererObjects = [
    30 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary);
$streamPreview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);

$expectedLines = ['Before commented DCT filter operand', 'After commented DCT filter operand'];
$payloadExcluded = !str_contains($plainText, 'WordPress commented DCT filter operand leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'malformed filter tail follows')
    && !str_contains($plainText, 'Crypt');
$xobjectRecovered = ($entry['raw_length'] ?? null) === strlen($jpegPayload)
    && (($entry['raw_length'] ?? 0) > $fakeTerminatorOffset);
$rendererRecovered = ($streamPreview['image_stream']['raw_length'] ?? null) === strlen($jpegPayload)
    && (($streamPreview['image_stream']['raw_length'] ?? 0) > $fakeTerminatorOffset);
$commentedOperandPreserved = ($entry['extra_filter_operand_after_comment'] ?? false) === true
    && ($plan['image_filter_boundary']['extra_filter_operand_after_comment'] ?? false) === true
    && ($streamPreview['image_filter_boundary']['extra_filter_operand_after_comment'] ?? false) === true;

if (
    $lines !== $expectedLines
    || !$payloadExcluded
    || !$xobjectRecovered
    || !$rendererRecovered
    || !$commentedOperandPreserved
    || (($entry['filters'] ?? []) !== ['MalformedFilterOperand', 'DCTDecode'])
    || (($plan['image_filters'] ?? []) !== ['MalformedFilterOperand', 'DCTDecode'])
    || (($entry['filter_operand_policy'] ?? null) !== 'reject_malformed_filter_operands')
    || (($plan['image_filter_boundary']['filter_operand_policy'] ?? null) !== 'reject_malformed_filter_operands')
    || (($streamPreview['image_stream']['preview_only_filters'] ?? []) !== ['DCTDecode'])
) {
    throw new RuntimeException('Commented DCTDecode filter operand boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-dctdecode-commented-filter-operand-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-commented-filter-operand-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image review-only DCT image handoff',
    'paragraphs' => $lines,
    'stream_filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
    'extra_filter_operand_after_comment' => $entry['extra_filter_operand_after_comment'] ?? false,
    'xobject_raw_length_recovered' => $xobjectRecovered,
    'renderer_raw_length_recovered' => $rendererRecovered,
    'dctdecode_payload_excluded_from_text' => $payloadExcluded,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
