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
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Destination Boundary XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Navigation names are review metadata</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:CreateDate>2026-06-02T12:41:55Z</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress destination metadata boundary fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Destination Metadata Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyAppendix [4 0 R /Fit] /LegacyStale [99 0 R /Fit] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Title (DocInfo Destination Title) /Author (Metadata Owner; Site Editor) /Producer (DocInfo Producer) >>\nendobj\n"
    . "8 0 obj\n<< /Kids [9 0 R 10 0 R 8 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /Limits [(A) (M)] /Names [(Chapter One) [3 0 R /FitH 640] 12 0 R 13 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Limits [(N) (Z)] /Names [(Review Deck) 14 0 R (Stale Review) [99 0 R /XYZ 1 2 3]] >>\nendobj\n"
    . "12 0 obj\n<FEFF0049006E00640069007200650063007400200044006500730074>\nendobj\n"
    . "13 0 obj\n<< /D [4 0 R /FitR 10 20 300 740] >>\nendobj\n"
    . "14 0 obj\n[3 0 R /XYZ 144 null 0]\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 7 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$destinations = is_array($metadata['document_destinations'] ?? null) ? $metadata['document_destinations'] : [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['source'] ?? []) !== ['xmp', 'info', 'catalog']) {
    throw new RuntimeException('Expected XMP, DocInfo, and catalog destination metadata sources.');
}
if (($metadata['title'] ?? null) !== 'Destination Boundary XMP Title' || ($metadata['authors'] ?? []) !== ['Metadata Owner', 'Site Editor']) {
    throw new RuntimeException('Expected XMP title with DocInfo author fallback.');
}
if (($destinations['names'] ?? []) !== ['Chapter One', 'Indirect Dest', 'Review Deck', 'LegacyAppendix']) {
    throw new RuntimeException('Expected resolved /Names /Dests plus legacy /Dests review names.');
}
if (!is_string($encoded) || str_contains($encoded, 'Stale Review') || str_contains($plainText, 'Chapter One')) {
    throw new RuntimeException('Expected stale destination names to stay out of metadata and visible text.');
}

echo '<!-- markerpdf-xmp-docinfo-names-destination-metadata-boundary-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF XMP plus trailer DocInfo metadata with catalog /Names /Dests and legacy /Dests review payloads',
    'source' => $metadata['source'],
    'xmp_title_preferred' => ($metadata['title'] ?? null) === 'Destination Boundary XMP Title',
    'docinfo_author_fallback' => ($metadata['authors'] ?? []) === ['Metadata Owner', 'Site Editor'],
    'destination_sources' => $destinations['source'] ?? [],
    'destination_names' => $destinations['names'] ?? [],
    'destination_count' => $destinations['count'] ?? 0,
    'unresolved_destination_count' => $destinations['unresolved_count'] ?? 0,
    'stale_destination_filtered' => is_string($encoded) && !str_contains($encoded, 'Stale Review'),
    'destination_names_hidden_from_visible_text' => !str_contains($plainText, 'Chapter One') && !str_contains($plainText, 'Indirect Dest'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo '<h2>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:destination-review ' . htmlspecialchars(json_encode([
    'authors' => $metadata['authors'] ?? [],
    'producer' => $metadata['producer'] ?? null,
    'destinations' => $destinations['destinations'] ?? [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
