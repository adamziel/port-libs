<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validPayload = '<wp-export><post id="valid-stream-dictionary-boundary"/></wp-export>';
$paramsOperandPayload = '<wp-export><post id="bad-stream-params-operand"/></wp-export>';
$decodeParmsOperandPayload = '<wp-export><post id="bad-stream-decodeparms-operand"/></wp-export>';
$validChecksum = md5($validPayload);
$paramsOperandChecksum = md5($paramsOperandPayload);
$decodeParmsOperandChecksum = md5($decodeParmsOperandPayload);
$decodeParmsOperandEncoded = gzcompress($decodeParmsOperandPayload);
if (!is_string($decodeParmsOperandEncoded)) {
    throw new RuntimeException('Unable to compress attachment stream-operand smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Stream Operand Boundary) Tj ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R 30 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(bad-params-operand.xml) 10 0 R (bad-decodeparms-operand.xml) 20 0 R (valid-stream-boundary.xml) 30 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (bad-params-operand.xml) /Desc (Bad top-level Params operand source) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($paramsOperandPayload) . " /CheckSum <{$paramsOperandChecksum}> /ModDate (D:20260608052619Z) >> 44 /Length " . strlen($paramsOperandPayload) . " >>\n"
    . "stream\n{$paramsOperandPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (bad-decodeparms-operand.xml) /Desc (Bad top-level DecodeParms operand source) /AFRelationship /Alternative /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Filter /FlateDecode /DecodeParms << /Predictor 1 >> 17 /Params << /Size " . strlen($decodeParmsOperandPayload) . " /CheckSum <{$decodeParmsOperandChecksum}> /ModDate (D:20260608052620Z) >> /Length " . strlen($decodeParmsOperandEncoded) . " >>\n"
    . "stream\n{$decodeParmsOperandEncoded}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (valid-stream-boundary.xml) /Desc (Valid stream dictionary operand source) /AFRelationship /Source /EF << /F 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608052621Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);
if ($summaryJson === false || $filesJson === false) {
    throw new RuntimeException('Expected attachment stream-operand smoke JSON.');
}

$attachment = $summary['attachments'][0] ?? null;
$file = $files[0] ?? null;
if (
    ($summary['attachment_count'] ?? null) !== 1
    || ($summary['filenames'] ?? []) !== ['valid-stream-boundary.xml']
    || !is_array($attachment)
    || !is_array($file)
    || ($attachment['filename'] ?? null) !== 'valid-stream-boundary.xml'
    || ($attachment['relationship_role'] ?? null) !== 'original_source'
    || ($attachment['byte_length'] ?? null) !== strlen($validPayload)
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $attachment)
    || ($file['content'] ?? null) !== $validPayload
    || ($file['checksum_matches'] ?? null) !== true
    || $plainText !== 'Visible Attachment Stream Operand Boundary'
    || ($summary['executes_python_or_models'] ?? null) !== false
    || ($summary['executes_external_pdf_tools'] ?? null) !== false
) {
    throw new RuntimeException('Expected only the valid stream-operand attachment to reach WordPress review.');
}

foreach ([
    'bad-params-operand.xml',
    'bad-decodeparms-operand.xml',
    'Bad top-level Params operand source',
    'Bad top-level DecodeParms operand source',
    $paramsOperandPayload,
    $decodeParmsOperandPayload,
    $paramsOperandChecksum,
    $decodeParmsOperandChecksum,
] as $hidden) {
    if (str_contains($summaryJson, $hidden) || str_contains($filesJson, $hidden) || str_contains($plainText, $hidden)) {
        throw new RuntimeException('Malformed attachment stream operand leaked into WordPress review.');
    }
}

echo json_encode([
    'scenario' => 'wordpress-pdf-attachment-stream-operand-boundary-currentbase',
    'source' => 'sddai/markerPDF no-GPU native PDF EmbeddedFiles attachment boundary',
    'purpose' => 'Reject malformed embedded-file stream dictionaries before WordPress attachment import.',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'total_bytes' => $summary['total_bytes'],
    'valid_relationship_role' => $attachment['relationship_role'],
    'valid_checksum_matches' => $attachment['checksum_matches'],
    'malformed_streams_excluded' => true,
    'summary_exposes_attachment_bytes' => array_key_exists('bytes', $attachment),
    'embedded_file_payload_available' => ($file['content'] ?? null) === $validPayload,
    'visible_text' => $plainText,
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
