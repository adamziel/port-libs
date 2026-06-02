<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$decodedContent = 'BT /F1 12 Tf 72 720 Td (Inline stream length repair) Tj ET' . "\n"
    . "endstream\nendobj\n"
    . "20 0 obj\n<< /Producer (Stream-owned fake object) >>\nendobj\n"
    . 'BT /F1 12 Tf 72 700 Td (Filter payload stays current) Tj ET';
$compressed = gzcompress($decodedContent, 0);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to build WordPress inline stream repair fixture.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Filter /FlateDecode /Length 5 0 R >>\nstream\n{$compressed}\nendstream\nendobj\n"
    . "5 0 obj\n" . strlen($compressed) . "\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-parser-inline-stream-length-filter-repair-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'filtered page /Contents stream with indirect /Length after payload keeps embedded endstream/endobj bytes inside the stream',
    'length_object_after_stream' => true,
    'filter_validated_boundary' => true,
    'compressed_payload_contains_fake_object_header' => str_contains($compressed, "\nendstream\nendobj\n20 0 obj"),
    'visible_text_imported' => $lines === ['Inline stream length repair', 'Filter payload stays current'],
    'stream_owned_fake_object_excluded' => !str_contains($plainText, 'Stream-owned fake object'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
