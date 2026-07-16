<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = "BT /F1 12 Tf 72 720 Td (Before Pattern Tint Import) Tj ET\n"
    . "BI /W 8 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x80 EI\n"
    . "/CSPattern cs\n"
    . "0.5 0.25 0.75 /P1 scn\n"
    . "BT /F1 12 Tf 72 704 Td (Visible Pattern Tint Import) Tj ET\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 688 Td (After Pattern Tint Import) Tj ET";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> /ColorSpace << /CSPattern [/Pattern /DeviceRGB] >> /Pattern << /P1 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /Pattern /PatternType 1 /PaintType 2 /TilingType 1 /BBox [0 0 8 8] /XStep 8 /YStep 8 /Resources << >> /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$lines = $extractor->extractTextLines($pdf);

echo '<!-- markerpdf-inline-image-pattern-tint-tokenizer-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'preview-only inline image sample-floor EI reopens before uncolored Pattern tint scn operands and visible WordPress text',
    'before_text_preserved' => str_contains($plainText, 'Before Pattern Tint Import'),
    'pattern_tint_text_preserved' => str_contains($plainText, 'Visible Pattern Tint Import'),
    'after_text_preserved' => str_contains($plainText, 'After Pattern Tint Import'),
    'inline_image_payload_excluded' => !str_contains($plainText, "\x80 EI"),
    'pattern_color_operands_excluded' => !str_contains($plainText, 'CSPattern')
        && !str_contains($plainText, '0.5 0.25 0.75 /P1 scn'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
