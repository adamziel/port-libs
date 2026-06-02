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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Caf' . chr(0xe9) . ' ' . chr(0x93) . 'Review' . chr(0x94) . ' Packet</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">WordPress import ' . chr(0x96) . ' encoded metadata</rdf:li></rdf:Alt></dc:description>'
    . '<pdf:Keywords>caf' . chr(0xe9) . ', wp' . chr(0x96) . 'migration</pdf:Keywords>'
    . '<xmp:CreatorTool>InDesign' . chr(0x99) . ' Exporter</xmp:CreatorTool>'
    . '<xmp:CreateDate>2024-06-02T07:15:00-04:00</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress XMP metadata fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Visible Encoding Fallback Body) Tj ET';
$info = '<< /Title (Legacy Encoded Title) /Author (' . chr(0x95) . 'ukasz Editor; Site Owner) /Producer (Info Producer) >>';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "6 0 obj\n{$info}\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

echo '<!-- markerpdf-xmp-info-encoding-fallback-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'Undeclared Windows-1252 XMP bytes decoded before DOM metadata parse with trailer Info fallback for missing authors',
    'source' => $metadata['source'],
    'xmp_packet_encoding' => $metadata['xmp']['packet_encoding'] ?? null,
    'xmp_encoding_fallback' => $metadata['xmp']['encoding_fallback'] ?? false,
    'decoded_xmp_title' => ($metadata['title'] ?? null) === 'Café “Review” Packet',
    'info_author_fallback' => ($metadata['authors'] ?? []) === ['Łukasz Editor', 'Site Owner'],
    'metadata_not_visible_text' => !str_contains($plainText, 'Café') && !str_contains($plainText, 'Legacy Encoded Title'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-metadata ' . htmlspecialchars(json_encode([
    'authors' => $metadata['authors'] ?? [],
    'description' => $metadata['description'] ?? null,
    'keywords' => $metadata['keywords'] ?? [],
    'creator_tool' => $metadata['creator_tool'] ?? null,
    'producer' => $metadata['producer'] ?? null,
    'created_at' => $metadata['created_at'] ?? null,
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'xmp_packet_encoding' => $metadata['xmp']['packet_encoding'] ?? null,
    'xmp_encoding_fallback' => $metadata['xmp']['encoding_fallback'] ?? false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
