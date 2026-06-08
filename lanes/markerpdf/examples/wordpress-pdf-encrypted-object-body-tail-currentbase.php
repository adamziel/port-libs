<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\CorePdfConverter;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (WordPress Encrypt object-body tail leak) Tj ET';
$ownerValidation = str_repeat('O', 32);
$userValidation = str_repeat('U', 32);
$ownerHex = strtoupper(bin2hex($ownerValidation));
$userHex = strtoupper(bin2hex($userValidation));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128"
    . " /O <{$ownerHex}> /U <{$userHex}> /P -44 /EncryptMetadata true >> 6 0 R\nendobj\n"
    . "6 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /O <DEADBEEF> /U <CAFEFEED> /P 16 /EncryptMetadata true >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF";

$path = sys_get_temp_dir() . '/markerpdf-encrypted-object-body-tail-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, $pdf);

$pipelineCalls = 0;
try {
    $security = (new PdfSecurityPreflight())->analyze($pdf);
    $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
    $conversion = (new CorePdfConverter())->convertWithSuppliedPages(
        $path,
        [['pnum' => 0, 'blocks' => []]],
        [],
        static function () use (&$pipelineCalls): array {
            $pipelineCalls++;

            return ['text' => 'should not run', 'images' => [], 'metadata' => []];
        }
    );
} finally {
    @unlink($path);
}

$encryption = is_array($security['encryption'] ?? null) ? $security['encryption'] : [];
$permission = is_array($security['permission_preflight'] ?? null) ? $security['permission_preflight'] : [];
$encoded = json_encode([$security, $conversion], JSON_UNESCAPED_SLASHES);
$rawMaterialExposed = is_string($encoded)
    && (
        str_contains($encoded, $content)
        || str_contains($encoded, $ownerValidation)
        || str_contains($encoded, $userValidation)
        || str_contains($encoded, $ownerHex)
        || str_contains($encoded, $userHex)
        || str_contains($encoded, 'DEADBEEF')
        || str_contains($encoded, 'CAFEFEED')
        || str_contains($encoded, 'FFFFFFD4')
    );

if ($plainText !== '') {
    throw new RuntimeException('Expected Encrypt object-body tail fixture text to stay blocked.');
}
if (($security['import_decision'] ?? null) !== 'block_encrypted_content_review_security_metadata') {
    throw new RuntimeException('Expected WordPress import to block malformed encrypted metadata.');
}
if (($encryption['encrypt_operand_status'] ?? null) !== 'encrypt_dictionary_trailing_operand_review') {
    throw new RuntimeException('Expected referenced Encrypt object body tail to be reviewed.');
}
if (($permission['policy'] ?? null) !== 'permissions_unknown_blocked_without_decryption'
    || ($permission['permission_bits_reliable'] ?? null) !== false
    || ($permission['permission_hex'] ?? null) !== null
) {
    throw new RuntimeException('Expected malformed Encrypt object body to suppress decoded Standard permissions.');
}
if (($conversion['context']['stage'] ?? null) !== 'encrypted-pdf-preflight' || $pipelineCalls !== 0) {
    throw new RuntimeException('Expected encrypted converter preflight to short-circuit before the supplied-page pipeline.');
}
if ($rawMaterialExposed) {
    throw new RuntimeException('Expected encrypted content and Standard auth material to remain redacted.');
}

echo json_encode([
    'scenario' => 'wordpress-pdf-encrypted-object-body-tail-currentbase',
    'native_boundary' => 'referenced /Encrypt object bodies with trailing top-level operands fail closed before permission preflight',
    'encrypted' => $security['encrypted'] ?? null,
    'import_decision' => $security['import_decision'] ?? null,
    'security_stage' => $security['text_extraction_policy'] ?? null,
    'conversion_stage' => $conversion['context']['stage'] ?? null,
    'permission_policy' => $permission['policy'] ?? null,
    'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
    'permission_hex' => $permission['permission_hex'] ?? null,
    'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
    'permission_bits_reliable' => $permission['permission_bits_reliable'] ?? null,
    'encrypt_dictionary_resolved' => $encryption['encrypt_dictionary_resolved'] ?? null,
    'encrypt_operand_status' => $encryption['encrypt_operand_status'] ?? null,
    'encrypt_trailing_operand_shape' => $encryption['encrypt_trailing_operand_shape'] ?? null,
    'encrypt_trailing_operand_preview' => $encryption['encrypt_trailing_operand_preview'] ?? null,
    'pipeline_calls' => $pipelineCalls,
    'should_queue_models' => $conversion['metadata']['pdf_security']['should_queue_models'] ?? null,
    'raw_material_exposed' => false,
    'executes_decryption' => $security['executes_decryption'] ?? null,
    'executes_permission_enforcement' => $security['executes_permission_enforcement'] ?? null,
    'executes_python_or_models' => $security['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $security['executes_external_pdf_tools'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
