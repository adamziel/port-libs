<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pdfWithIndirectProperty = static function (string $markedContentOperator): string {
    $label = $markedContentOperator === 'BDC' ? 'BDC' : 'DP';
    $markedContent = $markedContentOperator === 'BDC'
        ? "/Span 6 0 R BDC\nBT /F1 12 Tf 72 704 Td (Visible Indirect BDC Text) Tj ET EMC\n"
        : "/Span 6 0 R DP\nBT /F1 12 Tf 72 704 Td (Visible Indirect DP Text) Tj ET\n";
    $content = "BT /F1 12 Tf 72 720 Td (Before Indirect {$label} Boundary) Tj ET\n"
        . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
        . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Indirect {$label} Payload Noise) Tj ET rawtail\n"
        . "EI\n"
        . $markedContent
        . "EI\n"
        . "BT /F1 12 Tf 72 688 Td (After Indirect {$label} Boundary) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /MCID 9 >>\nendobj\n"
        . "%%EOF";
};

$extractor = new PdfTextExtractor();
$cases = [
    'BDC' => [
        'expected' => [
            'Before Indirect BDC Boundary',
            'Visible Indirect BDC Text',
            'After Indirect BDC Boundary',
        ],
        'noise' => 'Indirect BDC Payload Noise',
    ],
    'DP' => [
        'expected' => [
            'Before Indirect DP Boundary',
            'Visible Indirect DP Text',
            'After Indirect DP Boundary',
        ],
        'noise' => 'Indirect DP Payload Noise',
    ],
];

$metadata = [
    'source' => 'native-pdf-inline-image-tokenizer-indirect-property-currentbase',
    'upstream_boundary' => 'markerPDF searchable PDF text extraction keeps inline image bytes separate from following marked-content operators',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];
$allLines = [];

foreach ($cases as $operator => $case) {
    $lines = $extractor->extractTextLines($pdfWithIndirectProperty($operator));
    $plainText = implode("\n", $lines);
    $metadata[strtolower($operator) . '_visible_text_imported'] = $lines === $case['expected'];
    $metadata[strtolower($operator) . '_inline_payload_excluded'] = !str_contains($plainText, $case['noise'])
        && !str_contains($plainText, 'rawtail')
        && !str_contains($plainText, 'JBIG2Decode');
    $metadata[strtolower($operator) . '_indirect_reference_tokens_excluded'] = !str_contains(
        $plainText,
        "/Span 6 0 R {$operator}"
    );
    $allLines[$operator] = $lines;
}

if (
    ($metadata['bdc_visible_text_imported'] ?? false) !== true
    || ($metadata['bdc_inline_payload_excluded'] ?? false) !== true
    || ($metadata['bdc_indirect_reference_tokens_excluded'] ?? false) !== true
    || ($metadata['dp_visible_text_imported'] ?? false) !== true
    || ($metadata['dp_inline_payload_excluded'] ?? false) !== true
    || ($metadata['dp_indirect_reference_tokens_excluded'] ?? false) !== true
) {
    throw new RuntimeException('Expected indirect marked-content property boundaries to preserve visible WordPress text and exclude inline image payload bytes.');
}

echo '<!-- markerpdf:inline-image-tokenizer-indirect-property-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($allLines as $operator => $lines) {
    echo '<section data-boundary="' . htmlspecialchars($operator, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\">\n";
    foreach ($lines as $line) {
        echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    }
    echo "</section>\n";
}
