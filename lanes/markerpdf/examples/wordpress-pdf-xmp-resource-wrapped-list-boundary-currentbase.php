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
    . '<dc:title><rdf:Alt><rdf:Description><rdf:Alt>'
    . '<rdf:li xml:lang="fr-FR">Titre ressource decoy</rdf:li>'
    . '<rdf:li xml:lang="x-default">Resource Wrapped XMP Import Title</rdf:li>'
    . '</rdf:Alt></rdf:Description></rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Description>'
    . '<rdf:Seq><rdf:li>Resource Wrapped Import Author</rdf:li><rdf:li>Data Liberation Team</rdf:li></rdf:Seq>'
    . '<xmp:roles><rdf:Bag><rdf:li>internal author qualifier</rdf:li></rdf:Bag></xmp:roles>'
    . '</rdf:Description></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:Description><rdf:Alt>'
    . '<rdf:li xml:lang="x-default">RDF resource-wrapped list metadata stays structured before WordPress import</rdf:li>'
    . '</rdf:Alt><pdf:Producer><rdf:Seq><rdf:li>description qualifier list</rdf:li></rdf:Seq></pdf:Producer></rdf:Description></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Description>'
    . '<rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-resource-wrapper</rdf:li></rdf:Bag>'
    . '<xmp:labels><rdf:Seq><rdf:li>internal keyword qualifier</rdf:li></rdf:Seq></xmp:labels>'
    . '</rdf:Description></dc:subject>'
    . '<pdf:Producer>Resource Wrapped Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Resource Wrapped Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-05T12:48:20-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-05T16:48:20Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$trailingDecoy = str_replace(
    ['Resource Wrapped XMP Import Title', 'wordpress'],
    ['Trailing Resource Wrapped Decoy Title', 'decoy'],
    $xmp
);
$metadataBytes = $xmp . "\0\0 \n" . $trailingDecoy;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress resource-wrapped XMP smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Resource Wrapped XMP Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Resource Wrapped Info Title) /Author (Info Resource Author) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Resource Wrapped XMP Import Title') {
    throw new RuntimeException('Expected resource-wrapped XMP title to be promoted.');
}
if (($metadata['authors'] ?? []) !== ['Resource Wrapped Import Author', 'Data Liberation Team']) {
    throw new RuntimeException('Expected RDF resource-wrapped creator Seq items to remain separate authors.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-resource-wrapper']) {
    throw new RuntimeException('Expected RDF resource-wrapped subject Bag items to remain separate keywords.');
}
if (!is_string($encoded) || str_contains($encoded, 'internal author qualifier') || str_contains($encoded, 'internal keyword qualifier')) {
    throw new RuntimeException('Nested qualifier list text leaked into metadata output.');
}
if (!is_string($encoded) || str_contains($encoded, 'Trailing Resource Wrapped Decoy Title')) {
    throw new RuntimeException('Trailing XMP packet leaked into metadata output.');
}
if (str_contains($plainText, 'Resource Wrapped XMP Import Title') || str_contains($plainText, 'internal author qualifier')) {
    throw new RuntimeException('Resource-wrapped XMP metadata leaked into visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-resource-wrapped-list-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-resource-wrapped-rdf-lists',
    'native_boundary' => 'XMP RDF list containers inside direct rdf:Description resource wrappers are treated as document metadata while nested qualifier lists stay non-visible',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_resource_wrapped_alt' => ($metadata['title'] ?? null) === 'Resource Wrapped XMP Import Title',
    'authors_from_resource_wrapped_seq' => ($metadata['authors'] ?? []) === ['Resource Wrapped Import Author', 'Data Liberation Team'],
    'keywords_from_resource_wrapped_bag' => ($metadata['keywords'] ?? []) === ['wordpress', 'xmp-resource-wrapper'],
    'nested_qualifier_text_excluded' => is_string($encoded)
        && !str_contains($encoded, 'internal author qualifier')
        && !str_contains($encoded, 'internal keyword qualifier'),
    'decoy_xmp_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Resource Wrapped Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Resource Wrapped XMP Import Title')
        && !str_contains($plainText, 'internal author qualifier'),
    'packet_boundary_applied' => ($metadata['xmp']['packet_boundary_applied'] ?? null) === true,
],) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-resource-wrapped-list-review ' . $htmlJson([
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
