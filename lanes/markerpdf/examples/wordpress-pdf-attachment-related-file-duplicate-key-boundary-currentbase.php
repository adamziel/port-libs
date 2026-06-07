<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="rf-duplicate-smoke-source"/></wp-export>';
$safePayload = 'SAFE_RELATED_SMOKE_PAYLOAD_SHOULD_NOT_LEAK';
$stalePayload = 'STALE_RELATED_SMOKE_PAYLOAD_SHOULD_NOT_LEAK';
$manifestPayload = '{"related":"duplicate-rf-smoke"}';
$pageContent = 'BT /F1 12 Tf 72 720 Td (WordPress RF Duplicate Boundary Body) Tj ET';

$sourceChecksum = md5($sourcePayload);
$safeChecksum = md5($safePayload);
$staleChecksum = md5($stalePayload);
$manifestChecksum = md5($manifestPayload);

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(source.xml) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Source with duplicate RF review) /AFRelationship /Source /EF << /F 11 0 R >>"
    . " /RF << /F [(safe.css) 12 0 R] /F [(stale.css) 13 0 R] /UF [(manifest.json) 14 0 R] >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> >> /Length " . strlen($sourcePayload) . " >>\n"
    . "stream\n{$sourcePayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($safePayload) . " /CheckSum <{$safeChecksum}> >> /Length " . strlen($safePayload) . " >>\n"
    . "stream\n{$safePayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
    . "stream\n{$stalePayload}\nendstream\nendobj\n"
    . "14 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($manifestPayload) . " /CheckSum <{$manifestChecksum}> >> /Length " . strlen($manifestPayload) . " >>\n"
    . "stream\n{$manifestPayload}\nendstream\nendobj\n"
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
    || ($attachment['filename'] ?? null) !== 'source.xml'
    || ($attachment['relationship'] ?? null) !== 'Source'
    || array_key_exists('related_files', $attachment)
    || array_key_exists('related_files', $file)
    || str_contains($summaryJson, $safePayload)
    || str_contains($summaryJson, $stalePayload)
    || str_contains($summaryJson, $manifestPayload)
    || str_contains($summaryJson, $safeChecksum)
    || str_contains($summaryJson, $staleChecksum)
    || str_contains($summaryJson, $manifestChecksum)
    || str_contains($filesJson, $safePayload)
    || str_contains($filesJson, $stalePayload)
    || str_contains($filesJson, $manifestPayload)
    || $plainText !== 'WordPress RF Duplicate Boundary Body'
) {
    throw new RuntimeException('Expected duplicate RF related-file rows to be suppressed while preserving the primary source attachment.');
}

echo '<!-- markerpdf-pdf-attachment-related-file-duplicate-key-boundary ' . htmlspecialchars(json_encode([
    'native_boundary' => 'FileSpec /RF duplicate-key related-file fail-closed review',
    'attachment_count' => $summary['attachment_count'],
    'primary_filename' => $attachment['filename'],
    'primary_relationship' => $attachment['relationship'],
    'related_files_suppressed' => !array_key_exists('related_files', $attachment)
        && !array_key_exists('related_files', $file),
    'related_payload_bytes_omitted' => !str_contains($summaryJson, $safePayload)
        && !str_contains($summaryJson, $stalePayload)
        && !str_contains($summaryJson, $manifestPayload)
        && !str_contains($filesJson, $safePayload)
        && !str_contains($filesJson, $stalePayload)
        && !str_contains($filesJson, $manifestPayload),
    'visible_text' => $plainText,
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES) . " -->\n";
