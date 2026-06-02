<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (array $overrides = []): string {
    $title = $overrides['title'] ?? 'Current Associated XMP Title';
    $description = $overrides['description'] ?? 'Current associated XMP review metadata';
    $createDate = $overrides['create_date'] ?? '2026-06-02T19:07:22-04:00';
    $modifyDate = $overrides['modify_date'] ?? '2026-06-02T20:08:23-04:00';
    $metadataDate = $overrides['metadata_date'] ?? '2026-06-03T01:09:24Z';

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Import Editor</rdf:li><rdf:li>Metadata Reviewer</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>source</rdf:li><rdf:li>associated-xmp</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Current Associated Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Current Associated Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($createDate, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:ModifyDate>' . htmlspecialchars($modifyDate, ENT_XML1) . '</xmp:ModifyDate>'
        . '<xmp:MetadataDate>' . htmlspecialchars($metadataDate, ENT_XML1) . '</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$associatedXmp = $xmpPacket();
$pieceInfoPrivateXmp = $xmpPacket([
    'title' => 'Current PieceInfo Private XMP Title',
    'description' => 'PieceInfo private XMP remains review-only',
    'create_date' => '2026-06-02T21:10:25+02:00',
    'modify_date' => '2026-06-02T22:11:26+02:00',
    'metadata_date' => '2026-06-02T23:12:27+02:00',
]);
$staleAssociatedXmp = $xmpPacket([
    'title' => 'Stale Associated XMP Title',
    'description' => 'Stale associated XMP must not win',
]);
$stalePieceInfoXmp = $xmpPacket([
    'title' => 'Stale PieceInfo Private XMP Title',
    'description' => 'Stale PieceInfo private XMP must not win',
]);

$associatedXmpStream = gzcompress($associatedXmp);
$pieceInfoPrivateXmpStream = gzcompress($pieceInfoPrivateXmp);
$staleAssociatedXmpStream = gzcompress($staleAssociatedXmp);
$stalePieceInfoXmpStream = gzcompress($stalePieceInfoXmp);
if (
    !is_string($associatedXmpStream)
    || !is_string($pieceInfoPrivateXmpStream)
    || !is_string($staleAssociatedXmpStream)
    || !is_string($stalePieceInfoXmpStream)
) {
    throw new RuntimeException('Unable to compress associated XMP smoke streams.');
}

$sourcePayload = '<wp-export><post id="xmp-current"/></wp-export>';
$staleSourcePayload = '<wp-export><post id="stale-xmp"/></wp-export>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$content = 'BT /F1 12 Tf 72 720 Td (Current Associated XMP Body) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Associated XMP Body) Tj ET';
$pdf = "%PDF-2.0\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /AF [10 0 R] >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(5, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($associatedXmpStream) . " >>\nstream\n{$associatedXmpStream}\nendstream");
$addObject(6, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($pieceInfoPrivateXmpStream) . " >>\nstream\n{$pieceInfoPrivateXmpStream}\nendstream");
$addObject(10, '<< /Type /Filespec /F (legacy-xmp-source.xml) /UF (xmp-source.xml) /Desc (Current FileSpec XMP source) /AFRelationship /Source /Metadata 5 0 R /PieceInfo << /WPImport << /LastModified (D:20260602190722Z) /Private << /ManifestId (piece-xmp-current) /Metadata 6 0 R >> >> >> /EF << /F 11 0 R >> >>');
$addObject(11, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size ' . strlen($sourcePayload) . ' /CheckSum <' . $sourceChecksum . "> >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream");

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
    throw new RuntimeException('Unable to compress associated XMP xref stream.');
}

$pdf .= "17 0 obj\n"
    . '<< /Type /XRef /Size 18 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (de-DE) /AF [10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleAssociatedXmpStream) . " >>\nstream\n{$staleAssociatedXmpStream}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($stalePieceInfoXmpStream) . " >>\nstream\n{$stalePieceInfoXmpStream}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (stale-xmp-source.xml) /Desc (Stale FileSpec XMP source) /AFRelationship /Source /Metadata 5 0 R /PieceInfo << /WPImport << /LastModified (D:20260602200000Z) /Private << /ManifestId (stale-piece-xmp) /Metadata 6 0 R >> >> >> /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length " . strlen($staleSourcePayload) . " >>\nstream\n{$staleSourcePayload}\nendstream\nendobj\n";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$associatedFile = ($metadata['associated_files'] ?? [])[0] ?? [];
$provenance = $associatedFile['provenance_review'] ?? [];
$xmpSummary = $provenance['xmp_metadata']['xmp_summary'] ?? [];
$pieceInfoXmp = $provenance['piece_info_xmp_metadata'] ?? [];
$pieceInfoXmpSummary = $pieceInfoXmp['metadata_streams'][0]['xmp_summary'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($associatedFile['filename'] ?? null) !== 'xmp-source.xml') {
    throw new RuntimeException('Expected current xref-selected associated XMP FileSpec.');
}
if (($associatedFile['piece_info']['WPImport']['private']['ManifestId'] ?? null) !== 'piece-xmp-current') {
    throw new RuntimeException('Expected current FileSpec PieceInfo manifest metadata.');
}
if (($provenance['sources'] ?? []) !== ['filespec_afrelationship', 'embedded_file_payload_hash', 'embedded_file_params_checksum', 'filespec_metadata_stream', 'filespec_pieceinfo_metadata_stream']) {
    throw new RuntimeException('Expected associated XMP and PieceInfo XMP provenance sources.');
}
if (($xmpSummary['dates_utc']['created_at'] ?? null) !== '2026-06-02T23:07:22Z') {
    throw new RuntimeException('Expected associated XMP date normalization.');
}
if (($pieceInfoXmpSummary['dates_utc']['created_at'] ?? null) !== '2026-06-02T19:10:25Z') {
    throw new RuntimeException('Expected PieceInfo private XMP date normalization.');
}
if (!is_string($encoded) || str_contains($encoded, 'Current Associated XMP Title') || str_contains($encoded, 'Current PieceInfo Private XMP Title') || str_contains($encoded, 'Stale Associated XMP Title') || str_contains($encoded, 'Stale PieceInfo Private XMP Title')) {
    throw new RuntimeException('Expected XMP text values to stay redacted from metadata JSON.');
}
if (is_string($encoded) && (str_contains($encoded, $sourcePayload) || str_contains($encoded, $staleSourcePayload))) {
    throw new RuntimeException('Expected embedded payload bytes to stay out of metadata JSON.');
}
if ($plainText !== 'Current Associated XMP Body') {
    throw new RuntimeException('Expected current visible page text only.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-metadata-pieceinfo-associated-xmp-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-associated-filespec-xmp-pieceinfo-review-parser',
    'native_boundary' => 'current xref-selected catalog /AF FileSpec /Metadata and /PieceInfo /Private /Metadata XMP streams are summarized without importing XMP text values or payload bytes',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'associated_filename' => $associatedFile['filename'] ?? null,
    'relationship' => $associatedFile['relationship'] ?? null,
    'provenance_sources' => $provenance['sources'] ?? [],
    'xmp_field_names' => $xmpSummary['field_names'] ?? [],
    'xmp_dates_utc' => $xmpSummary['dates_utc'] ?? [],
    'pieceinfo_applications' => $pieceInfoXmp['applications'] ?? [],
    'pieceinfo_xmp_field_names' => $pieceInfoXmpSummary['field_names'] ?? [],
    'pieceinfo_xmp_dates_utc' => $pieceInfoXmpSummary['dates_utc'] ?? [],
    'payload_content_omitted' => !array_key_exists('content', $associatedFile),
    'xmp_text_values_redacted' => ($xmpSummary['text_values_redacted'] ?? false) === true
        && ($pieceInfoXmpSummary['text_values_redacted'] ?? false) === true,
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:associated-pieceinfo-xmp-review ' . $htmlJson([
    'xmp_summary' => $xmpSummary,
    'pieceinfo_xmp_metadata' => $pieceInfoXmp,
]) . " -->\n";
