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
        . '<xmp:CreateDate>2026-06-02T18:14:49Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$rootProfile = 'Current PieceInfo root ICC bytes';
$associatedProfile = 'Current PieceInfo associated ICC bytes';
$sourcePayload = '<wp-export><post id="181449"/></wp-export>';
$privatePayload = 'BT /F1 12 Tf 72 720 Td (PieceInfo Metadata Private Leak) Tj ET';
$staleSourcePayload = '<wp-export><post id="stale-pieceinfo"/></wp-export>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));

$fileXmp = gzcompress($xmpPacket('Current PieceInfo Associated XMP Title'));
$privateXmp = gzcompress($xmpPacket('Current PieceInfo Private XMP Title'));
$rootProfileStream = gzcompress($rootProfile);
$associatedProfileStream = gzcompress($associatedProfile);
$privateStream = gzcompress($privatePayload);
$staleXmp = gzcompress($xmpPacket('Stale PieceInfo Associated XMP Title'));
$staleProfileStream = gzcompress('Stale PieceInfo associated ICC bytes');
if (
    !is_string($fileXmp)
    || !is_string($privateXmp)
    || !is_string($rootProfileStream)
    || !is_string($associatedProfileStream)
    || !is_string($privateStream)
    || !is_string($staleXmp)
    || !is_string($staleProfileStream)
) {
    throw new RuntimeException('Unable to compress PieceInfo associated metadata smoke streams.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Current PieceInfo Associated Body) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale PieceInfo Associated Body) Tj ET';
$pdf = "%PDF-2.0\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /OutputIntents [9 0 R] /AF [10 0 R] >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(5, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($fileXmp) . " >>\nstream\n{$fileXmp}\nendstream");
$addObject(6, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($privateXmp) . " >>\nstream\n{$privateXmp}\nendstream");
$addObject(7, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream");
$addObject(8, '<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length ' . strlen($associatedProfileStream) . " >>\nstream\n{$associatedProfileStream}\nendstream");
$addObject(9, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current PieceInfo Root sRGB) /Info (Current root PDF/A profile) /DestOutputProfile 7 0 R >>');
$addObject(10, '<< /Type /Filespec /F (legacy-piece-source.xml) /UF (piece-source.xml) /Desc (Current PieceInfo WordPress source) /AFRelationship /Source /Metadata 5 0 R /OutputIntents [13 0 R] /PieceInfo << /WPImport << /LastModified (D:20260602181449Z) /Private << /ManifestId (piece-181449) /Metadata 6 0 R /OutputIntents [13 0 R] /PrivateStream 16 0 R >> >> >> /EF << /F 11 0 R >> >>');
$addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($sourcePayload) . ' /CheckSum <' . $sourceChecksum . "> >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");
$addObject(13, '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current PieceInfo Associated sRGB) /Info (Current associated PDF/A profile) /DestOutputProfile 8 0 R >>');
$addObject(16, '<< /Type /Metadata /Subtype /text#2Fplain /Filter /FlateDecode /Length ' . strlen($privateStream) . " >>\nstream\n{$privateStream}\nendstream");

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 18; $objectNumber++) {
    if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 17)) {
        $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
        continue;
    }

    $rows .= pack('CNn', 1, $objectNumber === 17 ? $xrefOffset : $offsets[$objectNumber], 0);
}
$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress PieceInfo associated xref stream.');
}

$pdf .= "17 0 obj\n"
    . '<< /Type /XRef /Size 18 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /OutputIntents [9 0 R] /AF [10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleXmp) . " >>\nstream\n{$staleXmp}\nendstream\nendobj\n"
    . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($staleProfileStream) . " >>\nstream\n{$staleProfileStream}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (stale-piece-source.xml) /Desc (Stale PieceInfo source) /AFRelationship /Source /Metadata 5 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Stale PieceInfo Associated sRGB) /Info (Stale associated PDF/A profile) /DestOutputProfile 8 0 R >>\nendobj\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$associatedFile = ($metadata['associated_files'] ?? [])[0] ?? [];
$provenance = $associatedFile['provenance_review'] ?? [];
$pieceInfo = $associatedFile['piece_info']['WPImport'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($associatedFile['filename'] ?? null) !== 'piece-source.xml') {
    throw new RuntimeException('Expected current xref-selected associated FileSpec.');
}
if (($pieceInfo['private']['ManifestId'] ?? null) !== 'piece-181449') {
    throw new RuntimeException('Expected FileSpec PieceInfo review metadata.');
}
if (($provenance['pdfa_output_intents']['output_condition_identifiers'] ?? []) !== ['Current PieceInfo Associated sRGB']) {
    throw new RuntimeException('Expected FileSpec-local OutputIntent provenance.');
}
if (!is_string($encoded) || str_contains($encoded, 'Stale PieceInfo') || str_contains($encoded, $sourcePayload) || str_contains($encoded, $privatePayload)) {
    throw new RuntimeException('Expected associated payloads, private XMP, and stale metadata to remain hidden.');
}
if ($plainText !== 'Current PieceInfo Associated Body') {
    throw new RuntimeException('Expected current visible page text only.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-metadata-pieceinfo-associated-outputintent-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-catalog-associated-pieceinfo-outputintent-review-parser',
    'native_boundary' => 'current xref-selected catalog /AF FileSpec /PieceInfo plus FileSpec-local Metadata/OutputIntents stay review-only before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'associated_filename' => $associatedFile['filename'] ?? null,
    'relationship' => $associatedFile['relationship'] ?? null,
    'pieceinfo_manifest' => $pieceInfo['private']['ManifestId'] ?? null,
    'pieceinfo_outputintent_identifier' => $pieceInfo['private']['OutputIntents'][0]['OutputConditionIdentifier'] ?? null,
    'provenance_sources' => $provenance['sources'] ?? [],
    'provenance_outputintent_identifiers' => $provenance['pdfa_output_intents']['output_condition_identifiers'] ?? [],
    'payload_content_omitted' => !array_key_exists('content', $associatedFile),
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:associated-pieceinfo-outputintent-review ' . $htmlJson([
    'last_modified' => $pieceInfo['last_modified'] ?? null,
    'metadata_review_type' => $pieceInfo['private']['Metadata']['Type'] ?? null,
    'outputintent_review' => $pieceInfo['private']['OutputIntents'][0] ?? [],
    'provenance_review' => $provenance,
]) . " -->\n";
