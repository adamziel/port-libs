<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ambiguousPayload = '<wp-export><post id="ambiguous-filespec-metadata"/></wp-export>';
$validPayload = '<wp-export><post id="valid-filespec-metadata"/></wp-export>';
$ambiguousChecksum = md5($ambiguousPayload);
$validChecksum = md5($validPayload);
$validPermanentIdHex = '00112233445566778899aabbccddeeff';
$validChangingIdHex = '776f726470726573732d6174746163686d656e742d7633';
$decoyIdentifierHex = 'badf00d';
$content = 'BT /F1 12 Tf 72 720 Td (FileSpec Metadata Key Operand Boundary Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(ambiguous-metadata.xml) 10 0 R (valid-metadata.xml) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (ambiguous-metadata.xml) /Desc (Ambiguous metadata WordPress source) /AFRelationship /Supplement /FS /URL /FS /Launch /ID [<0011> <2233>] <{$decoyIdentifierHex}> /V true false /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($ambiguousPayload) . " /CheckSum <{$ambiguousChecksum}> /ModDate (D:20260608104807Z) >> /Length " . strlen($ambiguousPayload) . " >>\n"
    . "stream\n{$ambiguousPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /FS /URL /F (https://example.test/private/valid-metadata.xml) /UF (valid-metadata.xml) /Desc (Valid metadata WordPress source) /AFRelationship /Source /ID [<{$validPermanentIdHex}> <{$validChangingIdHex}>] /V false /EF << /UF 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608104808Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '';
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES) ?: '';

$ambiguous = $summary['attachments'][0] ?? [];
$valid = $summary['attachments'][1] ?? [];
$ambiguousFile = $files[0] ?? [];
$validFile = $files[1] ?? [];

if (($summary['attachment_count'] ?? null) !== 2
    || ($ambiguous['filename'] ?? null) !== 'ambiguous-metadata.xml'
    || array_key_exists('file_system', $ambiguous)
    || array_key_exists('file_identifier', $ambiguous)
    || array_key_exists('volatile', $ambiguous)
    || ($valid['filename'] ?? null) !== 'valid-metadata.xml'
    || ($valid['file_system'] ?? null) !== 'URL'
    || ($valid['volatile'] ?? null) !== false
    || ($valid['file_identifier']['identifier_status'] ?? null) !== 'complete_file_identifier_pair'
    || ($ambiguousFile['content'] ?? null) !== $ambiguousPayload
    || array_key_exists('file_system', $ambiguousFile)
    || ($validFile['content'] ?? null) !== $validPayload
    || ($validFile['file_system'] ?? null) !== 'URL'
    || str_contains($summaryJson, 'Launch')
    || str_contains($filesJson, 'Launch')
    || str_contains($summaryJson, $decoyIdentifierHex)
    || str_contains($filesJson, $decoyIdentifierHex)
    || str_contains($summaryJson, $ambiguousPayload)
    || str_contains($summaryJson, $validPayload)
    || str_contains($plainText, '<wp-export>')
) {
    throw new RuntimeException('Expected malformed FileSpec /FS /ID /V metadata to be omitted while valid embedded attachments remain reviewable.');
}

echo '<!-- markerpdf:attachment-filespec-metadata-key-operand-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-embedded-file-attachment-parser',
    'native_boundary' => 'FileSpec /FS /ID /V duplicate and trailing operands are review-metadata only and fail closed',
    'attachment_count' => $summary['attachment_count'],
    'ambiguous_attachment_preserved' => ($ambiguous['filename'] ?? null) === 'ambiguous-metadata.xml',
    'ambiguous_metadata_omitted' => !array_key_exists('file_system', $ambiguous)
        && !array_key_exists('file_identifier', $ambiguous)
        && !array_key_exists('volatile', $ambiguous),
    'valid_metadata_preserved' => ($valid['file_system'] ?? null) === 'URL'
        && ($valid['volatile'] ?? null) === false
        && ($valid['file_identifier']['identifier_status'] ?? null) === 'complete_file_identifier_pair',
    'payload_bytes_omitted_from_summary' => !str_contains($summaryJson, $ambiguousPayload)
        && !str_contains($summaryJson, $validPayload),
    'decoy_metadata_excluded' => !str_contains($summaryJson, 'Launch')
        && !str_contains($filesJson, 'Launch')
        && !str_contains($summaryJson, $decoyIdentifierHex)
        && !str_contains($filesJson, $decoyIdentifierHex),
    'visible_text' => $plainText,
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
    echo "<!-- /wp:file -->\n";
}
