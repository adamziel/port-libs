<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (
    string $title,
    string $description,
    string $date,
    bool $includeInternalBegin = false
): string {
    $internalBegin = $includeInternalBegin
        ? '<?xpacket begin="inline-decoy" id="not-a-packet-boundary"?>'
        : '';

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . $internalBegin
        . '<dc:creator><rdf:Seq><rdf:li>Internal Begin Instruction Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-internal-begin</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Internal Begin Instruction Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Internal Begin Instruction Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T16:02:18Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$currentXmp = $xmpPacket(
    'Current Internal Begin XMP Title',
    'The active packet survives an internal xpacket begin instruction.',
    '2026-06-05T12:02:18-04:00',
    true
);
$decoyXmp = $xmpPacket(
    'Trailing Internal Begin Decoy XMP Title',
    'A trailing packet must not replace the active packet after an internal begin instruction.',
    '2026-06-05T16:59:59Z'
);
$metadataBytes = $currentXmp . "\0\0\n" . $decoyXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP internal-begin boundary smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Internal Begin Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Internal Begin Info Title) /Author (Info Internal Begin Author) /Producer (Info Internal Begin Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Current Internal Begin XMP Title') {
    throw new RuntimeException('Expected current XMP packet title to survive an internal xpacket begin instruction.');
}
if (($metadata['xmp']['packet_boundary_applied'] ?? null) !== true) {
    throw new RuntimeException('Expected XMP packet-boundary parsing to be recorded.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, 'Trailing Internal Begin Decoy XMP Title')
    || str_contains($encoded, 'not-a-packet-boundary')
) {
    throw new RuntimeException('Expected trailing decoy and internal xpacket begin instruction bytes to stay out of metadata output.');
}
if (
    str_contains($plainText, 'Current Internal Begin XMP Title')
    || str_contains($plainText, 'Trailing Internal Begin Decoy XMP Title')
    || str_contains($plainText, 'not-a-packet-boundary')
) {
    throw new RuntimeException('Expected XMP packet text to stay out of visible WordPress paragraphs.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-internal-begin-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-packet-boundary',
    'native_boundary' => 'Nested xpacket begin processing instructions inside an active XMP root are metadata content, not packet restarts',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_current_packet' => ($metadata['title'] ?? null) === 'Current Internal Begin XMP Title',
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'packet_encoding' => $metadata['xmp']['packet_encoding'] ?? null,
    'internal_begin_instruction_ignored' => is_string($encoded) && !str_contains($encoded, 'not-a-packet-boundary'),
    'trailing_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Internal Begin Decoy XMP Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Current Internal Begin XMP Title')
        && !str_contains($plainText, 'Trailing Internal Begin Decoy XMP Title')
        && !str_contains($plainText, 'not-a-packet-boundary'),
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
