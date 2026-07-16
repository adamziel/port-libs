<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Inline DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 690 Td (Clean WordPress Paragraph) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0 compressed bytes EI BT /F1 12 Tf 72 710 Td (Inline JPEG Noise) Tj ET \xff\xd9";

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
$noiseExcluded = !str_contains($plainText, 'Inline JPEG Noise') && !str_contains($plainText, 'JFIF');

if ($lines !== ['Inline DCT Import', 'Clean WordPress Paragraph'] || !$noiseExcluded) {
    throw new RuntimeException('Inline DCTDecode payload leaked into WordPress paragraph text.');
}

echo '<!-- markerpdf:inline-dctdecode-filter-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-inline-image-dctdecode-filter-boundary',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks',
    'stream_filters' => ['DCTDecode'],
    'paragraphs' => $lines,
    'jpeg_eoi_delimiter_guard' => true,
    'excluded_inline_jpeg_noise' => $noiseExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
