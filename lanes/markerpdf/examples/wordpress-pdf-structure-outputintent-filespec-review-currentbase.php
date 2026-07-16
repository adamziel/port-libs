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
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Structure Associated XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '<xmp:CreateDate>2026-06-02T17:21:00Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$rootProfile = 'Root tagged PDF/A ICC bytes';
$associatedProfile = 'Structure-associated ICC bytes should stay review-only';
$sourcePayload = '<wp-export><post id="1721"/></wp-export>';
$mathPayload = '<math><mi>x</mi><mo>=</mo><mn>1</mn></math>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$sourceXmpPacket = $xmpPacket();
$rootProfileStream = gzcompress($rootProfile);
$associatedProfileStream = gzcompress($associatedProfile);
$sourceXmpStream = gzcompress($sourceXmpPacket);
if (!is_string($rootProfileStream) || !is_string($associatedProfileStream) || !is_string($sourceXmpStream)) {
    throw new RuntimeException('Unable to compress structure-associated FileSpec smoke streams.');
}

$content = 'BT /F1 12 Tf /Formula << /MCID 0 >> BDC 72 720 Td (Visible formula caption) Tj EMC ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R /OutputIntents [9 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($sourceXmpStream) . " >>\nstream\n{$sourceXmpStream}\nendstream\nendobj\n"
    . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream\nendobj\n"
    . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($associatedProfileStream) . " >>\nstream\n{$associatedProfileStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Root Structure sRGB) /Info (Root tagged PDF/A profile) /DestOutputProfile 7 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress source for formula) /AFRelationship /Source /Metadata 5 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602172100Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fmathml#2Bxml /Params << /Size " . strlen($mathPayload) . " >> /Length " . strlen($mathPayload) . " >>\nstream\n{$mathPayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Structure Associated sRGB) /Info (Attachment-local structure PDF/A) /DestOutputProfile 8 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Formula /Formula >> /K [21 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Type /StructElem /S /Formula /Pg 3 0 R /Alt (Equation alternate text) /AF [10 0 R << /Type /Filespec /F (equation.mathml) /Desc (Accessible equation source) /AFRelationship /Supplement /EF << /F 12 0 R >> >>] /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$structure = $metadata['structure_tree'] ?? [];
$formula = $structure['elements'][0] ?? [];
$associatedFiles = is_array($formula) ? ($formula['associated_files'] ?? []) : [];

if (($metadata['pdfa']['output_condition_identifiers'] ?? []) !== ['Root Structure sRGB']) {
    throw new RuntimeException('Expected only the root PDF/A OutputIntent to be promoted.');
}
if (count($associatedFiles) !== 2) {
    throw new RuntimeException('Expected Source and Supplement files on the structure element.');
}
if (($associatedFiles[0]['provenance_review']['relationship_role'] ?? null) !== 'original_source') {
    throw new RuntimeException('Expected Source relationship provenance.');
}
if (($associatedFiles[1]['provenance_review']['relationship_role'] ?? null) !== 'supplemental_representation') {
    throw new RuntimeException('Expected Supplement relationship provenance.');
}
if (($associatedFiles[0]['provenance_review']['pdfa_output_intents']['output_condition_identifiers'] ?? []) !== ['Structure Associated sRGB']) {
    throw new RuntimeException('Expected attachment-local OutputIntent provenance.');
}
if (!is_string($encoded) || str_contains($encoded, 'Structure Associated XMP Title') || str_contains($encoded, $sourcePayload) || str_contains($encoded, $mathPayload) || str_contains($encoded, $associatedProfile)) {
    throw new RuntimeException('Expected associated XMP, payloads, and ICC bytes to remain undisclosed.');
}
if (str_contains($plainText, '<wp-export>') || str_contains($plainText, '<math>') || str_contains($plainText, 'Structure-associated ICC bytes')) {
    throw new RuntimeException('Expected associated payload bytes to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-structure-outputintent-filespec-review-currentbase-smoke ' . $htmlJson([
    'support_component' => 'native-pdf-structure-associated-files-review',
    'native_boundary' => 'StructElem /AF FileSpec XMP and OutputIntent provenance stays review-only before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'root_pdfa_identifiers' => $metadata['pdfa']['output_condition_identifiers'] ?? [],
    'structure_role' => $formula['role'] ?? null,
    'associated_file_count' => count($associatedFiles),
    'relationship_roles' => array_map(
        static fn (array $file): ?string => $file['provenance_review']['relationship_role'] ?? null,
        $associatedFiles
    ),
    'associated_pdfa_identifiers' => array_map(
        static fn (array $file): array => $file['provenance_review']['pdfa_output_intents']['output_condition_identifiers'] ?? [],
        $associatedFiles
    ),
    'payload_content_omitted' => !array_key_exists('content', $associatedFiles[0]) && !array_key_exists('content', $associatedFiles[1]),
    'nested_xmp_payload_omitted' => is_string($encoded) && !str_contains($encoded, 'Structure Associated XMP Title'),
    'associated_pdfa_not_promoted_to_root' => ($metadata['pdfa']['output_condition_identifiers'] ?? []) === ['Root Structure sRGB'],
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($associatedFiles as $file) {
    echo '<!-- markerpdf:structure-associated-file ' . $htmlJson([
        'filename' => $file['filename'] ?? null,
        'relationship' => $file['relationship'] ?? null,
        'description' => $file['description'] ?? null,
        'provenance_review' => $file['provenance_review'] ?? [],
    ]) . " -->\n";
}
