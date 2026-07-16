<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before Inline DCT Tail Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Inline DCT Tail Import) Tj ET';
$leak = 'BT /F1 12 Tf 72 700 Td (WordPress Inline DCT Tail Leak) Tj ET';
$dictionary = '/W 1 /H 1 /CS /RGB /BPC 8 /F [/DCT] /Crypt /DP << /ColorTransform 1 >>';
$payload = "\xff\xd8\xff\xe0JFIF\0 inline filter tail bytes EI {$leak} still image bytes \xff\xd9";
$content = $before . "\n"
    . "BI {$dictionary} ID\n{$payload}\nEI\n"
    . $after;
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
$plan = $renderer->inlineImageReviewPlan($dictionary, $payload);

$expected = ['Before Inline DCT Tail Import', 'After Inline DCT Tail Import'];
$payloadExcluded = !str_contains($plainText, 'WordPress Inline DCT Tail Leak')
    && !str_contains($plainText, 'JFIF')
    && !str_contains($plainText, 'still image bytes')
    && !str_contains($plainText, 'Crypt');
$decodeParmsPreserved = ($plan['image_filter_details'][1]['decode_parms']['color_transform'] ?? null) === 1
    && (($plan['image_filter_details'][1]['decode_parms']['valid_color_transform'] ?? false) === true);
$filterTailRejected = ($plan['image_filters'] ?? []) === ['MalformedFilterOperand', 'DCTDecode']
    && (($plan['image_filter_boundary']['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands')
    && (($plan['image_filter_boundary']['malformed_filter_operand_count'] ?? null) === 1)
    && (($plan['inline_image_dictionary_operand_invalid'] ?? false) === true)
    && (($plan['inline_image']['native_raster_decode'] ?? true) === false);

if ($lines !== $expected || !$payloadExcluded || !$decodeParmsPreserved || !$filterTailRejected) {
    throw new RuntimeException('Inline DCTDecode filter-tail smoke leaked payload bytes or lost DecodeParms review metadata.');
}

echo '<!-- markerpdf:pdf-inline-dctdecode-filter-tail-decodeparms-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-inline-dctdecode-filter-tail-decodeparms-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'inline_filter_tail_rejected' => true,
    'stream_filters' => $plan['image_filters'] ?? [],
    'preview_only_filters' => $plan['image_filter_boundary']['preview_only_filters'] ?? [],
    'filter_operand_policy' => $plan['image_filter_boundary']['filter_operand_policy'] ?? null,
    'malformed_filter_operand_count' => $plan['image_filter_boundary']['malformed_filter_operand_count'] ?? null,
    'dctdecode_color_transform' => $plan['image_filter_details'][1]['decode_parms']['color_transform'] ?? null,
    'decodeparms_preserved_after_filter_tail' => $decodeParmsPreserved,
    'inline_dictionary_operand_invalid' => $plan['inline_image_dictionary_operand_invalid'] ?? null,
    'native_raster_decode' => $plan['inline_image']['native_raster_decode'] ?? null,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
