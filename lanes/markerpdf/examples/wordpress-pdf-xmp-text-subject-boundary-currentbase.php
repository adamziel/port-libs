<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title>WordPress Text Subject XMP Title</dc:title>'
    . '<dc:creator>Doe, Jane</dc:creator>'
    . '<dc:description>Simple text dc:subject values stay useful for import filters</dc:description>'
    . '<dc:subject>wordpress, xmp-text-subject; import-review</dc:subject>'
    . '<pdf:Producer>Text Subject Boundary Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Text Subject Boundary Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-05T08:58:30-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-05T12:58:30Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$trailingDecoy = str_replace(
    ['WordPress Text Subject XMP Title', 'wordpress, xmp-text-subject; import-review'],
    ['Trailing Text Subject XMP Decoy Title', 'decoy, should-not-leak'],
    $xmp
);
$metadataBytes = $xmp . "\0\0 " . $trailingDecoy;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP text-subject boundary smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Text Subject Boundary Import Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Text Subject Info Title) /Author (Info Subject Author) /Producer (Info Subject Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'WordPress Text Subject XMP Title') {
    throw new RuntimeException('Expected simple text XMP title to be promoted.');
}
if (($metadata['authors'] ?? []) !== ['Doe, Jane']) {
    throw new RuntimeException('Expected comma-bearing simple text creator to remain one author.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-text-subject', 'import-review']) {
    throw new RuntimeException('Expected simple text dc:subject keywords to split for review filters.');
}
if (!is_string($encoded) || str_contains($encoded, 'Trailing Text Subject XMP Decoy Title') || str_contains($encoded, 'should-not-leak')) {
    throw new RuntimeException('Trailing simple text XMP decoy leaked into metadata output.');
}
if (str_contains($plainText, 'WordPress Text Subject XMP Title') || str_contains($plainText, 'Trailing Text Subject XMP Decoy Title')) {
    throw new RuntimeException('XMP metadata leaked into visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-text-subject-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-text-subject-keywords',
    'native_boundary' => 'Simple text XMP dc:subject values use keyword splitting while dc:creator remains a literal author value before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_xmp_text' => ($metadata['title'] ?? null) === 'WordPress Text Subject XMP Title',
    'creator_comma_preserved' => ($metadata['authors'] ?? []) === ['Doe, Jane'],
    'subject_keywords_split' => ($metadata['keywords'] ?? []) === ['wordpress', 'xmp-text-subject', 'import-review'],
    'packet_boundary_applied' => ($metadata['xmp']['packet_boundary_applied'] ?? null) === true,
    'trailing_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Text Subject XMP Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'WordPress Text Subject XMP Title')
        && !str_contains($plainText, 'Trailing Text Subject XMP Decoy Title'),
],) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-text-subject-review ' . $htmlJson([
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
