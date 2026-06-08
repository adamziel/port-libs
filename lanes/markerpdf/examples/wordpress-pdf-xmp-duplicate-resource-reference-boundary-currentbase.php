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
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title rdf:resource="#duplicateTitle"/>'
        . '<dc:creator rdf:nodeID="duplicateCreator"/>'
        . '<dc:description rdf:resource="#duplicateDescription"/>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-duplicate-resource-reference</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Duplicate Resource Reference Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Duplicate Resource Reference Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>2026-06-08T10:08:11-04:00</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T14:08:11Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about="#duplicateTitle"><rdf:Alt><rdf:li xml:lang="x-default">Stale Duplicate Target XMP Title</rdf:li></rdf:Alt></rdf:Description>'
        . '<rdf:Description rdf:ID="duplicateTitle"><rdf:Alt><rdf:li xml:lang="x-default">Current Duplicate Target XMP Title</rdf:li></rdf:Alt></rdf:Description>'
        . '<rdf:Description rdf:nodeID="duplicateCreator"><rdf:Seq><rdf:li>Stale Duplicate Target Author</rdf:li></rdf:Seq></rdf:Description>'
        . '<rdf:Description rdf:nodeID="duplicateCreator"><rdf:Seq><rdf:li>Current Duplicate Target Author</rdf:li></rdf:Seq></rdf:Description>'
        . '<rdf:Description rdf:about="#duplicateDescription"><rdf:Alt><rdf:li xml:lang="x-default">Stale duplicate target description</rdf:li></rdf:Alt></rdf:Description>'
        . '<rdf:Description xml:id="duplicateDescription"><rdf:Alt><rdf:li xml:lang="x-default">Current duplicate target description</rdf:li></rdf:Alt></rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$metadataBytes = $xmpPacket();
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress WordPress XMP duplicate reference metadata.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Duplicate Resource Reference Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Duplicate Reference Info Title) /Subject (Duplicate Reference Info Subject) /Author (Duplicate Reference Info Author) /Producer (Duplicate Reference Info Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$boundary = is_array($metadata['xmp_resource_reference_boundary'] ?? null)
    ? $metadata['xmp_resource_reference_boundary']
    : [];

if (($metadata['title'] ?? null) !== 'Duplicate Reference Info Title') {
    throw new RuntimeException('Expected ambiguous XMP title reference to fall back to Info metadata.');
}
if (($metadata['description'] ?? null) !== 'Duplicate Reference Info Subject') {
    throw new RuntimeException('Expected ambiguous XMP description reference to fall back to Info metadata.');
}
if (($metadata['authors'] ?? null) !== ['Duplicate Reference Info Author']) {
    throw new RuntimeException('Expected ambiguous XMP creator reference to fall back to Info metadata.');
}
if (($boundary['ambiguous_reference_count'] ?? null) !== 3) {
    throw new RuntimeException('Expected three ambiguous XMP resource references in review metadata.');
}
foreach ([
    'Stale Duplicate Target XMP Title',
    'Current Duplicate Target XMP Title',
    'Stale Duplicate Target Author',
    'Current Duplicate Target Author',
] as $excluded) {
    if (str_contains($encoded, $excluded) || str_contains($plainText, $excluded)) {
        throw new RuntimeException('Expected duplicate XMP target payload text to stay excluded.');
    }
}

echo '<!-- markerpdf-pdf-xmp-duplicate-resource-reference-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-xmp-duplicate-resource-reference-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-duplicate-resource-reference-boundary',
    'native_boundary' => 'ambiguous same-packet RDF targets remain review-only before WordPress metadata import',
    'source' => $metadata['source'] ?? [],
    'info_fallback_used_for_ambiguous_title' => ($metadata['title'] ?? null) === 'Duplicate Reference Info Title',
    'info_fallback_used_for_ambiguous_author' => ($metadata['authors'] ?? null) === ['Duplicate Reference Info Author'],
    'xmp_direct_keywords_preserved' => ($metadata['keywords'] ?? null) === ['wordpress', 'xmp-duplicate-resource-reference'],
    'ambiguous_reference_count' => $boundary['ambiguous_reference_count'] ?? 0,
    'ambiguous_resource_ids' => $boundary['ambiguous_resource_ids'] ?? [],
    'ambiguous_node_ids' => $boundary['ambiguous_node_ids'] ?? [],
    'target_payload_excluded' => !str_contains($encoded, 'Stale Duplicate Target XMP Title')
        && !str_contains($encoded, 'Current Duplicate Target Author'),
    'visible_text_excludes_xmp_target_payload' => !str_contains($plainText, 'Stale Duplicate Target XMP Title')
        && !str_contains($plainText, 'Current Duplicate Target Author'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-metadata ' . $htmlJson([
    'authors' => $metadata['authors'] ?? [],
    'keywords' => $metadata['keywords'] ?? [],
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'xmp_resource_reference_boundary' => [
        'ambiguous_reference_count' => $boundary['ambiguous_reference_count'] ?? 0,
        'ambiguous_resource_ids' => $boundary['ambiguous_resource_ids'] ?? [],
        'ambiguous_node_ids' => $boundary['ambiguous_node_ids'] ?? [],
        'review_only' => $boundary['review_only'] ?? null,
        'payload_included' => $boundary['payload_included'] ?? null,
    ],
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
