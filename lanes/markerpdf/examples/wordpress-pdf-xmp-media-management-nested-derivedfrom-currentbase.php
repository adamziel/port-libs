<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$xmpPacket = static function (
    string $title,
    string $documentId,
    string $sourceDocumentId
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="#private-nested-decoy"'
        . ' xmlns:xmpMM="http://ns.adobe.com/xap/1.0/mm/"'
        . ' xmpMM:DocumentID="xmp.did:WORDPRESS-NESTED-PRIVATE-DECOY"/>'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
        . ' xmlns:xmpMM="http://ns.adobe.com/xap/1.0/mm/"'
        . ' xmlns:stRef="http://ns.adobe.com/xap/1.0/sType/ResourceRef#"'
        . ' xmpMM:DocumentID="' . htmlspecialchars($documentId, ENT_XML1) . '"'
        . ' xmpMM:InstanceID="xmp.iid:WORDPRESS-NESTED-CURRENT-INSTANCE"'
        . ' xmpMM:OriginalDocumentID="xmp.did:WORDPRESS-NESTED-ORIGINAL-DOCUMENT">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>WordPress Nested Media Editor</rdf:li><rdf:li>Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Nested XMP source identity metadata stays review-only during import.</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-nested-media-management</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>WordPress Nested Media Producer</pdf:Producer>'
        . '<xmp:CreatorTool>WordPress Nested Media Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>2026-06-08T14:03:27-04:00</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T18:03:27Z</xmp:MetadataDate>'
        . '<xmpMM:DerivedFrom>'
        . '<rdf:Description'
        . ' stRef:documentID="' . htmlspecialchars($sourceDocumentId, ENT_XML1) . '"'
        . ' stRef:instanceID="xmp.iid:WORDPRESS-NESTED-SOURCE-INSTANCE">'
        . '<xmp:PrivateLabel>nested source qualifier noise</xmp:PrivateLabel>'
        . '</rdf:Description>'
        . '</xmpMM:DerivedFrom>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$currentXmp = $xmpPacket(
    'Current WordPress Nested XMP Media Title',
    'xmp.did:WORDPRESS-NESTED-CURRENT-DOCUMENT',
    'xmp.did:WORDPRESS-NESTED-SOURCE-DOCUMENT'
);
$trailingXmp = $xmpPacket(
    'Trailing WordPress Nested XMP Media Decoy Title',
    'xmp.did:WORDPRESS-NESTED-TRAILING-DOCUMENT',
    'xmp.did:WORDPRESS-NESTED-TRAILING-SOURCE'
);
$metadataBytes = $currentXmp . "\0\0\n" . $trailingXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress WordPress XMP nested media-management smoke metadata.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Nested Media Management WordPress Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Fallback Nested Media Info Title) /Author (Fallback Author) /Producer (Fallback Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$media = is_array($metadata['xmp_media_management'] ?? null) ? $metadata['xmp_media_management'] : [];
$derived = is_array($media['derived_from'] ?? null) ? $media['derived_from'] : [];

if (($media['document_id'] ?? null) !== 'xmp.did:WORDPRESS-NESTED-CURRENT-DOCUMENT') {
    throw new RuntimeException('Expected current nested XMP media-management document ID in review metadata.');
}
if (($derived['document_id'] ?? null) !== 'xmp.did:WORDPRESS-NESTED-SOURCE-DOCUMENT') {
    throw new RuntimeException('Expected nested XMP DerivedFrom source document ID in review metadata.');
}
if (str_contains($encoded, 'WORDPRESS-NESTED-TRAILING-DOCUMENT') || str_contains($encoded, 'WORDPRESS-NESTED-PRIVATE-DECOY')) {
    throw new RuntimeException('Expected trailing and private nested XMP media-management identifiers to stay excluded.');
}
if (str_contains($encoded, 'nested source qualifier noise')) {
    throw new RuntimeException('Expected nested DerivedFrom qualifier text to stay excluded from metadata values.');
}
if (str_contains($plainText, 'WORDPRESS-NESTED-CURRENT-DOCUMENT') || str_contains($plainText, 'WORDPRESS-NESTED-SOURCE-DOCUMENT')) {
    throw new RuntimeException('Expected nested XMP media-management identifiers to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-media-management-nested-derivedfrom-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-xmp-media-management-nested-derivedfrom-currentbase',
    'support_component' => 'native-pdf-xmp-media-management-boundary',
    'native_boundary' => 'nested RDF Description xmpMM DerivedFrom source identifiers are review metadata only, bounded before private and trailing XMP decoys',
    'source' => $metadata['source'] ?? [],
    'title_from_current_packet' => ($metadata['title'] ?? null) === 'Current WordPress Nested XMP Media Title',
    'document_id_preserved' => ($media['document_id'] ?? null) === 'xmp.did:WORDPRESS-NESTED-CURRENT-DOCUMENT',
    'derived_from_preserved' => ($derived['document_id'] ?? null) === 'xmp.did:WORDPRESS-NESTED-SOURCE-DOCUMENT',
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'private_resource_decoy_excluded' => !str_contains($encoded, 'WORDPRESS-NESTED-PRIVATE-DECOY'),
    'trailing_packet_decoy_excluded' => !str_contains($encoded, 'WORDPRESS-NESTED-TRAILING-DOCUMENT'),
    'qualifier_text_excluded' => !str_contains($encoded, 'nested source qualifier noise'),
    'visible_text_excludes_xmp_ids' => !str_contains($plainText, 'WORDPRESS-NESTED-CURRENT-DOCUMENT')
        && !str_contains($plainText, 'WORDPRESS-NESTED-SOURCE-DOCUMENT'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-metadata ' . $htmlJson([
    'authors' => $metadata['authors'] ?? [],
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'xmp_media_management' => [
        'document_id' => $media['document_id'] ?? null,
        'derived_from_document_id' => $derived['document_id'] ?? null,
        'review_only' => $media['review_only'] ?? null,
        'payload_included' => $media['payload_included'] ?? null,
    ],
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
