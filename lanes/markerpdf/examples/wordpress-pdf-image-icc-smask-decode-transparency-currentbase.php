<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();
$imageBytes = "\x00\x80\xff\xff\x00\x40";
$maskBytes = "\x00\x80";
$compressedImage = gzcompress($imageBytes);
$compressedMask = gzcompress($maskBytes);
if (!is_string($compressedImage) || !is_string($compressedMask)) {
    throw new RuntimeException('Unable to compress ICCBased image smoke fixtures.');
}

$imageHex = strtoupper(bin2hex($compressedImage)) . '>';
$maskHex = strtoupper(bin2hex($compressedMask)) . '>';
$objects = [
    10 => "<< /N 3 /Alternate /DeviceRGB /Range [0 1 0 1 0.25 0.75] /Length 11 >>\nstream\nICC-PROFILE\nendstream",
    42 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /DecodeParms [null << /Predictor 1 /Columns 2 /Colors 1 /BitsPerComponent 8 >>] /Decode [1 0] /Matte [1 0.5 0.25] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
    99 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length 8 >>\nstream\nSTALEMSK\nendstream",
];
$imageObject = "<< /Subtype /Image /Width 2 /Height 1 /ColorSpace [/ICCBased 10 0 R] /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /DecodeParms [null << /Predictor 1 /Columns 6 /Colors 1 /BitsPerComponent 8 >>] /Decode [1 0 0 1 0.25 0.75] /SMask 42 0 R /Length " . strlen($imageHex) . " >>\nstream\n{$imageHex}\nendstream";

$preview = $renderer->iccBasedImageStreamPreviewRows($imageObject, $objects, 2);
if ($preview['source_color_space'] !== 'ICCBased' || $preview['preview_pixel_count'] !== 2) {
    throw new RuntimeException('ICCBased soft-mask preview did not decode the expected pixel rows.');
}
if (($preview['soft_mask_filter_boundary']['source_object'] ?? null) !== 42 || ($preview['soft_mask_filter_boundary']['uses_current_object_map'] ?? false) !== true) {
    throw new RuntimeException('ICCBased soft-mask preview did not use the current object map.');
}

$roundedPixels = array_map(
    static function (array $pixel): array {
        $pixel['decoded_components'] = array_map(static fn (float $value): float => round($value, 6), $pixel['decoded_components']);
        $pixel['soft_mask_alpha'] = $pixel['soft_mask_alpha'] === null ? null : round((float) $pixel['soft_mask_alpha'], 6);
        $pixel['soft_mask_alpha_before_transfer'] = $pixel['soft_mask_alpha_before_transfer'] === null ? null : round((float) $pixel['soft_mask_alpha_before_transfer'], 6);

        return $pixel;
    },
    $preview['pixels']
);

$firstPixel = $preview['pixels'][0];
$rgb = array_map(
    static fn (float $component): int => max(0, min(255, (int) round($component * 255))),
    $firstPixel['decoded_components']
);
$rgba = sprintf(
    'rgba(%d,%d,%d,%.6f)',
    $rgb[0],
    $rgb[1],
    $rgb[2],
    (float) $firstPixel['soft_mask_alpha']
);

$metadata = [
    'source' => 'native-pdf-image-icc-smask-decode-transparency-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $preview['source_color_space'],
    'icc_profile' => $preview['icc_profile'],
    'image_decoded_with_current_filters' => $preview['image_stream']['decoded_with_current_filters'],
    'soft_mask_source_object' => $preview['soft_mask_filter_boundary']['source_object'],
    'soft_mask_uses_current_object_map' => $preview['soft_mask_filter_boundary']['uses_current_object_map'],
    'soft_mask_decoded_with_current_filters' => $preview['soft_mask_stream']['decoded_with_current_filters'],
    'pixels' => $roundedPixels,
    'stream_notes' => $preview['stream_notes'],
    'output_color_mode' => $preview['output_color_mode'],
    'alpha_output_mode' => $preview['alpha_output_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-image-icc-smask-decode-transparency-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-icc-components="' . htmlspecialchars((string) $preview['icc_profile']['components'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-source="' . htmlspecialchars((string) $preview['soft_mask_filter_boundary']['source_object'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-alpha-output="' . htmlspecialchars($preview['alpha_output_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($preview['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="ICCBased PDF image stream with decoded soft-mask alpha" style="background: ' . htmlspecialchars($rgba, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '; width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
