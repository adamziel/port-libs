<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title rdf:_2="Ignored WordPress Direct Title" rdf:_1="' . htmlspecialchars($title, ENT_QUOTES | ENT_XML1) . '">'
        . '<xmp:PrivateTitle>direct property title decoy</xmp:PrivateTitle>'
        . '</dc:title>'
        . '<dc:creator rdf:_2="WordPress Metadata Reviewer" rdf:_1="WordPress Metadata Editor">'
        . '<xmp:PrivateRole>direct property author decoy</xmp:PrivateRole>'
        . '</dc:creator>'
        . '<dc:description rdf:_2="Ignored WordPress Direct Description" rdf:_1="' . htmlspecialchars($description, ENT_QUOTES | ENT_XML1) . '">'
        . '<pdf:Producer>direct property description decoy</pdf:Producer>'
        . '</dc:description>'
        . '<dc:subject rdf:_2="xmp-property-attribute-membership" rdf:_1="wordpress">'
        . '<xmp:PrivateTag>direct property keyword decoy</xmp:PrivateTag>'
        . '</dc:subject>'
        . '<pdf:Producer>WordPress Property Attribute Producer</pdf:Producer>'
        . '<xmp:CreatorTool>WordPress Property Attribute Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T22:37:22Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$pdfWithMetadata = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress WordPress XMP property attribute-membership fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Info Property Attribute Title) /Author (Info Property Attribute Author) /Producer (Info Property Attribute Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

$currentXmp = $xmpPacket(
    'WordPress Property Attribute XMP Title',
    'Direct RDF membership attributes become WordPress metadata.',
    '2026-06-08T18:37:22-04:00'
);
$decoyXmp = $xmpPacket(
    'Trailing WordPress Property Attribute Decoy',
    'Trailing property attribute XMP must stay hidden.',
    '2026-06-08T22:59:59Z'
);
$metadataBytes = $currentXmp . "\0\0\n" . $decoyXmp;

$pdf = $pdfWithMetadata($metadataBytes, '/Type /Metadata /Subtype /XML', 'WordPress Property Attribute Body');
$rejectedPdf = $pdfWithMetadata(
    $metadataBytes,
    '/Type /EmbeddedFile /Subtype /text#2Fxml',
    'Rejected WordPress Property Attribute Body'
);

$metadataExtractor = new PdfMetadataExtractor();
$textExtractor = new PdfTextExtractor();

$metadata = $metadataExtractor->extractDocumentMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$rejectedMetadata = $metadataExtractor->extractDocumentMetadata($rejectedPdf);
$rejectedText = $textExtractor->extractPlainText($rejectedPdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$rejectedEncoded = json_encode($rejectedMetadata, JSON_UNESCAPED_SLASHES);
$review = $rejectedMetadata['catalog']['metadata_stream_review'] ?? [];
$summary = $review['xmp_summary'] ?? [];

if (($metadata['title'] ?? null) !== 'WordPress Property Attribute XMP Title') {
    throw new RuntimeException('Expected direct property membership XMP title to become document metadata.');
}
if (($metadata['description'] ?? null) !== 'Direct RDF membership attributes become WordPress metadata.') {
    throw new RuntimeException('Expected direct property membership XMP description.');
}
if (($metadata['authors'] ?? []) !== ['WordPress Metadata Editor', 'WordPress Metadata Reviewer']) {
    throw new RuntimeException('Expected ordered direct property membership authors.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-property-attribute-membership']) {
    throw new RuntimeException('Expected ordered direct property membership keywords.');
}
if (!is_string($encoded) || str_contains($encoded, 'direct property author decoy')) {
    throw new RuntimeException('Direct property membership qualifier text leaked into metadata.');
}
if (str_contains($plainText, 'WordPress Property Attribute XMP Title')) {
    throw new RuntimeException('XMP metadata title leaked into visible WordPress text.');
}
if (($review['status'] ?? null) !== 'rejected_non_metadata_xml_stream') {
    throw new RuntimeException('Expected rejected non-metadata XML stream review.');
}
if (($summary['field_names'] ?? []) !== ['title', 'description', 'creator_tool', 'producer', 'created_at', 'metadata_date', 'authors', 'keywords']) {
    throw new RuntimeException('Expected rejected stream summary to count direct property membership fields.');
}
if (!is_string($rejectedEncoded) || str_contains($rejectedEncoded, 'WordPress Property Attribute XMP Title')) {
    throw new RuntimeException('Rejected direct property membership values leaked into review metadata.');
}
if (str_contains($rejectedText, 'WordPress Property Attribute XMP Title')) {
    throw new RuntimeException('Rejected direct property membership XMP leaked into visible WordPress text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-property-attribute-membership-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-direct-property-membership',
    'native_boundary' => 'RDF membership attributes on XMP metadata property elements are ordered metadata values',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title' => $metadata['title'] ?? null,
    'authors' => $metadata['authors'] ?? [],
    'keywords' => $metadata['keywords'] ?? [],
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? null,
    'direct_property_membership_extracted' => ($metadata['authors'] ?? []) === ['WordPress Metadata Editor', 'WordPress Metadata Reviewer'],
    'qualifier_text_redacted' => is_string($encoded) && !str_contains($encoded, 'direct property author decoy'),
    'xmp_metadata_not_visible_text' => !str_contains($plainText, 'WordPress Property Attribute XMP Title'),
    'rejected_stream_status' => $review['status'] ?? null,
    'rejected_field_names' => $summary['field_names'] ?? [],
    'rejected_text_values_redacted' => is_string($rejectedEncoded)
        && !str_contains($rejectedEncoded, 'WordPress Property Attribute XMP Title'),
    'rejected_xmp_not_visible_text' => !str_contains($rejectedText, 'WordPress Property Attribute XMP Title'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-property-attribute-membership-metadata ' . $htmlJson([
    'description' => $metadata['description'] ?? null,
    'creator_tool' => $metadata['creator_tool'] ?? null,
    'producer' => $metadata['producer'] ?? null,
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $metadata['metadata_date_utc'] ?? null,
]) . " -->\n";
