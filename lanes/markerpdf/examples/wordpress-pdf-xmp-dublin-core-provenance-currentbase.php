<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmpPacket = static function (
    string $title,
    string $identifier = 'doi:10.5555/markerpdf.dc.provenance'
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="#private-provenance-decoy"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' dc:identifier="private-doi-decoy"'
        . ' dc:publisher="Private Publisher Decoy"/>'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Dublin Core Provenance Editor</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Dublin Core provenance stays metadata before WordPress import.</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-dublin-core-provenance</rdf:li></rdf:Bag></dc:subject>'
        . '<dc:identifier><rdf:Bag><rdf:li>' . htmlspecialchars($identifier, ENT_XML1) . '</rdf:li><rdf:li>urn:uuid:markerpdf-provenance</rdf:li></rdf:Bag></dc:identifier>'
        . '<dc:publisher><rdf:Seq><rdf:li>WordPress Data Liberation Press</rdf:li></rdf:Seq></dc:publisher>'
        . '<dc:contributor><rdf:Seq><rdf:li>Metadata Reviewer</rdf:li><rdf:li>Import Curator</rdf:li></rdf:Seq></dc:contributor>'
        . '<dc:relation><rdf:Bag><rdf:li>isPartOf:Migration Packet 7</rdf:li><rdf:li>hasFormat:text/markdown</rdf:li></rdf:Bag></dc:relation>'
        . '<dc:source>source-report.pdf</dc:source>'
        . '<dc:type><rdf:Bag><rdf:li>Text</rdf:li><rdf:li>Dataset</rdf:li></rdf:Bag></dc:type>'
        . '<dc:coverage>Customer migration archive 2026</dc:coverage>'
        . '<pdf:Producer>Dublin Core Provenance Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Dublin Core Provenance Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>2026-06-08T03:31:40-04:00</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T07:31:40Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$currentXmp = $xmpPacket('WordPress Dublin Core Provenance XMP Title');
$trailingXmp = $xmpPacket('Trailing Dublin Core Provenance Decoy Title', 'doi:10.5555/trailing.decoy');
$metadataBytes = $currentXmp . "\0\0\n" . $trailingXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP Dublin Core provenance smoke metadata.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Dublin Core Provenance WordPress Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Dublin Core Provenance Info Title) /Author (Info Provenance Author) /Producer (Info Provenance Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$dublinCore = $metadata['xmp_dublin_core'] ?? [];

if (($metadata['title'] ?? null) !== 'WordPress Dublin Core Provenance XMP Title') {
    throw new RuntimeException('Expected current XMP title to win over trailer Info.');
}
if (($dublinCore['identifier_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected two Dublin Core identifiers in review metadata.');
}
if (($dublinCore['publisher_count'] ?? null) !== 1 || ($dublinCore['contributor_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected Dublin Core publisher and contributor review counts.');
}
if (!is_string($encoded) || str_contains($encoded, 'private-doi-decoy') || str_contains($encoded, 'Trailing Dublin Core Provenance Decoy Title')) {
    throw new RuntimeException('Private or trailing XMP provenance leaked into metadata.');
}
if (str_contains($plainText, 'WordPress Data Liberation Press') || str_contains($plainText, 'Trailing Dublin Core Provenance Decoy Title')) {
    throw new RuntimeException('Dublin Core provenance leaked into visible WordPress body text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-dublin-core-provenance-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-dublin-core-provenance',
    'native_boundary' => 'accepted catalog /Metadata XMP streams preserve Dublin Core provenance as review-only metadata',
    'source' => $metadata['source'] ?? [],
    'identifiers' => $dublinCore['identifiers'] ?? [],
    'identifier_count' => $dublinCore['identifier_count'] ?? null,
    'publishers' => $dublinCore['publishers'] ?? [],
    'publisher_count' => $dublinCore['publisher_count'] ?? null,
    'contributors' => $dublinCore['contributors'] ?? [],
    'contributor_count' => $dublinCore['contributor_count'] ?? null,
    'relations' => $dublinCore['relations'] ?? [],
    'source_documents' => $dublinCore['source_documents'] ?? [],
    'types' => $dublinCore['types'] ?? [],
    'coverage' => $dublinCore['coverage'] ?? [],
    'review_only' => ($dublinCore['review_only'] ?? null) === true,
    'payload_included' => $dublinCore['payload_included'] ?? null,
    'private_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'private-doi-decoy'),
    'trailing_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Dublin Core Provenance Decoy Title'),
    'visible_text_excludes_xmp_provenance' => !str_contains($plainText, 'WordPress Data Liberation Press')
        && !str_contains($plainText, 'doi:10.5555/markerpdf.dc.provenance'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
