<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$jpxPayload = "\xff\x4fJPX ColorKey bytes EI BT /F1 12 Tf 72 690 Td (Inline ColorKey Noise) Tj ET\xff\xd9";
$inlineDictionary = '/W 2 /H 1 /CS /RGB /BPC 8 /F /JPXDecode /D [0 1 1 0 0 1] /Mask [0 0 120 140 200 255]';
$content = "BT /F1 12 Tf 72 720 Td (Before Inline ColorKey) Tj ET\n"
    . 'BI ' . $inlineDictionary . " ID\n" . $jpxPayload . "\nEI\n"
    . 'BT /F1 12 Tf 72 704 Td (After Inline ColorKey) Tj ET';

$renderer = new PdfImageRenderer();
$preview = $renderer->inlineJpxColorKeyOutputPreviewRows(
    $inlineDictionary,
    $jpxPayload,
    [
        [0, 128, 240],
        [40, 64, 180],
    ],
    [],
    2
);
$text = (new PdfTextExtractor())->extractPlainText($pdfWithContent($content));
$transparent = $preview['pixels'][0] ?? null;
$opaque = $preview['pixels'][1] ?? null;
if (!is_array($transparent) || ($transparent['matches_color_key'] ?? false) !== true || ($transparent['color_key_alpha'] ?? null) !== 0.0) {
    throw new RuntimeException('Inline JPX ColorKey transparent preview row failed.');
}
if (!is_array($opaque) || ($opaque['matches_color_key'] ?? true) !== false || ($opaque['color_key_alpha'] ?? null) !== 1.0) {
    throw new RuntimeException('Inline JPX ColorKey opaque preview row failed.');
}

$transparentRgba = $transparent['output_rgba'];
$opaqueRgba = $opaque['output_rgba'];
$rgbaCss = static function (array $rgba): string {
    return sprintf(
        'rgba(%d,%d,%d,%s)',
        (int) $rgba['red'],
        (int) $rgba['green'],
        (int) $rgba['blue'],
        rtrim(rtrim(sprintf('%.6F', (float) $rgba['alpha']), '0'), '.')
    );
};

$metadata = [
    'source' => 'native-pdf-inline-jpx-colorkey-output-preview-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb plus PDF inline JPX ColorKey output preview boundary',
    'visible_text_imported' => $text === "Before Inline ColorKey\nAfter Inline ColorKey",
    'excluded_inline_jpx_payload_text' => !str_contains($text, 'Inline ColorKey Noise'),
    'inline_image_abbreviations_expanded' => $preview['inline_image_abbreviations_expanded'],
    'inline_payload_excluded_from_text' => $preview['inline_image_payload_excluded_from_text'],
    'review_only_image_stream' => $preview['review_only_image_stream'],
    'native_jpx_raster_decode' => $preview['native_jpx_raster_decode'],
    'uses_supplied_jpx_samples' => $preview['uses_supplied_jpx_samples'],
    'complete_image_sample_data' => $preview['complete_image_sample_data'],
    'color_key_mask' => $preview['color_key_mask'],
    'transparent_raw_sample' => $transparent['raw_sample'],
    'transparent_decoded_components' => $transparent['decoded_components'],
    'transparent_output_rgba' => $transparentRgba,
    'opaque_raw_sample' => $opaque['raw_sample'],
    'opaque_decoded_components' => $opaque['decoded_components'],
    'opaque_output_rgba' => $opaqueRgba,
    'alpha_output_mode' => $preview['alpha_output_mode'],
    'output_color_mode' => $preview['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:inline-jpx-colorkey-output-preview-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-inline-image="true"';
echo ' data-marker-image-filter="' . htmlspecialchars(implode(',', $preview['image_stream']['filters']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-preview-only="' . ($preview['review_only_image_stream'] ? 'true' : 'false') . '"';
echo ' data-marker-native-jpx-raster-decode="' . ($preview['native_jpx_raster_decode'] ? 'true' : 'false') . '"';
echo ' data-marker-color-key-mask="' . (($preview['color_key_mask']['present'] ?? false) ? 'true' : 'false') . '"';
echo ' data-marker-transparent-alpha="' . htmlspecialchars((string) $transparent['color_key_alpha'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-opaque-alpha="' . htmlspecialchars((string) $opaque['color_key_alpha'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-alpha-mode="' . htmlspecialchars($preview['alpha_output_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Inline JPX ColorKey output preview swatch" style="display:flex;width:96px;height:48px;border:1px solid #777;">';
echo '<span style="flex:1;background:' . htmlspecialchars($rgbaCss($transparentRgba), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ';"></span>';
echo '<span style="flex:1;background:' . htmlspecialchars($rgbaCss($opaqueRgba), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ';"></span>';
echo '</div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
