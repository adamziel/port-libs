<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$imageBytes = "\x40\x80\xff\x00\x00\xff";
$maskBytes = "\x00\x40\xff";
$compressedImage = gzcompress($imageBytes);
$compressedMask = gzcompress($maskBytes);
if (!is_string($compressedImage) || !is_string($compressedMask)) {
    throw new RuntimeException('Unable to compress DeviceN stream fixtures.');
}

$imageHex = strtoupper(bin2hex($compressedImage)) . '>';
$maskHex = strtoupper(bin2hex($compressedMask)) . '>';
$objects = [
    71 => '[/ICCBased 72 0 R]',
    72 => "<< /N 3 /Alternate /DeviceRGB /Range [0 1 0 1 0 1] /Length 15 >>\nstream\nICC-DEVICE-N\nendstream",
    73 => "<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1] /Length 24 >>\nstream\n{ exch dup mul exch }\nendstream",
    74 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /DecodeParms [null << /Predictor 1 /Columns 3 /Colors 1 /BitsPerComponent 8 >>] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
];
$imageObject = "<< /Subtype /Image /Width 3 /Height 1 /ColorSpace [/DeviceN [/Spot#20Blue /Spot#20Varnish] 71 0 R 73 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0 0 1] /DecodeParms [null << /Predictor 1 /Columns 6 /Colors 1 /BitsPerComponent 8 >>] /SMask 74 0 R /Length " . strlen($imageHex) . " >>\nstream\n{$imageHex}\nendstream";

$renderer = new PdfImageRenderer();
$preview = $renderer->alternateColorantStreamPreviewRows($imageObject, $objects, 3);
$roundedPixels = array_map(
    static function (array $pixel): array {
        $pixel['colorant_tints'] = array_map(static fn (float $value): float => round($value, 6), $pixel['colorant_tints']);
        $pixel['tint_values'] = array_map(static fn (float $value): float => round($value, 6), $pixel['tint_values']);
        $pixel['soft_mask_alpha'] = $pixel['soft_mask_alpha'] === null ? null : round((float) $pixel['soft_mask_alpha'], 6);

        return $pixel;
    },
    $preview['pixels']
);

$metadata = [
    'source' => 'native-pdf-devicen-icc-softmask-stream-preview-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $preview['source_color_space'],
    'width' => $preview['width'],
    'height' => $preview['height'],
    'components_per_pixel' => $preview['components_per_pixel'],
    'alternate_color_space' => $preview['alternate_color_space'],
    'alternate_uses_icc_profile' => $preview['alternate_uses_icc_profile'],
    'tint_transform_object' => $preview['tint_transform_object'],
    'tint_transform_function_type' => $preview['tint_transform_function_type'],
    'image_stream' => $preview['image_stream'],
    'soft_mask_stream' => $preview['soft_mask_stream'],
    'pixels' => $roundedPixels,
    'stream_notes' => $preview['stream_notes'],
    'output_color_mode' => $preview['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-devicen-icc-softmask-stream-preview-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-alternate-color-space="' . htmlspecialchars((string) $preview['alternate_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-pixel-count="' . htmlspecialchars((string) $preview['preview_pixel_count'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-image-stream-decoded="' . ($preview['image_stream']['decoded_with_current_filters'] ? 'true' : 'false') . '"';
echo ' data-marker-soft-mask-stream-decoded="' . (($preview['soft_mask_stream']['decoded_with_current_filters'] ?? false) ? 'true' : 'false') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($preview['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="DeviceN ICCBased stream preview with soft-mask alpha" style="background: linear-gradient(90deg, rgba(64,128,255,1), rgba(255,0,255,0.75), rgba(0,255,255,0)); width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
