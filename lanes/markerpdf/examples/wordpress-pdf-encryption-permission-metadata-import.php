<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encryptedContent = 'BT /F1 12 Tf 72 720 Td (Encrypted cleartext leak) Tj ET';
$permsBytes = 'perm-check-16-by';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P -62956 /EncryptMetadata false"
    . " /CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> /EmbeddedFiles << /CFM /V2 /AuthEvent /EFOpen /Length 5 >> >>"
    . " /StmF /StdCF /StrF /StdCF /EFF /EmbeddedFiles /Perms <" . strtoupper(bin2hex($permsBytes)) . "> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encryption = $metadata['encryption'] ?? [];

echo '<!-- markerpdf-encryption-permission-metadata-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF trailer /Encrypt Standard security-handler permissions exposed as review metadata without decryption',
    'source' => $metadata['source'],
    'encrypted_text_blocked' => $plainText === '',
    'filter' => $encryption['filter'] ?? null,
    'version' => $encryption['version'] ?? null,
    'revision' => $encryption['revision'] ?? null,
    'key_length_bits' => $encryption['key_length_bits'] ?? null,
    'permission_hex' => $encryption['standard_permissions']['hex'] ?? null,
    'allowed' => $encryption['standard_permissions']['allowed'] ?? [],
    'denied' => $encryption['standard_permissions']['denied'] ?? [],
    'perms_hash_present' => isset($encryption['perms']['sha256']),
    'raw_owner_user_keys_exposed' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Import Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo '<!-- markerpdf:encryption-permissions ' . htmlspecialchars(json_encode([
    'filter' => $encryption['filter'] ?? null,
    'algorithm' => $encryption['algorithm'] ?? null,
    'revision_label' => $encryption['revision_label'] ?? null,
    'encrypt_metadata' => $encryption['encrypt_metadata'] ?? null,
    'stream_filter' => $encryption['stream_filter'] ?? null,
    'string_filter' => $encryption['string_filter'] ?? null,
    'embedded_file_filter' => $encryption['embedded_file_filter'] ?? null,
    'permission_hex' => $encryption['standard_permissions']['hex'] ?? null,
    'allowed' => $encryption['standard_permissions']['allowed'] ?? [],
    'denied' => $encryption['standard_permissions']['denied'] ?? [],
    'content_extraction' => 'blocked_without_decryption',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
