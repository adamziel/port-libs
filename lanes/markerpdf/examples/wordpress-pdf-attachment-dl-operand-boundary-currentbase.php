<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$badPayload = '<wp-export><post id="bad-dl-tail"/></wp-export>';
$validPayload = '<wp-export><post id="valid-dl-operand"/></wp-export>';
$relatedPayload = '{"related":"valid-dl-operand"}';
$badRelatedPayload = 'RELATED_DL_DUPLICATE_SHOULD_NOT_BE_REVIEWED';
$badChecksum = md5($badPayload);
$validChecksum = md5($validPayload);
$relatedChecksum = md5($relatedPayload);
$badRelatedChecksum = md5($badRelatedPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Attachment DL Operand Boundary Smoke) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(bad-dl-tail.xml) 10 0 R (valid-dl.xml) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (bad-dl-tail.xml) /Desc (Bad decoded-length attachment) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /DL " . strlen($badPayload) . " 88 /Params << /Size " . strlen($badPayload) . " /CheckSum <{$badChecksum}> >> /Length " . strlen($badPayload) . " >>\n"
    . "stream\n{$badPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (valid-dl.xml) /Desc (Valid decoded-length attachment) /AFRelationship /Source /EF << /F 21 0 R >> /RF << /UF [(related-valid.json) 22 0 R (related-bad.css) 23 0 R] >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /DL " . strlen($validPayload) . " /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608180349Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "22 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /DL " . strlen($relatedPayload) . " /Params << /Size " . strlen($relatedPayload) . " /CheckSum <{$relatedChecksum}> >> /Length " . strlen($relatedPayload) . " >>\n"
    . "stream\n{$relatedPayload}\nendstream\nendobj\n"
    . "23 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /DL " . strlen($badRelatedPayload) . " /DL 5 /Params << /Size " . strlen($badRelatedPayload) . " /CheckSum <{$badRelatedChecksum}> >> /Length " . strlen($badRelatedPayload) . " >>\n"
    . "stream\n{$badRelatedPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;
$related = is_array($attachment) ? ($attachment['related_files'][0] ?? null) : null;
$file = $files[0] ?? null;

if (!is_array($attachment)
    || !is_array($related)
    || !is_array($file)
    || ($summary['attachment_count'] ?? null) !== 1
    || ($attachment['filename'] ?? null) !== 'valid-dl.xml'
    || ($attachment['decoded_length_matches'] ?? null) !== true
    || ($related['related_filename'] ?? null) !== 'related-valid.json'
    || ($related['decoded_length_matches'] ?? null) !== true
    || ($file['decoded_length_matches'] ?? null) !== true
    || str_contains($summaryJson, 'bad-dl-tail.xml')
    || str_contains($summaryJson, $badPayload)
    || str_contains($summaryJson, $badRelatedPayload)
    || str_contains($filesJson, $badPayload)
    || str_contains($filesJson, $badRelatedPayload)
    || str_contains($plainText, '<wp-export>')
    || $plainText !== 'Attachment DL Operand Boundary Smoke'
) {
    throw new RuntimeException('Expected malformed EmbeddedFile /DL operands to fail closed before WordPress attachment review.');
}

echo '<!-- markerpdf-pdf-attachment-dl-operand-boundary ' . htmlspecialchars(json_encode([
    'native_boundary' => 'EmbeddedFile stream /DL duplicate and trailing operand rejection',
    'attachment_count' => $summary['attachment_count'],
    'filename' => $attachment['filename'],
    'decoded_length_matches' => $attachment['decoded_length_matches'],
    'related_filename' => $related['related_filename'],
    'related_decoded_length_matches' => $related['decoded_length_matches'],
    'bad_attachment_excluded' => !str_contains($summaryJson, 'bad-dl-tail.xml'),
    'bad_related_file_excluded' => !str_contains($summaryJson, $badRelatedChecksum),
    'attachment_payload_omitted' => !str_contains($summaryJson, $validPayload),
    'visible_text' => $plainText,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li data-marker-attachment-dl-boundary="'
    . htmlspecialchars((string) $attachment['decoded_length'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">'
    . htmlspecialchars(
        $attachment['filename'] . ' decoded length accepted while malformed DL operands are excluded',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    )
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
