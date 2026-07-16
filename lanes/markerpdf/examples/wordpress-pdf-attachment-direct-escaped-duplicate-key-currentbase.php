<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validPayload = '<wp-export><post id="valid-direct-escaped-duplicate-key-boundary"/></wp-export>';
$duplicateFileSpecPayload = '<wp-export><post id="duplicate-inline-filespec-f-key"/></wp-export>';
$duplicateEfPayload = '<wp-export><post id="duplicate-inline-ef-f-key"/></wp-export>';
$validChecksum = md5($validPayload);
$duplicateFileSpecChecksum = md5($duplicateFileSpecPayload);
$duplicateEfChecksum = md5($duplicateEfPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Direct Escaped Duplicate Key Boundary Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names ["
    . "(inline-duplicate-filespec.xml) << /Type /Filespec /F (current-inline-filespec.xml) /#46 (stale-inline-filespec.xml) /Desc (Escaped duplicate FileSpec key) /AFRelationship /Source /EF << /F 11 0 R >> >> "
    . "(inline-duplicate-ef.xml) << /Type /Filespec /F (inline-duplicate-ef.xml) /Desc (Escaped duplicate EF key) /AFRelationship /Source /EF << /F 21 0 R /#46 22 0 R >> >> "
    . "(valid-inline.xml) << /Type /Filespec /F (valid-inline.xml) /Desc (Valid inline FileSpec after escaped duplicates) /AFRelationship /Data /EF << /F 31 0 R >> >>"
    . "] >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateFileSpecPayload) . " /CheckSum <{$duplicateFileSpecChecksum}> >> /Length " . strlen($duplicateFileSpecPayload) . " >>\n"
    . "stream\n{$duplicateFileSpecPayload}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "22 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateEfPayload) . " /CheckSum <{$duplicateEfChecksum}> >> /Length " . strlen($duplicateEfPayload) . " >>\n"
    . "stream\n{$duplicateEfPayload}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260605222157Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;
$file = $files[0] ?? null;

if (!is_array($attachment)
    || !is_array($file)
    || ($summary['attachment_count'] ?? null) !== 1
    || count($files) !== 1
    || ($attachment['filename'] ?? null) !== 'valid-inline.xml'
    || ($attachment['relationship'] ?? null) !== 'Data'
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $attachment)
    || ($file['content'] ?? null) !== $validPayload
    || str_contains($summaryJson, 'stale-inline-filespec.xml')
    || str_contains($summaryJson, 'inline-duplicate-ef.xml')
    || str_contains($summaryJson, $duplicateFileSpecPayload)
    || str_contains($summaryJson, $duplicateEfPayload)
    || str_contains($summaryJson, $validPayload)
    || str_contains($filesJson, $duplicateFileSpecPayload)
    || str_contains($filesJson, $duplicateEfPayload)
    || $plainText !== 'Direct Escaped Duplicate Key Boundary Body'
) {
    throw new RuntimeException('Expected escaped duplicate direct FileSpec keys to fail closed before WordPress attachment rendering.');
}

echo "<!-- markerpdf-pdf-attachment-direct-escaped-duplicate-key " . htmlspecialchars(json_encode([
    'native_boundary' => 'direct inline EmbeddedFiles FileSpec escaped duplicate key preflight',
    'attachment_count' => $summary['attachment_count'],
    'embedded_file_count' => count($files),
    'filename' => $attachment['filename'],
    'relationship' => $attachment['relationship'],
    'relationship_role' => $attachment['relationship_role'],
    'checksum_matches' => $attachment['checksum_matches'],
    'escaped_duplicate_filespec_rejected' => !str_contains($summaryJson, 'stale-inline-filespec.xml'),
    'escaped_duplicate_ef_rejected' => !str_contains($summaryJson, 'inline-duplicate-ef.xml'),
    'payload_omitted_from_summary' => !str_contains($summaryJson, $validPayload),
    'visible_text_preserved' => $plainText === 'Direct Escaped Duplicate Key Boundary Body',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li data-marker-attachment-sha256="'
    . htmlspecialchars((string) $attachment['sha256'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">'
    . htmlspecialchars(
        $attachment['filename_storage_name'] . ' (direct inline FileSpec boundary, '
            . $attachment['relationship'] . ', '
            . $attachment['content_type'] . ', '
            . $attachment['byte_length']
            . ' bytes)',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    )
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
