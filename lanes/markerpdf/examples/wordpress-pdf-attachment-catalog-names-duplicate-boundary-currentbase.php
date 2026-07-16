<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$catalogPayload = '<wp-export><post id="catalog-af-source"/></wp-export>';
$currentNameTreePayload = '<wp-export><post id="current-name-tree-source"/></wp-export>';
$staleNameTreePayload = '<wp-export><post id="stale-duplicate-embeddedfiles"/></wp-export>';
$catalogChecksum = md5($catalogPayload);
$currentNameTreeChecksum = md5($currentNameTreePayload);
$staleNameTreeChecksum = md5($staleNameTreePayload);
$pageContent = 'BT /F1 12 Tf 72 720 Td (Catalog Names Duplicate EmbeddedFiles Boundary Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /#45mbeddedFiles 6 0 R /EmbeddedFiles 7 0 R >> /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(current-name-tree.xml) 20 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /Names [(stale-name-tree.xml) 30 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (catalog-source.xml) /Desc (Catalog AF fallback source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogPayload) . " /CheckSum <{$catalogChecksum}> /ModDate (D:20260607004300Z) >> /Length " . strlen($catalogPayload) . " >>\n"
    . "stream\n{$catalogPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (current-name-tree.xml) /Desc (Current duplicate catalog Names source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($currentNameTreePayload) . " /CheckSum <{$currentNameTreeChecksum}> >> /Length " . strlen($currentNameTreePayload) . " >>\n"
    . "stream\n{$currentNameTreePayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (stale-name-tree.xml) /Desc (Stale duplicate catalog Names source) /AFRelationship /Alternative /EF << /F 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($staleNameTreePayload) . " /CheckSum <{$staleNameTreeChecksum}> >> /Length " . strlen($staleNameTreePayload) . " >>\n"
    . "stream\n{$staleNameTreePayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$attachment = $summary['attachments'][0] ?? null;
$file = $files[0] ?? null;
if (!is_array($attachment)
    || ($summary['attachment_count'] ?? null) !== 1
    || ($summary['filenames'] ?? []) !== ['catalog-source.xml']
    || ($attachment['source'] ?? null) !== 'catalog-associated-file'
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $attachment)
    || !is_array($file)
    || count($files) !== 1
    || ($file['source'] ?? null) !== 'catalog_associated_files'
    || ($file['filename'] ?? null) !== 'catalog-source.xml'
    || ($plainText !== 'Catalog Names Duplicate EmbeddedFiles Boundary Body')
    || str_contains($summaryJson, 'current-name-tree.xml')
    || str_contains($summaryJson, 'stale-name-tree.xml')
    || str_contains($filesJson, 'current-name-tree.xml')
    || str_contains($filesJson, 'stale-name-tree.xml')
    || str_contains($summaryJson, $catalogPayload)
    || str_contains($summaryJson, $currentNameTreePayload)
    || str_contains($summaryJson, $staleNameTreePayload)
) {
    throw new RuntimeException('Expected duplicate catalog Names EmbeddedFiles rows to fail closed while catalog AF stays reviewable.');
}

echo '<!-- markerpdf:attachment-catalog-names-duplicate-boundary ' . htmlspecialchars(json_encode([
    'native_boundary' => 'catalog Names duplicate EmbeddedFiles fail-closed attachment preflight',
    'attachment_count' => $summary['attachment_count'],
    'embedded_file_count' => count($files),
    'filenames' => $summary['filenames'],
    'catalog_af_preserved' => ($attachment['source'] ?? null) === 'catalog-associated-file',
    'duplicate_embeddedfiles_name_tree_suppressed' => !str_contains($summaryJson, 'current-name-tree.xml')
        && !str_contains($summaryJson, 'stale-name-tree.xml')
        && !str_contains($filesJson, 'current-name-tree.xml')
        && !str_contains($filesJson, 'stale-name-tree.xml'),
    'payload_bytes_omitted_from_summary' => !str_contains($summaryJson, $catalogPayload)
        && !str_contains($summaryJson, $currentNameTreePayload)
        && !str_contains($summaryJson, $staleNameTreePayload),
    'visible_text' => $plainText,
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
