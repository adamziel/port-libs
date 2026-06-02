<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Catalog AF XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$rootProfile = 'Root catalog ICC profile bytes';
$associatedProfile = 'Associated catalog ICC bytes must stay attachment-local';
$sourcePayload = '<wp-export><post id="1715"/></wp-export>';
$previewPayload = 'Rendered associated preview bytes';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$compressedXmp = gzcompress($xmp);
$compressedRootProfile = gzcompress($rootProfile);
$compressedAssociatedProfile = gzcompress($associatedProfile);
if (!is_string($compressedXmp) || !is_string($compressedRootProfile) || !is_string($compressedAssociatedProfile)) {
    throw new RuntimeException('Unable to compress metadata output-intent associated-file fixture.');
}

$fileLang = strtoupper(bin2hex("\xfe\xff\x00e\x00s\x00-\x00M\x00X"));
$pageContent = 'BT /F1 12 Tf 72 720 Td (Catalog Associated Metadata Body) Tj ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /OutputIntents [9 0 R] /AF [10 0 R << /Type /Filespec /F (preview.pdf) /Desc (Rendered PDF alternative) /AFRelationship /Alternative /Lang (fr-CA) /OutputIntents [13 0 R] /EF << /F 15 0 R >> >>] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedRootProfile) . " >>\nstream\n{$compressedRootProfile}\nendstream\nendobj\n"
    . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedAssociatedProfile) . " >>\nstream\n{$compressedAssociatedProfile}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Root catalog sRGB) /Info (Root PDF/A profile) /DestOutputProfile 7 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (source-unicode.xml) /Desc (Original WordPress export) /AFRelationship /Source /Lang <{$fileLang}> /Metadata 5 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602165500Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Associated catalog sRGB) /Info (Nested associated PDF/A) /DestOutputProfile 8 0 R >>\nendobj\n"
    . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$associatedFiles = $metadata['catalog']['associated_files'] ?? [];

if (($metadata['language'] ?? null) !== 'en-US') {
    throw new RuntimeException('Expected catalog /Lang to be exposed as document metadata.');
}
if (($metadata['pdfa']['output_condition_identifiers'] ?? []) !== ['Root catalog sRGB']) {
    throw new RuntimeException('Expected only the root catalog OutputIntent to contribute PDF/A metadata.');
}
if (count($associatedFiles) !== 2) {
    throw new RuntimeException('Expected source and alternative catalog associated-file review rows.');
}
if (($associatedFiles[0]['language'] ?? null) !== 'es-MX' || ($associatedFiles[1]['language'] ?? null) !== 'fr-CA') {
    throw new RuntimeException('Expected associated-file language review metadata.');
}
if (($associatedFiles[0]['checksum_matches'] ?? null) !== true) {
    throw new RuntimeException('Expected source associated-file checksum match metadata.');
}
if (!is_string($encoded) || str_contains($encoded, 'Catalog AF XMP Title') || str_contains($encoded, $sourcePayload) || str_contains($encoded, $previewPayload) || str_contains($encoded, $associatedProfile)) {
    throw new RuntimeException('Expected associated-file XMP, payload, and ICC bytes to remain review-only and omitted.');
}
if (str_contains($plainText, '<wp-export>') || str_contains($plainText, 'Associated catalog ICC bytes')) {
    throw new RuntimeException('Expected associated-file bytes to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-metadata-outputintent-lang-associatedfile-review-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-catalog-associated-files-metadata-review',
    'native_boundary' => 'catalog /Lang, root /OutputIntents, and catalog /AF FileSpec review metadata before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'language' => $metadata['language'] ?? null,
    'pdfa_identifiers' => $metadata['pdfa']['output_condition_identifiers'] ?? [],
    'associated_file_count' => count($associatedFiles),
    'associated_languages' => array_map(static fn (array $file): ?string => $file['language'] ?? null, $associatedFiles),
    'associated_relationships' => array_map(static fn (array $file): ?string => $file['relationship'] ?? null, $associatedFiles),
    'associated_outputintent_identifiers' => array_map(
        static fn (array $file): ?string => $file['output_intents_review'][0]['OutputConditionIdentifier'] ?? null,
        $associatedFiles
    ),
    'associated_payload_content_omitted' => !array_key_exists('content', $associatedFiles[0]) && !array_key_exists('content', $associatedFiles[1]),
    'associated_xmp_not_promoted' => is_string($encoded) && !str_contains($encoded, 'Catalog AF XMP Title'),
    'associated_outputintent_not_pdfa_root' => ($metadata['pdfa']['output_condition_identifiers'] ?? []) === ['Root catalog sRGB'],
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($associatedFiles as $file) {
    echo '<!-- markerpdf:catalog-associated-file-review ' . $htmlJson([
        'filename' => $file['filename'] ?? null,
        'platform_filename' => $file['platform_filename'] ?? null,
        'description' => $file['description'] ?? null,
        'relationship' => $file['relationship'] ?? null,
        'language' => $file['language'] ?? null,
        'mime_type' => $file['mime_type'] ?? null,
        'size' => $file['size'] ?? null,
        'checksum_matches' => $file['checksum_matches'] ?? null,
        'metadata_review' => $file['metadata_review'] ?? [],
        'output_intents_review' => $file['output_intents_review'] ?? [],
    ]) . " -->\n";
}
