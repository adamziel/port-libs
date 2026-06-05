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
    . '<dc:title><rdf:Alt xml:lang="x-default">'
    . '<rdf:li xml:lang="fr-FR">Localized WordPress XMP Decoy Title</rdf:li>'
    . '<rdf:li>Inherited WordPress XMP Title</rdf:li>'
    . '</rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li>Inherited Language Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt xml:lang="x-default">'
    . '<rdf:li xml:lang="fr-FR">Localized description must not become the WordPress excerpt</rdf:li>'
    . '<rdf:li>Inherited x-default description is the document summary</rdf:li>'
    . '</rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-inherited-language</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>Inherited Language Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Inherited Language Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-05T11:22:56-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-05T15:22:56Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$content = 'BT /F1 12 Tf 72 720 Td (Inherited Language Import Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Length " . strlen($xmp) . " >>\nstream\n{$xmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Inherited Language Info Title) /Author (Info Inherited Author) /Producer (Info Inherited Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Inherited WordPress XMP Title') {
    throw new RuntimeException('Expected inherited xml:lang x-default XMP title to win before WordPress import.');
}
if (($metadata['description'] ?? null) !== 'Inherited x-default description is the document summary') {
    throw new RuntimeException('Expected inherited xml:lang x-default XMP description to win before WordPress import.');
}
if (!is_string($encoded) || str_contains($encoded, 'Localized WordPress XMP Decoy Title')) {
    throw new RuntimeException('Localized XMP alternative leaked into document metadata.');
}
if (str_contains($plainText, 'Inherited WordPress XMP Title') || str_contains($plainText, 'Localized WordPress XMP Decoy Title')) {
    throw new RuntimeException('XMP alternatives leaked into visible WordPress text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-xmp-inherited-language-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-metadata-boundary',
    'native_boundary' => 'XMP rdf:Alt xml:lang inheritance selects the x-default document metadata alternative before localized decoys',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_inherited_x_default' => ($metadata['title'] ?? null) === 'Inherited WordPress XMP Title',
    'description_from_inherited_x_default' => ($metadata['description'] ?? null) === 'Inherited x-default description is the document summary',
    'localized_alternative_excluded' => is_string($encoded) && !str_contains($encoded, 'Localized WordPress XMP Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Inherited WordPress XMP Title') && !str_contains($plainText, 'Localized WordPress XMP Decoy Title'),
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $metadata['metadata_date_utc'] ?? null,
],) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
