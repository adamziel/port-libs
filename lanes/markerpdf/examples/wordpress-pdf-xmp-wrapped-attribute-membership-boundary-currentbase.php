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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Wrapped Attribute WordPress Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Description rdf:_2="Wrapped Attribute Reviewer" rdf:_1="Wrapped Attribute Editor" rdf:_10="Wrapped Attribute Contributor"/></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Inline wrapped RDF membership attributes remain WordPress metadata.</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Description rdf:_2="xmp-wrapped-attribute" rdf:_1="wordpress"/></dc:subject>'
    . '<pdf:Producer>Wrapped Attribute Membership Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Wrapped Attribute Membership Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-06T07:52:48-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-06T11:52:48Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '<rdf:Description rdf:about="#wrappedAttributeDecoy" rdf:_1="External Wrapped Attribute Decoy"/>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$trailingDecoy = str_replace(
    ['Wrapped Attribute WordPress Title', 'wordpress'],
    ['Trailing Wrapped Attribute Decoy Title', 'decoy'],
    $xmp
);
$metadataBytes = $xmp . "\0\0 \n" . $trailingDecoy;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP wrapped attribute-membership smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Wrapped Attribute Import Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Wrapped Attribute Info Fallback Title) /Author (Info Wrapped Attribute Author) /Keywords (info, fallback) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';

if (($metadata['title'] ?? null) !== 'Wrapped Attribute WordPress Title') {
    throw new RuntimeException('Expected XMP title to be promoted.');
}
if (($metadata['authors'] ?? []) !== ['Wrapped Attribute Editor', 'Wrapped Attribute Reviewer', 'Wrapped Attribute Contributor']) {
    throw new RuntimeException('Expected rdf:_n attributes on inline wrapped creator nodes to be ordered by index.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-wrapped-attribute']) {
    throw new RuntimeException('Expected rdf:_n attributes on inline wrapped subject nodes to remain separate keywords.');
}
if (in_array('Info Wrapped Attribute Author', $metadata['authors'] ?? [], true)) {
    throw new RuntimeException('Expected inline XMP authors to take precedence over Info fallback authors.');
}
if (
    str_contains($encoded, 'Trailing Wrapped Attribute Decoy Title')
    || str_contains($encoded, 'External Wrapped Attribute Decoy')
) {
    throw new RuntimeException('Expected trailing packet and unreferenced wrapped resources to stay out of metadata output.');
}
if (
    str_contains($plainText, 'Wrapped Attribute WordPress Title')
    || str_contains($plainText, 'Wrapped Attribute Editor')
    || str_contains($plainText, 'Trailing Wrapped Attribute Decoy Title')
) {
    throw new RuntimeException('Expected XMP metadata values to stay out of visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-wrapped-attribute-membership-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-rdf-wrapped-attribute-membership-boundary',
    'native_boundary' => 'Inline XMP RDF resource wrappers carrying rdf:_n attributes are ordered before WordPress document metadata promotion',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'] ?? [],
    'title_from_xmp' => ($metadata['title'] ?? null) === 'Wrapped Attribute WordPress Title',
    'authors_from_inline_wrapped_membership' => ($metadata['authors'] ?? []) === ['Wrapped Attribute Editor', 'Wrapped Attribute Reviewer', 'Wrapped Attribute Contributor'],
    'keywords_from_inline_wrapped_membership' => ($metadata['keywords'] ?? []) === ['wordpress', 'xmp-wrapped-attribute'],
    'info_author_not_promoted' => !in_array('Info Wrapped Attribute Author', $metadata['authors'] ?? [], true),
    'packet_boundary_applied' => ($metadata['xmp']['packet_boundary_applied'] ?? null) === true,
    'trailing_decoy_excluded' => !str_contains($encoded, 'Trailing Wrapped Attribute Decoy Title'),
    'unreferenced_resource_excluded' => !str_contains($encoded, 'External Wrapped Attribute Decoy'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Wrapped Attribute WordPress Title')
        && !str_contains($plainText, 'Wrapped Attribute Editor')
        && !str_contains($plainText, 'Trailing Wrapped Attribute Decoy Title'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-wrapped-attribute-membership-review ' . $htmlJson([
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
