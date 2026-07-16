<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = '<wp-export><post id="duplicate-invalid-key-current"/></wp-export>';
$checksum = md5($payload);
$pageContent = 'BT /F1 12 Tf 72 720 Td (Duplicate Invalid Name Key Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(source.xml) 20 0 R (source.xml) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (current-source.xml) /Desc (Current duplicate-key WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605164243Z) >> /Length " . strlen($payload) . " >>\n"
    . "stream\n{$payload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (invalid-source.xml) /Desc (Invalid duplicate missing EF) /AFRelationship /Alternative >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;

if (!is_array($attachment)
    || ($summary['attachment_count'] ?? null) !== 1
    || ($attachment['source'] ?? null) !== 'embedded-files-name-tree'
    || ($attachment['name_key'] ?? null) !== 'source.xml'
    || ($attachment['filename'] ?? null) !== 'current-source.xml'
    || ($attachment['relationship'] ?? null) !== 'Source'
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $attachment)
    || str_contains($summaryJson, 'invalid-source.xml')
    || str_contains($summaryJson, 'Invalid duplicate missing EF')
    || str_contains($summaryJson, $payload)
    || str_contains($filesJson, 'invalid-source.xml')
    || $plainText !== 'Duplicate Invalid Name Key Body'
) {
    throw new RuntimeException('Expected malformed first duplicate EmbeddedFiles key to be skipped before WordPress attachment preflight.');
}

echo "<!-- markerpdf-pdf-attachment-duplicate-invalid-name-key " . htmlspecialchars(json_encode([
    'native_boundary' => 'PDF EmbeddedFiles duplicate name-tree key attachment preflight',
    'attachment_count' => $summary['attachment_count'],
    'filename' => $attachment['filename'],
    'name_key' => $attachment['name_key'],
    'relationship' => $attachment['relationship'],
    'checksum_matches' => $attachment['checksum_matches'],
    'invalid_first_duplicate_skipped' => !str_contains($summaryJson, 'invalid-source.xml'),
    'valid_duplicate_file_spec_recovered' => ($attachment['file_spec_object_id'] ?? null) === 10,
    'payload_omitted_from_summary' => !str_contains($summaryJson, $payload),
    'visible_text_preserved' => $plainText === 'Duplicate Invalid Name Key Body',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li data-marker-attachment-sha256="'
    . htmlspecialchars((string) $attachment['sha256'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">'
    . htmlspecialchars(
        $attachment['filename_storage_name'] . ' (EmbeddedFiles duplicate key boundary, '
            . $attachment['relationship'] . ', '
            . $attachment['content_type'] . ', '
            . $attachment['byte_length']
            . ' bytes)',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    )
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
