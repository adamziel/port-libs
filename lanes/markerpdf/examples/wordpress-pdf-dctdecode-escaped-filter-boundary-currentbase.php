<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before escaped DCT filter) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After escaped DCT filter) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress escaped DCT payload leak) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0escaped DCT bytes\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\xff\xd9";
$fakeTerminatorOffset = strpos($jpegPayload, "\nendstream\n");
if ($fakeTerminatorOffset === false) {
    throw new RuntimeException('Escaped DCT WordPress smoke must expose a fake endstream marker.');
}

$imageDictionary = '/Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK '
    . '/BitsPerComponent 8 /Fil#74er /DCT#44ecode '
    . '/Decode#50arms << /Color#54ransform 1 >> /Len#67th ' . $fakeTerminatorOffset;
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /EscapedDct Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /EscapedDct 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< {$imageDictionary} >>\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererObjects = [
    30 => "<< /N 4 /Alternate /DeviceCMYK /Length 7 >>\nstream\nPROFILE\nendstream",
];
$rendererImageDictionary = str_replace('/ColorSpace /DeviceCMYK', '/ColorSpace [/ICCBased 30 0 R]', $imageDictionary);
$rendererImage = "<< {$rendererImageDictionary} >>\nstream\n{$jpegPayload}\nendstream";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$plan = $renderer->imageColorSpaceSoftMaskPlan("<< {$imageDictionary} >>");
$colorPlan = $renderer->dctDecodeImageColorPlan("<< {$imageDictionary} >>", $jpegPayload);
$streamPreview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);

$expectedLines = ['Before escaped DCT filter', 'After escaped DCT filter'];
$payloadExcluded = !str_contains($plainText, 'WordPress escaped DCT payload leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'DCT#44ecode')
    && !str_contains($plainText, 'Fil#74er');
$xobjectRecovered = ($entry['raw_length'] ?? null) === strlen($jpegPayload)
    && (($entry['raw_length'] ?? 0) > $fakeTerminatorOffset);
$rendererRecovered = ($streamPreview['image_stream']['raw_length'] ?? null) === strlen($jpegPayload)
    && (($streamPreview['image_stream']['raw_length'] ?? 0) > $fakeTerminatorOffset);

if (
    $lines !== $expectedLines
    || !$payloadExcluded
    || !$xobjectRecovered
    || !$rendererRecovered
    || ($plan['image_filters'] ?? []) !== ['DCTDecode']
    || ($plan['image_filter_boundary']['preview_only_filters'] ?? []) !== ['DCTDecode']
    || (($plan['image_filter_details'][0]['decode_parms']['color_transform'] ?? null) !== 1)
    || (($colorPlan['decode_parms_color_transform'] ?? null) !== 1)
    || (($colorPlan['uses_ycck_transform'] ?? false) !== true)
    || (($entry['filters'] ?? []) !== ['DCTDecode'])
    || (($entry['filter_details'][0]['decode_parms']['color_transform'] ?? null) !== 1)
    || (($streamPreview['image_stream']['filters'] ?? []) !== ['DCTDecode'])
    || (($streamPreview['image_stream']['preview_only_filters'] ?? []) !== ['DCTDecode'])
) {
    throw new RuntimeException('Escaped DCTDecode filter boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-dctdecode-escaped-filter-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-escaped-filter-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image review-only DCT image handoff',
    'paragraphs' => $lines,
    'escaped_filter_key_decoded' => ($plan['image_filters'] ?? []) === ['DCTDecode'],
    'escaped_dct_filter_name_decoded' => ($entry['filters'] ?? []) === ['DCTDecode'],
    'escaped_decodeparms_key_decoded' => (($entry['filter_details'][0]['decode_parms']['color_transform'] ?? null) === 1),
    'xobject_raw_length_recovered' => $xobjectRecovered,
    'renderer_raw_length_recovered' => $rendererRecovered,
    'dctdecode_payload_excluded_from_text' => $payloadExcluded,
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'uses_ycck_transform' => $colorPlan['uses_ycck_transform'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
