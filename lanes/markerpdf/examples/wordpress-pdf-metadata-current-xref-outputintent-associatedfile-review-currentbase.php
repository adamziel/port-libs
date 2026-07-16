<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title, string $description): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreatorTool>WordPress PDF importer</xmp:CreatorTool>'
        . '<xmp:CreateDate>2026-06-02T17:44:11Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$currentRootProfile = 'Current xref root ICC profile bytes';
$currentAssociatedProfile = 'Current xref associated ICC bytes';
$currentPayload = '<wp-export><post id="174411"/></wp-export>';
$staleRootProfile = 'Stale xref root ICC bytes';
$staleAssociatedProfile = 'Stale xref associated ICC bytes';
$stalePayload = '<wp-export><post id="stale"/></wp-export>';

$currentRootXmp = gzcompress($xmpPacket('Current XRef WordPress Packet', 'Current xref-selected catalog metadata wins'));
$currentAssociatedXmp = gzcompress($xmpPacket('Current Associated Attachment XMP', 'Attachment-local XMP remains review-only'));
$currentRootProfileStream = gzcompress($currentRootProfile);
$currentAssociatedProfileStream = gzcompress($currentAssociatedProfile);
$staleRootXmp = gzcompress($xmpPacket('Stale XRef WordPress Packet', 'Stale catalog metadata must not win'));
$staleAssociatedXmp = gzcompress($xmpPacket('Stale Associated Attachment XMP', 'Stale attachment-local XMP must not win'));
$staleRootProfileStream = gzcompress($staleRootProfile);
$staleAssociatedProfileStream = gzcompress($staleAssociatedProfile);
if (
    !is_string($currentRootXmp)
    || !is_string($currentAssociatedXmp)
    || !is_string($currentRootProfileStream)
    || !is_string($currentAssociatedProfileStream)
    || !is_string($staleRootXmp)
    || !is_string($staleAssociatedXmp)
    || !is_string($staleRootProfileStream)
    || !is_string($staleAssociatedProfileStream)
) {
    throw new RuntimeException('Unable to compress current-xref metadata smoke streams.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Current XRef Metadata Import Body) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale XRef Metadata Body) Tj ET';
$currentChecksum = strtoupper(hash('md5', $currentPayload));

$pdf = "%PDF-2.0\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /Metadata 5 0 R /OutputIntents [9 0 R] /AF [10 0 R] >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(5, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentRootXmp) . " >>\nstream\n{$currentRootXmp}\nendstream");
$addObject(6, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentAssociatedXmp) . " >>\nstream\n{$currentAssociatedXmp}\nendstream");
$addObject(7, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($currentRootProfileStream) . " >>\nstream\n{$currentRootProfileStream}\nendstream");
$addObject(8, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($currentAssociatedProfileStream) . " >>\nstream\n{$currentAssociatedProfileStream}\nendstream");
$addObject(9, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current XRef sRGB) /Info (Current xref PDF/A profile) /DestOutputProfile 7 0 R >>');
$addObject(10, '<< /Type /Filespec /F (legacy-current-xref.xml) /UF (current-xref.xml) /Desc (Current xref WordPress source packet) /AFRelationship /Source /Lang (en-US) /Metadata 6 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>');
$addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($currentPayload) . ' /CheckSum <' . $currentChecksum . "> >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
$addObject(13, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current Attachment sRGB) /Info (Current attachment PDF/A profile) /DestOutputProfile 8 0 R >>');
$addObject(14, '<< /Title (Current XRef Info Title) /Author (Current XRef Info Author) >>');

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 16; $objectNumber++) {
    if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 15)) {
        $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
        continue;
    }

    $rows .= pack('CNn', 1, $objectNumber === 15 ? $xrefOffset : $offsets[$objectNumber], 0);
}

$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress current-xref metadata smoke xref stream.');
}

$pdf .= "15 0 obj\n"
    . '<< /Type /XRef /Size 16 /Root 1 0 R /Info 14 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /Metadata 5 0 R /OutputIntents [9 0 R] /AF [10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleRootXmp) . " >>\nstream\n{$staleRootXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleAssociatedXmp) . " >>\nstream\n{$staleAssociatedXmp}\nendstream\nendobj\n"
    . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($staleRootProfileStream) . " >>\nstream\n{$staleRootProfileStream}\nendstream\nendobj\n"
    . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($staleAssociatedProfileStream) . " >>\nstream\n{$staleAssociatedProfileStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Stale XRef sRGB) /Info (Stale xref PDF/A profile) /DestOutputProfile 7 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (stale-xref.xml) /Desc (Stale xref source packet) /AFRelationship /Source /Metadata 6 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Stale Attachment sRGB) /Info (Stale attachment PDF/A profile) /DestOutputProfile 8 0 R >>\nendobj\n"
    . "14 0 obj\n<< /Title (Stale XRef Info Title) /Author (Stale XRef Info Author) >>\nendobj\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$associatedFiles = $metadata['associated_files'] ?? [];

if (($metadata['title'] ?? null) !== 'Current XRef WordPress Packet') {
    throw new RuntimeException('Expected current xref-selected XMP metadata.');
}
if (($metadata['pdfa']['output_condition_identifiers'] ?? []) !== ['Current XRef sRGB']) {
    throw new RuntimeException('Expected current xref-selected root OutputIntent metadata.');
}
if (($associatedFiles[0]['filename'] ?? null) !== 'current-xref.xml' || ($associatedFiles[0]['checksum_matches'] ?? null) !== true) {
    throw new RuntimeException('Expected current xref-selected associated FileSpec payload review metadata.');
}
if (!is_string($encoded) || str_contains($encoded, 'Stale XRef') || str_contains($encoded, $stalePayload) || str_contains($encoded, $staleRootProfile) || str_contains($encoded, $staleAssociatedProfile)) {
    throw new RuntimeException('Expected stale duplicate metadata objects to stay excluded.');
}
if (str_contains($plainText, 'Stale XRef Metadata Body') || str_contains($plainText, '<wp-export>')) {
    throw new RuntimeException('Expected stale and attachment payload text to stay out of visible WordPress content.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-metadata-current-xref-outputintent-associatedfile-review-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-current-xref-metadata-object-selection',
    'native_boundary' => 'latest startxref xref-stream rows select catalog XMP OutputIntent and associated FileSpec objects before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title' => $metadata['title'] ?? null,
    'language' => $metadata['language'] ?? null,
    'pdfa_identifiers' => $metadata['pdfa']['output_condition_identifiers'] ?? [],
    'associated_file_count' => count($associatedFiles),
    'associated_filename' => $associatedFiles[0]['filename'] ?? null,
    'associated_relationship' => $associatedFiles[0]['relationship'] ?? null,
    'associated_outputintent_identifier' => $associatedFiles[0]['output_intents_review'][0]['OutputConditionIdentifier'] ?? null,
    'stale_duplicates_excluded' => is_string($encoded) && !str_contains($encoded, 'Stale XRef'),
    'associated_payload_content_omitted' => !array_key_exists('content', $associatedFiles[0] ?? []),
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
