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
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Dublin Core Date XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li>Dublin Core Date Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Dublin Core date sequences can define the current document date</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-dc-date</rdf:li></rdf:Bag></dc:subject>'
    . '<dc:date><rdf:Seq><rdf:li>2026-06-07T09:34:56-08:00</rdf:li><rdf:li>2026-06-08T10:00:00Z</rdf:li></rdf:Seq></dc:date>'
    . '<pdf:Producer>Dublin Core Date Producer</pdf:Producer>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$decoyXmp = str_replace(
    ['Current Dublin Core Date XMP Title', '2026-06-07T09:34:56-08:00'],
    ['Trailing Dublin Core Date Decoy Title', '2026-06-09T10:00:00Z'],
    $xmp
);
$metadataBytes = $xmp . "\0\0 \n" . $decoyXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP Dublin Core date smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Dublin Core Date Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Dublin Core Date Info Title) /Author (Info Date Author) /CreationDate (D:20240101000000Z) /Producer (Info Date Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['created_at'] ?? null) !== '2026-06-07T09:34:56-08:00') {
    throw new RuntimeException('Expected XMP Dublin Core date to win before stale Info CreationDate.');
}
if (($metadata['created_at_utc'] ?? null) !== '2026-06-07T17:34:56Z') {
    throw new RuntimeException('Expected XMP Dublin Core date to normalize to UTC.');
}
if (($metadata['xmp_dublin_core']['date_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected Dublin Core date review metadata to preserve the sequence count.');
}
if (!is_string($encoded) || str_contains($encoded, 'Trailing Dublin Core Date Decoy Title')) {
    throw new RuntimeException('Trailing XMP packet leaked into document metadata.');
}
if (str_contains($plainText, 'Current Dublin Core Date XMP Title') || str_contains($plainText, 'Trailing Dublin Core Date Decoy Title')) {
    throw new RuntimeException('XMP metadata leaked into visible WordPress paragraph text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-dublin-core-date-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-dublin-core-date-boundary',
    'native_boundary' => 'XMP dc:date sequence supplies document created_at when xmp:CreateDate is absent',
    'source' => $metadata['source'] ?? [],
    'xmp_dc_date_promoted' => ($metadata['created_at'] ?? null) === '2026-06-07T09:34:56-08:00',
    'xmp_dc_date_utc_normalized' => ($metadata['created_at_utc'] ?? null) === '2026-06-07T17:34:56Z',
    'stale_info_creation_date_not_promoted' => ($metadata['created_at'] ?? null) !== ($metadata['info']['CreationDate'] ?? null),
    'dublin_core_dates_preserved' => ($metadata['xmp_dublin_core']['dates'] ?? []) === [
        '2026-06-07T09:34:56-08:00',
        '2026-06-08T10:00:00Z',
    ],
    'trailing_packet_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Dublin Core Date Decoy Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Current Dublin Core Date XMP Title')
        && !str_contains($plainText, 'Trailing Dublin Core Date Decoy Title'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-dublin-core-date-review ' . $htmlJson([
    'created_at' => $metadata['created_at'] ?? null,
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'dublin_core_date_count' => $metadata['xmp_dublin_core']['date_count'] ?? null,
    'dublin_core_dates_utc' => $metadata['xmp_dublin_core']['dates_utc'] ?? [],
]) . " -->\n";
