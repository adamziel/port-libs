<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title rdf:resource="#nestedTitleAlt"/>'
    . '<dc:creator rdf:nodeID="nestedCreatorSeq"/>'
    . '<dc:description rdf:resource="#nestedDescriptionAlt"/>'
    . '<dc:subject rdf:nodeID="nestedSubjectBag"/>'
    . '<pdf:Producer rdf:resource="#nestedProducerValue"/>'
    . '<xmp:CreatorTool rdf:nodeID="nestedToolValue"/>'
    . '<xmp:CreateDate rdf:resource="#nestedCreateDateValue"/>'
    . '<xmp:ModifyDate rdf:resource="https://example.invalid/xmp#externalModified"/>'
    . '<xmp:MetadataDate rdf:nodeID="nestedMetadataDateValue"/>'
    . '<xmp:NestedGraph>'
    . '<rdf:RDF><rdf:Description rdf:about="#nestedProducerValue"><rdf:value>Nested RDF Decoy Producer</rdf:value></rdf:Description></rdf:RDF>'
    . '<rdf:Description rdf:ID="nestedTitleAlt"><rdf:Alt>'
    . '<rdf:li xml:lang="fr-FR">Titre imbrique decoy</rdf:li>'
    . '<rdf:li xml:lang="x-default">Nested Resource XMP Import Title</rdf:li>'
    . '</rdf:Alt><xmp:PrivateLabel>nested title qualifier</xmp:PrivateLabel></rdf:Description>'
    . '<rdf:Description rdf:nodeID="nestedCreatorSeq"><rdf:Seq>'
    . '<rdf:li>Nested Resource Import Author</rdf:li><rdf:li>Data Liberation Team</rdf:li>'
    . '</rdf:Seq><xmp:PrivateRole>nested author qualifier</xmp:PrivateRole></rdf:Description>'
    . '<rdf:Description xml:id="nestedDescriptionAlt"><rdf:Alt><rdf:li xml:lang="x-default">'
    . 'Nested same-packet RDF targets stay metadata before WordPress import'
    . '</rdf:li></rdf:Alt><pdf:Producer>nested description qualifier</pdf:Producer></rdf:Description>'
    . '<rdf:Description rdf:nodeID="nestedSubjectBag"><rdf:Bag>'
    . '<rdf:li>wordpress</rdf:li><rdf:li>xmp-nested-resource-reference</rdf:li>'
    . '</rdf:Bag><xmp:PrivateTag>nested keyword qualifier</xmp:PrivateTag></rdf:Description>'
    . '<rdf:Description rdf:about="#nestedProducerValue"><rdf:value>Nested Resource Producer</rdf:value></rdf:Description>'
    . '<rdf:Description rdf:nodeID="nestedToolValue" rdf:value="Nested Resource Tool"/>'
    . '<rdf:Description rdf:about="#nestedCreateDateValue"><rdf:value>2026-06-08T04:15:13-04:00</rdf:value></rdf:Description>'
    . '<rdf:Description rdf:nodeID="nestedMetadataDateValue" rdf:value="2026-06-08T08:15:13Z"/>'
    . '</xmp:NestedGraph>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$trailingDecoy = str_replace(
    ['Nested Resource XMP Import Title', 'wordpress'],
    ['Trailing Nested Resource Decoy Title', 'decoy'],
    $xmp
);
$metadataBytes = $xmp . "\0\0 \n" . $trailingDecoy;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress nested resource-reference XMP smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Nested Resource XMP Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Nested Resource Info Title) /Author (Info Nested Resource Author) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Nested Resource XMP Import Title') {
    throw new RuntimeException('Expected nested resource-reference XMP title to be promoted.');
}
if (($metadata['authors'] ?? []) !== ['Nested Resource Import Author', 'Data Liberation Team']) {
    throw new RuntimeException('Expected nested nodeID creator Seq items to remain separate authors.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-nested-resource-reference']) {
    throw new RuntimeException('Expected nested nodeID subject Bag items to remain separate keywords.');
}
if (($metadata['producer'] ?? null) !== 'Nested Resource Producer') {
    throw new RuntimeException('Expected nested resource producer to override nested RDF decoy.');
}
if (isset($metadata['modified_at'])) {
    throw new RuntimeException('External XMP rdf:resource reference should not be resolved.');
}
if (!is_string($encoded) || str_contains($encoded, 'Nested RDF Decoy Producer') || str_contains($encoded, 'nested author qualifier')) {
    throw new RuntimeException('Nested resource qualifier or nested RDF decoy text leaked into metadata output.');
}
if (!is_string($encoded) || str_contains($encoded, 'Trailing Nested Resource Decoy Title')) {
    throw new RuntimeException('Trailing XMP packet leaked into metadata output.');
}
if (str_contains($plainText, 'Nested Resource XMP Import Title') || str_contains($plainText, 'Nested Resource Import Author')) {
    throw new RuntimeException('Nested resource-reference XMP metadata leaked into visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-nested-resource-reference-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-nested-resource-reference-boundary',
    'native_boundary' => 'same-packet nested rdf:resource and rdf:nodeID targets resolve only from the document-level RDF graph',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_nested_fragment_resource' => ($metadata['title'] ?? null) === 'Nested Resource XMP Import Title',
    'authors_from_nested_node_seq' => ($metadata['authors'] ?? []) === ['Nested Resource Import Author', 'Data Liberation Team'],
    'keywords_from_nested_node_bag' => ($metadata['keywords'] ?? []) === ['wordpress', 'xmp-nested-resource-reference'],
    'nested_rdf_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'Nested RDF Decoy Producer'),
    'external_resource_ignored' => !isset($metadata['modified_at']),
    'fragment_target_qualifiers_excluded' => is_string($encoded)
        && !str_contains($encoded, 'nested author qualifier')
        && !str_contains($encoded, 'nested keyword qualifier'),
    'decoy_xmp_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Nested Resource Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Nested Resource XMP Import Title')
        && !str_contains($plainText, 'Nested Resource Import Author'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-nested-resource-reference-review ' . $htmlJson([
    'authors' => $metadata['authors'] ?? [],
    'description' => $metadata['description'] ?? null,
    'keywords' => $metadata['keywords'] ?? [],
    'creator_tool' => $metadata['creator_tool'] ?? null,
    'producer' => $metadata['producer'] ?? null,
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $metadata['metadata_date_utc'] ?? null,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
