<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="annotation-presentation"/></wp-export>';
$hiddenPayload = '{"review":"hidden-attachment"}';
$sourceChecksum = md5($sourcePayload);
$hiddenChecksum = md5($hiddenPayload);
$hiddenFileSpec = '<< /Type /Filespec /F (hidden-review.json) /Desc (Hidden reviewer packet) /AFRelationship /Supplement /EF << /F 11 0 R >> >>';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Annots [8 0 R 10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Presented source attachment) /AFRelationship /Source /EF << /F 5 0 R >> >>\nendobj\n"
    . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260605070300Z) >> /Length " . strlen($sourcePayload) . " >>\n"
    . "stream\n{$sourcePayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(source.xml) 4 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [72 700 92 720] /F 4 /Name /Paperclip /Contents (Visible attachment marker) /FS 4 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [120 700 140 720] /F 38 /Name /PushPin /Contents (Hidden attachment marker) /FS {$hiddenFileSpec} >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($hiddenPayload) . " /CheckSum <{$hiddenChecksum}> /ModDate (D:20260605070301Z) >> /Length " . strlen($hiddenPayload) . " >>\n"
    . "stream\n{$hiddenPayload}\nendstream\nendobj\n"
    . "%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$findAttachment = static function (array $summary, string $filename): ?array {
    foreach ($summary['attachments'] ?? [] as $attachment) {
        if (is_array($attachment) && ($attachment['filename'] ?? null) === $filename) {
            return $attachment;
        }
    }

    return null;
};

$source = $findAttachment($summary, 'source.xml');
$hidden = $findAttachment($summary, 'hidden-review.json');

if (($summary['attachment_count'] ?? null) !== 2
    || !is_array($source)
    || !is_array($hidden)
    || ($source['annotation_visibility'] ?? null) !== 'visible'
    || ($source['annotation_icon'] ?? null) !== 'Paperclip'
    || ($hidden['annotation_visibility'] ?? null) !== 'hidden'
    || ($hidden['annotation_no_view'] ?? null) !== true
    || ($hidden['annotation_icon'] ?? null) !== 'PushPin'
    || str_contains($summaryJson, $sourcePayload)
    || str_contains($summaryJson, $hiddenPayload)
) {
    throw new RuntimeException('Expected FileAttachment annotation presentation metadata without embedded payload leakage.');
}

echo "<!-- markerpdf-pdf-attachment-annotation-presentation-smoke " . htmlspecialchars(json_encode([
    'native_boundary' => 'PDF FileAttachment annotation icon and visibility review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => $summary['attachment_count'],
    'visible_attachment_icon' => $source['annotation_icon'] ?? null,
    'visible_attachment_visibility' => $source['annotation_visibility'] ?? null,
    'hidden_attachment_icon' => $hidden['annotation_icon'] ?? null,
    'hidden_attachment_visibility' => $hidden['annotation_visibility'] ?? null,
    'hidden_attachment_no_view' => $hidden['annotation_no_view'] ?? null,
    'payload_bytes_omitted' => !str_contains($summaryJson, $sourcePayload)
        && !str_contains($summaryJson, $hiddenPayload),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($summary['attachments'] as $attachment) {
    $label = ($attachment['filename'] ?? 'attachment')
        . ' (' . ($attachment['annotation_icon'] ?? 'file') . ', '
        . ($attachment['annotation_visibility'] ?? 'visible') . ', '
        . ($attachment['content_type'] ?? 'application/octet-stream') . ')';
    echo '<li data-marker-attachment-visibility="'
        . htmlspecialchars((string) ($attachment['annotation_visibility'] ?? 'visible'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
