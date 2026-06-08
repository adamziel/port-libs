<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$referencedPayload = '<wp-export><post id="referenced-generation-zero-ef-smoke"/></wp-export>';
$decoyPayload = '<wp-export><post id="decoy-generation-one-ef-smoke"/></wp-export>';
$referencedChecksum = md5($referencedPayload);
$decoyChecksum = md5($decoyPayload);
$visibleContent = 'BT /F1 12 Tf 72 720 Td (Attachment EF Stream Generation Smoke Body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 6 0 R /Names << /EmbeddedFiles 2 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Names [(generation-boundary-smoke.xml) 4 1 R] >>\nendobj\n"
    . "4 1 obj\n<< /Type /Filespec /F (generation-boundary-smoke.xml) /Desc (Current FileSpec with stale-generation EF stream smoke) /AFRelationship /Source /EF << /F 5 0 R >> >>\nendobj\n"
    . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($referencedPayload) . " /CheckSum <{$referencedChecksum}> /ModDate (D:20260608113747Z) >> /Length " . strlen($referencedPayload) . " >>\n"
    . "stream\n{$referencedPayload}\nendstream\nendobj\n"
    . "5 1 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($decoyPayload) . " /CheckSum <{$decoyChecksum}> /ModDate (D:20260608113847Z) >> /Length " . strlen($decoyPayload) . " >>\n"
    . "stream\n{$decoyPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Pages /Kids [7 0 R] /Count 1 >>\nendobj\n"
    . "7 0 obj\n<< /Type /Page /Parent 6 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> /Contents 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? [];
$file = $files[0] ?? [];

if (($summary['attachment_count'] ?? null) !== 1
    || ($summary['filenames'] ?? []) !== ['generation-boundary-smoke.xml']
    || count($files) !== 1
    || ($attachment['filename'] ?? null) !== 'generation-boundary-smoke.xml'
    || ($attachment['byte_length'] ?? null) !== strlen($referencedPayload)
    || ($attachment['sha256'] ?? null) !== hash('sha256', $referencedPayload)
    || ($attachment['checksum_hex'] ?? null) !== $referencedChecksum
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $attachment)
    || ($file['content'] ?? null) !== $referencedPayload
    || ($file['content_sha256'] ?? null) !== hash('sha256', $referencedPayload)
    || str_contains($summaryJson, $referencedPayload)
    || str_contains($summaryJson, $decoyPayload)
    || str_contains($summaryJson, $decoyChecksum)
    || str_contains($filesJson, $decoyPayload)
    || $plainText !== 'Attachment EF Stream Generation Smoke Body'
) {
    throw new RuntimeException('Expected generation-qualified EF stream reference to stay exact before WordPress attachment import.');
}

echo '<!-- markerpdf-pdf-attachment-ef-stream-generation-boundary ' . htmlspecialchars(json_encode([
    'native_boundary' => 'fallback EmbeddedFiles FileSpec /EF stream reference generation matching before WordPress attachment summaries',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'referenced_stream_object' => $attachment['stream_object_id'] ?? null,
    'referenced_generation_payload_selected' => ($attachment['sha256'] ?? null) === hash('sha256', $referencedPayload),
    'decoy_newer_generation_excluded' => !str_contains($summaryJson, $decoyChecksum) && !str_contains($filesJson, $decoyPayload),
    'payload_bytes_omitted_from_summary' => !str_contains($summaryJson, $referencedPayload) && !str_contains($summaryJson, $decoyPayload),
    'visible_text_preserved' => $plainText === 'Attachment EF Stream Generation Smoke Body',
    'relationship_role' => $attachment['relationship_role'] ?? null,
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
