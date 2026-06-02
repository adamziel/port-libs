<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="184406"/></wp-export>';
$relatedJson = '{"source":"rf-sidecar","status":"current"}';
$relatedText = 'BT /F1 12 Tf 72 720 Td (Related File Payload Leak) Tj ET';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$relatedChecksum = strtoupper(hash('md5', $relatedJson));
$compressedRelatedJson = gzcompress($relatedJson);
if (!is_string($compressedRelatedJson)) {
    throw new RuntimeException('Unable to compress related-file smoke payload.');
}

$pageContent = 'BT /F1 12 Tf 72 720 Td (Associated Related File Body) Tj ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress source plus related files) /AFRelationship /Source /EF << /F 11 0 R >> /RF << /F [12 0 R 13 0 R] >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Filter /FlateDecode /Params << /Size " . strlen($relatedJson) . " /CheckSum <{$relatedChecksum}> /ModDate (D:20260602184406Z) >> /Length " . strlen($compressedRelatedJson) . " >>\nstream\n{$compressedRelatedJson}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Length " . strlen($relatedText) . " >>\nstream\n{$relatedText}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$associatedFiles = $metadata['associated_files'] ?? [];
$source = $associatedFiles[0] ?? [];
$relatedFiles = $source['related_files'] ?? [];

if (($source['related_file_count'] ?? null) !== 2 || count($relatedFiles) !== 2) {
    throw new RuntimeException('Expected two related-file review rows.');
}
if (($source['provenance_review']['related_files']['embedded_file_objects'] ?? []) !== [12, 13]) {
    throw new RuntimeException('Expected related-file provenance object numbers.');
}
if (!is_string($encoded) || str_contains($encoded, $sourcePayload) || str_contains($encoded, $relatedJson) || str_contains($encoded, $relatedText)) {
    throw new RuntimeException('Expected associated and related payload bytes to stay out of metadata JSON.');
}
if ($plainText !== 'Associated Related File Body' || str_contains($plainText, '<wp-export>') || str_contains($plainText, 'Related File Payload Leak')) {
    throw new RuntimeException('Expected related-file payload bytes to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-metadata-associated-related-files-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-filespec-related-files-review-parser',
    'native_boundary' => 'catalog /AF FileSpec /RF related-file streams summarized as review metadata before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'associated_filename' => $source['filename'] ?? null,
    'relationship' => $source['relationship'] ?? null,
    'primary_checksum_matches' => $source['checksum_matches'] ?? null,
    'related_file_count' => $source['related_file_count'] ?? 0,
    'related_rf_keys' => array_map(static fn (array $file): ?string => $file['rf_key'] ?? null, $relatedFiles),
    'related_mime_types' => array_map(static fn (array $file): ?string => $file['mime_type'] ?? null, $relatedFiles),
    'related_payload_content_omitted' => !array_key_exists('content', $relatedFiles[0] ?? []) && !array_key_exists('content', $relatedFiles[1] ?? []),
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($relatedFiles as $file) {
    echo '<!-- markerpdf:associated-related-file-review ' . $htmlJson([
        'rf_key' => $file['rf_key'] ?? null,
        'related_file_index' => $file['related_file_index'] ?? null,
        'embedded_file_object' => $file['embedded_file_object'] ?? null,
        'mime_type' => $file['mime_type'] ?? null,
        'size' => $file['size'] ?? null,
        'declared_size' => $file['declared_size'] ?? null,
        'checksum_matches' => $file['checksum_matches'] ?? null,
    ]) . " -->\n";
}
