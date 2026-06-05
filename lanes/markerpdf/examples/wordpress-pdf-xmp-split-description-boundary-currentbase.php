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
        . '<dc:creator><rdf:Seq><rdf:li> </rdf:li></rdf:Seq></dc:creator>'
        . '<dc:subject><rdf:Bag><rdf:li> </rdf:li></rdf:Bag></dc:subject>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:creator><rdf:Seq><rdf:li>Split Description Editor</rdf:li><rdf:li>Data Liberation Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-split-description</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Split Description Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Split Description Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T12:20:09Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$currentXmp = $xmpPacket(
    'WordPress Split Description XMP Title',
    'Split XMP descriptions preserve later non-empty authors and keywords.',
    '2026-06-05T08:20:09-04:00'
);
$trailingXmp = $xmpPacket(
    'Trailing Split Description Decoy Title',
    'Trailing split-description XMP must not replace current metadata.',
    '2026-06-05T12:59:59Z'
);
$metadataBytes = $currentXmp . "\0\0 \n" . $trailingXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress split-description XMP smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Split Description Import Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Split Description Info Fallback Title) /Author (Info Split Author) /Producer (Info Split Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'WordPress Split Description XMP Title') {
    throw new RuntimeException('Expected split-description XMP title to be promoted.');
}
if (($metadata['authors'] ?? []) !== ['Split Description Editor', 'Data Liberation Team']) {
    throw new RuntimeException('Expected later non-empty XMP creator sequence to define authors.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-split-description']) {
    throw new RuntimeException('Expected later non-empty XMP subject bag to define keywords.');
}
if (!is_string($encoded) || str_contains($encoded, 'Trailing Split Description Decoy Title')) {
    throw new RuntimeException('Trailing split-description XMP decoy leaked into metadata output.');
}
if (str_contains($plainText, 'WordPress Split Description XMP Title') || str_contains($plainText, 'Trailing Split Description Decoy Title')) {
    throw new RuntimeException('XMP metadata leaked into visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-split-description-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-split-description-list-boundary',
    'native_boundary' => 'Empty XMP creator/subject lists in earlier rdf:Description nodes do not block later non-empty list metadata',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_xmp' => ($metadata['title'] ?? null) === 'WordPress Split Description XMP Title',
    'authors_from_later_description' => ($metadata['authors'] ?? []) === ['Split Description Editor', 'Data Liberation Team'],
    'keywords_from_later_description' => ($metadata['keywords'] ?? []) === ['wordpress', 'xmp-split-description'],
    'packet_boundary_applied' => ($metadata['xmp']['packet_boundary_applied'] ?? null) === true,
    'trailing_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Split Description Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'WordPress Split Description XMP Title')
        && !str_contains($plainText, 'Trailing Split Description Decoy Title'),
],) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-split-description-review ' . $htmlJson([
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
