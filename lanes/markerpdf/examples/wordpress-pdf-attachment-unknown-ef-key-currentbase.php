<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$unknownPayload = '<wp-export><post id="unknown-ef-key-decoy"/></wp-export>';
$validPayload = '<wp-export><post id="valid-standard-ef-key"/></wp-export>';
$unknownChecksum = md5($unknownPayload);
$validChecksum = md5($validPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Unknown EF Key Boundary Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(unknown-private-ef.xml) 10 0 R (valid-standard-ef.xml) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (unknown-private-ef.xml) /Desc (Unknown EF key should stay review-only) /AFRelationship /Alternative /EF << /Private 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($unknownPayload) . " /CheckSum <{$unknownChecksum}> >> /Length " . strlen($unknownPayload) . " >>\n"
    . "stream\n{$unknownPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (valid-standard-ef.xml) /Desc (Valid standard EF key WordPress source) /AFRelationship /Source /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608215631Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? null;
$embeddedFile = $files[0] ?? null;

if (!is_array($attachment)
    || !is_array($embeddedFile)
    || ($summary['attachment_count'] ?? null) !== 1
    || ($summary['total_bytes'] ?? null) !== strlen($validPayload)
    || ($summary['filenames'] ?? null) !== ['valid-standard-ef.xml']
    || ($attachment['filename'] ?? null) !== 'valid-standard-ef.xml'
    || ($attachment['description'] ?? null) !== 'Valid standard EF key WordPress source'
    || ($attachment['relationship'] ?? null) !== 'Source'
    || ($attachment['ef_key'] ?? null) !== 'F'
    || ($attachment['associated_file_source'] ?? null) !== 'catalog_af'
    || ($attachment['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $attachment)
    || ($embeddedFile['filename'] ?? null) !== 'valid-standard-ef.xml'
    || ($embeddedFile['content'] ?? null) !== $validPayload
    || ($embeddedFile['checksum_matches'] ?? null) !== true
    || str_contains($summaryJson, 'unknown-private-ef.xml')
    || str_contains($summaryJson, 'Unknown EF key should stay review-only')
    || str_contains($summaryJson, $unknownPayload)
    || str_contains($summaryJson, $unknownChecksum)
    || str_contains($summaryJson, $validPayload)
    || str_contains($filesJson, $unknownPayload)
    || str_contains($filesJson, $unknownChecksum)
    || $plainText !== 'Unknown EF Key Boundary Body'
    || ($summary['executes_python_or_models'] ?? null) !== false
    || ($summary['executes_external_pdf_tools'] ?? null) !== false
) {
    throw new RuntimeException('Expected unknown FileSpec /EF keys to stay out of WordPress attachment summaries.');
}

echo '<!-- markerpdf-pdf-attachment-unknown-ef-key-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-attachment-ef-key-boundary',
    'native_boundary' => 'FileSpec /EF standard-key selection only',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'accepted_ef_key' => $attachment['ef_key'],
    'unknown_ef_key_excluded' => !str_contains($summaryJson, 'unknown-private-ef.xml')
        && !str_contains($filesJson, $unknownPayload),
    'unknown_payload_omitted_from_summary' => !str_contains($summaryJson, $unknownPayload),
    'valid_payload_omitted_from_summary' => !str_contains($summaryJson, $validPayload),
    'valid_payload_returned_by_full_extractor' => ($embeddedFile['content'] ?? null) === $validPayload,
    'visible_text_excludes_attachment_payloads' => $plainText === 'Unknown EF Key Boundary Body',
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
