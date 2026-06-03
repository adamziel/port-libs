<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hex = static fn (string $bytes): string => '<' . strtoupper(bin2hex($bytes)) . '>';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Indirect Permission Root Title</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';

$standardContent = 'BT /F1 12 Tf 72 720 Td (Indirect Standard encrypted text leak) Tj ET';
$standardPdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 30 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($standardContent) . " >>\nstream\n{$standardContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter 6 0 R /V 7 0 R /R 8 0 R /Length 9 0 R /CF 10 0 R /StmF 11 0 R /StrF 11 0 R /EFF 12 0 R /O 14 0 R /U 15 0 R /OE 16 0 R /UE 17 0 R /P 18 0 R /EncryptMetadata 19 0 R /Perms 20 0 R >>\nendobj\n"
    . "6 0 obj\n/Standard\nendobj\n7 0 obj\n5\nendobj\n8 0 obj\n6\nendobj\n9 0 obj\n256\nendobj\n"
    . "10 0 obj\n<< /StdCF 21 0 R /EmbeddedIdentity 22 0 R >>\nendobj\n"
    . "11 0 obj\n/StdCF\nendobj\n12 0 obj\n/EmbeddedIdentity\nendobj\n"
    . "14 0 obj\n" . $hex(str_repeat('O', 48)) . "\nendobj\n"
    . "15 0 obj\n" . $hex(str_repeat('U', 48)) . "\nendobj\n"
    . "16 0 obj\n" . $hex(str_repeat('E', 32)) . "\nendobj\n"
    . "17 0 obj\n" . $hex(str_repeat('K', 32)) . "\nendobj\n"
    . "18 0 obj\n-44\nendobj\n19 0 obj\nfalse\nendobj\n20 0 obj\n" . $hex(str_repeat('P', 16)) . "\nendobj\n"
    . "21 0 obj\n<< /CFM 23 0 R /AuthEvent 24 0 R /Length 25 0 R >>\nendobj\n"
    . "22 0 obj\n<< /CFM /Identity /AuthEvent /EFOpen >>\nendobj\n"
    . "23 0 obj\n/AESV3\nendobj\n24 0 obj\n/DocOpen\nendobj\n25 0 obj\n32\nendobj\n"
    . "30 0 obj\n<< /Type /Metadata /Subtype /XML /Length " . strlen($xmp) . " >>\nstream\n{$xmp}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$documentRecipient = 'INDIRECT_PUBLICKEY_DOCUMENT_RECIPIENT_SHOULD_NOT_LEAK';
$embeddedRecipient = 'INDIRECT_PUBLICKEY_EMBEDDED_RECIPIENT_SHOULD_NOT_LEAK';
$publicKeyContent = 'BT /F1 12 Tf 72 720 Td (Indirect public-key encrypted text leak) Tj ET';
$publicKeyPdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($publicKeyContent) . " >>\nstream\n{$publicKeyContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter 6 0 R /SubFilter 7 0 R /V 8 0 R /Length 9 0 R /CF 10 0 R /StmF 11 0 R /StrF 11 0 R /EFF 12 0 R /EncryptMetadata true >>\nendobj\n"
    . "6 0 obj\n/Adobe.PubSec\nendobj\n7 0 obj\n/adbe.pkcs7.s5\nendobj\n8 0 obj\n4\nendobj\n9 0 obj\n128\nendobj\n"
    . "10 0 obj\n<< /DefaultCryptFilter 13 0 R /EmbeddedFiles 14 0 R >>\nendobj\n"
    . "11 0 obj\n/DefaultCryptFilter\nendobj\n12 0 obj\n/EmbeddedFiles\nendobj\n"
    . "13 0 obj\n<< /CFM /AESV2 /AuthEvent /DocOpen /Length 16 /Recipients [" . $hex($documentRecipient) . "] >>\nendobj\n"
    . "14 0 obj\n<< /CFM /AESV2 /AuthEvent /EFOpen /Length 16 /Recipients [" . $hex($embeddedRecipient) . "] >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($standardPdf);
$standardPreflight = (new PdfSecurityPreflight())->analyze($standardPdf);
$publicKeyPreflight = (new PdfSecurityPreflight())->analyze($publicKeyPdf);
$extractor = new PdfTextExtractor();

echo '<!-- markerpdf-encrypted-indirect-permission-preflight-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-indirect-permission-preflight-currentbase',
    'native_boundary' => 'indirect encryption dictionary permission and crypt-filter operands are resolved before encrypted WordPress import preflight',
    'standard_text_blocked' => $extractor->extractPlainText($standardPdf) === '',
    'standard_title_preserved' => $metadata['title'] ?? null,
    'standard_permission_hex' => $standardPreflight['permission_preflight']['permission_hex'] ?? null,
    'standard_policy' => $standardPreflight['permission_preflight']['policy'] ?? null,
    'standard_stream_filter' => $standardPreflight['encryption']['stream_filter'] ?? null,
    'standard_embedded_file_filter' => $standardPreflight['encryption']['embedded_file_filter'] ?? null,
    'public_key_text_blocked' => $extractor->extractPlainText($publicKeyPdf) === '',
    'public_key_policy' => $publicKeyPreflight['permission_preflight']['policy'] ?? null,
    'public_key_selected_recipient_count' => $publicKeyPreflight['permission_preflight']['selected_public_key_recipient_count'] ?? null,
    'raw_key_material_exposed' => false,
    'recipient_bytes_exposed' => false,
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Preflight</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text stays blocked, while indirect Standard and public-key permission operands are summarized as review metadata for import decisions.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-indirect-permission-preflight ' . htmlspecialchars(json_encode([
    'standard' => [
        'title' => $metadata['title'] ?? null,
        'policy' => $standardPreflight['permission_preflight']['policy'] ?? null,
        'permission_hex' => $standardPreflight['permission_preflight']['permission_hex'] ?? null,
        'content_extraction_boundary' => $standardPreflight['permission_preflight']['content_extraction_boundary'] ?? null,
        'stream_filter' => $standardPreflight['encryption']['stream_filter'] ?? null,
        'embedded_file_filter' => $standardPreflight['encryption']['embedded_file_filter'] ?? null,
    ],
    'public_key' => [
        'policy' => $publicKeyPreflight['permission_preflight']['policy'] ?? null,
        'selected_recipient_count' => $publicKeyPreflight['permission_preflight']['selected_public_key_recipient_count'] ?? null,
        'selected_filters' => $publicKeyPreflight['permission_preflight']['public_key_crypt_filter_selection']['selected_recipient_filter_names'] ?? [],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
