<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85Encode = static function (string $bytes, bool $includeTerminator = true): string {
    $encoded = '<~';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        $digits = [];
        for ($index = 0; $index < 5; $index++) {
            $digits[] = chr(($value % 85) + 33);
            $value = intdiv($value, 85);
        }

        $encoded .= implode('', array_slice(array_reverse($digits), 0, $chunkLength + 1));
    }

    return $encoded . ($includeTerminator ? '~>' : '');
};

$imageRow = 'raw EI BT /F1 12 Tf 72 690 Td (Inline DP Image Noise) Tj ET';
$compressedImage = gzcompress("\0" . $imageRow, 0);
if (!is_string($compressedImage)) {
    throw new RuntimeException('Unable to build inline image fixture.');
}

$content = "BT /F1 12 Tf 72 720 Td (Before DP Inline Image) Tj ET\n"
    . 'BI /W ' . strlen($imageRow) . ' /H 1 /CS /G /BPC 8 /F /Fl '
    . '/DP << /Predictor 12 /Columns ' . strlen($imageRow) . " /Colors 1 /BitsPerComponent 8 >> ID "
    . $compressedImage . "\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After DP Inline Image) Tj ET\n"
    . "BT /F1 12 Tf 72 688 Td (Before A85 Inline Image) Tj ET\n"
    . "BI /F /A85 ID\n"
    . "87cURDc^jtCh* EI BT /F1 12 Tf 72 672 Td (ASCII85 Inline Noise) Tj ET ~>\nEI\n"
    . "BT /F1 12 Tf 72 656 Td (After A85 Inline Image) Tj ET";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$renderer = new PdfImageRenderer();
$inlineReviewDictionary = '/W 3 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 2 /F /A85 /D [0 3]';
$inlineReviewObjects = [
    91 => '<000000FF000000FF000000FF>',
];
$completeInlineReview = $renderer->inlineIndexedImageStreamPreviewRows(
    $inlineReviewDictionary,
    $ascii85Encode("\x1c", true),
    $inlineReviewObjects,
    3
);
$indirectInlineObjects = [
    91 => '<000000FF000000FF000000FF>',
    101 => '3',
    102 => '1',
    103 => '2',
    104 => '[0 3]',
];
$indirectCompressedImage = gzcompress("\x1c");
if (!is_string($indirectCompressedImage)) {
    throw new RuntimeException('Unable to build indirect inline image preview fixture.');
}
$indirectIndexedReview = $renderer->inlineIndexedImageStreamPreviewRows(
    '/W 101 0 R /H 102 0 R /CS [/I /RGB 3 91 0 R] /BPC 103 0 R /F [/AHx /Fl] /D 104 0 R',
    strtoupper(bin2hex($indirectCompressedImage)) . '>',
    $indirectInlineObjects,
    3
);
$indirectMaskReview = $renderer->inlineImageMaskPreviewRows(
    '/W 101 0 R /H 102 0 R /IM true /D 103 0 R /BPC 104 0 R',
    "\xa0",
    [
        101 => '4',
        102 => '1',
        103 => '[1 0]',
        104 => '1',
    ],
    4
);
$incompleteAscii85ReviewDecodeFailed = false;
try {
    $renderer->inlineIndexedImageStreamPreviewRows(
        $inlineReviewDictionary,
        $ascii85Encode("\x1c", false),
        $inlineReviewObjects,
        3
    );
} catch (InvalidArgumentException) {
    $incompleteAscii85ReviewDecodeFailed = true;
}

echo '<!-- markerpdf-inline-image-decode-boundary-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'page /Contents BI inline image filter decode boundary before Gutenberg paragraph rendering',
    'uses_inline_image_abbreviations' => true,
    'fake_ei_inside_compressed_payload' => str_contains($compressedImage, ' EI '),
    'fake_ei_inside_ascii85_payload' => true,
    'visible_text_imported' => $lines === [
        'Before DP Inline Image',
        'After DP Inline Image',
        'Before A85 Inline Image',
        'After A85 Inline Image',
    ],
    'requires_ascii85_end_marker_before_ei' => true,
    'complete_ascii85_review_decoded' => ($completeInlineReview['image_stream']['decoded_with_current_filters'] ?? false) === true,
    'complete_ascii85_review_preview_pixels' => $completeInlineReview['preview_pixel_count'] ?? null,
    'incomplete_ascii85_review_decode_failed' => $incompleteAscii85ReviewDecodeFailed,
    'requires_ascii85_review_end_marker_before_rgb_preview' => true,
    'resolves_current_indirect_inline_preview_operands' => ($indirectIndexedReview['width'] ?? null) === 3
        && ($indirectIndexedReview['height'] ?? null) === 1
        && ($indirectIndexedReview['bits_per_component'] ?? null) === 2
        && ($indirectIndexedReview['preview_pixel_count'] ?? null) === 3,
    'indirect_inline_decode_source' => $indirectIndexedReview['image_decode']['source'] ?? null,
    'indirect_inline_palette_indexes' => array_column($indirectIndexedReview['pixels'] ?? [], 'palette_index'),
    'resolves_current_indirect_inline_imagemask_geometry' => ($indirectMaskReview['width'] ?? null) === 4
        && ($indirectMaskReview['height'] ?? null) === 1
        && ($indirectMaskReview['preview_pixel_count'] ?? null) === 4,
    'indirect_inline_imagemask_opacity' => array_column($indirectMaskReview['pixels'] ?? [], 'opacity'),
    'excluded_inline_image_text' => !str_contains($plainText, 'Inline DP Image Noise')
        && !str_contains($plainText, 'raw EI')
        && !str_contains($plainText, 'ASCII85 Inline Noise')
        && !str_contains($plainText, '87cURDc'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
