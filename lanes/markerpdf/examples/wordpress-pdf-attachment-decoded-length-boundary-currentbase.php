<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = '<wp-export><post id="decoded-length-source"/></wp-export>';
$relatedPayload = '{"manifest":"decoded-length-related"}';
$compressedPayload = gzcompress($payload);
if (!is_string($compressedPayload)) {
    throw new RuntimeException('Unable to compress decoded-length attachment smoke fixture.');
}

$checksum = md5($payload);
$relatedChecksum = md5($relatedPayload);
$staleRelatedDecodedLength = strlen($relatedPayload) + 7;
$content = 'BT /F1 12 Tf 72 720 Td (Decoded Length Attachment Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(decoded-source.xml) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (decoded-source.xml) /Desc (Decoded length WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> /RF << /F [(decoded-related.json) 12 0 R] >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Filter /FlateDecode /DL " . strlen($payload) . " /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605122914Z) >> /Length " . strlen($compressedPayload) . " >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /DL {$staleRelatedDecodedLength} /Params << /Size " . strlen($relatedPayload) . " /CheckSum <{$relatedChecksum}> >> /Length " . strlen($relatedPayload) . " >>\nstream\n{$relatedPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;
$related = is_array($attachment) ? ($attachment['related_files'][0] ?? null) : null;
$embeddedFile = $files[0] ?? null;

if (!is_array($attachment)
    || !is_array($related)
    || !is_array($embeddedFile)
    || ($summary['attachment_count'] ?? null) !== 1
    || ($attachment['filename'] ?? null) !== 'decoded-source.xml'
    || ($attachment['decoded_length'] ?? null) !== strlen($payload)
    || ($attachment['decoded_length_matches'] ?? null) !== true
    || ($related['decoded_length'] ?? null) !== $staleRelatedDecodedLength
    || ($related['decoded_length_matches'] ?? null) !== false
    || ($embeddedFile['decoded_length'] ?? null) !== strlen($payload)
    || ($embeddedFile['provenance_review']['payload']['decoded_length_matches'] ?? null) !== true
    || str_contains($summaryJson, $payload)
    || str_contains($summaryJson, $relatedPayload)
    || ($embeddedFile['content'] ?? null) !== $payload
    || $plainText !== 'Decoded Length Attachment Body'
) {
    throw new RuntimeException('Expected EmbeddedFile /DL review metadata without attachment payload leakage.');
}

echo '<!-- markerpdf-pdf-attachment-decoded-length-boundary ' . htmlspecialchars(json_encode([
    'native_boundary' => 'EmbeddedFile stream /DL decoded-length review metadata',
    'attachment_count' => $summary['attachment_count'],
    'filename' => $attachment['filename'],
    'decoded_length' => $attachment['decoded_length'],
    'decoded_length_matches' => $attachment['decoded_length_matches'],
    'related_filename' => $related['related_filename'],
    'related_decoded_length' => $related['decoded_length'],
    'related_decoded_length_matches' => $related['decoded_length_matches'],
    'provenance_decoded_length_matches' => $embeddedFile['provenance_review']['payload']['decoded_length_matches'],
    'attachment_payload_omitted' => !str_contains($summaryJson, $payload),
    'related_payload_omitted' => !str_contains($summaryJson, $relatedPayload),
    'visible_text' => $plainText,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li data-marker-attachment-decoded-length="'
    . htmlspecialchars((string) $attachment['decoded_length'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">'
    . htmlspecialchars(
        $attachment['filename'] . ' decoded length verified for WordPress attachment review',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    )
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
