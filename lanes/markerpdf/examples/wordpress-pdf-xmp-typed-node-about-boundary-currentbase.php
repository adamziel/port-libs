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

$xmpPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<xmp:PrivateReview'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
        . ' xmp:CreatorTool="Private WordPress Blank Typed Tool">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Private WordPress Blank Typed Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Private WordPress Blank Typed Author</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:subject><rdf:Bag><rdf:li>private-wordpress-typed</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Private WordPress Blank Typed Producer</pdf:Producer>'
        . '<xmp:CreateDate>2026-06-06T01:01:01Z</xmp:CreateDate>'
        . '</xmp:PrivateReview>'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Typed About WordPress Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-typed-about</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>WordPress Typed About Producer</pdf:Producer>'
        . '<xmp:CreatorTool>WordPress Typed About Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T09:16:19Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$pdfForMetadata = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $metadataStream = gzcompress($metadataBytes);
    if (!is_string($metadataStream)) {
        throw new RuntimeException('Unable to compress WordPress typed-node rdf:about boundary XMP.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($metadataStream) . " >>\nstream\n{$metadataStream}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Typed About Info Fallback Title) /Author (Typed About Info Author) /Producer (Typed About Info Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

$currentXmp = $xmpPacket(
    'WordPress Typed About XMP Title',
    'Anonymous typed XMP resources stay out of WordPress document metadata',
    '2026-06-06T05:16:19-04:00'
);
$trailingDecoy = $xmpPacket(
    'Trailing WordPress Typed About Decoy Title',
    'Trailing typed-about packet stays hidden',
    '2026-06-06T09:59:59Z'
);
$metadataBytes = $currentXmp . "\0\0 \n" . $trailingDecoy;

$acceptedPdf = $pdfForMetadata(
    $metadataBytes,
    '/Type /Metadata /Subtype /XML',
    'WordPress typed-about XMP import body'
);
$rejectedPdf = $pdfForMetadata(
    $metadataBytes,
    '/Type /EmbeddedFile /Subtype /text#2Fxml',
    'Rejected WordPress typed-about XMP import body'
);

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($acceptedPdf);
$rejectedMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($rejectedPdf);
$plainText = (new PdfTextExtractor())->extractPlainText($acceptedPdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$rejectedEncoded = json_encode($rejectedMetadata, JSON_UNESCAPED_SLASHES);
$summary = $rejectedMetadata['catalog']['metadata_stream_review']['xmp_summary'] ?? [];

if (($metadata['title'] ?? null) !== 'WordPress Typed About XMP Title') {
    throw new RuntimeException('Expected document rdf:about="" title to win over anonymous typed resource.');
}
if (($metadata['creator_tool'] ?? null) !== 'WordPress Typed About Tool') {
    throw new RuntimeException('Expected document creator tool to win over anonymous typed resource.');
}
if (($metadata['authors'] ?? []) !== ['Typed About WordPress Editor', 'Import Review Team']) {
    throw new RuntimeException('Expected document authors to win over anonymous typed resource.');
}
if (($summary['author_count'] ?? null) !== 2 || ($summary['dates_utc']['created_at'] ?? null) !== '2026-06-06T09:16:19Z') {
    throw new RuntimeException('Expected rejected-stream summary to ignore anonymous typed resource values.');
}
if (!is_string($encoded)
    || str_contains($encoded, 'Private WordPress Blank Typed Title')
    || str_contains($encoded, 'Private WordPress Blank Typed Author')
    || str_contains($encoded, 'Trailing WordPress Typed About Decoy Title')
    || !is_string($rejectedEncoded)
    || str_contains($rejectedEncoded, 'Private WordPress Blank Typed Title')
    || str_contains($rejectedEncoded, '2026-06-06T01:01:01Z')
) {
    throw new RuntimeException('Anonymous typed XMP resource values leaked into WordPress metadata review.');
}
if (str_contains($plainText, 'WordPress Typed About XMP Title')
    || str_contains($plainText, 'Private WordPress Blank Typed Title')
    || str_contains($plainText, 'Trailing WordPress Typed About Decoy Title')
) {
    throw new RuntimeException('XMP values leaked into visible WordPress paragraph text.');
}

echo '<!-- markerpdf-pdf-xmp-typed-node-about-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-xmp-typed-node-about-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-metadata-parser',
    'native_boundary' => 'anonymous top-level typed RDF nodes are blank resources; explicit document rdf:about nodes win metadata and rejected-stream summaries',
    'source' => $metadata['source'],
    'title_from_document_xmp' => ($metadata['title'] ?? null) === 'WordPress Typed About XMP Title',
    'authors_from_document_xmp' => ($metadata['authors'] ?? []) === ['Typed About WordPress Editor', 'Import Review Team'],
    'creator_tool_from_document_xmp' => ($metadata['creator_tool'] ?? null) === 'WordPress Typed About Tool',
    'anonymous_typed_resource_excluded' => is_string($encoded)
        && !str_contains($encoded, 'Private WordPress Blank Typed Title')
        && !str_contains($encoded, 'Private WordPress Blank Typed Author'),
    'rejected_summary_uses_document_resource' => ($summary['author_count'] ?? null) === 2
        && ($summary['dates_utc']['created_at'] ?? null) === '2026-06-06T09:16:19Z',
    'trailing_packet_excluded' => is_string($encoded)
        && !str_contains($encoded, 'Trailing WordPress Typed About Decoy Title'),
    'visible_text_excludes_xmp' => $plainText === 'WordPress typed-about XMP import body',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
