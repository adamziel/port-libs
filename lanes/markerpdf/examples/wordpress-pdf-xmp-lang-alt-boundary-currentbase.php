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
    . '<rdf:li xml:lang="fr-FR">Localized Lang Alt Decoy Title</rdf:li>'
    . '<rdf:li xml:lang="X-DEFAULT">Current Lang Alt XMP Title</rdf:li>'
    . '</rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li>Lang Alt Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt>'
    . '<rdf:li xml:lang="fr-FR">Localized lang alt description must not become the document summary</rdf:li>'
    . '<rdf:li xml:lang="X-DEFAULT">Default language XMP description wins case-insensitively</rdf:li>'
    . '</rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-lang-alt-boundary</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>Lang Alt Boundary Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Lang Alt Boundary Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-04T23:59:13-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-05T03:59:13Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress XMP language alternative smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Lang Alt Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Lang Alt Info Title) /Author (Info Fallback Author) /Producer (Info Fallback Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Current Lang Alt XMP Title') {
    throw new RuntimeException('Expected uppercase X-DEFAULT language alternative to supply the document title.');
}
if (($metadata['description'] ?? null) !== 'Default language XMP description wins case-insensitively') {
    throw new RuntimeException('Expected uppercase X-DEFAULT language alternative to supply the document description.');
}
if (!is_string($encoded) || str_contains($encoded, 'Localized Lang Alt Decoy Title')) {
    throw new RuntimeException('Expected localized language alternative decoy to stay out of WordPress metadata.');
}
if (str_contains($plainText, 'Current Lang Alt XMP Title') || str_contains($plainText, 'Localized Lang Alt Decoy Title')) {
    throw new RuntimeException('Expected XMP language alternatives to stay out of visible WordPress paragraphs.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-lang-alt-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-language-alternative-boundary',
    'native_boundary' => 'XMP rdf:Alt xml:lang x-default matching is case-insensitive before WordPress metadata import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_uppercase_x_default' => ($metadata['title'] ?? null) === 'Current Lang Alt XMP Title',
    'description_from_uppercase_x_default' => ($metadata['description'] ?? null) === 'Default language XMP description wins case-insensitively',
    'localized_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'Localized Lang Alt Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Current Lang Alt XMP Title')
        && !str_contains($plainText, 'Localized Lang Alt Decoy Title'),
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
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
