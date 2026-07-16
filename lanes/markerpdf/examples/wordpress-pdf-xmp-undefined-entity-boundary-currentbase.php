<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$metadataBytes = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">&undefinedWordPressTitle;</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">&undefinedWordPressDescription;</rdf:li></rdf:Alt></dc:description>'
    . '<pdf:Producer>Undefined Entity Boundary Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Undefined Entity Boundary Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>&undefinedWordPressDate;</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-08T23:18:33Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>'
    . '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Trailing Valid XMP Decoy Title</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP undefined-entity boundary smoke metadata.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Undefined Entity Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Undefined Entity Info Title) /Author (Info Undefined Entity Author) /Producer (Info Undefined Entity Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$review = $metadata['catalog']['metadata_stream_review'] ?? [];
$summary = is_array($review['xmp_summary'] ?? null) ? $review['xmp_summary'] : [];

if (($metadata['source'] ?? null) !== ['info', 'catalog']) {
    throw new RuntimeException('Expected undefined-entity XMP to leave Info and catalog review metadata.');
}
if (($metadata['title'] ?? null) !== 'Undefined Entity Info Title') {
    throw new RuntimeException('Expected undefined-entity XMP to fail closed to the Info title.');
}
if (($review['status'] ?? null) !== 'rejected_malformed_document_xmp_xml') {
    throw new RuntimeException('Expected malformed document XMP XML review metadata.');
}
if (($summary['status'] ?? null) !== 'rejected_malformed_xmp_xml') {
    throw new RuntimeException('Expected malformed XMP XML packet summary.');
}
if (
    str_contains($encoded, 'undefinedWordPressTitle')
    || str_contains($encoded, 'undefinedWordPressDescription')
    || str_contains($encoded, 'Trailing Valid XMP Decoy Title')
) {
    throw new RuntimeException('Expected undefined entity values and trailing packets to stay out of WordPress metadata.');
}
if (
    str_contains($plainText, 'undefinedWordPressTitle')
    || str_contains($plainText, 'undefinedWordPressDescription')
    || str_contains($plainText, 'Trailing Valid XMP Decoy Title')
) {
    throw new RuntimeException('Expected undefined entity values and trailing XMP packets to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-undefined-entity-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-xmp-undefined-entity-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-undefined-entity-boundary',
    'native_boundary' => 'undefined XML entity references fail closed before XMP promotion and later packet fallback',
    'source' => $metadata['source'] ?? [],
    'title_from_info_fallback' => $metadata['title'] ?? null,
    'xmp_promoted' => ($metadata['xmp'] ?? []) !== [],
    'metadata_review_status' => $review['status'] ?? null,
    'xmp_summary_status' => $summary['status'] ?? null,
    'malformed_xml_boundary' => $summary['malformed_xml_boundary'] ?? null,
    'reason' => $summary['reason'] ?? null,
    'packet_boundary_applied' => $summary['packet_boundary_applied'] ?? false,
    'undefined_entity_values_excluded' => !str_contains($encoded, 'undefinedWordPressTitle')
        && !str_contains($encoded, 'undefinedWordPressDescription'),
    'trailing_packet_not_promoted' => !str_contains($encoded, 'Trailing Valid XMP Decoy Title'),
    'visible_text_excludes_xmp_entities' => !str_contains($plainText, 'undefinedWordPressTitle')
        && !str_contains($plainText, 'undefinedWordPressDescription')
        && !str_contains($plainText, 'Trailing Valid XMP Decoy Title'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
