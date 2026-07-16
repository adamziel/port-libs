<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validPayload = '<wp-export><post id="valid-direct-nametree-filespec-smoke"/></wp-export>';
$duplicateFileSpecPayload = '<wp-export><post id="duplicate-direct-nametree-filespec-smoke"/></wp-export>';
$duplicateEfPayload = '<wp-export><post id="duplicate-direct-nametree-ef-smoke"/></wp-export>';
$duplicateEfDecoyPayload = '<wp-export><post id="duplicate-direct-nametree-ef-decoy-smoke"/></wp-export>';
$validChecksum = md5($validPayload);
$duplicateFileSpecChecksum = md5($duplicateFileSpecPayload);
$duplicateEfChecksum = md5($duplicateEfPayload);
$duplicateEfDecoyChecksum = md5($duplicateEfDecoyPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Direct NameTree FileSpec Smoke Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles << /Names ["
    . "(inline-duplicate-filespec.xml) << /Type /Filespec /F (current-inline.xml) /F (stale-inline.xml) /Desc (Duplicate inline EmbeddedFiles FileSpec) /AFRelationship /Source /EF << /F 11 0 R >> >> "
    . "(inline-duplicate-ef.xml) << /Type /Filespec /F (inline-duplicate-ef.xml) /Desc (Duplicate inline EmbeddedFiles EF keys) /AFRelationship /Supplement /EF << /F 21 0 R /F 22 0 R >> >> "
    . "(valid-inline-direct.xml) << /Type /Filespec /F (valid-inline-direct.xml) /Desc (Valid inline EmbeddedFiles source) /AFRelationship /Data /EF << /F 31 0 R >> >>"
    . "] >> >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateFileSpecPayload) . " /CheckSum <{$duplicateFileSpecChecksum}> >> /Length " . strlen($duplicateFileSpecPayload) . " >>\n"
    . "stream\n{$duplicateFileSpecPayload}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateEfPayload) . " /CheckSum <{$duplicateEfChecksum}> >> /Length " . strlen($duplicateEfPayload) . " >>\n"
    . "stream\n{$duplicateEfPayload}\nendstream\nendobj\n"
    . "22 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateEfDecoyPayload) . " /CheckSum <{$duplicateEfDecoyChecksum}> >> /Length " . strlen($duplicateEfDecoyPayload) . " >>\n"
    . "stream\n{$duplicateEfDecoyPayload}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260607000037Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? [];

if (($summary['attachment_count'] ?? null) !== 1
    || ($summary['filenames'] ?? []) !== ['valid-inline-direct.xml']
    || count($files) !== 1
    || ($attachment['stream_object_id'] ?? null) !== 31
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $attachment)
    || str_contains($summaryJson, 'stale-inline.xml')
    || str_contains($summaryJson, 'inline-duplicate-ef.xml')
    || str_contains($summaryJson, $validPayload)
    || str_contains($summaryJson, $duplicateFileSpecPayload)
    || str_contains($summaryJson, $duplicateEfPayload)
    || str_contains($summaryJson, $duplicateEfDecoyPayload)
    || str_contains($summaryJson, $duplicateFileSpecChecksum)
    || str_contains($summaryJson, $duplicateEfChecksum)
    || str_contains($summaryJson, $duplicateEfDecoyChecksum)
    || str_contains($filesJson, $duplicateFileSpecPayload)
    || str_contains($filesJson, $duplicateEfPayload)
    || str_contains($filesJson, $duplicateEfDecoyPayload)
    || $plainText !== 'Direct NameTree FileSpec Smoke Body'
) {
    throw new RuntimeException('Expected direct inline EmbeddedFiles FileSpec duplicate keys to fail closed before WordPress attachment summary import.');
}

echo '<!-- markerpdf-pdf-attachment-direct-nametree-filespec-boundary ' . htmlspecialchars(json_encode([
    'native_boundary' => 'direct inline EmbeddedFiles name-tree FileSpec duplicate-key fail-closed attachment summary',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'valid_inline_filespec_kept' => ($attachment['filename'] ?? null) === 'valid-inline-direct.xml',
    'duplicate_inline_filespec_rejected' => !str_contains($summaryJson, 'stale-inline.xml'),
    'duplicate_inline_ef_rejected' => !str_contains($summaryJson, 'inline-duplicate-ef.xml'),
    'payload_bytes_omitted_from_summary' => !str_contains($summaryJson, $validPayload),
    'duplicate_payloads_excluded' => !str_contains($summaryJson, $duplicateFileSpecPayload)
        && !str_contains($summaryJson, $duplicateEfPayload)
        && !str_contains($summaryJson, $duplicateEfDecoyPayload),
    'visible_text_preserved' => $plainText === 'Direct NameTree FileSpec Smoke Body',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:file {\"href\":\"media/" . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\"} -->\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
