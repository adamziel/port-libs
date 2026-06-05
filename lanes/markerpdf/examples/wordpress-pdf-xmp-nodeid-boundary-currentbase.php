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
    . '<dc:title rdf:nodeID="nodeTitleAlt"/>'
    . '<dc:creator rdf:nodeID="nodeCreatorSeq"/>'
    . '<dc:description rdf:nodeID="nodeDescriptionAlt"/>'
    . '<dc:subject rdf:nodeID="nodeSubjectBag"/>'
    . '<pdf:Producer rdf:nodeID="nodeProducerValue"/>'
    . '<xmp:CreatorTool rdf:nodeID="nodeCreatorToolValue"/>'
    . '<xmp:CreateDate rdf:nodeID="nodeCreateDateValue"/>'
    . '<xmp:MetadataDate rdf:nodeID="nodeMetadataDateValue"/>'
    . '</rdf:Description>'
    . '<rdf:Description rdf:nodeID="nodeDecoyProducer" xmlns:pdf="http://ns.adobe.com/pdf/1.3/">'
    . '<pdf:Producer>NodeID Decoy Producer</pdf:Producer>'
    . '</rdf:Description>'
    . '<rdf:Description rdf:nodeID="nodeTitleAlt"><rdf:Alt>'
    . '<rdf:li xml:lang="fr-FR">Titre nodeID ignore</rdf:li>'
    . '<rdf:li xml:lang="x-default">WordPress NodeID XMP Title</rdf:li>'
    . '</rdf:Alt></rdf:Description>'
    . '<rdf:Description rdf:nodeID="nodeCreatorSeq"><rdf:Seq>'
    . '<rdf:li>NodeID Import Author</rdf:li><rdf:li>Data Liberation Team</rdf:li>'
    . '</rdf:Seq></rdf:Description>'
    . '<rdf:Description rdf:nodeID="nodeDescriptionAlt"><rdf:Alt><rdf:li xml:lang="x-default">'
    . 'RDF blank-node references stay metadata before WordPress import'
    . '</rdf:li></rdf:Alt></rdf:Description>'
    . '<rdf:Description rdf:nodeID="nodeSubjectBag"><rdf:Bag>'
    . '<rdf:li>wordpress</rdf:li><rdf:li>xmp-nodeid</rdf:li>'
    . '</rdf:Bag></rdf:Description>'
    . '<rdf:Description rdf:nodeID="nodeProducerValue"><rdf:value>NodeID Boundary Producer</rdf:value></rdf:Description>'
    . '<rdf:Description rdf:nodeID="nodeCreatorToolValue"><rdf:value>NodeID Boundary Tool</rdf:value></rdf:Description>'
    . '<rdf:Description rdf:nodeID="nodeCreateDateValue"><rdf:value>2026-06-05T16:22:48-04:00</rdf:value></rdf:Description>'
    . '<rdf:Description rdf:nodeID="nodeMetadataDateValue"><rdf:value>2026-06-05T20:22:48Z</rdf:value></rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$trailingDecoy = str_replace(
    ['WordPress NodeID XMP Title', 'wordpress'],
    ['Trailing NodeID Decoy Title', 'decoy'],
    $xmp
);
$metadataBytes = $xmp . "\0\0 \n" . $trailingDecoy;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress nodeID XMP smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (NodeID XMP Boundary Import Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (NodeID Info Fallback Title) /Author (Info NodeID Author) /Producer (Info NodeID Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'WordPress NodeID XMP Title') {
    throw new RuntimeException('Expected rdf:nodeID XMP title to be promoted.');
}
if (($metadata['authors'] ?? []) !== ['NodeID Import Author', 'Data Liberation Team']) {
    throw new RuntimeException('Expected rdf:nodeID creator Seq items to remain separate authors.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-nodeid']) {
    throw new RuntimeException('Expected rdf:nodeID subject Bag items to remain separate keywords.');
}
if (!is_string($encoded) || str_contains($encoded, 'NodeID Decoy Producer')) {
    throw new RuntimeException('Unreferenced rdf:nodeID blank-node target leaked into metadata output.');
}
if (!is_string($encoded) || str_contains($encoded, 'Trailing NodeID Decoy Title')) {
    throw new RuntimeException('Trailing nodeID XMP packet leaked into metadata output.');
}
if (str_contains($plainText, 'WordPress NodeID XMP Title') || str_contains($plainText, 'NodeID Import Author')) {
    throw new RuntimeException('rdf:nodeID XMP metadata leaked into visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-nodeid-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-nodeid-boundary',
    'native_boundary' => 'same-packet RDF rdf:nodeID blank-node references resolve to XMP metadata while blank-node targets stay non-root review nodes',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_nodeid' => ($metadata['title'] ?? null) === 'WordPress NodeID XMP Title',
    'authors_from_nodeid_seq' => ($metadata['authors'] ?? []) === ['NodeID Import Author', 'Data Liberation Team'],
    'keywords_from_nodeid_bag' => ($metadata['keywords'] ?? []) === ['wordpress', 'xmp-nodeid'],
    'unreferenced_node_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'NodeID Decoy Producer'),
    'trailing_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing NodeID Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'WordPress NodeID XMP Title')
        && !str_contains($plainText, 'NodeID Import Author'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-nodeid-review ' . $htmlJson([
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
