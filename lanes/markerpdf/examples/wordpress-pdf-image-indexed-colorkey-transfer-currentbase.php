<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$imageBytes = "\x1c";
$compressedImage = gzcompress($imageBytes);
if (!is_string($compressedImage)) {
    throw new RuntimeException('Unable to compress Indexed ColorKey sample bytes.');
}

$imageObject = "<< /Subtype /Image /Width 3 /Height 1 /ColorSpace [/Indexed /DeviceRGB 3 <000000FF000000FF000000FF>] /BitsPerComponent 2 /Filter /FlateDecode /Decode [3 0] /Mask [1 1] /Length " . strlen($compressedImage) . " >>\nstream\n{$compressedImage}\nendstream";

$renderer = new PdfImageRenderer();
$preview = $renderer->indexedImageStreamPreviewRows($imageObject, [], 3);
$transparent = $preview['pixels'][1] ?? null;
$opaque = $preview['pixels'][2] ?? null;

if (!is_array($transparent) || ($transparent['matches_color_key'] ?? false) !== true || ($transparent['color_key_alpha'] ?? null) !== 0.0) {
    throw new RuntimeException('Indexed ColorKey transparent sample preview failed.');
}
if (!is_array($opaque) || ($opaque['matches_color_key'] ?? true) !== false || ($opaque['color_key_alpha'] ?? null) !== 1.0) {
    throw new RuntimeException('Indexed ColorKey opaque sample preview failed.');
}

$opaqueRgb = array_map(
    static fn (float $component): int => (int) round($component * 255),
    $opaque['base_components']
);
$opaqueColor = sprintf('rgb(%d,%d,%d)', $opaqueRgb[0], $opaqueRgb[1], $opaqueRgb[2]);

$metadata = [
    'source' => 'native-pdf-image-indexed-colorkey-transfer-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $preview['source_color_space'],
    'color_key_mask' => $preview['color_key_mask'],
    'transparent_raw_index' => $transparent['raw_sample'],
    'transparent_decoded_index' => $transparent['decoded_index'],
    'transparent_palette_index' => $transparent['palette_index'],
    'transparent_palette_rgb' => $transparent['base_components'],
    'transparent_alpha' => $transparent['color_key_alpha'],
    'opaque_raw_index' => $opaque['raw_sample'],
    'opaque_decoded_index' => $opaque['decoded_index'],
    'opaque_palette_index' => $opaque['palette_index'],
    'opaque_palette_rgb' => $opaque['base_components'],
    'opaque_alpha' => $opaque['color_key_alpha'],
    'decode_applied_after_color_key' => $transparent['decode_applied_after_color_key'],
    'palette_transfer_applied_after_color_key' => $transparent['palette_transfer_applied_after_color_key'],
    'alpha_output_mode' => $preview['alpha_output_mode'],
    'output_color_mode' => $preview['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-image-indexed-colorkey-transfer-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-color-key-mask="' . (($preview['color_key_mask']['present'] ?? false) ? 'true' : 'false') . '"';
echo ' data-marker-mask-compares="raw-index-before-decode"';
echo ' data-marker-transparent-index="' . htmlspecialchars((string) $transparent['raw_sample'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-transparent-alpha="' . htmlspecialchars((string) $transparent['color_key_alpha'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-opaque-index="' . htmlspecialchars((string) $opaque['raw_sample'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-opaque-alpha="' . htmlspecialchars((string) $opaque['color_key_alpha'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-palette-transfer-after-mask="' . ($transparent['palette_transfer_applied_after_color_key'] ? 'true' : 'false') . '">';
echo '<div role="img" aria-label="Indexed ColorKey mask and palette transfer review swatch" style="background: linear-gradient(90deg, transparent 0 48%, ' . htmlspecialchars($opaqueColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' 52% 100%); width: 96px; height: 48px; border: 1px solid #777;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
