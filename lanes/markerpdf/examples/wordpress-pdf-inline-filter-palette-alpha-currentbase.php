<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$imageBytes = "\x1c";
$compressedImage = gzcompress($imageBytes);
$maskBytes = "\x00\x80\xff";
$compressedMask = gzcompress($maskBytes);
if (!is_string($compressedImage) || !is_string($compressedMask)) {
    throw new RuntimeException('Unable to compress inline Indexed image smoke bytes.');
}

$imageHex = strtoupper(bin2hex($compressedImage)) . '>';
$maskHex = strtoupper(bin2hex($compressedMask)) . '>';
$objects = [
    91 => '<000000FF000000FF000000FF>',
    92 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
];

$renderer = new PdfImageRenderer();
$preview = $renderer->inlineIndexedImageStreamPreviewRows(
    '/W 3 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 2 /F [/AHx /Fl] /D [0 3] /SMask 92 0 R',
    $imageHex,
    $objects,
    3
);

$red = $preview['pixels'][1] ?? null;
$blue = $preview['pixels'][2] ?? null;
if (!is_array($red) || ($red['palette_index'] ?? null) !== 1 || !isset($red['soft_mask_alpha'])) {
    throw new RuntimeException('Inline Indexed red palette preview failed.');
}
if (!is_array($blue) || ($blue['palette_index'] ?? null) !== 3 || ($blue['soft_mask_alpha'] ?? null) !== 0.0) {
    throw new RuntimeException('Inline Indexed blue alpha preview failed.');
}

$redAlpha = (float) $red['soft_mask_alpha'];
$blueAlpha = (float) $blue['soft_mask_alpha'];
$metadata = [
    'source' => 'native-pdf-inline-filter-palette-alpha-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $preview['source_color_space'],
    'inline_image_abbreviations_expanded' => $preview['inline_image_abbreviations_expanded'],
    'inline_payload_excluded_from_text' => $preview['inline_image_payload_excluded_from_text'],
    'filters' => $preview['image_stream']['filters'],
    'decoded_with_current_filters' => $preview['image_stream']['decoded_with_current_filters'],
    'soft_mask_filters' => $preview['soft_mask_stream']['filters'] ?? [],
    'soft_mask_decoded_with_current_filters' => $preview['soft_mask_stream']['decoded_with_current_filters'] ?? false,
    'red_palette_index' => $red['palette_index'],
    'red_alpha' => $redAlpha,
    'blue_palette_index' => $blue['palette_index'],
    'blue_alpha' => $blueAlpha,
    'alpha_output_mode' => $preview['alpha_output_mode'],
    'output_color_mode' => $preview['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-inline-filter-palette-alpha-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-inline-image="true"';
echo ' data-marker-inline-filter-chain="' . htmlspecialchars(implode(',', $preview['image_stream']['filters']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-alpha="current-object"';
echo ' data-marker-red-alpha="' . htmlspecialchars((string) $redAlpha, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-blue-alpha="' . htmlspecialchars((string) $blueAlpha, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-mode="' . htmlspecialchars($preview['alpha_output_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Inline Indexed palette alpha preview swatch" style="display:flex;width:144px;height:48px;border:1px solid #777;">';
echo '<span style="flex:1;background:#000;"></span>';
echo '<span style="flex:1;background:rgba(255,0,0,' . htmlspecialchars((string) $redAlpha, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ');"></span>';
echo '<span style="flex:1;background:rgba(0,0,255,' . htmlspecialchars((string) $blueAlpha, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ');"></span>';
echo '</div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
