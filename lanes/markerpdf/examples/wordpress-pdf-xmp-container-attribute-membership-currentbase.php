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
        . '<dc:creator><rdf:Seq>'
        . '<rdf:Description rdf:_2="Container Attribute Reviewer" rdf:_1="Container Attribute Editor" rdf:_10="Container Attribute Contributor">'
        . '<xmp:PrivateRole>container attribute role decoy</xmp:PrivateRole>'
        . '</rdf:Description>'
        . '</rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag>'
        . '<rdf:Description rdf:_2="xmp-container-attribute" rdf:_1="wordpress"><xmp:PrivateTag>container keyword decoy</xmp:PrivateTag></rdf:Description>'
        . '</rdf:Bag></dc:subject>'
        . '<pdf:Producer>Container Attribute Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Container Attribute Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T06:05:43Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about="#privateContainer">'
        . '<rdf:Seq><rdf:Description rdf:_1="External Container Attribute Decoy"/></rdf:Seq>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$pdfWithMetadata = static function (string $metadataBytes, string $dictionary, string $bodyText): string {
    $compressed = gzcompress($metadataBytes);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress XMP container attribute smoke fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< {$dictionary} /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Container Attribute Info Title) /Author (Info Container Attribute Author) /Keywords (info, fallback) /Producer (Info Container Attribute Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

$metadataBytes = $xmpPacket(
    'WordPress Container Attribute XMP Title',
    'Nested RDF container attribute membership remains ordered XMP metadata.',
    '2026-06-08T02:05:43-04:00'
) . "\0\0 \n" . $xmpPacket(
    'Trailing Container Attribute Decoy Title',
    'Trailing container attribute packet stays outside metadata.',
    '2026-06-08T06:59:59Z'
);
$pdf = $pdfWithMetadata(
    $metadataBytes,
    '/Type /Metadata /Subtype /XML',
    'XMP Container Attribute WordPress Body'
);
$rejectedPdf = $pdfWithMetadata(
    $metadataBytes,
    '/Type /EmbeddedFile /Subtype /text#2Fxml',
    'Rejected XMP Container Attribute WordPress Body'
);

$metadataExtractor = new PdfMetadataExtractor();
$textExtractor = new PdfTextExtractor();

$metadata = $metadataExtractor->extractDocumentMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$rejectedMetadata = $metadataExtractor->extractDocumentMetadata($rejectedPdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$rejectedEncoded = json_encode($rejectedMetadata, JSON_UNESCAPED_SLASHES);
$rejectedReview = $rejectedMetadata['catalog']['metadata_stream_review'] ?? [];
$summary = $rejectedReview['xmp_summary'] ?? [];

if (($metadata['authors'] ?? []) !== ['Container Attribute Editor', 'Container Attribute Reviewer', 'Container Attribute Contributor']) {
    throw new RuntimeException('Expected nested RDF container attribute membership authors to be promoted.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-container-attribute']) {
    throw new RuntimeException('Expected nested RDF container attribute membership keywords to be promoted.');
}
if (($rejectedReview['status'] ?? null) !== 'rejected_non_metadata_xml_stream') {
    throw new RuntimeException('Expected non-metadata XML stream to remain review-only.');
}
if (($summary['author_count'] ?? null) !== 3 || ($summary['keyword_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected rejected XMP summary to preserve redacted author/keyword counts.');
}
if (!is_string($encoded) || str_contains($encoded, 'container attribute role decoy') || str_contains($encoded, 'External Container Attribute Decoy')) {
    throw new RuntimeException('Private XMP container attribute values leaked into accepted metadata.');
}
if (!is_string($rejectedEncoded) || str_contains($rejectedEncoded, 'Container Attribute Editor') || str_contains($rejectedEncoded, 'Trailing Container Attribute Decoy Title')) {
    throw new RuntimeException('Rejected XMP text values leaked into review metadata.');
}
if (str_contains($plainText, 'WordPress Container Attribute XMP Title') || str_contains($plainText, 'Container Attribute Editor')) {
    throw new RuntimeException('XMP metadata leaked into visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-container-attribute-membership-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-rdf-container-attribute-membership',
    'native_boundary' => 'RDF container wrappers with rdf:_n property attributes are ordered XMP list values before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'] ?? [],
    'title' => $metadata['title'] ?? null,
    'authors' => $metadata['authors'] ?? [],
    'keywords' => $metadata['keywords'] ?? [],
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? null,
    'private_xmp_values_redacted' => is_string($encoded)
        && !str_contains($encoded, 'container attribute role decoy')
        && !str_contains($encoded, 'External Container Attribute Decoy'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'WordPress Container Attribute XMP Title')
        && !str_contains($plainText, 'Container Attribute Editor'),
    'rejected_stream_status' => $rejectedReview['status'] ?? null,
    'rejected_author_count' => $summary['author_count'] ?? null,
    'rejected_keyword_count' => $summary['keyword_count'] ?? null,
    'rejected_text_values_redacted' => $summary['text_values_redacted'] ?? null,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-container-attribute-review ' . $htmlJson([
    'authors' => $metadata['authors'] ?? [],
    'keywords' => $metadata['keywords'] ?? [],
    'creator_tool' => $metadata['creator_tool'] ?? null,
    'producer' => $metadata['producer'] ?? null,
    'rejected_summary' => $summary,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
