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

$metadataBytes = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Duplicate RDF XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<xmp:CreateDate>2026-06-06T14:01:42-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-06T18:01:42Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:creator><rdf:Seq><rdf:li>Stale Duplicate RDF Author</rdf:li></rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Stale duplicate RDF description leak</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>stale-rdf-keyword</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>Stale Duplicate RDF Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Stale Duplicate RDF Tool</xmp:CreatorTool>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP duplicate-RDF-root smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Duplicate RDF Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Duplicate RDF Info Title) /Author (Info Duplicate RDF Author) /Producer (Info Duplicate RDF Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Current Duplicate RDF XMP Title') {
    throw new RuntimeException('Expected the first document-level RDF root to provide the XMP title.');
}
if (($metadata['xmp']['packet_boundary_applied'] ?? null) !== true) {
    throw new RuntimeException('Expected bounded xpacket selection to be recorded.');
}
if (!is_string($encoded) || str_contains($encoded, 'Stale Duplicate RDF Author')) {
    throw new RuntimeException('Expected stale duplicate RDF author to stay out of metadata.');
}
if (!is_string($encoded) || str_contains($encoded, 'Stale duplicate RDF description leak')) {
    throw new RuntimeException('Expected stale duplicate RDF description to stay out of metadata.');
}
if (!is_string($encoded) || str_contains($encoded, 'stale-rdf-keyword')) {
    throw new RuntimeException('Expected stale duplicate RDF keywords to stay out of metadata.');
}
if (str_contains($plainText, 'Current Duplicate RDF XMP Title') || str_contains($plainText, 'Stale Duplicate RDF Author')) {
    throw new RuntimeException('Expected XMP text to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-duplicate-rdf-root-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-document-level-rdf-boundary',
    'native_boundary' => 'Only the first document-level rdf:RDF root under the active XMP packet defines document metadata',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_first_rdf_root' => ($metadata['title'] ?? null) === 'Current Duplicate RDF XMP Title',
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'packet_encoding' => $metadata['xmp']['packet_encoding'] ?? null,
    'info_author_fallback_preserved' => ($metadata['authors'] ?? []) === ['Info Duplicate RDF Author'],
    'info_producer_fallback_preserved' => ($metadata['producer'] ?? null) === 'Info Duplicate RDF Producer',
    'stale_rdf_author_excluded' => is_string($encoded) && !str_contains($encoded, 'Stale Duplicate RDF Author'),
    'stale_rdf_description_excluded' => is_string($encoded) && !str_contains($encoded, 'Stale duplicate RDF description leak'),
    'stale_rdf_keywords_excluded' => is_string($encoded) && !str_contains($encoded, 'stale-rdf-keyword'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Current Duplicate RDF XMP Title')
        && !str_contains($plainText, 'Stale Duplicate RDF Author'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-metadata ' . $htmlJson([
    'authors' => $metadata['authors'] ?? [],
    'producer' => $metadata['producer'] ?? null,
    'created_at' => $metadata['created_at'] ?? null,
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $metadata['metadata_date_utc'] ?? null,
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
