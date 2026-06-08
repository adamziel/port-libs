<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfImageRenderer.php';
require_once __DIR__ . '/../src/BboxGeometry.php';
require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pdfWithStructuredInlineImageTail = static function (string $dictionary, string $label): string {
    $content = "BT /F1 12 Tf 72 720 Td (Before {$label} Tail Inline) Tj ET\n"
        . "BI {$dictionary} ID\n"
        . "\x7f EI BT /F1 12 Tf 72 690 Td ({$label} Tail Inline Noise) Tj ET rawtail\n"
        . "EI\n"
        . "BT /F1 12 Tf 72 704 Td (After {$label} Tail Inline) Tj ET";

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$cases = [
    'literal' => '/W 1 /H 1 /CS /G /BPC 8 /D [1 0] (BadTail) /F /MalformedPreview',
    'hex' => '/W 1 /H 1 /CS /G /BPC 8 /D [1 0] <4261645461696C> /F /MalformedPreview',
    'dictionary' => '/W 1 /H 1 /CS /G /BPC 8 /D [1 0] << /BadTail true >> /F /MalformedPreview',
    'array' => '/W 1 /H 1 /CS /G /BPC 8 /D [1 0] [9 9] /F /MalformedPreview',
];

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$metadata = [
    'source' => 'native-pdf-inline-image-tokenizer-structured-tail-currentbase',
    'upstream_boundary' => 'markerPDF searchable PDF extraction keeps inline image raster bytes out of text before later PDF operators',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'structured_tail_payloads_excluded' => true,
    'structured_tail_dictionary_operand_review_only' => true,
    'structured_tail_preview_failed_closed' => true,
    'imported_lines' => [],
];

foreach ($cases as $label => $dictionary) {
    $lines = $extractor->extractTextLines($pdfWithStructuredInlineImageTail($dictionary, ucfirst($label)));
    $plainText = implode("\n", $lines);
    $plan = $renderer->inlineImageReviewPlan($dictionary, "\x7f");
    $metadata['imported_lines'][$label] = $lines;
    $metadata['structured_tail_payloads_excluded'] = $metadata['structured_tail_payloads_excluded']
        && count($lines) === 2
        && $lines[0] === 'Before ' . ucfirst($label) . ' Tail Inline'
        && $lines[1] === 'After ' . ucfirst($label) . ' Tail Inline'
        && !str_contains($plainText, ucfirst($label) . ' Tail Inline Noise')
        && !str_contains($plainText, 'rawtail')
        && !str_contains($plainText, 'MalformedPreview');
    $metadata['structured_tail_dictionary_operand_review_only'] = $metadata['structured_tail_dictionary_operand_review_only']
        && ($plan['inline_image_dictionary_operand_invalid'] ?? false) === true
        && in_array('inline_image_dictionary_operand_review_only', $plan['notes'] ?? [], true);

    try {
        $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, "\x7f", [], 1);
        $metadata['structured_tail_preview_failed_closed'] = false;
    } catch (InvalidArgumentException) {
    }
}

if (
    $metadata['structured_tail_payloads_excluded'] !== true
    || $metadata['structured_tail_dictionary_operand_review_only'] !== true
    || $metadata['structured_tail_preview_failed_closed'] !== true
) {
    throw new RuntimeException('Expected structured inline image dictionary tails to remain review-only and payload-excluded.');
}

echo '<!-- markerpdf:inline-image-tokenizer-structured-tail-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<section data-boundary=\"inline-image-structured-tail\">\n";
foreach ($metadata['imported_lines'] as $label => $lines) {
    echo '<h2>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
    foreach ($lines as $line) {
        echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    }
}
echo "</section>\n";
