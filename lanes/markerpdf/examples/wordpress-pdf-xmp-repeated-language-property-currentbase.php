<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title xml:lang="fr-FR">Localized Repeated Language Decoy Title</dc:title>'
    . '<dc:title xml:lang="x-default">Current Repeated Language XMP Title</dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li>Repeated Language Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
    . '<dc:description xml:lang="fr-FR">Localized repeated language description must not become the summary</dc:description>'
    . '<dc:description xml:lang="x-default">Repeated simple XMP properties use the x-default sibling</dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-repeated-language-property</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>Repeated Language Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Repeated Language Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-08T17:57:35-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-08T21:57:35Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>'
    . "\0\0"
    . '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title xml:lang="x-default">Trailing Repeated Language Decoy Title</dc:title>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress XMP repeated-language property smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Repeated Language Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Repeated Language Info Title) /Author (Info Repeated Language Author) /Producer (Info Repeated Language Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Current Repeated Language XMP Title') {
    throw new RuntimeException('Expected repeated x-default title sibling to win over localized XMP title.');
}
if (($metadata['description'] ?? null) !== 'Repeated simple XMP properties use the x-default sibling') {
    throw new RuntimeException('Expected repeated x-default description sibling to win over localized XMP description.');
}
if (!is_string($encoded) || str_contains($encoded, 'Localized Repeated Language Decoy Title')) {
    throw new RuntimeException('Expected localized repeated XMP title to stay out of metadata JSON.');
}
if (str_contains($plainText, 'Current Repeated Language XMP Title') || str_contains($plainText, 'Localized Repeated Language Decoy Title')) {
    throw new RuntimeException('Expected repeated XMP language properties to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-repeated-language-property-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-repeated-language-property-boundary',
    'native_boundary' => 'Catalog /Metadata repeated simple dc:title/dc:description siblings prefer xml:lang x-default before WordPress metadata import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_repeated_x_default' => ($metadata['title'] ?? null) === 'Current Repeated Language XMP Title',
    'description_from_repeated_x_default' => ($metadata['description'] ?? null) === 'Repeated simple XMP properties use the x-default sibling',
    'localized_sibling_excluded' => is_string($encoded) && !str_contains($encoded, 'Localized Repeated Language Decoy Title'),
    'trailing_packet_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Repeated Language Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Current Repeated Language XMP Title')
        && !str_contains($plainText, 'Localized Repeated Language Decoy Title'),
]) . " -->\n";

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
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
