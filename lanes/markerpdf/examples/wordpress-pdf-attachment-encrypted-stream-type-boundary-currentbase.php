<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$decoyPayload = '<wp-export><post id="encrypted-xobject-decoy-smoke"/></wp-export>';
$validPayload = '<wp-export><post id="encrypted-embedded-source-smoke"/></wp-export>';
$decoyChecksum = md5($decoyPayload);
$validChecksum = md5($validPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Encrypted Attachment Stream Type Smoke Body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(encrypted-xobject-decoy.xml) 10 0 R (valid-encrypted-source.xml) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (encrypted-xobject-decoy.xml) /Desc (Encrypted typed stream decoy smoke) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /Params << /Size " . strlen($decoyPayload) . " /CheckSum <{$decoyChecksum}> >> /Length " . strlen($decoyPayload) . " >>\n"
    . "stream\n{$decoyPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (valid-encrypted-source.xml) /Desc (Encrypted valid EmbeddedFile source smoke) /AFRelationship /Source /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608232423Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -44 /EncryptMetadata true"
    . " /CF << /ClearStrings << /CFM /None /AuthEvent /DocOpen >> /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> >>"
    . " /StmF /ClearStrings /StrF /ClearStrings /EFF /StdCF >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 30 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? [];
$file = $files[0] ?? [];

if (($summary['attachment_count'] ?? null) !== 1
    || ($summary['total_bytes'] ?? null) !== 0
    || ($summary['filenames'] ?? null) !== ['valid-encrypted-source.xml']
    || ($attachment['stream_object_id'] ?? null) !== 21
    || ($attachment['encrypted_payload_suppressed'] ?? null) !== true
    || ($file['embedded_file_object'] ?? null) !== 21
    || ($file['encrypted_payload_suppressed'] ?? null) !== true
    || array_key_exists('content', $file)
    || array_key_exists('bytes', $attachment)
    || $plainText !== ''
    || str_contains($summaryJson, 'encrypted-xobject-decoy.xml')
    || str_contains($summaryJson, 'Encrypted typed stream decoy')
    || str_contains($summaryJson, $decoyPayload)
    || str_contains($summaryJson, $validPayload)
    || str_contains($summaryJson, $decoyChecksum)
    || str_contains($summaryJson, $validChecksum)
    || str_contains($filesJson, 'encrypted-xobject-decoy.xml')
    || str_contains($filesJson, $decoyPayload)
    || str_contains($filesJson, $validPayload)
) {
    throw new RuntimeException('Expected encrypted typed non-EmbeddedFile EF stream to be excluded before WordPress attachment review.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-attachment-encrypted-stream-type-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-encrypted-filespec-stream-type-review',
    'native_boundary' => 'encrypted FileSpec /EF rows still validate /Type /EmbeddedFile before WordPress attachment summaries',
    'attachment_count' => $summary['attachment_count'],
    'embedded_file_count' => count($files),
    'filename' => $attachment['filename'] ?? null,
    'stream_object_id' => $attachment['stream_object_id'] ?? null,
    'xobject_decoy_excluded' => !str_contains($summaryJson, 'encrypted-xobject-decoy.xml')
        && !str_contains($filesJson, 'encrypted-xobject-decoy.xml'),
    'encrypted_payload_suppressed' => $attachment['encrypted_payload_suppressed'] ?? null,
    'payload_hash_available' => $attachment['encryption_policy']['payload_hash_available'] ?? null,
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $attachment),
    'embedded_file_content_omitted' => !array_key_exists('content', $file),
    'visible_text_blocked_without_decryption' => $plainText === '',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo "<p>Encrypted PDF attachments require review before import. Typed non-EmbeddedFile streams are excluded, and valid embedded-file payload bytes stay suppressed until decryption is available.</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) ($attachment['filename_storage_name'] ?? 'valid-encrypted-source.xml'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) ($attachment['filename_storage_name'] ?? 'valid-encrypted-source.xml'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) ($attachment['filename'] ?? 'valid-encrypted-source.xml'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
