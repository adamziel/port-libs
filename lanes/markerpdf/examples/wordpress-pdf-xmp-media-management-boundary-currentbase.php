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
    string $instanceId,
    string $sourceDocumentId
): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="#private-decoy"'
        . ' xmlns:xmpMM="http://ns.adobe.com/xap/1.0/mm/"'
        . ' xmpMM:DocumentID="xmp.did:PRIVATE-WORDPRESS-DECOY"/>'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
        . ' xmlns:xmpMM="http://ns.adobe.com/xap/1.0/mm/"'
        . ' xmlns:stRef="http://ns.adobe.com/xap/1.0/sType/ResourceRef#"'
        . ' xmpMM:DocumentID="' . htmlspecialchars($documentId, ENT_XML1) . '"'
        . ' xmpMM:InstanceID="' . htmlspecialchars($instanceId, ENT_XML1) . '"'
        . ' xmpMM:OriginalDocumentID="xmp.did:WORDPRESS-ORIGINAL-DOCUMENT">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>WordPress Import Editor</rdf:li><rdf:li>Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">XMP identity metadata stays review-only during import.</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-media-management</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>WordPress Media Management Producer</pdf:Producer>'
        . '<xmp:CreatorTool>WordPress Media Management Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>2026-06-06T01:58:43-04:00</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T05:58:43Z</xmp:MetadataDate>'
        . '<xmpMM:DerivedFrom rdf:resource="#source-doc"/>'
        . '</rdf:Description>'
        . '<rdf:Description rdf:ID="source-doc"'
        . ' xmlns:stRef="http://ns.adobe.com/xap/1.0/sType/ResourceRef#"'
        . ' stRef:documentID="' . htmlspecialchars($sourceDocumentId, ENT_XML1) . '"'
        . ' stRef:instanceID="xmp.iid:WORDPRESS-SOURCE-INSTANCE"/>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$currentXmp = $xmpPacket(
    'Current WordPress XMP Media Management Title',
    'xmp.did:WORDPRESS-CURRENT-DOCUMENT',
    'xmp.iid:WORDPRESS-CURRENT-INSTANCE',
    'xmp.did:WORDPRESS-SOURCE-DOCUMENT'
);
$trailingXmp = $xmpPacket(
    'Trailing WordPress XMP Media Management Decoy Title',
    'xmp.did:WORDPRESS-TRAILING-DOCUMENT',
    'xmp.iid:WORDPRESS-TRAILING-INSTANCE',
    'xmp.did:WORDPRESS-TRAILING-SOURCE'
);
$metadataBytes = $currentXmp . "\0\0\n" . $trailingXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress WordPress XMP media-management smoke metadata.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Media Management WordPress Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Fallback Media Management Info Title) /Author (Fallback Author) /Producer (Fallback Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$media = is_array($metadata['xmp_media_management'] ?? null) ? $metadata['xmp_media_management'] : [];
$derived = is_array($media['derived_from'] ?? null) ? $media['derived_from'] : [];

if (($media['document_id'] ?? null) !== 'xmp.did:WORDPRESS-CURRENT-DOCUMENT') {
    throw new RuntimeException('Expected current XMP media-management document ID in review metadata.');
}
if (($derived['document_id'] ?? null) !== 'xmp.did:WORDPRESS-SOURCE-DOCUMENT') {
    throw new RuntimeException('Expected current XMP DerivedFrom source document ID in review metadata.');
}
if (str_contains($encoded, 'WORDPRESS-TRAILING-DOCUMENT') || str_contains($encoded, 'PRIVATE-WORDPRESS-DECOY')) {
    throw new RuntimeException('Expected trailing and private XMP media-management identifiers to stay excluded.');
}
if (str_contains($plainText, 'WORDPRESS-CURRENT-DOCUMENT') || str_contains($plainText, 'WORDPRESS-SOURCE-DOCUMENT')) {
    throw new RuntimeException('Expected XMP media-management identifiers to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-media-management-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-xmp-media-management-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-media-management-boundary',
    'native_boundary' => 'document-level XMP xmpMM identifiers and DerivedFrom references are review metadata only, bounded before private and trailing XMP decoys',
    'source' => $metadata['source'] ?? [],
    'title_from_current_packet' => ($metadata['title'] ?? null) === 'Current WordPress XMP Media Management Title',
    'document_id_preserved' => ($media['document_id'] ?? null) === 'xmp.did:WORDPRESS-CURRENT-DOCUMENT',
    'instance_id_preserved' => ($media['instance_id'] ?? null) === 'xmp.iid:WORDPRESS-CURRENT-INSTANCE',
    'derived_from_preserved' => ($derived['document_id'] ?? null) === 'xmp.did:WORDPRESS-SOURCE-DOCUMENT',
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'private_resource_decoy_excluded' => !str_contains($encoded, 'PRIVATE-WORDPRESS-DECOY'),
    'trailing_packet_decoy_excluded' => !str_contains($encoded, 'WORDPRESS-TRAILING-DOCUMENT'),
    'visible_text_excludes_xmp_ids' => !str_contains($plainText, 'WORDPRESS-CURRENT-DOCUMENT')
        && !str_contains($plainText, 'WORDPRESS-SOURCE-DOCUMENT'),
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
        'instance_id' => $media['instance_id'] ?? null,
        'derived_from_document_id' => $derived['document_id'] ?? null,
        'review_only' => $media['review_only'] ?? null,
        'payload_included' => $media['payload_included'] ?? null,
    ],
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
