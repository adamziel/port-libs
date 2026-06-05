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

$xmpPacket = static function (
    string $title,
    string $description,
    string $date,
    bool $includeNonTerminalInstruction = false
): string {
    $nonTerminalInstruction = $includeNonTerminalInstruction
        ? '<?xpacket id="wordpress-review-boundary" end="decoy"?>'
        : '';

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . $nonTerminalInstruction
        . '<dc:creator><rdf:Seq><rdf:li>Instruction Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-instruction-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Instruction Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Instruction Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T09:26:26Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$bareRoot = static function (string $packet): string {
    $packet = preg_replace('/^<\?xpacket\b[^?]*\?>/s', '', $packet, 1) ?? $packet;

    return preg_replace('/<\?xpacket\s+end="w"\?>$/s', '', $packet, 1) ?? $packet;
};

$staleRoot = $bareRoot($xmpPacket(
    'Stale Instruction Boundary XMP Title',
    'A stale root before the active packet must not define WordPress metadata.',
    '2026-06-05T09:20:00Z'
));
$currentXmp = $xmpPacket(
    'Current Instruction Boundary XMP Title',
    'The active packet survives an internal xpacket processing instruction.',
    '2026-06-05T05:26:26-04:00',
    true
);
$trailingXmp = $xmpPacket(
    'Trailing Instruction Boundary Decoy Title',
    'A trailing packet after the active packet must stay hidden.',
    '2026-06-05T09:40:00Z'
);
$metadataBytes = $staleRoot . "\n" . $currentXmp . "\0\0" . $trailingXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP instruction boundary smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Instruction Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Instruction Boundary Info Title) /Author (Info Instruction Author) /Producer (Info Instruction Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Current Instruction Boundary XMP Title') {
    throw new RuntimeException('Expected current active XMP packet title to win past non-terminal xpacket instructions.');
}
if (($metadata['xmp']['packet_boundary_applied'] ?? null) !== true) {
    throw new RuntimeException('Expected XMP packet boundary selection to be recorded.');
}
if (!is_string($encoded) || str_contains($encoded, 'Stale Instruction Boundary XMP Title') || str_contains($encoded, 'Trailing Instruction Boundary Decoy Title')) {
    throw new RuntimeException('Expected stale and trailing XMP roots to stay out of metadata JSON.');
}
if (str_contains($plainText, 'Current Instruction Boundary XMP Title') || str_contains($plainText, 'Stale Instruction Boundary XMP Title')) {
    throw new RuntimeException('Expected XMP packet text to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-instruction-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-packet-instruction-boundary',
    'native_boundary' => 'Only terminal xpacket end processing instructions close active document XMP packets',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_active_packet' => ($metadata['title'] ?? null) === 'Current Instruction Boundary XMP Title',
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'nonterminal_instruction_ignored' => is_string($encoded) && !str_contains($encoded, 'end="decoy"'),
    'stale_xmp_excluded' => is_string($encoded) && !str_contains($encoded, 'Stale Instruction Boundary XMP Title'),
    'trailing_xmp_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Instruction Boundary Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Current Instruction Boundary XMP Title')
        && !str_contains($plainText, 'Stale Instruction Boundary XMP Title'),
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
