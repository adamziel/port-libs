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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">PieceInfo Private XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Application-private packet</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:CreateDate>2026-06-02T10:50:00Z</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$compressedXmp = gzcompress($xmp);
$compressedProfile = gzcompress('PieceInfo private ICC bytes should not be promoted');
if (!is_string($compressedXmp) || !is_string($compressedProfile)) {
    throw new RuntimeException('Unable to compress PieceInfo metadata boundary fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (PieceInfo Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PieceInfo << /WPMetadata << /LastModified (D:20260602105000Z) /Private << /Workflow (metadata-boundary) /ReviewFlag true /Metadata 5 0 R /OutputIntents [9 0 R] >> >> >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedProfile) . " >>\nstream\n{$compressedProfile}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (PieceInfo sRGB) /Info (PieceInfo PDF/A) /DestOutputProfile 7 0 R >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$pieceInfo = $metadata['catalog']['piece_info']['WPMetadata'] ?? [];
$private = is_array($pieceInfo['private'] ?? null) ? $pieceInfo['private'] : [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['source'] ?? []) !== ['catalog'] || isset($metadata['title']) || isset($metadata['pdfa'])) {
    throw new RuntimeException('Expected PieceInfo private metadata to stay out of document metadata roots.');
}
if (($private['Metadata']['Type'] ?? null) !== 'Metadata' || ($private['OutputIntents'][0]['S'] ?? null) !== 'GTS_PDFA1') {
    throw new RuntimeException('Expected catalog PieceInfo review metadata for private Metadata and OutputIntents keys.');
}

echo '<!-- markerpdf-metadata-pieceinfo-outputintent-xmp-boundary-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /PieceInfo private /Metadata and /OutputIntents review keys stay separate from document XMP/PDF-A metadata',
    'source' => $metadata['source'],
    'piece_info_apps' => array_keys($metadata['catalog']['piece_info'] ?? []),
    'pieceinfo_private_xmp_not_promoted' => is_string($encoded) && !str_contains($encoded, 'PieceInfo Private XMP Title'),
    'pieceinfo_outputintent_not_promoted_to_pdfa' => !isset($metadata['pdfa']) && ($metadata['output_intents'] ?? []) === [],
    'pieceinfo_metadata_type' => $private['Metadata']['Type'] ?? null,
    'pieceinfo_outputintent_subtype' => $private['OutputIntents'][0]['S'] ?? null,
    'visible_text' => $plainText,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>PDF PieceInfo Metadata Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:pieceinfo-metadata-boundary ' . htmlspecialchars(json_encode([
    'last_modified' => $pieceInfo['last_modified'] ?? null,
    'workflow' => $private['Workflow'] ?? null,
    'review_flag' => $private['ReviewFlag'] ?? null,
    'metadata_review' => $private['Metadata'] ?? [],
    'outputintent_review' => $private['OutputIntents'][0] ?? [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
