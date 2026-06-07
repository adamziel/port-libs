<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validPayload = '<wp-export><post id="valid-params-boundary"/></wp-export>';
$relatedPayload = '{"manifest":"valid-related-params-boundary"}';
$duplicatePayload = '<wp-export><post id="duplicate-params-should-not-count"/></wp-export>';
$duplicateRelatedPayload = 'DUPLICATE_PARAMS_RELATED_PAYLOAD_SHOULD_NOT_COUNT';
$validChecksum = md5($validPayload);
$relatedChecksum = md5($relatedPayload);
$duplicateChecksum = md5($duplicatePayload);
$duplicateRelatedChecksum = md5($duplicateRelatedPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Duplicate Params Attachment Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(valid-source.xml) 10 0 R (duplicate-params.xml) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (valid-source.xml) /Desc (Valid Params WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> /RF << /F [(duplicate-related.json) 12 0 R (valid-related.json) 13 0 R] >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260607021200Z) >> /Length " . strlen($validPayload) . " >>\nstream\n{$validPayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($duplicateRelatedPayload) . " /CheckSum <{$duplicateRelatedChecksum}> >> /Params << /Size 1 /CheckSum <00000000000000000000000000000000> >> /Length " . strlen($duplicateRelatedPayload) . " >>\nstream\n{$duplicateRelatedPayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($relatedPayload) . " /CheckSum <{$relatedChecksum}> /CreationDate (D:20260607021201Z) >> /Length " . strlen($relatedPayload) . " >>\nstream\n{$relatedPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (duplicate-params.xml) /Desc (Duplicate Params WordPress source) /AFRelationship /Source /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicatePayload) . " /CheckSum <{$duplicateChecksum}> /ModDate (D:20260607021100Z) >> /Params << /Size 2 /CheckSum <11111111111111111111111111111111> /ModDate (D:20260607021101Z) >> /Length " . strlen($duplicatePayload) . " >>\nstream\n{$duplicatePayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;
$related = is_array($attachment) ? ($attachment['related_files'][0] ?? null) : null;
$file = $files[0] ?? null;
$fileRelated = is_array($file) ? ($file['related_files'][0] ?? null) : null;

if (!is_array($attachment)
    || !is_array($related)
    || !is_array($file)
    || !is_array($fileRelated)
    || ($summary['attachment_count'] ?? null) !== 1
    || ($attachment['filename'] ?? null) !== 'valid-source.xml'
    || ($attachment['checksum_matches'] ?? null) !== true
    || ($related['related_filename'] ?? null) !== 'valid-related.json'
    || ($related['checksum_matches'] ?? null) !== true
    || count($files) !== 1
    || ($file['filename'] ?? null) !== 'valid-source.xml'
    || ($file['content'] ?? null) !== $validPayload
    || ($fileRelated['related_filename'] ?? null) !== 'valid-related.json'
    || str_contains($summaryJson, 'duplicate-params.xml')
    || str_contains($summaryJson, 'duplicate-related.json')
    || str_contains($summaryJson, $duplicatePayload)
    || str_contains($summaryJson, $duplicateRelatedPayload)
    || str_contains($summaryJson, $duplicateChecksum)
    || str_contains($summaryJson, $duplicateRelatedChecksum)
    || str_contains($summaryJson, $validPayload)
    || str_contains($summaryJson, $relatedPayload)
    || str_contains($filesJson, $duplicatePayload)
    || str_contains($filesJson, $duplicateRelatedPayload)
    || $plainText !== 'Duplicate Params Attachment Body'
) {
    throw new RuntimeException('Expected duplicate EmbeddedFile /Params dictionaries to fail closed before attachment review.');
}

echo '<!-- markerpdf-pdf-attachment-params-duplicate-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-embeddedfile-params-boundary',
    'native_boundary' => 'duplicate EmbeddedFile stream /Params dictionaries are rejected before attachment metadata review',
    'attachment_count' => $summary['attachment_count'],
    'filename' => $attachment['filename'],
    'related_filename' => $related['related_filename'],
    'valid_checksum_matches' => $attachment['checksum_matches'],
    'valid_related_checksum_matches' => $related['checksum_matches'],
    'duplicate_params_attachment_excluded' => !str_contains($summaryJson, 'duplicate-params.xml')
        && !str_contains($filesJson, $duplicatePayload),
    'duplicate_params_related_file_excluded' => !str_contains($summaryJson, 'duplicate-related.json')
        && !str_contains($filesJson, $duplicateRelatedPayload),
    'attachment_payload_omitted_from_summary' => !str_contains($summaryJson, $validPayload),
    'visible_text_excludes_attachment_payloads' => $plainText === 'Duplicate Params Attachment Body',
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
