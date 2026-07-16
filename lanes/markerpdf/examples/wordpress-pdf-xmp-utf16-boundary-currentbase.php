<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (string $title, string $description, string $date): string {
    return '<?xml version="1.0" encoding="UTF-16BE"?>'
        . '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>UTF16 Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>utf16-xmp-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>UTF16 Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>UTF16 Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T01:15:34Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$toUtf16Be = static function (string $xml): string {
    $bytes = iconv('UTF-8', 'UTF-16BE', $xml);
    if (!is_string($bytes)) {
        throw new RuntimeException('Unable to encode XMP UTF-16 boundary smoke fixture.');
    }

    return $bytes;
};

$currentXmp = $toUtf16Be($xmpPacket(
    'Current UTF16BE XMP Title',
    'BOM-less UTF-16BE root survives trailing packet bytes',
    '2026-06-04T21:15:34-04:00'
));
$decoyXmp = $toUtf16Be($xmpPacket(
    'Trailing UTF16BE Decoy Title',
    'Trailing UTF-16BE decoy packet must not replace the current root',
    '2026-06-05T01:59:59Z'
));
$metadataBytes = $currentXmp . "\0\0" . $decoyXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP UTF-16 boundary smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (UTF16 XMP Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (UTF16 Boundary Info Title) /Author (Info Fallback Author) /Producer (Info Fallback Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Current UTF16BE XMP Title') {
    throw new RuntimeException('Expected current BOM-less UTF-16BE XMP title to win.');
}
if (($metadata['xmp']['packet_encoding'] ?? null) !== 'UTF-16BE') {
    throw new RuntimeException('Expected packet encoding provenance to report UTF-16BE.');
}
if (($metadata['xmp']['decoded_to_utf8'] ?? null) !== true) {
    throw new RuntimeException('Expected BOM-less UTF-16BE XMP packet to be decoded to UTF-8.');
}
if (($metadata['xmp']['packet_boundary_applied'] ?? null) !== true) {
    throw new RuntimeException('Expected UTF-16 XMP packet boundary to be applied.');
}
if (!is_string($encoded) || str_contains($encoded, 'Trailing UTF16BE Decoy Title')) {
    throw new RuntimeException('Expected trailing UTF-16 XMP decoy packet to stay out of document metadata.');
}
if (str_contains($plainText, 'Current UTF16BE XMP Title') || str_contains($plainText, 'Trailing UTF16BE Decoy Title')) {
    throw new RuntimeException('Expected UTF-16 XMP packet text to stay out of visible WordPress paragraphs.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-utf16-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-bomless-utf16-boundary',
    'native_boundary' => 'Catalog /Metadata XMP root is sniffed as BOM-less UTF-16, decoded, and bounded before trailing packet bytes',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_current_packet' => ($metadata['title'] ?? null) === 'Current UTF16BE XMP Title',
    'packet_encoding' => $metadata['xmp']['packet_encoding'] ?? null,
    'decoded_to_utf8' => $metadata['xmp']['decoded_to_utf8'] ?? false,
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'decoy_xmp_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing UTF16BE Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Current UTF16BE XMP Title')
        && !str_contains($plainText, 'Trailing UTF16BE Decoy Title'),
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
    'packet_encoding' => $metadata['xmp']['packet_encoding'] ?? null,
    'decoded_to_utf8' => $metadata['xmp']['decoded_to_utf8'] ?? false,
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
