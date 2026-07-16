<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentPayload = "Title,Status\nCurrent Duplicate Name,Ready\n";
$stalePayload = "Title,Status\nStale Duplicate Name,Ignore\n";
$currentChecksum = md5($currentPayload);
$staleChecksum = md5($stalePayload);
$content = 'BT /F1 12 Tf 72 720 Td (Visible Duplicate Attachment Boundary) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Limits [(shared.csv) (shared.csv)] /Kids [7 0 R 8 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /Limits [(shared.csv) (shared.csv)] /Names [(shared.csv) 10 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Limits [(shared.csv) (shared.csv)] /Names [(shared.csv) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (current-shared.csv) /Desc (Current duplicate name-tree attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260606033001Z) >> /Length " . strlen($currentPayload) . " >>\n"
    . "stream\n{$currentPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (stale-shared.csv) /Desc (Stale duplicate name-tree attachment) /AFRelationship /Alternative /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
    . "stream\n{$stalePayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$file = $files[0] ?? null;
$attachment = $summary['attachments'][0] ?? null;

if (!is_array($file)
    || !is_array($attachment)
    || count($files) !== 1
    || ($summary['attachment_count'] ?? null) !== 1
    || ($file['name'] ?? null) !== 'shared.csv'
    || ($file['filename'] ?? null) !== 'current-shared.csv'
    || ($attachment['filename'] ?? null) !== 'current-shared.csv'
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $attachment)
    || str_contains($filesJson, 'stale-shared.csv')
    || str_contains($summaryJson, 'stale-shared.csv')
    || str_contains($filesJson, $stalePayload)
    || str_contains($summaryJson, $stalePayload)
    || str_contains($summaryJson, $currentPayload)
    || str_contains($plainText, 'Duplicate Name')
) {
    throw new RuntimeException('Expected first duplicate EmbeddedFiles name-tree key to bound full and summary attachment import.');
}

echo "<!-- markerpdf-pdf-embedded-files-duplicate-name-boundary " . htmlspecialchars(json_encode([
    'native_boundary' => 'EmbeddedFiles duplicate name-tree key attachment review',
    'embedded_file_count' => count($files),
    'attachment_count' => $summary['attachment_count'],
    'name_tree_key' => $attachment['name_key'],
    'filename' => $attachment['filename'],
    'relationship' => $attachment['relationship'],
    'checksum_matches' => $attachment['checksum_matches'],
    'stale_duplicate_excluded_from_full_extractor' => !str_contains($filesJson, 'stale-shared.csv'),
    'stale_duplicate_excluded_from_summary' => !str_contains($summaryJson, 'stale-shared.csv'),
    'payload_omitted_from_summary' => !array_key_exists('bytes', $attachment),
    'visible_text_preserved' => $plainText === 'Visible Duplicate Attachment Boundary',
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
