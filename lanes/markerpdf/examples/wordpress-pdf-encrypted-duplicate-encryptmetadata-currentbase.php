<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Duplicate EncryptMetadata Hidden XMP</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Ambiguous EncryptMetadata must not preserve this stream</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:MetadataDate>2026-06-05T14:11:14Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress duplicate EncryptMetadata fixture XMP.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Duplicate EncryptMetadata encrypted text leak) Tj ET';
$ownerValidation = str_repeat('O', 32);
$userValidation = str_repeat('U', 32);
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <" . strtoupper(bin2hex($ownerValidation)) . ">"
    . " /U <" . strtoupper(bin2hex($userValidation)) . ">"
    . " /P -44 /EncryptMetadata true /EncryptMetadata false >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$policy = $metadata['encryption']['metadata_source_policy'] ?? [];
$review = $metadata['encryption']['encrypt_metadata_declaration_review'] ?? [];
$encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, 'Duplicate EncryptMetadata Hidden XMP')
        || str_contains($encoded, 'Ambiguous EncryptMetadata must not preserve this stream')
        || str_contains($encoded, $content)
        || str_contains($encoded, $ownerValidation)
        || str_contains($encoded, $userValidation)
        || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
        || str_contains($encoded, strtoupper(bin2hex($userValidation)))
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted page text to stay blocked.');
}
if (($metadata['source'] ?? []) !== ['encryption']) {
    throw new RuntimeException('Expected duplicate EncryptMetadata to suppress root XMP metadata.');
}
if (($metadata['encryption']['encrypt_metadata_defaulted_fail_closed'] ?? null) !== true) {
    throw new RuntimeException('Expected duplicate EncryptMetadata to default fail-closed.');
}
if (($policy['xmp_stream_policy'] ?? null) !== 'suppressed_encrypted_metadata_stream') {
    throw new RuntimeException('Expected encrypted XMP stream to stay suppressed.');
}
if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted content, XMP, and Standard auth material to stay redacted.');
}

echo '<!-- markerpdf-encrypted-duplicate-encryptmetadata-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-duplicate-encryptmetadata-currentbase',
    'native_boundary' => 'duplicate EncryptMetadata declarations are ambiguous and default fail-closed before WordPress metadata import',
    'encrypted_text_blocked' => $plainText === '',
    'metadata_source' => $metadata['source'] ?? [],
    'xmp_stream_policy' => $policy['xmp_stream_policy'] ?? null,
    'suppressed_sources' => $policy['suppressed_sources'] ?? [],
    'preserved_sources' => $policy['preserved_sources'] ?? [],
    'encrypt_metadata_status' => $metadata['encryption']['encrypt_metadata_status'] ?? null,
    'encrypt_metadata_trusted' => $metadata['encryption']['encrypt_metadata_trusted'] ?? null,
    'encrypt_metadata_defaulted_fail_closed' => $metadata['encryption']['encrypt_metadata_defaulted_fail_closed'] ?? null,
    'declared_entry_count' => $review['declared_entry_count'] ?? null,
    'boolean_values' => $review['boolean_values'] ?? [],
    'policy' => $report['permission_preflight']['policy'] ?? null,
    'review_reasons' => $report['review_reasons'] ?? [],
    'raw_material_exposed' => false,
    'executes_decryption' => $report['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $report['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $report['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $report['executes_external_pdf_tools'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Metadata Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text and XMP metadata remain blocked when the encryption dictionary gives conflicting metadata-encryption declarations.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-duplicate-encryptmetadata ' . htmlspecialchars(json_encode([
    'decision' => $report['import_decision'] ?? null,
    'metadata_policy' => [
        'xmp' => $policy['xmp_stream_policy'] ?? null,
        'status' => $metadata['encryption']['encrypt_metadata_status'] ?? null,
        'trusted' => $metadata['encryption']['encrypt_metadata_trusted'] ?? null,
    ],
    'blocked_operations' => $report['blocked_operations'] ?? [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
