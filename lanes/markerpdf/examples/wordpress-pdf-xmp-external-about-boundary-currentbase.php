<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="https://example.org/external-asset"'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">External Asset XMP Decoy Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li>External Asset Author</rdf:li></rdf:Seq></dc:creator>'
    . '<pdf:Producer>External Asset Producer</pdf:Producer>'
    . '<xmp:CreateDate>2026-06-05T22:59:59Z</xmp:CreateDate>'
    . '</rdf:Description>'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current External About WordPress Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li>External About Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">WordPress import ignores non-document XMP resources.</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-external-about</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>External About Boundary Producer</pdf:Producer>'
    . '<xmp:CreatorTool>External About Boundary Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-05T18:14:53-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-05T22:14:53Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$trailingXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Trailing External About XMP Decoy Title</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$xmp = $currentXmp . "\0\0 \n" . $trailingXmp;

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress XMP external-about boundary smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP External About Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (External About Info Fallback Title) /Author (Info External About Author) /Producer (Info External About Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Current External About WordPress Title') {
    throw new RuntimeException('Expected document-level rdf:about="" XMP title to win.');
}
if (($metadata['authors'] ?? []) !== ['External About Boundary Editor', 'Import Review Team']) {
    throw new RuntimeException('Expected document-level XMP authors to win.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, 'External Asset XMP Decoy Title')
    || str_contains($encoded, 'External Asset Author')
    || str_contains($encoded, 'Trailing External About XMP Decoy Title')
    || str_contains($plainText, 'External Asset XMP Decoy Title')
    || str_contains($plainText, 'Trailing External About XMP Decoy Title')
) {
    throw new RuntimeException('External-resource XMP values leaked into WordPress import output.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-external-about-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-document-resource-boundary',
    'native_boundary' => 'XMP rdf:about values identify resources; only empty or absent rdf:about top-level nodes are document metadata roots',
    'source' => $metadata['source'],
    'title_from_document_xmp' => ($metadata['title'] ?? null) === 'Current External About WordPress Title',
    'authors_from_document_xmp' => ($metadata['authors'] ?? []) === ['External About Boundary Editor', 'Import Review Team'],
    'external_about_values_excluded' => is_string($encoded)
        && !str_contains($encoded, 'External Asset XMP Decoy Title')
        && !str_contains($encoded, 'External Asset Author'),
    'trailing_packet_excluded' => is_string($encoded)
        && !str_contains($encoded, 'Trailing External About XMP Decoy Title')
        && !str_contains($plainText, 'Trailing External About XMP Decoy Title'),
    'visible_text_excludes_xmp' => $plainText === 'XMP External About Boundary Body',
    'packet_boundary_applied' => ($metadata['xmp']['packet_boundary_applied'] ?? null) === true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
