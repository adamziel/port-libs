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
        . '<rdf:Description rdf:resource="#privateDescription"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Private WordPress Resource Description Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Private WordPress Resource Author</rdf:li></rdf:Seq></dc:creator>'
        . '<pdf:Producer>Private WordPress Resource Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Private WordPress Resource Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>2026-06-06T01:13:55Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Description Resource WordPress Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-description-resource</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>WordPress Description Resource Producer</pdf:Producer>'
        . '<xmp:CreatorTool>WordPress Description Resource Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T19:13:55Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about="#privateDescription" rdf:value="Private WordPress reference target scalar"/>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$pdfForMetadata = static function (string $metadataBytes, string $metadataDictionary, string $bodyText): string {
    $metadataStream = gzcompress($metadataBytes);
    if (!is_string($metadataStream)) {
        throw new RuntimeException('Unable to compress WordPress XMP description-resource boundary stream.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$metadataDictionary} /Filter /FlateDecode /Length " . strlen($metadataStream) . " >>\nstream\n{$metadataStream}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Description Resource Info Fallback Title) /Author (Description Resource Info Author) /Producer (Description Resource Info Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

$currentXmp = $xmpPacket(
    'WordPress Description Resource XMP Title',
    'Private RDF resource descriptions stay out of WordPress document metadata',
    '2026-06-06T15:13:55-04:00'
);
$trailingDecoy = $xmpPacket(
    'Trailing WordPress Description Resource Decoy Title',
    'Trailing description-resource packet stays hidden',
    '2026-06-06T19:59:59Z'
);
$metadataBytes = $currentXmp . "\0\0 \n" . $trailingDecoy;

$acceptedPdf = $pdfForMetadata(
    $metadataBytes,
    '/Type /Metadata /Subtype /XML',
    'WordPress description-resource XMP import body'
);
$rejectedPdf = $pdfForMetadata(
    $metadataBytes,
    '/Type /EmbeddedFile /Subtype /text#2Fxml',
    'Rejected WordPress description-resource XMP import body'
);

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($acceptedPdf);
$rejectedMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($rejectedPdf);
$plainText = (new PdfTextExtractor())->extractPlainText($acceptedPdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$rejectedEncoded = json_encode($rejectedMetadata, JSON_UNESCAPED_SLASHES);
$summary = $rejectedMetadata['catalog']['metadata_stream_review']['xmp_summary'] ?? [];

if (($metadata['title'] ?? null) !== 'WordPress Description Resource XMP Title') {
    throw new RuntimeException('Expected document rdf:about="" title to win over resource description.');
}
if (($metadata['creator_tool'] ?? null) !== 'WordPress Description Resource Tool') {
    throw new RuntimeException('Expected document creator tool to win over resource description.');
}
if (($metadata['authors'] ?? []) !== ['Description Resource WordPress Editor', 'Import Review Team']) {
    throw new RuntimeException('Expected document authors to win over resource description.');
}
if (($summary['author_count'] ?? null) !== 2 || ($summary['dates_utc']['created_at'] ?? null) !== '2026-06-06T19:13:55Z') {
    throw new RuntimeException('Expected rejected-stream summary to ignore private resource description values.');
}
if (!is_string($encoded)
    || str_contains($encoded, 'Private WordPress Resource Description Title')
    || str_contains($encoded, 'Private WordPress Resource Author')
    || str_contains($encoded, 'Private WordPress reference target scalar')
    || str_contains($encoded, 'Trailing WordPress Description Resource Decoy Title')
    || !is_string($rejectedEncoded)
    || str_contains($rejectedEncoded, 'Private WordPress Resource Description Title')
    || str_contains($rejectedEncoded, 'Private WordPress reference target scalar')
) {
    throw new RuntimeException('Private RDF resource description values leaked into WordPress metadata review.');
}
if (str_contains($plainText, 'WordPress Description Resource XMP Title')
    || str_contains($plainText, 'Private WordPress Resource Description Title')
    || str_contains($plainText, 'Trailing WordPress Description Resource Decoy Title')
) {
    throw new RuntimeException('XMP values leaked into visible WordPress paragraph text.');
}

echo '<!-- markerpdf-pdf-xmp-description-resource-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-xmp-description-resource-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-metadata-parser',
    'native_boundary' => 'top-level RDF resource descriptions are review-only; document rdf:about XMP metadata wins',
    'source' => $metadata['source'],
    'title_from_document_xmp' => ($metadata['title'] ?? null) === 'WordPress Description Resource XMP Title',
    'authors_from_document_xmp' => ($metadata['authors'] ?? []) === ['Description Resource WordPress Editor', 'Import Review Team'],
    'creator_tool_from_document_xmp' => ($metadata['creator_tool'] ?? null) === 'WordPress Description Resource Tool',
    'resource_description_excluded' => is_string($encoded)
        && !str_contains($encoded, 'Private WordPress Resource Description Title')
        && !str_contains($encoded, 'Private WordPress Resource Author'),
    'resource_target_scalar_excluded' => is_string($encoded)
        && !str_contains($encoded, 'Private WordPress reference target scalar'),
    'rejected_summary_uses_document_description' => ($summary['author_count'] ?? null) === 2
        && ($summary['dates_utc']['created_at'] ?? null) === '2026-06-06T19:13:55Z',
    'trailing_packet_excluded' => is_string($encoded)
        && !str_contains($encoded, 'Trailing WordPress Description Resource Decoy Title'),
    'visible_text_excludes_xmp' => $plainText === 'WordPress description-resource XMP import body',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
