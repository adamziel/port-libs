<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$maskBytes = "\x00\x40\xff";
$compressedMask = gzcompress($maskBytes);
if (!is_string($compressedMask)) {
    throw new RuntimeException('Unable to compress soft-mask fixture.');
}

$maskHex = strtoupper(bin2hex($compressedMask)) . '>';
$imageDictionary = '<< /Subtype /Image /Width 3 /Height 1 /ColorSpace [/DeviceN [/Spot#20Blue /Spot#20Varnish] 71 0 R 73 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Decode [1 0 0 1] /SMask 74 0 R >>';
$objects = [
    71 => '[/ICCBased 72 0 R]',
    72 => "<< /N 3 /Alternate /DeviceRGB /Range [0 1 0 1 0 1] /Length 15 >>\nstream\nICC-DEVICE-N\nendstream",
    73 => "<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1] /Length 24 >>\nstream\n{ exch dup mul exch }\nendstream",
    74 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /DecodeParms [null << /Predictor 1 /Columns 3 /Colors 1 /BitsPerComponent 8 >>] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
];

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);
$preview = $renderer->alternateColorantSamplePreview([64, 128], $plan, 64);

$roundedTints = array_map(static fn (float $value): float => round($value, 6), $preview['colorant_tints']);
$metadata = [
    'source' => 'native-pdf-icc-devicen-softmask-preview-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $preview['source_color_space'],
    'colorant_tints' => $roundedTints,
    'alternate_color_space' => $preview['alternate_color_space'],
    'alternate_uses_icc_profile' => $preview['alternate_uses_icc_profile'],
    'icc_profile' => $preview['icc_profile'],
    'tint_transform_object' => $preview['tint_transform_object'],
    'tint_transform_function_type' => $preview['tint_transform_function_type'],
    'tint_transform_preview_mode' => $preview['tint_transform_preview_mode'],
    'soft_mask_alpha' => round((float) $preview['soft_mask_alpha'], 6),
    'soft_mask_filter_boundary' => $plan['soft_mask_filter_boundary'],
    'output_color_mode' => $preview['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-icc-devicen-softmask-preview-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-alternate-color-space="' . htmlspecialchars((string) $preview['alternate_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-colorants="' . htmlspecialchars(implode(',', array_keys($roundedTints)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-alpha="' . htmlspecialchars((string) round((float) $preview['soft_mask_alpha'], 6), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($preview['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="DeviceN ICCBased image with soft mask review swatch" style="background: linear-gradient(90deg, rgb(64,128,255), rgb(255,255,255)); width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
