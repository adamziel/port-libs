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
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
        . ' dc:title="' . htmlspecialchars($title, ENT_XML1) . '"'
        . ' dc:creator="Doe, Jane"'
        . ' dc:description="' . htmlspecialchars($description, ENT_XML1) . '"'
        . ' dc:subject="wordpress, xmp-attribute; compact-rdf"'
        . ' pdf:Producer="Attribute Boundary Producer"'
        . ' xmp:CreatorTool="Attribute Boundary Tool"'
        . ' xmp:CreateDate="' . htmlspecialchars($date, ENT_XML1) . '"'
        . ' xmp:MetadataDate="2026-06-05T11:46:50Z"/>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$currentXmp = $xmpPacket(
    'WordPress Attribute XMP Title',
    'Compact RDF attribute metadata remains safe for import review',
    '2026-06-05T07:46:50-04:00'
);
$trailingXmp = $xmpPacket(
    'Trailing Attribute XMP Decoy Title',
    'Trailing compact RDF XMP must not replace current metadata',
    '2026-06-05T11:59:59Z'
);
$metadataBytes = $currentXmp . "\0\0 \n" . $trailingXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP attribute boundary smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Attribute Boundary Import Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Attribute Info Fallback Title) /Author (Info Attribute Author) /Producer (Info Attribute Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'WordPress Attribute XMP Title') {
    throw new RuntimeException('Expected compact RDF XMP title to be promoted.');
}
if (($metadata['authors'] ?? []) !== ['Doe, Jane']) {
    throw new RuntimeException('Expected comma-bearing compact RDF creator to remain one author.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-attribute', 'compact-rdf']) {
    throw new RuntimeException('Expected compact RDF subject keywords to split for review filters.');
}
if (!is_string($encoded) || str_contains($encoded, 'Trailing Attribute XMP Decoy Title')) {
    throw new RuntimeException('Trailing compact RDF XMP decoy leaked into metadata output.');
}
if (str_contains($plainText, 'WordPress Attribute XMP Title') || str_contains($plainText, 'Trailing Attribute XMP Decoy Title')) {
    throw new RuntimeException('XMP metadata leaked into visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-attribute-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-compact-rdf-attributes',
    'native_boundary' => 'Compact RDF XMP attributes are document metadata; dc:creator literal commas are not author separators',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_xmp_attribute' => ($metadata['title'] ?? null) === 'WordPress Attribute XMP Title',
    'creator_comma_preserved' => ($metadata['authors'] ?? []) === ['Doe, Jane'],
    'subject_keywords_split' => ($metadata['keywords'] ?? []) === ['wordpress', 'xmp-attribute', 'compact-rdf'],
    'packet_boundary_applied' => ($metadata['xmp']['packet_boundary_applied'] ?? null) === true,
    'trailing_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Attribute XMP Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'WordPress Attribute XMP Title')
        && !str_contains($plainText, 'Trailing Attribute XMP Decoy Title'),
],) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-attribute-review ' . $htmlJson([
    'authors' => $metadata['authors'] ?? [],
    'keywords' => $metadata['keywords'] ?? [],
    'creator_tool' => $metadata['creator_tool'] ?? null,
    'producer' => $metadata['producer'] ?? null,
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $metadata['metadata_date_utc'] ?? null,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
