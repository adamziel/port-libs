<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<xmp:CreateDate>2026-06-02T14:55:00Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$rootProfile = 'Root PDF/A ICC profile bytes';
$associatedProfile = 'Associated FileSpec ICC bytes should not become PDF/A root metadata';
$sourcePayload = '<wp-export><post id="1455"/></wp-export>';
$previewPayload = 'Rendered preview attachment bytes';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$compressedRootProfile = gzcompress($rootProfile);
$compressedAssociatedProfile = gzcompress($associatedProfile);
$sourceXmp = gzcompress($xmpPacket('Associated Source XMP Title'));
$previewXmp = gzcompress($xmpPacket('Associated Preview XMP Title'));
if (
    !is_string($compressedRootProfile)
    || !is_string($compressedAssociatedProfile)
    || !is_string($sourceXmp)
    || !is_string($previewXmp)
) {
    throw new RuntimeException('Unable to compress OutputIntent associated metadata fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (OutputIntent Associated Body) Tj ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OutputIntents [9 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($sourceXmp) . " >>\nstream\n{$sourceXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($previewXmp) . " >>\nstream\n{$previewXmp}\nendstream\nendobj\n"
    . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedRootProfile) . " >>\nstream\n{$compressedRootProfile}\nendstream\nendobj\n"
    . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedAssociatedProfile) . " >>\nstream\n{$compressedAssociatedProfile}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Root sRGB) /Info (Root PDF/A profile) /DestOutputProfile 7 0 R /AF [10 0 R << /Type /Filespec /F (preview.pdf) /Desc (Rendered PDF preview) /AFRelationship /Alternative /Metadata 6 0 R /OutputIntents [13 0 R] /EF << /F 15 0 R >> >>] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /Metadata 5 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Associated sRGB) /Info (Nested associated PDF/A) /DestOutputProfile 8 0 R >>\nendobj\n"
    . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$intent = $metadata['output_intents'][0] ?? [];
$associatedFiles = is_array($intent) ? ($intent['associated_files'] ?? []) : [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['pdfa']['output_condition_identifiers'] ?? []) !== ['Root sRGB']) {
    throw new RuntimeException('Expected only the root OutputIntent to contribute PDF/A metadata.');
}
if (count($associatedFiles) !== 2) {
    throw new RuntimeException('Expected source and alternative OutputIntent-associated files.');
}
if (!is_string($encoded) || str_contains($encoded, $sourcePayload) || str_contains($encoded, $previewPayload)) {
    throw new RuntimeException('Associated file payload bytes leaked into metadata review output.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-metadata-associated-outputintent-boundary-smoke ' . $htmlJson([
    'support_component' => 'native-pdf-outputintent-associated-files-review',
    'native_boundary' => 'catalog /OutputIntents /AF FileSpec metadata stays review-only before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'pdfa_identifiers' => $metadata['pdfa']['output_condition_identifiers'] ?? [],
    'associated_file_count' => count($associatedFiles),
    'associated_relationships' => array_map(
        static fn (array $file): ?string => $file['relationship'] ?? null,
        $associatedFiles
    ),
    'associated_metadata_types' => array_map(
        static fn (array $file): ?string => $file['metadata_review']['Type'] ?? null,
        $associatedFiles
    ),
    'associated_outputintent_identifiers' => array_map(
        static fn (array $file): ?string => $file['output_intents_review'][0]['OutputConditionIdentifier'] ?? null,
        $associatedFiles
    ),
    'associated_payload_content_omitted' => !array_key_exists('content', $associatedFiles[0]) && !array_key_exists('content', $associatedFiles[1]),
    'associated_xmp_not_promoted' => !str_contains($encoded, 'Associated Source XMP Title') && !str_contains($encoded, 'Associated Preview XMP Title'),
    'associated_outputintent_not_pdfa_root' => ($metadata['pdfa']['output_condition_identifiers'] ?? []) === ['Root sRGB'],
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:outputintent-associated-files ' . $htmlJson(array_map(
    static fn (array $file): array => [
        'filename' => $file['filename'] ?? null,
        'description' => $file['description'] ?? null,
        'relationship' => $file['relationship'] ?? null,
        'mime_type' => $file['mime_type'] ?? null,
        'size' => $file['size'] ?? null,
        'checksum_matches' => $file['checksum_matches'] ?? null,
        'metadata_review' => $file['metadata_review'] ?? [],
        'output_intents_review' => $file['output_intents_review'] ?? [],
    ],
    $associatedFiles
)) . " -->\n";
