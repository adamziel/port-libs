<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$nameTreePayload = '<wp-export><post id="name-tree-generation-zero-smoke"/></wp-export>';
$catalogPayload = '<wp-export><post id="catalog-af-generation-one-smoke"/></wp-export>';
$nameTreeChecksum = md5($nameTreePayload);
$catalogChecksum = md5($catalogPayload);
$visibleContent = 'BT /F1 12 Tf 72 720 Td (Attachment Mirror Generation Smoke Body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 6 0 R /Names << /EmbeddedFiles 2 0 R >> /AF [4 1 R] >>\nendobj\n"
    . "2 0 obj\n<< /Names [(name-tree-generation-smoke.xml) 4 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Filespec /F (name-tree-generation-smoke.xml) /Desc (Name tree generation zero FileSpec smoke) /AFRelationship /Source /EF << /F 5 0 R >> >>\nendobj\n"
    . "4 1 obj\n<< /Type /Filespec /F (catalog-af-generation-smoke.xml) /Desc (Catalog AF generation one FileSpec smoke) /AFRelationship /Data /EF << /F 5 1 R >> >>\nendobj\n"
    . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($nameTreePayload) . " /CheckSum <{$nameTreeChecksum}> /ModDate (D:20260608223742Z) >> /Length " . strlen($nameTreePayload) . " >>\n"
    . "stream\n{$nameTreePayload}\nendstream\nendobj\n"
    . "5 1 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogPayload) . " /CheckSum <{$catalogChecksum}> /ModDate (D:20260608223842Z) >> /Length " . strlen($catalogPayload) . " >>\n"
    . "stream\n{$catalogPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Pages /Kids [7 0 R] /Count 1 >>\nendobj\n"
    . "7 0 obj\n<< /Type /Page /Parent 6 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> /Contents 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$nameTreeAttachment = $summary['attachments'][0] ?? [];
$catalogAttachment = $summary['attachments'][1] ?? [];

if (($summary['attachment_count'] ?? null) !== 2
    || ($summary['filenames'] ?? []) !== ['name-tree-generation-smoke.xml', 'catalog-af-generation-smoke.xml']
    || ($summary['total_bytes'] ?? null) !== strlen($nameTreePayload) + strlen($catalogPayload)
    || count($files) !== 2
    || ($nameTreeAttachment['file_spec_object_generation'] ?? null) !== 0
    || ($nameTreeAttachment['stream_object_generation'] ?? null) !== 0
    || ($catalogAttachment['file_spec_object_generation'] ?? null) !== 1
    || ($catalogAttachment['stream_object_generation'] ?? null) !== 1
    || ($catalogAttachment['relationship_role'] ?? null) !== 'base_data_for_visual_presentation'
    || str_contains($summaryJson, $nameTreePayload)
    || str_contains($summaryJson, $catalogPayload)
    || $plainText !== 'Attachment Mirror Generation Smoke Body'
) {
    throw new RuntimeException('Expected generation-distinct FileSpec mirrors to remain separate before WordPress attachment import.');
}

echo '<!-- markerpdf-pdf-attachment-mirror-generation-boundary ' . htmlspecialchars(json_encode([
    'native_boundary' => 'same-object-number FileSpec and EmbeddedFile references stay generation-distinct before WordPress attachment summaries',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'file_spec_generations' => array_column($summary['attachments'], 'file_spec_object_generation'),
    'stream_generations' => array_column($summary['attachments'], 'stream_object_generation'),
    'relationship_roles' => array_column($summary['attachments'], 'relationship_role'),
    'payload_bytes_omitted_from_summary' => !str_contains($summaryJson, $nameTreePayload)
        && !str_contains($summaryJson, $catalogPayload),
    'visible_text_preserved' => $plainText === 'Attachment Mirror Generation Smoke Body',
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($summary['attachments'] as $attachment) {
    $storageName = (string) ($attachment['filename_storage_name'] ?? $attachment['filename'] ?? 'attachment.bin');
    $label = (string) ($attachment['filename'] ?? $storageName);
    echo '<!-- wp:file {"href":"media/' . htmlspecialchars($storageName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($storageName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n";
}
