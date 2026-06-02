<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$rootXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Encrypted AF Root XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Only root XMP is explicitly unencrypted</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:CreateDate>2026-06-02T22:12:39Z</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-02T22:13:39Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$fileXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Encrypted Attachment XMP Hidden Title</rdf:li></rdf:Alt></dc:title>'
    . '<xmp:MetadataDate>2026-06-02T22:14:39Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$rootXmpStream = gzcompress($rootXmp);
$fileXmpStream = gzcompress($fileXmp);
$rootProfile = 'Encrypted root OutputIntent profile bytes should not be parsed without decryption';
$fileProfile = 'Encrypted attachment OutputIntent profile bytes should not be parsed without decryption';
$rootProfileStream = gzcompress($rootProfile);
$fileProfileStream = gzcompress($fileProfile);
if (!is_string($rootXmpStream) || !is_string($fileXmpStream) || !is_string($rootProfileStream) || !is_string($fileProfileStream)) {
    throw new RuntimeException('Unable to compress encrypted associated-file metadata smoke streams.');
}

$payload = '<wp-export><post id="encrypted-associated-file"/></wp-export>';
$content = 'BT /F1 12 Tf 72 720 Td (Encrypted associated visible text leak) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /OutputIntents [9 0 R] /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootXmpStream) . " >>\nstream\n{$rootXmpStream}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($fileXmpStream) . " >>\nstream\n{$fileXmpStream}\nendstream\nendobj\n"
    . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($rootProfileStream) . " >>\nstream\n{$rootProfileStream}\nendstream\nendobj\n"
    . "8 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($fileProfileStream) . " >>\nstream\n{$fileProfileStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Encrypted Root PDF/A) /Info (Encrypted root profile) /DestOutputProfile 7 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (encrypted-source.xml) /Desc (Encrypted source payload) /AFRelationship /Source /Metadata 6 0 R /OutputIntents [13 0 R] /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . ' /CheckSum <' . strtoupper(hash('md5', $payload)) . "> >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Encrypted Attachment PDF/A) /Info (Encrypted attachment profile) /DestOutputProfile 8 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -64 /EncryptMetadata false /StmF /StdCF /StrF /StdCF /EFF /StdCF /CF << /StdCF << /CFM /AESV2 /Length 16 /AuthEvent /DocOpen >> >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 20 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$policy = $metadata['encryption']['metadata_source_policy'] ?? [];
$filePolicy = $metadata['associated_files'][0]['encryption_policy'] ?? [];

if (($metadata['title'] ?? null) !== 'Encrypted AF Root XMP Title') {
    throw new RuntimeException('Expected root XMP title to be preserved when EncryptMetadata is false.');
}
if (($policy['associated_files_policy'] ?? null) !== 'suppressed_encrypted_associated_file_metadata') {
    throw new RuntimeException('Expected encrypted associated-file metadata policy.');
}
if (($filePolicy['embedded_file_stream_policy'] ?? null) !== 'suppressed_encrypted_embedded_file_streams') {
    throw new RuntimeException('Expected encrypted embedded-file stream boundary.');
}
if (($preflight['import_decision'] ?? null) !== 'block_encrypted_content_review_security_metadata') {
    throw new RuntimeException('Expected encrypted document import to be blocked without decryption.');
}
if (!is_string($encoded) || str_contains($encoded, $payload) || str_contains($encoded, 'encrypted-source.xml') || str_contains($encoded, 'Encrypted Attachment XMP Hidden Title') || str_contains($encoded, 'Encrypted Attachment PDF/A') || str_contains($encoded, $rootProfile) || str_contains($encoded, $fileProfile)) {
    throw new RuntimeException('Expected encrypted associated-file payload, filename, XMP, and OutputIntent bytes to stay suppressed.');
}
if ($plainText !== '') {
    throw new RuntimeException('Expected visible text extraction to stay blocked without decryption.');
}

echo '<!-- markerpdf-encrypted-associated-file-metadata-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-encrypted-associated-file-metadata-review',
    'native_boundary' => 'EncryptMetadata false preserves only root XMP while encrypted FileSpec strings, embedded payloads, attachment-local XMP, and attachment-local OutputIntent remain blocked',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_decryption' => false,
    'source' => $metadata['source'],
    'title' => $metadata['title'] ?? null,
    'metadata_source_policy' => $policy,
    'associated_file_policy' => $filePolicy,
    'import_decision' => $preflight['import_decision'] ?? null,
    'payload_content_omitted' => is_string($encoded) && !str_contains($encoded, $payload),
    'attachment_xmp_omitted' => is_string($encoded) && !str_contains($encoded, 'Encrypted Attachment XMP Hidden Title'),
    'attachment_outputintent_omitted' => is_string($encoded) && !str_contains($encoded, 'Encrypted Attachment PDF/A'),
    'visible_text_blocked' => $plainText === '',
]) . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted Associated File Metadata Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'This encrypted PDF preserves only explicitly unencrypted root XMP for review. Catalog associated-file strings, embedded payload hashes, attachment XMP, and attachment OutputIntent data stay blocked until decryption is available.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
