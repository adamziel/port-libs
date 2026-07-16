<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = '<wp-export><post id="mac-params-source"/></wp-export>';
$resourceForkPayload = "BT /F1 12 Tf 72 720 Td (Mac Resource Fork Payload Leak) Tj ET";
$payloadChecksum = md5($payload);
$resourceForkChecksum = md5($resourceForkPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Mac Params Attachment Body) Tj ET';
$fileType = unpack('N', 'TEXT')[1];
$creator = unpack('N', 'MPRT')[1];

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(mac-source.xml) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (mac-source.xml) /Desc (Mac params WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$payloadChecksum}> /ModDate (D:20260605184200Z) /Mac << /Subtype {$fileType} /Creator {$creator} /ResFork 12 0 R >> >> /Length " . strlen($payload) . " >>\n"
    . "stream\n{$payload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Params << /Size " . strlen($resourceForkPayload) . " /CheckSum <{$resourceForkChecksum}> /CreationDate (D:20260605184201Z) >> /Length " . strlen($resourceForkPayload) . " >>\n"
    . "stream\n{$resourceForkPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;
$embeddedFile = $files[0] ?? null;
$mac = is_array($attachment) ? ($attachment['mac_file_info'] ?? null) : null;
$resourceFork = is_array($mac) ? ($mac['resource_fork'] ?? null) : null;
$fileMac = is_array($embeddedFile) ? ($embeddedFile['mac_file_info'] ?? null) : null;

if (!is_array($attachment)
    || !is_array($embeddedFile)
    || !is_array($mac)
    || !is_array($resourceFork)
    || !is_array($fileMac)
    || ($summary['attachment_count'] ?? null) !== 1
    || ($attachment['filename'] ?? null) !== 'mac-source.xml'
    || ($attachment['checksum_matches'] ?? null) !== true
    || ($mac['file_type']['four_char_code'] ?? null) !== 'TEXT'
    || ($mac['creator']['four_char_code'] ?? null) !== 'MPRT'
    || ($resourceFork['stream_object_id'] ?? null) !== 12
    || ($resourceFork['checksum_matches'] ?? null) !== true
    || ($resourceFork['payload_bytes_included'] ?? null) !== false
    || array_key_exists('bytes', $resourceFork)
    || ($fileMac['resource_fork']['payload_included'] ?? null) !== false
    || str_contains($summaryJson, $payload)
    || str_contains($summaryJson, $resourceForkPayload)
    || str_contains($filesJson, $resourceForkPayload)
    || ($embeddedFile['content'] ?? null) !== $payload
    || $plainText !== 'Mac Params Attachment Body'
) {
    throw new RuntimeException('Expected EmbeddedFile Mac /Params review metadata without resource-fork payload promotion.');
}

echo '<!-- markerpdf-pdf-attachment-mac-params-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-embeddedfile-mac-params-review',
    'native_boundary' => 'EmbeddedFile /Params /Mac type creator and resource-fork metadata review',
    'attachment_count' => $summary['attachment_count'],
    'filename' => $attachment['filename'],
    'mac_file_type' => $mac['file_type']['four_char_code'],
    'mac_creator' => $mac['creator']['four_char_code'],
    'resource_fork_object' => $resourceFork['stream_object_id'],
    'resource_fork_checksum_matches' => $resourceFork['checksum_matches'],
    'resource_fork_payload_omitted' => !str_contains($summaryJson, $resourceForkPayload)
        && !str_contains($filesJson, $resourceForkPayload),
    'attachment_payload_omitted_from_summary' => !str_contains($summaryJson, $payload),
    'visible_text_excludes_attachment_payloads' => $plainText === 'Mac Params Attachment Body',
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
