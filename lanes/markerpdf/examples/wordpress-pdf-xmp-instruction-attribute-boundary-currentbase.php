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

$xmpRoot = static function (string $title, string $description, string $date): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Instruction Attribute Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-instruction-attribute</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Instruction Attribute Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Instruction Attribute Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T13:49:54Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$xmpPacket = static function (string $root): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . $root
        . '<?xpacket end="w"?>';
};

$fakeDelimitedStale = '<?xpacket id="not-a-delimiter begin=\'\'"?>'
    . $xmpRoot(
        'Stale Instruction Attribute XMP Title',
        'Quoted begin and end text in unrelated xpacket attributes must not define WordPress metadata.',
        '2026-06-06T13:59:59Z'
    )
    . '<?xpacket id="not-a-delimiter end=\'w\'"?>';
$currentXmp = $xmpPacket($xmpRoot(
    'Current Instruction Attribute XMP Title',
    'Only real xpacket pseudo-attributes delimit the active packet.',
    '2026-06-06T09:49:54-04:00'
));
$trailingXmp = $xmpPacket($xmpRoot(
    'Trailing Instruction Attribute Decoy Title',
    'A trailing valid packet must stay outside the selected packet boundary.',
    '2026-06-06T14:59:59Z'
));
$metadataBytes = $fakeDelimitedStale . "\n" . $currentXmp . "\0\0" . $trailingXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP instruction-attribute smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Instruction Attribute Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Instruction Attribute Info Title) /Author (Info Instruction Attribute Author) /Producer (Info Instruction Attribute Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Current Instruction Attribute XMP Title') {
    throw new RuntimeException('Expected real xpacket begin/end attributes to select the current XMP packet.');
}
if (($metadata['xmp']['packet_boundary_applied'] ?? null) !== true) {
    throw new RuntimeException('Expected packet boundary selection to be recorded.');
}
if (!is_string($encoded) || str_contains($encoded, 'Stale Instruction Attribute XMP Title')) {
    throw new RuntimeException('Expected quoted fake xpacket attributes to stay out of promoted metadata.');
}
if (!is_string($encoded) || str_contains($encoded, 'Trailing Instruction Attribute Decoy Title')) {
    throw new RuntimeException('Expected trailing XMP packet to stay out of promoted metadata.');
}
if (str_contains($plainText, 'Current Instruction Attribute XMP Title') || str_contains($plainText, 'Stale Instruction Attribute XMP Title')) {
    throw new RuntimeException('Expected XMP text to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-instruction-attribute-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-packet-instruction-attribute-boundary',
    'native_boundary' => 'Only xpacket pseudo-attributes delimit XMP packets; quoted attribute values are ignored',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_active_packet' => ($metadata['title'] ?? null) === 'Current Instruction Attribute XMP Title',
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'quoted_begin_text_ignored' => is_string($encoded) && !str_contains($encoded, 'Stale Instruction Attribute XMP Title'),
    'quoted_end_text_ignored' => is_string($encoded) && !str_contains($encoded, 'Stale Instruction Attribute XMP Title'),
    'trailing_xmp_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Instruction Attribute Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Current Instruction Attribute XMP Title')
        && !str_contains($plainText, 'Stale Instruction Attribute XMP Title'),
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
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
