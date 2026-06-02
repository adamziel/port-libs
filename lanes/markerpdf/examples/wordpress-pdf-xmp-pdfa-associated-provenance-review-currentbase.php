<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$xmpPacket = static function (): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Associated Provenance XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '<xmp:CreateDate>2026-06-02T16:42:00Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$rootProfile = 'Root provenance PDF/A ICC bytes';
$associatedProfile = 'Associated provenance ICC bytes should stay review-only';
$sourcePayload = '<wp-export><post id="1642"/></wp-export>';
$schemaPayload = '<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema"/>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$sourceXmpPacket = $xmpPacket();
$rootProfileStream = gzcompress($rootProfile);
$associatedProfileStream = gzcompress($associatedProfile);
$sourceXmpStream = gzcompress($sourceXmpPacket);
if (!is_string($rootProfileStream) || !is_string($associatedProfileStream) || !is_string($sourceXmpStream)) {
    throw new RuntimeException('Unable to compress associated provenance fixture streams.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Associated Provenance Body) Tj ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OutputIntents [9 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($sourceXmpStream) . " >>\nstream\n{$sourceXmpStream}\nendstream\nendobj\n"
    . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream\nendobj\n"
    . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($associatedProfileStream) . " >>\nstream\n{$associatedProfileStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Root Provenance sRGB) /Info (Root provenance PDF/A profile) /DestOutputProfile 7 0 R /AF [10 0 R << /Type /Filespec /F (schema.xsd) /Desc (XMP extension schema) /AFRelationship /Schema /Metadata 5 0 R /OutputIntents [13 0 R] /EF << /F 12 0 R >> >>] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress source export) /AFRelationship /Source /Metadata 5 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:202606021642Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fxml /Params << /Size " . strlen($schemaPayload) . " >> /Length " . strlen($schemaPayload) . " >>\nstream\n{$schemaPayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Associated Provenance sRGB) /Info (Attachment-local PDF/A provenance) /DestOutputProfile 8 0 R >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$associatedFiles = $metadata['output_intents'][0]['associated_files'] ?? [];

if (count($associatedFiles) !== 2) {
    throw new RuntimeException('Expected Source and Schema associated files.');
}
if (($metadata['pdfa']['output_condition_identifiers'] ?? []) !== ['Root Provenance sRGB']) {
    throw new RuntimeException('Expected only root PDF/A OutputIntent metadata to be promoted.');
}
if (($associatedFiles[0]['provenance_review']['relationship_role'] ?? null) !== 'original_source') {
    throw new RuntimeException('Expected Source associated-file provenance role.');
}
if (($associatedFiles[1]['provenance_review']['relationship_role'] ?? null) !== 'schema_definition') {
    throw new RuntimeException('Expected Schema associated-file provenance role.');
}
if (($associatedFiles[0]['provenance_review']['xmp_metadata']['sha256'] ?? null) !== hash('sha256', $sourceXmpPacket)) {
    throw new RuntimeException('Expected decoded XMP packet hash in provenance review.');
}
if (($associatedFiles[0]['provenance_review']['pdfa_output_intents']['output_condition_identifiers'] ?? []) !== ['Associated Provenance sRGB']) {
    throw new RuntimeException('Expected attachment-local PDF/A OutputIntent provenance.');
}
if (!is_string($encoded) || str_contains($encoded, 'Associated Provenance XMP Title') || str_contains($encoded, $sourcePayload) || str_contains($encoded, $associatedProfile)) {
    throw new RuntimeException('Expected associated XMP, payload, and ICC bytes to remain undisclosed.');
}
if (str_contains($plainText, '<wp-export>') || str_contains($plainText, 'Associated provenance ICC bytes')) {
    throw new RuntimeException('Expected associated payload bytes to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-xmp-pdfa-associated-provenance-smoke ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-pdfa-associated-file-provenance-review',
    'native_boundary' => 'OutputIntent /AF FileSpec XMP and PDF/A provenance stays review-only before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'root_pdfa_identifiers' => $metadata['pdfa']['output_condition_identifiers'] ?? [],
    'associated_file_count' => count($associatedFiles),
    'relationship_roles' => array_map(
        static fn (array $file): ?string => $file['provenance_review']['relationship_role'] ?? null,
        $associatedFiles
    ),
    'associated_xmp_hashes' => array_map(
        static fn (array $file): ?string => $file['provenance_review']['xmp_metadata']['sha256'] ?? null,
        $associatedFiles
    ),
    'associated_pdfa_identifiers' => array_map(
        static fn (array $file): array => $file['provenance_review']['pdfa_output_intents']['output_condition_identifiers'] ?? [],
        $associatedFiles
    ),
    'payload_content_omitted' => !array_key_exists('content', $associatedFiles[0]) && !array_key_exists('content', $associatedFiles[1]),
    'xmp_payload_omitted' => is_string($encoded) && !str_contains($encoded, 'Associated Provenance XMP Title'),
    'associated_pdfa_not_promoted_to_root' => ($metadata['pdfa']['output_condition_identifiers'] ?? []) === ['Root Provenance sRGB'],
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($associatedFiles as $file) {
    echo '<!-- markerpdf:associated-provenance-file ' . $htmlJson([
        'filename' => $file['filename'] ?? null,
        'relationship' => $file['relationship'] ?? null,
        'description' => $file['description'] ?? null,
        'provenance_review' => $file['provenance_review'] ?? [],
    ]) . " -->\n";
}
