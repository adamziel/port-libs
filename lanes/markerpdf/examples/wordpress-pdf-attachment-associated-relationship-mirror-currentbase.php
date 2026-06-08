<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="relationship-mirror-source-smoke"/></wp-export>';
$orphanPayload = '{"review":"missing-associated-relationship-smoke"}';
$sourceChecksum = md5($sourcePayload);
$orphanChecksum = md5($orphanPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Associated Relationship Mirror Smoke Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF ["
    . "<< /Type /Filespec /AFRelationship /Source /EF << /F 11 0 R >> >> "
    . "<< /Type /Filespec /EF << /F 21 0 R >> >>"
    . "] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names ["
    . "(source.xml) << /Type /Filespec /Desc (Smoke source without relationship) /EF << /F 11 0 R >> >> "
    . "(orphan-review.json) << /Type /Filespec /Desc (Smoke review without relationship) /EF << /F 21 0 R >> >>"
    . "] >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260608122825Z) >> /Length " . strlen($sourcePayload) . " >>\n"
    . "stream\n{$sourcePayload}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($orphanPayload) . " /CheckSum <{$orphanChecksum}> /ModDate (D:20260608122826Z) >> /Length " . strlen($orphanPayload) . " >>\n"
    . "stream\n{$orphanPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$attachments = $summary['attachments'] ?? [];
$encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

if (($summary['attachment_count'] ?? null) !== 2 || count($attachments) !== 2) {
    throw new RuntimeException('Expected two mirrored associated attachment summaries.');
}
if (($attachments[0]['relationship'] ?? null) !== 'Source'
    || ($attachments[0]['relationship_status'] ?? null) !== 'standard_pdf_associated_file_relationship'
    || ($attachments[0]['associated_file_source'] ?? null) !== 'catalog_af'
    || ($attachments[1]['relationship_status'] ?? null) !== 'missing_pdf_associated_file_relationship'
    || array_key_exists('relationship', $attachments[1])
    || $plainText !== 'Associated Relationship Mirror Smoke Body'
    || !is_string($encodedSummary)
    || str_contains($encodedSummary, $sourcePayload)
    || str_contains($encodedSummary, $orphanPayload)
    || str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'missing-associated-relationship-smoke')
) {
    throw new RuntimeException('Expected associated relationship mirror metadata without payload or visible-text leakage.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf:attachment-associated-relationship-mirror ' . $htmlJson([
    'native_boundary' => 'catalog /AF FileSpec mirrors preserve standard and missing AFRelationship review status on EmbeddedFiles name-tree summaries',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'relationship_statuses' => array_map(static fn (array $attachment): ?string => $attachment['relationship_status'] ?? null, $attachments),
    'associated_file_sources' => array_map(static fn (array $attachment): ?string => $attachment['associated_file_source'] ?? null, $attachments),
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $attachments[0]) && !array_key_exists('bytes', $attachments[1]),
    'payload_text_excluded_from_visible_text' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'missing-associated-relationship-smoke'),
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($attachments as $attachment) {
    echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n";
    echo '<!-- markerpdf:attachment-review ' . $htmlJson([
        'filename' => $attachment['filename'] ?? null,
        'relationship' => $attachment['relationship'] ?? null,
        'relationship_status' => $attachment['relationship_status'] ?? null,
        'relationship_role' => $attachment['relationship_role'] ?? null,
        'associated_file_source' => $attachment['associated_file_source'] ?? null,
        'checksum_matches' => $attachment['checksum_matches'] ?? null,
    ]) . " -->\n\n";
}
