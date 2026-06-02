<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="schema-smoke"/></wp-export>';
$previewPayload = '{"preview":"associated-file-schema"}';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$previewChecksum = str_repeat('0b', 16);
$pageContent = 'BT /F1 12 Tf 72 720 Td (Associated File Schema Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Collection /View /T /D (source-unicode.xml) /Schema << /NameField << /Subtype /F /N (Filename) /O 1 >> /DescriptionField << /Subtype /Desc /N (Description) /O 2 /V true /E false >> /ModifiedField << /Subtype /ModDate /N (Modified) /O 3 >> /BytesField << /Subtype /Size /N (Bytes) /O 4 >> /Subject << /Subtype /S /N (Subject) /O 5 >> /Priority << /Subtype /N /N (Priority) /O 6 >> /ReviewDate << /Subtype /D /N (Reviewed) /O 7 >> >> /Sort << /S [/Priority /ReviewDate] /A [true false] >> >>\nendobj\n"
    . "6 0 obj\n<< /Limits [(preview.json) (source-unicode.xml)] /Names [(source-unicode.xml) 10 0 R (preview.json) << /Type /Filespec /F (preview.json) /Desc (Rendered name-tree preview) /AFRelationship /Alternative /CI << /Subject (Preview JSON) /Priority << /Type /CollectionSubitem /D 1 /P (P) >> /ReviewDate (D:20260602200700Z) >> /EF << /F 21 0 R >> >>] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (source-unicode.xml) /Desc (Original WordPress export) /AFRelationship /Source /CI 30 0 R /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602200600Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($previewPayload) . " /CheckSum <{$previewChecksum}> >> /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /CollectionItem /Subject (Migration Source) /Priority << /Type /CollectionSubitem /D 2 /P (P) >> /ReviewDate (D:20260602200800Z) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$embeddedFiles = $metadata['embedded_files'] ?? [];

if (count($embeddedFiles) !== 2) {
    throw new RuntimeException('Expected two EmbeddedFiles name-tree metadata rows.');
}
if (($embeddedFiles[0]['collection_field_values']['Priority']['display_value'] ?? null) !== 'P2') {
    throw new RuntimeException('Expected source FileSpec collection schema fields.');
}
if (($embeddedFiles[1]['collection_field_values']['Priority']['display_value'] ?? null) !== 'P1') {
    throw new RuntimeException('Expected preview FileSpec collection schema fields.');
}
if (($embeddedFiles[0]['checksum_matches'] ?? null) !== true || ($embeddedFiles[1]['checksum_matches'] ?? null) !== false) {
    throw new RuntimeException('Expected current checksum match states.');
}
if (!is_string($encoded) || str_contains($encoded, $sourcePayload) || str_contains($encoded, $previewPayload)) {
    throw new RuntimeException('Expected attachment payload bytes to stay out of review metadata.');
}
if (str_contains($plainText, '<wp-export>') || str_contains($plainText, 'associated-file-schema')) {
    throw new RuntimeException('Expected attachment payload bytes to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-metadata-associated-file-schema-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-metadata-associated-file-schema-review',
    'native_boundary' => 'catalog /Collection schema applied to /Names /EmbeddedFiles FileSpec rows before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'collection_source' => $metadata['collection']['source'] ?? null,
    'schema_fields' => array_keys($metadata['collection']['schema'] ?? []),
    'sort_keys' => $metadata['collection']['sort']['keys'] ?? [],
    'embedded_file_count' => count($embeddedFiles),
    'embedded_relationships' => array_map(static fn (array $file): ?string => $file['relationship'] ?? null, $embeddedFiles),
    'priority_display_values' => array_map(
        static fn (array $file): ?string => $file['collection_field_values']['Priority']['display_value'] ?? null,
        $embeddedFiles
    ),
    'checksum_matches' => array_map(static fn (array $file): ?bool => $file['checksum_matches'] ?? null, $embeddedFiles),
    'payload_content_omitted' => !array_key_exists('content', $embeddedFiles[0]) && !array_key_exists('content', $embeddedFiles[1]),
    'payload_text_not_visible' => !str_contains($plainText, '<wp-export>') && !str_contains($plainText, 'associated-file-schema'),
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($embeddedFiles as $file) {
    echo '<!-- markerpdf:embedded-file-schema-review ' . $htmlJson([
        'name_tree_name' => $file['name_tree_name'] ?? null,
        'filename' => $file['filename'] ?? null,
        'description' => $file['description'] ?? null,
        'relationship' => $file['relationship'] ?? null,
        'mime_type' => $file['mime_type'] ?? null,
        'collection_item' => $file['collection_item'] ?? [],
        'collection_field_values' => $file['collection_field_values'] ?? [],
        'checksum_matches' => $file['checksum_matches'] ?? null,
    ]) . " -->\n";
}
