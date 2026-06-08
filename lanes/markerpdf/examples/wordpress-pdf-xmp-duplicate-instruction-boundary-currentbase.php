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
        . '<dc:creator><rdf:Seq><rdf:li>Duplicate Instruction Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-duplicate-instruction</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Duplicate Instruction Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Duplicate Instruction Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T20:49:08Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$xmpPacket = static function (string $root): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . $root
        . '<?xpacket end="w"?>';
};

$metadataBytesFor = static function (
    string $title,
    string $description,
    string $date
) use ($xmpRoot, $xmpPacket): string {
    $duplicateBeginStale = '<?xpacket begin="" begin="stale-duplicate-begin" id="duplicate-begin"?>'
        . $xmpRoot(
            'Stale Duplicate Begin XMP Title',
            'Duplicate begin pseudo-attributes must not delimit WordPress metadata.',
            '2026-06-08T20:59:59Z'
        )
        . '<?xpacket end="w"?>';
    $duplicateEndStale = '<?xpacket begin="" id="valid-stale-begin"?>'
        . $xmpRoot(
            'Stale Duplicate End XMP Title',
            'Duplicate end pseudo-attributes must not delimit WordPress metadata.',
            '2026-06-08T21:09:59Z'
        )
        . '<?xpacket end="w" end="continue"?>';
    $current = $xmpPacket($xmpRoot($title, $description, $date));
    $trailing = $xmpPacket($xmpRoot(
        'Trailing Duplicate Instruction Decoy Title',
        'A trailing valid packet must stay outside the selected packet boundary.',
        '2026-06-08T21:19:59Z'
    ));

    return $duplicateBeginStale . "\n" . $duplicateEndStale . "\n" . $current . "\0\0" . $trailing;
};

