<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$xmpTitle = 'Trailing EncryptMetadata Hidden XMP';
$xmpDescription = 'A tailed EncryptMetadata false operand must not preserve this stream';
$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . $xmpTitle . '</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . $xmpDescription . '</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:MetadataDate>2026-06-08T18:03:19Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress trailing EncryptMetadata fixture XMP.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Trailing EncryptMetadata encrypted WordPress text leak) Tj ET';
$ownerValidation = str_repeat('E', 32);
$userValidation = str_repeat('M', 32);
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "6 0 obj\nfalse\nendobj\n"
    . "8 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O " . $hex($ownerValidation)
    . " /U " . $hex($userValidation)
    . " /P -44 /EncryptMetadata false 6 0 R >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 8 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$report = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encryption = is_array($metadata['encryption'] ?? null) ? $metadata['encryption'] : [];
$policy = is_array($encryption['metadata_source_policy'] ?? null) ? $encryption['metadata_source_policy'] : [];
$review = is_array($encryption['encrypt_metadata_declaration_review'] ?? null)
    ? $encryption['encrypt_metadata_declaration_review']
    : [];
$entry = is_array($review['entries'][0] ?? null) ? $review['entries'][0] : [];
$encoded = json_encode([$metadata, $report], JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $xmpTitle)
        || str_contains($encoded, $xmpDescription)
        || str_contains($encoded, $content)
        || str_contains($encoded, $ownerValidation)
        || str_contains($encoded, $userValidation)
        || str_contains($encoded, strtoupper(bin2hex($ownerValidation)))
        || str_contains($encoded, strtoupper(bin2hex($userValidation)))
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected encrypted text to stay blocked before decryption.');
}
if (($metadata['source'] ?? null) !== ['encryption'] || ($metadata['xmp'] ?? null) !== []) {
    throw new RuntimeException('Expected tailed EncryptMetadata to suppress encrypted root XMP.');
}
if (($encryption['encrypt_metadata'] ?? null) !== true
    || ($encryption['encrypt_metadata_explicit'] ?? null) !== true
    || ($encryption['encrypt_metadata_trusted'] ?? null) !== false
    || ($encryption['encrypt_metadata_defaulted_fail_closed'] ?? null) !== true
    || ($encryption['encrypt_metadata_status'] ?? null) !== 'malformed_encrypt_metadata_declaration_review'
) {
    throw new RuntimeException('Expected tailed EncryptMetadata false to default fail-closed.');
}
if (($review['boolean_entry_count'] ?? null) !== 0
    || ($entry['status'] ?? null) !== 'encrypt_metadata_trailing_operand_review'
    || ($entry['trailing_operand_shape'] ?? null) !== 'indirect_reference'
    || ($entry['trailing_operand_preview'] ?? null) !== '6 0 R'
) {
    throw new RuntimeException('Expected trailing EncryptMetadata operand review metadata.');
}
if (($policy['xmp_stream_policy'] ?? null) !== 'suppressed_encrypted_metadata_stream') {
    throw new RuntimeException('Expected encrypted XMP stream to stay suppressed.');
}
if (($report['import_decision'] ?? null) !== 'block_encrypted_content_review_security_metadata'
    || !in_array('encrypt_metadata_fail_closed', $report['review_reasons'] ?? [], true)
) {
    throw new RuntimeException('Expected security preflight to record fail-closed metadata review.');
}
if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted content, XMP, and Standard auth material to stay redacted.');
}

echo '<!-- markerpdf-encrypted-encryptmetadata-trailing-operand-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-encryptmetadata-trailing-operand-currentbase',
    'native_boundary' => 'tailed EncryptMetadata false is malformed and defaults fail-closed before WordPress metadata import',
    'encrypted_text_blocked' => $plainText === '',
    'metadata_source' => $metadata['source'] ?? [],
    'xmp_stream_policy' => $policy['xmp_stream_policy'] ?? null,
    'suppressed_sources' => $policy['suppressed_sources'] ?? [],
    'preserved_sources' => $policy['preserved_sources'] ?? [],
    'encrypt_metadata_status' => $encryption['encrypt_metadata_status'] ?? null,
    'encrypt_metadata_trusted' => $encryption['encrypt_metadata_trusted'] ?? null,
    'encrypt_metadata_defaulted_fail_closed' => $encryption['encrypt_metadata_defaulted_fail_closed'] ?? null,
    'entry_statuses' => $review['entry_statuses'] ?? [],
    'boolean_entry_count' => $review['boolean_entry_count'] ?? null,
    'trailing_operand_shape' => $entry['trailing_operand_shape'] ?? null,
    'trailing_operand_preview' => $entry['trailing_operand_preview'] ?? null,
    'import_decision' => $report['import_decision'] ?? null,
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
    'Encrypted PDF text and XMP metadata remain blocked when the metadata-encryption declaration has a trailing operand.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-encryptmetadata-trailing-operand ' . htmlspecialchars(json_encode([
    'decision' => $report['import_decision'] ?? null,
    'metadata_policy' => [
        'xmp' => $policy['xmp_stream_policy'] ?? null,
        'status' => $encryption['encrypt_metadata_status'] ?? null,
        'trusted' => $encryption['encrypt_metadata_trusted'] ?? null,
        'defaulted_fail_closed' => $encryption['encrypt_metadata_defaulted_fail_closed'] ?? null,
    ],
    'encrypt_metadata_review' => [
        'declared_entry_count' => $review['declared_entry_count'] ?? null,
        'boolean_entry_count' => $review['boolean_entry_count'] ?? null,
        'entry_statuses' => $review['entry_statuses'] ?? [],
        'trailing_operand_shape' => $entry['trailing_operand_shape'] ?? null,
        'trailing_operand_preview' => $entry['trailing_operand_preview'] ?? null,
    ],
    'blocked_operations' => $report['blocked_operations'] ?? [],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
