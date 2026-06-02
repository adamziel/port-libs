<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();

$separationImageBytes = "\x00\xff";
$separationMaskBytes = "\x00\x80";
$separationCompressedImage = gzcompress($separationImageBytes);
$separationCompressedMask = gzcompress($separationMaskBytes);
if (!is_string($separationCompressedImage) || !is_string($separationCompressedMask)) {
    throw new RuntimeException('Unable to compress Separation SMask smoke fixture.');
}

$separationImageHex = strtoupper(bin2hex($separationCompressedImage)) . '>';
$separationMaskHex = strtoupper(bin2hex($separationCompressedMask)) . '>';
$separationObjects = [
    40 => '<< /FunctionType 4 /Domain [0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>',
    42 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /Length " . strlen($separationMaskHex) . " >>\nstream\n{$separationMaskHex}\nendstream",
];
$separationObject = "<< /Subtype /Image /Width 2 /Height 1 /ColorSpace [/Separation /Spot#20Red /DeviceCMYK 40 0 R] /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /SMask 42 0 R /Length " . strlen($separationImageHex) . " >>\nstream\n{$separationImageHex}\nendstream";
$separationPreview = $renderer->alternateColorantStreamPreviewRows($separationObject, $separationObjects, 2);

$deviceNImageBytes = "\x00\x40\xff\x80";
$deviceNMaskBytes = "\xff\x00";
$deviceNCompressedImage = gzcompress($deviceNImageBytes);
$deviceNCompressedMask = gzcompress($deviceNMaskBytes);
if (!is_string($deviceNCompressedImage) || !is_string($deviceNCompressedMask)) {
    throw new RuntimeException('Unable to compress DeviceN SMask smoke fixture.');
}

$deviceNObjects = [
    60 => '<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>',
    77 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [0.25 0.75] /Length " . strlen($deviceNCompressedMask) . " >>\nstream\n{$deviceNCompressedMask}\nendstream",
];
$deviceNObject = "<< /Subtype /Image /Width 2 /Height 1 /ColorSpace [/DeviceN [/Spot#20Blue /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1 1 0] /SMask 77 0 R /Length " . strlen($deviceNCompressedImage) . " >>\nstream\n{$deviceNCompressedImage}\nendstream";
$deviceNPreview = $renderer->alternateColorantStreamPreviewRows($deviceNObject, $deviceNObjects, 2);

$metadata = [
    'source' => 'native-pdf-image-devicen-separation-smask-decode-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'separation_color_space' => $separationPreview['source_color_space'],
    'separation_soft_mask_decode_review' => $separationPreview['soft_mask_decode_review'],
    'separation_first_tint' => $separationPreview['pixels'][0]['colorant_tints']['Spot Red'],
    'separation_second_alpha' => round((float) $separationPreview['pixels'][1]['soft_mask_alpha'], 6),
    'devicen_color_space' => $deviceNPreview['source_color_space'],
    'devicen_colorants' => array_keys($deviceNPreview['pixels'][0]['colorant_tints']),
    'devicen_soft_mask_decode_review' => $deviceNPreview['soft_mask_decode_review'],
    'devicen_first_alpha' => round((float) $deviceNPreview['pixels'][0]['soft_mask_alpha'], 6),
    'devicen_second_alpha' => round((float) $deviceNPreview['pixels'][1]['soft_mask_alpha'], 6),
    'output_color_mode' => $deviceNPreview['output_color_mode'],
    'smask_payload_included' => false,
    'executes_python_or_models' => false,
    'executes_pypdfium_or_pil' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-image-devicen-separation-smask-decode-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-image-review="devicen-separation-smask-decode"';
echo ' data-marker-separation-alpha="' . htmlspecialchars((string) $metadata['separation_second_alpha'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-devicen-alpha="' . htmlspecialchars((string) $metadata['devicen_first_alpha'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($metadata['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Current PDF Separation and DeviceN soft-mask decode review swatch" style="background: linear-gradient(90deg, rgb(180,32,32), rgba(32,96,180,0.75)); width: 128px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
