<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$leak = 'BT /F1 12 Tf 72 700 Td (WordPress inline CCF source alias leak) Tj ET';
$nativeBytes = "\x11\x22\x33 EI {$leak} \x00\x10\x01";
$payload = strtoupper(bin2hex($nativeBytes)) . '>';
$inlineDictionary = '/W 16 /H 1 /IM true /F [/AHx /CCF] '
    . '/DP [null << /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock true >>] '
    . '/D [1 0]';

$plan = (new PdfImageRenderer())->inlineImageReviewPlan($inlineDictionary, $payload);
$inline = $plan['inline_image'];

$before = 'BT /F1 12 Tf 72 720 Td (Before WordPress inline CCF alias) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After WordPress inline CCF alias) Tj ET';
$content = $before . "\nBI {$inlineDictionary} ID\n{$payload}\nEI\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$encodedPlan = json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '';

if (($inline['source_filters'] ?? null) !== ['AHx', 'CCF']) {
    throw new RuntimeException('Inline source filter aliases were not preserved for WordPress review.');
}
if (($inline['source_preview_only_filters'] ?? null) !== ['CCF']) {
    throw new RuntimeException('Inline source preview-only CCF alias was not preserved.');
}
if (($inline['source_ccitt_alias_used'] ?? null) !== true) {
    throw new RuntimeException('Inline CCF source alias was not marked as used.');
}
if (($plan['image_filters'] ?? null) !== ['ASCIIHexDecode', 'CCITTFaxDecode']) {
    throw new RuntimeException('Inline canonical filter stack changed unexpectedly.');
}
if (($inline['review_only_filters'] ?? null) !== ['CCITTFaxDecode']) {
    throw new RuntimeException('Inline canonical CCITT review-only boundary was not preserved.');
}
if (($inline['native_raster_decode'] ?? null) !== false) {
    throw new RuntimeException('Inline CCITT source alias should remain review-only.');
}
if (($plan['ccitt_fax_decode_boundary']['invalid_decode_parms'] ?? null) !== false) {
    throw new RuntimeException('Inline CCITT source alias DecodeParms should remain valid.');
}
if (!in_array('inline_ccitt_fax_source_alias_preserved', $plan['notes'] ?? [], true)) {
    throw new RuntimeException('Inline CCITT source alias review note is missing.');
}
if ($lines !== ['Before WordPress inline CCF alias', 'After WordPress inline CCF alias']) {
    throw new RuntimeException('Inline CCITT source alias payload changed WordPress text extraction.');
}
if (str_contains($plainText, 'WordPress inline CCF source alias leak') || str_contains($plainText, '/AHx') || str_contains($plainText, '/CCF')) {
    throw new RuntimeException('Inline CCITT source alias payload leaked into WordPress text.');
}
if (str_contains($encodedPlan, 'WordPress inline CCF source alias leak') || str_contains($encodedPlan, $nativeBytes)) {
    throw new RuntimeException('Inline CCITT source alias payload leaked into review metadata.');
}

echo json_encode([
    'source' => 'wordpress_pdf_inline_ccitt_source_alias_boundary_currentbase',
    'inline_source_filters' => $inline['source_filters'],
    'inline_source_preview_only_filters' => $inline['source_preview_only_filters'],
    'inline_source_filter_aliases' => $inline['source_filter_aliases'],
    'canonical_filters' => $plan['image_filters'],
    'review_only_filters' => $inline['review_only_filters'],
    'source_ccitt_alias_used' => $inline['source_ccitt_alias_used'],
    'visible_text_lines' => $lines,
    'payload_excluded_from_text' => !str_contains($plainText, 'WordPress inline CCF source alias leak'),
    'payload_excluded_from_review' => !str_contains($encodedPlan, 'WordPress inline CCF source alias leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
