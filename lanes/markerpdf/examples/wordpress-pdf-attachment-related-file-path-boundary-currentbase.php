<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="related-path-source"/></wp-export>';
$notesPayload = "review=private\n";
$manifestPayload = '{"manifest":"url-related"}';
$notesChecksum = md5($notesPayload);
$manifestChecksum = md5($manifestPayload);

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /Names [(source.xml) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Source with path-shaped related files) /AFRelationship /Source /EF << /F 11 0 R >>"
    . " /RF << /F [(../private/review-notes.txt) 12 0 R] /UF [(https://example.test/download/private/manifest.json?token=secret) 13 0 R] >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " >> /Length " . strlen($sourcePayload) . " >>\n"
    . "stream\n{$sourcePayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size " . strlen($notesPayload) . " /CheckSum <{$notesChecksum}> >> /Length " . strlen($notesPayload) . " >>\n"
    . "stream\n{$notesPayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($manifestPayload) . " /CheckSum <{$manifestChecksum}> >> /Length " . strlen($manifestPayload) . " >>\n"
    . "stream\n{$manifestPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$json = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;
$related = is_array($attachment) ? ($attachment['related_files'] ?? []) : [];

if (!is_array($attachment)
    || ($summary['attachment_count'] ?? null) !== 1
    || ($attachment['related_file_count'] ?? null) !== 2
    || ($related[0]['related_filename_leaf'] ?? null) !== 'review-notes.txt'
    || ($related[0]['related_filename_storage_name'] ?? null) !== 'review-notes.txt'
    || ($related[0]['related_filename_path_status'] ?? null) !== 'relative_path_segments_review_only'
    || ($related[0]['related_filename_contains_parent_segment'] ?? null) !== true
    || ($related[0]['checksum_matches'] ?? null) !== true
    || ($related[1]['related_filename_leaf'] ?? null) !== 'manifest.json'
    || ($related[1]['related_filename_storage_name'] ?? null) !== 'manifest.json'
    || ($related[1]['related_filename_path_status'] ?? null) !== 'url_path_review_only'
    || ($related[1]['related_filename_url_scheme'] ?? null) !== 'https'
    || ($related[1]['checksum_matches'] ?? null) !== true
    || str_contains($json, $sourcePayload)
    || str_contains($json, $notesPayload)
    || str_contains($json, $manifestPayload)
) {
    throw new RuntimeException('Expected related FileSpec path names to expose basename-safe review metadata without payload bytes.');
}

echo "<!-- markerpdf-related-file-path-boundary " . htmlspecialchars(json_encode([
    'native_boundary' => 'FileSpec RF related filename path review',
    'attachment_count' => $summary['attachment_count'],
    'related_file_count' => $attachment['related_file_count'],
    'related_filename_storage_names' => [
        $related[0]['related_filename_storage_name'],
        $related[1]['related_filename_storage_name'],
    ],
    'related_path_statuses' => [
        $related[0]['related_filename_path_status'],
        $related[1]['related_filename_path_status'],
    ],
    'related_payload_bytes_omitted' => !str_contains($json, $notesPayload)
        && !str_contains($json, $manifestPayload),
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES) . " -->\n";
