<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentPayload = '<wp-export><post id="generation-nested-current"/></wp-export>';
$summaryPayload = '<wp-export><post id="generation-sibling-summary"/></wp-export>';
$currentChecksum = md5($currentPayload);
$summaryChecksum = md5($summaryPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Attachment Kid Generation Smoke Body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Limits [(current-generation-kid.xml) (summary-generation-kid.xml)] /Kids [7 0 R 8 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /Limits [(current-generation-kid.xml) (current-generation-kid.xml)] /Kids [7 1 R] >>\nendobj\n"
    . "7 1 obj\n<< /Limits [(current-generation-kid.xml) (current-generation-kid.xml)] /Names [(current-generation-kid.xml) 10 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Limits [(summary-generation-kid.xml) (summary-generation-kid.xml)] /Names [(summary-generation-kid.xml) 12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (current-generation-kid.xml) /Desc (Generation nested current attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260607084728Z) >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (summary-generation-kid.xml) /Desc (Generation sibling summary attachment) /AFRelationship /Data /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($summaryPayload) . " /CheckSum <{$summaryChecksum}> /ModDate (D:20260607084729Z) >> /Length " . strlen($summaryPayload) . " >>\nstream\n{$summaryPayload}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$firstAttachment = $summary['attachments'][0] ?? null;
$secondAttachment = $summary['attachments'][1] ?? null;
$firstFile = $files[0] ?? null;
$secondFile = $files[1] ?? null;

if (!is_array($firstAttachment)
    || !is_array($secondAttachment)
    || !is_array($firstFile)
    || !is_array($secondFile)
    || ($summary['attachment_count'] ?? null) !== 2
    || ($summary['filenames'] ?? []) !== ['current-generation-kid.xml', 'summary-generation-kid.xml']
    || count($files) !== 2
    || ($firstAttachment['file_spec_object_id'] ?? null) !== 10
    || ($secondAttachment['file_spec_object_id'] ?? null) !== 12
    || ($firstAttachment['stream_object_id'] ?? null) !== 11
    || ($secondAttachment['stream_object_id'] ?? null) !== 13
    || ($firstAttachment['checksum_matches'] ?? null) !== true
    || ($secondAttachment['checksum_matches'] ?? null) !== true
    || ($firstFile['content'] ?? null) !== $currentPayload
    || ($secondFile['content'] ?? null) !== $summaryPayload
    || str_contains($summaryJson, $currentPayload)
    || str_contains($summaryJson, $summaryPayload)
    || !str_contains($filesJson, 'generation-nested-current')
    || !str_contains($filesJson, 'generation-sibling-summary')
    || $plainText !== 'Attachment Kid Generation Smoke Body'
) {
    throw new RuntimeException('Expected no-xref EmbeddedFiles name-tree kids to stay generation-distinct before WordPress attachment import.');
}

echo '<!-- markerpdf-pdf-attachment-kid-generation-boundary ' . htmlspecialchars(json_encode([
    'native_boundary' => 'no-xref EmbeddedFiles name-tree /Kids keep object generations distinct',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'nested_generation_kid_resolved' => ($firstAttachment['file_spec_object_id'] ?? null) === 10,
    'sibling_generation_kid_resolved' => ($secondAttachment['file_spec_object_id'] ?? null) === 12,
    'payload_bytes_omitted_from_summary' => !str_contains($summaryJson, $currentPayload)
        && !str_contains($summaryJson, $summaryPayload),
    'payload_bytes_available_to_full_extractor' => str_contains($filesJson, 'generation-nested-current')
        && str_contains($filesJson, 'generation-sibling-summary'),
    'visible_text_preserved' => $plainText === 'Attachment Kid Generation Smoke Body',
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ([$firstAttachment, $secondAttachment] as $attachment) {
    echo "<!-- wp:file {\"href\":\"media/" . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\"} -->\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n";
}
