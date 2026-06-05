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
        . '<dc:creator><rdf:Seq><rdf:li>Unpaired Begin Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-unpaired-begin</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Unpaired Begin Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Unpaired Begin Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T07:33:17Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$staleUnclosed = '<?xpacket begin="" id="stale-unpaired"?>'
    . $xmpRoot(
        'Stale Unpaired Begin XMP Title',
        'A stale unclosed packet must not define WordPress metadata',
        '2026-06-05T07:59:59Z'
    );
$currentXmp = '<?xpacket begin="" id="current-complete"?>'
    . $xmpRoot(
        'Current Unpaired Begin XMP Title',
        'Current complete packet wins after a stale unpaired begin',
        '2026-06-05T03:32:17-04:00'
    )
    . '<?xpacket end="w"?>';
$trailingXmp = $xmpRoot(
    'Trailing Unpaired Begin Decoy Title',
    'Trailing packet bytes stay outside the current packet boundary',
    '2026-06-05T08:59:59Z'
);
$metadataBytes = $staleUnclosed . "\n" . $currentXmp . "\0\0 " . $trailingXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP unpaired-begin boundary smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Unpaired Begin Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Unpaired Begin Info Title) /Author (Info Fallback Author) /Producer (Info Fallback Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Current Unpaired Begin XMP Title') {
    throw new RuntimeException('Expected current complete XMP packet title to win after a stale unpaired begin.');
}
if (($metadata['xmp']['packet_boundary_applied'] ?? null) !== true) {
    throw new RuntimeException('Expected XMP packet boundary fallback to be recorded.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, 'Stale Unpaired Begin XMP Title')
    || str_contains($encoded, 'Trailing Unpaired Begin Decoy Title')
) {
    throw new RuntimeException('Expected stale and trailing XMP roots to stay out of document metadata.');
}
if (
    str_contains($plainText, 'Current Unpaired Begin XMP Title')
    || str_contains($plainText, 'Stale Unpaired Begin XMP Title')
    || str_contains($plainText, 'Trailing Unpaired Begin Decoy Title')
) {
    throw new RuntimeException('Expected XMP packet text to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-unpaired-begin-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-packet-boundary',
    'native_boundary' => 'Catalog /Metadata skips stale unpaired xpacket begin markers before accepting a later complete begin/end packet',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_current_complete_packet' => ($metadata['title'] ?? null) === 'Current Unpaired Begin XMP Title',
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'packet_encoding' => $metadata['xmp']['packet_encoding'] ?? null,
    'stale_unpaired_begin_excluded' => is_string($encoded) && !str_contains($encoded, 'Stale Unpaired Begin XMP Title'),
    'trailing_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Unpaired Begin Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Current Unpaired Begin XMP Title')
        && !str_contains($plainText, 'Stale Unpaired Begin XMP Title')
        && !str_contains($plainText, 'Trailing Unpaired Begin Decoy Title'),
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
    'created_at' => $metadata['created_at'] ?? null,
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $metadata['metadata_date_utc'] ?? null,
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
