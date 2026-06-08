<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$duplicatePayload = '<wp-export><post id="duplicate-annotation-fs"/></wp-export>';
$trailingPayload = '<wp-export><post id="trailing-annotation-fs"/></wp-export>';
$validPayload = '<wp-export><post id="valid-annotation-fs"/></wp-export>';
$duplicateChecksum = md5($duplicatePayload);
$trailingChecksum = md5($trailingPayload);
$validChecksum = md5($validPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Annotation FS Key Boundary Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [72 700 92 720] /Contents (Duplicate annotation FS smoke) /FS 9 0 R /#46S 10 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /Filespec /F (duplicate-fs-current.xml) /Desc (Duplicate FS current source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (duplicate-fs-decoy.xml) /Desc (Duplicate FS decoy source) /AFRelationship /Alternative /EF << /F 19 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicatePayload) . " /CheckSum <{$duplicateChecksum}> >> /Length " . strlen($duplicatePayload) . " >>\n"
    . "stream\n{$duplicatePayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [120 700 140 720] /Contents (Trailing annotation FS smoke) /FS 13 0 R 14 0 R >>\nendobj\n"
    . "13 0 obj\n<< /Type /Filespec /F (trailing-fs-current.xml) /Desc (Trailing FS current source) /AFRelationship /Supplement /EF << /F 15 0 R >> >>\nendobj\n"
    . "14 0 obj\n<< /Type /Filespec /F (trailing-fs-decoy.xml) /Desc (Trailing FS decoy source) /AFRelationship /Alternative /EF << /F 20 0 R >> >>\nendobj\n"
    . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($trailingPayload) . " /CheckSum <{$trailingChecksum}> >> /Length " . strlen($trailingPayload) . " >>\n"
    . "stream\n{$trailingPayload}\nendstream\nendobj\n"
    . "16 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [168 700 188 720] /Contents (Valid annotation FS smoke) /FS 17 0 R >>\nendobj\n"
    . "17 0 obj\n<< /Type /Filespec /F (valid-annotation-fs.xml) /Desc (Valid annotation FS source) /AFRelationship /Data /EF << /F 18 0 R >> >>\nendobj\n"
    . "18 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608131159Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "19 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicatePayload) . " /CheckSum <{$duplicateChecksum}> >> /Length " . strlen($duplicatePayload) . " >>\n"
    . "stream\n{$duplicatePayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($trailingPayload) . " /CheckSum <{$trailingChecksum}> >> /Length " . strlen($trailingPayload) . " >>\n"
    . "stream\n{$trailingPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$valid = $summary['attachments'][0] ?? null;
if (($summary['attachment_count'] ?? null) !== 1
    || !is_array($valid)
    || ($valid['filename'] ?? null) !== 'valid-annotation-fs.xml'
    || ($valid['annotation_object_id'] ?? null) !== 16
    || ($valid['checksum_matches'] ?? null) !== true
    || ($plainText !== 'Annotation FS Key Boundary Body')
    || str_contains($summaryJson, 'duplicate-fs-current.xml')
    || str_contains($summaryJson, 'duplicate-fs-decoy.xml')
    || str_contains($summaryJson, 'trailing-fs-current.xml')
    || str_contains($summaryJson, 'trailing-fs-decoy.xml')
    || str_contains($summaryJson, $duplicatePayload)
    || str_contains($summaryJson, $trailingPayload)
    || str_contains($summaryJson, $validPayload)
    || str_contains($plainText, '<wp-export>')
) {
    throw new RuntimeException('Expected malformed FileAttachment annotation FS owners to fail closed without payload leakage.');
}

echo "<!-- markerpdf-pdf-attachment-annotation-fs-key-boundary-smoke " . htmlspecialchars(json_encode([
    'native_boundary' => 'FileAttachment annotation /FS duplicate and trailing operands fail closed before attachment import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => $summary['attachment_count'],
    'valid_attachment' => $valid['filename'],
    'valid_annotation_object_id' => $valid['annotation_object_id'],
    'malformed_annotation_fs_owners_excluded' => true,
    'payload_text_excluded_from_visible_text' => !str_contains($plainText, '<wp-export>'),
    'payload_bytes_omitted_from_summary' => !str_contains($summaryJson, $validPayload)
        && !str_contains($summaryJson, $duplicatePayload)
        && !str_contains($summaryJson, $trailingPayload),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($summary['attachments'] as $attachment) {
    echo '<li data-marker-attachment-source="'
        . htmlspecialchars((string) ($attachment['source'] ?? 'attachment'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars((string) ($attachment['filename'] ?? 'attachment'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . ' - '
        . htmlspecialchars((string) ($attachment['relationship_role'] ?? 'review'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
