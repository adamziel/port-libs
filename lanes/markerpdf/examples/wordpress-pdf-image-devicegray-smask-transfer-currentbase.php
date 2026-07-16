<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();

$groupPayload = "q /ImGray Do Q\n";
$transferObjects = [
    91 => "<< /Type /XObject /Subtype /Form /BBox [0 0 24 12] /Group << /S /Transparency /CS /DeviceGray /I true /K false >> /Length " . strlen($groupPayload) . " >>\nstream\n{$groupPayload}endstream",
    95 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [0.1] /C1 [0.9] /N 2 >>',
    96 => '<< /Type /Mask /S /Luminosity /G 91 0 R /BC [0.5] /TR 95 0 R >>',
];
$transferPlan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [1 0] /SMask 96 0 R >>',
    $transferObjects
);
$transferPreview = $renderer->deviceGraySamplePreview(64, $transferPlan, 0.5);

$imageBytes = "\x00\x80\xff";
$maskBytes = "\xff\x80\x00";
$compressedImage = gzcompress($imageBytes);
$compressedMask = gzcompress($maskBytes);
if (!is_string($compressedImage) || !is_string($compressedMask)) {
    throw new RuntimeException('Unable to compress DeviceGray smoke fixtures.');
}

$imageHex = strtoupper(bin2hex($compressedImage)) . '>';
$maskHex = strtoupper(bin2hex($compressedMask)) . '>';
$streamObjects = [
    42 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /DecodeParms [null << /Predictor 1 /Columns 3 /Colors 1 /BitsPerComponent 8 >>] /Decode [1 0] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
    99 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length 9 >>\nstream\nSTALEMASK\nendstream",
];
$imageObject = "<< /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /DecodeParms [null << /Predictor 1 /Columns 3 /Colors 1 /BitsPerComponent 8 >>] /Decode [1 0] /SMask 42 0 R /Length " . strlen($imageHex) . " >>\nstream\n{$imageHex}\nendstream";
$streamPreview = $renderer->deviceGrayImageStreamPreviewRows($imageObject, $streamObjects, 3);

if ($transferPreview['source_color_space'] !== 'DeviceGray' || $transferPreview['soft_mask_transfer_applied'] !== true) {
    throw new RuntimeException('DeviceGray soft-mask transfer preview failed.');
}
if (($streamPreview['soft_mask_filter_boundary']['source_object'] ?? null) !== 42 || ($streamPreview['soft_mask_filter_boundary']['uses_current_object_map'] ?? false) !== true) {
    throw new RuntimeException('DeviceGray current soft-mask stream boundary failed.');
}

$roundedPixels = array_map(
    static function (array $pixel): array {
        $pixel['decoded_gray'] = round((float) $pixel['decoded_gray'], 6);
        $pixel['rgb_components'] = array_map(static fn (float $value): float => round($value, 6), $pixel['rgb_components']);
        $pixel['soft_mask_alpha'] = $pixel['soft_mask_alpha'] === null ? null : round((float) $pixel['soft_mask_alpha'], 6);

        return $pixel;
    },
    $streamPreview['pixels']
);

$metadata = [
    'source' => 'native-pdf-image-devicegray-smask-transfer-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $transferPreview['source_color_space'],
    'decoded_gray' => round($transferPreview['decoded_gray'], 6),
    'soft_mask_alpha_before_transfer' => round((float) $transferPreview['soft_mask_alpha_before_transfer'], 6),
    'soft_mask_alpha' => round((float) $transferPreview['soft_mask_alpha'], 6),
    'soft_mask_transfer_function' => $transferPreview['soft_mask_transfer_function'],
    'soft_mask_transfer_applied' => $transferPreview['soft_mask_transfer_applied'],
    'stream_image_decoded' => $streamPreview['image_stream']['decoded_with_current_filters'],
    'stream_soft_mask_source_object' => $streamPreview['soft_mask_filter_boundary']['source_object'],
    'stream_soft_mask_uses_current_object_map' => $streamPreview['soft_mask_filter_boundary']['uses_current_object_map'],
    'stream_pixels' => $roundedPixels,
    'stream_notes' => $streamPreview['stream_notes'],
    'output_color_mode' => $transferPreview['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

$gray = (int) round($transferPreview['decoded_gray'] * 255);
$alpha = round((float) $transferPreview['soft_mask_alpha'], 6);
$rgba = sprintf('rgba(%d,%d,%d,%.6f)', $gray, $gray, $gray, $alpha);

echo '<!-- markerpdf:pdf-image-devicegray-smask-transfer-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($transferPreview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-devicegray="' . htmlspecialchars((string) round($transferPreview['decoded_gray'], 6), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-transfer-alpha="' . htmlspecialchars((string) $alpha, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-source="' . htmlspecialchars((string) $streamPreview['soft_mask_filter_boundary']['source_object'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($transferPreview['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="DeviceGray PDF image with soft-mask transfer review swatch" style="background: ' . htmlspecialchars($rgba, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '; width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
