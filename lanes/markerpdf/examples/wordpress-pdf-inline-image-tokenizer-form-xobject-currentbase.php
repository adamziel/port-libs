<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pdfWithFormXObjectBoundary = static function (): string {
    $formContent = "BT /F1 12 Tf 0 0 Td (Visible Form XObject Boundary Text) Tj ET";
    $content = "BT /F1 12 Tf 72 720 Td (Before Form XObject Boundary) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Form XObject Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . "q\n"
        . "1 0 0 1 72 704 cm\n"
        . "/FormText Do\n"
        . "Q\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (After Form XObject Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> /XObject << /FormText 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Resources << /Font << /F1 5 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "%%EOF";
};

$extractor = new PdfTextExtractor();
$expected = [
    'Before Form XObject Boundary',
    'Visible Form XObject Boundary Text',
    'After Form XObject Boundary',
];
$lines = $extractor->extractTextLines($pdfWithFormXObjectBoundary());
$plainText = implode("\n", $lines);

$metadata = [
    'source' => 'native-pdf-inline-image-tokenizer-form-xobject-currentbase',
    'upstream_boundary' => 'markerPDF searchable PDF extraction preserves visible text after inline image payloads',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'form_xobject_text_imported' => $lines === $expected,
    'inline_payload_excluded' => !str_contains($plainText, 'Form XObject Payload Noise')
        && !str_contains($plainText, 'rawtail')
        && !str_contains($plainText, 'JBIG2Decode'),
    'form_do_operator_excluded' => !str_contains($plainText, '/FormText Do'),
];

if (
    $metadata['form_xobject_text_imported'] !== true
    || $metadata['inline_payload_excluded'] !== true
    || $metadata['form_do_operator_excluded'] !== true
) {
    throw new RuntimeException('Expected inline image tokenizer to preserve Form XObject text while excluding image payload bytes.');
}

echo '<!-- markerpdf:inline-image-tokenizer-form-xobject-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<section data-boundary=\"form-xobject\">\n";
foreach ($lines as $line) {
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
}
echo "</section>\n";
