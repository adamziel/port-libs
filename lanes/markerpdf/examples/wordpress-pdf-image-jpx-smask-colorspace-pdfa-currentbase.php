<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$profileBytes = 'Current WordPress JPX PDF/A OutputIntent RGB profile';
$compressedProfile = gzcompress($profileBytes);
if (!is_string($compressedProfile)) {
    throw new RuntimeException('Unable to compress JPX PDF/A smoke profile.');
}

$jpxPayload = "\xff\x4fWordPress JPX PDF/A image bytes stay review-only\xff\xd9";
$content = 'BT /F1 12 Tf 72 720 Td (Current WordPress JPX PDF/A body) Tj ET q /ImJPX Do Q';
$imageObject = "<< /Subtype /Image /Filter /JPXDecode /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMaskInData 1 /Length " . strlen($jpxPayload) . " >>\nstream\n{$jpxPayload}\nendstream";
$objects = [
    6 => $imageObject,
    7 => "<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedProfile) . " >>\nstream\n{$compressedProfile}\nendstream",
    9 => '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current WordPress JPX PDF/A RGB) /Info (Current WordPress JPX RGB profile) /DestOutputProfile 7 0 R >>',
];

$pdf = "%PDF-2.0\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /OutputIntents [9 0 R] >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /XObject << /ImJPX 6 0 R >> >> /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(6, $objects[6]);
$addObject(7, $objects[7]);
$addObject(9, $objects[9]);
$addObject(60, '<< /Title (Current WordPress JPX PDF/A Title) /Producer (Current JPX PDF/A Producer) >>');

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 91; $objectNumber++) {
    if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 90)) {
        $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
        continue;
    }

    $rows .= pack('CNn', 1, $objectNumber === 90 ? $xrefOffset : $offsets[$objectNumber], 0);
}
$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress JPX PDF/A smoke xref stream.');
}

$pdf .= "90 0 obj\n"
    . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 60 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OutputIntents [19 0 R] >>\nendobj\n"
    . "19 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Stale WordPress JPX PDF/A) /DestOutputProfile 7 0 R >>\nendobj\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$plan = (new PdfImageRenderer())->jpxSoftMaskColorSpacePdfaReviewPlan($imageObject, $objects, $metadata);

if (($plan['color_management']['profile_source'] ?? null) !== 'pdfa_output_intent') {
    throw new RuntimeException('Expected PDF/A OutputIntent to provide DeviceRGB JPX preview context.');
}
if (($plan['jpx_soft_mask_in_data']['uses_embedded_soft_mask'] ?? false) !== true || ($plan['review_only_image_stream'] ?? false) !== true) {
    throw new RuntimeException('Expected JPX embedded opacity to stay review-only.');
}
if (($metadata['pdfa']['output_condition_identifiers'] ?? []) !== ['Current WordPress JPX PDF/A RGB']) {
    throw new RuntimeException('Expected current xref PDF/A output intent metadata.');
}
if (str_contains(json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '', 'Stale WordPress JPX PDF/A')) {
    throw new RuntimeException('Stale PDF/A OutputIntent leaked into current metadata.');
}

$blockMetadata = [
    'source' => 'native-pdf-image-jpx-smask-colorspace-pdfa-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'visible_text' => $plainText,
    'image_filters' => $plan['image_filters'],
    'review_only_image_stream' => $plan['review_only_image_stream'],
    'native_jpx_raster_decode' => $plan['native_jpx_raster_decode'],
    'source_color_space' => $plan['source_color_space'],
    'alpha_output_mode' => $plan['alpha_output_mode'],
    'pdfa_output_intent' => $plan['pdfa_output_intent'],
    'color_management' => $plan['color_management'],
    'smask_in_data' => $plan['jpx_soft_mask_in_data'],
    'notes' => $plan['notes'],
    'executes_python_or_models' => false,
    'executes_pypdfium_or_pil' => false,
    'executes_external_pdf_tools' => false,
    'executes_jpx_raster_decode' => false,
];

echo '<!-- markerpdf:pdf-image-jpx-smask-colorspace-pdfa-currentbase ' . $htmlJson($blockMetadata) . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-image-filter="' . htmlspecialchars(implode(',', $plan['image_filters']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-review-only="' . ($plan['review_only_image_stream'] ? 'true' : 'false') . '"';
echo ' data-marker-color-space="' . htmlspecialchars($plan['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-pdfa-profile-source="' . htmlspecialchars($plan['color_management']['profile_source'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-pdfa-output-intent="' . htmlspecialchars(implode(',', $plan['pdfa_output_intent']['output_condition_identifiers']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-alpha-mode="' . htmlspecialchars($plan['alpha_output_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="JPX PDF/A image review swatch" style="background: linear-gradient(90deg, rgba(33, 94, 170, 0.65), rgba(255, 255, 255, 0.9)); width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
