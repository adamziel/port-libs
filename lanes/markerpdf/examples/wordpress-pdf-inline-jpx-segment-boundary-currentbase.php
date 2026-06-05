<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jpxPayload = static function (): string {
    $leak = 'BT /F1 12 Tf 72 690 Td (Inline JPX Segment Noise) Tj ET';
    $segmentPayload = "SIZ segment before false EOC \xff\xd9 EI {$leak} still inside segment";

    return "\xff\x4f"
        . "\xff\x51" . pack('n', strlen($segmentPayload) + 2) . $segmentPayload
        . "\xff\xd9";
};

$content = "BT /F1 12 Tf 72 720 Td (Before JPX Segment Import) Tj ET\n"
    . "BI /W 1 /H 1 /CS /RGB /BPC 8 /F /JPXDecode ID\n"
    . $jpxPayload() . "\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After JPX Segment Import) Tj ET";

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

echo '<!-- markerpdf-inline-jpx-segment-boundary-currentbase ' . htmlspecialchars(json_encode([
    'native_boundary' => 'inline JPXDecode codestream marker-segment lengths are honored before accepting EOC/EI tokenizer boundaries',
    'false_jpx_eoc_inside_length_segment_ignored' => $lines === [
        'Before JPX Segment Import',
        'After JPX Segment Import',
    ],
    'inline_jpx_payload_excluded_from_text' => !str_contains($plainText, 'Inline JPX Segment Noise')
        && !str_contains($plainText, 'SIZ segment before false EOC')
        && !str_contains($plainText, 'still inside segment'),
    'visible_text_imported' => str_contains($plainText, 'Before JPX Segment Import')
        && str_contains($plainText, 'After JPX Segment Import'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'paragraphs' => $lines,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($line, ENT_QUOTES) . "</p>\n<!-- /wp:paragraph -->\n";
}