$pdfFor = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress XMP duplicate-instruction smoke fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Duplicate Instruction Info Title) /Author (Info Duplicate Instruction Author) /Producer (Info Duplicate Instruction Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

$acceptedBytes = $metadataBytesFor(
    'Current Duplicate Instruction XMP Title',
    'Only unambiguous xpacket pseudo-attributes delimit the active packet.',
    '2026-06-08T16:49:08-04:00'
);
$acceptedPdf = $pdfFor(
    $acceptedBytes,
    '/Type /Metadata /Subtype /XML',
    'XMP Duplicate Instruction Boundary Body'
);
$acceptedMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($acceptedPdf);
$acceptedPlainText = (new PdfTextExtractor())->extractPlainText($acceptedPdf);
$acceptedEncoded = json_encode($acceptedMetadata, JSON_UNESCAPED_SLASHES);

$rejectedBytes = $metadataBytesFor(
    'Rejected Duplicate Instruction XMP Title',
    'Rejected current packet is summarized but redacted.',
    '2026-06-08T20:50:08Z'
);
$rejectedPdf = $pdfFor(
    $rejectedBytes,
    '/Type /EmbeddedFile /Subtype /text#2Fxml',
    'Rejected XMP Duplicate Instruction Boundary Body'
);
$rejectedMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($rejectedPdf);
$rejectedPlainText = (new PdfTextExtractor())->extractPlainText($rejectedPdf);
$rejectedEncoded = json_encode($rejectedMetadata, JSON_UNESCAPED_SLASHES);
$review = $rejectedMetadata['catalog']['metadata_stream_review'] ?? [];
$summary = $review['xmp_summary'] ?? [];

if (($acceptedMetadata['title'] ?? null) !== 'Current Duplicate Instruction XMP Title') {
    throw new RuntimeException('Expected duplicate xpacket boundary attributes to be ignored before the current packet.');
}
if (($acceptedMetadata['xmp']['packet_boundary_applied'] ?? null) !== true) {
    throw new RuntimeException('Expected current packet boundary selection to be recorded.');
}
if (!is_string($acceptedEncoded) || str_contains($acceptedEncoded, 'Stale Duplicate Begin XMP Title')) {
    throw new RuntimeException('Expected stale duplicate-begin XMP metadata to stay out of promoted metadata.');
}
if (!is_string($acceptedEncoded) || str_contains($acceptedEncoded, 'Stale Duplicate End XMP Title')) {
    throw new RuntimeException('Expected stale duplicate-end XMP metadata to stay out of promoted metadata.');
}
if (!is_string($acceptedEncoded) || str_contains($acceptedEncoded, 'Trailing Duplicate Instruction Decoy Title')) {
    throw new RuntimeException('Expected trailing XMP packet to stay out of promoted metadata.');
}
if (str_contains($acceptedPlainText, 'Current Duplicate Instruction XMP Title') || str_contains($acceptedPlainText, 'Stale Duplicate Begin XMP Title')) {
    throw new RuntimeException('Expected XMP text to stay out of visible WordPress paragraphs.');
}
if (($review['status'] ?? null) !== 'rejected_non_metadata_xml_stream') {
    throw new RuntimeException('Expected non-Metadata XML stream to stay review-only.');
}
if (($summary['dates_utc']['created_at'] ?? null) !== '2026-06-08T20:50:08Z') {
    throw new RuntimeException('Expected rejected review summary to use the current packet date.');
}
if (!is_string($rejectedEncoded) || str_contains($rejectedEncoded, 'Rejected Duplicate Instruction XMP Title')) {
    throw new RuntimeException('Expected rejected XMP text values to be redacted from metadata output.');
}
if (str_contains($rejectedPlainText, 'Rejected Duplicate Instruction XMP Title')) {
    throw new RuntimeException('Expected rejected XMP text to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-duplicate-instruction-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-duplicate-packet-instruction-boundary',
    'native_boundary' => 'duplicate xpacket begin/end pseudo-attributes are ambiguous and ignored before WordPress metadata promotion',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $acceptedMetadata['source'],
    'title_from_current_packet' => ($acceptedMetadata['title'] ?? null) === 'Current Duplicate Instruction XMP Title',
    'packet_boundary_applied' => $acceptedMetadata['xmp']['packet_boundary_applied'] ?? false,
    'duplicate_begin_stale_packet_ignored' => is_string($acceptedEncoded) && !str_contains($acceptedEncoded, 'Stale Duplicate Begin XMP Title'),
    'duplicate_end_stale_packet_ignored' => is_string($acceptedEncoded) && !str_contains($acceptedEncoded, 'Stale Duplicate End XMP Title'),
    'trailing_xmp_excluded' => is_string($acceptedEncoded) && !str_contains($acceptedEncoded, 'Trailing Duplicate Instruction Decoy Title'),
    'rejected_review_summary_uses_current_packet' => ($summary['dates_utc']['created_at'] ?? null) === '2026-06-08T20:50:08Z',
    'rejected_text_values_redacted' => ($summary['text_values_redacted'] ?? null) === true,
    'visible_text_excludes_xmp' => !str_contains($acceptedPlainText, 'Current Duplicate Instruction XMP Title')
        && !str_contains($acceptedPlainText, 'Stale Duplicate Begin XMP Title')
        && !str_contains($acceptedPlainText, 'Stale Duplicate End XMP Title'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($acceptedMetadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-metadata ' . $htmlJson([
    'authors' => $acceptedMetadata['authors'] ?? [],
    'description' => $acceptedMetadata['description'] ?? null,
    'keywords' => $acceptedMetadata['keywords'] ?? [],
    'creator_tool' => $acceptedMetadata['creator_tool'] ?? null,
    'producer' => $acceptedMetadata['producer'] ?? null,
    'created_at_utc' => $acceptedMetadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $acceptedMetadata['metadata_date_utc'] ?? null,
    'packet_boundary_applied' => $acceptedMetadata['xmp']['packet_boundary_applied'] ?? false,
    'rejected_stream_review_status' => $review['status'] ?? null,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($acceptedPlainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
