<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$badPayload = '<wp-export><post id="bad-ci-still-attachment"/></wp-export>';
$validPayload = '<wp-export><post id="valid-ci-attachment"/></wp-export>';
$badPrivatePayload = 'BT /F1 12 Tf 72 720 Td (Bad CI Private Payload Leak) Tj ET';
$validPrivatePayload = 'BT /F1 12 Tf 72 720 Td (Valid CI Private Payload Leak) Tj ET';
$badChecksum = md5($badPayload);
$validChecksum = md5($validPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Attachment CI Operand Boundary Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Collection /View /T /D (valid-ci.xml) /Schema << /SubjectField << /Subtype /S /N (Subject) /O 1 >> /StatusField << /Subtype /S /N (Status) /O 2 >> /DescriptionField << /Subtype /Desc /N (Description) /O 3 >> /BytesField << /Subtype /Size /N (Bytes) /O 4 >> >> >>\nendobj\n"
    . "6 0 obj\n<< /Names [(bad-ci.xml) 10 0 R (valid-ci.xml) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (bad-ci.xml) /Desc (Bad CI source still imports) /AFRelationship /Data /CI 30 0 R /CI 31 0 R /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($badPayload) . " /CheckSum <{$badChecksum}> >> /Length " . strlen($badPayload) . " >>\nstream\n{$badPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (valid-ci.xml) /Desc (Valid CI source) /AFRelationship /Source /CI 32 0 R /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> >> /Length " . strlen($validPayload) . " >>\nstream\n{$validPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /CollectionItem /SubjectField (Bad First CI Subject) /StatusField (stale-first) /PrivateStream 40 0 R >>\nendobj\n"
    . "31 0 obj\n<< /Type /CollectionItem /SubjectField (Bad Duplicate CI Subject) /StatusField (stale-duplicate) /PrivateStream 41 0 R >>\nendobj\n"
    . "32 0 obj\n<< /Type /CollectionItem /SubjectField (Valid CI Subject) /StatusField << /Type /CollectionSubitem /D (ready) /P (status:) >> /PrivateStream 42 0 R >>\nendobj\n"
    . "40 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Length " . strlen($badPrivatePayload) . " >>\nstream\n{$badPrivatePayload}\nendstream\nendobj\n"
    . "41 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Length " . strlen($badPrivatePayload) . " >>\nstream\n{$badPrivatePayload}\nendstream\nendobj\n"
    . "42 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Length " . strlen($validPrivatePayload) . " >>\nstream\n{$validPrivatePayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$bad = $summary['attachments'][0] ?? null;
$valid = $summary['attachments'][1] ?? null;
$badFields = is_array($bad) && is_array($bad['portfolio_field_values'] ?? null) ? $bad['portfolio_field_values'] : [];
$validFields = is_array($valid) && is_array($valid['portfolio_field_values'] ?? null) ? $valid['portfolio_field_values'] : [];

if (!is_array($bad)
    || !is_array($valid)
    || ($summary['attachment_count'] ?? null) !== 2
    || ($bad['portfolio_item_status'] ?? null) !== 'malformed_filespec_collection_item_omitted'
    || array_key_exists('portfolio_item', $bad)
    || array_key_exists('SubjectField', $badFields)
    || array_key_exists('StatusField', $badFields)
    || ($badFields['DescriptionField']['value'] ?? null) !== 'Bad CI source still imports'
    || ($validFields['SubjectField']['value'] ?? null) !== 'Valid CI Subject'
    || ($validFields['StatusField']['display_value'] ?? null) !== 'status:ready'
    || str_contains($summaryJson, 'Bad First CI Subject')
    || str_contains($summaryJson, 'Bad Duplicate CI Subject')
    || str_contains($summaryJson, 'stale-duplicate')
    || str_contains($summaryJson, $badPayload)
    || str_contains($summaryJson, $validPayload)
    || str_contains($summaryJson, $badPrivatePayload)
    || str_contains($summaryJson, $validPrivatePayload)
    || $plainText !== 'Attachment CI Operand Boundary Body'
) {
    throw new RuntimeException('Expected malformed FileSpec /CI operands to be omitted while attachments remain reviewable.');
}

echo '<!-- markerpdf-pdf-attachment-ci-operand-boundary ' . htmlspecialchars(json_encode([
    'native_boundary' => 'FileSpec duplicate /CI collection-item operand omission',
    'attachment_count' => $summary['attachment_count'],
    'bad_attachment_filename' => $bad['filename'],
    'bad_portfolio_item_status' => $bad['portfolio_item_status'],
    'bad_ci_subject_omitted' => !array_key_exists('SubjectField', $badFields),
    'bad_ci_status_omitted' => !array_key_exists('StatusField', $badFields),
    'valid_ci_subject' => $validFields['SubjectField']['value'],
    'valid_ci_status' => $validFields['StatusField']['display_value'],
    'payloads_omitted_from_summary' => !str_contains($summaryJson, '<wp-export>'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li data-marker-attachment-sha256="'
    . htmlspecialchars((string) $bad['sha256'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">'
    . htmlspecialchars($bad['filename'] . ': malformed Portfolio item omitted; attachment retained for review', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</li>\n";
echo '<li data-marker-attachment-sha256="'
    . htmlspecialchars((string) $valid['sha256'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">'
    . htmlspecialchars($valid['filename'] . ': ' . $validFields['SubjectField']['value'] . ' / ' . $validFields['StatusField']['display_value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
