<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$indexedDictionary = '/W 3 /H 1 /CS [/I /RGB 2 <00000080FF40010203>] /BPC 2 /F /JBIG2Decode /DP << /JBIG2Globals 24 0 R >> /D [0 2]';
$indexedPayload = "\x97JB2\r\n\x1a\nBT /F1 12 Tf 72 690 Td (Inline Indexed JBIG2 Noise) Tj ET";
$maskDictionary = '/W 2 /H 1 /IM true /D [1 0] /F /JBIG2Decode /DP << /JBIG2Globals 24 0 R >>';
$maskPayload = "\xff\x00BT /F1 12 Tf 72 680 Td (Inline ImageMask Noise) Tj ET";
$content = "BT /F1 12 Tf 72 720 Td (Before Inline Image Review) Tj ET\n"
    . 'BI ' . $indexedDictionary . " ID\n" . $indexedPayload . "\nEI\n"
    . 'BI ' . $maskDictionary . " ID\n" . $maskPayload . "\nEI\n"
    . 'BT /F1 12 Tf 72 704 Td (After Inline Image Review) Tj ET';

$renderer = new PdfImageRenderer();
$objects = [
    24 => "<< /Length 4 >>\nstream\nGBLS\nendstream",
];
$indexedPlan = $renderer->inlineImageReviewPlan($indexedDictionary, $indexedPayload, $objects);
$maskPlan = $renderer->inlineImageReviewPlan($maskDictionary, $maskPayload, $objects);
$indexedPreview = $renderer->indexedSamplePreview(2, $indexedPlan);
$maskOpacityZero = $renderer->imageMaskSampleOpacity(0, $maskPlan['image_mask']);
$maskOpacityOne = $renderer->imageMaskSampleOpacity(1, $maskPlan['image_mask']);
$text = (new PdfTextExtractor())->extractPlainText($pdfWithContent($content));

$metadata = [
    'source' => 'native-pdf-inline-mask-indexed-jbig-review',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb plus pdftext inline image payload boundary',
    'visible_text_imported' => $text === "Before Inline Image Review\nAfter Inline Image Review",
    'excluded_inline_indexed_payload_text' => !str_contains($text, 'Inline Indexed JBIG2 Noise'),
    'excluded_inline_mask_payload_text' => !str_contains($text, 'Inline ImageMask Noise'),
    'indexed_canonical_dictionary' => $indexedPlan['inline_image']['canonical_dictionary'],
    'indexed_abbreviations_expanded' => $indexedPlan['inline_image_abbreviations_expanded'],
    'indexed_source_color_space' => $indexedPlan['source_color_space'],
    'indexed_preview_only_filters' => $indexedPlan['inline_image']['review_only_filters'],
    'indexed_jbig2_globals_present' => $indexedPlan['image_filter_boundary']['jbig2_globals_present'],
    'indexed_palette_rgb' => $indexedPreview['base_components'],
    'mask_canonical_dictionary' => $maskPlan['inline_image']['canonical_dictionary'],
    'mask_abbreviations_expanded' => $maskPlan['inline_image_abbreviations_expanded'],
    'mask_source_color_space' => $maskPlan['source_color_space'],
    'mask_decode_inverted' => $maskPlan['image_mask']['inverted'],
    'mask_opacity_zero' => $maskOpacityZero,
    'mask_opacity_one' => $maskOpacityOne,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:inline-mask-indexed-jbig-review ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-inline-image="true"';
echo ' data-marker-color-space="' . htmlspecialchars($indexedPlan['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-image-filter="' . htmlspecialchars(implode(',', $indexedPlan['image_filters']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-preview-only="' . ($indexedPlan['inline_image_review_only'] ? 'true' : 'false') . '"';
echo ' data-marker-image-mask-decode="' . ($maskPlan['image_mask']['inverted'] ? 'inverted' : 'identity') . '">';
echo '<div role="img" aria-label="Inline Indexed JBIG2 image review swatch"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
