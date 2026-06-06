<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$metadataDecoyPayload = '<?xpacket begin="w"?><wp-export><post id="metadata-stream-decoy-smoke"/></wp-export><?xpacket end="w"?>';
$xobjectDecoyPayload = '<wp-export><post id="xobject-stream-decoy-smoke"/></wp-export>';
$legacyPayload = '<wp-export><post id="legacy-untyped-embedded-file-smoke"/></wp-export>';
$typedPayload = '<wp-export><post id="typed-embedded-file-smoke"/></wp-export>';
$metadataChecksum = md5($metadataDecoyPayload);
$xobjectChecksum = md5($xobjectDecoyPayload);
$legacyChecksum = md5($legacyPayload);
$typedChecksum = md5($typedPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Embedded File Stream Type Smoke Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [30 0 R 40 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names ["
    . "(metadata-decoy.xml) 10 0 R "
    . "(xobject-decoy.xml) 20 0 R "
    . "(legacy-untyped.xml) 30 0 R "
    . "(typed-source.xml) 40 0 R"
    . "] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (metadata-decoy.xml) /Desc (Metadata stream decoy) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /Metadata /Subtype /XML /Params << /Size " . strlen($metadataDecoyPayload) . " /CheckSum <{$metadataChecksum}> >> /Length " . strlen($metadataDecoyPayload) . " >>\n"
    . "stream\n{$metadataDecoyPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (xobject-decoy.xml) /Desc (XObject stream decoy) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /XObject /Subtype /Image /Params << /Size " . strlen($xobjectDecoyPayload) . " /CheckSum <{$xobjectChecksum}> >> /Length " . strlen($xobjectDecoyPayload) . " >>\n"
    . "stream\n{$xobjectDecoyPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (legacy-untyped.xml) /Desc (Legacy untyped embedded source) /AFRelationship /Source /EF << /F 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Subtype /text#2Fxml /Params << /Size " . strlen($legacyPayload) . " /CheckSum <{$legacyChecksum}> /ModDate (D:20260606155704Z) >> /Length " . strlen($legacyPayload) . " >>\n"
    . "stream\n{$legacyPayload}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Filespec /F (typed-source.xml) /Desc (Typed embedded source) /AFRelationship /Source /EF << /F 41 0 R >> >>\nendobj\n"
    . "41 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($typedPayload) . " /CheckSum <{$typedChecksum}> /ModDate (D:20260606155705Z) >> /Length " . strlen($typedPayload) . " >>\n"
    . "stream\n{$typedPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachmentNames = $summary['filenames'] ?? [];

if (($summary['attachment_count'] ?? null) !== 2
    || $attachmentNames !== ['legacy-untyped.xml', 'typed-source.xml']
    || count($files) !== 2
    || count($metadata['embedded_files'] ?? []) !== 2
    || ($files[0]['content'] ?? null) !== $legacyPayload
    || ($files[1]['content'] ?? null) !== $typedPayload
    || str_contains($summaryJson, 'metadata-decoy.xml')
    || str_contains($summaryJson, 'xobject-decoy.xml')
    || str_contains($summaryJson, $metadataDecoyPayload)
    || str_contains($summaryJson, $xobjectDecoyPayload)
    || str_contains($summaryJson, $legacyPayload)
    || str_contains($summaryJson, $typedPayload)
    || str_contains($filesJson, $metadataDecoyPayload)
    || str_contains($filesJson, $xobjectDecoyPayload)
    || str_contains($metadataJson, $metadataDecoyPayload)
    || str_contains($metadataJson, $xobjectDecoyPayload)
    || $plainText !== 'Embedded File Stream Type Smoke Body'
) {
    throw new RuntimeException('Expected typed non-EmbeddedFile EF streams to stay out of WordPress attachment review.');
}

$firstAttachment = $summary['attachments'][0] ?? [];
$secondAttachment = $summary['attachments'][1] ?? [];

echo "<!-- markerpdf-pdf-attachment-ef-stream-type-boundary " . htmlspecialchars(json_encode([
    'native_boundary' => 'FileSpec /EF stream type validation excludes typed non-EmbeddedFile payloads',
    'attachment_count' => $summary['attachment_count'],
    'embedded_file_count' => count($files),
    'metadata_embedded_file_count' => count($metadata['embedded_files'] ?? []),
    'filenames' => $attachmentNames,
    'legacy_untyped_stream_preserved' => ($firstAttachment['stream_object_id'] ?? null) === 31,
    'typed_embedded_file_stream_preserved' => ($secondAttachment['stream_object_id'] ?? null) === 41,
    'metadata_decoy_rejected' => !str_contains($summaryJson, 'metadata-decoy.xml'),
    'xobject_decoy_rejected' => !str_contains($summaryJson, 'xobject-decoy.xml'),
    'payload_omitted_from_summary' => !str_contains($summaryJson, $legacyPayload) && !str_contains($summaryJson, $typedPayload),
    'visible_text_preserved' => $plainText === 'Embedded File Stream Type Smoke Body',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($summary['attachments'] as $attachment) {
    echo "<!-- wp:file {\"href\":\"media/" . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\"} -->\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n";
}
