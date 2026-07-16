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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Encrypted XMP Review Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Metadata stream preserved only when EncryptMetadata is false</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:CreateDate>2024-06-02T08:30:00-04:00</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$compressedXmp = gzcompress($xmp);
$compressedProfile = gzcompress('Encrypted ICC profile bytes should not be trusted');
if (!is_string($compressedXmp) || !is_string($compressedProfile)) {
    throw new RuntimeException('Unable to compress metadata security priority fixture.');
}

$pdfFactory = static function (string $encryptMetadataValue) use ($compressedXmp, $compressedProfile): string {
    $encryptedContent = 'BT /F1 12 Tf 72 720 Td (Encrypted metadata priority leak) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /OutputIntents [9 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Encrypted Info Title) /Author (Encrypted Info Author) /Producer (Encrypted Info Producer) >>\nendobj\n"
        . "7 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedProfile) . " >>\nstream\n{$compressedProfile}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Encrypted sRGB) /Info (Encrypted PDF/A) /DestOutputProfile 7 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -64 /EncryptMetadata {$encryptMetadataValue} >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R /Encrypt 10 0 R >>\n%%EOF";
};

$encryptedMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfFactory('true'));
$unencryptedMetadataStream = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfFactory('false'));
$encryptedText = (new PdfTextExtractor())->extractPlainText($pdfFactory('true'));

$encryptedPolicy = $encryptedMetadata['encryption']['metadata_source_policy'] ?? [];
$unencryptedPolicy = $unencryptedMetadataStream['encryption']['metadata_source_policy'] ?? [];
$encryptedEncoded = json_encode($encryptedMetadata, JSON_UNESCAPED_SLASHES);
$unencryptedEncoded = json_encode($unencryptedMetadataStream, JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-metadata-security-priority-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_decryption' => false,
    'native_boundary' => 'PDF encryption metadata source priority before XMP Info and OutputIntent review import',
    'encrypted_source' => $encryptedMetadata['source'],
    'encrypt_metadata_true_suppressed' => $encryptedPolicy['suppressed_sources'] ?? [],
    'encrypt_metadata_false_preserved' => $unencryptedPolicy['preserved_sources'] ?? [],
    'encrypted_text_blocked' => $encryptedText === '',
    'xmp_preserved_when_unencrypted' => ($unencryptedMetadataStream['title'] ?? null) === 'Encrypted XMP Review Title',
    'info_outputintent_suppressed_when_encrypted' => is_string($unencryptedEncoded)
        && !str_contains($unencryptedEncoded, 'Encrypted Info Title')
        && !str_contains($unencryptedEncoded, 'Encrypted sRGB'),
    'raw_key_material_exposed' => is_string($encryptedEncoded)
        && (str_contains($encryptedEncoded, 'DEADBEEF') || str_contains($encryptedEncoded, 'CAFEFEED')),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Metadata Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF metadata is review-gated before WordPress import. Only explicitly unencrypted XMP metadata is preserved; encrypted Info and OutputIntent strings remain suppressed.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:metadata-security-priority ' . htmlspecialchars(json_encode([
    'encrypted_document_policy' => $encryptedPolicy,
    'unencrypted_metadata_stream_policy' => $unencryptedPolicy,
    'trusted_title' => $unencryptedMetadataStream['title'] ?? null,
    'text_extraction' => 'blocked_without_decryption',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
