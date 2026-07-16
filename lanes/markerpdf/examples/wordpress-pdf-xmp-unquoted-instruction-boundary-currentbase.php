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
        . '<dc:creator><rdf:Seq><rdf:li>WordPress Unquoted Instruction Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-unquoted-instruction</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>WordPress Unquoted Instruction Producer</pdf:Producer>'
        . '<xmp:CreatorTool>WordPress Unquoted Instruction Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T07:02:31Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$xmpPacket = static function (string $root): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . $root
        . '<?xpacket end="w"?>';
};

$metadataBytes = '<?xpacket begin=unquoted id=stale-unquoted?>'
    . $xmpRoot(
        'Stale Unquoted Instruction XMP Title',
        'Unquoted xpacket pseudo-attributes must not delimit document metadata.',
        '2026-06-08T07:59:59Z'
    )
    . '<?xpacket end=w?>'
    . "\n"
    . $xmpPacket($xmpRoot(
        'WordPress Current Quoted Instruction XMP Title',
        'Only quoted xpacket pseudo-attributes delimit the active WordPress import packet.',
        '2026-06-08T03:02:31-04:00'
    ))
    . "\0\0\n"
    . $xmpPacket($xmpRoot(
        'Trailing Quoted Instruction Decoy Title',
        'A later valid packet must not replace the current quoted packet.',
        '2026-06-08T08:59:59Z'
    ));

$makePdf = static function (string $metadataDictionary, string $bodyText) use ($metadataBytes): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress unquoted-instruction XMP smoke metadata.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Unquoted Instruction Info Title) /Author (Info Unquoted Instruction Author) /Producer (Info Unquoted Instruction Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

$documentPdf = $makePdf('/Type /Metadata /Subtype /XML', 'XMP Unquoted Instruction Boundary Body');
$documentMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($documentPdf);
$documentText = (new PdfTextExtractor())->extractPlainText($documentPdf);
$documentEncoded = json_encode($documentMetadata, JSON_UNESCAPED_SLASHES) ?: '';

if (($documentMetadata['title'] ?? null) !== 'WordPress Current Quoted Instruction XMP Title') {
    throw new RuntimeException('Expected quoted xpacket instruction to select current WordPress XMP metadata.');
}
if (($documentMetadata['xmp']['packet_boundary_applied'] ?? null) !== true) {
    throw new RuntimeException('Expected XMP packet boundary selection to be recorded.');
}
if (str_contains($documentEncoded, 'Stale Unquoted Instruction XMP Title')
    || str_contains($documentEncoded, 'Trailing Quoted Instruction Decoy Title')
) {
    throw new RuntimeException('Expected unquoted and trailing XMP packets to stay out of document metadata.');
}
if (str_contains($documentText, 'WordPress Current Quoted Instruction XMP Title')
    || str_contains($documentText, 'Stale Unquoted Instruction XMP Title')
) {
    throw new RuntimeException('Expected XMP metadata values to stay out of visible WordPress paragraphs.');
}

$rejectedPdf = $makePdf('/Type /EmbeddedFile /Subtype /text#2Fxml', 'Rejected XMP Unquoted Instruction Boundary Body');
$rejectedMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($rejectedPdf);
$rejectedReview = $rejectedMetadata['catalog']['metadata_stream_review'] ?? [];
$rejectedSummary = $rejectedReview['xmp_summary'] ?? [];

if (($rejectedReview['status'] ?? null) !== 'rejected_non_metadata_xml_stream') {
    throw new RuntimeException('Expected non-metadata XML stream to be summarized as rejected.');
}
if (($rejectedSummary['dates_utc']['created_at'] ?? null) !== '2026-06-08T07:02:31Z') {
    throw new RuntimeException('Expected rejected XML summary to use the current quoted packet boundary.');
}
if (($rejectedSummary['text_values_redacted'] ?? null) !== true) {
    throw new RuntimeException('Expected rejected XML summary text values to stay redacted.');
}

echo '<!-- markerpdf-pdf-xmp-unquoted-instruction-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-xmp-unquoted-instruction-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-packet-instruction-boundary',
    'native_boundary' => 'Unquoted xpacket pseudo-attributes are ignored before selecting the current quoted packet',
    'source' => $documentMetadata['source'] ?? [],
    'title' => $documentMetadata['title'] ?? null,
    'description' => $documentMetadata['description'] ?? null,
    'authors' => $documentMetadata['authors'] ?? [],
    'keywords' => $documentMetadata['keywords'] ?? [],
    'created_at_utc' => $documentMetadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $documentMetadata['metadata_date_utc'] ?? null,
    'packet_boundary_applied' => $documentMetadata['xmp']['packet_boundary_applied'] ?? false,
    'unquoted_packet_ignored' => !str_contains($documentEncoded, 'Stale Unquoted Instruction XMP Title'),
    'trailing_packet_excluded' => !str_contains($documentEncoded, 'Trailing Quoted Instruction Decoy Title'),
    'visible_text_excludes_xmp_metadata' => !str_contains($documentText, 'WordPress Current Quoted Instruction XMP Title'),
    'rejected_xml_summary_status' => $rejectedReview['status'] ?? null,
    'rejected_xml_summary_redacted' => $rejectedSummary['text_values_redacted'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($documentMetadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-metadata ' . $htmlJson([
    'authors' => $documentMetadata['authors'] ?? [],
    'description' => $documentMetadata['description'] ?? null,
    'keywords' => $documentMetadata['keywords'] ?? [],
    'creator_tool' => $documentMetadata['creator_tool'] ?? null,
    'producer' => $documentMetadata['producer'] ?? null,
    'created_at_utc' => $documentMetadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $documentMetadata['metadata_date_utc'] ?? null,
    'packet_boundary_applied' => $documentMetadata['xmp']['packet_boundary_applied'] ?? false,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($documentText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
