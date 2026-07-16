<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$xmpPacket = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">NameTree Associated XMP Hidden Title</rdf:li></rdf:Alt></dc:title>'
    . '<xmp:CreateDate>2026-06-02T20:40:00Z</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$rootProfile = 'Current PDF/A name-tree root ICC bytes';
$fileProfile = 'Current PDF/A name-tree associated ICC bytes';
$sourcePayload = '<wp-export><post id="pdfa-nametree-current"/></wp-export>';
$schemaPayload = '<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema"/>';
$rootProfileStream = gzcompress($rootProfile);
$fileProfileStream = gzcompress($fileProfile);
$xmpStream = gzcompress($xmpPacket);
if (!is_string($rootProfileStream) || !is_string($fileProfileStream) || !is_string($xmpStream)) {
    throw new RuntimeException('Unable to compress PDF/A name-tree smoke streams.');
}

$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$content = 'BT /F1 12 Tf 72 720 Td (Current PDF/A NameTree Body) Tj ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OutputIntents [9 0 R] /Names << /EmbeddedFiles 20 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($xmpStream) . " >>\nstream\n{$xmpStream}\nendstream\nendobj\n"
    . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream\nendobj\n"
    . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($fileProfileStream) . " >>\nstream\n{$fileProfileStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current NameTree Root PDF/A) /Info (Current root PDF/A profile) /DestOutputProfile 7 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (migrate-source.xml) /Desc (Current PDF/A source export) /AFRelationship /Source /Metadata 5 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602204000Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current NameTree Attachment PDF/A) /Info (Attachment-local PDF/A profile) /DestOutputProfile 8 0 R >>\nendobj\n"
    . "14 0 obj\n<< /Type /Filespec /F (schema.xsd) /Desc (PDF/A extension schema) /AFRelationship /Schema /EF << /F 15 0 R >> >>\nendobj\n"
    . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fxml /Params << /Size " . strlen($schemaPayload) . " >> /Length " . strlen($schemaPayload) . " >>\nstream\n{$schemaPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Names [(migrate-source.xml) 10 0 R (schema.xsd) 14 0 R] >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$summary = $metadata['pdfa_associated_name_tree'] ?? [];

if (($summary['source'] ?? null) !== 'pdfa_associated_name_tree') {
    throw new RuntimeException('Expected PDF/A associated name-tree metadata summary.');
}
if (($summary['root_output_condition_identifiers'] ?? []) !== ['Current NameTree Root PDF/A']) {
    throw new RuntimeException('Expected root PDF/A identifier in summary.');
}
if (($summary['relationship_roles'] ?? []) !== ['original_source', 'schema_definition']) {
    throw new RuntimeException('Expected Source and Schema PDF/A associated-file roles.');
}
if (($summary['entries'][0]['attachment_pdfa_output_intents']['output_condition_identifiers'] ?? []) !== ['Current NameTree Attachment PDF/A']) {
    throw new RuntimeException('Expected attachment-local PDF/A identifier in summary.');
}
if (!is_string($encoded) || str_contains($encoded, $sourcePayload) || str_contains($encoded, $schemaPayload) || str_contains($encoded, $fileProfile)) {
    throw new RuntimeException('Expected PDF/A name-tree payloads and ICC bytes to stay review-only.');
}
if ($plainText !== 'Current PDF/A NameTree Body') {
    throw new RuntimeException('Expected visible WordPress text to come only from page content.');
}

echo '<!-- markerpdf-pdfa-associated-name-tree-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-pdfa-associated-name-tree-review',
    'native_boundary' => 'catalog /Names /EmbeddedFiles rows are summarized as PDF/A associated-file review metadata when a root PDF/A OutputIntent is present',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'root_pdfa_identifiers' => $metadata['pdfa']['output_condition_identifiers'] ?? [],
    'summary_names' => $summary['names'] ?? [],
    'relationship_roles' => $summary['relationship_roles'] ?? [],
    'attachment_pdfa_identifiers' => array_map(
        static fn (array $entry): array => $entry['attachment_pdfa_output_intents']['output_condition_identifiers'] ?? [],
        $summary['entries'] ?? []
    ),
    'payload_content_omitted' => is_string($encoded) && !str_contains($encoded, $sourcePayload) && !str_contains($encoded, $schemaPayload),
    'associated_pdfa_not_promoted_to_root' => ($metadata['pdfa']['output_condition_identifiers'] ?? []) === ['Current NameTree Root PDF/A'],
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($summary['entries'] ?? [] as $entry) {
    echo '<!-- markerpdf:pdfa-associated-name-tree-file ' . $htmlJson([
        'name_tree_name' => $entry['name_tree_name'] ?? null,
        'filename' => $entry['filename'] ?? null,
        'relationship' => $entry['relationship'] ?? null,
        'relationship_role' => $entry['relationship_role'] ?? null,
        'payload' => $entry['payload'] ?? [],
        'attachment_pdfa_output_intents' => $entry['attachment_pdfa_output_intents'] ?? [],
    ]) . " -->\n";
}
