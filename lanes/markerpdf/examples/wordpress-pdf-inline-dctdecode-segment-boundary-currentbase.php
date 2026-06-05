<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before Inline APP DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Inline APP DCT Import) Tj ET';
$segmentLeak = 'BT /F1 12 Tf 72 700 Td (WordPress Inline APP DCT Leak) Tj ET';
$segmentPayload = "APP segment bytes before false EOI \xff\xd9 EI {$segmentLeak} still inside segment";
$jpegPayload = "\xff\xd8"
    . "\xff\xe1" . pack('n', strlen($segmentPayload) + 2) . $segmentPayload
    . "\xff\xd9";
$content = $before . "\n"
    . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /DCTDecode ID\n{$jpegPayload}\nEI\n"
    . $after;

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
$payloadExcluded = !str_contains($plainText, 'WordPress Inline APP DCT Leak')
    && !str_contains($plainText, 'APP segment bytes')
    && !str_contains($plainText, 'still inside segment');

if ($lines !== ['Before Inline APP DCT Import', 'After Inline APP DCT Import'] || !$payloadExcluded) {
    throw new RuntimeException('Inline DCTDecode APP-segment boundary leaked JPEG payload bytes into WordPress import.');
}

echo '<!-- markerpdf:pdf-inline-dctdecode-segment-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-inline-dctdecode-segment-boundary',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks',
    'stream_filters' => ['DCTDecode'],
    'jpeg_segment_length_boundary' => true,
    'fake_inline_ei_after_false_eoi_ignored' => true,
    'inline_dctdecode_payload_excluded_from_text' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
