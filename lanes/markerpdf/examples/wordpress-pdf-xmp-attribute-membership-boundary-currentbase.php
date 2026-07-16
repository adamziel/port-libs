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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Attribute Membership WordPress Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:creator rdf:nodeID="attributeAuthors"/>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">RDF membership attributes on XMP resource nodes remain WordPress metadata.</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject rdf:resource="#attributeSubjects"/>'
    . '<pdf:Producer>Attribute Membership Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Attribute Membership Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-06T01:19:03-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-06T05:19:03Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '<rdf:Description rdf:nodeID="attributeAuthors"'
    . ' rdf:_2="Attribute Membership Reviewer"'
    . ' rdf:_1="Attribute Membership Editor"'
    . ' rdf:_10="Attribute Membership Contributor"/>'
    . '<rdf:Description rdf:about="#attributeSubjects" rdf:_2="xmp-attribute-membership" rdf:_1="wordpress"/>'
    . '<rdf:Description rdf:about="#attributeDecoy" rdf:_1="External Attribute Decoy"/>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$trailingDecoy = str_replace(
    ['Attribute Membership WordPress Title', 'wordpress'],
    ['Trailing Attribute Membership Decoy Title', 'decoy'],
    $xmp
);
$metadataBytes = $xmp . "\0\0 \n" . $trailingDecoy;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP attribute-membership smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Attribute Membership Import Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Attribute Membership Info Fallback Title) /Author (Info Attribute Membership Author) /Keywords (info, fallback) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';

if (($metadata['title'] ?? null) !== 'Attribute Membership WordPress Title') {
    throw new RuntimeException('Expected XMP title to be promoted.');
}
if (($metadata['authors'] ?? []) !== ['Attribute Membership Editor', 'Attribute Membership Reviewer', 'Attribute Membership Contributor']) {
    throw new RuntimeException('Expected rdf:_n attributes on referenced creator node to be ordered by index.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-attribute-membership']) {
    throw new RuntimeException('Expected rdf:_n attributes on referenced subject node to remain separate keywords.');
}
if (in_array('Info Attribute Membership Author', $metadata['authors'] ?? [], true)) {
    throw new RuntimeException('Expected XMP authors to take precedence over Info fallback authors.');
}
if (
    str_contains($encoded, 'Trailing Attribute Membership Decoy Title')
    || str_contains($encoded, 'External Attribute Decoy')
) {
    throw new RuntimeException('Expected trailing packet and unreferenced membership resources to stay out of metadata output.');
}
if (
    str_contains($plainText, 'Attribute Membership WordPress Title')
    || str_contains($plainText, 'Attribute Membership Editor')
    || str_contains($plainText, 'Trailing Attribute Membership Decoy Title')
) {
    throw new RuntimeException('Expected XMP metadata values to stay out of visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-attribute-membership-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-rdf-attribute-membership-boundary',
    'native_boundary' => 'XMP RDF membership attributes rdf:_n on referenced resource nodes are ordered before WordPress document metadata promotion',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'] ?? [],
    'title_from_xmp' => ($metadata['title'] ?? null) === 'Attribute Membership WordPress Title',
    'authors_from_attribute_membership' => ($metadata['authors'] ?? []) === ['Attribute Membership Editor', 'Attribute Membership Reviewer', 'Attribute Membership Contributor'],
    'keywords_from_attribute_membership' => ($metadata['keywords'] ?? []) === ['wordpress', 'xmp-attribute-membership'],
    'info_author_not_promoted' => !in_array('Info Attribute Membership Author', $metadata['authors'] ?? [], true),
    'packet_boundary_applied' => ($metadata['xmp']['packet_boundary_applied'] ?? null) === true,
    'trailing_decoy_excluded' => !str_contains($encoded, 'Trailing Attribute Membership Decoy Title'),
    'unreferenced_resource_excluded' => !str_contains($encoded, 'External Attribute Decoy'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Attribute Membership WordPress Title')
        && !str_contains($plainText, 'Attribute Membership Editor')
        && !str_contains($plainText, 'Trailing Attribute Membership Decoy Title'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-attribute-membership-review ' . $htmlJson([
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
