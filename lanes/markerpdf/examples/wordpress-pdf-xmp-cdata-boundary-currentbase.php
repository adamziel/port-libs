<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>CDATA Boundary Editor</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default"><![CDATA[' . $description . ']]></rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>cdata-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>CDATA Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>CDATA Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T00:42:15Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '<!-- literal </rdf:RDF> and </x:xmpmeta> markers in comments are not packet terminators -->'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$currentXmp = $xmpPacket(
    'WordPress CDATA XMP Boundary',
    'Current CDATA keeps literal </x:xmpmeta> and </rdf:RDF> text without ending the packet root.',
    '2026-06-04T20:42:15-04:00'
);
$decoyXmp = $xmpPacket(
    'Trailing CDATA Decoy Title',
    'Trailing decoy packet must not replace current CDATA root.',
    '2026-06-05T00:59:59Z'
);
$metadataBytes = $currentXmp . "\0\0 \n" . $decoyXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP CDATA boundary smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP CDATA Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Info CDATA Boundary Fallback) /Author (Info Fallback Author) /Producer (Info Fallback Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'WordPress CDATA XMP Boundary') {
    throw new RuntimeException('Expected current CDATA-bounded XMP packet title to win.');
}
if (($metadata['xmp']['packet_boundary_applied'] ?? null) !== true) {
    throw new RuntimeException('Expected XML-aware XMP packet boundary fallback to be recorded.');
}
if (!is_string($encoded) || str_contains($encoded, 'Trailing CDATA Decoy Title')) {
    throw new RuntimeException('Expected trailing decoy XMP packet to stay out of document metadata.');
}
if (
    str_contains($plainText, 'WordPress CDATA XMP Boundary')
    || str_contains($plainText, 'Trailing CDATA Decoy Title')
    || str_contains($plainText, '</x:xmpmeta>')
) {
    throw new RuntimeException('Expected XMP metadata and XML-looking CDATA markers to stay out of visible WordPress paragraphs.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-cdata-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-xml-aware-boundary',
    'native_boundary' => 'Catalog /Metadata XMP root is bounded with XML-aware CDATA/comment handling before trailing packet bytes',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_current_packet' => ($metadata['title'] ?? null) === 'WordPress CDATA XMP Boundary',
    'description_preserves_cdata_text' => str_contains((string) ($metadata['description'] ?? ''), '</x:xmpmeta>'),
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'packet_encoding' => $metadata['xmp']['packet_encoding'] ?? null,
    'decoy_xmp_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing CDATA Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'WordPress CDATA XMP Boundary')
        && !str_contains($plainText, 'Trailing CDATA Decoy Title')
        && !str_contains($plainText, '</x:xmpmeta>'),
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
    'created_at' => $metadata['created_at'] ?? null,
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $metadata['metadata_date_utc'] ?? null,
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
