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
    . '<dc:title rdf:resource="#titleAlt"/>'
    . '<dc:creator rdf:resource="#creatorSeq"/>'
    . '<dc:description rdf:resource="#descriptionAlt"/>'
    . '<dc:subject rdf:resource="#subjectBag"/>'
    . '<pdf:Producer rdf:resource="#producerValue"/>'
    . '<xmp:CreatorTool rdf:resource="#toolValue"/>'
    . '<xmp:CreateDate rdf:resource="#createDateValue"/>'
    . '<xmp:ModifyDate rdf:resource="https://example.invalid/xmp#externalModified"/>'
    . '<xmp:MetadataDate rdf:resource="#metadataDateValue"/>'
    . '</rdf:Description>'
    . '<rdf:Description rdf:about="#titleAlt"><rdf:Alt>'
    . '<rdf:li xml:lang="fr-FR">Titre reference decoy</rdf:li>'
    . '<rdf:li xml:lang="x-default">Resource Reference XMP Import Title</rdf:li>'
    . '</rdf:Alt><xmp:PrivateLabel>title reference qualifier</xmp:PrivateLabel></rdf:Description>'
    . '<rdf:Description rdf:about="#creatorSeq"><rdf:Seq>'
    . '<rdf:li>Resource Reference Import Author</rdf:li><rdf:li>Data Liberation Team</rdf:li>'
    . '</rdf:Seq><xmp:PrivateRole>author reference qualifier</xmp:PrivateRole></rdf:Description>'
    . '<rdf:Description rdf:about="#descriptionAlt"><rdf:Alt><rdf:li xml:lang="x-default">'
    . 'Same-packet RDF fragment references stay metadata before WordPress import'
    . '</rdf:li></rdf:Alt><pdf:Producer>description qualifier noise</pdf:Producer></rdf:Description>'
    . '<rdf:Description rdf:about="#subjectBag"><rdf:Bag>'
    . '<rdf:li>wordpress</rdf:li><rdf:li>xmp-resource-reference</rdf:li>'
    . '</rdf:Bag><xmp:PrivateTag>keyword reference qualifier</xmp:PrivateTag></rdf:Description>'
    . '<rdf:Description rdf:about="#producerValue"><rdf:value>Resource Reference Producer</rdf:value></rdf:Description>'
    . '<rdf:Description rdf:about="#toolValue" rdf:value="Resource Reference Tool"/>'
    . '<rdf:Description rdf:about="#createDateValue"><rdf:value>2026-06-05T14:30:45-04:00</rdf:value></rdf:Description>'
    . '<rdf:Description rdf:about="#metadataDateValue" rdf:value="2026-06-05T18:30:45Z"/>'
    . '<rdf:Description rdf:about="#cycleA" rdf:resource="#cycleB"/>'
    . '<rdf:Description rdf:about="#cycleB" rdf:resource="#cycleA"/>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$trailingDecoy = str_replace(
    ['Resource Reference XMP Import Title', 'wordpress'],
    ['Trailing Resource Reference Decoy Title', 'decoy'],
    $xmp
);
$metadataBytes = $xmp . "\0\0 \n" . $trailingDecoy;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress resource-reference XMP smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Resource Reference XMP Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Resource Reference Info Title) /Author (Info Resource Reference Author) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Resource Reference XMP Import Title') {
    throw new RuntimeException('Expected resource-reference XMP title to be promoted.');
}
if (($metadata['authors'] ?? []) !== ['Resource Reference Import Author', 'Data Liberation Team']) {
    throw new RuntimeException('Expected resource-reference creator Seq items to remain separate authors.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-resource-reference']) {
    throw new RuntimeException('Expected resource-reference subject Bag items to remain separate keywords.');
}
if (isset($metadata['modified_at'])) {
    throw new RuntimeException('External XMP rdf:resource reference should not be resolved.');
}
if (!is_string($encoded) || str_contains($encoded, 'author reference qualifier') || str_contains($encoded, 'keyword reference qualifier')) {
    throw new RuntimeException('Fragment target qualifier text leaked into metadata output.');
}
if (!is_string($encoded) || str_contains($encoded, 'Trailing Resource Reference Decoy Title')) {
    throw new RuntimeException('Trailing XMP packet leaked into metadata output.');
}
if (str_contains($plainText, 'Resource Reference XMP Import Title') || str_contains($plainText, 'Resource Reference Import Author')) {
    throw new RuntimeException('Resource-reference XMP metadata leaked into visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-resource-reference-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-resource-reference-boundary',
    'native_boundary' => 'same-packet rdf:resource fragments resolve to document metadata while external, missing, and cyclic references stay ignored',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_fragment_resource' => ($metadata['title'] ?? null) === 'Resource Reference XMP Import Title',
    'authors_from_fragment_seq' => ($metadata['authors'] ?? []) === ['Resource Reference Import Author', 'Data Liberation Team'],
    'keywords_from_fragment_bag' => ($metadata['keywords'] ?? []) === ['wordpress', 'xmp-resource-reference'],
    'external_resource_ignored' => !isset($metadata['modified_at']),
    'fragment_target_qualifiers_excluded' => is_string($encoded)
        && !str_contains($encoded, 'author reference qualifier')
        && !str_contains($encoded, 'keyword reference qualifier'),
    'decoy_xmp_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Resource Reference Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Resource Reference XMP Import Title')
        && !str_contains($plainText, 'Resource Reference Import Author'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-resource-reference-review ' . $htmlJson([
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
