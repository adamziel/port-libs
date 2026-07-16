<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$imageBytes = "\x20\xc0\xff\x00";
$compressedImage = gzcompress($imageBytes);
if (!is_string($compressedImage)) {
    throw new RuntimeException('Unable to compress DeviceN ICC SMask transfer smoke fixture.');
}

$groupPayload = "q /ImSpot Do Q\n";
$objects = [
    71 => '[/ICCBased 72 0 R]',
    72 => "<< /N 3 /Alternate /DeviceRGB /Range [0 1 0 1 0 1] /Length 17 >>\nstream\nICC-DEVICE-N-XFER\nendstream",
    73 => "<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1] /Length 24 >>\nstream\n{ exch dup mul exch }\nendstream",
    91 => "<< /Type /XObject /Subtype /Form /BBox [0 0 40 20] /Group << /S /Transparency /CS 97 0 R /I true /K false >> /Length " . strlen($groupPayload) . " >>\nstream\n{$groupPayload}endstream",
    95 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [0.1] /C1 [0.9] /N 2 >>',
    96 => '<< /Type /Mask /S /Luminosity /G 91 0 R /BC [0.5] /TR 95 0 R >>',
    97 => '[/ICCBased 98 0 R]',
    98 => "<< /N 1 /Alternate /DeviceGray /Range [0 1] /Length 15 >>\nstream\nICC-ALPHA-GROUP\nendstream",
];
$imageObject = "<< /Subtype /Image /Width 2 /Height 1 /ColorSpace [/DeviceN [/Spot#20Blue /Spot#20Varnish] 71 0 R 73 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0 0 1] /SMask 96 0 R /Length " . strlen($compressedImage) . " >>\nstream\n{$compressedImage}\nendstream";

$renderer = new PdfImageRenderer();
$preview = $renderer->alternateColorantStreamPreviewRows($imageObject, $objects, 2, [0.25, 0.75]);

if (($preview['source_color_space'] ?? null) !== 'DeviceN' || ($preview['alternate_color_space'] ?? null) !== 'ICCBased') {
    throw new RuntimeException('DeviceN ICCBased color-space metadata was not preserved.');
}
if (($preview['uses_supplied_soft_mask_samples'] ?? false) !== true || ($preview['soft_mask_transfer_function_applied_before_rgb'] ?? false) !== true) {
    throw new RuntimeException('Supplied soft-mask transfer samples were not applied before RGB preview.');
}
if (($preview['preview_pixel_count'] ?? null) !== 2 || ($preview['image_stream']['decoded_with_current_filters'] ?? false) !== true) {
    throw new RuntimeException('Decoded DeviceN stream rows were not available for preview.');
}

$first = $preview['pixels'][0];
$second = $preview['pixels'][1];
$firstAlpha = round((float) $first['soft_mask_alpha'], 6);
$secondAlpha = round((float) $second['soft_mask_alpha'], 6);
if ($firstAlpha !== 0.15 || $secondAlpha !== 0.55) {
    throw new RuntimeException('Soft-mask transfer alpha rows did not match the expected Type 2 function output.');
}

$metadata = [
    'source' => 'native-pdf-image-devicen-icc-smask-transfer-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $preview['source_color_space'],
    'alternate_color_space' => $preview['alternate_color_space'],
    'alternate_uses_icc_profile' => $preview['alternate_uses_icc_profile'],
    'icc_profile' => $preview['icc_profile'],
    'soft_mask_subtype' => $preview['soft_mask_group']['subtype'],
    'soft_mask_group_color_space' => $preview['soft_mask_group']['group_color_space'],
    'soft_mask_transfer_function' => $preview['soft_mask_transfer_function'],
    'uses_supplied_soft_mask_samples' => $preview['uses_supplied_soft_mask_samples'],
    'first_alpha_after_transfer' => $firstAlpha,
    'second_alpha_after_transfer' => $secondAlpha,
    'first_colorants' => array_map(static fn (float $value): float => round($value, 6), $first['colorant_tints']),
    'second_colorants' => array_map(static fn (float $value): float => round($value, 6), $second['colorant_tints']),
    'stream_notes' => $preview['stream_notes'],
    'alpha_output_mode' => $preview['alpha_output_mode'],
    'output_color_mode' => $preview['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_pypdfium_or_pil' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-image-devicen-icc-smask-transfer-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-image-review="devicen-icc-smask-transfer"';
echo ' data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-alternate-color-space="' . htmlspecialchars((string) $preview['alternate_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-smask-group-color-space="' . htmlspecialchars((string) $preview['soft_mask_group']['group_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-transfer-object="' . htmlspecialchars((string) $preview['soft_mask_transfer_function']['object'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-first-alpha="' . htmlspecialchars((string) $firstAlpha, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-second-alpha="' . htmlspecialchars((string) $secondAlpha, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($preview['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="DeviceN ICCBased image with supplied soft-mask transfer alpha" style="background: linear-gradient(90deg, rgba(50,160,220,0.15), rgba(255,255,255,0.55)); width: 128px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
