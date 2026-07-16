<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$imageByte = 'Z';
$compressedImage = gzcompress($imageByte, 0);
if (!is_string($compressedImage)) {
    throw new RuntimeException('Unable to build Flate inline image smoke payload.');
}

$postStreamSurplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (WordPress Flate Inline Image Payload Leak) Tj ET rawtail';
$payload = $compressedImage . $postStreamSurplus;
$dictionary = '/W 1 /H 1 /CS /G /BPC 8 /F /Fl /D [0 1]';
$content = "BT /F1 12 Tf 72 720 Td (Before Flate Inline Image Import) Tj ET\n"
    . "BI {$dictionary} ID "
    . $payload . "\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After Flate Inline Image Import) Tj ET";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expected = ['Before Flate Inline Image Import', 'After Flate Inline Image Import'];

$postStreamPreviewRejected = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $payload, [], 1);
} catch (InvalidArgumentException) {
    $postStreamPreviewRejected = true;
}

$validPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $compressedImage, [], 1);
$payloadExcluded = !str_contains($plainText, 'WordPress Flate Inline Image Payload Leak')
    && !str_contains($plainText, 'rawtail')
    && !str_contains($plainText, 'ZZ EI');

if (
    $lines !== $expected
    || !$payloadExcluded
    || !$postStreamPreviewRejected
    || ($validPreview['image_stream']['decoded_with_current_filters'] ?? false) !== true
    || ($validPreview['image_stream']['decoded_sha256'] ?? null) !== hash('sha256', $imageByte)
) {
    throw new RuntimeException('Flate inline image post-stream boundary smoke failed.');
}

echo '<!-- markerpdf:inline-image-flate-post-stream-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-inline-image-flate-post-stream-boundary-currentbase',
    'upstream_boundary' => 'PDF content-stream inline image BI/ID/EI tokenizer with native FlateDecode payload validation',
    'text_paragraphs' => $lines,
    'flate_member_surplus_contains_fake_ei' => str_contains($postStreamSurplus, ' EI '),
    'payload_excluded_from_visible_text' => $payloadExcluded,
    'post_stream_preview_rejected' => $postStreamPreviewRejected,
    'valid_flate_preview_decoded' => true,
    'valid_flate_decoded_sha256' => $validPreview['image_stream']['decoded_sha256'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
