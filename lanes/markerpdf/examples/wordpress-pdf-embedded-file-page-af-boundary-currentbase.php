<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOnlyPayload = '<wp-page><attachment role="page-af-only"/></wp-page>';
$pageMirrorPayload = '<wp-page><attachment role="page-af-mirror"/></wp-page>';
$pageOnlyChecksum = md5($pageOnlyPayload);
$pageMirrorChecksum = md5($pageMirrorPayload);
$pageContent = 'BT /F1 12 Tf 72 720 Td (Page AF Embedded Boundary Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 5 0 R /AF [10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /AF [20 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(page-mirror.xml) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (page-only.xml) /Desc (Page-only associated WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageOnlyPayload) . " /CheckSum <{$pageOnlyChecksum}> /ModDate (D:20260606022800Z) >> /Length " . strlen($pageOnlyPayload) . " >>\n"
    . "stream\n{$pageOnlyPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (page-mirror.xml) /Desc (Mirrored page associated WordPress source) /AFRelationship /Supplement /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageMirrorPayload) . " /CheckSum <{$pageMirrorChecksum}> /ModDate (D:20260606022900Z) >> /Length " . strlen($pageMirrorPayload) . " >>\n"
    . "stream\n{$pageMirrorPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';

if (count($files) !== 2
    || ($summary['attachment_count'] ?? null) !== 2
    || ($files[0]['filename'] ?? null) !== 'page-mirror.xml'
    || ($files[0]['page_associated_file'] ?? null) !== true
    || ($files[0]['page_associated_file_source'] ?? null) !== 'page_af'
    || ($files[0]['page_number'] ?? null) !== 2
    || ($files[1]['source'] ?? null) !== 'page_associated_files'
    || ($files[1]['filename'] ?? null) !== 'page-only.xml'
    || ($files[1]['page_number'] ?? null) !== 1
    || ($summary['attachments'][0]['page_associated_file'] ?? null) !== true
    || ($summary['attachments'][1]['page_associated_file'] ?? null) !== true
    || str_contains($summaryJson, $pageOnlyPayload)
    || str_contains($summaryJson, $pageMirrorPayload)
    || str_contains($plainText, '<wp-page>')
) {
    throw new RuntimeException('Expected page-level /AF files to appear in embedded-file review and attachment summary without leaking payload text.');
}

echo '<!-- markerpdf:embedded-file-page-af-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-page-associated-files-embeddedfile-parser',
    'native_boundary' => 'page-level /AF FileSpec entries are included in embedded-file review and mirrored name-tree entries retain page scope',
    'embedded_file_count' => count($files),
    'attachment_count' => $summary['attachment_count'],
    'filenames' => array_column($files, 'filename'),
    'sources' => array_column($files, 'source'),
    'page_numbers' => array_column($files, 'page_number'),
    'page_mirror_source' => $files[0]['page_associated_file_source'] ?? null,
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $summary['attachments'][0] ?? [])
        && !array_key_exists('bytes', $summary['attachments'][1] ?? []),
    'payload_text_excluded_from_visible_text' => !str_contains($plainText, '<wp-page>'),
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($summary['attachments'] as $attachment) {
    $storageName = (string) ($attachment['filename_storage_name'] ?? $attachment['filename']);
    echo '<!-- wp:file {"href":"media/' . htmlspecialchars($storageName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($storageName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n\n";
}
