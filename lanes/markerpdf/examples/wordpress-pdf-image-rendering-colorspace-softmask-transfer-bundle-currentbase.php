<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();

$jpxPayload = "\xff\x4fNamed DeviceN JPX payload stays preview-only\xff\xd9";
$groupPayload = "q /ImSpot Do Q\n";
$transferObjects = [
    'Resources' => '70 0 R',
    70 => '<< /ColorSpace << /CSspot [/DeviceN [/Spot#20Blue /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] /CSstale /DeviceRGB >> >>',
    60 => '<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>',
    91 => "<< /Type /XObject /Subtype /Form /BBox [0 0 36 18] /Group << /S /Transparency /CS /DeviceGray /I true /K false >> /Length " . strlen($groupPayload) . " >>\nstream\n{$groupPayload}endstream",
    95 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [0.2] /C1 [0.8] /N 2 >>',
    96 => '<< /Type /Mask /S /Luminosity /G 91 0 R /BC [0.25] /TR 95 0 R >>',
];
$transferImage = "<< /Subtype /Image /Filter /JPXDecode /Width 2 /Height 1 /ColorSpace /CSspot /BitsPerComponent 8 /Decode [1 0 0 1] /SMask 96 0 R /Length " . strlen($jpxPayload) . " >>\nstream\n{$jpxPayload}\nendstream";
$transferPreview = $renderer->imageRenderingColorSpaceSoftMaskTransferBundle($transferImage, $transferObjects, 2);

$imageBytes = "\x00\xff";
$maskBytes = "\xff\x00";
$compressedImage = gzcompress($imageBytes);
$compressedMask = gzcompress($maskBytes);
if (!is_string($compressedImage) || !is_string($compressedMask)) {
    throw new RuntimeException('Unable to compress image rendering bundle smoke fixtures.');
}

$streamObjects = [
    'Resources' => '70 0 R',
    70 => '<< /ColorSpace << /CSgold [/Separation /Spot#20Gold /DeviceCMYK 81 0 R] /CSstale /DeviceRGB >> >>',
    81 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1 0 1 0 1 0 1] /C0 [0 0 0 0] /C1 [0 0.12 0.8 0] /N 1 >>',
    82 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($compressedMask) . " >>\nstream\n{$compressedMask}\nendstream",
];
$streamImage = "<< /Subtype /Image /Width 2 /Height 1 /ColorSpace /CSgold /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0] /SMask 82 0 R /Length " . strlen($compressedImage) . " >>\nstream\n{$compressedImage}\nendstream";
$streamPreview = $renderer->imageRenderingColorSpaceSoftMaskTransferBundle($streamImage, $streamObjects, 2);

if (($transferPreview['render_bundle']['soft_mask_transfer_applied_before_rgb'] ?? false) !== true) {
    throw new RuntimeException('Soft-mask transfer bundle was not preserved.');
}
if (($streamPreview['render_bundle']['soft_mask_stream_decoded'] ?? false) !== true) {
    throw new RuntimeException('Current soft-mask stream bundle was not decoded.');
}

$roundedPixels = array_map(
    static function (array $pixel): array {
        $pixel['soft_mask_alpha'] = $pixel['soft_mask_alpha'] === null ? null : round((float) $pixel['soft_mask_alpha'], 6);
        $pixel['colorant_tints'] = array_map(static fn (float $value): float => round($value, 6), $pixel['colorant_tints'] ?? []);

        return $pixel;
    },
    $streamPreview['pixels']
);

$metadata = [
    'source' => 'native-pdf-image-rendering-colorspace-softmask-transfer-bundle-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'transfer_bundle' => $transferPreview['render_bundle'],
    'transfer_preview_only_filters' => $transferPreview['image_stream']['preview_only_filters'],
    'stream_bundle' => $streamPreview['render_bundle'],
    'stream_pixels' => $roundedPixels,
    'stream_notes' => $streamPreview['stream_notes'],
    'image_payload_bytes_exposed' => false,
    'soft_mask_payload_bytes_exposed' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

$alpha = round((float) ($streamPreview['pixels'][1]['soft_mask_alpha'] ?? 1.0), 6);
$swatch = sprintf('rgba(%d,%d,%d,%.6f)', 255, 216, 40, $alpha);

echo '<!-- markerpdf:pdf-image-rendering-colorspace-softmask-transfer-bundle-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-bundle="' . htmlspecialchars((string) $streamPreview['render_bundle']['selected_preview'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-color-space="' . htmlspecialchars($streamPreview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-color-space-resource="' . htmlspecialchars((string) $streamPreview['render_bundle']['color_space_resource_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-source="' . htmlspecialchars((string) $streamPreview['render_bundle']['soft_mask_source_object'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-decoded="' . ($streamPreview['render_bundle']['soft_mask_stream_decoded'] ? 'true' : 'false') . '"';
echo ' data-marker-transfer-preserved="' . ($transferPreview['render_bundle']['soft_mask_transfer_present'] ? 'true' : 'false') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($streamPreview['render_bundle']['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="PDF image rendering color-space soft-mask transfer bundle review swatch" style="background: ' . htmlspecialchars($swatch, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '; width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
