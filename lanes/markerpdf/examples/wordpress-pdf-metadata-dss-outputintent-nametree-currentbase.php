<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Metadata DSS NameTree Document Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Document metadata remains distinct from DSS and name-tree payloads</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:CreateDate>2026-06-02T18:03:02Z</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$attachmentXmp = str_replace('Metadata DSS NameTree Document Title', 'NameTree Attachment XMP Title', $xmp);
$documentXmpStream = gzcompress($xmp);
$attachmentXmpStream = gzcompress($attachmentXmp);
$rootProfile = 'Root document ICC profile bytes for PDF/A review';
$attachmentProfile = 'Attachment-local ICC profile bytes should not be promoted';
$rootProfileStream = gzcompress($rootProfile);
$attachmentProfileStream = gzcompress($attachmentProfile);
if (
    !is_string($documentXmpStream)
    || !is_string($attachmentXmpStream)
    || !is_string($rootProfileStream)
    || !is_string($attachmentProfileStream)
) {
    throw new RuntimeException('Unable to compress metadata DSS name-tree smoke streams.');
}

$sourcePayload = '<wp-export><post id="180302"/></wp-export>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$certPayload = 'NAMETREE_DSS_CERTIFICATE_BYTES_SHOULD_NOT_LEAK';
$ocspPayload = 'NAMETREE_DSS_OCSP_BYTES_SHOULD_NOT_LEAK';
$timestampPayload = 'NAMETREE_DSS_TIMESTAMP_BYTES_SHOULD_NOT_LEAK';
$pageContent = 'BT /F1 12 Tf 72 720 Td (Metadata DSS OutputIntent NameTree Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 14 0 R /OutputIntents [9 0 R] /DSS 60 0 R /Names << /EmbeddedFiles 6 0 R /Dests 16 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($attachmentXmpStream) . " >>\nstream\n{$attachmentXmpStream}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Kids [17 0 R 6 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream\nendobj\n"
    . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($attachmentProfileStream) . " >>\nstream\n{$attachmentProfileStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Document sRGB) /Info (Root document PDF/A profile) /DestOutputProfile 7 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /UF (source-unicode.xml) /Desc (Original WordPress export) /AFRelationship /Source /Lang (de-DE) /Metadata 5 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602180302Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Attachment sRGB) /Info (Attachment-local PDF/A) /DestOutputProfile 8 0 R >>\nendobj\n"
    . "14 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($documentXmpStream) . " >>\nstream\n{$documentXmpStream}\nendstream\nendobj\n"
    . "16 0 obj\n<< /Names [(Review Start) [3 0 R /FitH 720]] >>\nendobj\n"
    . "17 0 obj\n<< /Names [(source.xml) 10 0 R (missing.xml) 99 0 R] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "60 0 obj\n<< /Type /DSS /Certs [70 0 R] /OCSPs [71 0 R] /VRI << /ABCDEF123456 61 0 R >> >>\nendobj\n"
    . "61 0 obj\n<< /Type /VRI /Cert [70 0 R] /OCSP [71 0 R] /TU (D:20260602180302Z) /TS 73 0 R >>\nendobj\n"
    . "70 0 obj\n<< /Length " . strlen($certPayload) . " /Subtype /application#2Fpkix-cert >>\nstream\n{$certPayload}\nendstream\nendobj\n"
    . "71 0 obj\n<< /Length " . strlen($ocspPayload) . " /Subtype /application#2Focsp-response >>\nstream\n{$ocspPayload}\nendstream\nendobj\n"
    . "73 0 obj\n<< /Length " . strlen($timestampPayload) . " /Subtype /application#2Ftst-info >>\nstream\n{$timestampPayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$embeddedFile = $metadata['embedded_files'][0] ?? [];
$dss = $metadata['document_security_store'] ?? [];
$destinations = $metadata['document_destinations'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Metadata DSS NameTree Document Title') {
    throw new RuntimeException('Expected document XMP title metadata.');
}
if (($metadata['pdfa']['output_condition_identifiers'] ?? []) !== ['Document sRGB']) {
    throw new RuntimeException('Expected only root OutputIntent to contribute PDF/A metadata.');
}
if (($embeddedFile['name_tree_name'] ?? null) !== 'source.xml' || array_key_exists('content', $embeddedFile)) {
    throw new RuntimeException('Expected review-only name-tree embedded-file metadata.');
}
if (($dss['total_validation_stream_count'] ?? null) !== 3 || ($dss['raw_validation_bytes_exposed'] ?? null) !== false) {
    throw new RuntimeException('Expected review-only DSS validation stream metadata.');
}
if (($destinations['names'] ?? []) !== ['Review Start']) {
    throw new RuntimeException('Expected destination name-tree review metadata.');
}
if (
    !is_string($encoded)
    || str_contains($encoded, 'NameTree Attachment XMP Title')
    || str_contains($encoded, $sourcePayload)
    || str_contains($encoded, $attachmentProfile)
    || str_contains($encoded, $certPayload)
    || str_contains($encoded, $ocspPayload)
    || str_contains($encoded, $timestampPayload)
    || str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'Document sRGB')
    || str_contains($plainText, 'Review Start')
) {
    throw new RuntimeException('Expected review metadata and payload bytes to stay out of visible WordPress import output.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-metadata-dss-outputintent-nametree-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-catalog-metadata-review',
    'native_boundary' => 'catalog /Metadata, /DSS, /OutputIntents, /Names /EmbeddedFiles, and /Names /Dests review metadata before WordPress import',
    'executes_signature_validation' => $dss['executes_signature_validation'] ?? null,
    'executes_revocation_check' => $dss['executes_revocation_check'] ?? null,
    'executes_trust_chain_validation' => $dss['executes_trust_chain_validation'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title' => $metadata['title'] ?? null,
    'pdfa_identifiers' => $metadata['pdfa']['output_condition_identifiers'] ?? [],
    'embedded_name_tree_files' => array_map(static fn (array $file): ?string => $file['name_tree_name'] ?? null, $metadata['embedded_files'] ?? []),
    'embedded_payload_content_omitted' => !array_key_exists('content', $embeddedFile),
    'dss_validation_stream_count' => $dss['total_validation_stream_count'] ?? null,
    'dss_raw_validation_bytes_exposed' => $dss['raw_validation_bytes_exposed'] ?? null,
    'destination_names' => $destinations['names'] ?? [],
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:metadata-review ' . $htmlJson([
    'document_title' => $metadata['title'] ?? null,
    'pdfa' => $metadata['pdfa'] ?? [],
    'dss' => [
        'present' => $dss['present'] ?? null,
        'validation_stream_count' => $dss['total_validation_stream_count'] ?? null,
        'vri_keys' => $dss['vri_keys'] ?? [],
    ],
    'embedded_file' => [
        'name_tree_name' => $embeddedFile['name_tree_name'] ?? null,
        'filename' => $embeddedFile['filename'] ?? null,
        'relationship' => $embeddedFile['relationship'] ?? null,
        'language' => $embeddedFile['language'] ?? null,
        'checksum_matches' => $embeddedFile['checksum_matches'] ?? null,
        'payload_included' => array_key_exists('content', $embeddedFile),
    ],
    'destinations' => $destinations['names'] ?? [],
]) . " -->\n";
