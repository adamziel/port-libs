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
    . '<dc:title><rdf:Alt><rdf:li> </rdf:li><rdf:Description><rdf:Alt>'
    . '<rdf:li xml:lang="fr-FR">Titre sparse import decoy</rdf:li>'
    . '<rdf:li xml:lang="x-default">Sparse List XMP Import Title</rdf:li>'
    . '</rdf:Alt></rdf:Description></rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li> </rdf:li></rdf:Seq><rdf:Description><rdf:Seq>'
    . '<rdf:li>Sparse Import Author</rdf:li><rdf:li>Data Liberation Team</rdf:li>'
    . '</rdf:Seq></rdf:Description></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Sparse RDF list placeholders fall through before WordPress import</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li> </rdf:li></rdf:Bag><rdf:Description><rdf:Bag>'
    . '<rdf:li>wordpress</rdf:li><rdf:li>xmp-sparse-list</rdf:li>'
    . '</rdf:Bag></rdf:Description></dc:subject>'
    . '<pdf:Producer>Sparse List Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Sparse List Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-05T13:15:01-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-05T17:15:01Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$trailingDecoy = str_replace(
    ['Sparse List XMP Import Title', 'wordpress'],
    ['Trailing Sparse List Decoy Title', 'decoy'],
    $xmp
);
$metadataBytes = $xmp . "\0\0 \n" . $trailingDecoy;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress sparse-list XMP smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Sparse List XMP Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Sparse List Info Title) /Author (Info Sparse Author) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Sparse List XMP Import Title') {
    throw new RuntimeException('Expected sparse-list fallback XMP title to be promoted.');
}
if (($metadata['authors'] ?? []) !== ['Sparse Import Author', 'Data Liberation Team']) {
    throw new RuntimeException('Expected sparse-list creator fallback Seq items to remain separate authors.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-sparse-list']) {
    throw new RuntimeException('Expected sparse-list subject fallback Bag items to remain separate keywords.');
}
if (!is_string($encoded) || str_contains($encoded, 'Sparse Import AuthorData Liberation Team')) {
    throw new RuntimeException('Sparse-list author values were concatenated.');
}
if (!is_string($encoded) || str_contains($encoded, 'wordpressxmp-sparse-list')) {
    throw new RuntimeException('Sparse-list keyword values were concatenated.');
}
if (!is_string($encoded) || str_contains($encoded, 'Trailing Sparse List Decoy Title')) {
    throw new RuntimeException('Trailing XMP packet leaked into metadata output.');
}
if (str_contains($plainText, 'Sparse List XMP Import Title') || str_contains($plainText, 'Sparse Import Author')) {
    throw new RuntimeException('Sparse-list XMP metadata leaked into visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-sparse-list-fallback-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-sparse-rdf-list-fallback',
    'native_boundary' => 'empty RDF list placeholders fall through to a later resource-wrapped list before WordPress metadata import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_sparse_list_fallback' => ($metadata['title'] ?? null) === 'Sparse List XMP Import Title',
    'authors_not_concatenated' => ($metadata['authors'] ?? []) === ['Sparse Import Author', 'Data Liberation Team'],
    'keywords_not_concatenated' => ($metadata['keywords'] ?? []) === ['wordpress', 'xmp-sparse-list'],
    'empty_placeholder_excluded' => is_string($encoded)
        && !str_contains($encoded, 'Sparse Import AuthorData Liberation Team')
        && !str_contains($encoded, 'wordpressxmp-sparse-list'),
    'decoy_xmp_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Sparse List Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Sparse List XMP Import Title')
        && !str_contains($plainText, 'Sparse Import Author'),
    'packet_boundary_applied' => ($metadata['xmp']['packet_boundary_applied'] ?? null) === true,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-sparse-list-review ' . $htmlJson([
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
