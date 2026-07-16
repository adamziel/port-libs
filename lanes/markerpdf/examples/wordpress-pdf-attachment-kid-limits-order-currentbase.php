<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payloads = [
    'alpha-source.xml' => '<wp-export><post id="alpha-source-smoke"/></wp-export>',
    'deck-source.xml' => '<wp-export><post id="deck-source-smoke"/></wp-export>',
    'review-summary.xml' => '<wp-export><post id="review-summary-smoke"/></wp-export>',
    'same-lower-current.xml' => '<wp-export><post id="same-lower-current-smoke"/></wp-export>',
    'same-lower-narrow.xml' => '<wp-export><post id="same-lower-narrow-smoke"/></wp-export>',
    'zulu-appendix.xml' => '<wp-export><post id="zulu-appendix-smoke"/></wp-export>',
];
$content = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Limits Order Smoke) Tj ET';

$fileSpec = static function (int $fileSpecObject, int $streamObject, string $filename, string $description) use ($payloads): string {
    $payload = $payloads[$filename];
    $checksum = md5($payload);

    return "{$fileSpecObject} 0 obj\n"
        . "<< /Type /Filespec /F ({$filename}) /Desc ({$description}) /AFRelationship /Data /EF << /F {$streamObject} 0 R >> >>\n"
        . "endobj\n"
        . "{$streamObject} 0 obj\n"
        . "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($payload) . " >>\n"
        . "stream\n{$payload}\nendstream\nendobj\n";
};

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 8 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Limits [(alpha-source.xml) (zulu-appendix.xml)] /Kids [14 0 R 10 0 R 9 0 R 11 0 R 12 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /Limits [(alpha-source.xml) (deck-source.xml)] /Names [(alpha-source.xml) 20 0 R (deck-source.xml) 22 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Limits [(review-summary.xml) (review-summary.xml)] /Names [(review-summary.xml) 24 0 R] >>\nendobj\n"
    . "11 0 obj\n<< /Limits [(same-lower) (same-lower-wide.xml)] /Names [(same-lower-current.xml) 26 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Limits [(same-lower) (same-lower-z.xml)] /Names [(same-lower-narrow.xml) 28 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Limits [(zulu-appendix.xml) (zulu-appendix.xml)] /Names [(zulu-appendix.xml) 30 0 R] >>\nendobj\n"
    . $fileSpec(20, 21, 'alpha-source.xml', 'Alpha WordPress source attachment')
    . $fileSpec(22, 23, 'deck-source.xml', 'Deck WordPress source attachment')
    . $fileSpec(24, 25, 'review-summary.xml', 'Review summary WordPress source attachment')
    . $fileSpec(26, 27, 'same-lower-current.xml', 'Same lower current attachment')
    . $fileSpec(28, 29, 'same-lower-narrow.xml', 'Same lower narrow attachment')
    . $fileSpec(30, 31, 'zulu-appendix.xml', 'Zulu appendix attachment')
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

$expectedOrder = [
    'alpha-source.xml',
    'deck-source.xml',
    'review-summary.xml',
    'same-lower-current.xml',
    'same-lower-narrow.xml',
    'zulu-appendix.xml',
];

if (
    ($summary['filenames'] ?? []) !== $expectedOrder
    || array_column($files, 'name') !== $expectedOrder
    || array_column($summary['attachments'] ?? [], 'file_spec_object_id') !== [20, 22, 24, 26, 28, 30]
    || array_column($files, 'embedded_file_object') !== [21, 23, 25, 27, 29, 31]
    || array_column($files, 'content') !== array_values($payloads)
    || $plainText !== 'Visible Attachment Limits Order Smoke'
    || !is_string($summaryJson)
) {
    throw new RuntimeException('Expected EmbeddedFiles attachments to follow child /Limits ordering before WordPress import review.');
}

foreach ($payloads as $filename => $payload) {
    if (str_contains($summaryJson, $payload) || str_contains($plainText, $filename) || str_contains($plainText, $payload)) {
        throw new RuntimeException('Expected attachment payloads and name-tree keys to remain out of visible WordPress text.');
    }
}

echo '<!-- markerpdf-pdf-attachment-kid-limits-order-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-embeddedfiles-name-tree-parser',
    'native_boundary' => 'EmbeddedFiles name-tree Kids are ordered by effective child Limits before attachment review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'kid_order_from_pdf' => ['zulu-appendix.xml', 'review-summary.xml', 'alpha-source.xml', 'same-lower-current.xml', 'same-lower-narrow.xml'],
    'review_order_from_limits' => $summary['filenames'],
    'embedded_file_order_matches_summary' => array_column($files, 'name') === $summary['filenames'],
    'same_lower_source_order_preserved' => array_slice($summary['filenames'], 3, 2) === ['same-lower-current.xml', 'same-lower-narrow.xml'],
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $summary['attachments'][0] ?? []),
    'visible_text_excludes_attachment_payloads' => true,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($summary['attachments'] as $attachment) {
    echo '<li data-marker-attachment-source="' . htmlspecialchars((string) $attachment['source'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-attachment-stream="' . htmlspecialchars((string) $attachment['stream_object_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
