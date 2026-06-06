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
    . '<dc:title><rdf:Alt>'
    . '<rdf:_1 xml:lang="fr-FR">Titre membership ignore</rdf:_1>'
    . '<rdf:_2 xml:lang="x-default">Membership Property WordPress Title</rdf:_2>'
    . '</rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq>'
    . '<rdf:_2>Second Import Author</rdf:_2>'
    . '<rdf:_1>First Import Author</rdf:_1>'
    . '</rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt>'
    . '<rdf:_1 xml:lang="fr-FR">Localized membership decoy</rdf:_1>'
    . '<rdf:_2 xml:lang="x-default">RDF membership properties remain ordered WordPress metadata.</rdf:_2>'
    . '</rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag>'
    . '<rdf:_2>xmp-membership-property</rdf:_2>'
    . '<rdf:_1>wordpress</rdf:_1>'
    . '</rdf:Bag></dc:subject>'
    . '<pdf:Producer>Membership Property Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Membership Property Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-05T20:12:02-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-06T00:12:02Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$decoyXmp = str_replace('Membership Property WordPress Title', 'Trailing Membership Property Decoy Title', $xmp);
$metadataBytes = $xmp . "\0\0 \n" . $decoyXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP membership-property smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Membership Property Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';

if (($metadata['title'] ?? null) !== 'Membership Property WordPress Title') {
    throw new RuntimeException('Expected rdf:_n title alternative to become document metadata.');
}
if (($metadata['authors'] ?? []) !== ['First Import Author', 'Second Import Author']) {
    throw new RuntimeException('Expected rdf:_n author membership properties to be ordered by index.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-membership-property']) {
    throw new RuntimeException('Expected rdf:_n keyword membership properties to remain separate values.');
}
if (($metadata['created_at_utc'] ?? null) !== '2026-06-06T00:12:02Z') {
    throw new RuntimeException('Expected XMP membership-property dates to normalize.');
}
if (
    str_contains($encoded, 'First Import AuthorSecond Import Author')
    || str_contains($encoded, 'wordpressxmp-membership-property')
    || str_contains($encoded, 'Trailing Membership Property Decoy Title')
) {
    throw new RuntimeException('Expected concatenated and trailing XMP values to stay out of WordPress metadata.');
}
if (
    str_contains($plainText, 'Membership Property WordPress Title')
    || str_contains($plainText, 'First Import Author')
    || str_contains($plainText, 'Trailing Membership Property Decoy Title')
) {
    throw new RuntimeException('Expected XMP metadata values to stay out of visible WordPress paragraphs.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-membership-property-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-rdf-membership-property-boundary',
    'native_boundary' => 'XMP RDF container membership properties rdf:_n are ordered before WordPress document metadata promotion',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'] ?? [],
    'title_from_xmp_membership_alt' => ($metadata['title'] ?? null) === 'Membership Property WordPress Title',
    'authors_ordered_by_membership_index' => ($metadata['authors'] ?? []) === ['First Import Author', 'Second Import Author'],
    'keywords_remain_separate' => ($metadata['keywords'] ?? []) === ['wordpress', 'xmp-membership-property'],
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'concatenated_values_excluded' => !str_contains($encoded, 'First Import AuthorSecond Import Author')
        && !str_contains($encoded, 'wordpressxmp-membership-property'),
    'decoy_xmp_excluded' => !str_contains($encoded, 'Trailing Membership Property Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Membership Property WordPress Title')
        && !str_contains($plainText, 'First Import Author')
        && !str_contains($plainText, 'Trailing Membership Property Decoy Title'),
],) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-metadata ' . $htmlJson([
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
