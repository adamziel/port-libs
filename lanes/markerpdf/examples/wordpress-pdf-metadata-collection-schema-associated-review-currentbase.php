<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Associated Collection XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$sourcePayload = '<wp-export><post id="1628"/></wp-export>';
$previewPayload = '{"preview":"metadata-schema"}';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$previewChecksum = str_repeat('0a', 16);
$compressedXmp = gzcompress($xmp);
$compressedProfile = gzcompress('Associated Collection ICC bytes should stay nested');
if (!is_string($compressedXmp) || !is_string($compressedProfile)) {
    throw new RuntimeException('Unable to compress collection schema associated metadata fixture.');
}

$pageContent = 'BT /F1 12 Tf 72 720 Td (Collection Schema Metadata Body) Tj ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /AF [10 0 R << /Type /Filespec /F (preview.json) /Desc (Rendered preview JSON) /AFRelationship /Alternative /CI << /Subject (Preview JSON) /Priority << /Type /CollectionSubitem /D 1 /P (P) >> /ReviewDate (D:20260602162500Z) >> /Metadata 31 0 R /OutputIntents [40 0 R] /EF << /F 21 0 R >> >>] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Collection /View /T /D (source-unicode.xml) /Schema << /NameField << /Subtype /F /N (Filename) /O 1 >> /DescriptionField << /Subtype /Desc /N (Description) /O 2 /V true /E false >> /BytesField << /Subtype /Size /N (Bytes) /O 3 >> /Subject << /Subtype /S /N (Subject) /O 4 >> /Priority << /Subtype /N /N (Priority) /O 5 >> /ReviewDate << /Subtype /D /N (Reviewed) /O 6 >> >> /Sort << /S [/Priority /ReviewDate] /A [true false] >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (source-unicode.xml) /Desc (Original WordPress export) /AFRelationship /Source /CI 30 0 R /Metadata 31 0 R /OutputIntents [40 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602162400Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($previewPayload) . " /CheckSum <{$previewChecksum}> >> /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /CollectionItem /Subject (Migration Source) /Priority << /Type /CollectionSubitem /D 2 /P (P) >> /ReviewDate (D:20260602162600Z) >>\nendobj\n"
    . "31 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Associated Collection sRGB) /Info (Nested collection PDF/A) /DestOutputProfile 41 0 R >>\nendobj\n"
    . "41 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedProfile) . " >>\nstream\n{$compressedProfile}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$collection = $metadata['catalog']['collection'] ?? [];
$associatedFiles = $collection['associated_files'] ?? [];

if (count($associatedFiles) !== 2) {
    throw new RuntimeException('Expected two catalog collection associated-file review rows.');
}
if (($collection['schema']['Priority']['label'] ?? null) !== 'Priority') {
    throw new RuntimeException('Expected /Subtype /N schema label to survive collection parsing.');
}
if (($associatedFiles[0]['collection_field_values']['Priority']['display_value'] ?? null) !== 'P2') {
    throw new RuntimeException('Expected source collection subitem display value.');
}
if (($associatedFiles[0]['checksum_matches'] ?? null) !== true || ($associatedFiles[1]['checksum_matches'] ?? null) !== false) {
    throw new RuntimeException('Expected verified and stale checksum review states.');
}
if (!is_string($encoded) || str_contains($encoded, 'Associated Collection XMP Title') || str_contains($encoded, 'Associated Collection ICC bytes')) {
    throw new RuntimeException('Expected associated XMP/ICC payloads to remain attachment-local review metadata.');
}
if (str_contains($plainText, '<wp-export>') || str_contains($plainText, 'metadata-schema')) {
    throw new RuntimeException('Expected associated payload bytes to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-metadata-collection-schema-associated-review-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-metadata-collection-schema-associated-review',
    'native_boundary' => 'catalog /Collection schema plus catalog /AF FileSpec review metadata before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'page_mode' => $metadata['page_mode'] ?? null,
    'collection_source' => $collection['source'] ?? null,
    'schema_fields' => array_keys($collection['schema'] ?? []),
    'sort_keys' => $collection['sort']['keys'] ?? [],
    'associated_file_count' => count($associatedFiles),
    'associated_relationships' => array_map(static fn (array $file): ?string => $file['relationship'] ?? null, $associatedFiles),
    'priority_display_values' => array_map(
        static fn (array $file): ?string => $file['collection_field_values']['Priority']['display_value'] ?? null,
        $associatedFiles
    ),
    'checksum_matches' => array_map(static fn (array $file): ?bool => $file['checksum_matches'] ?? null, $associatedFiles),
    'associated_outputintent_not_pdfa_root' => ($metadata['output_intents'] ?? []) === [] && !isset($metadata['pdfa']),
    'associated_payload_content_omitted' => !array_key_exists('content', $associatedFiles[0]) && !array_key_exists('content', $associatedFiles[1]),
    'associated_xmp_payload_omitted' => is_string($encoded) && !str_contains($encoded, 'Associated Collection XMP Title'),
    'associated_icc_payload_omitted' => is_string($encoded) && !str_contains($encoded, 'Associated Collection ICC bytes'),
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($associatedFiles as $file) {
    echo '<!-- markerpdf:collection-schema-associated-file ' . $htmlJson([
        'filename' => $file['filename'] ?? null,
        'description' => $file['description'] ?? null,
        'relationship' => $file['relationship'] ?? null,
        'mime_type' => $file['mime_type'] ?? null,
        'collection_item' => $file['collection_item'] ?? [],
        'collection_field_values' => $file['collection_field_values'] ?? [],
        'output_intents_review' => $file['output_intents_review'] ?? [],
        'checksum_matches' => $file['checksum_matches'] ?? null,
    ]) . " -->\n";
}
