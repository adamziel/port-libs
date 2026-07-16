<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$dictionary = '/W 4 /H 1 /IM true /D [1 0]';
$payload = "\xa0BT /F1 12 Tf 72 690 Td (Inline ImageMask Noise) Tj ET";
$content = "BT /F1 12 Tf 72 720 Td (Before Inline Mask Preview) Tj ET\n"
    . 'BI ' . $dictionary . " ID\n" . $payload . "\nEI\n"
    . 'BT /F1 12 Tf 72 704 Td (After Inline Mask Preview) Tj ET';

$renderer = new PdfImageRenderer();
$preview = $renderer->inlineImageMaskPreviewRows($dictionary, "\xa0", [], 4);
$text = (new PdfTextExtractor())->extractPlainText($pdfWithContent($content));
$metadata = [
    'source' => 'native-pdf-inline-image-mask-preview-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb plus pdftext inline image payload boundary',
    'visible_text_imported' => $text === "Before Inline Mask Preview\nAfter Inline Mask Preview",
    'excluded_inline_mask_payload_text' => !str_contains($text, 'Inline ImageMask Noise'),
    'inline_mask_canonical_dictionary' => $preview['inline_image']['canonical_dictionary'],
    'inline_abbreviations_expanded' => $preview['inline_image_abbreviations_expanded'],
    'mask_decode_inverted' => $preview['image_mask']['inverted'],
    'expected_pixel_count' => $preview['expected_pixel_count'],
    'preview_pixel_count' => $preview['preview_pixel_count'],
    'mask_pixels' => $preview['pixels'],
    'complete_image_sample_data' => $preview['complete_image_sample_data'],
    'alpha_output_mode' => $preview['alpha_output_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:inline-image-mask-preview-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-inline-image="true"';
echo ' data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-image-mask-decode="' . ($preview['image_mask']['inverted'] ? 'inverted' : 'identity') . '"';
echo ' data-marker-preview-pixels="' . htmlspecialchars((string) $preview['preview_pixel_count'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-alpha-mode="' . htmlspecialchars($preview['alpha_output_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Inline ImageMask stencil preview swatch"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
