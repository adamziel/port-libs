<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$imageDictionary = '<< /Subtype /Image /Filter [/CCITTFaxDecode /ASCIIHexDecode] /DecodeParms [50 0 R null] /Width 1728 /Height 8 /ColorSpace [/Separation /PANTONE#20485#20C 30 0 R 40 0 R] /BitsPerComponent 1 /Decode [1 0] >>';
$objects = [
    30 => '[/ICCBased 31 0 R]',
    31 => "<< /N 3 /Alternate /DeviceRGB /Range [0 1 0 1 0 1] /Length 11 >>\nstream\nICC-PROFILE\nendstream",
    40 => '<< /FunctionType 2 /Domain [0 1] /C0 [0 0 0] /C1 [1 0.2 0] /N 1 >>',
    50 => '<< /K -1 /Columns 1728 /Rows 8 /BlackIs1 true /EncodedByteAlign true /DamagedRowsBeforeError 2 >>',
    60 => '<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 18 >>',
];

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);
$deviceNPlan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Filter /JPXDecode /Width 2 /Height 1 /ColorSpace [/DeviceN [/Cyan /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Decode [0 1 1 0] >>',
    $objects
);
$decodedOff = $renderer->imageSampleDecodeValues([0], $plan['image_decode'], $plan['bits_per_component'])[0];
$decodedOn = $renderer->imageSampleDecodeValues([1], $plan['image_decode'], $plan['bits_per_component'])[0];

$metadata = [
    'source' => 'native-pdf-separation-devicen-ccitt-preview-boundary',
    'upstream_boundary' => 'marker.pdf.images.render_image',
    'source_color_space' => $plan['source_color_space'],
    'colorant_names' => $plan['alternate_color_space']['colorant_names'],
    'alternate_color_space' => $plan['alternate_color_space']['alternate_color_space'],
    'alternate_uses_icc_profile' => $plan['alternate_color_space']['alternate_uses_icc_profile'],
    'tint_transform_object' => $plan['alternate_color_space']['tint_transform_object'],
    'tint_transform_function_type' => $plan['alternate_color_space']['tint_transform_function_type'],
    'image_filters' => $plan['image_filters'],
    'preview_only_filters' => $plan['image_filter_boundary']['preview_only_filters'],
    'ccitt_decode_parms' => $plan['image_filter_details'][0]['decode_parms'],
    'decode_sample_zero' => $decodedOff,
    'decode_sample_one' => $decodedOn,
    'devicen_colorants' => $deviceNPlan['alternate_color_space']['colorant_names'],
    'devicen_alternate_color_space' => $deviceNPlan['alternate_color_space']['alternate_color_space'],
    'devicen_preview_filter' => $deviceNPlan['image_filter_boundary']['preview_only_filters'],
    'output_color_mode' => $plan['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-separation-devicen-ccitt-preview-boundary ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($plan['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-colorant="' . htmlspecialchars(implode(',', $plan['alternate_color_space']['colorant_names']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-alternate="' . htmlspecialchars((string) $plan['alternate_color_space']['alternate_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-filter="' . htmlspecialchars(implode(',', $plan['image_filters']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-preview-only="' . ($plan['image_filter_boundary']['native_raster_decode'] ? 'false' : 'true') . '">';
echo '<div role="img" aria-label="Separation CCITT image review swatch" style="background: linear-gradient(90deg, rgb(255,51,0), rgb(255,255,255)); width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
