<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validPayload = '<wp-export><post id="valid-type-boundary"/></wp-export>';
$duplicateFileSpecPayload = '<wp-export><post id="duplicate-filespec-type-leak"/></wp-export>';
$duplicateStreamTypePayload = '<wp-export><post id="duplicate-stream-type-leak"/></wp-export>';
$duplicateSubtypePayload = '<wp-export><post id="duplicate-stream-subtype-leak"/></wp-export>';
$validChecksum = md5($validPayload);
$duplicateFileSpecChecksum = md5($duplicateFileSpecPayload);
$duplicateStreamTypeChecksum = md5($duplicateStreamTypePayload);
$duplicateSubtypeChecksum = md5($duplicateSubtypePayload);
$content = 'BT /F1 12 Tf 72 720 Td (Attachment Type Boundary Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R 30 0 R 40 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names ["
    . "(valid-type.xml) 10 0 R "
    . "(duplicate-filespec-type.xml) 20 0 R "
    . "(duplicate-stream-type.xml) 30 0 R "
    . "(duplicate-stream-subtype.xml) 40 0 R"
    . "] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (valid-type.xml) /Desc (Valid type boundary source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260609000817Z) >> /Length " . strlen($validPayload) . " >>\n"
    . "stream\n{$validPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Catalog /Type /Filespec /F (duplicate-filespec-type.xml) /Desc (Duplicate FileSpec Type should stay private) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateFileSpecPayload) . " /CheckSum <{$duplicateFileSpecChecksum}> >> /Length " . strlen($duplicateFileSpecPayload) . " >>\n"
    . "stream\n{$duplicateFileSpecPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (duplicate-stream-type.xml) /Desc (Duplicate stream Type should stay private) /AFRelationship /Data /EF << /F 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /Metadata /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateStreamTypePayload) . " /CheckSum <{$duplicateStreamTypeChecksum}> >> /Length " . strlen($duplicateStreamTypePayload) . " >>\n"
    . "stream\n{$duplicateStreamTypePayload}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Filespec /F (duplicate-stream-subtype.xml) /Desc (Duplicate stream Subtype should stay private) /AFRelationship /Data /EF << /F 41 0 R >> >>\nendobj\n"
    . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateSubtypePayload) . " /CheckSum <{$duplicateSubtypeChecksum}> >> /Length " . strlen($duplicateSubtypePayload) . " >>\n"
    . "stream\n{$duplicateSubtypePayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$hiddenMarkers = [
    'duplicate-filespec-type.xml',
    'duplicate-stream-type.xml',
    'duplicate-stream-subtype.xml',
    'Duplicate FileSpec Type should stay private',
    'Duplicate stream Type should stay private',
    'Duplicate stream Subtype should stay private',
    $duplicateFileSpecPayload,
    $duplicateStreamTypePayload,
    $duplicateSubtypePayload,
    $duplicateFileSpecChecksum,
    $duplicateStreamTypeChecksum,
    $duplicateSubtypeChecksum,
    $validPayload,
];

$hiddenExcluded = true;
foreach ($hiddenMarkers as $hiddenMarker) {
    $hiddenExcluded = $hiddenExcluded
        && !str_contains($summaryJson, $hiddenMarker)
        && !str_contains($filesJson, $hiddenMarker)
        && !str_contains($plainText, $hiddenMarker);
}

if (($summary['attachment_count'] ?? null) !== 1
    || ($summary['filenames'] ?? null) !== ['valid-type.xml']
    || count($files) !== 1
    || ($files[0]['filename'] ?? null) !== 'valid-type.xml'
    || ($files[0]['content'] ?? null) !== $validPayload
    || !$hiddenExcluded
    || str_contains($summaryJson, $validPayload)
    || $plainText !== 'Attachment Type Boundary Body'
) {
    throw new RuntimeException('Expected duplicate FileSpec/EmbeddedFile type-key attachment rows to be excluded.');
}

echo "<!-- markerpdf-pdf-attachment-type-key-boundary " . htmlspecialchars(json_encode([
    'native_boundary' => 'duplicate FileSpec /Type and EmbeddedFile stream /Type or /Subtype keys are rejected before WordPress attachment preflight',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'duplicate_filespec_type_excluded' => !str_contains($summaryJson, 'duplicate-filespec-type.xml'),
    'duplicate_stream_type_excluded' => !str_contains($summaryJson, 'duplicate-stream-type.xml'),
    'duplicate_stream_subtype_excluded' => !str_contains($summaryJson, 'duplicate-stream-subtype.xml'),
    'payload_bytes_omitted_from_summary' => !str_contains($summaryJson, $validPayload),
    'visible_text_excludes_attachment_payloads' => !str_contains($plainText, '<wp-export>'),
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES) . " -->\n";
echo "<!-- wp:file {\"href\":\"valid-type.xml\"} -->\n";
echo "<div class=\"wp-block-file\"><a href=\"valid-type.xml\">valid-type.xml</a></div>\n";
echo "<!-- /wp:file -->\n";
