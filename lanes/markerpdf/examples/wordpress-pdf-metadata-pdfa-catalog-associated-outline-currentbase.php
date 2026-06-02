<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$rootXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Catalog AF PDF/A Root Title</rdf:li></rdf:Alt></dc:title>'
    . '<xmp:CreateDate>2026-06-02T21:52:00Z</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$fileXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden Catalog AF XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<xmp:MetadataDate>2026-06-02T21:54:00Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$rootXmpStream = gzcompress($rootXmp);
$fileXmpStream = gzcompress($fileXmp);
$rootProfile = 'Catalog AF root PDF/A profile bytes';
$fileProfile = 'Catalog AF attachment-local PDF/A profile bytes';
$rootProfileStream = gzcompress($rootProfile);
$fileProfileStream = gzcompress($fileProfile);
if (!is_string($rootXmpStream) || !is_string($fileXmpStream) || !is_string($rootProfileStream) || !is_string($fileProfileStream)) {
    throw new RuntimeException('Unable to compress catalog AF PDF/A smoke streams.');
}

$sourcePayload = '<wp-export><post id="catalog-af-pdfa"/></wp-export>';
$schemaPayload = '<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema" id="catalog-af-schema"/>';
$targetPayload = '<wp-page-target outline="catalog-af"/>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$schemaChecksum = strtoupper(hash('md5', $schemaPayload));
$targetChecksum = strtoupper(hash('md5', $targetPayload));
$introContent = 'BT /F1 12 Tf 72 720 Td (Catalog AF intro body) Tj ET';
$targetContent = 'BT /F1 12 Tf 72 720 Td (Catalog AF outline target body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /OutputIntents [9 0 R] /AF [10 0 R 20 0 R] /Outlines 40 0 R /Names << /Dests 50 0 R >> /OpenAction /CatalogAFTarget >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 32 0 R /AF [30 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootXmpStream) . " >>\nstream\n{$rootXmpStream}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($fileXmpStream) . " >>\nstream\n{$fileXmpStream}\nendstream\nendobj\n"
    . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream\nendobj\n"
    . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($fileProfileStream) . " >>\nstream\n{$fileProfileStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Catalog AF Root PDF/A) /Info (Root PDF/A profile) /DestOutputProfile 7 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (catalog-source.xml) /Desc (Catalog associated WordPress source) /AFRelationship /Source /Metadata 6 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Catalog AF Attachment PDF/A) /Info (Attachment PDF/A profile) /DestOutputProfile 8 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (catalog-schema.xsd) /Desc (Catalog AF extension schema) /AFRelationship /Schema /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fxml /Params << /Size " . strlen($schemaPayload) . " /CheckSum <{$schemaChecksum}> >> /Length " . strlen($schemaPayload) . " >>\nstream\n{$schemaPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (outline-target-source.xml) /Desc (Outline target page source) /AFRelationship /Supplement /EF << /F 33 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
    . "33 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($targetPayload) . " /CheckSum <{$targetChecksum}> >> /Length " . strlen($targetPayload) . " >>\nstream\n{$targetPayload}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Outlines /First 41 0 R /Count 1 >>\nendobj\n"
    . "41 0 obj\n<< /Title (Catalog AF Outline Target) /Parent 40 0 R /Dest /CatalogAFTarget >>\nendobj\n"
    . "50 0 obj\n<< /Names [(CatalogAFTarget) [4 0 R /FitH 700]] >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$summary = $metadata['pdfa_associated_files'] ?? [];
$outline = $navigation['outline'][0] ?? [];
$targetAttachment = $outline['target_page_review']['page_associated_files'][0] ?? [];

if (($summary['source'] ?? null) !== 'pdfa_associated_files') {
    throw new RuntimeException('Expected catalog /AF PDF/A associated-file summary.');
}
if (($summary['relationship_roles'] ?? []) !== ['original_source', 'schema_definition']) {
    throw new RuntimeException('Expected Source and Schema catalog AF roles.');
}
if (($summary['entries'][0]['attachment_pdfa_output_intents']['output_condition_identifiers'] ?? []) !== ['Catalog AF Attachment PDF/A']) {
    throw new RuntimeException('Expected attachment-local PDF/A output intent summary.');
}
if (($targetAttachment['filename'] ?? null) !== 'outline-target-source.xml' || ($targetAttachment['checksum_matches'] ?? null) !== true) {
    throw new RuntimeException('Expected outline target page associated-file review metadata.');
}
if (!is_string($encoded) || str_contains($encoded, $sourcePayload) || str_contains($encoded, $schemaPayload) || str_contains($encoded, $targetPayload) || str_contains($encoded, $fileProfile)) {
    throw new RuntimeException('Expected attachment payloads and profile bytes to stay review-only.');
}
if (str_contains($plainText, '<wp-export>') || str_contains($plainText, 'Hidden Catalog AF XMP Title') || str_contains($plainText, 'Catalog AF Attachment PDF/A')) {
    throw new RuntimeException('Expected catalog AF metadata operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-pdfa-catalog-associated-outline-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-pdfa-catalog-af-outline-review',
    'native_boundary' => 'root PDF/A OutputIntent plus catalog /AF source/schema rows and outline target page attachments are review-only metadata',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'document_title' => $metadata['title'] ?? null,
    'root_pdfa_identifiers' => $metadata['pdfa']['output_condition_identifiers'] ?? [],
    'catalog_af_filenames' => $summary['filenames'] ?? [],
    'catalog_af_relationship_roles' => $summary['relationship_roles'] ?? [],
    'attachment_pdfa_identifiers' => $summary['attachment_output_condition_identifiers'] ?? [],
    'outline_title' => $outline['title'] ?? null,
    'outline_target_attachment' => $targetAttachment['filename'] ?? null,
    'payload_content_omitted' => is_string($encoded)
        && !str_contains($encoded, $sourcePayload)
        && !str_contains($encoded, $schemaPayload)
        && !str_contains($encoded, $targetPayload),
    'visible_text' => $plainText,
]) . " -->\n";

foreach (array_filter(array_map('trim', explode("\n", $plainText))) as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
