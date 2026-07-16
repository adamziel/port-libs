<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validPayload = '<wp-export><post id="valid-annotation-filespec-smoke"/></wp-export>';
$duplicateFileSpecPayload = '<wp-export><post id="duplicate-annotation-filespec-smoke"/></wp-export>';
$duplicateEfPayload = '<wp-export><post id="duplicate-annotation-ef-smoke"/></wp-export>';
$duplicateEfDecoyPayload = '<wp-export><post id="duplicate-annotation-ef-decoy-smoke"/></wp-export>';
$validChecksum = md5($validPayload);
$duplicateFileSpecChecksum = md5($duplicateFileSpecPayload);
$duplicateEfChecksum = md5($duplicateEfPayload);
$duplicateEfDecoyChecksum = md5($duplicateEfDecoyPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Annotation FileSpec Smoke Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Annots [8 0 R 9 0 R 10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [72 700 90 718] /Contents (Duplicate annotation filename smoke) /FS << /Type /Filespec /F (annotation-current.xml) /F (annotation-stale.xml) /Desc (Duplicate annotation FileSpec smoke) /AFRelationship /Source /EF << /F 11 0 R >> >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [108 700 126 718] /Contents (Duplicate annotation EF smoke) /FS << /Type /Filespec /F (annotation-duplicate-ef.xml) /Desc (Duplicate annotation EF smoke) /AFRelationship /Supplement /EF << /F 21 0 R /F 22 0 R >> >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [144 700 162 718] /Contents (Valid annotation smoke) /FS << /Type /Filespec /F (valid-annotation.xml) /Desc (Valid direct annotation FileSpec smoke) /AFRelationship /Data /EF << /F 31 0 R >> >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateFileSpecPayload) . " /CheckSum <{$duplicateFileSpecChecksum}> >> /Length " . strlen($duplicateFileSpecPayload) . " >>\n"
    . "stream\n{$duplicateFileSpecPayload}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateEfPayload) . " /CheckSum <{$duplicateEfChecksum}> >> /Length " . strlen($duplicateEfPayload) . " >>\n"
    . "stream\n{$duplicateEfPayload}\nendstream\nendobj\n"
    . "22 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateEfDecoyPayload) . " /CheckSum <{$duplicateEfDecoyChecksum}> >> /Length " . strlen($duplicateEfDecoyPayload) . " >>\n"
    . "stream\n{$duplicateEfDecoyPayload}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260607044412Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;

$duplicateAnnotationFileSpecRejected = ($summary['attachment_count'] ?? null) === 1
    && !str_contains($summaryJson, 'annotation-stale.xml')
    && !str_contains($summaryJson, $duplicateFileSpecChecksum);
$duplicateAnnotationEfRejected = !str_contains($summaryJson, 'annotation-duplicate-ef.xml')
    && !str_contains($summaryJson, $duplicateEfChecksum)
    && !str_contains($summaryJson, $duplicateEfDecoyChecksum);
$validAnnotationKept = is_array($attachment)
    && ($attachment['filename'] ?? null) === 'valid-annotation.xml'
    && ($attachment['source'] ?? null) === 'file-attachment-annotation'
    && ($attachment['annotation_object_id'] ?? null) === 10
    && ($attachment['checksum_matches'] ?? null) === true;
$payloadBytesOmitted = is_array($attachment)
    && !array_key_exists('bytes', $attachment)
    && !str_contains($summaryJson, $validPayload)
    && !str_contains($summaryJson, $duplicateFileSpecPayload)
    && !str_contains($summaryJson, $duplicateEfPayload)
    && !str_contains($summaryJson, $duplicateEfDecoyPayload);
$payloadTextExcluded = $plainText === 'Annotation FileSpec Smoke Body'
    && !str_contains($plainText, '<wp-export>');

if (
    !$duplicateAnnotationFileSpecRejected
    || !$duplicateAnnotationEfRejected
    || !$validAnnotationKept
    || !$payloadBytesOmitted
    || !$payloadTextExcluded
) {
    throw new RuntimeException('Expected direct FileAttachment annotation FileSpec duplicate keys to fail closed before WordPress attachment review.');
}

echo json_encode([
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'duplicate_annotation_filespec_rejected' => $duplicateAnnotationFileSpecRejected,
    'duplicate_annotation_ef_rejected' => $duplicateAnnotationEfRejected,
    'valid_annotation_kept' => $validAnnotationKept,
    'payload_bytes_omitted_from_summary' => $payloadBytesOmitted,
    'payload_text_excluded_from_visible_text' => $payloadTextExcluded,
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
