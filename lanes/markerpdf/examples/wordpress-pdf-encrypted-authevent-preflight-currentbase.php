<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress AuthEvent encrypted text leak) Tj ET';
$ownerKey = str_repeat('W', 32);
$userKey = str_repeat('P', 32);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <" . strtoupper(bin2hex($ownerKey)) . ">"
    . " /U <" . strtoupper(bin2hex($userKey)) . ">"
    . " /P -44 /EncryptMetadata true"
    . " /CF <<"
    . " /DocStreams << /CFM /AESV2 /Length 16 >>"
    . " /EmbeddedOnly << /CFM /AESV2 /AuthEvent /EFOpen /Length 16 >>"
    . " >>"
    . " /StmF /DocStreams /StrF /EmbeddedOnly /EFF /EmbeddedOnly >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$review = $preflight['crypt_filter_content_review'] ?? [];
$encoded = json_encode([$metadata, $preflight], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-encrypted-authevent-preflight-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-encrypted-authevent-preflight-currentbase',
    'native_boundary' => 'crypt-filter AuthEvent defaults and role mismatches are surfaced before encrypted WordPress import',
    'encrypted_text_blocked' => (new PdfTextExtractor())->extractPlainText($pdf) === '',
    'permission_policy' => $preflight['permission_preflight']['policy'] ?? null,
    'doc_stream_auth_event' => $metadata['encryption']['crypt_filters']['DocStreams']['auth_event'] ?? null,
    'doc_stream_auth_event_defaulted' => $metadata['encryption']['crypt_filters']['DocStreams']['auth_event_defaulted'] ?? null,
    'auth_event_statuses' => $review['auth_event_statuses'] ?? [],
    'auth_event_defaulted_role_names' => $review['auth_event_defaulted_role_names'] ?? [],
    'auth_event_mismatch_role_names' => $review['auth_event_mismatch_role_names'] ?? [],
    'raw_key_material_exposed' => is_string($encoded) && (
        str_contains($encoded, $ownerKey)
        || str_contains($encoded, $userKey)
        || str_contains($encoded, strtoupper(bin2hex($ownerKey)))
        || str_contains($encoded, strtoupper(bin2hex($userKey)))
    ),
    'encrypted_text_exposed' => is_string($encoded) && str_contains($encoded, $content),
    'executes_decryption' => false,
    'executes_permission_enforcement' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF AuthEvent Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'Encrypted PDF text remains blocked. The import preflight records crypt-filter authorization events, including default DocOpen behavior and EFOpen filters selected for document content.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:encrypted-authevent-preflight ' . htmlspecialchars(json_encode([
    'permission' => [
        'policy' => $preflight['permission_preflight']['policy'] ?? null,
        'content_extraction_boundary' => $preflight['permission_preflight']['content_extraction_boundary'] ?? null,
    ],
    'crypt_filter_auth_event_review' => [
        'statuses' => $review['auth_event_statuses'] ?? [],
        'defaulted_roles' => $review['auth_event_defaulted_role_names'] ?? [],
        'defaulted_filters' => $review['auth_event_defaulted_filter_names'] ?? [],
        'mismatch_roles' => $review['auth_event_mismatch_role_names'] ?? [],
        'mismatch_filters' => $review['auth_event_mismatch_filter_names'] ?? [],
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
