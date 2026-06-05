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
        . '<dc:creator><rdf:Seq><rdf:li>Packet Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Packet Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Packet Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-04T23:35:35Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$bareXmpRoot = static function (string $packet): string {
    $packet = preg_replace('/^<\?xpacket\b[^?]*\?>/s', '', $packet, 1) ?? $packet;

    return preg_replace('/<\?xpacket\s+end="w"\?>$/s', '', $packet, 1) ?? $packet;
};

$prePacketDecoy = $bareXmpRoot($xmpPacket(
    'Pre Packet Decoy XMP Title',
    'A root before xpacket begin must not become WordPress metadata',
    '2026-06-05T07:59:59Z'
));
$currentXmp = $xmpPacket(
    'Current XPacket Root Title',
    'The begin/end packet root wins before WordPress import',
    '2026-06-05T03:45:12-04:00'
);
$decoyXmp = $xmpPacket(
    'Trailing Decoy XMP Title',
    'Trailing packet must not replace the current root',
    '2026-06-04T23:59:59Z'
);
$metadataBytes = $prePacketDecoy . "\n" . $currentXmp . "\0\0 \n" . $decoyXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP packet boundary smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Packet Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Fallback Packet Boundary Title) /Author (Fallback Author) /Producer (Fallback Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Current XPacket Root Title') {
    throw new RuntimeException('Expected current bounded XMP packet title to win before pre-packet and trailing decoys.');
}
if (($metadata['xmp']['packet_boundary_applied'] ?? null) !== true) {
    throw new RuntimeException('Expected XMP packet boundary fallback to be recorded.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, 'Pre Packet Decoy XMP Title')
    || str_contains($encoded, 'Trailing Decoy XMP Title')
) {
    throw new RuntimeException('Expected pre-packet and trailing decoy XMP roots to stay out of document metadata.');
}
if (
    str_contains($plainText, 'Current XPacket Root Title')
    || str_contains($plainText, 'Pre Packet Decoy XMP Title')
    || str_contains($plainText, 'Trailing Decoy XMP Title')
) {
    throw new RuntimeException('Expected XMP packet text to stay out of visible WordPress paragraphs.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-packet-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-packet-boundary',
    'native_boundary' => 'Catalog /Metadata XMP packet root is bounded by xpacket begin/end before pre-packet or appended decoy XMP roots',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_current_packet' => ($metadata['title'] ?? null) === 'Current XPacket Root Title',
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'packet_encoding' => $metadata['xmp']['packet_encoding'] ?? null,
    'pre_packet_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'Pre Packet Decoy XMP Title'),
    'decoy_xmp_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Decoy XMP Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Current XPacket Root Title')
        && !str_contains($plainText, 'Pre Packet Decoy XMP Title')
        && !str_contains($plainText, 'Trailing Decoy XMP Title'),
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
