<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payloads = [
    'alpha-source.xml' => '<wp-export><post id="alpha-leaf-order-smoke"/></wp-export>',
    'review-summary.xml' => '<wp-export><post id="review-leaf-order-smoke"/></wp-export>',
    'zulu-appendix.xml' => '<wp-export><post id="zulu-leaf-order-smoke"/></wp-export>',
];
$content = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Leaf Order Smoke) Tj ET';

$fileSpec = static function (int $fileSpecObject, int $streamObject, string $filename, string $description) use ($payloads): string {
    $payload = $payloads[$filename];
    $checksum = md5($payload);

    return "{$fileSpecObject} 0 obj\n"
        . "<< /Type /Filespec /F ({$filename}) /Desc ({$description}) /AFRelationship /Data /EF << /F {$streamObject} 0 R >> >>\n"
        . "endobj\n"
        . "{$streamObject} 0 obj\n"
        . "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260607101800Z) >> /Length " . strlen($payload) . " >>\n"
        . "stream\n{$payload}\nendstream\nendobj\n";
};

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 8 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Limits [(alpha-source.xml) (zulu-appendix.xml)] /Names [(zulu-appendix.xml) 30 0 R (alpha-source.xml) 20 0 R (review-summary.xml) 24 0 R] >>\nendobj\n"
    . $fileSpec(20, 21, 'alpha-source.xml', 'Alpha WordPress source attachment')
    . $fileSpec(24, 25, 'review-summary.xml', 'Review summary WordPress source attachment')
    . $fileSpec(30, 31, 'zulu-appendix.xml', 'Zulu appendix WordPress source attachment')
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

$expectedOrder = [
    'alpha-source.xml',
    'review-summary.xml',
    'zulu-appendix.xml',
];

if (
    ($summary['filenames'] ?? []) !== $expectedOrder
    || array_column($files, 'name') !== $expectedOrder
    || array_column($summary['attachments'] ?? [], 'file_spec_object_id') !== [20, 24, 30]
    || array_column($files, 'embedded_file_object') !== [21, 25, 31]
    || array_column($files, 'content') !== array_values($payloads)
    || $plainText !== 'Visible Attachment Leaf Order Smoke'
    || !is_string($summaryJson)
) {
    throw new RuntimeException('Expected EmbeddedFiles leaf /Names pairs to be sorted by byte key before WordPress import review.');
}

foreach ($payloads as $filename => $payload) {
    if (str_contains($summaryJson, $payload) || str_contains($plainText, $filename) || str_contains($plainText, $payload)) {
        throw new RuntimeException('Expected attachment payloads and name-tree keys to stay out of visible WordPress text.');
    }
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-attachment-leaf-names-order-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-embeddedfiles-name-tree-parser',
    'native_boundary' => 'EmbeddedFiles leaf Names pairs are ordered by decoded byte-string key before attachment review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'pdf_leaf_order' => ['zulu-appendix.xml', 'alpha-source.xml', 'review-summary.xml'],
    'review_order_from_key_sort' => $summary['filenames'],
    'embedded_file_order_matches_summary' => array_column($files, 'name') === $summary['filenames'],
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $summary['attachments'][0] ?? []),
    'visible_text_excludes_attachment_payloads' => true,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";
